<?php
declare(strict_types=1);

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

namespace App\Command;

use App\Geocoder\OriginPayloadHelper;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;

/**
 * StripOriginPii command.
 *
 * One-off backfill for the map question ("origine-spostamenti", config key
 * `Questions.origine_spostamenti` — id 340 on 5t/Brescia/RER, 302 on
 * another brand; ALWAYS read from config, never hardcoded).
 *
 * Removes `origin.address`/`lat`/`lon` from every existing `answers.answer`
 * row for that question, after first:
 *   (a) backing up the untouched row into `backup_answers_origin_strip`,
 *   (b) creating an `Origins` row for any answer whose (user_id, survey_id)
 *       has none yet (orphans — otherwise the coordinates are lost forever),
 *   (c) recovering a missing `geohash` on an existing `Origins` row when the
 *       answer payload still has usable coordinates.
 *
 * Recovery always runs BEFORE the strip. Re-running is safe: already-backed-up
 * rows, already-recovered origins, and already-stripped answers are skipped.
 *
 * `--restore-first` runs an additional phase, right after the backup table is
 * ensured but before recovery/stripping: it restores every in-scope
 * `answers.answer` row from its `backup_answers_origin_strip` backup (an
 * `UPDATE ... JOIN`, scoped the same way the rest of the command is scoped —
 * i.e. respecting `--survey` when given). This is only useful to re-derive
 * `origin.geohash` (see `OriginPayloadHelper::stripOriginPii()`) into answers
 * that were stripped before that field existed: restoring first puts the
 * original lat/lon back, and the normal strip pass that follows in the same
 * run then re-derives a fresh geohash from each row's own real coordinates.
 * The restore runs inside the SAME transaction as the rest of the command, so
 * `--dry-run --restore-first` rolls the restore back too, and a failure
 * anywhere later in the run also undoes the restore.
 *
 * Usage:
 *   bin/cake strip_origin_pii --dry-run
 *   bin/cake strip_origin_pii --survey=1339
 *   bin/cake strip_origin_pii --batch=200
 *   bin/cake strip_origin_pii --restore-first --dry-run
 *   bin/cake strip_origin_pii
 *
 * Scale note: on a table with a very large number of in-scope answers (e.g.
 * `5t_moma_angelcake` production scale), this command processes ONE
 * `survey_id` per transaction rather than wrapping every survey in a single
 * transaction. This bounds transaction/lock size and, critically, makes a
 * re-run resumable: a per-survey failure (timeout, OOM-kill, connection
 * drop) rolls back only that survey and moves on — already-committed
 * surveys stay done and are skipped as no-ops (`already_stripped`) on the
 * next invocation, instead of forcing a full redo of every survey from
 * scratch. See docs/StripOriginPii.md.
 */
class StripOriginPiiCommand extends Command
{
    private const BACKUP_TABLE = 'backup_answers_origin_strip';

    protected bool $dryRun = false;

    /**
     * @var array<string,int>
     */
    protected array $counters = [
        'surveys_total' => 0,
        'surveys_failed' => 0,
        'answers_total' => 0,
        'backed_up' => 0,
        'backup_already_present' => 0,
        'orphans_recovered' => 0,
        'orphans_unrecoverable' => 0,
        'geohash_recovered' => 0,
        'duplicate_pairs_found' => 0,
        'restored' => 0,
        'stripped' => 0,
        'already_stripped' => 0,
        'malformed_json' => 0,
        'malformed_json_fixed' => 0,
        'malformed_json_unfixable' => 0,
    ];

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to configure.
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Strip origin.address/lat/lon from map-question (Questions.origine_spostamenti) ' .
                'answers, after backing up and recovering any missing Origins data. Idempotent.'
            )
            ->addOption('dry-run', [
                'help' => 'Run everything inside a transaction then roll back (no persistent change).',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('survey', [
                'help' => 'Limit to a single survey_id.',
            ])
            ->addOption('batch', [
                'help' => 'Rows fetched per batch (default 500).',
                'default' => '500',
            ])
            ->addOption('restore-first', [
                'help' => 'Before stripping, restore every in-scope answer from ' .
                    'backup_answers_origin_strip (same transaction — rolled back ' .
                    'together with everything else on --dry-run or on failure). ' .
                    'Useful to re-derive origin.geohash into answers stripped ' .
                    'before that field existed.',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->dryRun = (bool)$args->getOption('dry-run');
        $surveyId = $args->getOption('survey') ? (int)$args->getOption('survey') : null;
        $batchSize = max(1, (int)$args->getOption('batch'));
        $restoreFirst = (bool)$args->getOption('restore-first');

        $questionId = Configure::read('Questions.origine_spostamenti');
        if (empty($questionId)) {
            $io->err('<error>Configure key Questions.origine_spostamenti is not set — aborting.</error>');

            return self::CODE_ERROR;
        }

        $io->out('<info>StripOriginPii</info>' . ($this->dryRun ? ' (DRY-RUN)' : '') . " — question_id=$questionId");

        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $this->Origins = TableRegistry::getTableLocator()->get('Origins');
        $connection = ConnectionManager::get('default');

        // Idempotent (checked via information_schema) and cheap — DDL is not
        // transactional in MySQL anyway, so this is deliberately done once,
        // outside any per-survey transaction below.
        $this->ensureBackupTable($io, $connection);

        $surveyIds = $this->resolveSurveyIds($connection, $questionId, $surveyId);
        $this->counters['surveys_total'] = count($surveyIds);
        $io->out('  ' . count($surveyIds) . ' survey(s) in scope.');

        foreach ($surveyIds as $sid) {
            try {
                $connection->begin();

                if ($restoreFirst) {
                    $this->restoreFromBackup($io, $connection, $questionId, $sid);
                }

                $query = $this->Answers->find()
                    ->where(['question_id' => $questionId, 'survey_id' => $sid]);
                $this->counters['answers_total'] += $query->count();

                $duplicatePairs = $this->findDuplicateOriginPairs($io, $connection, $questionId, $sid);
                $this->counters['duplicate_pairs_found'] += count($duplicatePairs);

                foreach ($query->all()->chunk($batchSize) as $chunk) {
                    foreach ($chunk as $answer) {
                        $this->processAnswer($io, $answer, $duplicatePairs);
                    }
                }

                if ($this->dryRun) {
                    $connection->rollback();
                } else {
                    $connection->commit();
                }
            } catch (\Throwable $e) {
                if ($connection->inTransaction()) {
                    $connection->rollback();
                }
                $io->err("  <error>survey_id=$sid aborted: " . $e->getMessage() . '</error>');
                $io->err($e->getTraceAsString());
                $this->counters['surveys_failed']++;
                // Don't abort the whole run: a failure on one survey (timeout,
                // OOM-kill, connection drop) must not force re-processing every
                // already-committed survey from scratch on a huge table.
                continue;
            }
        }

        if ($this->dryRun) {
            $io->out('<warning>DRY-RUN: every survey transaction was rolled back, no changes persisted.</warning>');
        } elseif ($this->counters['surveys_failed'] > 0) {
            $io->err(
                '<error>Completed with ' . $this->counters['surveys_failed'] . ' failed survey(s) — ' .
                're-run to retry them (already-committed surveys are skipped as no-ops).</error>'
            );
        } else {
            $io->out('<success>Committed (one transaction per survey).</success>');
        }

        $this->printSummary($io);

        return $this->counters['surveys_failed'] > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Resolve the list of survey ids in scope: either the single `--survey`
     * value, or every distinct `survey_id` that has at least one in-scope
     * answer — so each can be processed (and committed) independently.
     *
     * @param \Cake\Database\Connection $connection The DB connection.
     * @param int $questionId The map question id.
     * @param int|null $surveyId Optional `--survey` scope.
     * @return array<int>
     */
    protected function resolveSurveyIds($connection, $questionId, ?int $surveyId): array
    {
        if ($surveyId !== null) {
            return [$surveyId];
        }

        $rows = $connection->execute(
            'SELECT DISTINCT survey_id FROM answers WHERE question_id = :qid ORDER BY survey_id ASC',
            ['qid' => $questionId]
        )->fetchAll('assoc');

        return array_map(static fn ($row) => (int)$row['survey_id'], $rows);
    }

    /**
     * Create the backup table if it doesn't already exist.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \Cake\Database\Connection $connection The DB connection.
     * @return void
     */
    protected function ensureBackupTable(ConsoleIo $io, $connection): void
    {
        $row = $connection->execute(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            ['t' => self::BACKUP_TABLE]
        )->fetch('assoc');

        if ((int)($row['c'] ?? 0) > 0) {
            $io->out('  backup table already exists.');

            return;
        }

        $io->out('  creating backup table ' . self::BACKUP_TABLE);
        $connection->execute(
            'CREATE TABLE ' . self::BACKUP_TABLE . ' (' .
            'answer_id INT NOT NULL PRIMARY KEY, ' .
            'answer LONGTEXT NULL, ' .
            'stripped_at DATETIME NOT NULL' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Restore every in-scope answer's `answer` column from its backup, before
     * the normal recover/strip loop runs. Scoped the same way the rest of the
     * command is scoped (optionally by `--survey`). Runs inside the caller's
     * already-open transaction — no transaction handling here.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \Cake\Database\Connection $connection The DB connection.
     * @param int $questionId The map question id.
     * @param int|null $surveyId Optional survey scope.
     * @return void
     */
    protected function restoreFromBackup(ConsoleIo $io, $connection, $questionId, ?int $surveyId): void
    {
        $io->out('  restoring answers from backup_answers_origin_strip...');

        $sql = 'UPDATE answers a ' .
            'JOIN ' . self::BACKUP_TABLE . ' b ON b.answer_id = a.id ' .
            'SET a.answer = b.answer ' .
            'WHERE a.question_id = :qid';
        $params = ['qid' => $questionId];
        if ($surveyId !== null) {
            $sql .= ' AND a.survey_id = :sid';
            $params['sid'] = $surveyId;
        }

        $affected = $connection->execute($sql, $params)->rowCount();
        $this->counters['restored'] = $affected;

        $io->out("  restored $affected answer(s) from backup.");
    }

    /**
     * Find (user_id, survey_id) pairs with more than one Origins row, restricted
     * to pairs that actually have a q340 answer. Returns a map keyed by
     * "user_id|survey_id" => lowest origin id (the only one recovery may touch).
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \Cake\Database\Connection $connection The DB connection.
     * @param int $questionId The map question id.
     * @param int|null $surveyId Optional survey scope.
     * @return array<string,int>
     */
    protected function findDuplicateOriginPairs(ConsoleIo $io, $connection, $questionId, ?int $surveyId): array
    {
        $sql = 'SELECT o.user_id, o.survey_id, MIN(o.id) AS keep_id, COUNT(*) AS cnt ' .
            'FROM origins o ' .
            'JOIN answers a ON a.user_id = o.user_id AND a.survey_id = o.survey_id ' .
            'WHERE a.question_id = :qid';
        $params = ['qid' => $questionId];
        if ($surveyId !== null) {
            $sql .= ' AND a.survey_id = :sid';
            $params['sid'] = $surveyId;
        }
        $sql .= ' GROUP BY o.user_id, o.survey_id HAVING COUNT(*) > 1';

        $rows = $connection->execute($sql, $params)->fetchAll('assoc');

        $map = [];
        foreach ($rows as $row) {
            $key = $row['user_id'] . '|' . $row['survey_id'];
            $map[$key] = (int)$row['keep_id'];
            $io->out(
                "  <warning>duplicate origins</warning> for user={$row['user_id']} survey={$row['survey_id']}" .
                " ({$row['cnt']} rows) — recovery will only touch origin id {$row['keep_id']}"
            );
        }

        return $map;
    }

    /**
     * Backup + recover + strip a single answer row.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Model\Entity\Answer $answer The answer entity to process.
     * @param array<string,int> $duplicatePairs Map from findDuplicateOriginPairs().
     * @return void
     */
    protected function processAnswer(ConsoleIo $io, $answer, array $duplicatePairs): void
    {
        $this->backupAnswer($io, $answer);

        $decoded = $this->decodeAnswerDefensively($io, $answer);
        if ($decoded === null) {
            // Malformed JSON we could not repair — do not touch this row further.
            return;
        }

        $origin = is_object($decoded) ? ($decoded->origin ?? null) : ($decoded['origin'] ?? null);

        $this->recoverOrigin($io, $answer, $origin, $duplicatePairs);

        $stripped = OriginPayloadHelper::stripOriginPii($decoded);
        $strippedJson = json_encode($stripped);
        $currentJson = is_string($answer->answer) ? $answer->answer : json_encode($decoded);

        if ($strippedJson === $currentJson) {
            $this->counters['already_stripped']++;

            return;
        }

        $answer->answer = $stripped;
        if (!$this->Answers->save($answer)) {
            $io->err("  <error>failed to save stripped answer id={$answer->id}: " .
                json_encode($answer->getErrors()) . '</error>');

            return;
        }
        $this->counters['stripped']++;
    }

    /**
     * Insert a pristine copy of the answer into the backup table, unless
     * already present (idempotent re-run).
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Model\Entity\Answer $answer The answer entity to back up.
     * @return void
     */
    protected function backupAnswer(ConsoleIo $io, $answer): void
    {
        $connection = ConnectionManager::get('default');
        $existing = $connection->execute(
            'SELECT 1 FROM ' . self::BACKUP_TABLE . ' WHERE answer_id = :id',
            ['id' => $answer->id]
        )->fetch();

        if ($existing) {
            $this->counters['backup_already_present']++;

            return;
        }

        $rawAnswer = is_string($answer->answer) ? $answer->answer : json_encode($answer->answer);
        $connection->execute(
            'INSERT INTO ' . self::BACKUP_TABLE . ' (answer_id, answer, stripped_at) VALUES (:id, :answer, :now)',
            ['id' => $answer->id, 'answer' => $rawAnswer, 'now' => FrozenTime::now()->toDateTimeString()]
        );
        $this->counters['backed_up']++;
    }

    /**
     * Decode the answer's raw JSON, defensively repairing the one known class
     * of malformation: an unescaped double-quote inside `origin.address`
     * (e.g. `"address":"corso racconigi 238/!""`), which is exactly the PII
     * this command removes — repairing it and stripping is strictly an
     * improvement over leaving invalid JSON in the column.
     *
     * `AnswersTable` casts `answer` as the `json` type, so `JsonType::toPHP()`
     * (`json_decode($value, true)`) already ran during hydration — for a
     * malformed row that collapses `$answer->answer` to `null` and the
     * original raw string is gone. This method re-reads the raw column value
     * directly via SQL whenever the hydrated value is `null` but the row is
     * not genuinely empty, so the repair has real text to work with.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Model\Entity\Answer $answer The answer entity to decode.
     * @return object|array|null Null when the row could not be repaired.
     */
    protected function decodeAnswerDefensively(ConsoleIo $io, $answer)
    {
        $decoded = $answer->answer;
        if (is_array($decoded) || is_object($decoded)) {
            // Hydrated successfully — JsonType already decoded valid JSON.
            return $decoded;
        }

        if ($decoded !== null) {
            // Some other scalar (shouldn't happen for this question, but not
            // our concern to repair) — nothing to do.
            return $decoded;
        }

        // $answer->answer is null: either the row is genuinely NULL/empty, or
        // JsonType's json_decode() silently failed on malformed JSON. Re-read
        // the raw column to tell these apart and, in the latter case, have
        // the actual text to repair.
        $raw = ConnectionManager::get('default')->execute(
            'SELECT answer FROM answers WHERE id = :id',
            ['id' => $answer->id]
        )->fetch('assoc')['answer'] ?? null;

        if ($raw === null || $raw === '' || $raw === 'null') {
            return null;
        }

        $this->counters['malformed_json']++;
        $io->out("  <warning>malformed JSON in answer id={$answer->id}, attempting repair</warning>");

        // Repair heuristic: an unescaped double-quote directly inside the
        // "address":"..." value, bounded by the next known key
        // ("postal_code") so a lone stray quote doesn't need balanced
        // escaping logic — everything between the opening quote and the
        // literal `,"postal_code"` is the (possibly quote-laden) address text.
        $repaired = preg_replace_callback(
            '/"address"\s*:\s*"(.*)"\s*,\s*"postal_code"/s',
            static function ($m) {
                return '"address":"' . str_replace('"', '\\"', $m[1]) . '","postal_code"';
            },
            $raw,
            1
        );

        $decoded = $repaired !== null ? json_decode($repaired) : null;

        if ($decoded === null) {
            $this->counters['malformed_json_unfixable']++;
            $io->err("  <error>could not repair malformed JSON for answer id={$answer->id} — left untouched</error>");

            return null;
        }

        $this->counters['malformed_json_fixed']++;

        return $decoded;
    }

    /**
     * Ensure an Origins row exists and has a geohash, when the payload has
     * recoverable coordinates. Handles both the "orphan" case (no Origins row
     * at all) and the "geohash missing" case (row exists, geohash is null).
     * Honors the duplicate-pair map: only the designated "keep" id is touched.
     *
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @param \App\Model\Entity\Answer $answer The answer entity the origin belongs to.
     * @param object|array|null $origin The decoded `origin` sub-object.
     * @param array<string,int> $duplicatePairs Map from findDuplicateOriginPairs().
     * @return void
     */
    protected function recoverOrigin(ConsoleIo $io, $answer, $origin, array $duplicatePairs): void
    {
        if (empty($origin)) {
            return;
        }

        $rowData = OriginPayloadHelper::extractOriginRowData($origin);
        if (empty($rowData['geohash'] ?? null)) {
            // No usable coordinates in this payload — nothing to recover.
            return;
        }

        $pairKey = $answer->user_id . '|' . $answer->survey_id;

        $existing = $this->Origins->find()
            ->where([
                'user_id' => $answer->user_id,
                'survey_id' => $answer->survey_id,
            ])
            ->order(['id' => 'ASC'])
            ->first();

        if (empty($existing)) {
            // Orphan: no Origins row at all for this (user_id, survey_id).
            $originEntity = $this->Origins->newEntity(array_merge($rowData, [
                'user_id' => $answer->user_id,
                'survey_id' => $answer->survey_id,
            ]));
            if (!$this->Origins->save($originEntity)) {
                $io->err("  <error>failed to create orphan origin for answer id={$answer->id}: " .
                    json_encode($originEntity->getErrors()) . '</error>');
                $this->counters['orphans_unrecoverable']++;

                return;
            }
            $this->counters['orphans_recovered']++;

            return;
        }

        if (isset($duplicatePairs[$pairKey]) && $duplicatePairs[$pairKey] !== $existing->id) {
            // A duplicate pair exists and this isn't the designated "keep" row
            // — skip, already logged once by findDuplicateOriginPairs().
            return;
        }

        if (empty($existing->geohash)) {
            $existing->geohash = $rowData['geohash'];
            if (!$this->Origins->save($existing)) {
                $io->err("  <error>failed to recover geohash for origin id={$existing->id}: " .
                    json_encode($existing->getErrors()) . '</error>');

                return;
            }
            $this->counters['geohash_recovered']++;
        }
    }

    /**
     * @param \Cake\Console\ConsoleIo $io The console io.
     * @return void
     */
    protected function printSummary(ConsoleIo $io): void
    {
        $io->out('');
        $io->out('<info>Summary</info>');
        foreach ($this->counters as $key => $value) {
            $io->out(str_pad("  $key", 32) . $value);
        }
    }
}

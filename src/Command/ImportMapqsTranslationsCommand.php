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
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * ImportMapqsTranslations command.
 *
 * Adds a per-language UI-label block to the map-question `mapqs` structure
 * embedded directly inside `questions.long_description` / `questions_surveys.long_description`
 * (a JSON column). This is a DIFFERENT storage mechanism from
 * `ImportQuestionsTranslationsCommand` (which writes plain description/long_description/options
 * strings into the `i18n` table via the Translate behavior) - `mapqs.<lang>` is never
 * translated through Translate/i18n at all (confirmed: the i18n row for a map question's
 * long_description is always empty). `MapQuestionEdit.vue`/`MapQuestion.vue` read
 * `long_description.mapqs[lang]` directly off the canonical column, in whichever language
 * the survey admin/respondent has selected.
 *
 * Mirrors the raw-SQL JSON_SET pattern already used by
 * `config/Migrations/20251006000100_AddMapqsInsertProvinceToOrigineSpostamenti.php` for the
 * same reason that migration bypasses the ORM entity layer: `long_description`'s entity
 * docblock says `string|null`, and going through `$table->save($entity)` risks the Translate
 * behavior's marshalling interfering with a column it also (separately, for the SAME field
 * name) manages via i18n. A direct `JSON_SET(long_description, '$.mapqs.<lang>', CAST(:json AS JSON))`
 * touches ONLY that one key, leaving `mapqs.it`, `mapqs.en`, `mapqs.enabled`, and any other
 * sibling data (e.g. `description`/`tipiSpostamento` for map-gal questions) completely untouched.
 *
 * Safety guard: every UPDATE requires `JSON_CONTAINS_PATH(long_description, 'one', '$.mapqs')`
 * to already be true for that row - this command only ADDS a language block to an EXISTING
 * mapqs structure, it never creates one from scratch on a row that doesn't have one (a row
 * without `$.mapqs` isn't a map-question-with-per-language-UI-config at all, and silently
 * fabricating that structure there would be a different, much riskier operation than this
 * command is meant to do).
 *
 * Idempotent: re-running the same file just overwrites `mapqs.<lang>` with the same content
 * (JSON_SET replaces the key if already present). Per-row error handling: one bad row does not
 * abort the rest of the file.
 *
 * Input file: JSON with top-level keys `questions` (keyed by Questions.id) and
 * `questions_surveys` (keyed by QuestionsSurveys.id - the row's own id, NOT question_id/survey_id).
 * Each entry: `{ "mapqs_<locale>": { <full UI-label dictionary, same keys as the row's
 * existing mapqs.it/mapqs.en> } }` - the key name is locale-specific (`mapqs_de`, `mapqs_fr`, ...)
 * and must match whatever `--locale` is passed.
 *
 * Options:
 *   file                   Required argument - path to the JSON input file.
 *   --locale (default: de) Locale code to import; also selects the `mapqs_<locale>` input key.
 *   --dry-run              Roll back the transaction at the end (no persistent change).
 *   --verbose              Print a per-row trace.
 */
class ImportMapqsTranslationsCommand extends Command
{
    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $verbose = false;

    /**
     * @var string
     */
    protected $locale = 'de';

    /**
     * @var array<string,int>
     */
    protected $counters = [
    'questions_updated' => 0,
    'questions_skipped' => 0,
    'questions_unchanged' => 0,
    'questions_mismatch' => 0,
    'questions_failed' => 0,
    'questions_surveys_updated' => 0,
    'questions_surveys_skipped' => 0,
    'questions_surveys_unchanged' => 0,
    'questions_surveys_mismatch' => 0,
    'questions_surveys_failed' => 0,
    ];

    /**
     * @var array<string>
     */
    protected $errors = [];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
        ->setDescription(
            'Add a per-language mapqs UI-label block (embedded inside the long_description ' .
            'JSON column) for map questions/questions_surveys from a JSON file. Idempotent + ' .
            'transactional. Mirrors the JSON_SET pattern used by the AddMapqsInsertProvince migration.'
        )
        ->addArgument('file', [
        'help' => 'Path to the JSON input file with mapqs_<locale> translations.',
        'required' => true,
        ])
        ->addOption('locale', [
        'help' => 'Locale code to import (default: de). Selects the mapqs_<locale> input key ' .
            'and the $.mapqs.<locale> JSON path written.',
        'default' => 'de',
        'short' => 'l',
        ])
        ->addOption('dry-run', [
        'help' => 'Run everything inside a transaction then roll back (no persistent change).',
        'boolean' => true,
        'default' => false,
        ])
        ->addOption('verbose', [
        'help' => 'Print a per-row trace.',
        'boolean' => true,
        'default' => false,
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->dryRun = (bool)$args->getOption('dry-run');
        $this->verbose = (bool)$args->getOption('verbose');
        $this->locale = (string)$args->getOption('locale');
        $filePath = (string)$args->getArgument('file');
        $mapqsKey = 'mapqs_' . $this->locale;

        if (!file_exists($filePath)) {
            $io->err("<error>File not found: $filePath</error>");

            return self::CODE_ERROR;
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $io->err("<error>Cannot read file: $filePath</error>");

            return self::CODE_ERROR;
        }

        $data = json_decode($fileContent, true);
        if (!is_array($data)) {
            $io->err('<error>Invalid JSON in input file</error>');

            return self::CODE_ERROR;
        }

        $io->out(sprintf(
            '<info>ImportMapqsTranslations</info> (locale=%s, key=%s)%s',
            $this->locale,
            $mapqsKey,
            $this->dryRun ? ' (DRY-RUN)' : ''
        ));

        $connection = ConnectionManager::get('default');

        try {
            $connection->begin();

          // Import questions (canonical map questions)
            if (!empty($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $questionId => $entry) {
                    $this->importMapqsBlock(
                        $io,
                        $connection,
                        'questions',
                        (int)$questionId,
                        $entry,
                        $mapqsKey
                    );
                }
            }

          // Import questions_surveys (per-survey overrides), keyed by the row's OWN id
            if (!empty($data['questions_surveys']) && is_array($data['questions_surveys'])) {
                foreach ($data['questions_surveys'] as $qsId => $entry) {
                    $this->importMapqsBlock(
                        $io,
                        $connection,
                        'questions_surveys',
                        (int)$qsId,
                        $entry,
                        $mapqsKey
                    );
                }
            }

            if ($this->dryRun) {
                $connection->rollback();
                $io->out('<warning>DRY-RUN: transaction rolled back, no changes persisted.</warning>');
            } else {
                $connection->commit();
                $io->out('<success>Committed.</success>');
            }
        } catch (\Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollback();
            }
            $io->err('<error>Aborted: ' . $e->getMessage() . '</error>');
            $io->err($e->getTraceAsString());

            return self::CODE_ERROR;
        }

        $this->printSummary($io);

      // Return error if any row failed
        if ($this->counters['questions_failed'] > 0 || $this->counters['questions_surveys_failed'] > 0) {
            return self::CODE_ERROR;
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Merge a single row's translated mapqs block into its existing
     * `long_description.mapqs.<locale>` path via a guarded JSON_SET.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param \Cake\Database\Connection $connection Active DB connection (already in a transaction).
     * @param string $table Either 'questions' or 'questions_surveys'.
     * @param int $id The row's own id (Questions.id, or QuestionsSurveys.id - NOT question_id).
     * @param mixed $entry The entry from the input file, expected to hold `{$mapqsKey}: {...}`.
     * @param string $mapqsKey The input key to read (e.g. 'mapqs_de').
     * @return void
     */
    protected function importMapqsBlock(
        ConsoleIo $io,
        $connection,
        string $table,
        int $id,
        $entry,
        string $mapqsKey
    ): void {
        $modelLabel = $table === 'questions' ? 'Questions' : 'QuestionsSurveys';
        $counterPrefix = $table;

        if (!is_array($entry) || empty($entry[$mapqsKey]) || !is_array($entry[$mapqsKey])) {
            $this->counters["{$counterPrefix}_skipped"]++;
            if ($this->verbose) {
                $io->out("  $modelLabel#$id: no '$mapqsKey' block in input, skipped.");
            }

            return;
        }

        try {
          // Guard: the row must already have a $.mapqs structure - this command
          // only ADDS a language block to an existing one, never fabricates it.
            $exists = $connection->execute(
                "SELECT id FROM $table WHERE id = :id " .
                "AND long_description IS NOT NULL AND long_description != '' " .
                'AND JSON_VALID(long_description) = 1 ' .
                "AND JSON_CONTAINS_PATH(long_description, 'one', '\$.mapqs')",
                ['id' => $id]
            )->fetch('assoc');

            if (empty($exists)) {
                $this->counters["{$counterPrefix}_skipped"]++;
                if ($this->verbose) {
                    $io->out("  $modelLabel#$id: not found or has no existing \$.mapqs, skipped.");
                }

                return;
            }

          // Guard: the id must be the SAME row this file was authored for. The file
          // is keyed to row ids of the 5T database, so on a different database the
          // id can exist but hold another question. A different number of keys than
          // the row's own Italian mapqs block means it is not the expected row: skip.
            $italian = $connection->execute(
                "SELECT JSON_LENGTH(JSON_EXTRACT(long_description, '\$.mapqs.it')) AS n FROM $table WHERE id = :id",
                ['id' => $id]
            )->fetch('assoc');
            $expectedKeys = $italian['n'] ?? null;
            if ($expectedKeys !== null && (int)$expectedKeys !== count($entry[$mapqsKey])) {
                $this->counters["{$counterPrefix}_mismatch"]++;
                if ($this->verbose) {
                    $io->out(sprintf(
                        '  %s#%d: id does not match the expected question (mapqs.it has %d keys, file has %d), skipped.',
                        $modelLabel,
                        $id,
                        (int)$expectedKeys,
                        count($entry[$mapqsKey])
                    ));
                }

                return;
            }

            $json = json_encode($entry[$mapqsKey], JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new \RuntimeException('json_encode failed for ' . $modelLabel . '#' . $id);
            }

            $path = '$.mapqs.' . $this->locale;
            $affected = $connection->execute(
                "UPDATE $table SET long_description = JSON_SET(long_description, :path, CAST(:json AS JSON)) " .
                'WHERE id = :id',
                ['path' => $path, 'json' => $json, 'id' => $id]
            )->rowCount();

            // The row was verified to exist above, so 0 affected rows means the block
            // already holds exactly this content (MySQL reports changed rows only).
            if ($affected < 1) {
                $this->counters["{$counterPrefix}_unchanged"]++;
                if ($this->verbose) {
                    $io->out("  $modelLabel#$id: mapqs.{$this->locale} already up to date, skipped.");
                }

                return;
            }

            $this->counters["{$counterPrefix}_updated"]++;
            if ($this->verbose) {
                $io->out("  $modelLabel#$id: mapqs.{$this->locale} set (" . count($entry[$mapqsKey]) . ' keys).');
            }
        } catch (\Throwable $e) {
            $this->counters["{$counterPrefix}_failed"]++;
            $this->errors[] = "$modelLabel#$id: " . $e->getMessage();
            if ($this->verbose) {
                $io->out("  <warning>$modelLabel#$id: FAILED - " . $e->getMessage() . '</warning>');
            }
        }
    }

    /**
     * Print a summary of the import operation.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function printSummary(ConsoleIo $io): void
    {
        $io->out('');
        $io->out('<info>Questions:</info>');
        $io->out(sprintf('  Updated:                     %d', $this->counters['questions_updated']));
        $io->out(sprintf('  Skipped (missing/no mapqs):  %d', $this->counters['questions_skipped']));
        $io->out(sprintf('  Already up to date:          %d', $this->counters['questions_unchanged']));
        $io->out(sprintf('  Skipped (id mismatch):       %d', $this->counters['questions_mismatch']));
        $io->out(sprintf('  Failed:                      %d', $this->counters['questions_failed']));

        $io->out('');
        $io->out('<info>Questions_surveys:</info>');
        $io->out(sprintf('  Updated:                     %d', $this->counters['questions_surveys_updated']));
        $io->out(sprintf('  Skipped (missing/no mapqs):  %d', $this->counters['questions_surveys_skipped']));
        $io->out(sprintf('  Already up to date:          %d', $this->counters['questions_surveys_unchanged']));
        $io->out(sprintf('  Skipped (id mismatch):       %d', $this->counters['questions_surveys_mismatch']));
        $io->out(sprintf('  Failed:                      %d', $this->counters['questions_surveys_failed']));

        if (!empty($this->errors)) {
            $io->out('');
            $io->out('<warning>Errors:</warning>');
            foreach ($this->errors as $error) {
                $io->out('  ' . $error);
            }
        }
    }
}

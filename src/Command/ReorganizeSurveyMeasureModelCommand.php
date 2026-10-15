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

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * ReorganizeSurveyMeasureModel command.
 *
 * Idempotent, transactional data transform that collapses the redundant
 * `sections` and `pillars` grouping models into the entities they decorate.
 * It MUST run BEFORE the ReorganizeSurveyAndMeasureModel schema migration
 * (which drops the columns/tables) — at this point `section_id` and `pillar_id`
 * still exist, which is exactly what this command reads.
 *
 * Phases:
 *   (a) sections        -> Questions of type='section' (name='sec-<id>')
 *   (b) Sections i18n    -> Questions i18n (field='description')
 *   (c) per-survey re-sequence + section-header insertion (weight 0,10,20,...)
 *   (d) pillars         -> Measures of type=0 (slug='pillar-<id>'), child re-weight
 *
 * Idempotency keys: section-question name='sec-'+oldSectionId (fits varchar(20)),
 * axis-measure slug='pillar-'+oldPillarId. Re-running leaves counts unchanged.
 *
 * Options:
 *   --dry-run   Roll back the transaction at the end (no persistent change).
 *   --verbose   Print per-phase counts and a per-survey trace.
 */
class ReorganizeSurveyMeasureModelCommand extends Command
{
    /**
     * @var \Cake\Database\Connection
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $verbose = false;

    /**
     * Aggregate counters for the summary.
     *
     * @var array<string,int>
     */
    protected $counters = [
        'sections_total' => 0,
        'section_questions_created' => 0,
        'section_questions_existing' => 0,
        'i18n_moved' => 0,
        'i18n_existing' => 0,
        'surveys_total' => 0,
        'qs_headers_created' => 0,
        'qs_headers_existing' => 0,
        'qs_reweighted' => 0,
        'sectionless_warnings' => 0,
        'pillars_total' => 0,
        'axis_measures_created' => 0,
        'axis_measures_updated' => 0,
        'child_measures_reweighted' => 0,
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Collapse sections->type=section questions and pillars->type=0 measures. ' .
                'Idempotent + transactional. Run BEFORE the schema migration.'
            )
            ->addOption('dry-run', [
                'help' => 'Run everything inside a transaction then roll back (no persistent change).',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('verbose', [
                'help' => 'Print per-phase counts and a per-survey trace.',
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
        $this->connection = ConnectionManager::get('default');

        $io->out('<info>ReorganizeSurveyMeasureModel</info>' . ($this->dryRun ? ' (DRY-RUN)' : ''));

        // The data command runs BEFORE the schema migration, so it needs the
        // measures.weight column to exist. This MUST happen OUTSIDE the
        // transaction: MySQL implicitly commits the open transaction when it
        // encounters DDL, which would otherwise silently persist a partial
        // transform (and defeat --dry-run). The column is nullable and additive;
        // the later Phinx migration guards with hasColumn so this is harmless.
        $this->ensureMeasuresWeightColumn($io);

        try {
            $this->connection->begin();

            $sectionMap = $this->phaseSections($io);
            $this->phaseSectionsI18n($io, $sectionMap);
            $this->phaseResequenceSurveys($io, $sectionMap);
            $this->phasePillars($io);

            if ($this->dryRun) {
                $this->connection->rollback();
                $io->out('<warning>DRY-RUN: transaction rolled back, no changes persisted.</warning>');
            } else {
                $this->connection->commit();
                $io->out('<success>Committed.</success>');
            }
        } catch (\Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollback();
            }
            $io->err('<error>Aborted: ' . $e->getMessage() . '</error>');
            $io->err($e->getTraceAsString());

            return self::CODE_ERROR;
        }

        $this->printSummary($io);

        return self::CODE_SUCCESS;
    }

    /**
     * Phase (a): sections -> global section-questions.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return array<int,int> Map oldSectionId => sectionQuestionId.
     */
    protected function phaseSections(ConsoleIo $io): array
    {
        $io->out('Phase (a): sections -> type=section questions');
        $map = [];

        if (!$this->tableExists('sections')) {
            $io->out('  sections table not found, skipping (already migrated?).');

            return $map;
        }

        $sections = $this->connection
            ->execute('SELECT id, name FROM sections ORDER BY weight, id')
            ->fetchAll('assoc');
        $this->counters['sections_total'] = count($sections);

        foreach ($sections as $section) {
            $sectionId = (int)$section['id'];
            $name = 'sec-' . $sectionId; // fits questions.name varchar(20)
            $description = $section['name'];

            $existing = $this->connection
                ->execute('SELECT id FROM questions WHERE name = :name LIMIT 1', ['name' => $name])
                ->fetch('assoc');

            if ($existing) {
                $questionId = (int)$existing['id'];
                $this->counters['section_questions_existing']++;
            } else {
                $this->connection->execute(
                    'INSERT INTO questions (name, description, type, moma_area) VALUES (:name, :description, :type, 0)',
                    ['name' => $name, 'description' => $description, 'type' => 'section']
                );
                $questionId = (int)$this->connection->execute('SELECT LAST_INSERT_ID() AS id')->fetch('assoc')['id'];
                $this->counters['section_questions_created']++;
            }

            $map[$sectionId] = $questionId;
            if ($this->verbose) {
                $io->out("  section $sectionId ('{$description}') -> question $questionId ($name)");
            }
        }

        return $map;
    }

    /**
     * Phase (b): move Sections i18n -> Questions i18n (field=description).
     * The old Sections i18n rows are NOT deleted here (the table drop removes
     * them; an early delete would break re-runs / rollback).
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param array<int,int> $sectionMap Map oldSectionId => sectionQuestionId.
     * @return void
     */
    protected function phaseSectionsI18n(ConsoleIo $io, array $sectionMap): void
    {
        $io->out('Phase (b): Sections i18n -> Questions i18n');

        if (empty($sectionMap) || !$this->tableExists('i18n')) {
            $io->out('  no section map or i18n table, skipping.');

            return;
        }

        foreach ($sectionMap as $oldSectionId => $questionId) {
            $rows = $this->connection->execute(
                "SELECT locale, content FROM i18n WHERE model = 'Sections' AND field = 'name' AND foreign_key = :fk",
                ['fk' => $oldSectionId]
            )->fetchAll('assoc');

            foreach ($rows as $row) {
                $locale = $row['locale'];
                $content = $row['content'];

                $existing = $this->connection->execute(
                    "SELECT id FROM i18n WHERE model = 'Questions' AND field = 'description' AND foreign_key = :fk AND locale = :locale LIMIT 1",
                    ['fk' => $questionId, 'locale' => $locale]
                )->fetch('assoc');

                if ($existing) {
                    $this->connection->execute(
                        'UPDATE i18n SET content = :content WHERE id = :id',
                        ['content' => $content, 'id' => (int)$existing['id']]
                    );
                    $this->counters['i18n_existing']++;
                } else {
                    $this->connection->execute(
                        'INSERT INTO i18n (locale, model, foreign_key, field, content) ' .
                        "VALUES (:locale, 'Questions', :fk, 'description', :content)",
                        ['locale' => $locale, 'fk' => $questionId, 'content' => $content]
                    );
                    $this->counters['i18n_moved']++;
                }
            }
        }
    }

    /**
     * Phase (c): per-survey re-sequence + section-header insertion.
     *
     * For EVERY distinct survey_id (templates included):
     *  - effective section = COALESCE(qs.section_id, q.section_id)
     *  - order sections by (sections.weight, sections.id)
     *  - within a section order real questions by (COALESCE(qs.weight,0), qs.id)
     *  - walk sections in order; ensure a qs row linking the survey to the
     *    section-question (find-or-create on survey_id+question_id), then its
     *    real questions; assign a fresh weight 0,10,20,... to every qs row
     *    (headers included). No final weight may be NULL.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param array<int,int> $sectionMap Map oldSectionId => sectionQuestionId.
     * @return void
     */
    protected function phaseResequenceSurveys(ConsoleIo $io, array $sectionMap): void
    {
        $io->out('Phase (c): per-survey re-sequence + section headers');

        if (!$this->columnExists('questions_surveys', 'section_id') && !$this->columnExists('questions', 'section_id')) {
            $io->out('  no section_id columns, skipping (already migrated?).');

            return;
        }

        $surveyIds = array_map(
            static fn ($r) => (int)$r['survey_id'],
            $this->connection
                ->execute('SELECT DISTINCT survey_id FROM questions_surveys ORDER BY survey_id')
                ->fetchAll('assoc')
        );
        $this->counters['surveys_total'] = count($surveyIds);

        // Pre-compute the set of section-question ids so we can tell headers apart.
        $sectionQuestionIds = array_flip(array_values($sectionMap));

        foreach ($surveyIds as $surveyId) {
            $this->resequenceSurvey($io, $surveyId, $sectionMap, $sectionQuestionIds);
        }
    }

    /**
     * Re-sequence a single survey.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param int $surveyId Survey id.
     * @param array<int,int> $sectionMap Map oldSectionId => sectionQuestionId.
     * @param array<int,int> $sectionQuestionIds Set of section-question ids.
     * @return void
     */
    protected function resequenceSurvey(ConsoleIo $io, int $surveyId, array $sectionMap, array $sectionQuestionIds): void
    {
        // Load this survey's qs rows joined to questions, with the effective section.
        $rows = $this->connection->execute(
            'SELECT qs.id AS qs_id, qs.question_id, qs.weight AS qs_weight, ' .
            'COALESCE(qs.section_id, q.section_id) AS eff_section, ' .
            'q.type AS q_type ' .
            'FROM questions_surveys qs ' .
            'JOIN questions q ON q.id = qs.question_id ' .
            'WHERE qs.survey_id = :sid',
            ['sid' => $surveyId]
        )->fetchAll('assoc');

        if (empty($rows)) {
            return;
        }

        // Section ordering: (sections.weight, sections.id). Build it once.
        $sectionOrder = $this->sectionWeightOrder();

        // Group real-question rows by effective section, preserving their qs ids.
        // Skip existing header rows (questions that are section-questions): they
        // will be (re)placed by the walk below so re-runs stay idempotent.
        $bySection = []; // eff_section => list of ['qs_id','qs_weight','q_id']
        $sectionless = []; // rows with no effective section
        foreach ($rows as $r) {
            $qId = (int)$r['question_id'];
            // A row pointing at a section-question is a header; don't treat as content.
            if (isset($sectionQuestionIds[$qId]) || strtolower((string)$r['q_type']) === 'section') {
                continue;
            }
            $eff = $r['eff_section'];
            $entry = [
                'qs_id' => (int)$r['qs_id'],
                'qs_weight' => $r['qs_weight'] === null ? 0 : (int)$r['qs_weight'],
                'q_id' => $qId,
            ];
            if ($eff === null) {
                $sectionless[] = $entry;
            } else {
                $bySection[(int)$eff][] = $entry;
            }
        }

        // Determine the order in which sections appear for this survey.
        $presentSections = array_keys($bySection);
        usort($presentSections, function ($a, $b) use ($sectionOrder) {
            $wa = $sectionOrder[$a] ?? [PHP_INT_MAX, $a];
            $wb = $sectionOrder[$b] ?? [PHP_INT_MAX, $b];

            return [$wa[0], $wa[1]] <=> [$wb[0], $wb[1]];
        });

        $weight = 0;
        foreach ($presentSections as $sectionId) {
            // Ensure the section-header qs row exists for this survey.
            $headerQuestionId = $sectionMap[$sectionId] ?? null;
            if ($headerQuestionId !== null) {
                $headerQsId = $this->findOrCreateHeaderQs($surveyId, $headerQuestionId);
                $this->setQsWeight($headerQsId, $weight);
                $weight += 10;
            }

            // Order the real questions within the section.
            $items = $bySection[$sectionId];
            usort($items, function ($a, $b) {
                return [$a['qs_weight'], $a['qs_id']] <=> [$b['qs_weight'], $b['qs_id']];
            });
            foreach ($items as $item) {
                $this->setQsWeight($item['qs_id'], $weight);
                $weight += 10;
            }
        }

        // Sectionless questions: keep them, re-weight, warn, but do NOT create a
        // header and do NOT delete (matches frontend hide behavior).
        if (!empty($sectionless)) {
            usort($sectionless, function ($a, $b) {
                return [$a['qs_weight'], $a['qs_id']] <=> [$b['qs_weight'], $b['qs_id']];
            });
            foreach ($sectionless as $item) {
                $this->setQsWeight($item['qs_id'], $weight);
                $weight += 10;
                $this->counters['sectionless_warnings']++;
                $io->warning(
                    "  survey $surveyId: sectionless question (qs_id={$item['qs_id']}, q_id={$item['q_id']}) " .
                    "re-weighted to $weight without a header (review)."
                );
            }
        }

        if ($this->verbose) {
            $io->out("  survey $surveyId: " . count($presentSections) . ' sections, last weight ' . ($weight - 10));
        }
    }

    /**
     * Find-or-create the questions_surveys header row linking a survey to a
     * section-question. Idempotent on (survey_id, question_id).
     *
     * @param int $surveyId Survey id.
     * @param int $questionId Section-question id.
     * @return int The questions_surveys id.
     */
    protected function findOrCreateHeaderQs(int $surveyId, int $questionId): int
    {
        $existing = $this->connection->execute(
            'SELECT id FROM questions_surveys WHERE survey_id = :sid AND question_id = :qid LIMIT 1',
            ['sid' => $surveyId, 'qid' => $questionId]
        )->fetch('assoc');

        if ($existing) {
            $this->counters['qs_headers_existing']++;

            return (int)$existing['id'];
        }

        $this->connection->execute(
            'INSERT INTO questions_surveys (survey_id, question_id, weight, compulsory, hidden, compulsory_answer) ' .
            'VALUES (:sid, :qid, 0, 0, 0, 0)',
            ['sid' => $surveyId, 'qid' => $questionId]
        );
        $this->counters['qs_headers_created']++;

        return (int)$this->connection->execute('SELECT LAST_INSERT_ID() AS id')->fetch('assoc')['id'];
    }

    /**
     * Write a fresh weight to a questions_surveys row.
     *
     * @param int $qsId questions_surveys id.
     * @param int $weight New weight.
     * @return void
     */
    protected function setQsWeight(int $qsId, int $weight): void
    {
        $this->connection->execute(
            'UPDATE questions_surveys SET weight = :w WHERE id = :id',
            ['w' => $weight, 'id' => $qsId]
        );
        $this->counters['qs_reweighted']++;
    }

    /**
     * Build the canonical section ordering map: sectionId => [weight, id].
     *
     * @return array<int,array{0:int,1:int}>
     */
    protected function sectionWeightOrder(): array
    {
        if (!$this->tableExists('sections')) {
            return [];
        }
        $order = [];
        $rows = $this->connection->execute('SELECT id, weight FROM sections')->fetchAll('assoc');
        foreach ($rows as $r) {
            $order[(int)$r['id']] = [(int)($r['weight'] ?? 0), (int)$r['id']];
        }

        return $order;
    }

    /**
     * Phase (d): pillars -> type=0 measures; re-weight child measures.
     * Axis measure weight = k*1000 (k = 0-based pillar index in id order).
     * Child measures of that pillar get k*1000 + 10,20,30,... in current id order.
     * Measure ids are preserved (update in place, never delete/recreate).
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function phasePillars(ConsoleIo $io): void
    {
        $io->out('Phase (d): pillars -> type=0 measures + child re-weight');

        if (!$this->tableExists('pillars')) {
            $io->out('  pillars table not found, skipping (already migrated?).');

            return;
        }
        if (!$this->columnExists('measures', 'pillar_id')) {
            $io->out('  measures.pillar_id not found, skipping (already migrated?).');

            return;
        }
        // measures.weight is guaranteed to exist here: ensureMeasuresWeightColumn()
        // added it before the transaction (DDL cannot live inside the transaction).

        $pillars = $this->connection
            ->execute('SELECT id, name, description FROM pillars ORDER BY id')
            ->fetchAll('assoc');
        $this->counters['pillars_total'] = count($pillars);

        $k = 0;
        foreach ($pillars as $pillar) {
            $pillarId = (int)$pillar['id'];
            $slug = 'pillar-' . $pillarId;
            $axisWeight = $k * 1000;

            $existing = $this->connection
                ->execute('SELECT id FROM measures WHERE slug = :slug LIMIT 1', ['slug' => $slug])
                ->fetch('assoc');

            if ($existing) {
                $axisId = (int)$existing['id'];
                $this->connection->execute(
                    'UPDATE measures SET type = 0, name = :name, description = :description, weight = :weight WHERE id = :id',
                    [
                        'name' => $pillar['name'],
                        'description' => $pillar['description'],
                        'weight' => $axisWeight,
                        'id' => $axisId,
                    ]
                );
                $this->counters['axis_measures_updated']++;
            } else {
                // measures.service_url is NOT NULL with no default: provide ''.
                $this->connection->execute(
                    'INSERT INTO measures (slug, name, description, type, weight, service_url) ' .
                    "VALUES (:slug, :name, :description, 0, :weight, '')",
                    [
                        'slug' => $slug,
                        'name' => $pillar['name'],
                        'description' => $pillar['description'],
                        'weight' => $axisWeight,
                    ]
                );
                $axisId = (int)$this->connection->execute('SELECT LAST_INSERT_ID() AS id')->fetch('assoc')['id'];
                $this->counters['axis_measures_created']++;
            }

            if ($this->verbose) {
                $io->out("  pillar $pillarId ('{$pillar['name']}') -> axis measure $axisId ($slug) weight $axisWeight");
            }

            // Re-weight this pillar's child measures (current pillar_id = this pillar)
            // in their current id order, skipping the axis measure itself.
            $children = $this->connection->execute(
                'SELECT id FROM measures WHERE pillar_id = :pid AND id <> :axis ORDER BY id',
                ['pid' => $pillarId, 'axis' => $axisId]
            )->fetchAll('assoc');

            $offset = 10;
            foreach ($children as $child) {
                $this->connection->execute(
                    'UPDATE measures SET weight = :w WHERE id = :id',
                    ['w' => $axisWeight + $offset, 'id' => (int)$child['id']]
                );
                $offset += 10;
                $this->counters['child_measures_reweighted']++;
            }

            $k++;
        }
    }

    /**
     * Ensure measures.weight exists BEFORE the transaction opens. DDL must never
     * run inside the transaction (MySQL auto-commits on DDL). The column is
     * nullable/additive and the Phinx migration guards with hasColumn, so adding
     * it early is idempotent and harmless. In --dry-run it is still added once
     * (the single, documented exception to "no persistent change") because
     * phase (d) cannot validate measure re-weighting without the column; the
     * column is left in place for the subsequent real run / migration.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function ensureMeasuresWeightColumn(ConsoleIo $io): void
    {
        if (!$this->tableExists('measures')) {
            return;
        }
        if ($this->columnExists('measures', 'weight')) {
            return;
        }
        $io->out('  measures.weight missing: adding it (additive, nullable) before the transaction.');
        $this->connection->execute('ALTER TABLE measures ADD COLUMN weight INT NULL');
    }

    /**
     * Print the per-phase summary.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function printSummary(ConsoleIo $io): void
    {
        $io->out('');
        $io->out('<info>Summary' . ($this->dryRun ? ' (DRY-RUN, rolled back)' : '') . '</info>');
        $io->out(sprintf('  Sections processed:            %d', $this->counters['sections_total']));
        $io->out(sprintf('  Section-questions created:     %d', $this->counters['section_questions_created']));
        $io->out(sprintf('  Section-questions existing:    %d', $this->counters['section_questions_existing']));
        $io->out(sprintf('  i18n rows moved (inserted):    %d', $this->counters['i18n_moved']));
        $io->out(sprintf('  i18n rows updated (existing):  %d', $this->counters['i18n_existing']));
        $io->out(sprintf('  Surveys processed:             %d', $this->counters['surveys_total']));
        $io->out(sprintf('  Header qs rows created:        %d', $this->counters['qs_headers_created']));
        $io->out(sprintf('  Header qs rows existing:       %d', $this->counters['qs_headers_existing']));
        $io->out(sprintf('  questions_surveys re-weighted: %d', $this->counters['qs_reweighted']));
        $io->out(sprintf('  Sectionless warnings:          %d', $this->counters['sectionless_warnings']));
        $io->out(sprintf('  Pillars processed:             %d', $this->counters['pillars_total']));
        $io->out(sprintf('  Axis measures created:         %d', $this->counters['axis_measures_created']));
        $io->out(sprintf('  Axis measures updated:         %d', $this->counters['axis_measures_updated']));
        $io->out(sprintf('  Child measures re-weighted:    %d', $this->counters['child_measures_reweighted']));
    }

    /**
     * Whether a table exists in the current schema.
     *
     * @param string $table Table name.
     * @return bool
     */
    protected function tableExists(string $table): bool
    {
        // Use information_schema (live) rather than the ORM schema collection:
        // the cached collection can return a stale result within a run and led
        // to a false "missing column" that double-issued an ALTER.
        $row = $this->connection->execute(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            ['t' => $table]
        )->fetch('assoc');

        return (int)($row['c'] ?? 0) > 0;
    }

    /**
     * Whether a column exists on a table in the current schema (live).
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @return bool
     */
    protected function columnExists(string $table, string $column): bool
    {
        $row = $this->connection->execute(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
            ['t' => $table, 'c' => $column]
        )->fetch('assoc');

        return (int)($row['c'] ?? 0) > 0;
    }
}

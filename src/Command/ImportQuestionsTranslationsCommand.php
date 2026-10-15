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
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;

/**
 * ImportQuestionsTranslations command.
 *
 * Imports German (or other locale) question and questions_surveys translations
 * from a reviewable JSON file into the `i18n` translations table. Mirrors the
 * SAFE SAVE PATTERN already used in `QuestionsController::editFromSurvey()`
 * (lines 780-890) to avoid the known duplicate-row bug.
 *
 * The save sequence is critical and must not be altered:
 * 1. stripEmptyTranslations() — removes empty-string field values before any DB write
 * 2. syncExistingTranslationRows() — UPDATEs any pre-existing i18n rows directly
 *    (bypassing Translate behavior's `allowEmptyTranslations => false` filter,
 *    which makes empty-content rows invisible to dedup), removing them from payload
 * 3. Normal ORM save via patchEntity() — handles only genuinely new translations
 *
 * This ordering prevents SQLSTATE[23000] duplicate-entry collisions that occur
 * when updating an existing i18n row that carries empty content: the behavior's
 * dedup cannot see the empty row, so it attempts INSERT and crashes.
 *
 * Idempotent: re-running the same file produces the same end state (no duplicates,
 * same content). Unknown IDs are skipped without aborting other rows.
 *
 * Input file: JSON with top-level keys `questions` (keyed by Questions.id) and
 * `questions_surveys` (keyed by QuestionsSurveys.id). Each entry: `{ "description": "...",
 * "long_description": "...", "options": "..." }` (any subset — untouched fields are
 * preserved). The `options` field must already be in the format stored in that row's
 * base column (pipe-delimited string OR JSON array — do NOT reformat it).
 *
 * Options:
 *   file                   Required argument — path to the JSON input file.
 *   --locale (default: de) Locale code to import (e.g., 'de', 'en', 'fr').
 *   --dry-run              Roll back the transaction at the end (no persistent change).
 *   --verbose              Print a per-row trace.
 */
class ImportQuestionsTranslationsCommand extends Command
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
    'questions_imported' => 0,
    'questions_skipped' => 0,
    'questions_mismatch' => 0,
    'questions_failed' => 0,
    'questions_surveys_imported' => 0,
    'questions_surveys_skipped' => 0,
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
            'Import locale translations for questions and questions_surveys from a JSON file. ' .
            'Idempotent + transactional. Mirrors the safe-save pattern from QuestionsController.'
        )
        ->addArgument('file', [
        'help' => 'Path to the JSON input file with translations.',
        'required' => true,
        ])
        ->addOption('locale', [
        'help' => 'Locale code to import (default: de).',
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
            '<info>ImportQuestionsTranslations</info> (locale=%s)%s',
            $this->locale,
            $this->dryRun ? ' (DRY-RUN)' : ''
        ));

        $Questions = TableRegistry::getTableLocator()->get('Questions');
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $connection = $Questions->getConnection();

        try {
            $connection->begin();

          // Import questions
            if (!empty($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $questionId => $fields) {
                    $this->importTranslation(
                        $io,
                        'questions',
                        (int)$questionId,
                        $fields,
                        $Questions
                    );
                }
            }

          // Import questions_surveys
            if (!empty($data['questions_surveys']) && is_array($data['questions_surveys'])) {
                foreach ($data['questions_surveys'] as $questionsSurveysId => $fields) {
                    $this->importTranslation(
                        $io,
                        'questions_surveys',
                        (int)$questionsSurveysId,
                        $fields,
                        $QuestionsSurveys
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
     * Import translations for a single question or questions_surveys row.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param string $type Either 'questions' or 'questions_surveys'.
     * @param int $id The Questions.id or QuestionsSurveys.id.
     * @param mixed $fields The fields array from the input file (any subset).
     * @param \Cake\ORM\Table $table The Questions or QuestionsSurveys table.
     * @return void
     */
    protected function importTranslation(
        ConsoleIo $io,
        string $type,
        int $id,
        $fields,
        $table
    ): void {
        $modelName = $type === 'questions' ? 'Questions' : 'QuestionsSurveys';
        $counterPrefix = $type === 'questions' ? 'questions' : 'questions_surveys';

        if (!is_array($fields) || empty($fields)) {
            $this->counters["{$counterPrefix}_skipped"]++;

            return;
        }

      // `_it` is the Italian source text the translation was written for. It is a
      // verification value, never a translatable field, so remove it before the
      // fields are imported.
        $sourceIt = isset($fields['_it']) && is_string($fields['_it']) ? $fields['_it'] : null;
        unset($fields['_it']);
        if (empty($fields)) {
            $this->counters["{$counterPrefix}_skipped"]++;

            return;
        }

        try {
          // Load the entity
            $entity = $table->get($id);

          // The id must be the SAME row this file was authored for. On another
          // database the id can exist but hold a different question: skip it.
            $mismatch = $this->sourceMismatch($entity, $sourceIt, $type)
                ?? $this->optionsMismatch($entity, $fields, $type);
            if ($mismatch !== null) {
                $this->counters["{$counterPrefix}_mismatch"]++;
                if ($this->verbose) {
                    $io->out("  $modelName#$id: id does not match the expected question ($mismatch), skipped.");
                }

                return;
            }

          // Build translations payload in the standard shape [locale => [field => value]]
            $translations = [
            $this->locale => $fields,
            ];

          // Step 1: Strip empty values
            $translations = $this->stripEmptyTranslations($translations);
            if (empty($translations)) {
                $this->counters["{$counterPrefix}_skipped"]++;
                if ($this->verbose) {
                    $io->out("  $modelName#$id: all fields empty, skipped.");
                }

                return;
            }

          // Step 2: Sync existing i18n rows and remove them from payload
          // THIS MUST HAPPEN BEFORE the entity assignment in step 3
            $translations = $this->syncExistingTranslationRows($id, $modelName, $translations);

          // Step 3: Handle remaining (new) translations via the ORM
            foreach ($translations as $lang => $langFields) {
                if (!is_array($langFields)) {
                    continue;
                }
                foreach ($langFields as $field => $value) {
                    $entity->translation($lang)->$field = $value;
                }
            }

          // Save the entity
            if (!$table->save($entity)) {
                $this->counters["{$counterPrefix}_failed"]++;
                $errorMsg = json_encode($entity->getErrors());
                $this->errors[] = "$modelName#$id: " . $errorMsg;
                if ($this->verbose) {
                    $io->out("  <warning>$modelName#$id: FAILED - " . $errorMsg . '</warning>');
                }

                return;
            }

            $this->counters["{$counterPrefix}_imported"]++;
            if ($this->verbose) {
                $io->out("  $modelName#$id: imported successfully.");
            }
        } catch (RecordNotFoundException $e) {
            // Record not found: skip this row without failing
            $this->counters["{$counterPrefix}_skipped"]++;
            if ($this->verbose) {
                $io->out("  $modelName#$id: not found, skipped.");
            }
        } catch (\Throwable $e) {
            $this->counters["{$counterPrefix}_failed"]++;
            $this->errors[] = "$modelName#$id: " . $e->getMessage();
            if ($this->verbose) {
                $io->out("  <warning>$modelName#$id: FAILED - " . $e->getMessage() . '</warning>');
            }
        }
    }

    /**
     * Strips empty/whitespace field VALUES out of a raw `_translations` payload
     * before it reaches the database. Ported from QuestionsController::stripEmptyTranslations()
     * (Part 11, lines 801-822).
     *
     * Necessary because the Translate behavior's `buildMarshalMap()` auto-marshals
     * `_translations` inside `patchEntity()`, creating a translation sub-entity for
     * every language key present regardless of field content — a skip-empty check
     * applied only after `patchEntity()` is too late to stop that sub-entity (and
     * its i18n row) from being persisted on save.
     *
     * @param mixed $translations The raw translations value, expected to be
     *   shaped as [locale => [field => value]].
     * @return array The filtered translations, with any language left empty
     *   after filtering removed entirely.
     */
    private function stripEmptyTranslations($translations): array
    {
        if (!is_array($translations)) {
            return [];
        }
        foreach ($translations as $lang => $fields) {
            if (!is_array($fields)) {
                unset($translations[$lang]);
                continue;
            }
            foreach ($fields as $field => $value) {
                if (trim((string)$value) === '') {
                    unset($translations[$lang][$field]);
                }
            }
            if (empty($translations[$lang])) {
                unset($translations[$lang]);
            }
        }

        return $translations;
    }

    /**
     * Directly UPDATEs any i18n row that already exists for the given
     * (model, foreign_key, locale, field) — bypassing the Translate behavior's
     * `i18n` association entirely. Ported from QuestionsController::syncExistingTranslationRows()
     * (Part 17, lines 852-883).
     *
     * When a table sets `allowEmptyTranslations => false` (as `QuestionsTable` does),
     * the behavior adds a `content != ''` condition to the i18n association used for
     * both finding existing rows and deduplication during save. An existing but
     * empty-content i18n row is therefore invisible to both reads and the save-time dedup.
     * When a user later fills in that translation (non-empty value), the ORM cannot
     * find the existing row and attempts INSERT, colliding with it
     * (SQLSTATE[23000] Duplicate entry).
     *
     * Solution: for each translation in the payload, directly UPDATE any
     * pre-existing i18n row via the I18n table (without Translate behavior,
     * therefore without the content-filter), and remove that (lang, field) pair
     * from the returned array. The normal ORM save then handles only genuinely
     * new translations, which do not carry this risk.
     *
     * @param int $foreignKey The owning record's id (i18n.foreign_key).
     * @param string $model The i18n model value (e.g. 'Questions', 'QuestionsSurveys').
     * @param array $translations Translations shaped as [lang => [field => value]],
     *   already passed through stripEmptyTranslations().
     * @return array The same shape, with any (lang, field) pair that had an
     *   existing row removed (that row was updated directly).
     * @throws \Exception When a row update fails.
     */
    private function syncExistingTranslationRows(int $foreignKey, string $model, array $translations): array
    {
        $i18n = TableRegistry::getTableLocator()->get('I18n');
        foreach ($translations as $lang => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $existing = $i18n->find()
                ->where([
                'model' => $model,
                'foreign_key' => $foreignKey,
                'locale' => $lang,
                'field' => $field,
                ])
                ->first();
                if (empty($existing)) {
                        continue;
                }
                $existing->content = (string)$value;
                if (!$i18n->save($existing)) {
                    throw new \Exception("Impossibile aggiornare la traduzione $lang/$field per $model#$foreignKey");
                }
                unset($translations[$lang][$field]);
            }
            if (empty($translations[$lang])) {
                unset($translations[$lang]);
            }
        }

        return $translations;
    }

    /**
     * Detects a row whose id exists but whose Italian description is not the one
     * the translation was written for (`_it` in the input file). A per-survey row
     * with an empty description falls back to its parent question's description.
     *
     * @param \Cake\Datasource\EntityInterface $entity The loaded Questions/QuestionsSurveys row.
     * @param string|null $sourceIt The `_it` value from the input, or null when absent.
     * @param string $type Either 'questions' or 'questions_surveys'.
     * @return string|null A reason when the row does not match, null otherwise.
     */
    protected function sourceMismatch($entity, ?string $sourceIt, string $type): ?string
    {
        if ($sourceIt === null || $this->trimUnicode($sourceIt) === '') {
            return null;
        }

        $description = (string)$entity->description;
        if ($type === 'questions_surveys' && trim($description) === '') {
            $parent = TableRegistry::getTableLocator()->get('Questions')
                ->find()
                ->select(['id', 'description'])
                ->where(['id' => $entity->question_id])
                ->first();
            $description = $parent ? (string)$parent->description : '';
        }

        return $this->trimUnicode($description) === $this->trimUnicode($sourceIt)
            ? null
            : 'Italian source text differs from the row in the database';
    }

    /**
     * Trims all Unicode whitespace, including the non-breaking space (U+00A0) that
     * PHP's trim() leaves in place and that real question texts start or end with.
     *
     * @param string $value Text to trim.
     * @return string
     */
    private function trimUnicode(string $value): string
    {
        return (string)preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value);
    }

    /**
     * Detects a row whose id exists but is not the question the translation was
     * written for: the translated option list must have as many entries as the
     * row's own options. Only checkable for plain option lists (single/multiple/
     * likert); anything else (array groups, no options, no base options) returns
     * null, meaning "cannot tell, do not skip".
     *
     * @param \Cake\Datasource\EntityInterface $entity The loaded Questions/QuestionsSurveys row.
     * @param array $fields The input fields for that row.
     * @param string $type Either 'questions' or 'questions_surveys'.
     * @return string|null A reason when the row does not match, null otherwise.
     */
    protected function optionsMismatch($entity, array $fields, string $type): ?string
    {
        if (empty($fields['options']) || !is_string($fields['options'])) {
            return null;
        }

        $base = $entity->options;
        if ($type === 'questions_surveys' && !$this->isNonEmptyList($base)) {
            // An empty per-survey override falls back to the parent question's options.
            $parent = TableRegistry::getTableLocator()->get('Questions')
                ->find()
                ->select(['id', 'options'])
                ->where(['id' => $entity->question_id])
                ->first();
            $base = $parent ? $parent->options : null;
        }
        if (!$this->isNonEmptyList($base)) {
            return null;
        }

        $decoded = json_decode($fields['options'], true);
        $given = is_array($decoded) && array_is_list($decoded)
            ? count($decoded)
            : count(explode('|', $fields['options']));
        $expected = count($base);

        return $given === $expected ? null : "$given translated options vs $expected in the row";
    }

    /**
     * @param mixed $value Value to test.
     * @return bool True for a non-empty list array (not an assoc/groups structure).
     */
    private function isNonEmptyList($value): bool
    {
        return is_array($value) && $value !== [] && array_is_list($value);
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
        $io->out(sprintf('  Imported:                    %d', $this->counters['questions_imported']));
        $io->out(sprintf('  Skipped (empty/unknown):     %d', $this->counters['questions_skipped']));
        $io->out(sprintf('  Skipped (id mismatch):       %d', $this->counters['questions_mismatch']));
        $io->out(sprintf('  Failed:                      %d', $this->counters['questions_failed']));

        $io->out('');
        $io->out('<info>Questions_surveys:</info>');
        $io->out(sprintf('  Imported:                    %d', $this->counters['questions_surveys_imported']));
        $io->out(sprintf('  Skipped (empty/unknown):     %d', $this->counters['questions_surveys_skipped']));
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

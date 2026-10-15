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
use Cake\I18n\I18n;
use Cake\ORM\TableRegistry;

/**
 * BackfillMomaOptionGrantsCommand (Brescia #496)
 *
 * Company surveys cloned from a template BEFORE the cloneQuestions() fix lost the
 * admin's `questions_surveys.moma_can_edit_options` grant. This command copies the
 * grant from the template row to the company-survey row (matched by `question_id`)
 * ONLY where the company row currently has it 0/NULL.
 *
 * Guarantees: it NEVER touches `disabled_options` and NEVER overwrites a company
 * row that already has the grant (hand-set per-company values, e.g. Brescia survey
 * 454, are preserved). It never REVOKES a grant. Idempotent.
 *
 * There is no column linking a company survey to the template it was cloned from, so
 * the target surveys are explicit: `--survey` (comma-separated ids) or
 * `--all-company-surveys` (every survey with company_id NOT NULL; surveys sharing no
 * question with the template are skipped).
 *
 * Usage: bin/cake backfill_moma_option_grants <template_id> (--survey 454,455 | --all-company-surveys) [--dry-run]
 */
class BackfillMomaOptionGrantsCommand extends Command
{
    /**
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to update.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Copia moma_can_edit_options dal template alle survey aziendali (match per question_id) ' .
                'solo dove la riga aziendale ha 0/NULL. Non tocca disabled_options ne\' concessioni esistenti.'
            )
            ->addArgument('template_id', [
                'help' => 'id del questionario template sorgente',
                'required' => true,
            ])
            ->addOption('survey', [
                'help' => 'id survey aziendali di destinazione, separati da virgola',
            ])
            ->addOption('all-company-surveys', [
                'help' => 'tutte le survey con company_id valorizzato (alternativa a --survey)',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('dry-run', [
                'help' => 'stampa il report per survey SENZA scrivere sul DB',
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
        $templateId = (int)$args->getArgument('template_id');
        $dryRun = (bool)$args->getOption('dry-run');
        $surveyOpt = (string)($args->getOption('survey') ?? '');
        $all = (bool)$args->getOption('all-company-surveys');

        if ($surveyOpt === '' && !$all) {
            $io->err('Specificare --survey <id,id,...> oppure --all-company-surveys');

            return static::CODE_ERROR;
        }

        $Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        // Campi letti non tradotti, ma le tabelle hanno il Translate behavior: locale di
        // default per evitare il join i18n (id ambiguo, vedi CLAUDE.md "Part 14 Stage A/C").
        $defaultLocale = I18n::getDefaultLocale();
        $Surveys->setLocale($defaultLocale);
        $QuestionsSurveys->setLocale($defaultLocale);

        if (!$Surveys->exists(['id' => $templateId])) {
            $io->err("Template {$templateId} non trovato");

            return static::CODE_ERROR;
        }

        $granted = $QuestionsSurveys->find()
            ->select(['question_id'])
            ->where(['survey_id' => $templateId, 'moma_can_edit_options' => 1])
            ->all()
            ->extract('question_id')
            ->map(fn($id) => (int)$id)
            ->toList();

        if ($surveyOpt !== '') {
            $targetIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $surveyOpt)))));
        } else {
            $targetIds = $Surveys->find()
                ->select(['id'])
                ->where(['company_id IS NOT' => null, 'id !=' => $templateId])
                ->orderAsc('id')
                ->all()
                ->extract('id')
                ->map(fn($id) => (int)$id)
                ->toList();
        }

        $io->out(
            '<info>BackfillMomaOptionGrants</info> template=' . $templateId .
            ' grants_nel_template=' . count($granted) . ($dryRun ? ' (DRY-RUN)' : '')
        );

        $table = [['survey_id', 'in_template', 'aggiornate', 'gia_concesse', 'question_ids_aggiornate']];
        $totalUpdated = 0;
        $connection = ConnectionManager::get('default');

        foreach ($targetIds as $surveyId) {
            if ($surveyId === $templateId) {
                continue;
            }
            $rows = $QuestionsSurveys->find()
                ->select(['question_id', 'moma_can_edit_options'])
                ->where(['survey_id' => $surveyId, 'question_id IN' => $granted ?: [0]])
                ->all();

            if ($rows->isEmpty() && $all) {
                continue; // nessuna domanda in comune col template: non e' un suo clone
            }

            $toUpdate = [];
            $already = 0;
            foreach ($rows as $row) {
                if (empty($row->moma_can_edit_options)) {
                    $toUpdate[] = (int)$row->question_id;
                } else {
                    $already++;
                }
            }

            if (!$dryRun && $toUpdate) {
                // Raw update: the field is $_accessible=false on the entity on purpose.
                // Re-assert "0/NULL" in the WHERE so a concurrent grant is never overwritten.
                $connection->newQuery()
                    ->update('questions_surveys')
                    ->set(['moma_can_edit_options' => 1])
                    ->where(['survey_id' => $surveyId, 'question_id IN' => $toUpdate])
                    ->where(['OR' => ['moma_can_edit_options IS' => null, 'moma_can_edit_options' => 0]])
                    ->execute();
            }

            $totalUpdated += count($toUpdate);
            $table[] = [
                (string)$surveyId,
                (string)$rows->count(),
                (string)count($toUpdate),
                (string)$already,
                implode(',', $toUpdate),
            ];
        }

        $io->helper('Table')->output($table);

        if ($dryRun) {
            $io->warning($totalUpdated . ' righe verrebbero aggiornate (nessuna scrittura, --dry-run attivo).');
        } else {
            $io->success($totalUpdated . ' righe aggiornate.');
        }

        return static::CODE_SUCCESS;
    }
}

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
use Cake\I18n\I18n;
use Cake\ORM\TableRegistry;

/**
 * AlignSurveyMandatoryQuestionsCommand
 *
 * Sets `questions_surveys.compulsory_answer` for a given survey, matching
 * each question by `questions.name` (the machine code — NOT `id`, which
 * differs per brand) against a fixed list of mandatory question names. Every
 * OTHER question in the survey is forced to NOT compulsory. Idempotent/safe
 * to re-run.
 *
 * Usage: bin/cake align_survey_mandatory_questions <survey_id> [--dry-run]
 */
class AlignSurveyMandatoryQuestionsCommand extends Command
{
    /**
     * `questions.name` values that must end up `compulsory_answer = 1` for
     * the target survey. Every other question in the survey → 0.
     *
     * @var array<int, string>
     */
    private const MANDATORY_QUESTION_NAMES = [
        'origine-spostamenti',
        'sede-mappa',
        'mezzo-prevalente',
        'tipo-auto',
        'tipo-bici',
        'tipo-moto',
        'tipo-bus',
        'tipo-monopattino',
        'tipo-treno',
        'quale-distanza',
        'spesa-spostamento',
        'rinuncia-auto',
        'ALTERNATIVE-MODALI',
    ];

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser to update.
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Allinea questions_surveys.compulsory_answer per un survey alla lista fissa di ' .
                'domande obbligatorie (match per questions.name, non per id — gli id cambiano per brand).'
            )
            ->addArgument('survey_id', [
                'help' => 'id del questionario da allineare',
                'required' => true,
            ])
            ->addOption('dry-run', [
                'help' => 'calcola e stampa la tabella prima/dopo SENZA scrivere sul DB',
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
        $surveyId = (int)$args->getArgument('survey_id');
        $dryRun = (bool)$args->getOption('dry-run');

        $this->QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        // Forzo la lingua di default: leggo `name`/`compulsory_answer`, campi
        // non tradotti, ma le tabelle coinvolte hanno il Translate behavior —
        // sotto una locale non default un find() qui aprirebbe comunque il
        // join i18n (id ambiguo, vedi CLAUDE.md "Part 14 Stage A/C").
        $defaultLocale = I18n::getDefaultLocale();
        $this->QuestionsSurveys->setLocale($defaultLocale);
        $this->Questions->setLocale($defaultLocale);

        $rows = $this->QuestionsSurveys->find()
            ->where(['QuestionsSurveys.survey_id' => $surveyId])
            ->contain(['Questions'])
            ->all();

        if ($rows->isEmpty()) {
            $io->err("Nessuna domanda trovata per il survey_id {$surveyId}");

            return static::CODE_ERROR;
        }

        $table = [['question_id', 'name', 'old', 'new']];
        $updates = [];

        foreach ($rows as $row) {
            $name = $row->question->name ?? '';
            $old = (int)$row->compulsory_answer;
            $new = in_array($name, self::MANDATORY_QUESTION_NAMES, true) ? 1 : 0;

            if ($old !== $new) {
                $updates[(int)$row->question_id] = $new;
            }

            $table[] = [(string)$row->question_id, (string)$name, (string)$old, (string)$new];
        }

        $io->out('<info>AlignSurveyMandatoryQuestions</info> survey_id=' . $surveyId . ($dryRun ? ' (DRY-RUN)' : ''));
        $io->helper('Table')->output($table);

        if ($dryRun) {
            $io->warning(count($updates) . ' domande verrebbero aggiornate (nessuna scrittura, --dry-run attivo).');

            return static::CODE_SUCCESS;
        }

        foreach ($updates as $questionId => $newValue) {
            $this->QuestionsSurveys->updateAll(
                ['compulsory_answer' => $newValue],
                ['survey_id' => $surveyId, 'question_id' => $questionId]
            );
        }

        $io->success(count($updates) . " domande aggiornate per il survey_id {$surveyId}");

        return static::CODE_SUCCESS;
    }
}

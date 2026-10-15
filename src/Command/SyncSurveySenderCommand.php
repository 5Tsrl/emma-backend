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
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Allinea survey_delivery_configs.sender_email al mittente forzato (Surveys.forcedSenderEmail,
 * env SURVEY_FORCED_SENDER_EMAIL). Dry-run di default: senza --apply elenca solo le righe da cambiare.
 * Il sito è scelto da HTTP_HOST (come gli altri comandi di manutenzione).
 *
 * Esempio (dev prima, poi prod solo dopo conferma):
 *   bin/cake sync_survey_sender            # elenco
 *   bin/cake sync_survey_sender --apply    # scrive
 */
class SyncSurveySenderCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(
            'Elenca (dry-run, default) o aggiorna con --apply le configurazioni di invio con un mittente diverso da Surveys.forcedSenderEmail.'
        );
        $parser->addOption('apply', [
            'help' => 'Scrive davvero le modifiche (senza: solo elenco).',
            'boolean' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $forced = trim((string)Configure::read('Surveys.forcedSenderEmail'));
        if ($forced === '' || !filter_var($forced, FILTER_VALIDATE_EMAIL)) {
            $io->error('Surveys.forcedSenderEmail (SURVEY_FORCED_SENDER_EMAIL) non impostato o non valido: nulla da fare.');

            return static::CODE_ERROR;
        }

        $table = TableRegistry::getTableLocator()->get('SurveyDeliveryConfigs');
        $wrong = $table->find()
            ->select(['id', 'survey_id', 'sender_email'])
            ->where(['OR' => ['sender_email IS' => null, 'sender_email !=' => $forced]])
            ->order(['id' => 'ASC'])
            ->all();

        $count = $wrong->count();
        $io->out("Configurazioni con mittente diverso da '{$forced}': {$count}");
        foreach ($wrong as $cfg) {
            $io->out("  id={$cfg->id} survey_id={$cfg->survey_id} sender_email=" . ($cfg->sender_email ?? 'NULL'));
        }

        if ($count === 0) {
            $io->success('Nulla da aggiornare.');

            return static::CODE_SUCCESS;
        }

        if (!$args->getOption('apply')) {
            $io->warning('Dry-run: nessuna modifica. Rilancia con --apply per scrivere.');

            return static::CODE_SUCCESS;
        }

        // updateAll salta i callback: il valore è quello già validato sopra, quindi l'effetto è identico al beforeSave
        $updated = $table->updateAll(['sender_email' => $forced], ['id IN' => $wrong->extract('id')->toList()]);
        $io->success("Aggiornate {$updated} configurazioni a '{$forced}'.");

        return static::CODE_SUCCESS;
    }
}

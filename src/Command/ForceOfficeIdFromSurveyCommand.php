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

class ForceOfficeIdFromSurveyCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->loadModel('Answers');
        $this->loadModel('Users');
        //TODO: Valutare se trasformare in parametri
        $qid = 302; //Domanda mappa
        $survey_id = 152; // Survey di riferimento

        $answers = $this->Answers->find()
            ->where(['question_id' => $qid])
            ->where(['survey_id' => $survey_id]);

        foreach ($answers as $a) {
            $ad = '';
            if (!is_null($a->answer)) {
                $uid = $a->user_id;
                $sede = json_decode($a->answer);
                $office_id = $sede->destination->office_id;
                $u = $this->Users->findById($uid)->first();
                $u->office_id = $office_id;
                if ($this->Users->save($u)) {
                    $io->out("OK: Sede dell'utente $uid Aggiornata");
                } else {
                    $io->out("KO: Impossibile aggiornare l'utente $uid");
                }
            } else {
                $io->out("KO: Risposta invalida $a->id");
            }
        }
    }
}

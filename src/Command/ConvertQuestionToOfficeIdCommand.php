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

class ConvertQuestionToOfficeIdCommand extends Command
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
        $qid = 380; //Domanda sulla sede del consiglio regionale
        $survey_id = 1215; // Survey di riferimento
        $company_id = 1816; // Azienda di riferimento

        $answers = $this->Answers->find()
            ->where(['question_id' => $qid])
            ->where(['survey_id' => $survey_id]);

        foreach ($answers as $a) {
            $ad = '';
            if (!is_null($a->answer)) {
                $uid = $a->user_id;
                $sede = $a->answer;
                if (is_array($sede)) {
                    $sede = trim($sede[0]);
                }
                $u = $this->Users->findById($uid)->first();
                switch ($sede) {
                    case 'P.zza Solferino, 22':
                        $u->office_id = 2502;
                        break;
                    case 'Via Arsenale, 14':
                        $u->office_id = 2509;
                        break;
                    case 'Via Arsenale, 12':
                        $u->office_id = 2510;
                        break;
                    case 'Via Alfieri, 15':
                        $u->office_id = 2461;
                        break;
                    case "Via San Francesco D'Assisi, 35":
                        $u->office_id = 2511;
                        break;
                }
                if ($this->Users->save($u)) {
                    $io->out("OK: Sede dell'utente $uid Aggiornata");
                } else {
                    $io->out("KO: Impossibile aggiornare l'utente $uid");
                }
            } else {
                $io->out("KO: Risposta invalida $a->id");
            }
        }

        //Cerco gli utenti che non hanno sede
        $users = $this->Users->find()
            ->where(['company_id' => $company_id])
            ->where(['office_id IS ' => null]);

        foreach ($users as $u) {
            $io->out("Utente senza sede {$u->id}");
        }
    }
}

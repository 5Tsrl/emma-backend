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

class ConvertArrayAnswersCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->loadModel('Questions');
        $this->loadModel('Answers');
        $type = 'array';

        $questions = $this->Questions->find()
        ->where(['type' => $type]);

        foreach ($questions as $q) {
            $o = $q->options;

            $groups = null;
            if (isset($o['groups'])) {
                $groups = $o['groups'];
            }

            $labels = [];

            $io->out("Questions {$q->name} ");
            if (is_array($groups)) {
                foreach ($groups as $g) {
                    $io->out("\t Label {$g['label']} ");
                    $labels[]  = $g['label'];
                }
            }

            $answers = $this->Answers->find()
            ->where(['question_id' => $q->id]);

            foreach ($answers as $a) {
                //$io->out("\t\t Answer {$a->id} ready to be converted");
                $ad = '';
                if (!is_null($a->answer)) {
                    $ad = $a->answer;
                    $keys = array_keys($ad);
                    //Potrei fare un controllo più accurato, ma scommetto che questo basta - massimoi 20/2/21
                    //https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential/4254008#4254008
                    if ($type == 'array') {
                        if (isset($keys[0]) && is_numeric($keys[0])) {
                            $newAnswer = [];
                            foreach ($ad as $k => $option) {
                                if ($k !== 'Altro' && $labels[$k] != 'Altro') {
                                    $newAnswer[$labels[$k]] = $option;
                                }
                            }
                            $a->answer = $newAnswer;
                            if ($this->Answers->save($a)) {
                                $io->out("\t\t Salvataggio {$a->id} OK");
                            } else {
                                $io->out("\t\t Salvataggio {$a->id} KO");
                            }
                        }
                    }
                    if ($type == 'single' && is_array($ad)) {
                        $io->out("\t\t Answer {$a->id} to question {$q->id} is multiple");
                    }
                    if (($type == 'multiple' || $type == 'array') && !is_array($ad)) {
                        $io->out("\t\t Answer {$a->id} to question {$q->id} is single");
                    }
                }

                //$io->out("\t\t Answer {$ad} converted OK");
            }
        }
    }
}

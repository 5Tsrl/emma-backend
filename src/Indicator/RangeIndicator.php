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
namespace App\Indicator;

use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\ModelAwareTrait;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;

class RangeIndicator extends baseIndicator
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;

    private $question = null;
    private $query = null;
    private $altro = null;
    private $slug = null;

    public function __construct($slug, $survey_id = null, $office_id = null, $filters = [], $subcompany = null)
    {
        $this->slug = $slug;
        $this->question = $this->getQuestion($slug);
        $question_id = $this->question->id;

        $this->loadModel('Answers');
        $query = $this->Answers->find()
            ->where(['question_id' => $question_id]);

        //Aggiungo una voce altro per tutti i casi spuri
        $altro = $this->Answers->find()
            ->select(['count' => $query->func()->count('answer')])
            ->where([
                'question_id' => $question_id,
                'answer LIKE' => '%altro%',
            ]);

        if (!empty($survey_id)) {
            $query->where(['survey_id' => $survey_id]);
            $altro->where(['survey_id' => $survey_id]);
        }

        if (!empty($office_id)) {
            $query->matching('Users', function ($qq) use ($office_id) {
                return $qq->where(['Users.office_id' => $office_id]);
            });
            $altro->matching('Users', function ($qq) use ($office_id) {
                return $qq->where(['Users.office_id' => $office_id]);
            });
        }
        if (!empty($subcompany)) {
            $query->matching('Users', function ($qq) use ($subcompany) {
                return $qq->where(['Users.subcompany' => $subcompany]);
            });
            $altro->matching('Users', function ($qq) use ($subcompany) {
                return $qq->where(['Users.subcompany' => $subcompany]);
            });
        }
        $answ_usr_ids = [];
        $answ_usr_ids_1 = [];
        $answ_usr_ids_2 = [];

        if (!empty($filters)) {
            foreach ($filters as $flt => $answ) {
                $qst_name = str_replace('_', '-', str_replace('filter_', '', $flt));
                $filter_question = $this->Questions->find()
                            ->where(['Questions.name' => $qst_name])
                            ->first();

                //Se ho trovato la domanda filtro, altrimenti non faccio nulla
                if (!empty($filter_question)) {
                    $answ_usr = $this->Answers->find()
                                ->select(['Answers.user_id', 'Answers.answer'])
                                ->where(['Answers.question_id' => $filter_question->id,
                                'survey_id' => $survey_id]);
                                //->where(['Answers.answer ' => "%$answ%" ]);
                    //sqld($answ_usr);
                    //Purtroppo non posso usare il filtro in SQL perchè il valore è memorizzato in json e sbaglia
                    //Devo iterare sui risultati ;-(
                    foreach ($answ_usr as $a) {
                        //Se la risposta è un array cerco un valore dentro l'array
                        if (is_array($a->answer)) {
                            if (in_array($answ, $a->answer)) {
                                $answ_usr_ids_2[] = $a->user_id;
                            }
                        } else {
                        //Altrimenti cerco il valore esatto
                            if ($answ == $a->answer) {
                                $answ_usr_ids_2[] = $a->user_id;
                            }
                        }
                    }
                    if (empty($answ_usr_ids_1)) {
                        $answ_usr_ids = $answ_usr_ids_2;
                    } else {
                        $answ_usr_ids = array_intersect($answ_usr_ids_1, $answ_usr_ids_2);
                    }

                    $answ_usr_ids_1 = $answ_usr_ids_2;
                    $answ_usr_ids_2 = [];
                    //Se la risposta è vuota inserisco un valore falso per non ritornare nulla
                    if (empty($answ_usr_ids)) {
                        $answ_usr_ids = [-1];
                    }
                    $query->where(['Answers.user_id IN' => $answ_usr_ids]);
                    $altro->where(['Answers.user_id IN' => $answ_usr_ids]);
                }
            }
        }

        //Preparo le query senza select, perchè le select sono diverse
        //se chiamo count conteggio i valori per le tabelle
        //se chiamo detail voglio la lista di valori per un singolo risultato
        $this->query = $query;
        $this->altro = $altro;

        return $this;
    }

    public function getQuestionType()
    {
        return $this->question->type;
    }

    private function getQuestion($slug)
    {
        //Prima cerco in configurazione
        $question_id = Configure::read("Questions.$slug");

        //cerco ancora nella tabella delle question se ce n'è una con quel nome (sostituendo _ con -)
        if (empty($question_id)) {
            $this->loadModel('Questions');
            $slug = str_replace('_', '-', $slug);
            $this->question = $this->Questions->find()
                ->select(['id', 'type', 'options'])
                ->where(['name' => $slug])
                ->first();
            if (!empty($this->question)) {
                $question_id = $this->question->id;
            } else {
                $question_id = null;
            }
        }

        if (empty($question_id)) {
            throw new NotFoundException("L'indicatore richiesto non è definito");
        }

        if (empty($this->question)) {
            $this->loadModel('Questions');
            $this->question = $this->Questions->find()
                ->select(['id', 'type', 'options'])
                ->where(['id' => $question_id])
                ->first();
        }

        return $this->question;
    }

    private function getLabelFromQuestion($k)
    {
        return $this->questionsOptions['groups'][$k]['label'] ?? '--';
    }

    public function getList($answer)
    {
        if ($this->question->type == 'single' || $this->question->type == 'multiple' || $this->question->type == 'text') {
            if (is_numeric($answer)) {
                $answer = (float)$answer;
            }

            // ->when($this->query->newExpr()->between('answer', 30, 50))
            // ->then('tra 30 e 50 €/mese')
            // ->when($this->query->newExpr()->between('answer', 50, 100))
            // ->then('tra 50 e 100 €/mese')
            // ->when($this->query->newExpr()->between('answer', 100, 150))
            // ->then('tra 100 e 150 €/mese')
            // ->when($this->query->newExpr()->between('answer', 150, 200))
            // ->then('tra 150 e 200 €/mese ')
            // ->when(['answer >=' => 200])
            // ->then('più di 200 €/mese');


            if ($answer == 'meno di 30 €/mese') {
                $this->query->select([
                'user_id', 'Users.email', 'Users.first_name', 'Users.last_name',
                ])
                ->contain(['Users'])
                ->where(function (QueryExpression $exp) {
                    return $exp
                    ->lte('answer', 30);
                });
            // ->where(['answer' => 30]);
                // $subquery="['answer<=' => 30]";
            } else {
                $this->query->select([
                    'user_id', 'Users.email', 'Users.first_name', 'Users.last_name',
                ])
                ->contain(['Users'])
                ->where(['answer' => $answer]);
            }

            // ->where(['answer' => $answer]);

            /* TODO: capire bene come usare la funzione json_contain in cake
            if ( $this->question->type == 'multiple' ){
                $filter = $this->query->func()->json_contain([
                    'field' => 'identifier',
                    "val" => 'identifier',
                    "$" => 'literal',
                ]);
                $this->query->where('answer' =>'' ));
            } */

            $res = $this->query->toArray();
            //Devo appiattire i risultati per avere nomi usabili in vue table
            $res = array_map(function ($r) {
                return [
                    'id' => $r['user_id'],
                    'email' => $r['user']['email'],
                    'first_name' => $r['user']['first_name'],
                    'last_name' => $r['user']['last_name'],
                ];
            }, $res);

            return $res;
        }

        //Se la domanda non è del tipo giusto restituisco null
        return null;
    }

    public function rangeQuestions($label, $indicator)
    {
        if ($indicator == 'costo-spostamento') {
            if ($label <= 30) {
                return array_search('meno di 30 €/mese', $this->labels);
            } elseif ($label > 30 and $label <= 50) {
                return array_search('tra 30 e 50 €/mese', $this->labels);
            } elseif ($label > 50 and $label <= 100) {
                return array_search('tra 50 e 100 €/mese', $this->labels);
            } elseif ($label > 100 and $label <= 150) {
                return array_search('tra 100 e 150 €/mese', $this->labels);
            } elseif ($label > 150 and $label <= 200) {
                return array_search('tra 150 e 200 €/mese', $this->labels);
            } elseif ($label > 200 and $label <= 100000) {
                return array_search('più di 200 €/mese', $this->labels);
            } else {
                return array_search((string)$label, $this->labels);
            }
        } elseif ($indicator == 'quale-distanza') {
            if ($label <= 2) {
                return array_search('<2 km', $this->labels);
            } elseif ($label > 2 and $label <= 5) {
                return array_search('2,1-5 km', $this->labels);
            } elseif ($label > 5 and $label <= 10) {
                return array_search('5,1-10 km', $this->labels);
            } elseif ($label > 10 and $label <= 15) {
                return array_search('10,1-15 km', $this->labels);
            } elseif ($label > 15 and $label <= 25) {
                return array_search('15,1-25 km', $this->labels);
            } elseif ($label > 25 and $label <= 50) {
                return array_search('25,1-50 km', $this->labels);
            } elseif ($label > 50 and $label <= 75) {
                return array_search('50,1-75 km', $this->labels);
            } elseif ($label > 75 and $label <= 100) {
                return array_search('75,1-100 km', $this->labels);
            } elseif ($label > 100 and $label <= 125) {
                return array_search('100,1-125 km', $this->labels);
            } elseif ($label > 125 and $label <= 150) {
                return array_search('125,1-150 km', $this->labels);
            } elseif ($label > 150 and $label <= 100000) {
                return array_search('>150 km', $this->labels);
            } else {
                return array_search((string)$label, $this->labels);
            }
        } elseif ($indicator == 'quale-distanza-auto') {
            if ($label <= 2) {
                return array_search('<2 km', $this->labels);
            } elseif ($label > 2 and $label <= 5) {
                return array_search('2,1-5 km', $this->labels);
            } elseif ($label > 5 and $label <= 10) {
                return array_search('5,1-10 km', $this->labels);
            } elseif ($label > 10 and $label <= 15) {
                return array_search('10,1-15 km', $this->labels);
            } elseif ($label > 15 and $label <= 25) {
                return array_search('15,1-25 km', $this->labels);
            } elseif ($label > 25 and $label <= 50) {
                return array_search('25,1-50 km', $this->labels);
            } elseif ($label > 50 and $label <= 75) {
                return array_search('50,1-75 km', $this->labels);
            } elseif ($label > 75 and $label <= 100) {
                return array_search('75,1-100 km', $this->labels);
            } elseif ($label > 100 and $label <= 125) {
                return array_search('100,1-125 km', $this->labels);
            } elseif ($label > 125 and $label <= 150) {
                return array_search('125,1-150 km', $this->labels);
            } elseif ($label > 150 and $label <= 100000) {
                return array_search('>150 km', $this->labels);
            }
            // elseif($label > 150 and $label <= 200){
            //     return array_search("150,1-200 km", $this->labels);
            // }elseif($label > 200 and $label <= 1000){
            //     return array_search("200,1-100 km", $this->labels);
            // }elseif($label > 1000){
            //     return array_search(">1000 km", $this->labels);
            // }

            else {
                return array_search((string)$label, $this->labels);
            }
        } elseif ($indicator == 'spesa-spostamento') {
            if ($label <= 30) {
                return array_search('Meno di 30 Euro', $this->labels);
            } elseif ($label > 30 and $label <= 50) {
                return array_search('Tra 30 e 50 Euro', $this->labels);
            } elseif ($label > 50 and $label <= 80) {
                return array_search('Tra 50 e 80 Euro', $this->labels);
            } elseif ($label > 80 and $label <= 10000) {
                return array_search('Più di 80 Euro', $this->labels);
            } else {
                return array_search('Dati non validi', $this->labels);
            }
        } elseif ($indicator == 'distanza-totale') {
            if ($label <= 2) {
                return array_search('<2 km', $this->labels);
            } elseif ($label > 2 and $label <= 5) {
                return array_search('2,1-5 km', $this->labels);
            } elseif ($label > 5 and $label <= 10) {
                return array_search('5,1-10 km', $this->labels);
            } elseif ($label > 10 and $label <= 15) {
                return array_search('10,1-15 km', $this->labels);
            } elseif ($label > 15 and $label <= 25) {
                return array_search('15,1-25 km', $this->labels);
            } elseif ($label > 25 and $label <= 50) {
                return array_search('25,1-50 km', $this->labels);
            } elseif ($label > 50 and $label <= 75) {
                return array_search('50,1-75 km', $this->labels);
            } elseif ($label > 75 and $label <= 100) {
                return array_search('75,1-100 km', $this->labels);
            } elseif ($label > 100 and $label <= 125) {
                return array_search('100,1-125 km', $this->labels);
            } elseif ($label > 125 and $label <= 150) {
                return array_search('125,1-150 km', $this->labels);
            } elseif ($label > 150 and $label <= 100000) {
                return array_search('>150 km', $this->labels);
            } else {
                if (array_search((string)$label, $this->labels)) {
                    return array_search((string)$label, $this->labels);
                } else {
                    return array_search('Dati non validi', $this->labels);
                }
            }
        }
    }

     //Aggiunge un item nell'array delle serie

    private function addItem($label, $count)
    {
        if (is_null($label)) {
            return;
        }
        if (is_numeric($label)) {
            $k = $this->rangeQuestions($label, $this->slug);
        } else {
            $k = array_search($label, $this->labels);
        }

        if ($k === false) {
            $k = array_search('Dati non validi', $this->labels);
            $this->series[0][$k] += $count;
             //L'indice dell'array delle serie dev'essere vuoto e corrispondente alle label
        } else {
            $this->series[0][$k] += $count; //Se c'era già aggiungo il conteggio
        }
    }

    public function count($default_sort)
    {
        $numSerie = 0;
        if ($this->question->type == 'single' || $this->question->type == 'multiple' || $this->question->type == 'text') {
            // $costCase = $this->query->newExpr()
            // ->case()
            // // "meno di 30 €/mese"+
            // // ->when(['answer <' => 30,'answer LIKE' => '[A-Za-z]%'])
            // ->when([$this->query->newExpr()->between('answer', 0, 30)])
            // ->then("meno di 30 €/mese")
            // ->when($this->query->newExpr()->between('answer', 30, 50))
            // ->then("tra 30 e 50 €/mese")
            // // ->then(new IdentifierExpression('answer'))
            // ->when($this->query->newExpr()->between('answer', 50, 100))
            // ->then("tra 50 e 100 €/mese")
            // ->when($this->query->newExpr()->between('answer', 100, 150))
            // ->then("tra 100 e 150 €/mese")
            // ->when($this->query->newExpr()->between('answer', 150, 200))
            // ->then("tra 150 e 200 €/mese")
            // ->when(['answer >' => 200])
            // ->then("più di 200 €/mese")
            // ->when(['answer not LIKE' => '%[0-9]%'])
            // ->then($this->query->identifier('answer'))
            // // ->then(new IdentifierExpression('answer'))
            // ->else('altro');

            // $this->query->select([
            //     // 'ans' =>'answer',
            //     'answer' => $costCase,
            //     // 'count' => 1
            //     'count' => $this->query->func()->count('answer')

            // ])
            // ->where(['not' => ['answer LIKE' => '%altro%']])
            // // ->group($costCase)
            // ->group(['answer'])
            // ->orderDesc($this->query->func()->count('answer'))
            // ;

            $this->query->select([
                'count' => $this->query->func()->count('answer'),
                'answer',
            ])
                ->where(['not' => ['answer LIKE' => '%altro%']])
                ->group(['answer'])
                ->orderDesc($this->query->func()->count('answer'));

            if ($default_sort && is_array($this->question->options)) {
                $this->labels = array_values($this->question->options);
                $this->series[$numSerie] = array_fill(0, count($this->labels), null);
            }
        } elseif ($this->question->type == 'array') {
            $this->query->select([
                'count' => 1,
                'answer',
            ]);
            $this->questionsOptions = $this->question->options;

            $this->series[$numSerie] = [];
        }
        // $publishedCase = $this->query->newExpr()
        // ->addCase(
        //     $this->query->newExpr()->add(['answer <=' => 10]),
        //     'meno di 30 €/mese'
        // );

    //     $unpublishedCase = $this->query->newExpr()
    //     ->case()
    // ->when(['answer <=' => 200])
    // ->then('meno di 30 €/mese')
    // ->when($this->query->newExpr()->between('answer', 200, 400))
    // ->then('tra 30 e 50 €/mese')
    // ->when(['answer >=' => 400])
    // ->then('più di 200 €/mese');

        // $this->query->select([
        // $this->query->func()->count($publishedCase),
        // ]);

        // $this->query->select([
        //     'size' =>$unpublishedCase
        //     ])
        //     ->group(['size'])
        //     ;
        $res = $this->query->toArray();
        //sqld($query);

        foreach ($res as $r) {
            //Se la risposta è vuota vado avanti
            if (is_null($r['answer'])) {
                continue;
            }

            $lbl = $r['answer'];
            if (is_array($lbl)) {
                foreach ($lbl as $k => $l) {
                    if (is_numeric($k) && $this->question->type == 'array') {
                        $l = $this->getLabelFromQuestion($k) . "| $l";
                    }
                    if (is_string($k) && $this->question->type == 'array') {
                        $l = "$k| $l";
                    }
                    if ($this->question->type == 'text') { //Devo forzare a stringa se no viene interpretato come indice numerico
                        $l = (string)$l;
                    }
                    $this->addItem($l, $r['count']);
                }
            } else {
                $this->addItem($lbl, $r['count']);
            }
        }

        foreach ($this->series as $key => $value) {
            foreach ($value as $key2 => $el) {
                if ($value > $this->question->options) {
                    if (is_null($this->series[$key][$key2])) {
                        unset($this->series[$key][$key2]);
                        unset($this->labels[$key2]);
                    }
                } else {
                    if (is_null($this->series[$key][$key2])) {
                        $this->series[$key][$key2] = 0;
                    }
                }
            }
            $this->series[$key] = array_values($this->series[$key]);
            $this->labels = array_values($this->labels);
        }
        $altro = $this->altro->toArray();

        if ($altro[0]->count > 0) {
            $this->addItem('altro', $altro[0]->count);
        }

        return $this;
    }
}

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
use Cake\Datasource\ModelAwareTrait;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;

class baseIndicator
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;

    protected $labels = [];
    protected $series = [[]];
    protected $questionType = null;
    protected $questionsOptions = null;
    protected $filterUsers = [];
    private $question = null;
    private $query = null;
    private $altro = null;
    private $Answers;
    private $Questions;

    public function __construct($slug, $survey_id = null, $office_id = null, $filters = [], $subcompany = null)
    {
        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        $this->question = $this->getQuestion($slug, $survey_id);
        $question_id = $this->question->id;

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
                                ->where(['Answers.question_id' => $filter_question->id]);
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

    public function count($default_sort)
    {
        $numSerie = 0;
        if ($this->question->type == 'single' || $this->question->type == 'multiple' || $this->question->type == 'text' || $this->question->type == 'map') {
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

    public function getList($answer)
    {
        if ($this->question->type == 'single' || $this->question->type == 'multiple' || $this->question->type == 'text') {
            if (is_numeric($answer)) {
                $answer = (float)$answer;
            }

            $this->query->select([
                'user_id', 'Users.email', 'Users.first_name', 'Users.last_name',
            ])
            ->contain(['Users'])
            ->where(['answer' => $answer]);

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

    public function getSeries()
    {
        return $this->series;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function getQuestionType()
    {
        // return $this->question->type;
        if ($this->question && is_object($this->question)) {
            return $this->question->type;
        } else {
            return null; // Return a default value or handle the situation accordingly
        }
    }

    private function getLabelFromQuestion($k)
    {
        return $this->questionsOptions['groups'][$k]['label'] ?? '--';
    }

    //Aggiunge un item nell'array delle serie

    private function addItem($label, $count)
    {
        if (is_null($label)) {
            return;
        }
        if (is_numeric($label)) {
            $k = array_search((string)$label, $this->labels);
        } else {
            $k = array_search($label, $this->labels);
        }

        if ($k === false && is_string($label)) { // Try again with all lowercase
            $k = array_search(
                strtolower($label),
                array_map(fn ($e) => strtolower(strval($e)), $this->labels)
            );
        }

        // if ($k === false && is_string($label)) {
        //     foreach ($this->labels as $i => $l) {
        //         if (is_string($l) && is_string($label)) {
        //             if (strlen($l) > 50 || strlen($label) > 50) {
        //                 $dist = levenshtein(substr($l, 0, 240), substr($label, 0, 240));
        //             } else {
        //                 $dist = levenshtein($l, $label);
        //             }
        //         } else {
        //             $dist = ($l == $label) ?  0 : 100;
        //         }

        //         if ($dist <= 1) {
        //             $k = $i;
        //             break;
        //         }
        //     }
        // }

        if ($k === false) {
            $this->labels[] = $label;
            $this->series[0][] = $count; //L'indice dell'array delle serie dev'essere vuoto e corrispondente alle label
        } else {
            $this->series[0][$k] += $count; //Se c'era già aggiungo il conteggio
        }
    }

    private function getQuestion($slug, $survey_id = null)
    {
        //Prima cerco in configurazione
        $question_id = Configure::read("Questions.$slug");

        //cerco ancora nella tabella delle question se ce n'è una con quel nome (sostituendo _ con -)
        if (empty($question_id)) {
            $slug = str_replace('_', '-', $slug);
            if ($survey_id == null) {
                $this->question = $this->Questions->find()
                ->select(['id', 'type', 'options'])
                ->where(['name' => $slug])
                ->first();
            } else {
                $this->question = $this->Questions->find()
                    ->where(['Questions.name' => $slug])
                    ->matching(
                        'QuestionsSurveys',
                        function ($q) use ($survey_id) {
                            return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
                        }
                    )
                    ->select(['Questions.id', 'Questions.type', 'QuestionsSurveys.options'])
                    ->first();
                // if matching do not exist
                if(!empty($this->question->_matchingData['QuestionsSurveys'])){
                    $this->question->options =  $this->question->_matchingData['QuestionsSurveys']->options;
                }
            }
            if (!empty($this->question)) {
                if(!empty($this->question->_matchingData['QuestionsSurveys'])){
                    $question_id = $this->question->id;
                }else{
                    $question_id = null;
                }
            } else {
                $question_id = null;
            }
        }

        if (empty($question_id)) {
            throw new NotFoundException("L'indicatore richiesto non è definito");
        }

        if (empty($this->question)) {
            if ($survey_id == null) {
                $this->question = $this->Questions->find()
                    ->select(['id', 'type', 'options'])
                    ->where(['id' => $question_id])
                    ->first();
            } else {
                $this->question = $this->Questions->find()
                    ->where(['Questions.id' => $question_id])
                    ->matching(
                        'QuestionsSurveys',
                        function ($q) use ($survey_id) {
                            return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
                        }
                    )
                    ->select(['Questions.id', 'Questions.type', 'QuestionsSurveys.options'])
                    ->first();

                $this->question->options =  $this->question->_matchingData['QuestionsSurveys']->options;
            }
        }

        return $this->question;
    }
}

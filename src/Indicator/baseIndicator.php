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
use Cake\I18n\I18n;
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

    protected function isRangeQuestion($questionName): bool
    {
        $rangeQuestions = Configure::read('Range_questions') ?? [];

        return in_array($questionName, $rangeQuestions, true);
    }

    protected function normalizeRangeToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(' ', '', $normalized);
        $normalized = str_replace('€', 'euro', $normalized);

        return $normalized;
    }

    protected function parseRangeBounds($questionName, $value): ?array
    {
        $rawValue = trim((string)$value);
        if ($rawValue === '') {
            return null;
        }

        $rangeMap = [
            'quale-distanza' => [
                '<2 km' => [0.0, 2.0],
                '2,1-5 km' => [2.1, 5.0],
                '5,1-10 km' => [5.1, 10.0],
                '10,1-15 km' => [10.1, 15.0],
                '15,1-25 km' => [15.1, 25.0],
                '25,1-50 km' => [25.1, 50.0],
                '50,1-75 km' => [50.1, 75.0],
                '75,1-100 km' => [75.1, 100.0],
                '100,1-125 km' => [100.1, 125.0],
                '125,1-150 km' => [125.1, 150.0],
                '>150 km' => [150.0, 100000.0],
            ],
            'quale-distanza-auto' => [
                '<2 km' => [0.0, 2.0],
                '2,1-5 km' => [2.1, 5.0],
                '5,1-10 km' => [5.1, 10.0],
                '10,1-15 km' => [10.1, 15.0],
                '15,1-25 km' => [15.1, 25.0],
                '25,1-50 km' => [25.1, 50.0],
                '50,1-75 km' => [50.1, 75.0],
                '75,1-100 km' => [75.1, 100.0],
                '100,1-125 km' => [100.1, 125.0],
                '125,1-150 km' => [125.1, 150.0],
                '>150 km' => [150.0, 100000.0],
            ],
            'distanza-totale' => [
                '<2 km' => [0.0, 2.0],
                '2,1-5 km' => [2.1, 5.0],
                '5,1-10 km' => [5.1, 10.0],
                '10,1-15 km' => [10.1, 15.0],
                '15,1-25 km' => [15.1, 25.0],
                '25,1-50 km' => [25.1, 50.0],
                '50,1-75 km' => [50.1, 75.0],
                '75,1-100 km' => [75.1, 100.0],
                '100,1-125 km' => [100.1, 125.0],
                '125,1-150 km' => [125.1, 150.0],
                '>150 km' => [150.0, 100000.0],
            ],
            'costo-spostamento' => [
                'meno di 30 €/mese' => [0.0, 30.0],
                'tra 30 e 50 €/mese' => [30.0, 50.0],
                'tra 50 e 100 €/mese' => [50.0, 100.0],
                'tra 100 e 150 €/mese' => [100.0, 150.0],
                'tra 150 e 200 €/mese' => [150.0, 200.0],
                'più di 200 €/mese' => [200.0, 100000.0],
            ],
            'spesa-spostamento' => [
                'Meno di 30 Euro' => [0.0, 30.0],
                'Tra 30 e 50 Euro' => [30.0, 50.0],
                'Tra 50 e 80 Euro' => [50.0, 80.0],
                'Più di 80 Euro' => [80.0, 100000.0],
            ],
        ];

        if (isset($rangeMap[$questionName][$rawValue])) {
            return $rangeMap[$questionName][$rawValue];
        }

        $normalized = $this->normalizeRangeToken($rawValue);
        if (preg_match('/^<\s*(\d+(?:[\.,]\d+)?)$/', $normalized, $m)) {
            $max = (float)str_replace(',', '.', $m[1]);

            return [0.0, $max];
        }
        if (preg_match('/^>\s*(\d+(?:[\.,]\d+)?)$/', $normalized, $m)) {
            $min = (float)str_replace(',', '.', $m[1]);

            return [$min, 100000.0];
        }
        if (preg_match('/^(\d+(?:[\.,]\d+)?)\s*[-]\s*(\d+(?:[\.,]\d+)?)$/', $normalized, $m)) {
            $min = (float)str_replace(',', '.', $m[1]);
            $max = (float)str_replace(',', '.', $m[2]);

            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            return [$min, $max];
        }
        if (is_numeric(str_replace(',', '.', $normalized))) {
            $num = (float)str_replace(',', '.', $normalized);

            return [$num, $num];
        }

        return null;
    }

    protected function toRangeBounds($questionName, $answer): ?array
    {
        if (is_numeric($answer)) {
            $num = (float)$answer;

            return [$num, $num];
        }

        return $this->parseRangeBounds($questionName, (string)$answer);
    }

    protected function rangesOverlap(array $first, array $second): bool
    {
        return $first[0] <= $second[1] && $second[0] <= $first[1];
    }

    protected function answerMatchesFilter($questionName, $filterValue, $answer): bool
    {
        $answers = is_array($answer) ? $answer : [$answer];

        if ($this->isRangeQuestion($questionName)) {
            $filterRange = $this->parseRangeBounds($questionName, (string)$filterValue);
            if ($filterRange !== null) {
                foreach ($answers as $singleAnswer) {
                    $answerRange = $this->toRangeBounds($questionName, $singleAnswer);
                    if ($answerRange !== null && $this->rangesOverlap($filterRange, $answerRange)) {
                        return true;
                    }
                }
            }
        }

        $filterAsString = (string)$filterValue;
        foreach ($answers as $singleAnswer) {
            if ((string)$singleAnswer === $filterAsString) {
                return true;
            }
        }

        return false;
    }

    public function __construct($slug, $survey_id = null, $office_id = null, $filters = [], $subcompany = null)
    {
        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        // Gli indicatori calcolano su dati canonici (le risposte sono memorizzate
        // con il testo italiano delle opzioni). Sotto una lingua non di default il
        // Translate behavior su Questions/QuestionsSurveys (e Answers→Users) farebbe
        // il LEFT JOIN della tabella i18n: questo (a) rende ambiguo il riferimento
        // `id` e (b) tradurrebbe `options`, rompendo il match risposta↔opzione in
        // addItem(). Forziamo quindi la lingua di default su questi find: con
        // locale == defaultLocale, EavStrategy::beforeFind esce subito (nessun join).
        // Le label vengono tradotte a parte, per la sola visualizzazione, in
        // AnswersController::translateIndicatorLabels(). Vedi nota i18n in CLAUDE.md.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        TableRegistry::getTableLocator()->get('QuestionsSurveys')->setLocale($defaultLocale);

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

        // Normalize office id: convert string 'null' to real null
        if (is_string($office_id) && strtolower($office_id) === 'null') {
            $office_id = null;
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
                if ($survey_id == null) {
                    $filter_question = $this->Questions->find()
                        ->where(['Questions.name' => $qst_name])
                        ->first();
                } else {
                    $filter_question = $this->Questions->find()
                        ->where(['Questions.name' => $qst_name])
                        ->matching(
                            'QuestionsSurveys',
                            function ($q) use ($survey_id) {
                                return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
                            }
                        )
                        ->first();

                    if (
                        !empty($filter_question) &&
                        !empty($filter_question->_matchingData['QuestionsSurveys'])
                    ) {
                        $filter_question->options = $filter_question->_matchingData['QuestionsSurveys']->options;
                    }
                }

                //Se ho trovato la domanda filtro, altrimenti non faccio nulla
                if (!empty($filter_question)) {
                    $answersToMatch = [(string)$answ];
                    if (
                        $filter_question->type === 'likert' &&
                        is_array($filter_question->options)
                    ) {
                        $likertOptions = array_values($filter_question->options);
                        $likertIndex = null;

                        if (is_numeric($answ)) {
                            $likertIndex = (int)$answ;
                        } else {
                            $likertIndex = array_search((string)$answ, array_map('strval', $likertOptions), true);
                            if ($likertIndex === false) {
                                $likertIndex = array_search(
                                    strtolower((string)$answ),
                                    array_map(fn($v) => strtolower((string)$v), $likertOptions),
                                    true
                                );
                            }
                        }

                        if ($likertIndex !== false && $likertIndex !== null) {
                            $answersToMatch[] = (string)$likertIndex;
                        }
                    }

                    $answ_usr = $this->Answers->find()
                        ->select(['Answers.user_id', 'Answers.answer'])
                        ->where(['Answers.question_id' => $filter_question->id]);
                    //->where(['Answers.answer ' => "%$answ%" ]);
                    //sqld($answ_usr);
                    //Purtroppo non posso usare il filtro in SQL perchè il valore è memorizzato in json e sbaglia
                    //Devo iterare sui risultati ;-(
                    foreach ($answ_usr as $a) {
                        foreach ($answersToMatch as $answerToMatch) {
                            if ($this->answerMatchesFilter($qst_name, $answerToMatch, $a->answer)) {
                                $answ_usr_ids_2[] = $a->user_id;
                                break;
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
        } elseif ($this->question->type == 'likert') {
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
                // For Likert questions, map numeric index to its text label
                if (
                    $this->question->type === 'likert' &&
                    is_numeric($lbl) &&
                    is_array($this->question->options) &&
                    isset($this->question->options[(int)$lbl])
                ) {
                    $lbl = $this->question->options[(int)$lbl];
                }
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
                'user_id',
                'Users.email',
                'Users.first_name',
                'Users.last_name',
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
                array_map(fn($e) => strtolower(strval($e)), $this->labels)
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
            $this->series[0][$k] += $count; //Altrimenti sommo il conteggio
        }
    }

    private function getQuestion($slug, $survey_id = null)
    {
        //Prima cerco in configurazione
        $question_id = Configure::read("Questions.$slug");

        //cerco ancora nella tabella delle question se ce n'è una con quel nome.
        //Provo sia lo slug così com'è (es. carpool_paglieri2) sia la variante con _ -> -
        //(es. ripartizione-modale) perché in DB convivono entrambe le convenzioni.
        if (empty($question_id)) {
            $slugVariants = array_values(array_unique([$slug, str_replace('_', '-', $slug)]));
            if ($survey_id == null) {
                $this->question = $this->Questions->find()
                    ->select(['Questions.id', 'Questions.type', 'Questions.options'])
                    ->where(['Questions.name IN' => $slugVariants])
                    ->first();
            } else {
                $this->question = $this->Questions->find()
                    ->where(['Questions.name IN' => $slugVariants])
                    ->matching(
                        'QuestionsSurveys',
                        function ($q) use ($survey_id) {
                            return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
                        }
                    )
                    ->select(['Questions.id', 'Questions.type', 'QuestionsSurveys.options'])
                    ->first();
                // if matching do not exist
                if (!empty($this->question->_matchingData['QuestionsSurveys'])) {
                    $this->question->options =  $this->question->_matchingData['QuestionsSurveys']->options;
                }
            }
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
            if ($survey_id == null) {
                $this->question = $this->Questions->find()
                    ->select(['Questions.id', 'Questions.type', 'Questions.options'])
                    ->where(['Questions.id' => $question_id])
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

                if (empty($this->question)) {
                    return $this->Questions->newEmptyEntity();
                }

                if (
                    isset($this->question->_matchingData['QuestionsSurveys']) &&
                    isset($this->question->_matchingData['QuestionsSurveys']->options)
                ) {
                    $this->question->options = $this->question->_matchingData['QuestionsSurveys']->options;
                } else {
                    $this->question->options = [];
                }
            }
        }

        return $this->question;
    }
}

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
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\ModelAwareTrait;
use Cake\I18n\I18n;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;

/**
 * Split Modale Secondo il modello usato da BresciaMobilità, raggruppato per
 * range di distanza percorsa (domanda "quale-distanza", stessi bucket usati
 * da RangeIndicator::rangeQuestions()).
 *
 * getLabels() restituisce i range di distanza (asse X, sempre nell'ordine
 * canonico delle opzioni della domanda "quale-distanza"); getSeriesLabels()
 * restituisce il nome di ciascuna serie (la modalità di trasporto a cui quella
 * riga di getSeries() si riferisce), ordinate per totale utenti decrescente.
 * Esempio: getSeriesLabels() = ['Auto come conducente o moto', 'bici o
 * monopattino', ...] e getSeries()[0] = conteggi di "Auto come conducente o
 * moto" per ciascun range di getLabels() (0-2km, 2-5km, ...).
 *
 * @package App\Indicator
 */
class splitBSKMIndicator
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;
    use SplitBSModalMappingTrait;

    protected $labels = [];
    protected $series = [[]];
    protected $seriesLabels = [];
    protected $questionType = null;
    protected $questionsOptions = null;
    protected $filterUsers = [];
    private $question = null;
    private $Answers;
    private $Questions;
    private $filters = [];
    private $subcompany = null;
    private $whereClause = [];
    private $bindings = [];

    public function __construct($survey_id = null, $office_id = null, $filters = [], $subcompany = null)
    {

        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');

        // Indicatore su dati canonici: forzo la lingua di default per evitare il
        // join i18n del Translate behavior (id ambiguo + opzioni tradotte). Vedi
        // baseIndicator e la nota i18n in CLAUDE.md.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $QuestionsSurveys->setLocale($defaultLocale);

        //Prendo la domanda mappa dalla configurazione
        //Select from questions where type= 'map'
        $question_map_id = $this->Questions->find()
            ->select(['Questions.id'])
            ->where(['type' => 'map'])
            ->all();

        //Check in questions_surveys if the question is enabled for the survey
        $question_id = $QuestionsSurveys->find()
            ->where(['question_id IN' => $question_map_id->extract('id')->toList(), 'survey_id' => $survey_id])
            ->first();
        //Se non c'è la domanda mappa uso la vecchia domanda mezzo, prendendo da config::
        $question_id = $question_id ? $question_id->question_id : Configure::read('Questions.mezzo');

        // Costruiamo il WHERE dinamicamente
        $wheres[] = 'a.question_id = :question_id';
        $this->bindings['question_id'] = (int)$question_id;

        // Domanda "quale-distanza": stessa domanda usata da RangeIndicator per
        // raggruppare i chilometri percorsi in range. Cerco prima in
        // configurazione, poi per nome (con o senza trattino) eventualmente
        // filtrata sulla survey corrente, esattamente come fa
        // RangeIndicator::getQuestion().
        $distance_question_id = Configure::read('Questions.quale-distanza');
        if (empty($distance_question_id)) {
            $distanceQuery = $this->Questions->find()
                ->select(['Questions.id'])
                ->where(['Questions.name IN' => ['quale-distanza', 'quale_distanza']]);
            if (!empty($survey_id)) {
                $distanceQuery->matching('QuestionsSurveys', function ($q) use ($survey_id) {
                    return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
                });
            }
            $distanceQuestion = $distanceQuery->first();
            $distance_question_id = $distanceQuestion ? $distanceQuestion->id : null;
        }
        $this->bindings['distance_question_id'] = (int)$distance_question_id;

        if (!empty($survey_id)) {
            $wheres[] = 'a.survey_id = :survey_id';
            $this->bindings['survey_id'] = (int)$survey_id;
        }

        // Gestione office_id e subcompany tramite subquery IN
        if (!empty($office_id) || !empty($subcompany)) {
            $userConditions = [];

            if (!empty($office_id)) {
                $userConditions[] = 'u.office_id = :office_id';
                $this->bindings['office_id'] = (int)$office_id;
            }

            if (!empty($subcompany)) {
                $userConditions[] = 'u.subcompany = :subcompany';
                $this->bindings['subcompany'] = (string)$subcompany;
            }

            $userWhereClause = implode(' AND ', $userConditions);
            $wheres[] = "a.user_id IN (SELECT id FROM users u WHERE {$userWhereClause})";
        }

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
                }
            }
        }

        if (!empty($answ_usr_ids)) {
            // user_id IN (...)
            $inClause = implode(', ', array_fill(0, count($answ_usr_ids), '?'));
            $wheres[] = "a.id IN ($inClause)";
            $this->bindings = array_merge($this->bindings, $answ_usr_ids);
        }

        $this->whereClause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        return $this;
    }

    public function count($default_sort)
    {
        $connection = ConnectionManager::get('default');

        // Se è impostato un filtro sulla survey, applico lo stesso vincolo
        // anche alla risposta "quale-distanza" per non prendere la distanza
        // dichiarata dallo stesso utente in una survey diversa.
        $distanceJoinSurvey = isset($this->bindings['survey_id']) ? ' AND d.survey_id = :survey_id' : '';

        $caseSql = self::splitBsCategoryCaseSql();
        $allMeansSql = self::splitBsAllMeansSql();
        $legColumns = self::splitBsLegColumnsSql();

        $sql = <<<SQL
            SELECT
                {$caseSql} AS tipo_trasporto,
                distanza,
                SUM(total_utenti) AS total_utenti
            FROM (
                    SELECT
                        all_means, distanza, COUNT(*) AS total_utenti
                    FROM (
                            SELECT
                                a.user_id, {$allMeansSql} AS all_means,
                                MAX(JSON_UNQUOTE(d.answer)) AS distanza
                            FROM answers a
                                CROSS JOIN JSON_TABLE(
                                    JSON_EXTRACT(IF(JSON_VALID(a.answer), a.answer, '{}'), '$.modes'), '$[*]' COLUMNS (
                                        {$legColumns}
                                    )
                                ) AS modes
                                LEFT JOIN answers d
                                    ON d.user_id = a.user_id
                                    AND d.question_id = :distance_question_id
                                    {$distanceJoinSurvey}
                                {$this->whereClause}
                            GROUP BY
                                a.user_id
                        ) AS combinazioni
                    WHERE
                        all_means IS NOT NULL
                    GROUP BY
                        all_means, distanza
                ) AS sub
            GROUP BY
                tipo_trasporto, distanza
            ORDER BY tipo_trasporto;
        SQL;
        //debug($sql);  die();

        // Eseguo la query
        $res = $connection->execute($sql, $this->bindings)->fetchAll('assoc');

        // Le label sulle distanze percorse ricalcano esattamente le opzioni
        // canoniche della domanda "quale-distanza" (vedi config/Seeds/QuestionsSeed.php),
        // incluso il bucket "Dati non validi" per risposte mancanti o non numeriche.
        $this->labels = [
            '<2 km', '2,1-5 km', '5,1-10 km', '10,1-15 km', '15,1-25 km',
            '25,1-50 km', '50,1-75 km', '75,1-100 km', '100,1-125 km',
            '125,1-150 km', '>150 km', 'Dati non validi',
        ];

        // Accumulo i conteggi in una matrice [tipo_trasporto][range_distanza] = utenti
        $counts = [];
        foreach ($res as $row) {
            $tipo = $row['tipo_trasporto'];
            $rangeLabel = $this->resolveDistanceRangeLabel($row['distanza']);
            $counts[$tipo][$rangeLabel] = ($counts[$tipo][$rangeLabel] ?? 0) + (int)$row['total_utenti'];
        }

        // Ordino le serie (modalità di trasporto) per totale utenti decrescente,
        // come faceva la ORDER BY total_utenti DESC della versione precedente.
        $totalsByType = array_map('array_sum', $counts);
        arsort($totalsByType);

        $this->seriesLabels = array_keys($totalsByType);
        $this->series = [];
        foreach ($this->seriesLabels as $i => $tipo) {
            $this->series[$i] = array_map(
                fn($label) => $counts[$tipo][$label] ?? 0,
                $this->labels
            );
        }

        return $this;
    }

    /**
     * Mappa il valore grezzo della risposta "quale-distanza" su uno dei bucket
     * canonici di getLabels(). La domanda è di tipo 'single' con opzioni già
     * uguali ai bucket (es. "10,1-15 km"): in quel caso il valore torna
     * invariato. Se invece arriva un valore numerico (km), applico le stesse
     * soglie di RangeIndicator::rangeQuestions() per l'indicatore
     * 'quale-distanza'. Qualsiasi altro caso (risposta mancante o non
     * riconosciuta) finisce nel bucket "Dati non validi".
     *
     * @param mixed $distanza Valore grezzo della risposta "quale-distanza"
     * @return string Etichetta del bucket, tra quelle di getLabels()
     */
    private function resolveDistanceRangeLabel($distanza)
    {
        if ($distanza === null || $distanza === '') {
            return 'Dati non validi';
        }

        if (in_array($distanza, $this->labels, true)) {
            return $distanza;
        }

        if (is_numeric($distanza)) {
            $km = (float)$distanza;
            if ($km <= 2) {
                return '<2 km';
            } elseif ($km <= 5) {
                return '2,1-5 km';
            } elseif ($km <= 10) {
                return '5,1-10 km';
            } elseif ($km <= 15) {
                return '10,1-15 km';
            } elseif ($km <= 25) {
                return '15,1-25 km';
            } elseif ($km <= 50) {
                return '25,1-50 km';
            } elseif ($km <= 75) {
                return '50,1-75 km';
            } elseif ($km <= 100) {
                return '75,1-100 km';
            } elseif ($km <= 125) {
                return '100,1-125 km';
            } elseif ($km <= 150) {
                return '125,1-150 km';
            }

            return '>150 km';
        }

        return 'Dati non validi';
    }

    public function getList($answer)
    {
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

    /**
     * Nome di ciascuna serie (modalità di trasporto), nello stesso ordine di
     * getSeries(): getSeriesLabels()[$i] è il nome della serie getSeries()[$i].
     *
     * @return string[]
     */
    public function getSeriesLabels()
    {
        return $this->seriesLabels;
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
}

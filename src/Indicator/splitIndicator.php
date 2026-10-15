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

class splitIndicator
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
    private $Answers;
    private $Questions;
    private $filters = [];
    private $subcompany = null;
    private $whereClause = [];
    private $bindings = [];

    public function __construct($survey_id = null, $office_id = null, $filters = [], $subcompany = null, $year = null)
    {

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
        $question_id = empty($survey_id) ? null : $QuestionsSurveys->find()
            ->where(['question_id IN' => $question_map_id->extract('id')->toList(), 'survey_id' => $survey_id])
            ->first();
        //Se non c'è la domanda mappa uso la vecchia domanda mezzo, prendendo da config::
        $question_id = $question_id ? $question_id->question_id : Configure::read('Questions.mezzo');

        // Costruiamo il WHERE dinamicamente
        if (empty($survey_id)) {
            // Nessun questionario (es. dashboard senza azienda scelta): non c'è una
            // singola domanda mappa da leggere, quindi aggrego su TUTTE le domande
            // mappa. Il fallback legacy Questions.mezzo qui darebbe sempre vuoto:
            // le sue risposte sono testo libero, non il JSON {modes: [...]} letto
            // dalla query.
            $mapIds = array_map('intval', $question_map_id->extract('id')->toList());
            $wheres[] = 'a.question_id IN (' . ($mapIds ? implode(', ', $mapIds) : '0') . ')';
            // Senza questionario l'anno scelto delimita i questionari aggregati
            // (con un questionario l'anno e' gia' stato applicato a monte).
            if (!empty($year)) {
                $wheres[] = 'a.survey_id IN (SELECT id FROM surveys WHERE year = :year)';
                $this->bindings['year'] = (int)$year;
            }
        } else {
            $wheres[] = 'a.question_id = :question_id';
            $this->bindings['question_id'] = (int)$question_id;
        }

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

        $sql = <<<SQL
        SELECT
            CASE
                WHEN num_mezzi = 1 THEN all_means
                WHEN all_means LIKE '%Auto%'
                AND (
                    all_means LIKE '%Bus/Tram%'
                    OR all_means LIKE '%Metro%'
                    OR all_means LIKE '%Train%'
                ) THEN 'Intermodale Auto+TPL'
                WHEN all_means LIKE '%Auto%'
                AND all_means LIKE '%Bike%'
                AND all_means NOT LIKE '%Bus/Tram%'
                AND all_means NOT LIKE '%Metro%'
                AND all_means NOT LIKE '%Train%' THEN 'Intermodale Auto+Bici'
                WHEN (
                    all_means LIKE '%Moto%'
                    OR all_means LIKE '%Scooter%'
                )
                AND num_mezzi > 1 THEN 'Intermodale con Moto/Scooter'
                WHEN all_means NOT LIKE '%Auto%'
                AND all_means LIKE '%Bike%'
                AND (
                    (all_means LIKE '%Bus/Tram%') + (all_means LIKE '%Metro%') + (all_means LIKE '%Train%')
                ) >= 1 THEN 'Intermodale TPL+Bici'
                WHEN all_means NOT LIKE '%Auto%'
                AND all_means NOT LIKE '%Bike%'
                AND (
                    (all_means LIKE '%Bus/Tram%') + (all_means LIKE '%Metro%') + (all_means LIKE '%Train%')
                ) >= 2 THEN 'Intermodale vari TPL'
                ELSE 'altro'
            END AS tipo_trasporto,
            SUM(total_utenti) AS total_utenti
        FROM (
                SELECT
                    all_means, LENGTH(all_means) - LENGTH(
                        REPLACE (all_means, '+', '')
                    ) + 1 AS num_mezzi, COUNT(*) AS total_utenti
                FROM (
                        SELECT
                            a.user_id, CASE
                                WHEN COUNT(
                                    CASE
                                        WHEN modes.selTransportmean != 'on_foot' THEN 1
                                    END
                                ) = 0 THEN GROUP_CONCAT(
                                    DISTINCT modes.selTransportmean
                                    ORDER BY modes.selTransportmean SEPARATOR '+'
                                )
                                ELSE GROUP_CONCAT(
                                    DISTINCT CASE
                                        WHEN modes.selTransportmean != 'on_foot' THEN modes.selTransportmean
                                    END
                                    ORDER BY modes.selTransportmean SEPARATOR '+'
                                )
                            END AS all_means
                        FROM answers a, JSON_TABLE(
                                JSON_EXTRACT(IF(JSON_VALID(a.answer), a.answer, '{}'), '$.modes'), '$[*]' COLUMNS (
                                    selTransportmean VARCHAR(50) PATH '$.selTransportmean'
                                )
                            ) AS modes
                        
                            {$this->whereClause}
                        GROUP BY
                            a.user_id
                    ) AS combinazioni
                WHERE
                    all_means IS NOT NULL
                GROUP BY
                    all_means
            ) AS sub
        GROUP BY
            tipo_trasporto
        ORDER BY total_utenti DESC;
        SQL;
        //debug($sql);  die();

        // Eseguo la query
        $res = $connection->execute($sql, $this->bindings)->fetchAll('assoc');
        foreach ($res as $row) {
            $this->labels[] = $row['tipo_trasporto'];
            $this->series[0][] = (int)$row['total_utenti'];
        }

        return $this;
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

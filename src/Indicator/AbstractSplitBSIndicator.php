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
 * Base comune per gli indicatori "split modale" secondo il modello usato da
 * BresciaMobilità (vedi splitBSKMIndicator, di cui questa classe generalizza
 * la logica), che affiancano alla ripartizione modale una seconda dimensione
 * di analisi: distanza percorsa (splitBSKMIndicator, non derivata da questa
 * base), tipologia di orario di lavoro, soddisfazione del viaggio, durata
 * dello spostamento, comune/quartiere di domicilio.
 *
 * Le sottoclassi definiscono SOLO:
 * - resolveSecondaryDimension(): risolve/valorizza i binding necessari alla
 *   seconda dimensione (es. l'id di una domanda secondaria);
 * - buildSecondaryJoinSql(): il LEFT JOIN SQL che porta il valore grezzo
 *   della seconda dimensione (alias risultante: `dimval`);
 * - getSecondaryValueExpr(): l'espressione SQL aggregata (es. MAX(...)) che
 *   estrae quel valore per utente;
 * - mapRawToLabel(): la mappatura valore grezzo -> etichetta canonica;
 * - canonicalLabels(): l'elenco fisso delle etichette (asse X), oppure
 *   null quando le etichette non sono note a priori (es. comune/quartiere,
 *   valori liberi) — in quel caso count() le deriva dai dati, ordinate per
 *   totale utenti decrescente con "Dati non validi" sempre in coda.
 *
 * getLabels() restituisce le etichette della seconda dimensione (asse X);
 * getSeriesLabels() il nome di ciascuna serie (la modalità di trasporto a cui
 * quella riga di getSeries() si riferisce), ordinate per totale utenti
 * decrescente, esattamente come in splitBSKMIndicator.
 *
 * @package App\Indicator
 */
abstract class AbstractSplitBSIndicator
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
    protected $question = null;
    protected $Answers;
    protected $Questions;
    protected $filters = [];
    protected $subcompany = null;
    protected $whereClause = [];
    protected $bindings = [];

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

        // Solo questionari completati: escludo chi ha una riga partecipante non conclusa
        // (survey_completed_at NULL). Chi non ha riga partecipante (anonimo/legacy) resta incluso.
        $wheres[] = 'NOT EXISTS (SELECT 1 FROM survey_participants sp WHERE sp.user_id = a.user_id'
            . ' AND sp.survey_id = a.survey_id AND sp.survey_completed_at IS NULL)';

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

        // Hook di sottoclasse: valorizza i binding della seconda dimensione
        // (es. l'id della domanda secondaria). Deve girare dopo che
        // $this->bindings['survey_id'] è stato eventualmente impostato sopra,
        // perché buildSecondaryJoinSql() lo controlla per scopare il join
        // sulla stessa survey.
        $this->resolveSecondaryDimension($survey_id);

        return $this;
    }

    /**
     * Risolve/valorizza i binding necessari alla seconda dimensione (es.
     * l'id di una domanda secondaria), letti da buildSecondaryJoinSql().
     *
     * @param int|string|null $survey_id Id survey richiesto (per lo scoping opzionale).
     * @return void
     */
    abstract protected function resolveSecondaryDimension($survey_id): void;

    /**
     * SQL del LEFT JOIN sulla seconda dimensione, con eventuali placeholder
     * :binding definiti in resolveSecondaryDimension(). Deve unirsi su
     * `a.user_id` (alias della subquery per-utente della query principale) e
     * produrre le righe da cui getSecondaryValueExpr() estrae il valore
     * grezzo.
     *
     * @return string
     */
    abstract protected function buildSecondaryJoinSql(): string;

    /**
     * Espressione SQL (aggregata, es. MAX(...)) che produce il valore grezzo
     * della seconda dimensione per utente; viene aliasata come `dimval`.
     *
     * @return string
     */
    abstract protected function getSecondaryValueExpr(): string;

    /**
     * Mappa il valore grezzo di `dimval` su un'etichetta canonica. Deve
     * tornare 'Dati non validi' per valori mancanti/non riconosciuti.
     *
     * @param mixed $raw Valore grezzo prodotto da getSecondaryValueExpr().
     * @return string
     */
    abstract protected function mapRawToLabel($raw): string;

    /**
     * Elenco fisso delle etichette canoniche (asse X), nell'ordine in cui
     * devono comparire in getLabels(). Torna null quando le etichette non
     * sono note a priori (es. comune/quartiere di domicilio): in quel caso
     * count() le deriva dai dati, ordinate per totale utenti decrescente con
     * "Dati non validi" sempre in coda (quando presente).
     *
     * @return string[]|null
     */
    abstract protected function canonicalLabels(): ?array;

    /**
     * Helper riutilizzabile dalle sottoclassi "domanda secondaria a scelta
     * singola" (orario-lavorativo, valutazione-viaggio, durata-spostamento):
     * risolve l'id domanda prima da Configure (`Questions.$configKey`), poi
     * per nome (eventualmente scoped alla survey), stesso pattern di
     * splitBSKMIndicator/RangeIndicator::getQuestion() per "quale-distanza".
     *
     * @param string $configKey Chiave Configure `Questions.<configKey>`.
     * @param string[] $names Possibili nomi della domanda (es. con/senza trattino).
     */
    protected function resolveQuestionIdByNameOrConfig(string $configKey, array $names, $survey_id): ?int
    {
        $question_id = Configure::read("Questions.$configKey");
        if (!empty($question_id)) {
            return (int)$question_id;
        }

        $query = $this->Questions->find()
            ->select(['Questions.id'])
            ->where(['Questions.name IN' => $names]);
        if (!empty($survey_id)) {
            $query->matching('QuestionsSurveys', function ($q) use ($survey_id) {
                return $q->where(['QuestionsSurveys.survey_id' => $survey_id]);
            });
        }
        $question = $query->first();

        return $question ? (int)$question->id : null;
    }

    public function count($default_sort)
    {
        $connection = ConnectionManager::get('default');

        $secondaryJoin = $this->buildSecondaryJoinSql();
        $secondaryValueExpr = $this->getSecondaryValueExpr();

        $caseSql = self::splitBsCategoryCaseSql();
        $allMeansSql = self::splitBsAllMeansSql();
        $legColumns = self::splitBsLegColumnsSql();

        $sql = <<<SQL
            SELECT
                {$caseSql} AS tipo_trasporto,
                dimval,
                SUM(total_utenti) AS total_utenti
            FROM (
                    SELECT
                        all_means, dimval, COUNT(*) AS total_utenti
                    FROM (
                            SELECT
                                a.user_id, {$allMeansSql} AS all_means,
                                {$secondaryValueExpr} AS dimval
                            FROM answers a
                                CROSS JOIN JSON_TABLE(
                                    JSON_EXTRACT(IF(JSON_VALID(a.answer), a.answer, '{}'), '$.modes'), '$[*]' COLUMNS (
                                        {$legColumns}
                                    )
                                ) AS modes
                                {$secondaryJoin}
                                {$this->whereClause}
                            GROUP BY
                                a.user_id
                        ) AS combinazioni
                    WHERE
                        all_means IS NOT NULL
                    GROUP BY
                        all_means, dimval
                ) AS sub
            GROUP BY
                tipo_trasporto, dimval
            ORDER BY tipo_trasporto;
        SQL;

        // Eseguo la query
        $res = $connection->execute($sql, $this->bindings)->fetchAll('assoc');

        // Accumulo i conteggi in una matrice [tipo_trasporto][etichetta] = utenti
        $counts = [];
        foreach ($res as $row) {
            $tipo = $row['tipo_trasporto'];
            $label = $this->mapRawToLabel($row['dimval']);
            $counts[$tipo][$label] = ($counts[$tipo][$label] ?? 0) + (int)$row['total_utenti'];
        }

        $labels = $this->canonicalLabels();
        if ($labels === null) {
            // Etichette non note a priori (es. comune/quartiere): le derivo
            // dai dati, ordinate per totale utenti decrescente, con "Dati non
            // validi" sempre spostato in coda quando presente.
            $totals = [];
            foreach ($counts as $byLabel) {
                foreach ($byLabel as $label => $n) {
                    $totals[$label] = ($totals[$label] ?? 0) + $n;
                }
            }
            $hasInvalid = array_key_exists('Dati non validi', $totals);
            unset($totals['Dati non validi']);
            arsort($totals);
            $labels = array_keys($totals);
            if ($hasInvalid) {
                $labels[] = 'Dati non validi';
            }
        }
        $this->labels = $labels;

        // Ordino le serie (modalità di trasporto) per totale utenti decrescente,
        // come in splitBSKMIndicator.
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

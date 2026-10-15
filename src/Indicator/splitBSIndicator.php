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
 * Split Modale Secondo il modello usato da BresciaMobilità
 *
 * @package App\Indicator
 */
class splitBSIndicator
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;
    use SplitBSModalMappingTrait;

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
    private $office_id = null;
    private ?int $respondents = null;
    private ?int $year = null;
    private ?int $companyId = null;
    private bool $requireCompanyScope = true;
    private string $source = 'live';
    private ?int $aggregatedYear = null;

    /**
     * Mappa tra le chiavi di offices.aggregated_survey.splitModale (dati aggregati
     * caricati manualmente, es. indagini non gestite direttamente su questa
     * piattaforma) e le etichette canoniche prodotte dalla query "live" su answers
     * in count(). Usata solo come fallback quando la query live non restituisce
     * righe: vedi il blocco in count(). Tenere allineata alle label prodotte dal
     * CASE SQL più sotto.
     */
    private const SPLIT_MODALE_LABEL_MAP = [
        'aPiedi' => 'aPiedi',
        'bici' => 'bici o monopattino',
        'autoConducente' => 'Auto come conducente o moto',
        'autoPasseggero' => 'Auto come passeggero o in carpooling',
        'navetta' => 'navetta',
        'trasportoPubblico' => 'TPL',
        'autoTpl' => 'Auto, moto o Auto come passeggero o in carpooling + TPL',
        'altro' => 'altro',
    ];

    public function __construct($survey_id = null, $office_id = null, $filters = [], $subcompany = null, $year = null)
    {
        // Anno opzionale (solo per la scheda aggregata): vuoto/0/'TUTTI' = nessun filtro
        $this->year = !empty($year) && is_numeric($year) ? (int)$year : null;

        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $this->office_id = !empty($office_id) ? (int)$office_id : null;

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

        // Senza survey (ne' azienda) ma con un anno: aggrego TUTTE le survey di quell'anno.
        // La domanda mappa puo' variare per survey: uso tutte le domande mappa abilitate
        // nelle survey di quell'anno (Questions.mezzo solo se non ce ne sono).
        $yearScope = empty($survey_id) && !empty($this->year);
        $questionIds = [];
        if ($yearScope) {
            $yearSurveyIds = TableRegistry::getTableLocator()->get('Surveys')->find()
                ->select(['Surveys.id'])
                ->where(['Surveys.year' => $this->year]);
            $questionIds = $QuestionsSurveys->find()
                ->select(['QuestionsSurveys.question_id'])
                ->distinct(['QuestionsSurveys.question_id'])
                ->where([
                    'QuestionsSurveys.question_id IN' => $question_map_id->extract('id')->toList() ?: [0],
                    'QuestionsSurveys.survey_id IN' => $yearSurveyIds,
                ])
                ->all()
                ->extract('question_id')
                ->map(fn($id) => (int)$id)
                ->toList();
        }

        // Costruiamo il WHERE dinamicamente
        if (!empty($questionIds)) {
            $wheres[] = 'a.question_id IN (' . implode(', ', $questionIds) . ')';
        } else {
            //Check in questions_surveys if the question is enabled for the survey
            $question_id = $QuestionsSurveys->find()
                ->where(['question_id IN' => $question_map_id->extract('id')->toList(), 'survey_id' => $survey_id])
                ->first();
            //Se non c'è la domanda mappa uso la vecchia domanda mezzo, prendendo da config::
            $question_id = $question_id ? $question_id->question_id : Configure::read('Questions.mezzo');
            $wheres[] = 'a.question_id = :question_id';
            $this->bindings['question_id'] = (int)$question_id;
        }

        // Solo questionari completati: escludo chi ha una riga partecipante non conclusa
        // (survey_completed_at NULL). Chi non ha riga partecipante (anonimo/legacy) resta incluso.
        $wheres[] = 'NOT EXISTS (SELECT 1 FROM survey_participants sp WHERE sp.user_id = a.user_id'
            . ' AND sp.survey_id = a.survey_id AND sp.survey_completed_at IS NULL)';

        if (!empty($survey_id)) {
            $wheres[] = 'a.survey_id = :survey_id';
            $this->bindings['survey_id'] = (int)$survey_id;
        } elseif ($yearScope) {
            $wheres[] = 'a.survey_id IN (SELECT sy.id FROM surveys sy WHERE sy.year = :year)';
            $this->bindings['year'] = $this->year;
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

        $caseSql = self::splitBsCategoryCaseSql();
        $allMeansSql = self::splitBsAllMeansSql();
        $legColumns = self::splitBsLegColumnsSql();

        $sql = <<<SQL
            SELECT
                {$caseSql} AS tipo_trasporto,
                SUM(total_utenti) AS total_utenti
            FROM (
                    SELECT
                        all_means, COUNT(*) AS total_utenti
                    FROM (
                            SELECT
                                a.user_id, {$allMeansSql} AS all_means
                            FROM answers a, JSON_TABLE(
                                    JSON_EXTRACT(IF(JSON_VALID(a.answer), a.answer, '{}'), '$.modes'), '$[*]' COLUMNS (
                                        {$legColumns}
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
        // Rispondenti reali (utenti distinti con risposta completata): solo sul percorso live
        $this->respondents = array_sum($this->series[0] ?? []);

        // Doppio funzionamento: se la query "live" su answers non restituisce
        // nulla (nessuna risposta alla domanda mappa/mezzo per l'ufficio/survey
        // richiesti), ripiego sui dati aggregati caricati manualmente su
        // offices.aggregated_survey.splitModale (es. indagini BresciaMobilità
        // gestite fuori da questa piattaforma). Richiede un office_id: senza,
        // non c'è un singolo record aggregated_survey da cui attingere.
        if (empty($this->labels) && !empty($this->office_id)) {
            $this->loadAggregatedOnly();
        }

        return $this;
    }

    /**
     * Limita la scheda aggregata a un'azienda: se impostata, l'ufficio letto
     * dal fallback deve appartenere a quell'azienda (altrimenti nessun dato).
     *
     * @param int|null $companyId Id azienda
     * @return $this
     */
    public function setCompanyId(?int $companyId)
    {
        $this->companyId = $companyId ?: null;

        return $this;
    }

    /**
     * Fail-closed per default: la scheda aggregata di una sede viene letta solo se e' stato
     * impostato un company id (setCompanyId()) a cui la sede appartiene. Senza company id
     * il fallback non restituisce nulla. Solo i chiamanti fidati/privilegiati (admin, moma_area,
     * moma senza azienda decisi dal controller; esportatori interni; test) possono disattivare
     * l'obbligo con requireCompanyScope(false): in tal caso, senza company id, nessun controllo.
     *
     * @param bool $required Se true (default) il company id e' obbligatorio per il fallback aggregato
     * @return $this
     */
    public function requireCompanyScope(bool $required = true)
    {
        $this->requireCompanyScope = $required;

        return $this;
    }

    /**
     * Carica SOLO la scheda aggregata (nessuna query live su answers): usato sia dal
     * fallback di count() sia quando per l'anno richiesto non esiste alcuna survey ma
     * l'ufficio ha una scheda aggregata per quell'anno. Richiede office_id.
     * I valori aggregati sono percentuali, non un conteggio: total resta null.
     *
     * @param bool $strictYear Se true (nessuna survey per l'anno) legge SOLO la riga di storia
     *   dell'anno, mai il valore corrente di offices.aggregated_survey (sarebbe un altro anno).
     * @return $this
     */
    public function loadAggregatedOnly(bool $strictYear = false)
    {
        $this->respondents = null;
        if (!empty($this->office_id)) {
            $this->loadFromAggregatedSurvey($strictYear);
            if (!empty($this->labels)) {
                $this->source = 'aggregated';
            }
        }

        return $this;
    }

    /**
     * Origine dei dati: 'live' (risposte su answers) o 'aggregated' (scheda aggregata).
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Anno (colonna `year` della riga di company_survey_history) della scheda aggregata
     * usata; null sul percorso live o se i dati vengono dal valore corrente di offices.aggregated_survey.
     *
     * @return int|null
     */
    public function getAggregatedYear(): ?int
    {
        return $this->aggregatedYear;
    }

    /**
     * Fallback: costruisce labels/series dalla scheda aggregata dell'ufficio (colonna json,
     * popolata manualmente tramite OfficesController::saveAggregated()).
     * Con un anno richiesto legge company_survey_history (type='aggregated', azienda + ufficio
     * + anno, riga piu' recente per `created`; l'anno riportato e' la colonna `year` della riga,
     * non `anno` nel JSON); se non c'e' la riga per quell'anno (o nessun anno), ripiega sul valore
     * corrente di offices.aggregated_survey. I valori di splitModale sono percentuali (sommano a
     * 100): li uso direttamente come "series".
     *
     * @param bool $strictYear Vedi loadAggregatedOnly()
     * @return void
     */
    private function loadFromAggregatedSurvey(bool $strictYear = false): void
    {
        $Offices = TableRegistry::getTableLocator()->get('Offices');
        $office = $Offices->find()
            ->select(['Offices.id', 'Offices.company_id', 'Offices.aggregated_survey'])
            ->where(['Offices.id' => $this->office_id])
            ->first();

        if (empty($office)) {
            return;
        }
        if (empty($this->companyId)) {
            if ($this->requireCompanyScope) {
                return;
            }
        } elseif ((int)$office->company_id !== $this->companyId) {
            return;
        }

        $aggregated = null;
        if (!empty($this->year)) {
            $history = TableRegistry::getTableLocator()->get('CompanySurveyHistory')->find()
                ->where([
                    'company_id' => $office->company_id,
                    'office_id' => $office->id,
                    'year' => $this->year,
                    'type' => 'aggregated',
                ])
                ->order(['created' => 'DESC', 'id' => 'DESC'])
                ->first();
            if (!empty($history) && !empty($history->answer)) {
                $aggregated = $history->answer;
                $this->aggregatedYear = (int)$history->year;
            }
        }
        if ($aggregated === null && $strictYear) {
            return;
        }
        if ($aggregated === null) {
            $aggregated = $office->aggregated_survey;
        }
        if (empty($aggregated)) {
            return;
        }

        if (is_string($aggregated)) {
            $aggregated = json_decode($aggregated, true) ?? [];
        }

        $splitModale = $aggregated['splitModale'] ?? [];
        if (empty($splitModale) || !is_array($splitModale)) {
            $this->aggregatedYear = null;

            return;
        }

        $data = [];
        foreach (self::SPLIT_MODALE_LABEL_MAP as $key => $label) {
            if (isset($splitModale[$key]) && $splitModale[$key] !== '' && $splitModale[$key] !== null) {
                $data[$label] = (float)$splitModale[$key];
            }
        }

        if (empty($data)) {
            $this->aggregatedYear = null;

            return;
        }

        // Stesso ordine della query live: valore decrescente.
        arsort($data);

        foreach ($data as $label => $value) {
            $this->labels[] = $label;
            $this->series[0][] = $value;
        }
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

    /**
     * Chiavi di offices.aggregated_survey.splitModale in parallelo a getLabels():
     * indipendenti dalla lingua (le label vengono tradotte dopo la cache).
     *
     * @return array<int, string|null>
     */
    public function getKeys(): array
    {
        $labelToKey = array_flip(self::SPLIT_MODALE_LABEL_MAP);

        return array_map(fn($label) => $labelToKey[$label] ?? null, $this->labels);
    }

    /**
     * Numero di rispondenti (questionari completati) o null se i dati vengono dalla scheda aggregata.
     *
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->respondents;
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

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
 * Base per gli indicatori "cross-tab" a due domande a scelta singola.
 *
 * ATTENZIONE: questa classe NON fa parte della famiglia `AbstractSplitBSIndicator`
 * (splitBSOrarioLavorativo/ValutazioneViaggio/DurataSpostamento/ComuneQuartiere) e
 * non va confusa/unificata con essa. Quella famiglia affianca alla ripartizione
 * modale BresciaMobilità (derivata dalla domanda "mappa"/JSON `answer->'$.modes'`
 * dello spostamento casa-lavoro, vedi AbstractSplitBSIndicator) una seconda
 * dimensione. Qui invece NON c'è alcun dato JSON multi-modale né spostamento
 * casa-lavoro coinvolto: è un semplice incrocio per utente fra le risposte a
 * DUE domande indipendenti `type=single`, una per l'asse X (etichette fisse) e
 * una per la serie (anch'essa a opzioni fisse) — join `answers`-`answers` per
 * `user_id` (ed eventualmente `survey_id`), niente `JSON_TABLE`.
 *
 * Le sottoclassi definiscono SOLO:
 * - resolvePrimaryQuestionId($survey_id): id della domanda asse X;
 * - resolveSecondaryQuestionId($survey_id): id della domanda serie;
 * - primaryCanonicalLabels(): elenco fisso etichette asse X, con 'Dati non
 *   validi' in coda;
 * - secondaryCanonicalLabels(): elenco fisso etichette serie (senza il
 *   bucket 'Dati non validi': il bucket viene comunque prodotto in fase di
 *   conteggio per risposte mancanti/non riconosciute, coerentemente con
 *   l'asse X).
 *
 * getLabels() restituisce le etichette canoniche della domanda asse X;
 * getSeries() un array per ciascuna etichetta di serie (getSeriesLabels()),
 * ordinate per totale utenti decrescente — stesso contratto di
 * AbstractSplitBSIndicator, cosi' il generico mixin frontend `stackedIndicator.js`
 * (isStackedResponse/buildStackedChartTable) renderizza allo stesso modo
 * entrambe le famiglie di indicatori.
 *
 * @package App\Indicator
 */
abstract class AbstractCrossQuestionIndicator
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;

    protected $labels = [];
    protected $series = [[]];
    protected $seriesLabels = [];
    protected $questionType = null;
    protected $question = null;
    protected $Answers;
    protected $Questions;
    protected $filters = [];
    protected $subcompany = null;
    protected $scopeWhereSql = '';
    protected $bindings = [];

    public function __construct($survey_id = null, $office_id = null, $filters = [], $subcompany = null)
    {
        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');

        // Indicatore su dati canonici: forzo la lingua di default per evitare il
        // join i18n del Translate behavior (id ambiguo + opzioni tradotte). Vedi
        // AbstractSplitBSIndicator e la nota i18n in CLAUDE.md.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $QuestionsSurveys->setLocale($defaultLocale);

        $primary_question_id = $this->resolvePrimaryQuestionId($survey_id);
        $secondary_question_id = $this->resolveSecondaryQuestionId($survey_id);

        $this->bindings['primary_question_id'] = (int)$primary_question_id;
        $this->bindings['secondary_question_id'] = (int)$secondary_question_id;

        // Where di scoping (survey/office/subcompany/filter_*), applicato
        // identicamente ad entrambe le sotto-query per-utente (primaria e
        // secondaria) in count(): ciascuna è un `FROM answers a WHERE
        // a.question_id = :xxx_question_id ...`, quindi l'alias `a` è locale
        // ad ognuna e questo frammento si può riusare cosi' com'e' su entrambe.
        $scopeWheres = [];

        if (!empty($survey_id)) {
            $scopeWheres[] = 'a.survey_id = :survey_id';
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
            $scopeWheres[] = "a.user_id IN (SELECT id FROM users u WHERE {$userWhereClause})";
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
            // a.id IN (...)
            $inClause = implode(', ', array_fill(0, count($answ_usr_ids), '?'));
            $scopeWheres[] = "a.id IN ($inClause)";
            $this->bindings = array_merge($this->bindings, $answ_usr_ids);
        }

        $this->scopeWhereSql = $scopeWheres ? ' AND ' . implode(' AND ', $scopeWheres) : '';

        return $this;
    }

    /**
     * Id della domanda asse X (X axis / prima dimensione).
     *
     * @param int|string|null $survey_id Id survey richiesto (per lo scoping opzionale).
     * @return int
     */
    abstract protected function resolvePrimaryQuestionId($survey_id): int;

    /**
     * Id della domanda serie (seconda dimensione).
     *
     * @param int|string|null $survey_id Id survey richiesto (per lo scoping opzionale).
     * @return int
     */
    abstract protected function resolveSecondaryQuestionId($survey_id): int;

    /**
     * Elenco fisso delle etichette canoniche della domanda asse X, con
     * 'Dati non validi' sempre in coda.
     *
     * @return string[]
     */
    abstract protected function primaryCanonicalLabels(): array;

    /**
     * Elenco fisso delle etichette canoniche della domanda serie (senza il
     * bucket 'Dati non validi': viene comunque prodotto in fase di conteggio
     * per le risposte mancanti/non riconosciute).
     *
     * @return string[]
     */
    abstract protected function secondaryCanonicalLabels(): array;

    /**
     * Helper riutilizzabile dalle sottoclassi: risolve l'id domanda prima da
     * Configure (`Questions.$configKey`), poi per nome (eventualmente scoped
     * alla survey) — stesso pattern di
     * AbstractSplitBSIndicator::resolveQuestionIdByNameOrConfig()/
     * RangeIndicator::getQuestion(). Duplicato qui (anziché condiviso con la
     * famiglia AbstractSplitBSIndicator) per tenere le due famiglie
     * completamente disaccoppiate, come da nota di classe sopra.
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

    /**
     * Mappa il valore grezzo della domanda asse X su un'etichetta canonica.
     * Torna 'Dati non validi' per valori mancanti/non riconosciuti.
     *
     * @param mixed $raw Valore grezzo della domanda asse X.
     * @return string
     */
    protected function mapPrimaryRawToLabel($raw): string
    {
        if ($raw === null || $raw === '') {
            return 'Dati non validi';
        }

        return in_array((string)$raw, $this->primaryCanonicalLabels(), true) ? (string)$raw : 'Dati non validi';
    }

    /**
     * Mappa il valore grezzo della domanda serie su un'etichetta canonica.
     * Torna 'Dati non validi' per valori mancanti/non riconosciuti.
     *
     * @param mixed $raw Valore grezzo della domanda serie.
     * @return string
     */
    protected function mapSecondaryRawToLabel($raw): string
    {
        if ($raw === null || $raw === '') {
            return 'Dati non validi';
        }

        return in_array((string)$raw, $this->secondaryCanonicalLabels(), true) ? (string)$raw : 'Dati non validi';
    }

    public function count($default_sort)
    {
        $connection = ConnectionManager::get('default');

        $sql = <<<SQL
            SELECT p.pval AS primary_raw, s.sval AS secondary_raw, COUNT(*) AS total_utenti
            FROM (
                SELECT a.user_id, MAX(JSON_UNQUOTE(a.answer)) AS pval
                FROM answers a
                WHERE a.question_id = :primary_question_id{$this->scopeWhereSql}
                GROUP BY a.user_id
            ) p
            LEFT JOIN (
                SELECT a.user_id, MAX(JSON_UNQUOTE(a.answer)) AS sval
                FROM answers a
                WHERE a.question_id = :secondary_question_id{$this->scopeWhereSql}
                GROUP BY a.user_id
            ) s ON s.user_id = p.user_id
            GROUP BY p.pval, s.sval
        SQL;

        // Eseguo la query
        $res = $connection->execute($sql, $this->bindings)->fetchAll('assoc');

        // Accumulo i conteggi in una matrice [serie][etichetta_asse_x] = utenti
        $counts = [];
        foreach ($res as $row) {
            $primaryLabel = $this->mapPrimaryRawToLabel($row['primary_raw']);
            $seriesLabel = $this->mapSecondaryRawToLabel($row['secondary_raw']);
            $current = $counts[$seriesLabel][$primaryLabel] ?? 0;
            $counts[$seriesLabel][$primaryLabel] = $current + (int)$row['total_utenti'];
        }

        $this->labels = $this->primaryCanonicalLabels();

        // Ordino le serie per totale utenti decrescente, come in
        // AbstractSplitBSIndicator::count().
        $totalsBySeries = array_map('array_sum', $counts);
        arsort($totalsBySeries);

        $this->seriesLabels = array_keys($totalsBySeries);
        $this->series = [];
        foreach ($this->seriesLabels as $i => $seriesLabel) {
            $this->series[$i] = array_map(
                fn($label) => $counts[$seriesLabel][$label] ?? 0,
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
     * Nome di ciascuna serie, nello stesso ordine di getSeries():
     * getSeriesLabels()[$i] è il nome della serie getSeries()[$i].
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
}

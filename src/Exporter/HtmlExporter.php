<?php
declare(strict_types=1);

namespace App\Exporter;

use App\Indicator\baseIndicator;
use App\Indicator\RangeIndicator;
use Cake\Core\Configure;
use Cake\Datasource\FactoryLocator;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Utility\Text;
use Exception;

class HtmlExporter implements ExporterInterface
{
    use \App\Exporter\UtilityExporterTrait;

    protected $Surveys;
    protected $Sections;
    protected $Questions;
    protected $Pillars;
    protected $Measures;
    protected $Monitorings;
    protected $office_id;
    protected $company_id;
    protected $survey_id;
    protected $office;
    protected $ignore_office;
    protected $mustacheVars;
    protected $colors;
    protected $year;
    protected $pscl_id;
    protected $company_survey;
    public $Offices;

    //constructor

    public function __construct($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $this->Offices = TableRegistry::getTableLocator()->get('Offices');
        $Pscl = TableRegistry::getTableLocator()->get('Pscl');
        if ($office_id != 'null') {
            $this->office = $this->Offices->get($office_id, [
                'contain' => ['Companies' => ['CompanyTypes']],
            ]);
            $tipo = $this->office['company']['company_type']['survey_template'];
        }

        $this->office_id = $office_id;
        $this->company_id = $company_id;
        $this->survey_id = $survey_id;
        $this->ignore_office = filter_var($ignore_office, FILTER_VALIDATE_BOOLEAN);
        $this->year = $year;
        // $tipo = $this->office['company']['company_type']['survey_template'];
        $this->colors = Configure::read('chartColors');
        $this->pscl_id = $Pscl->getId($company_id, $office_id, $survey_id, $year);
        $sitedir = Configure::read('sitedir');
        // Carica le misure PSCL dalla cartella
        $configPath = WWW_ROOT . $sitedir . DS . 'company-survey-config.php';
        if (file_exists($configPath)) {
            $m = require $configPath;
            $this->company_survey = $m['CompanySurvey'] ?? [];
        } else {
            $this->company_survey = [];
        }
    }

    /**
     * Whether this office has a non-empty "all years" (TUTTI) PSCL plan.
     *
     * Replaces the legacy `$this->office->PSCL` truthy check (used as a gate
     * before calling `fillPSCLBlock()`) now that `offices.PSCL` is gone,
     * moved into a `pscl` row (`office_id`, `year IS NULL`) — see
     * `PsclTable::findAllYearsForOffice()` / CLAUDE.md dev-notes
     * ("office.PSCL -> pscl.plan migration"). Used by `MdExporter`/
     * `MdMeasuresExporter::generatePSCL()`.
     *
     * @return bool
     */
    protected function hasOfficeAllYearsPscl(): bool
    {
        if ($this->office_id == 'null' || $this->office_id === null) {
            return false;
        }

        $Pscl = TableRegistry::getTableLocator()->get('Pscl');
        $tuttiPscl = $Pscl->findAllYearsForOffice($this->company_id, $this->office_id);

        return $tuttiPscl !== null && !empty($tuttiPscl->plan[1]);
    }

    public function fillSurvey()
    {
        //Import table registry
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        //TODO: Per il momento, non essendoci la domanda sulla sede in cui lavoro
        //ignoro office_id
        $survey  = $this->Surveys->find()
            ->contain('Questions')
            ->select(['Surveys.id', 'name', 'description', 'date'])
            ->where([
                'company_id' => $this->company_id,
                'Surveys.id' => $this->survey_id,
            ])
            ->first();

        if (empty($survey)) {
            return;
        }

        $this->mustacheVars['questionario_description'] = $survey->description;
        $this->mustacheVars['questionario_date'] = $survey->date;

        // Sections are no longer a separate model: order the survey's questions
        // directly by questions_surveys.weight and skip type='section' headers.
        $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $section_questions = $QuestionsSurveys->find()
            ->contain(['Questions'])
            ->where(['QuestionsSurveys.survey_id' => $survey->id])
            ->order(['QuestionsSurveys.weight', 'QuestionsSurveys.id'])
            ->toArray();

        $qid = -1;
        {
        foreach ($section_questions as $section_question) {
            //Nel db ho un livello in più devo toglierlo
            $section_question = $section_question->question;

            if ($section_question == null || $section_question->type == 'map' || $section_question->type == 'section') {
                continue;   //A volta capita che ci siano domande spurie? (incl. headers type='section')
            }                    //In questa parte faccio i conti dei valori di quell'indicatore

            $indicator_id = $section_question->name;
            $survey_id = $survey->id;
            $office_id = $this->office_id;
            $filters = [];
            $subcompany = null;

            $class = '\\App\\Indicator\\' . $indicator_id . 'Indicator';
            //Leggo quali domande vanno trattate diversamente
            //Massimoi - 15/11/2025 - Fare un refactoring per capirlo automaticamente.
            $rangeQuestions = Configure::read('Range_questions');
            try {
                if (!class_exists($class)) {
                    if (in_array($indicator_id, $rangeQuestions)) {
                        $objIndicator = new RangeIndicator($indicator_id, $survey_id, $office_id, $filters, $subcompany);
                    } else {
                        $objIndicator = new baseIndicator($indicator_id, $survey_id, $office_id, $filters, $subcompany);
                    }
                } else {
                    $objIndicator = new $class($survey_id, $office_id, $filters, $subcompany);    //Creo una classe dinamica con il nome passato come parametro
                }
                $qid++;
            } catch (Exception $e) {
                Log::write('error', "indicatore mancante: {$section_question->name}");
                continue;
            }

            $objIndicator->count(true);
            $labels = $objIndicator->getLabels();
            $series = $objIndicator->getSeries();
            //Rimuovo le risposte vuote dalle serie e dalle labels
            //https://github.com/impronta48/moma-md-exporter-vue/issues/5
            $totalSum = array_sum(array_map('array_sum', $series));
            if ($totalSum == 0) {
                continue;
            }

            //Tolgo i caratteri strani dalle label
            foreach ($labels as &$label) {
                $label = $this->cleanLabel($label);
            }

            //scelgo il tipo di domanda
            $title = $section_question->name;
            $this->mustacheVars['domanda'][$qid]['tipo'] = $section_question->type;
            $this->mustacheVars['domanda'][$qid]['titolo'] = $section_question->name;
            $this->mustacheVars['domanda'][$qid]['descrizione'] = $section_question->description;

            $this->removeEmptyAnswers($series, $labels);

            //Faccio uno switch in base a $section_question->type
            switch ($section_question->type) {
                case 'array':
                    $this->getSurveyBlockArray($qid, $labels, $series, $title);
                    break;

                case 'drowpdown':
                    $this->getSurveyBlockDropdown($qid, $labels, $series, $title);
                    break;

                case 'text':
                    $this->getSurveyBlockText($qid, $labels, $series, $title);
                    break;

                case 'multiple':
                    $this->getSurveyBlockMultiple($qid, $labels, $series, $title);
                    break;

                default:
                    $this->getSurveyBlockSingle($qid, $labels, $series, $title);
                    break;
            }
        }
        }
        //Attenzione: questo è necessario per far funzionare mustache con array numerici
        //https://stackoverflow.com/questions/17145774/mustache-not-iterating-over-array?rq=4
        if (isset($this->mustacheVars['domanda']) && is_array($this->mustacheVars['domanda'])) {
            $this->mustacheVars['domanda'] = array_values($this->mustacheVars['domanda']);
        }
    }

    private function getSurveyBlockText($qid, $labels, $series, $title)
    {
        //Pixel bianco
        $this->mustacheVars['domanda'][$qid]['istogramma_risposte'][] = ['istogramma_risposte' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='];
        $this->mustacheVars['domanda'][$qid]['torta_risposte'][] = ['torta_risposte' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='];
        //Conto i totali
        $rows = count($labels);
        $total_sum = array_sum($series[0]);

        //Creo le intestazioni della tabella
        $this->mustacheVars['domanda'][$qid]['tabella'][] = 'Opzioni';
        foreach ($labels as $label) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = $label;
        }

        if ($total_sum == 0) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = ['-', '-', '-', '', 'transparent'];
        } else {
            $j = 1;
            for ($i = 0; $i < $rows; $i++) {
                $this->mustacheVars['domanda'][$qid]['tabella'][$i + 1] = [$labels[$i], '-', '-', 0, 'transparent'];
                $c = 'transparent';
                $this->mustacheVars['domanda'][$qid]['tabella'][$i + 1] = [$labels[$i], $series[0][$i], round($series[0][$i] / $total_sum, 2) * 100 . '%', 0, $c];
            }
        }
    }

    private function getSurveyBlockSingle($qid, $labels, $series, $title)
    {
        //Genero i grafici
        $this->mustacheVars['domanda'][$qid]['istogramma_risposte'][] = ['istogramma_risposte' => $this->getHistogram($labels, $series, $title)];
        $this->mustacheVars['domanda'][$qid]['torta_risposte'][] = ['torta_risposte' => $this->getPie($labels, $series, $title)];
        //Conto i totali
        $rows = count($labels);
        $total_sum = array_sum($series[0]);

        //Creo le intestazioni della tabella
        $this->mustacheVars['domanda'][$qid]['tabella'][] = ['Opzioni', 'Conteggio', 'Percentuale', '', 'transparent'];

        if ($total_sum == 0) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = ['-', '-', '-', '-'];
        } else {
            $j = 1;
            for ($i = 0; $i < $rows; $i++) {
                $this->mustacheVars['domanda'][$qid]['tabella'][$i + 1] = [$labels[$i], '-', '-'];

                //Indico il colore della legenda per quella specifica label
                if (isset($this->colors[$i])) {
                    $c = $this->colors[$i];
                } else {
                    $c = 'gray';
                }
                $this->mustacheVars['domanda'][$qid]['tabella'][$i + 1] = [$labels[$i], $series[0][$i], round($series[0][$i] / $total_sum, 2) * 100 . '%', 0, $c];
            }
        }
    }

    private function getSurveyBlockArray($qid, $labels, $series, $title)
    {
        [$rows, $total_sum, $main_options, $main_options_total_counts, $count_for_differtent_options, $suboption_names] = $this->countLabels($labels, $series);

        //Creo le intestazioni della tabella
        $this->mustacheVars['domanda'][$qid]['tabella'][] = ['Scelta', 'Opzioni', 'Conteggio', 'Percentuale', 'transparent'];

        if ($total_sum == 0) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = ['-', '-', '-'];
        } else {
            $j = 1;
            for ($i = 0; $i < $rows; $i++) {
                $temp_main_option = $main_options[$i];
                $count_graphs[] = $count_for_differtent_options[$i];
                if (!isset($this->mustacheVars['domanda'][$qid]['istogramma_risposte'])) {
                    $this->mustacheVars['domanda'][$qid]['istogramma_risposte'][] = ['istogramma_risposte' => $this->getHistogram($suboption_names[$i], $count_graphs, $main_options[$i])];
                } else {
                    array_push($this->mustacheVars['domanda'][$qid]['istogramma_risposte'], ['istogramma_risposte' => $this->getHistogram($suboption_names[$i], $count_graphs, $main_options[$i])]);
                }
                if (!isset($this->mustacheVars['domanda'][$qid]['torta_risposte'])) {
                    $this->mustacheVars['domanda'][$qid]['torta_risposte'][] = ['torta_risposte' => $this->getPie($suboption_names[$i], $count_graphs, $main_options[$i], $title)];
                } else {
                    array_push($this->mustacheVars['domanda'][$qid]['torta_risposte'], ['torta_risposte' => $this->getPie($suboption_names[$i], $count_graphs, $main_options[$i], $title)]);
                }

                foreach ($suboption_names[$i] as $index => $name) {
                    $this->mustacheVars['domanda'][$qid]['tabella'][$j] = [
                        $temp_main_option,
                        $name,
                        $count_for_differtent_options[$i][$index],
                        round($count_for_differtent_options[$i][$index] / array_sum($count_for_differtent_options[$i]) * 100, 2)  . '%',
                    ];
                    $j++;
                }
            }
        }
    }

    private function getSurveyBlockDropDown($qid, $labels, $series, $title)
    {
        [$rows, $total_sum, $main_options, $main_options_total_counts, $count_for_differtent_options, $suboption_names] = $this->countLabels($labels, $series);

        //Creo le intestazioni della tabella
        $this->mustacheVars['domanda'][$qid]['tabella'][] = ['Opzioni', 'Conteggio', 'Percentuale', '', 'transparent'];

        if ($total_sum == 0) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = ['-', '-', '-'];
        } else {
            $j = 1;
            for ($i = 0; $i < $rows; $i++) {
                $temp_main_option = $main_options[$i];
                $count_graphs[] = $count_for_differtent_options[$i];
                if (!isset($this->mustacheVars['domanda'][$qid]['istogramma_risposte'])) {
                    $this->mustacheVars['domanda'][$qid]['istogramma_risposte'][] = ['istogramma_risposte' => $this->getHistogram($suboption_names[$i], $count_graphs, $main_options[$i])];
                } else {
                    array_push($this->mustacheVars['domanda'][$qid]['istogramma_risposte'], ['istogramma_risposte' => $this->getHistogram($suboption_names[$i], $count_graphs, $main_options[$i])]);
                }
                if (!isset($this->mustacheVars['domanda'][$qid]['torta_risposte'])) {
                    $this->mustacheVars['domanda'][$qid]['torta_risposte'][] = ['torta_risposte' => $this->getPie($suboption_names[$i], $count_graphs, $main_options[$i], $title)];
                } else {
                    array_push($this->mustacheVars['domanda'][$qid]['torta_risposte'], ['torta_risposte' => $this->getPie($suboption_names[$i], $count_graphs, $main_options[$i], $title)]);
                }

                foreach ($suboption_names[$i] as $index => $name) {
                    $this->mustacheVars['domanda'][$qid]['tabella'][$j] = [
                        $temp_main_option,
                        $name,
                        $count_for_differtent_options[$i][$index],
                        round($count_for_differtent_options[$i][$index] / array_sum($count_for_differtent_options[$i]) * 100, 2)  . '%',
                    ];
                    $j++;
                }
            }
        }
    }

    private function cleanLabel($label)
    {
        if (is_string($label)) {
            if (str_contains($label, '[')) {
                $rplc_left = str_replace('["', '', $label);
                $rplc = str_replace('"]', '', $rplc_left);

                return $rplc;
            }
        }

        return $label;
    }

    public function fillOfficeSurvey($survey)
    {
        if (is_null($survey)) {
            return;
        }

        foreach ($survey as $question => $answer) {
            // Translate options to human-readable labels if config exists for this question
            if (is_array($answer) && isset($this->company_survey[$question])) {
                $questionConfig = $this->company_survey[$question];
                $hasNestedItems = false;
                foreach ($answer as $item) {
                    if (is_array($item)) {
                        $hasNestedItems = true;
                        break;
                    }
                }

                if ($hasNestedItems) {
                    foreach ($answer as &$item) {
                        if (is_array($item)) {
                            // For each field in the config, check if it exists in the item and translate it
                            foreach ($questionConfig as $field => $translations) {
                                if (isset($item[$field]) && isset($translations[$item[$field]])) {
                                    $item[$field] = $translations[$item[$field]];
                                }
                            }
                        }
                    }
                    unset($item); // Break reference
                } else {
                    if (is_array($questionConfig)) {
                        foreach ($answer as $index => $value) {
                            if (isset($questionConfig[$value])) {
                                $answer[$index] = $questionConfig[$value];
                            }
                        }
                    }
                }
            }

            if (is_array($answer)) {
                $hasNestedItems = false;
                foreach ($answer as $item) {
                    if (is_array($item)) {
                        $hasNestedItems = true;
                        break;
                    }
                }
                if (!$hasNestedItems) {
                    $answer = implode('<br>', array_map('strval', $answer));
                }
            }

            $this->mustacheVars[$question] = $answer;
        }
        // print_r($this->mustacheVars);
        // die;
    }

    private function fillAggregatedSurvey(): void
    {
        if (Configure::read('theme') !== 'Brescia') {
            return;
        }

        $aggregatedSurvey = $this->office->aggregated_survey ?? null;
        if (!is_array($aggregatedSurvey)) {
            return;
        }

        $summary = [];
        if (!empty($aggregatedSurvey['anno'])) {
            $summary['anno'] = (string)$aggregatedSurvey['anno'];
        }
        if (!empty($aggregatedSurvey['rispondenti'])) {
            $summary['rispondenti'] = (string)$aggregatedSurvey['rispondenti'];
        }
        if (!empty($summary)) {
            $this->mustacheVars['aggregated_summary_section'] = [$summary];
        }

        $splitModaleRows = $this->buildAggregatedRows(
            $aggregatedSurvey['splitModale'] ?? null,
            [
                'aPiedi' => 'A piedi',
                'bici' => 'Bicicletta',
                'autoConducente' => 'Auto conducente',
                'autoPasseggero' => 'Auto passeggero',
                'navetta' => 'Navetta',
                'trasportoPubblico' => 'Trasporto pubblico',
                'autoTpl' => 'Auto + TPL',
                'altro' => 'Altro',
            ]
        );
        if (!empty($splitModaleRows)) {
            $this->mustacheVars['aggregated_split_modale_section'] = [[
                'rows' => $splitModaleRows,
            ]];
        }

        $propensioneRows = $this->buildAggregatedRows(
            $aggregatedSurvey['propensioneCambiamento'] ?? null,
            [
                'aPiedi' => 'A piedi',
                'bici' => 'Bicicletta',
                'carpooling' => 'Carpooling',
                'navetta' => 'Navetta',
                'trasportoPubblico' => 'Trasporto pubblico',
                'intermodalita' => 'Intermodalita',
            ]
        );
        if (!empty($propensioneRows)) {
            $this->mustacheVars['aggregated_propensione_cambiamento_section'] = [[
                'rows' => $propensioneRows,
            ]];
        }
    }

    private function buildAggregatedRows($values, array $labels): array
    {
        if (!is_array($values)) {
            return [];
        }

        $rows = [];
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $this->formatAggregatedPercentage($value),
            ];
        }

        return $rows;
    }

    private function formatAggregatedPercentage($value): string
    {
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return '-';
        }
        if (str_contains($stringValue, '%')) {
            return $stringValue;
        }

        return $stringValue . '%';
    }

    private function removeEmptyAnswers(&$series, &$labels)
    {
        for ($i = count($labels) - 1; $i >= 0; $i--) {
            $allZero = true;
            foreach ($series as $serie) {
                if (isset($serie[$i]) && $serie[$i] != 0) {
                    $allZero = false;
                    break;
                }
            }
            if ($allZero) {
                unset($labels[$i]);
                foreach ($series as &$serie) {
                    unset($serie[$i]);
                }
            }
        }
        // Re-index arrays
        $labels = array_values($labels);
        foreach ($series as &$serie) {
            $serie = array_values($serie);
        }
    }

    private function countLabels($labels, $series)
    {
        $main_options = [];
        foreach ($labels as $label) {
            $index = strpos($label, '|');
            if ($index !== false) {
                $option = substr($label, 0, $index);
                if (!in_array($option, $main_options)) {
                    array_push($main_options, $option);
                }
            } else {
                array_push($main_options, $label);
            }
        }
        $main_options_total_counts = [];
        $count_for_differtent_options = [];
        $suboption_names = [];
        for ($i = 0; $i < count($main_options); $i++) {
            $sum_of_counts = 0;
            $tmp_count = [];
            $tmp_suboption = [];
            for ($j = 0; $j < count($labels); $j++) {
                $index = strpos($labels[$j], '|');
                if ($index !== false) {
                    $option = substr($labels[$j], 0, $index);
                    if ($option === $main_options[$i]) {
                        $tmp_var = (int)$series[0][$j];
                        $sum_of_counts += $tmp_var;
                        array_push($tmp_count, $tmp_var);
                        array_push($tmp_suboption, trim(substr($labels[$j], $index + 1)));
                    }
                } else {
                    if ($labels[$j] === $main_options[$i]) {
                        $sum_of_counts += (int)$series[0][$j];
                    }
                }
            }
            array_push($main_options_total_counts, $sum_of_counts);
            array_push($count_for_differtent_options, $tmp_count);
            array_push($suboption_names, $tmp_suboption);
        }
        $rows = count($main_options);
        $total_sum = -1;

        return [$rows, $total_sum, $main_options, $main_options_total_counts, $count_for_differtent_options, $suboption_names];
    }

    public function fillPSCLBlock()
    {
        $this->Measures = TableRegistry::getTableLocator()->get('Measures');
        $this->Monitorings = TableRegistry::getTableLocator()->get('Monitorings');

        // Pillars are derived from type=0 axis measures (see UtilityExporterTrait).
        $pillars = $this->getPillarsFromMeasures();
        // $pscl = $this->reducePSCLToActive($this->office->PSCL);

        $monitorings_query = $this->Monitorings->find();
        // ->where(['Monitorings.office_id' => $office_id]);
        if ($this->year != 'null') {
            // $monitorings_query->where(['year' => $year]);
            $monitorings_query->contain(['Pscl'])->matching('Pscl', function ($q) {
                if ($this->office_id == 'null') {
                    return $q->where(['Pscl.year' => $this->year, 'Pscl.office_id is null', 'Pscl.company_id' => $this->company_id]);
                } else {
                    return $q->where(['Pscl.year' => $this->year, 'Pscl.office_id' => $this->office_id]);
                }
            });
            // $pscl = $this->reducePSCLToActive($monitorings_query->pscl->toArray());
        } else {
            // where survey_id

            $monitorings_query->where(['Monitorings.office_id' => $this->office_id, 'pscl_id IS NULL']);
            if ($this->office_id != null) {
                // Legacy offices.PSCL is gone, replaced by the "all years"
                // pscl row (office_id, year IS NULL) - see
                // PsclTable::findAllYearsForOffice() / CLAUDE.md dev-notes.
                $Pscl = TableRegistry::getTableLocator()->get('Pscl');
                $tuttiPscl = $Pscl->findAllYearsForOffice($this->company_id, $this->office_id);
                $pscl = $tuttiPscl !== null ? $this->reducePSCLToActive($tuttiPscl->plan) : [];
            }
        }

        $monitorings = $monitorings_query->toArray();
        if ($this->year != 'null' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        }

        $base = Router::url('/', true);
        foreach ($pillars as $pillar) {
            foreach ($pillar->measures as $measure) {
                if (isset($pscl[$measure->id])) {
                    $m = [];
                    $m['pilastro'] = $pillar->id . ' - ' . $pillar->name;
                    $m['titolo'] = $measure->name;
                    $m['slug'] = $measure->slug;
                    $m['descrizione'] = h($measure->description);
                    if (file_exists(WWW_ROOT . 'img/' . DS . $measure->img)) {
                        $m['copertina'] = $base .  'img/'  . $measure->img . '?w=400&fit=crop';
                    }
                    $this->mustacheVars['baseurl'] = $base;
                    $this->mustacheVars['misura'][$measure->id] = $m;
                // }

                    //dopo aver letto la measure devo leggere l'action
                    //Passi
                    //1- ciclo su tutte le action del pscl_id corrente
                    //2 - prendo solo le action che hanno action.level < 4 (ossia action attiva)
                    $actions = TableRegistry::getTableLocator()->get('Actions');
                    $a = $actions->find()
                        ->where(['pscl_id' => $this->pscl_id])
                        ->andWhere(['measure_id' => $measure->id])
                        ->first();

                    //3 - per action corrente leggo tutti i valori da $v e li metto in $this->mustacheVars['action']['id'][];                // le varibaili previeste sono:
                    $v = [
                        'title',
                        'description',
                        'detailed_description',
                        'slug',
                        'pillar_name',
                        'level',
                        'priority',
                        'benefits_employees_time',
                        'benefits_employees_costs',
                        'benefits_employees_safety',
                        'benefits_employees_comfort',
                        'benefits_employees_social',
                        'benefits_employees_other',
                        'benefits_employees_other_text',

                        'benefits_company_regolarita',
                        'benefits_company_dedizione',
                        'benefits_company_image',
                        'benefits_company_other',
                        'benefits_company_other_text',

                        'benefits_community_emissions',
                        'benefits_community_congestion',
                        'benefits_community_safety',
                        'benefits_community_other',
                        'benefits_community_other_text',

                        'usage_methods',
                        'target_audience',
                        'timeline',
                        'economic_resources',
                        'human_resources',
                        'instrumental_resources',
                    ];

                    //Scrivo in mustachevars tutte le righe di $varname
                    foreach ($v as $varname) {
                        if (!empty($a->$varname)) {
                            // Convert priority int to string label
                            if ($varname === 'priority') {
                                $priorityLabels = [1 => 'alta', 2 => 'media', 3 => 'bassa'];
                                $this->mustacheVars['misura'][$measure->id][$varname] = $priorityLabels[$a->$varname] ?? $a->$varname;
                            } else {
                                $this->mustacheVars['misura'][$measure->id][$varname] = $a->$varname;
                            }
                        }
                    }
                }
                if (isset($this->mustacheVars['misura']) && is_array($this->mustacheVars['misura'])) {
                    $this->mustacheVars['misura'] = array_values($this->mustacheVars['misura']);
                }
            }
        }

        // print_r($this->mustacheVars['misura']);
        // die;
    }

    public function generatePSCL($response)
    {
        //Leggo il template
        // if office_id is null, then obtain the company template from company table
        if ($this->office_id == 'null') {
            $Companies = TableRegistry::getTableLocator()->get('Companies');
            $company = $Companies->get($this->company_id, [
                'contain' => ['CompanyTypes'],
            ]);
            $tipo = $company['company_type']['survey_template'];
            //Sostituisco tutte le variabili nel template
            $this->mustacheVars = [
                'nome_azienda' => $company['name'],
                'logo-azienda' => Router::url('/', true) . Configure::read('sitedir') . '/img/' . Configure::read('VUE_APP_ICON'),
            ];
            // //Genera l'analisi di contesto (questionario azienda)
            $this->fillOfficeSurvey($company->survey);
            //Genera il PSCL
            $this->fillPSCLBlock();
        } else {
            $tipo = $this->office['company']['company_type']['survey_template'];
            $company_id = $this->office['company_id'];
            //id dell'azienda
            //Sostituisco tutte le variabili nel template
            $this->mustacheVars = [
                'nome_azienda' => $this->office['company']['name'],
                'nome_sede' => $this->office['name'],
                'indirizzo_sede' => $this->office['address'],
                'comune' => $this->office['city'],
                'provincia' => $this->office['province'],
                'logo-azienda' => Router::url('/', true) . Configure::read('sitedir') . '/img/' . Configure::read('VUE_APP_ICON'),
            ];

            // //Genera l'analisi di contesto (questionario azienda)
            $this->fillOfficeSurvey($this->office->company->survey);
            $this->fillOfficeSurvey($this->office->survey);
            $this->fillAggregatedSurvey();
            // get coworking from office
            $this->fillOfficeCoworking($this->office->info_coworking);
            //Genera il PSCL
            // if ($this->office->PSCL) {
            $this->fillPSCLBlock();
            // }
            // generate impacts
            $this->fillImpacts($company_id, $this->office_id, $this->office->survey_id, $this->year);
        }
        // $tipo = $this->office['company']['company_type']['survey_template'];
        $templatePath = WWW_ROOT . Configure::read('sitedir') . "/modello-pscl-$tipo.html";
        $handle = fopen($templatePath, 'r');
        $mustacheTemplate = fread($handle, filesize($templatePath));
        fclose($handle);

        // Riporta il questionario e l'analisi
        $this->fillSurvey();

        // Debugging output
        //$this->dumpMustacheVars(); //Scrive le variabili in un file di testo
        //dd($mustacheVars);         //Stampa a video le variabili

        $m = new \Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
            //'logger' => \Cake\Log\Log::engine('default'),
            'escape' => function ($value) {
                if (is_array($value)) {
                    echo 'errore array used as string:';
                    var_dump($value);
                    exit;
                }
                    $value = strval($value);

                    return htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            },
        ]);
        try {
            $rendered_pscl = $m->render($mustacheTemplate, $this->mustacheVars);
        } catch (Exception $e) {
            $rendered_pscl = 'Errore durante la generazione del PSCL: ' . $e->getMessage();
            Log::error($e->getMessage());
        }

        //Al momento sospeso, preferisco salvare in HTML direttamente
        //$response = $this->convertWithPandoc($rendered_pscl, $response);

        // Inject string content into response body
        if (!is_null($response)) {
            $response = $response->withStringBody($rendered_pscl);
        } else {
            $resultPath = $this->resultPath($this->year);
            $filename = "pscl-{$this->office['office_code']}";
            //Salvo il file HTML
            //file_put_contents("$resultPath/$filename.html", $rendered_pscl);
            file_put_contents("$resultPath/$filename.doc", $rendered_pscl);
        }

        // Return response object to prevent controller from trying to render
        // a view.
        return $response;
    }

    private function dumpMustacheVars()
    {
        $filePath = WWW_ROOT . Configure::read('sitedir') . 'mustachevars_dump.txt';
        $dump = '';
        foreach ($this->mustacheVars as $key => $value) {
            $type = gettype($value);
            if (is_array($value) || is_object($value)) {
                $valStr = print_r($value, true);
            } else {
                $valStr = var_export($value, true);
            }
            $dump .= "$key ($type): $valStr\n";
        }
        file_put_contents($filePath, $dump);
    }

    /** Converte il documento HTML in Word usando pandoc
     *  Funzione al momento sospesa
     */
    private function convertWithPandoc($html_pscl, $response)
    {
        $temp = tmpfile();
        fwrite($temp, $html_pscl);
        $path = stream_get_meta_data($temp)['uri']; // eg: /tmp/phpFx0513a
        exec("PATH=/usr/bin: pandoc --from=html $path -o /tmp/result.docx");
        $response = $response->withType('doc');
        $response = $response->withFile('/tmp/result.docx');
        $response = $response->withDownload('pscl.docx');

        return $response;
    }

    /** Where do we store the PSCL */
    public function resultPath($year)
    {
        //Load the Company model
        $this->Companies = FactoryLocator::get('Table')->get('Companies');
        $company_code = $this->Companies->getCodeOrCreate($this->company_id);

        if (!$this->ignore_office) {
            $office_code = $this->office->office_code;
        }
        $this->Surveys = FactoryLocator::get('Table')->get('Surveys');
        $survey_tag = Text::slug($this->Surveys->get($this->survey_id)->version_tag);

        if ($year == 'null') {
            $year = '';
        }
        if ($this->ignore_office || empty($office_code)) {
            $resultPath = WWW_ROOT . Configure::read('sitedir') . "/pscl/$year/{$company_code}";
        } else {
            $resultPath = WWW_ROOT . Configure::read('sitedir') . "/pscl/$year/{$company_code}-$office_code";
        }
        //Se non esiste la cartella la creo qui, così centralizzo
        if (!file_exists($resultPath)) {
            mkdir($resultPath, 0777, true);
        }

        return $resultPath;
    }

    private function fillOfficeCoworking($info_coworking)
    {
        if (is_null($info_coworking)) {
            return;
        }
        $coworking = Configure::read('Coworking');

        // translate $info_coworking using $coworking config
        if (is_array($info_coworking)) {
            foreach ($info_coworking as &$item) {
                if (is_array($item) && isset($item['type'])) {
                    $typeValue = $item['type'];
                    // If type is an array of type IDs, translate each one
                    if (is_array($typeValue)) {
                        $translatedTypes = [];
                        foreach ($typeValue as $typeKey) {
                            if (isset($coworking['types'][$typeKey])) {
                                $translatedTypes[] = $coworking['types'][$typeKey];
                            } else {
                                $translatedTypes[] = $typeKey;
                            }
                        }
                        $item['type'] = implode('<br>', $translatedTypes);
                    } elseif (is_string($typeValue) || is_int($typeValue)) {
                        // If type is a single value (string or int), translate it directly
                        if (isset($coworking['types'][$typeValue])) {
                            $item['type'] = $coworking['types'][$typeValue];
                        }
                    }
                }
            }
            unset($item);
        }

        $this->mustacheVars['info_coworking'] = $info_coworking;
    }

    // multiple

    private function getSurveyBlockMultiple($qid, $labels, $series, $title)
    {
        //Genero i grafici
        $this->mustacheVars['domanda'][$qid]['istogramma_risposte'][] = ['istogramma_risposte' => $this->getHistogram($labels, $series, $title)];
        $this->mustacheVars['domanda'][$qid]['torta_risposte'][] = ['torta_risposte' => $this->getPie($labels, $series, $title)];
        //Conto i totali
        $rows = count($labels);
        // Handle both single series (array of counts) and multiple series (2D array)
        $firstSeries = is_array($series[0]) ? $series[0] : $series;
        $total_sum = array_sum($firstSeries);

        //Creo le intestazioni della tabella
        $this->mustacheVars['domanda'][$qid]['tabella'][] = ['Opzioni', 'Conteggio', 'Percentuale', '', 'transparent'];

        if ($total_sum == 0) {
            $this->mustacheVars['domanda'][$qid]['tabella'][] = ['-', '-', '-', '', 'transparent'];
        } else {
            for ($i = 0; $i < $rows; $i++) {
                $option_sum = $firstSeries[$i] ?? 0;
                if (isset($this->colors[$i])) {
                    $c = $this->colors[$i];
                } else {
                    $c = 'gray';
                }
                $this->mustacheVars['domanda'][$qid]['tabella'][$i + 1] = [$labels[$i], $option_sum, round($option_sum / $total_sum * 100, 2) . '%', '', $c];
            }
        }
    }

    public function fillImpacts($company_id, $office_id, $survey_id, $year)
    {
        $impacts = [];

        // $this->loadModel('Offices');

        // $this->loadModel('Monitorings');
        // $Offices = TableRegistry::getTableLocator()->get('Offices');
        $Monitorings = TableRegistry::getTableLocator()->get('Monitorings');

        $monitorings_query = $Monitorings->find();
        // ->where(['Monitorings.office_id' => $office_id]);
        if ($year != 'TUTTI') {
            // $monitorings_query->where(['year' => $year]);
            $monitorings_query->contain(['Pscl'])->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                if ($office_id == 'null') {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                } else {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                }
            });
            // $pscl = $this->reducePSCLToActive($monitorings_query->pscl->toArray());
        } else {
            // where survey_id

            $monitorings_query->where(['Monitorings.office_id' => $office_id, 'pscl_id IS NULL']);
            if ($office_id != null) {
                // Legacy offices.PSCL is gone, replaced by the "all years"
                // pscl row (office_id, year IS NULL) - see
                // PsclTable::findAllYearsForOffice() / CLAUDE.md dev-notes.
                $Pscl = TableRegistry::getTableLocator()->get('Pscl');
                $tuttiPscl = $Pscl->findAllYearsForOffice($company_id, $office_id);
                $pscl = $tuttiPscl !== null ? $this->reducePSCLToActive($tuttiPscl->plan) : [];
            }
        }

        $monitorings = $monitorings_query->toArray();
        $all_series = $monitorings;
        if ($year != 'TUTTI' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        }
        // Pillars are derived from type=0 axis measures (see UtilityExporterTrait).
        $pillars = $this->getPillarsFromMeasures();

        foreach ($pillars as $pillar) {
            foreach ($pillar->measures as $measure) {
                $measure_monitorings = array_filter($all_series, function ($series) use ($measure) {
                    return $series->measure_id === $measure->id;
                });
                if (isset($pscl[$measure->id])) {
                    $tmp = [];
                    foreach ($measure_monitorings as $measure_monitoring) {
                        $values = $measure_monitoring->jvalues;
                        // if ($measure->id==21) {
                        //     print_r($values);
                        //     print_r($measure_monitoring);
                        // }
                        $emissions = $this->Measures->get($measure->id)->calculateImpactPscl($measure->id, $values);

                        if ($emissions == null) {
                            break;
                        }
                        array_unshift($emissions, $measure_monitoring->name);

                        $emissions['name'] = $emissions[0];
                        unset($emissions[0]);

                        array_push($tmp, $emissions);
                    }
                    if (!empty($tmp)) {
                        $impacts[$measure->id] = $tmp;
                    }
                }
            }
        }
        // sum impacts
        $totalCO2 = 0;
        $totalNOx = 0;
        $totalPM10 = 0;
        $obj_CO2 = 0;
        $obj_NOx = 0;
        $obj_PM10 = 0;

        foreach ($impacts as $key => $measureImpacts) {
            if (!empty($measureImpacts)) {
                $lastItem = end($measureImpacts);
                $totalCO2 += $lastItem['CO2'] ?? 0;
                $totalNOx += $lastItem['NOx'] ?? 0;
                $totalPM10 += $lastItem['PM10'] ?? 0;

                $firstItem = reset($measureImpacts);
                $obj_CO2 += $firstItem['CO2'] ?? 0;
                $obj_NOx += $firstItem['NOx'] ?? 0;
                $obj_PM10 += $firstItem['PM10'] ?? 0;
            }
        }
        // round impacts
        $totalCO2 = round($totalCO2, 2);
        $totalNOx = round($totalNOx, 2);
        $totalPM10 = round($totalPM10, 2);
        $totalCost = round(((27 * round($totalPM10, 2)) + (25.4 * round($totalNOx, 2))) / 10) / 100;
        $obj_CO2 = round($obj_CO2, 2);
        $obj_NOx = round($obj_NOx, 2);
        $obj_PM10 = round($obj_PM10, 2);
        $obj_Cost = round(((27 * round($obj_PM10, 2)) + (25.4 * round($obj_NOx, 2))) / 10) / 100;

        $impacts['totals'] = [
            'totalCO2' => $totalCO2,
            'totalNOx' => $totalNOx,
            'totalPM10' => $totalPM10,
            'totalCost' => $totalCost,
            'obj_CO2' => $obj_CO2,
            'obj_NOx' => $obj_NOx,
            'obj_PM10' => $obj_PM10,
            'obj_Cost' => $obj_Cost,
        ];
        $this->mustacheVars['impacts'] = $impacts['totals'];
        // print impacts for debugging
        // echo "<pre>";
        // print_r($this->mustacheVars['impacts']);
        // echo "</pre>";
        // die;
        // $this->set(compact('impacts'));
        // $this->viewBuilder()->setOption('serialize', ['impacts']);
    }
}

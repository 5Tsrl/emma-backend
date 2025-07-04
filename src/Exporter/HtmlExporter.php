<?php

declare(strict_types=1);

namespace App\Exporter;

use App\Indicator\baseIndicator;
use Cake\Core\Configure;
use Cake\Database\Query;
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

    //constructor

    public function __construct($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $Offices = TableRegistry::getTableLocator()->get('Offices');
        if ($office_id != 'null') {
            $this->office = $Offices->get($office_id, [
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
    }

    public function fillSurvey()
    {
        //Import table registry
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $this->Sections = TableRegistry::getTableLocator()->get('Sections');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        //TODO: Per il momento, non essendoci la domanda sulla sede in cui lavoro
        //ignoro office_id
        $survey  = $this->Surveys->find()
            ->contain('Questions')
            ->select(['id', 'name', 'description', 'date'])
            ->where([
                'company_id' => $this->company_id,
                'id' => $this->survey_id,
            ])
            ->first();

        if (empty($survey)) {
            return;
        }

        $this->mustacheVars['questionario_description'] = $survey->description;
        $this->mustacheVars['questionario_date'] = $survey->date;

        $sections = $this->Sections->find()
            ->contain(
                'QuestionsSurveys',
                function (Query $q) use ($survey) {
                    return $q
                        //->contain(['Questions'])  //Intellisense segna errore, ma ci vuole!
                        ->where(['QuestionsSurveys.survey_id' => $survey->id]);
                }
            )
            ->contain(['QuestionsSurveys' => 'Questions'])
            ->order('weight')
            ->toArray();

        $qid = -1;
        foreach ($sections as $section) {
            if (isset($section->questions_surveys) && $section->questions_surveys != null) {
                $section_questions = $section->questions_surveys;
            } else {
                continue;
            }

            foreach ($section_questions as $section_question) {
                //Nel db ho un livello in più devo toglierlo
                $section_question = $section_question->question;

                if ($section_question == null || $section_question->type == 'map') {
                    continue;   //A volta capita che ci siano domande spurie?
                }                    //In questa parte faccio i conti dei valori di quell'indicatore
                $class = '\\App\\Indicator\\' . $section_question->name . 'Indicator';
                try {
                    if (!class_exists($class)) {
                        //distinguo bene se per il questionario corrente è impostato office_id
                        if ($this->ignore_office) {
                            $objIndicator = new baseIndicator($section_question->name, $survey->id, null, [], null);
                        } else {
                            $objIndicator = new baseIndicator($section_question->name, $survey->id, $this->office_id, [], null);
                        }
                    } else {
                        $objIndicator = new $class($survey->id);
                    }
                    $qid++;
                } catch (Exception $e) {
                    Log::write('error', "indicatore mancante: {$section_question->name}");
                    continue;
                }

                $objIndicator->count(true);
                $labels = $objIndicator->getLabels();
                $series = $objIndicator->getSeries();
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

                    default:
                        $this->getSurveyBlockSingle($qid, $labels, $series, $title);
                        break;
                }
            }
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
            $this->mustacheVars[$question] = $answer;
        }
    }

    private function removeEmptyAnswers(&$series, &$labels)
    {
        $index_for_empty_answers = array_search('[]', $labels);
        if ($index_for_empty_answers != false) {
            array_splice($labels, $index_for_empty_answers, 1);
            array_splice($series[0], $index_for_empty_answers, 1);
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
        $this->Pillars = TableRegistry::getTableLocator()->get('Pillars');
        $this->Measures = TableRegistry::getTableLocator()->get('Measures');
        $this->Monitorings = TableRegistry::getTableLocator()->get('Monitorings');

        $pillars  = $this->Pillars->find()
            ->contain(['Measures']);
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
                $pscl = $this->reducePSCLToActive($this->office->PSCL);
            }
        }

        $monitorings = $monitorings_query->toArray();
        if ($this->year != 'null' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        }

        $m = 1;   //indice della misura che sto compilando
        foreach ($pillars as $pillar) {
            foreach ($pillar->measures as $measure) {
                //Todo, controlla se nell'array $pscl questa accoppiata $measure->id, $pillar->id vale TRUE
                $measure_monitorings = array_filter($monitorings, function ($series) use ($measure) {
                    return $series->measure_id === $measure->id;
                });

                $base = Router::url('/', true);
                //TODO: Testing only
                $base = 'https://api.mobility48.it/';

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
                    $this->mustacheVars['misura'][] = $m;
                }
            }
        }
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
            //Genera il PSCL
            // if ($this->office->PSCL) {
            $this->fillPSCLBlock();
            // }
        }
        // $tipo = $this->office['company']['company_type']['survey_template'];
        $templatePath = WWW_ROOT . Configure::read('sitedir') . "/modello-pscl-$tipo.html";
        $handle = fopen($templatePath, 'r');
        $mustacheTemplate = fread($handle, filesize($templatePath));
        fclose($handle);

        // Riporta il questionario e l'analisi
        $this->fillSurvey();

        //dd($mustacheVars);
        $m = new \Mustache_Engine(['entity_flags' => ENT_QUOTES]);
        try {
            $rendered_pscl = $m->render($mustacheTemplate, $this->mustacheVars);
        } catch (Exception $e) {
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
            file_put_contents("$resultPath/$filename.html", $rendered_pscl);
            file_put_contents("$resultPath/$filename.doc", $rendered_pscl);
        }

        // Return response object to prevent controller from trying to render
        // a view.
        return $response;
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
}

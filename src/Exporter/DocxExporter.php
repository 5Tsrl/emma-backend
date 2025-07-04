<?php
declare(strict_types=1);

namespace App\Exporter;

use App\Indicator\baseIndicator;
use Cake\Core\Configure;
use Cake\Database\Query;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;
use PhpOffice\PhpWord\Element\Chart;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class DocxExporter implements ExporterInterface
{
    use \App\Exporter\UtilityExporterTrait;

    private $templateProcessor;
    private $Surveys;
    private $Sections;
    private $Questions;
    private $Pillars;
    private $Measures;
    private $Monitorings;
    private $office_id;
    private $company_id;
    private $survey_id;
    private $ignore_office;
    private $office;
    private $year;

    //constructor

    public function __construct($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $Offices = TableRegistry::getTableLocator()->get('Offices');
        $this->office_id = $office_id;
        $this->company_id = $company_id;
        $this->survey_id = $survey_id;
        $this->ignore_office = filter_var($ignore_office, FILTER_VALIDATE_BOOLEAN);
        $this->year = $year;
        // $tipo = $this->office['company']['company_type']['survey_template'];
        if ($office_id != 'null') {
            $this->office = $Offices->get($office_id, [
                'contain' => ['Companies' => ['CompanyTypes']],
            ]);
            $tipo = $this->office['company']['company_type']['survey_template'];
        } else {
            $Companies = TableRegistry::getTableLocator()->get('Companies');
            $company = $Companies->get($this->company_id, [
                'contain' => ['CompanyTypes'],
            ]);
            $tipo = $company['company_type']['survey_template'];
        }

        //Il modello da cui generare il pscl
        $templatePath = WWW_ROOT . Configure::read('sitedir') . "/modello-pscl-$tipo.docx";
        $this->templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
    }

    //Compila il questionario in word

    public function fillSurvey()
    {
        //Import table registry
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $this->Sections = TableRegistry::getTableLocator()->get('Sections');
        $this->Questions = TableRegistry::getTableLocator()->get('Questions');

        $survey  = $this->Surveys->find()
            ->contain(['Questions'])
            ->select(['id', 'name', 'description', 'date'])
            ->where([
                'company_id' => $this->company_id,
                'id' => $this->survey_id,
                ])
            ->first();
        if (empty($survey)) {
            return;
        }
        $questions = $survey->questions;

        $this->templateProcessor->setValue('questionario_description', $survey->description);
        $this->templateProcessor->setValue('questionario_date', $survey->date);
        ini_set('pcre.backtrack_limit', '2000000');
        //Clono tante misure quante sono attive nel pscl
        $this->templateProcessor->cloneBlock('domanda', count($questions) - 1, true, true);

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
        usort($sections, fn ($a, $b) => $a->weight - $b->weight);

        $m = 1;
        foreach ($sections as $section) {
            $section_questions = array_filter($questions, function ($question) use ($section) {
                return $question->section_id === $section->id;
            });

            $flag = 0;
            foreach ($section_questions as $section_question) {
                $flag++;
                $question_id = null;
                if (empty($question_id)) {
                    $slug = str_replace('_', '-', $section_question->name);
                    $q = $this->Questions->find()
                        ->select(['id', 'type'])
                        ->where(['name' => $slug])
                        ->first();
                    if (!empty($q)) {
                        $question_id = $q->id;
                    }
                }
                if (empty($question_id)) {
                    continue;
                }
                if ($section_question == null || $section_question->type == 'map') {
                    continue;   //A volta capita che ci siano domande spurie?
                }

                $class = '\\App\\Indicator\\' . $section_question->name . 'Indicator';
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

                $objIndicator->count(true);
                $labels = $objIndicator->getLabels();
                $series = $objIndicator->getSeries();

                $index_for_empty_answers = array_search('[]', $labels);
                if ($index_for_empty_answers != false) {
                    array_splice($labels, $index_for_empty_answers, 1);
                    array_splice($series[0], $index_for_empty_answers, 1);
                }

                if ($section_question->type === 'array' || $section_question->type === 'dropdown') {
                    $main_options = [];
                    foreach ($labels as $label) {
                        $index = strpos($label, ':');
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
                            $index = strpos($labels[$j], ':');
                            if ($index !== false) {
                                $option = substr($labels[$j], 0, $index);
                                if ($option === $main_options[$i]) {
                                    $tmp_var = (int)$series[0][$j];
                                    $sum_of_counts += $tmp_var;
                                    array_push($tmp_count, $tmp_var);
                                    array_push($tmp_suboption, trim(substr($labels[$j], $index)));
                                }
                            } else {
                                if ($labels[$j] === $main_options[$i]) {
                                    $sum_of_counts += (int)$series[0][$j];
                                }
                            }
                        }
                        array_push($main_options_total_counts, $sum_of_counts);
                        if ($main_options[$i] !== 'altro') {
                            array_push($count_for_differtent_options, $tmp_count);
                            array_push($suboption_names, $tmp_suboption);
                        }
                    }
                    $rows = count($main_options);

                    $total_sum = -1;
                } else {
                    $rows = count($labels);
                    $total_sum = array_sum($series[0]);
                }

                $this->templateProcessor->setValue("titolo#$m", $section_question->name);
                $this->templateProcessor->setValue("descrizione#$m", $section_question->description);
                $color = 000000;

                $styleFont = ['bgcolor' => '8c8787'];
                $styleFont1 = ['bold' => true, 'size' => 11];
                $styleFont2 = ['bgcolor' => 'ada4a4'];
                $styleFont3 = ['bold' => false, 'size' => 10];

                $style = [
                    'width' => 10000.7952,
                    'borderSize' => 10,
                    //'font' => 'Carlito',
                    'unit' => TblWidth::TWIP,
                    'borderColor' => $color,
                    'shading' => ['color' => $color],
                ];

                $column_width = 300;

                $table = new Table($style);
                $table->addRow();

                if ($section_question->type == 'text') {
                    $table->addCell(250, $styleFont)->addText('Opzioni', $styleFont1);

                    foreach ($labels as $label) {
                        $table->addRow();
                        $table->addCell(250)->addText($label);
                    }
                    $this->templateProcessor->cloneBlock("graph#$m", 1, true, true);
                    $this->templateProcessor->setValue("grafico_risposte#$m#1", '');
                    $this->templateProcessor->setValue("option#$m#1", '');
                    $this->templateProcessor->setComplexBlock("table#$m", $table);
                    $m++;
                    continue;
                } else {
                    $table = new Table($style);
                    $table->addRow();

                    $table->addCell($column_width, $styleFont)->addText('Opzioni', $styleFont1);
                    $table->addCell($column_width, $styleFont)->addText('Conteggio', $styleFont1);

                    if ($section_question->type != 'array' && $section_question->type != 'dropdown') {
                        $table->addCell($column_width, $styleFont)->addText('Percentuale', $styleFont1);
                    }
                }

                if ($total_sum == 0) {
                    $table->addRow();
                    $table->addCell($column_width)->addText('-');
                    $table->addCell($column_width)->addText('-');
                    $table->addCell($column_width)->addText('-');
                    $this->templateProcessor->cloneBlock("graph#$m", 1, true, true);
                    $this->templateProcessor->setValue("istogramma_risposte#$m#1", '');
                    $this->templateProcessor->setValue("torta_risposte#$m#1", '');
                    $this->templateProcessor->setValue("option#$m#1", '');
                } else {
                    for ($i = 1; $i <= $rows; $i++) {
                        $table->addRow();
                        if ($section_question->type == 'array' || $section_question->type == 'dropdown') {
                            $temp_main_option = $main_options[$i - 1];
                            $table->addCell($column_width, $styleFont2)->addText($temp_main_option, $styleFont3);

                            for ($j = 0; $j < count($labels); $j++) {
                                $index = strpos($labels[$j], ':');
                                $series_value = $series[0][$j];

                                if ($index !== false) {
                                    $option = substr($labels[$j], 0, $index);
                                    if ($option == $temp_main_option) {
                                        $cell = $table->addCell($column_width);
                                        $cell->addText(substr($labels[$j], $index + 2));
                                        $cell->addText($series_value . ' (' . round(($series_value / $main_options_total_counts[$i - 1]) * 100, 2)  . '%)', $styleFont3);
                                    }
                                } else {
                                    if ($temp_main_option === $labels[$j] && $main_options_total_counts[$i - 1] > 0) {
                                        $cell = $table->addCell($column_width);
                                        $cell->addText($labels[$j]);
                                        $cell->addText($series_value . ' (' . round(($series_value / $main_options_total_counts[$i - 1]) * 100, 2) . '%)', $styleFont3);
                                    } else {
                                        if ($temp_main_option === $labels[$j]) {
                                            $cell = $table->addCell($column_width);
                                            $cell->addText($labels[$j]);
                                            $cell->addText($series_value . ' (0%)', $styleFont3);
                                        }
                                    }
                                }
                            }
                        } elseif (is_string($labels[$i - 1])) {
                            if (str_contains($labels[$i - 1], '[')) {
                                $rplc_left = str_replace('["', '', $labels[$i - 1]);
                                $rplc = str_replace('"]', '', $rplc_left);

                                $table->addCell($column_width)->addText($rplc, $styleFont3);
                                $labels[$i - 1] = $rplc;
                            } else {
                                $table->addCell($column_width)->addText($labels[$i - 1], $styleFont3);
                            }
                        } else {
                            $table->addCell($column_width)->addText($labels[$i - 1], $styleFont3);
                        }
                        if ($section_question->type !== 'array' && $section_question->type != 'dropdown') {
                            $table->addCell($column_width)->addText($series[0][$i - 1], $styleFont3);
                            $table->addCell($column_width)->addText(round($series[0][$i - 1] / $total_sum, 2) * 100 . '%', $styleFont3);
                        }
                    }
                    if ($section_question->type !== 'array' && $section_question->type != 'dropdown') {
                        $showLegend = true;
                        $legendPosition = 'b';
                        // r = right, l = left, t = top, b = bottom, tr = top right
                        $this->templateProcessor->cloneBlock("graph#$m", 1, true, true);
                        $chart = new Chart('doughnut', $labels, $series[0]);

                        $chart->getStyle()->setWidth(Converter::cmToEmu(16))->setHeight(Converter::cmToEmu(8));
                        $chart->getStyle()->setShowLegend($showLegend);
                        $chart->getStyle()->setLegendPosition($legendPosition);
                        $chart->getStyle()->setDataLabelOptions([
                            'showCatName' => false,
                            'showVal' => false,
                            'showPercent' => true,
                        ]);
                        $chart->getStyle()->set3d(true);
                        //Uso la stessa color palette di google
                        $colors = Configure::read('chartColors');
                        $colors = $this->removeHash($colors);

                        $chart->getStyle()->setColors($colors);
                        $this->templateProcessor->setChart("torta_risposte#$m#1", $chart);
                        $this->templateProcessor->setValue("option#$m#1", '');

                        $chart = new Chart('bar', $labels, $series[0]);
                        $h = 1.25 * count($series[0]);   //Altezza dinamica in base al numero di valori
                        $chart->getStyle()->setWidth(Converter::cmToEmu(16))->setHeight(Converter::cmToEmu($h));
                        $chart->getStyle()->setShowLegend(false);
                        //Uso la stessa color palette di google
                        $chart->getStyle()->setColors($colors);
                        $chart->getStyle()->setDataLabelOptions([
                            'showCatName' => true,
                            'showVal' => true,
                            'showPercent' => true,
                        ]);
                        $this->templateProcessor->setChart("istogramma_risposte#$m#1", $chart);
                        $this->templateProcessor->setValue("option#$m#1", '');
                    } elseif ($section_question->type === 'array' || $section_question->type === 'dropdown') {
                        //TODO: Questo grafico non funziona ancora bene, lo commento
                        /*
                        $this->templateProcessor->cloneBlock("graph#$m", $rows - 1, true, true);
                        for ($i = 1; $i <= $rows; $i++) {
                            if (isset($main_options[$i - 1]) && isset($count_for_differtent_options[$i - 1]) && $main_options[$i - 1] !== 'altro') {
                                $chart = new Chart('bar', $suboption_names[$i - 1], $count_for_differtent_options[$i - 1]);
                                $h = 1.5 * $rows;   //Altezza dinamica in base al numero di valori
                                $chart->getStyle()->setWidth(Converter::cmToEmu(17))->setHeight(Converter::cmToEmu($h));
                                $chart->getStyle()->setColors(Configure::read('chartColors'));
                                // $chart->getStyle()->setTitle($main_options[$i - 1]);
                                $this->templateProcessor->setChart("istogramma_risposte#$m#$i", $chart);
                                 $this->templateProcessor->setValue("option#$m#$i", $main_options[$i - 1], );
                            } else {
                                $this->templateProcessor->setValue("grafico_risposte#$m#$i", "");
                            }
                        }
                        */
                    } elseif ($section_question->type === 'text') {
                        $this->templateProcessor->cloneBlock("graph#$m", 1, true, true);
                        $this->templateProcessor->setValue("istogramma_risposte#$m", '');
                        $this->templateProcessor->setValue("torta_risposte#$m", '');
                    }
                }
                $this->templateProcessor->setComplexBlock("table#$m", $table);
                $m++;
            }
        }
    }

    public function fillOfficeSurvey($survey)
    {
        if (is_null($survey)) {
            return;
        }

        foreach ($survey as $question => $answer) {
            if (is_array($answer)) {
                //Tratta l'array opportunamente
                $c = count($answer);
                if ($c == 0) {
                    continue;
                }
                //Se è un array multidimensionale
                if (is_array($answer[0])) {
                    //Copio la prima riga tante volte quanti solo gli elementi dell'array
                    $ks = array_keys($answer[0]);
                    $k = array_key_first($answer[0]);
                } else {
                    //Copio la prima riga tante volte quanti solo gli elementi dell'array
                    $ks = array_keys($answer);
                    $k = array_key_first($answer);
                }
                try {
                    $this->templateProcessor->cloneRow("$question.$k", $c);
                    for ($i = 0; $i < $c; $i++) {
                        if (is_array($answer[$i])) {  //Array multidimensionale
                            foreach ($ks as $k) {
                                if (!isset($answer[$i][$k]) || is_null($answer[$i][$k])) {
                                    if (is_array($answer[$i])) {
                                        $answer[$i][$k] = '--';
                                    } else {
                                        continue;
                                    }
                                }
                                $j = $i + 1;    //Devo farlo perchè il template conta da 1 e non da 0

                                $this->templateProcessor->setValue("$question.$k#$j", $this->cleanStr($answer[$i][$k]));
                            }
                        } else { //Array Monodimensionale
                            $j = $i + 1;    //Devo farlo perchè il template conta da 1 e non da 0
                            $this->templateProcessor->setValue("$question.$k#$j", $this->cleanStr($answer[$i]));
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Errore durante la generazione del docx per la domanda: $question.$k");
                }
            } else {
                $this->templateProcessor->setValue($question, $this->cleanStr($answer));
            }
        }
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

            $monitorings_query->where(['Monitorings.office_id' => $this->office_id,'pscl_id IS NULL']);
            if ($this->office_id != null) {
                $pscl = $this->reducePSCLToActive($this->office->PSCL);
            }
        }

        $monitorings = $monitorings_query->toArray();
        if ($this->year != 'null' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        } else {
            $pscl = [];
        }

        $color = 000000;
        $column_width = 300;

        $styleFont = ['bgcolor' => 'b4c7dc'];
        $styleFont1 = ['bold' => true, 'size' => 10, 'name' => 'Carlito'];
        $styleFont2 = ['bgcolor' => 'b4c7dc'];
        $styleFont3 = ['bold' => false, 'size' => 10, 'name' => 'Carlito'];

        $style = [
            'width' => 10000.7952,
            'borderSize' => 13,
            'font' => 'Carlito',
            'unit' => TblWidth::TWIP,
            'borderColor' => $color,
            'shading' => ['color' => $color],
        ];

        //Clono tante misure quante sono attive nel pscl
        $this->templateProcessor->cloneBlock('misura', count($pscl), true, true);

        $m = 1;   //indice della misura che sto compilando
        foreach ($pillars as $pillar) {
            foreach ($pillar->measures as $measure) {
                //Todo, controlla se nell'array $pscl questa accoppiata $measure->id, $pillar->id vale TRUE
                $measure_monitorings = array_filter($monitorings, function ($series) use ($measure) {
                    return $series->measure_id === $measure->id;
                });

                if (isset($pscl[$measure->id])) {
                    $this->templateProcessor->setValue("titolo#$m", $measure->name);
                    $this->templateProcessor->setValue("pilastro#$m", $pillar->id . ' - ' . $pillar->name);
                    if (file_exists(WWW_ROOT . 'img/' . DS . $measure->img)) {
                        $this->templateProcessor->setImageValue("copertina#$m", [
                            'path' => WWW_ROOT . 'img/' . DS . $measure->img,
                            'width' => '17cm', 'height' => '5cm', 'ratio' => true,
                        ]);
                    }
                    $this->templateProcessor->setValue("descrizione#$m", h($measure->description));

                    if (count($measure_monitorings) > 0) {
                        //Monitoraggio
                        $table_columns = array_keys(array_values($measure_monitorings)[0]->jvalues);
                        array_unshift($table_columns, 'Data');
                        array_unshift($table_columns, 'Nome monitaraggio');
                        $table = new Table($style);
                        $table->addRow();
                        foreach ($table_columns as $table_column) {
                            $table->addCell($column_width, $styleFont)->addText($table_column, $styleFont1);
                        }
                        //impatto
                        $impatto_table_columns = ['Nome monitaraggio', 'riduzione_km_gg_auto', 'C', 'CO', 'CO2', 'NOx', 'PM10'];
                        $categories = ['riduzione_km_gg_auto', 'C', 'CO', 'CO2', 'NOx', 'PM10'];
                        $chartData = [];
                        $series_names = [];
                        $table_impatto = new Table($style);
                        $table_impatto->addRow();
                        foreach ($impatto_table_columns as $column) {
                            $table_impatto->addCell($column_width, $styleFont)->addText($column, $styleFont1);
                        }

                        foreach ($measure_monitorings as $measure_monitoring) {
                            array_push($series_names, $measure_monitoring->name);
                            //Monitoraggio
                            $table->addRow();
                            $table->addCell($column_width, $styleFont2)->addText($measure_monitoring->name, $styleFont3);
                            $table->addCell($column_width)->addText($measure_monitoring->dt, $styleFont3);
                            foreach (array_values($measure_monitoring->jvalues) as $value) {
                                // if value not is array
                                if (!is_array($value)) {
                                    $table->addCell($column_width)->addText($value, $styleFont3);
                                }
                                // $table->addCell($column_width)->addText($value, $styleFont3);
                            }
                            //impatto
                            $values = $measure_monitoring->jvalues;
                            $emissions = $measure->calculateImpactPscl($measure->id, $values);
                            $table_impatto->addRow();
                            $table_impatto->addCell($column_width, $styleFont2)->addText($measure_monitoring->name, $styleFont3);
                            foreach ($emissions as $emission) {
                                $table_impatto->addCell($column_width)->addText(round($emission, 4), $styleFont3);
                            }
                            array_push($chartData, array_values($emissions));
                        }
                        // grafic_risposte
                        $chart = new Chart('bar', $categories, $chartData[0], $series_names[0]);
                        for ($i = 1; $i < count($measure_monitorings); $i++) {
                            $chart->addSeries($categories, $chartData[$i], $series_names[$i]);
                        }

                        $h = 1.5 * count($chartData[0]);
                        $chart->getStyle()->setWidth(Converter::cmToEmu(17))->setHeight(Converter::cmToEmu($h));

                        $this->templateProcessor->setComplexBlock("table_monitoraggio#$m", $table);
                        $this->templateProcessor->setComplexBlock("table_impatto#$m", $table_impatto);
                        $this->templateProcessor->setChart("grafico_risposte#$m", $chart);
                    } else {
                        $this->templateProcessor->setValue("table_monitoraggio#$m", '');
                        $this->templateProcessor->setValue("table_impatto#$m", '');
                        $this->templateProcessor->setValue("grafico_risposte#$m", '');
                    }

                    $m++;
                }
            }
        }
    }

    public function generatePSCL($response)
    {

        //Fisso il nome del file
        // if office_id is null, then obtain the company template from company table
        if ($this->office_id == 'null') {
            $Companies = TableRegistry::getTableLocator()->get('Companies');
            $company = $Companies->get($this->company_id, [
                'contain' => ['CompanyTypes'],
            ]);
            $filename = 'pscl-' . str_replace(' ', '-', $company['name']) . '.docx';
            //Sostituisco tutte le variabili nel template
            $this->templateProcessor->setValue('nome_azienda', $company['name']);
            // //Genera l'analisi di contesto (questionario azienda)
            $this->fillOfficeSurvey($company->survey);
            // Riporta il questionario e l'analisi
            $this->fillSurvey($this->survey_id, null);

            //Genera il PSCL

            $this->fillPSCLBlock();
        } else {
            $filename = 'pscl-' . str_replace(' ', '-', $this->office['company']['name']) . '-' . str_replace(' .', '-', $this->office['name']) . '.docx';
            $company_id =   $this->office['company']['id'];
            //Sostituisco tutte le variabili nel template
            // $company_id =   $this->office['company']['id'];
            $this->templateProcessor->setValue('nome_azienda', $this->office['company']['name']);
            $this->templateProcessor->setValue('nome_sede', $this->office['name']);
            $this->templateProcessor->setValue('indirizzo_sede', $this->office['address']);
            $this->templateProcessor->setValue('comune', $this->office['city']);
            $this->templateProcessor->setValue('provincia', $this->office['province']);
            // //Genera l'analisi di contesto (questionario azienda)
            $this->fillOfficeSurvey($this->office->company->survey);
            $this->fillOfficeSurvey($this->office->survey);
            // Riporta il questionario e l'analisi
            $this->fillSurvey($this->office->survey_id, null);

            //Genera il PSCL
            // if ($this->office->PSCL) {
            $this->fillPSCLBlock();
            // }
        }
        $outputPath = TMP . $filename;

        //$this->templateProcessor->setImageValue('logo-azienda', ['value' => Asset::imageUrl(Configure::read('VUE_APP_ICON'), ['fullBase' => true]), 'width' => 400]);
        try {
            $this->templateProcessor->setImageValue('logo-azienda', ['path' =>  Configure::read('sitedir') . '/img/' . Configure::read('VUE_APP_ICON'), 'width' => 600]);
        } catch (Exception $e) {
            Log::error('Errore durante l\'inserimento del logo aziendale nel docx');
        }

        //Salvo e trasferisco
        $res = $this->templateProcessor->saveAs($outputPath);
        if (!is_null($response)) {
            $response = $response->withFile(
                $outputPath,
                ['download' => true, 'name' => $filename]
            );
        } else {
            $resultPath = $this->resultPath();
            //copy tmp file to result path
            copy($outputPath, $resultPath . "$filename.docx");
        }

        return $response;
    }

    private function removeHash($colors)
    {
            $newColors = [];

        foreach ($colors as $color) {
            $newColor = ltrim($color, '#');
            $newColors[] = $newColor;
        }

            return $newColors;
    }
}

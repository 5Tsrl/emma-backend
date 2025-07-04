<?php
declare(strict_types=1);

namespace App\Exporter;

use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Core\Exception\CakeException;
use Cake\Datasource\FactoryLocator;
use Cake\Log\Log;
use Cake\Routing\Asset;
use Cake\Utility\Text;
use Exception;
use InvalidArgumentException;
use Mustache_Loader_FilesystemLoader;
use ParsedownExtra;
use RuntimeException;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class MdExporter extends HtmlExporter implements ExporterInterface
{
    use \App\Exporter\UtilityExporterTrait;

    public function __construct($company_id, $office, $survey_id, $ignore_office, $year)
    {
        parent::__construct($company_id, $office, $survey_id, $ignore_office, $year);

        //Genera le variabili per il template con tutte le chiamate successive
        //TODO: Inserire il vero logo dell'azienda dal PSCL
        //$company_id = $this->office['company']['id'];

        $this->mustacheVars = [
            'nome_azienda' => $this->office['company']['name'],
            'nome_sede' => $this->office['name'],
            'indirizzo_sede' => $this->office['address'],
            'comune' => $this->office['city'],
            'provincia' => $this->office['province'],
            'logo-azienda' => Asset::imageUrl(Configure::read('VUE_APP_ICON'), ['fullBase' => true]),
            //'logo-azienda' => Router::url('/', true) . Configure::read('sitedir') . "/".  Configure::read('logodir') . "/" . $company_id . '.png',
        ];
    }

    /**
     * Genera il PSCL in formato HTML duplicando un template fatto di markdown+html+mustach
     * Il risultato finare e pscl.html che può essere puoi tratto con WeasyPrint per convertirlo in PDF da stampa.
     *
     * @param mixed $response
     * @return void
     * @throws \InvalidArgumentException
     * @throws \Cake\Core\Exception\CakeException
     * @throws \App\Exporter\MissingPluginException
     * @throws \App\Exporter\MissingDatasourceConfigException
     * @throws \App\Exporter\RuntimeException
     * @throws \App\Exporter\DatabaseException
     * @throws \App\Exporter\BadMethodCallException
     */
    public function generatePSCL($response)
    {
        //Leggo la cartella dei template
        $tipo = $this->office['company']['company_type']['survey_template'];
        $templatePath = WWW_ROOT . Configure::read('sitedir') . "/pscl/template-$tipo";

        //Copio il template-tipo nella cartella del progetto /pscl/$id_sede
        $resultPath = $this->resultPath($this->year);
        $this->copyResources($templatePath, $resultPath);

        // //Genera l'analisi di contesto (questionario azienda)
        $this->fillOfficeSurvey($this->office->company->survey);
        $this->fillOfficeSurvey($this->office->survey);

        //devo salvare i due grafici della domanda con il $question->name-histogram.png e $question->name-pie.png
        //devo generare un file md per ogni domanda del questionario
        // Devo generare un file che includa tutte le domande (index.html)
        // Riporta il questionario e l'analisi
        $this->fillSurvey();
        $this->dumpSurvey($resultPath);

        //Genera il PSCL
        //Devo generare un file md per ogni misura del PSCL
        //Devo generare un file che includa tutte le misure (index.htmls)
        if ($this->office->PSCL) {
            $this->fillPSCLBlock();
            $this->dumpMisure($resultPath);
        }

        return $this->AllMd2Html($response);     
    }

    /**
     * Prende tutti i file MD che ci sono nella cartella e genera il file complessivo HTML
     * @param mixed $response 
     * @return mixed 
     * @throws InvalidArgumentException 
     * @throws RuntimeException 
     * @throws RecordNotFoundException 
     * @throws CakeException 
     */
    protected function AllMd2Html($response){
        //Copio il template-tipo nella cartella del progetto /pscl/$id_sede
        $resultPath = $this->resultPath($this->year);

        //Ora che ho tutte le variabili posso generare il PSCL
        $templateFile = 'index.html';
        $m = new \Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
            'loader'          => new Mustache_Loader_FilesystemLoader($resultPath, ['extension' => '.html']),
            'partials_loader' => new Mustache_Loader_FilesystemLoader($resultPath, ['extension' => '.md']),
            'helpers' => [
                'include' => function ($partial) use ($resultPath) {
                    $partial = trim($partial);
                    $extension = pathinfo($partial, PATHINFO_EXTENSION);
                    if ($extension === 'md' || $extension === 'markdown') {
                        $markdown = new ParsedownExtra();
                        //$markdown = new Parsedown();
                        $html = $markdown->text(file_get_contents($resultPath . '/' . $partial));

                        return $html;
                    } else {
                        return file_get_contents($resultPath . '/' . $partial);
                    }
                },
            ],
        ]);
        try {
            // //Genera l'analisi di contesto (questionario azienda)
            $this->fillOfficeSurvey($this->office->company->survey);
            $this->fillOfficeSurvey($this->office->survey);
            $this->fillSurvey();
            $this->fillPSCLBlock();
            
            $rendered_pscl = $m->render($templateFile, $this->mustacheVars);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        // Salvo il risultato in un file nella cartella stessa
        $filename = 'pscl';

        //Genero il TOC
        $rendered_pscl = $this->generateTOC($rendered_pscl);

        //Tolgo il :443 dagli url
        $rendered_pscl = str_replace(':443', '', $rendered_pscl);

        //Salvo il file HTML
        file_put_contents("$resultPath/$filename.html", $rendered_pscl);

        // Return response object to prevent controller from trying to render
        // a view.
        return $response; 
    }

    //Copy the resources recursively, following the same structure
    private function copyResources($templatePath, $resourcePath)
    {
        $dir = opendir($templatePath);
        while (($file = readdir($dir)) !== false) {
            if (is_dir($templatePath . '/' . $file) && $file != '.' && $file != '..') {
                if (!file_exists($resourcePath . '/' . $file)) {
                    mkdir($resourcePath . '/' . $file, 0777, true);
                }
                $this->copyResources($templatePath . '/' . $file, $resourcePath . '/' . $file);
            } elseif ($file != '.' && $file != '..') {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                copy($templatePath . '/' . $file, $resourcePath . '/' . $file);
            }
        }
        closedir($dir);
    }

    protected function generateTOC($rendered_pscl)
    {
        //Genera il TOC a partire dall'HTML
        $markupFixer  = new MarkupFixer();
        $tocGenerator = new TocGenerator();
        $rendered_pscl = "<div class='content'>" . $markupFixer->fix($rendered_pscl) . '</div>';
        $toc = $tocGenerator->getHtmlMenu($rendered_pscl, 1, 1);
        $rendered_pscl = str_replace('%%TOC%%', $toc, $rendered_pscl);

        return $rendered_pscl;
    }

    protected function dumpSurvey($path)
    {
        //Il file che contiene il template di ogni grafico
        //TODO: rendere parametrico
        $templateFile = '/../../shared/box/domanda-questionario.html';
        $template = file_get_contents($path . $templateFile);
        $m = new \Mustache_Engine([
                'entity_flags' => ENT_QUOTES,
        ]);

        if (!file_exists("$path/domande/")) {
            mkdir("$path/domande/", 0777, true);
        }

        $inc = "<h2>Risposte al questionario</h2>\n";
        //Ciclo su tutte le domande
        //Apro il template
        //Sostituisco le variabili
        //Salvo il file con il nome della domanda nella cartella domande/nome-domanda.html
        foreach ($this->mustacheVars['domanda'] as $qid => $d) {
            try {
                $rendered = $m->render($template, $this->mustacheVars['domanda'][$qid]);
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
            file_put_contents("$path/domande/{$d['titolo']}.html", $rendered);

            //Genero l'include di tutte le domande
            $inc .= "\n\n{{#include}}domande/{$d['titolo']}.html{{/include}}";
        }
        file_put_contents("$path/domande/index.html", $inc);
    }

    protected function dumpMisure($path, $overwrite=false)
    {
        //Il file che contiene il template di ogni grafico
        //TODO: rendere parametrico
        $templateFile = '/../../shared/box/misura.html';
        $template = file_get_contents($path . $templateFile);

        $m = new \Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
        ]);

        if (!file_exists("$path/misure/")) {
            mkdir("$path/misure/", 0777, true);
        }

        $inc = '';
        //Ciclo su tutte le domande
        //Apro il template
        //Sostituisco le variabili
        //Salvo il file con il nome della domanda nella cartella domande/nome-domanda.html
        if (isset($this->mustacheVars['misura'])) {
            foreach ($this->mustacheVars['misura'] as $mid => $mis) {
                try {
                    $rendered = $m->render($template, $this->mustacheVars['misura'][$mid]);
                } catch (Exception $e) {
                    Log::error($e->getMessage());
                }
                if (!file_exists("$path/misure/{$mis['slug']}.html") || $overwrite){
                    file_put_contents("$path/misure/{$mis['slug']}.html", $rendered);
                }
                
                //Genero l'include di tutte le domande
                $inc .= "\n\n{{#include}}misure/{$mis['slug']}.html{{/include}}";
            }
        }

        file_put_contents("$path/misure/index.html", $inc);
    }


}

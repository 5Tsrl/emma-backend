<?php
declare(strict_types=1);

namespace App\Exporter;

class MdSurveyExporter extends MdExporter implements ExporterInterface
{
    use \App\Exporter\UtilityExporterTrait;

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
        $resultPath = $this->resultPath($this->year);

        //devo salvare i due grafici della domanda con il $question->name-histogram.png e $question->name-pie.png
        //devo generare un file md per ogni domanda del questionario
        // Devo generare un file che includa tutte le domande (index.html)
        // Riporta il questionario e l'analisi
        $this->fillSurvey();
        $this->dumpSurvey($resultPath);
        
        // Return response object to prevent controller from trying to render
        // a view.
        return $response;
    }
}
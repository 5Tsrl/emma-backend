<?php
declare(strict_types=1);

namespace App\Exporter;

class MdMeasuresExporter extends MdExporter implements ExporterInterface
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
        
        //Genera il PSCL
        //Devo generare un file md per ogni misura del PSCL
        //Devo generare un file che includa tutte le misure (index.htmls)
        if ($this->office->PSCL) {
            $this->fillPSCLBlock();
            $this->dumpMisure($resultPath);
        }

        // Return response object to prevent controller from trying to render
        // a view.
        return $response;
    }
}
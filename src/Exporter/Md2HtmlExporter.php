<?php
declare(strict_types=1);

namespace App\Exporter;

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Routing\Asset;
use Exception;
use Mustache_Loader_FilesystemLoader;
use ParsedownExtra;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class MD2HtmlExporter extends MdExporter implements ExporterInterface
{

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
       return $this->AllMd2Html($response);
    }

}

<?php
declare(strict_types=1);

namespace App\Exporter;

class PdfExporter extends MdExporter implements ExporterInterface
{
    use \App\Exporter\UtilityExporterTrait;

    public function generatePSCL($response)
    {
        //Copio il template-tipo nella cartella del progetto /pscl/$id_sede
        $resultPath = $this->resultPath($this->year);

        $filename = 'pscl';

        //Genero il PDF usando weasyprint
        $command = "/usr/bin/weasyprint $resultPath/$filename.html $resultPath/$filename.pdf";
        $output = shell_exec($command);

        //restituisco il file pdf nell'output
        if (! is_null($response)) {
            // Inject string content into response body
            $response = $response->withFile($resultPath . '/' . $filename . '.pdf', ['download' => true, 'name' => $filename . '.pdf']);
        }

        // Return response object to prevent controller from trying to render
        // a view.
        return $response;
    }
}

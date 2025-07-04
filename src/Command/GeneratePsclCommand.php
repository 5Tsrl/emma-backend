<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * HTTP_HOST=api.mobility48.test bin/cake generate_pscl --company_id=1624 --office_id=946 --survey_id=49 --format=md
 *
 * @package App\Command
 */
class GeneratePsclCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('company_id', [
                'help' => "id dell'azienda",
                'required' => true,
            ])
            ->addOption('office_id', [
                'help' => 'id della sede',
                'required' => true,
            ])
            ->addOption('survey_id', [
                'help' => 'id del questionario',
                'required' => true,
            ])
            ->addOption('ignore_office', [
                'help' => "se true ignore l'ufficio nel calcolod degli indicatori",
                'boolean' => false,
                'default' => false,
            ])
            ->addOption('format', [
                'help' => 'formato del report ammessi: ' . implode(', ', Configure::read('Exporter.extensions', ['html', 'docx'])),
                'required' => true,
                'choices' => Configure::read('Exporter.extensions', ['html', 'docx']),
            ])
            ->addOption('year', [
                'help' => 'anno di riferimento',
                'required' => false,
                'default' => null,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $company_id = (int)$args->getOption('company_id');
        $office_id = (int)$args->getOption('office_id');
        $survey_id = (int)$args->getOption('survey_id');
        $ignore_office = (int)$args->getOption('ignore_office');
        $format = (string)$args->getOption('format');
        $year = (string)$args->getOption('year');

        $this->generate($format, $company_id, $office_id, $survey_id, $ignore_office, $year);
    }

    private function generate($format, $company_id, $office_id, $survey_id, $ignore_office = false, $year = null)
    {
        //Verifico che il formato sia corretto, altrimenti prendo Html
        //TODO: leggere i formati da un file di configurazione
        if (!in_array(strtolower($format), Configure::read('Exporter.extensions', ['html', 'docx']))) {
            $format = 'Html';
        } else {
            $format = ucfirst(strtolower($format));
        }

        //Uso il formattatore corretto
        $class = '\\App\\Exporter\\' . $format . 'Exporter';
        if (!class_exists($class)) {
            throw new \Exception('Formato non supportato');
        }

        //Massimoi: 30/12/2023
        // ---> Questa è la vera chiamata al formattatore
        //L'ultimo parametro serve per dire agli indicatori di non considerare la sede
        //Si potrebbe mettere office_id = null, ma non funziona perchè il PSCL è legato alla sede
        $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);
        $e->generatePSCL(null);
    }
}

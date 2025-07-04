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
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;

class GeocodeOriginsCommand extends Command
{
    // HTTP_HOST=api.5t.drupalvm.test bin/cake geocode_origins 99999 --redo=1  --survey=1
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addArgument('company_id', [
                'help' => "id dell'azienda che si vuole geocodificare, 99999 per geocodificarle tutte",
                'required' => true,
            ])
            ->addOption('redo', [
                'help' => 'se redo=1 vengono ri-geocodificati tutti gli indirizzi',
            ])
            ->addOption('survey', [
                'help' => 'se survey=1 vengono ri-geocodificati solo i risultati del questionario',
            ]);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $company_id = (int)$args->getArgument('company_id');
        $redo = $args->getOption('redo');
        $survey_id = (int)$args->getOption('survey');

        $this->loadModel('Companies');
        $this->loadModel('Origins');

        //Se hai passato -1 ricordifico tutte le aziende

        $companies = $this->Companies->find()
                        ->orderDesc('id');

        if ($company_id != 99999) {
            $companies->where(['id' => $company_id]);
        }

        foreach ($companies as $company) {
            if ($redo) {
                $originIds = $this->Origins->getAll($company->id, $survey_id);
            } else {
                $originIds = $this->Origins->getAllNotGeocoded($company->id);
            }

            //$originIds = [205113] ;
            foreach ($originIds as $originId) {                
                $io->out("Geocoding origin # $originId");
                $this->Origins->geocode($originId);
                $io->out("Update distance for # $originId");
                $this->Origins->updateDistance($originId);
            }
        }
    }
}

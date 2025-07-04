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
use Cake\Datasource\ConnectionManager;

class ConvertQOptionsJsonCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $connection = ConnectionManager::get('default');
        $sql = 'ALTER TABLE questions ADD joptions json NULL;';
        //$connection->execute($sql);

        $this->loadModel('Questions');

        $questions = $this->Questions->find();

        foreach ($questions as $q) {
            $o = $q->options;

            if (!is_null($o) && !is_array($o)) {
                $ad = json_decode($o, true); //answer decoded
                if (json_last_error() != JSON_ERROR_NONE) {
                    $q->options = json_encode($ad);
                } else {
                    $q->options = $ad;
                }
                if ($this->Questions->save($q)) {
                    $io->out("\t\t Salvataggio {$q->id} OK");
                } else {
                    $io->out("\t\t Salvataggio {$q->id} KO");
                }
            }
        }

        $sql = 'ALTER TABLE questions drop options ;';
        //$connection->execute($sql);
        $sql = 'ALTER TABLE questions change joptions options ;';
        //$connection->execute($sql);
    }
}

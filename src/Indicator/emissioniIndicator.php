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
namespace App\Indicator;

use Cake\Datasource\ConnectionManager;
use Exception;

class emissioniIndicator extends baseIndicator
{
    public function __construct($survey_id = null)
    {
        //Carico la query corretta per questo indicatore
        $this->loadModel('Reports');
        $r = $this->Reports->findByName('coIndicator')->first();
        if (empty($r)) {
            throw new Exception("L'indircatore $survey_id non è definito nella tabella reports", 1);
        }
        $sql = $r->q;

        //Preparo la query
        $connection = ConnectionManager::get('default');
        $statement = $connection->prepare($sql);

        //Eventuali parametri della query vanno qui

        // if (!empty($survey_id)) {
        //     $statement->bindValue('survey_id', $survey_id, 'integer');
        // }

        //Eseguo la query
        try {
            $statement->execute([]);
            $res = $statement->fetchAll('assoc');
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        //Ordino il risultato per ottenere label e series
        $this->series[0] = [];
        foreach ($res[0] as $k => $r) {
            if (!in_array($k, $this->labels) && !is_null($r)) {
                $label = $k;
                $this->labels[] = $label;
                $this->series[0][] = $r;
            }
        }
    }

    public function count($default_sort)
    {
        return $this;
    }
}

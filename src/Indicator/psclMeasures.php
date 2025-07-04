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

use Cake\Datasource\ModelAwareTrait;
use Cake\Log\LogTrait;
use Cake\ORM\Locator\LocatorAwareTrait;

class psclMeasures
{
    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;

    private $measures = [];

    public function __construct()
    {
        $this->measures[1] = function ($series) {
            $a = new emissionA(floatval($series['days']), floatval($series['users']), floatval($series['distance']));

            return $a->getOutput();
        };
        $this->measures[2] = function ($series) {
            $a = new emissionB(floatval($series['days']), floatval($series['users']), floatval($series['distance']), floatval($series['salary']), floatval($series['days']));

            return $a->getOutput();
        };
    }

    public function getPsclMeasures()
    {
        return $this->measures;
    }
}

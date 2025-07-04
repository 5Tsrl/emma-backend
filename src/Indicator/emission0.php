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

class emission0
{
    public function getOutput()
    {
        return [
            'Consumo' => 0.0,
            'CO' => 0.0,
            'CO2' => 0.0,
            'NOx' => 0.0,
            'PM2_5' => 0.0,
            'PM10' => 0.0,
        ];
    }
}

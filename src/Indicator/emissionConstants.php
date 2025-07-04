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

// Good example here https://stackoverflow.com/a/32987693/3813117
namespace App\Indicator;

class emissionConstants
{
    public const occup_media_auto = 1.2;
    public const fc_auto = 8.69;
    public const feCO2_auto = 163.0846;
    public const feCO_auto = 0.7853;
    public const feNOx_auto = 0.4256;
    public const fePM10_auto = 0.0297;
    public const fePM2_5_auto = 0.0241;
    // protected static float $fePM2_5_auto = 0.0241; //const g/km
}

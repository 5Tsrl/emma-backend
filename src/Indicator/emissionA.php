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

class emissionA extends emissionConstants
{
    protected float $gg;
    protected float $utenti;
    protected float $km_sostenibili;

    public function __construct(float $gg, float $utenti, float $km_sostenibili)
    {
        $this->gg = $gg;
        $this->utenti = $utenti;
        $this->km_sostenibili = $km_sostenibili;
    }

    public function riduzione_km_auto()
    {
        return (floatval($this->utenti) / $this::occup_media_auto * floatval($this->km_sostenibili)) * $this->gg;
    }

    public function dC()
    {
        return floatval($this->riduzione_km_auto()) * $this::fc_auto / 100;
    }

    public function dCO2()
    {
        return floatval($this->riduzione_km_auto()) * $this::feCO2_auto / 1000;
    }

    public function dCO()
    {
        return floatval($this->riduzione_km_auto()) * $this::feCO_auto / 1000;
    }

    public function dNOx()
    {
        return floatval($this->riduzione_km_auto()) * $this::feNOx_auto / 1000;
    }

    public function dPM10()
    {
        return floatval($this->riduzione_km_auto()) * $this::fePM10_auto / 1000;
    }

    public function getOutput()
    {
        return [
            'riduzione_km_gg_auto' => $this->riduzione_km_auto(),
            'Consumo' => $this->dC(),
            'CO' => $this->dCO(),
            'CO2' => $this->dCO2(),
            'NOx' => $this->dNOx(),
            'PM10' => $this->dPM10(),
        ];
    }
}

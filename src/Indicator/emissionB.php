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

class emissionB extends emissionConstants
{
    protected float $gg_sh;
    protected float $km_auto_gg;
    protected float $utenti_sharing;
    protected float $km_sh;
    protected float $viaggi_sh_gg;

    //Parametri da usare nel caso di auto in sharing
    protected float $fc_auto_sh = 8.69;
    protected float $feCO2_auto_sh = 163.0846;
    protected float $feCO_auto_sh = 0.7853;
    protected float $feNOx_auto_sh = 0.4256;
    protected float $fePM10_auto_sh = 0.0297;

    public function __construct(
        float $gg_sh,
        float $km_auto_gg,
        float $utenti_sharing,
        float $km_sh,
        float $viaggi_sh_gg
    ) {
        $this->gg_sh = $gg_sh;
        $this->km_auto_gg = $km_auto_gg;
        $this->utenti_sharing = $utenti_sharing;
        $this->km_sh = $km_sh;
        $this->viaggi_sh_gg = $viaggi_sh_gg;    //Numero di noleggi al giorno, diventa il numero di equipaggi che partecipano
    }

    public function bTot_km_sh()
    {
        return floatval($this->viaggi_sh_gg) * floatval($this->km_sh) * $this->gg_sh;
    }

    public function bTot_km_auto()
    {
        return floatval($this->utenti_sharing) / $this::occup_media_auto * $this->km_auto_gg * $this->gg_sh;
    }

    public function bdC()
    {
        return floatval($this->bTot_km_auto()) * $this::fc_auto / 100
            - ($this->bTot_km_sh() * floatval($this->fc_auto_sh)) / 100;
    }

    public function bdCO2()
    {
        return floatval($this->bTot_km_auto()) * $this::feCO2_auto / 1000
            - ($this->bTot_km_sh() * floatval($this->feCO2_auto_sh)) / 1000;
    }

    public function bdCO()
    {
        return floatval($this->bTot_km_auto()) * $this::feCO_auto / 1000
            - ($this->bTot_km_sh() * floatval($this->feCO_auto_sh)) / 1000;
    }

    public function bdNOx()
    {
        return floatval($this->bTot_km_auto()) * $this::feNOx_auto / 1000
            - ($this->bTot_km_sh() * floatval($this->feNOx_auto_sh)) / 1000;
    }

    public function bdPM10()
    {
        return floatval($this->bTot_km_auto()) * $this::fePM10_auto / 1000
            - ($this->bTot_km_sh() * floatval($this->fePM10_auto_sh)) / 1000;
    }

    public function getOutput()
    {
        return [
            'riduzione_km_gg_auto' => $this->bTot_km_auto() - $this->bTot_km_sh(),
            'Consumo' => $this->bdC(),
            'CO' => $this->bdCO(),
            'CO2' => $this->bdCO2(),
            'NOx' => $this->bdNOx(),
            'PM10' => $this->bdPM10(),
        ];
    }
}

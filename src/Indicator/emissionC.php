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

class emissionC extends emissionConstants
{
    protected float $gg_user;
    protected float $gg_navetta;
    protected float $km_da_sostituire;
    protected float $km_nuovi;
    protected float $fc_auto_nuovi;

    // use emissionTrait;

    protected float $feCO2_auto_nuovi;
    protected float $feCO_auto_nuovi;
    protected float $feNOx_auto_nuovi;
    protected float $fePM10_auto_nuovi;

    public function __construct(
        float $gg_user,
        float $gg_navetta,
        float $km_da_sostituire,
        float $km_nuovi,
        float $fc_auto_nuovi = 80,
        float $feCO2_auto_nuovi = 725.89171,
        float $feCO_auto_nuovi = 0.97150,
        float $feNOx_auto_nuovi = 3.45962,
        float $fePM10_auto_nuovi = 0.13064
    ) {
        $this->gg_user = $gg_user;
        $this->gg_navetta = $gg_navetta;
        $this->km_da_sostituire = $km_da_sostituire;
        $this->km_nuovi = $km_nuovi;
        $this->fc_auto_nuovi = $fc_auto_nuovi;
        $this->feCO2_auto_nuovi = $feCO2_auto_nuovi;
        $this->feCO_auto_nuovi = $feCO_auto_nuovi;
        $this->feNOx_auto_nuovi = $feNOx_auto_nuovi;
        $this->fePM10_auto_nuovi = $fePM10_auto_nuovi;
    }

    // public function bTot_km_sh()
    // {
    //     return floatval($this->noleggi_gg) * floatval($this->km_noleggio);
    // }

    // public function briduzione_km_gg_auto()
    // {
    //     return floatval($this->utenti_sharing) * $this->riduzione_km_auto;
    // }

    public function cdC()
    {
        return (floatval($this->km_da_sostituire) * $this::fc_auto * floatval($this->gg_user) / 1.2 ) / 100 - ($this->km_nuovi * floatval($this->fc_auto_nuovi) * floatval($this->gg_navetta)) / 100;
    }

    public function cdCO2()
    {
        return (floatval($this->km_da_sostituire) * $this::feCO2_auto * floatval($this->gg_user) / 1.2) / 1000 - ($this->km_nuovi * floatval($this->feCO2_auto_nuovi) * floatval($this->gg_navetta)) / 1000;
    }

    public function cdCO()
    {
        return (floatval($this->km_da_sostituire) * $this::feCO_auto * floatval($this->gg_user) / 1.2) / 1000 - ($this->km_nuovi * floatval($this->feCO_auto_nuovi) * floatval($this->gg_navetta)) / 1000;
    }

    public function cdNOx()
    {
        return (floatval($this->km_da_sostituire) * $this::feNOx_auto * floatval($this->gg_user) / 1.2) / 1000 - ($this->km_nuovi * floatval($this->feNOx_auto_nuovi) * floatval($this->gg_navetta)) / 1000;
    }

    public function cdPM10()
    {
        return (floatval($this->km_da_sostituire) * $this::fePM10_auto * floatval($this->gg_user) / 1.2) / 1000 - ($this->km_nuovi * floatval($this->fePM10_auto_nuovi) * floatval($this->gg_navetta)) / 1000;
    }

    public function getOutput()
    {
        return [
            'Consumo' => $this->cdC(),
            'CO' => $this->cdCO(),
            'CO2' => $this->cdCO2(),
            'NOx' => $this->cdNOx(),
            'PM10' => $this->cdPM10(),
        ];
    }
}

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

class emissionD extends emissionConstants
{
    // use emissionTrait;

    protected float $gg;
    protected float $az_nr_dipendenti;
    protected float $az_distanza_spostamenti;
    protected float $az_spostamenti_auto;

    public function __construct(float $gg, float $az_nr_dipendenti, float $az_distanza_spostamenti, float $az_spostamenti_auto)
    {
        $this->gg = $gg;
        $this->az_nr_dipendenti = $az_nr_dipendenti;
        $this->az_distanza_spostamenti = $az_distanza_spostamenti;
        $this->az_spostamenti_auto = $az_spostamenti_auto;
    }

    public function azKm_annui()
    {
        return $this->az_nr_dipendenti *
            $this->az_distanza_spostamenti *
            2 *
            $this->az_spostamenti_auto *
            0.01 * //0.01 si tratta di divisione per 100 visto che viene inserita la % dei dipendenti che utilizzano l'auto
            $this->gg; //Giorni di lavoro all'anno
    }

    public function azC()
    {
        return floatval($this->azKm_annui()) * $this::fc_auto * 0.01;
    }

    public function azCO()
    {
        return floatval($this->azKm_annui()) * $this::feCO_auto * 0.001;
    }

    public function azCO2()
    {
        return floatval($this->azKm_annui()) * $this::feCO2_auto * 0.001;
    }

    public function azNOx()
    {
        return floatval($this->azKm_annui()) * $this::feNOx_auto * 0.001;
    }

    public function azPM2_5()
    {
        return floatval($this->azKm_annui()) * $this::fePM2_5_auto * 0.001;
    }

    public function azPM10()
    {
        return floatval($this->azKm_annui()) * $this::fePM10_auto * 0.001;
    }

    public function getOutput()
    {
        return [
            'Consumo' => $this->azC(),
            'CO' => $this->azCO(),
            'CO2' => $this->azCO2(),
            'NOx' => $this->azNOx(),
            'PM2_5' => $this->azPM2_5(),
            'PM10' => $this->azPM10(),
        ];
    }
}

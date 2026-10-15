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

namespace App\Model\Entity;

use Cake\Core\Configure;
use Cake\ORM\Entity;

/**
 * Office Entity
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $cap
 * @property string|null $city
 * @property string|null $province
 * @property int|null $company_id
 * @property float|null $lat
 * @property float|null $lon
 * @property string|null $office_code
 * @property array|null $survey
 *
 * @property \App\Model\Entity\Company $company
 */
class Office extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
    '*' => true,
    ];

    public function geocode(origin $origin): array
    {
        // ⚠️ `Configure::read('Geocoding.Engine', 'komootGeocoder')`'s default argument
        // never actually applies: config/app.php always DEFINES the key
        // (`'Geocoding.Engine' => env('GEOCODING_ENGINE', false)`), it's just `false`
        // when the env var is unset — `Configure::read()`'s fallback only kicks in when
        // the key is entirely ABSENT, not when it resolves to a falsy value. Coerce
        // defensively instead of trusting the default arg (same fix as OriginsTable).
        $configuredEngine = Configure::read('Geocoding.Engine', 'komootGeocoder');
        $geocodingEngine = is_string($configuredEngine) && $configuredEngine !== ''
            ? $configuredEngine
            : 'komootGeocoder';

        //Creo dinamicamente l'oggetto geocoder, in base al parametro di configurazione
        //https://www.php.net/manual/en/functions.variable-functions.php
        $geocoderName = '\\App\\Geocoder\\' . $geocodingEngine;
        $geocoderClass = new $geocoderName();

        $coords = [
            'lat' => -1,
            'lon' => -1,
            'postal_code' => '',
            'precision' => -1,
        ];
        //Si potrebbe usare l'operatore ... da php 7.4 ma per compatibilità lascio array_merge
        $res = $geocoderClass->geocode($origin, 0, 0);
        if ($res) {
            $coords = array_merge($coords, $res);
        }

        return $coords;
    }

    /**
     * Check if company is complete (name and survey are not empty)
     *
     * @return bool True if name and survey are not empty
     */
    public function checkComplete(): bool
    {

      // Check if name is not empty
        if (empty($this->name)) {
            return false;
        }

      // Check if address is not empty
        if (empty($this->address)) {
            return false;
        }

      // Check if cap is not empty
        if (empty($this->cap)) {
            return false;
        }

      // Check if city is not empty
        if (empty($this->city)) {
            return false;
        }

      // Check if province is not empty
        if (empty($this->province)) {
            return false;
        }

      // Check if company_id is not empty
        if (empty($this->company_id)) {
            return false;
        }

      // Check if lat is not empty
        if (empty($this->lat)) {
            return false;
        }

      // Check if lon is not empty
        if (empty($this->lon)) {
            return false;
        }

      // Check if office_code is not empty
        if (empty($this->office_code)) {
            return false;
        }

      // Check if survey field is not empty (adjust field name if different)
        if (empty($this->survey)) {
            return false;
        }

        return true;
    }

    /**
     * Conta gli impiegati della sede, opzionalmente filtrando per anno.
     *
     * L'anno è risolto su `users.years` (vedi EmployeesTable::countForOffice),
     * perché la tabella `employees` non ha una colonna anno.
     *
     * @param string|int|null $year Anno di riferimento; se null conta tutti gli impiegati.
     * @return int
     */
    public function numEmployees($year = null)
    {
        $employeesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Employees');

        return $employeesTable->countForOffice($this->id, $year);
    }

    public function getNumEmployees()
    {
        $office_survey = $this->survey;
      //TODO: fare somma di dipendenti +  collaboratori + interinali
        if (!empty($office_survey) && isset($office_survey['ul_nr_dipendenti'])) {
            return $office_survey['ul_nr_dipendenti'];
        } else {
            return -1;
        }
    }
}

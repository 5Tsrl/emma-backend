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

    public function geocode($origin)
    {
        $geocodingEngine = Configure::read('Geocoding.Engine', 'komootGeocoder');
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
        $coords = array_merge($coords, $geocoderClass->geocode($origin, 0, 0));

        return $coords;
    }
}

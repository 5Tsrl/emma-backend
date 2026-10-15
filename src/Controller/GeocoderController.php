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


namespace App\Controller;

use App\Model\Entity\Origin;
use Cake\Core\Configure;

class GeocoderController extends AppController
{
    private $geocodingEngine;

    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'index',
        ]);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'index',
                ]
            );
        }
    }

    public function index($geocodingEngine = null)
    {
        $q = $this->request->getQuery('q');
        if ($q) {
            $origin['q'] = $q;
            $origin['address'] = '';
            $origin['city'] = '';
            $origin['postal_code'] = '';
            $origin['province'] = '';
            $origin['district'] = '';
        } else {
            $origin['address'] = $this->request->getQuery('address');
            $origin['city'] = $this->request->getQuery('city');
            $origin['postal_code'] = $this->request->getQuery('postal_code');
            $origin['province'] = $this->request->getQuery('province');
            $origin['district'] = $this->request->getQuery('district');
        }
        //Se il geocoder lo supporta lo uso per focalizzare la ricerca
        $focusLat = $this->request->getQuery('lat');
        $focusLon = $this->request->getQuery('lon');

        $this->geocodingEngine = $geocodingEngine ?? Configure::read('Geocoding.Engine', 'komootGeocoder');
        //Creo dinamicamente l'oggetto geocoder, in base al parametro di configurazione
        //https://www.php.net/manual/en/functions.variable-functions.php
        $geocoderName = '\\App\\Geocoder\\' . $this->geocodingEngine;
        $geocoderClass = new $geocoderName();

        $coords = [
            'lat' => 0,
            'lon' => 0,
            'postal_code' => '',
            'precision' => -1,
        ];

        $originEntity = new Origin($origin);
        $res = $geocoderClass->geocode($originEntity, $focusLat, $focusLon);
        if (isset($res['lat']) && isset($res['lon'])) {
            $coords = array_merge($coords, $res);
        }

        $this->set('lat', $coords['lat']);
        $this->set('lon', $coords['lon']);
        $this->set('address', $coords['address'] ?? $origin['address']);
        $this->set('postal_code', $coords['postal_code'] ?? $origin['postal_code']);
        $this->set('city', $coords['city'] ?? $origin['city']);
        $this->set('province', $coords['province'] ?? $origin['province']);
        $this->set('precision', 1);
        $this->set('geohash', $coords['geohash'] ?? null);
        $this->viewBuilder()->setOption('serialize', ['lat', 'lon','address' ,'postal_code', 'city', 'province', 'precision','geohash']);
    }
}

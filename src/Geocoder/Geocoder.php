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
namespace App\Geocoder;

use Cake\Core\Configure;

//Classe base da cui ereditano i vari geocoder
class Geocoder
{
    protected $url;

    protected $province = [
        'AG' => 'Agrigento',
        'AL' => 'Alessandria',
        'AN' => 'Ancona',
        'AO' => 'Aosta',
        'AR' => 'Arezzo',
        'AP' => 'Ascoli Piceno',
        'AT' => 'Asti',
        'AV' => 'Avellino',
        'BA' => 'Bari',
        'BT' => 'Barletta-Andria-Trani',
        'BL' => 'Belluno',
        'BN' => 'Benevento',
        'BG' => 'Bergamo',
        'BI' => 'Biella',
        'BO' => 'Bologna',
        'BZ' => 'Bolzano',
        'BS' => 'Brescia',
        'BR' => 'Brindisi',
        'CA' => 'Cagliari',
        'CL' => 'Caltanissetta',
        'CB' => 'Campobasso',
        'CE' => 'Caserta',
        'CT' => 'Catania',
        'CZ' => 'Catanzaro',
        'CH' => 'Chieti',
        'CO' => 'Como',
        'CS' => 'Cosenza',
        'CR' => 'Cremona',
        'KR' => 'Crotone',
        'CN' => 'Cuneo',
        'EN' => 'Enna',
        'FM' => 'Fermo',
        'FE' => 'Ferrara',
        'FI' => 'Firenze',
        'FG' => 'Foggia',
        'FC' => 'Forlì-Cesena',
        'FR' => 'Frosinone',
        'GE' => 'Genova',
        'GO' => 'Gorizia',
        'GR' => 'Grosseto',
        'IM' => 'Imperia',
        'IS' => 'Isernia',
        'SP' => 'La Spezia',
        'AQ' => 'L\'Aquila',
        'LT' => 'Latina',
        'LE' => 'Lecce',
        'LC' => 'Lecco',
        'LI' => 'Livorno',
        'LO' => 'Lodi',
        'LU' => 'Lucca',
        'MC' => 'Macerata',
        'MN' => 'Mantova',
        'MS' => 'Massa-Carrara',
        'MT' => 'Matera',
        'ME' => 'Messina',
        'MI' => 'Milano',
        'MO' => 'Modena',
        'MB' => 'Monza e della Brianza',
        'NA' => 'Napoli',
        'NO' => 'Novara',
        'NU' => 'Nuoro',
        'OR' => 'Oristano',
        'PD' => 'Padova',
        'PA' => 'Palermo',
        'PR' => 'Parma',
        'PV' => 'Pavia',
        'PG' => 'Perugia',
        'PU' => 'Pesaro e Urbino',
        'PE' => 'Pescara',
        'PC' => 'Piacenza',
        'PI' => 'Pisa',
        'PT' => 'Pistoia',
        'PN' => 'Pordenone',
        'PZ' => 'Potenza',
        'PO' => 'Prato',
        'RG' => 'Ragusa',
        'RA' => 'Ravenna',
        'RC' => 'Reggio Calabria',
        'RE' => 'Reggio Emilia',
        'RI' => 'Rieti',
        'RN' => 'Rimini',
        'RM' => 'Roma',
        'RO' => 'Rovigo',
        'SA' => 'Salerno',
        'SS' => 'Sassari',
        'SV' => 'Savona',
        'SI' => 'Siena',
        'SR' => 'Siracusa',
        'SO' => 'Sondrio',
        'SU' => 'Sud Sardegna',
        'TA' => 'Taranto',
        'TE' => 'Teramo',
        'TR' => 'Terni',
        'TO' => 'Torino',
        'TP' => 'Trapani',
        'TN' => 'Trento',
        'TV' => 'Treviso',
        'TS' => 'Trieste',
        'UD' => 'Udine',
        'VA' => 'Varese',
        'VE' => 'Venezia',
        'VB' => 'Verbano-Cusio-Ossola',
        'VC' => 'Vercelli',
        'VR' => 'Verona',
        'VV' => 'Vibo Valentia',
        'VI' => 'Vicenza',
        'VT' => 'Viterbo',
    ];

    public function __construct()
    {
        $this->url = Configure::read('Geocoding.Url');
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
    }

    protected function throttler($requestsInSeconds, $throttlerID)
    {

        // Use FLOCK() to create a system global lock (it's crash-safe:))
        $fp = fopen(sys_get_temp_dir() . "/$throttlerID", 'w+');

        // exclusive lock will blocking wait until obtained
        if (flock($fp, LOCK_EX)) {
            // Sleep for a while (requestsInSeconds should be 1 or higher)
            $time_to_sleep = (int)(999999999 / $requestsInSeconds);
            time_nanosleep(0, $time_to_sleep);

            flock($fp, LOCK_UN); // unlock
        }

        fclose($fp);
    }
}

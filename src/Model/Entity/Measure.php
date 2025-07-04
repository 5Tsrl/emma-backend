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

use App\Indicator\emission0;
use Cake\Core\Configure;
use Cake\ORM\Entity;

/**
 * Measure Entity
 *
 * @property int $id
 * @property string|null $slug
 * @property int|null $pillar_id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $img
 * @property string|null $target
 * @property int|null $type
 *
 * @property \App\Model\Entity\Pillar $pillar
 */
class Measure extends Entity
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
    'slug' => true,
    'pillar_id' => true,
    'name' => true,
    'description' => true,
    'img' => true,
    'target' => true,
    'type' => true,
    'pillar' => true,
    'service_url' => true,
    'inputs' => true,
    'indicator' => true,
    ];
    private $pscl_measures = [];

    public function __construct($id = [], $table = null, $ds = null)
    {
        parent::__construct($id, $table, $ds);
        $this->pscl_measures = Configure::read('Measures'); // PSCL
    }

  // PSCL

    public function calculateImpactPscl($id, $series)
    {
        $measure_indicator = $this->pscl_measures[$id]['indicator'];
        try {
            if (!isset($series['days'])) {
                $series['days'] = 0;
            }
            if (!isset($series['users'])) {
                $series['users'] = 0;
            }
            if (!isset($series['distance'])) {
                $series['distance'] = 0;
            }
            $r = $measure_indicator($series);
        } catch (\Exception $e) {
            $e0 = new emission0();
            $r = $e0->getOutput();
        }

        return $r;
    }

    public function getPsclLabels($id)
    {
        $r = $this->pscl_measures[$id]['labels'];

        return $r;
    }
}

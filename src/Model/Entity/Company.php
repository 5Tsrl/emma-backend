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

use Cake\ORM\Entity;

/**
 * Company Entity
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $cap
 * @property string|null $city
 * @property string|null $province
 * @property string|null $country
 * @property int|null $num_employees
 * @property int|null $moma_id
 * @property string|null $company_type_id
 * @property string|null $ateco
 * @property int|null $pscl_template_id
 *
 * @property \App\Model\Entity\Moma $moma
 * @property \App\Model\Entity\CompanyType $company_type
 * @property \App\Model\Entity\Survey[] $surveys
 */
class Company extends Entity
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

        // Check if survey field is not empty (adjust field name if different)
        if (empty($this->survey)) {
            return false;
        }

        // Check if survey array contains null values
        foreach ($this->survey as $name => $value) {
            if ($value === null && stripos($name, 'altro') === false) {
                return false;
            }
        }

        return true;
    }

    public function getNumEmployees()
    {
        if (!empty($this->survey) && isset($this->survey['nr_dipendenti'])) {
            return $this->survey['nr_dipendenti'];
        } else {
            return -1;
        }
    }
}

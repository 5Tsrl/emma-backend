<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Pscl Entity
 *
 * @property int $id
 * @property string|null $version_tag
 * @property int|null $company_id
 * @property int|null $office_id
 * @property int|null $survey_id
 * @property array|null $plan
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\Office $office
 * @property \App\Model\Entity\Survey $survey
 */
class Pscl extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'version_tag' => true,
        'company_id' => true,
        'office_id' => true,
        'survey_id' => true,
        'plan' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'office' => true,
        'survey' => true,
    ];
}

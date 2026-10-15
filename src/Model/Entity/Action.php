<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Action Entity
 *
 * @property int $id
 * @property int $pscl_id
 * @property int $measure_id
 * @property string $title
 * @property string $description
 * @property string $detailed_description
 * @property int|null $level
 * @property int|null $priority
 * @property bool $benefits_employees_time
 * @property bool $benefits_employees_costs
 * @property bool $benefits_employees_safety
 * @property bool $benefits_employees_comfort
 * @property bool $benefits_employees_social
 * @property bool $benefits_employees_other
 * @property string|null $benefits_employees_other_text
 * @property bool $benefits_company_regolarita
 * @property bool $benefits_company_dedizione
 * @property bool $benefits_company_image
 * @property bool $benefits_company_other
 * @property string|null $benefits_company_other_text
 * @property bool $benefits_community_emissions
 * @property bool $benefits_community_congestion
 * @property bool $benefits_community_safety
 * @property bool $benefits_community_other
 * @property string|null $benefits_community_other_text
 * @property string $usage_methods
 * @property string $target_audience
 * @property string $timeline
 * @property string $economic_resources
 * @property string $human_resources
 * @property string $instrumental_resources
 * @property \Cake\I18n\FrozenTime $created_at
 * @property \Cake\I18n\FrozenTime|null $updated_at
 *
 * @property \App\Model\Entity\Measure $measure
 */
class Action extends Entity
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
        'pscl_id' => true,
        'measure_id' => true,
        'title' => true,
        'description' => true,
        'detailed_description' => true,
        'level' => true,
        'priority' => true,
        'benefits_employees_time' => true,
        'benefits_employees_costs' => true,
        'benefits_employees_safety' => true,
        'benefits_employees_comfort' => true,
        'benefits_employees_social' => true,
        'benefits_employees_other' => true,
        'benefits_employees_other_text' => true,
        'benefits_company_regolarita' => true,
        'benefits_company_dedizione' => true,
        'benefits_company_image' => true,
        'benefits_company_other' => true,
        'benefits_company_other_text' => true,
        'benefits_community_emissions' => true,
        'benefits_community_congestion' => true,
        'benefits_community_safety' => true,
        'benefits_community_other' => true,
        'benefits_community_other_text' => true,
        'usage_methods' => true,
        'target_audience' => true,
        'timeline' => true,
        'economic_resources' => true,
        'human_resources' => true,
        'instrumental_resources' => true,
        'created' => true,
        'modified' => true,
    ];
}

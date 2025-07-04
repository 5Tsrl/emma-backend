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

use Cake\ORM\Behavior\Translate\TranslateTrait;
use Cake\ORM\Entity;

/**
 * Survey Entity
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $company_id
 * @property string|null $version_tag
 * @property string|null $description
 * @property \Cake\I18n\FrozenDate|null $date
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 * @property int|null $user_id
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\Answer[] $answers
 */
class Survey extends Entity
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
        'questions' => true,
        'id' => false,
    ];
    use TranslateTrait;
}

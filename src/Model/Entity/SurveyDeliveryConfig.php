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
 * @property int $days_before_first_reminder
 * @property int $days_before_second_reminder
 * @property string|null $invitation_template
 * @property string|null $reminder_template
 * @property int $is_active
 * @property int $survey_id
 *
 * @property \App\Model\Entity\Survey $survey
 */
class SurveyDeliveryConfig extends Entity
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
    'days_before_first_reminder' => true,
    'days_before_second_reminder' => true,
    'invitation_template' => true,
    'reminder_template' => true,
    'invitation_subject' => true,
    'reminder_subject' => true,
    'is_active' => true,
    'survey_id' => true,
    'survey' => true,
    'sender_name' => true,
    'sender_email' => true,
    'mailer_config' => true,
    'mail_footer' => true,
    ];
}

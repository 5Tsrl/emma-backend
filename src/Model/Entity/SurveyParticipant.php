<?php
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

declare(strict_types=1);

namespace App\Model\Entity;

use App\Notification\surveyNotification;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Exception;

/**
 * Company Entity
 *
 * @property int $id
 * @property int $survey_id
 * @property int $employee_id
 * @property int $is_survey_completed
 * @property int $sent_invitation_num
 * @property string|null $last_invitation_date
 *
 * @property \App\Model\Entity\Survey $survey
 * @property \App\Model\Entity\Employee $employee
 */
class SurveyParticipant extends Entity
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

    public function sendToParticipant($survey, $type)
    {
        $participantData['id'] = $this->id;
        try {
            if (empty($this->user)) {
                throw new Exception('Participant has no user');
            }

            //Devo controllare che la mail sia valida e che non sia un segnaposto creato durante
            //l'importazione
            $email = trim($this->user['email']);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@email.invalid') == false) {
                $n = new surveyNotification(
                    $survey,
                    $this->toArray(),
                    $type == 'invitation'
                );
                $n->setFrom($survey->survey_delivery_config->sender_email, $survey->survey_delivery_config->sender_name);
                if (empty($survey->survey_delivery_config->mailer_config)) {
                    $survey->survey_delivery_config->mailer_config = 'default';
                }
                Log::write('debug', "Pronto all'invio di $email");
                $res = $n->toEmailQueue($survey->survey_delivery_config->mailer_config);
                Log::write('debug', "Accodata l' $email");
                if ($type == 'reminder') {
                    $participantData["first_{$type}_delivered_at"] = date('Y-m-d H:i:s');
                } elseif ($type == 'invitation') {
                    $participantData["{$type}_delivered_at"] = date('Y-m-d H:i:s');
                }

                $participantData['notifications'] = [[
                    'channel' => 'email',
                    'type' => $type,
                    'delivered_at' => date('Y-m-d H:i:s'),
                    'created' => date('Y-m-d H:i:s'),
                ]];
            }
        } catch (Exception $e) {
            $participantData['notifications'] = [[
                'channel' => 'email',
                'type' => $type,
                'errors' => $e->getMessage(),
                'created' => date('Y-m-d H:i:s'),
            ]];
        }

        return $participantData;
    }
}

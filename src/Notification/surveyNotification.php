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

namespace App\Notification;

use Cake\Core\Configure;
use Cake\Routing\Router;
use Notifications\Notification\mustacheNotification;

class surveyNotification extends mustacheNotification
{
    public function __construct($survey, $participant, $is_invitation)
    {
        // todo: il link deve contenere la corretta sintassi di vue
        $frontEnd = Configure::read('FrontendUrl');
        $survey_link = Router::url("$frontEnd/questionari/fill/{$survey['id']}/{$participant['id']}", true);

        $prefix = $is_invitation ? 'invitation' : 'reminder';
        $vars = [
            'nome' => "<b>{$participant['user']['first_name']}</b>",
            'titolo' => "<b>{$survey['name']}</b>",
            'link' => "<a href=\"{$survey_link}\">{$survey_link}</a>",
            'id' => $survey['id'],
        ];

        parent::__construct(
            (object)$participant['user'],
            $survey['survey_delivery_config']["{$prefix}_subject"],
            $survey['survey_delivery_config']["{$prefix}_template"],
            $vars,
            $survey->survey_delivery_config->mailer_config,
            Configure::read('sitedir')  . '/' . $survey->logo,
        );
    }
}

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

class psclNotification extends mustacheNotification
{
    public function __construct($toUser, $subject, $mustacheTemplate, $mustacheVars, $mailer = 'default', $logo = null)
  {
    // todo: il link deve contenere la corretta sintassi di vue
    $frontEnd = Configure::read('FrontendUrl');
    $pscl_link = Router::url("$frontEnd/pscl", true);
    $mustacheVars['link'] = "<a href=\"{$pscl_link}\">{$pscl_link}</a>";
    parent::__construct(
        $toUser, 
        $subject, 
        $mustacheTemplate, 
        $mustacheVars, 
        $mailer, 
        $logo);
  }
}

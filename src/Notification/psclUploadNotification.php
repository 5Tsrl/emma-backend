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

namespace App\Notification;

use Cake\Core\Configure;
use Notifications\Notification\baseNotification;

class psclUploadNotification extends baseNotification
{
    public function __construct($toUser, $office_id, $fname, $token, $company, $year)
    {
        parent::__construct();
        $this->to = Configure::read('MailAdmin');
        $this->subject = "Nuovo PSCL Caricato da $toUser";
        $this->vars = [
                'user' => $toUser,
                'office_id' => $office_id,
                'fname' => $fname,
                'referer' => $_SERVER['HTTP_REFERER'],
                'token' => $token,
                'company' => $company,
                'year' => $year,
            ];
    }
}

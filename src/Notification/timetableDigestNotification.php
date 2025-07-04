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
use Notifications\Notification\baseNotification;

class timetableDigestNotification extends baseNotification
{
    private $timetables;

    public function __construct($timetables, $user)
    {
        parent::__construct();
        $this->timetables = $timetables;
        $this->from = Configure::read('MailAdmin');
        $this->to = $user->email;
        $this->subject = '#orariscuole - Aggiornamento al ' . date('d-M-Y');
        $this->vars = ['timetables' => $this->timetables, 'user' => $user];
    }
}

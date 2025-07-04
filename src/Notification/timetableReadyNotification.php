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

class timetableReadyNotification extends baseNotification
{
    private $timetable;

    public function __construct($timetable)
    {
        parent::__construct();
        $this->timetable = $timetable;
        $this->from = Configure::read('MailAdmin');
        $this->to = Configure::read('MailAgenzia');
        $this->subject = "#orari - {$this->timetable->office->name} - {$this->timetable->office->extended_name}  {$this->timetable->office->company->name} - {$this->timetable->office->city}";
        $this->vars = ['timetable' => $this->timetable];
    }
}

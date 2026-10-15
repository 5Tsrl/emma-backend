<?php
declare(strict_types=1);

namespace App\Notification;

use Cake\Core\Configure;
use Notifications\Notification\baseNotification;

/**
 * Notification sent to users 30 days before account expiration
 * asking them to confirm they are still active
 */
class userExpirationNotification extends baseNotification
{
    public function __construct($toUser, $token, $expirationDate)
    {
        parent::__construct();
        $referer = Configure::read('FrontendUrl');
        $platformName = Configure::read('VUE_APP_TITLE');
        $this->vars = [
            'user' => $toUser,
            'token' => $token,
            'referer' => $referer,
            'platformName' => $platformName,
            'expirationDate' => $expirationDate,
        ];
        $this->subject = 'Conferma che sei ancora attivo - Account in scadenza';
        $this->to = $toUser->email;
    }
}

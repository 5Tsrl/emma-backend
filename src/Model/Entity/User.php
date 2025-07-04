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

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;
use Cake\ORM\RulesChecker;
use Cake\Utility\Security;
use Firebase\JWT\JWT;
use Notifications\Notification\forgotPasswordNotification;

/**
 * Application specific User Entity with non plugin conform field(s)
 */

class User extends Entity
// implements IdentityInterface
{
    protected $_accessible = [
        '*' => true,
    ];
    protected $_hidden = [
    'password',
    'token',
    'token_expires',
    'api_token'
    // Add other sensitive fields here
];

    public const TOKEN_HOUR_LIVE = HOUR * 24;

    public function getToken($uid)
    {
        $expireTime = time() + self::TOKEN_HOUR_LIVE;
        $alg = 'HS256'; // Replace with your algorithm
        $token = JWT::encode([
            'sub' => $uid,
            'exp' => $expireTime,
        ], Security::getSalt(), $alg);

        return $token;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['company_id'], 'Companies'));

        return $rules;
    }

    protected function _setPassword(string $password): ?string
    {
        if (strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }
    }

    public function requestResetPassword($user, $referer = null)
    {
        $token = $this->getToken($user->id);
        // $user->save();
        //Compongo la notifica
        $n = new forgotPasswordNotification($user, $token, $referer);

        //Invio la notifica
        $n->toMail();

        return ['msg' => 'Controlla la tua mail, troverai un link per il cambio password. A tra poco.',
            'token' => $token,
        ];
    }
}

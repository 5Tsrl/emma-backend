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
use Cake\Http\Cookie\Cookie;
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
    /**
     * Ruoli canonici dell'applicazione (per i controlli lato controller/policy).
     *
     * NB: i plugin Subscriptions/Testcommuters creano utenti tecnici con i ruoli
     * `sportello`/`pendolare`: NON sono ruoli "canonici" e non vanno mai assegnabili
     * dalla UI di gestione utenti, ma esistono a DB. La validazione del valore di
     * `role` usa la lista più ampia self::ASSIGNABLE_ROLES, mentre admin/moma_area
     * possono assegnare solo i ruoli in self::ROLES.
     */
    public const ROLES = ['admin', 'moma', 'moma_area', 'user', 'superiori'];

    /**
     * Tutti i valori di `role` accettati dalla validazione, inclusi i ruoli tecnici
     * generati dai plugin (sportello, pendolare). Serve solo a rifiutare stringhe
     * arbitrarie senza rompere i flussi SSO/abbonamenti esistenti.
     */
    public const ASSIGNABLE_ROLES = ['admin', 'moma', 'moma_area', 'user', 'superiori', 'sportello', 'pendolare'];

    /**
     * Allow-list di mass-assignment (anti VH1 / Broken Access Control).
     *
     * Sono assegnabili via patchEntity()/newEntity() SOLO i campi self-service di
     * profilo e i campi relazionali/anagrafici che la UI modifica legittimamente.
     * I campi sensibili (escalation di privilegio o di tenant) sono bloccati a false
     * e vanno impostati esplicitamente nel controller/servizio (oppure tramite
     * l'opzione 'accessibleFields' nei flussi interni fidati: command, import HR,
     * SSO dei plugin).
     */
    protected $_accessible = [
        // --- Self-service / profilo (assegnabili) ---
        'first_name' => true,
        'last_name' => true,
        'email' => true,
        'mobile' => true,
        'cf' => true,
        'badge_number' => true,
        'tos_date' => true,
        'expiration_date' => true,
        'active' => true,
        'years' => true,
        'subcompany' => true,
        'username' => true,
        'company_id' => true,
        'office_id' => false,
        // Relazioni gestite esplicitamente nei flussi fidati
        'batches' => true,
        // --- Campi sensibili: NON mass-assignabili ---
        'role' => false,
        'is_superuser' => false,
        'password' => false,
        'token' => false,
        'token_expires' => false,
        'api_token' => false,
        'refresh_token' => false,
        'refresh_token_expires' => false,
        'id' => false,
    ];
    protected $_hidden = [
    'password',
    'token',
    'token_expires',
    'api_token',
    'refresh_token',
    'refresh_token_expires',
    // Add other sensitive fields here
    ];

    public const TOKEN_HOUR_LIVE = HOUR;
    public const REFRESH_TOKEN_MONTH_LIVE = DAY * 30;

    /**
     * Durata BREVE dell'access token emesso al login/refresh (anti VM1 — improper
     * logout). NON tocca il default di getToken(): i token di reset password e gli
     * altri flussi (Pscl, SSO) continuano a usare TOKEN_HOUR_LIVE.
     */
    public const ACCESS_TOKEN_LIVE = MINUTE * 15;

    /**
     * Durata di default del pending-login token (vedi getPendingLoginToken()),
     * sovrascrivibile da Configure::read('MultiProfileLogin.pendingTtl').
     */
    public const PENDING_LOGIN_LIVE = MINUTE * 5;

    //TODO: essendo nella entity $uid è ridondante, usare $this->id

    public function getToken($uid, $duration = self::TOKEN_HOUR_LIVE)
    {
        $expireTime = time() + $duration;
        $alg = 'HS256'; // Replace with your algorithm
        // jti: identificatore univoco del token, necessario per la revoca puntuale
        // lato server (denylist RevokedTokens). iat: emissione, utile per audit.
        $token = JWT::encode([
            'sub' => $uid,
            'exp' => $expireTime,
            'iat' => time(),
            'jti' => self::generateJti(),
        ], Security::getSalt(), $alg);

        return $token;
    }

    /**
     * Generate a refresh token valid for 30 days
     *
     * @param int $uid User ID
     * @return string JWT refresh token
     */
    //TODO: essendo nella entity $uid è ridondante, usare $this->id

    public function getRefreshToken($uid)
    {
        $expireTime = time() + self::REFRESH_TOKEN_MONTH_LIVE;
        $alg = 'HS256';
        $token = JWT::encode([
            'sub' => $uid,
            'exp' => $expireTime,
            'iat' => time(),
            'jti' => self::generateJti(),
            'type' => 'refresh',
        ], Security::getSalt(), $alg);

        return $token;
    }

    /**
     * Identificatore univoco del token (JWT ID). 32 caratteri esadecimali.
     *
     * @return string
     */
    public static function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Decodifica un JWT (HS256) restituendo il payload, o null se non valido/scaduto.
     * Usato per estrarre jti/exp in fase di revoca (logout/rotazione refresh).
     *
     * @param string|null $token Il JWT da decodificare.
     * @return object|null Il payload decodificato, o null se non valido/scaduto.
     */
    public static function decodeToken($token)
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }
        try {
            return JWT::decode($token, new \Firebase\JWT\Key(Security::getSalt(), 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validate and decode a refresh token
     *
     * @param string $token JWT refresh token
     * @return object|null Decoded token data or null if invalid
     */
    public static function validateRefreshToken($token)
    {
        try {
            $decoded = JWT::decode($token, new \Firebase\JWT\Key(Security::getSalt(), 'HS256'));

            // Verify it's a refresh token
            if (!isset($decoded->type) || $decoded->type !== 'refresh') {
                return null;
            }

            // Verify token hasn't expired
            if ($decoded->exp < time()) {
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Genera un token JWT a vita breve ("pending login") usato quando più record
     * Users corrispondono alle stesse credenziali/allo stesso claim IdP (vedi
     * App\Identifier\MultiProfilePasswordIdentifier/MultiProfileAuth0Identifier e
     * UsersController::login()/selectProfile()). Il token elenca gli id dei soli
     * profili già verificati (credenziali corrette per ognuno di essi): la scelta
     * finale dell'utente in selectProfile() è validata contro questo elenco, quindi
     * non serve far reinserire le credenziali per completare l'accesso.
     *
     * @param array<int|string> $userIds Id dei profili tra cui scegliere.
     * @return string
     */
    public static function getPendingLoginToken(array $userIds): string
    {
        $ttl = (int)\Cake\Core\Configure::read('MultiProfileLogin.pendingTtl') ?: self::PENDING_LOGIN_LIVE;

        return JWT::encode([
            'profiles' => array_values(array_map('strval', $userIds)),
            'exp' => time() + $ttl,
            'iat' => time(),
            'jti' => self::generateJti(),
            'type' => 'pending_login',
        ], Security::getSalt(), 'HS256');
    }

    /**
     * Decodifica e valida un pending-login token generato da getPendingLoginToken().
     *
     * @param string|null $token Il token da validare.
     * @return object|null Il payload (con `profiles`), o null se mancante/scaduto/non del tipo atteso.
     */
    public static function validatePendingLoginToken($token): ?object
    {
        $decoded = self::decodeToken($token);
        if (!$decoded || empty($decoded->type) || $decoded->type !== 'pending_login' || empty($decoded->profiles)) {
            return null;
        }

        return $decoded;
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

    public function getHttpOnlyCookies()
    {

                // Access token a vita breve (anti VM1) + refresh token a vita lunga.
                $token = $this->getToken($this->id, User::ACCESS_TOKEN_LIVE);
                $refreshToken = $this->getRefreshToken($this->id);

                $this->refresh_token = $refreshToken;
                $this->refresh_token_expires = date('Y-m-d H:i:s', time() + User::REFRESH_TOKEN_MONTH_LIVE);
                $this->token = $token;
                // token_expires, exp del JWT e scadenza del cookie sono ora allineati
                // alla stessa finestra (prima il cookie scadeva in 1 minuto: bug).
                $this->token_expires = date('Y-m-d H:i:s', time() + User::ACCESS_TOKEN_LIVE);

                // Imposta cookie HttpOnly + Secure per access token
                $accessCookie = new Cookie(
                    'jwt_token',
                    $token,
                    new \DateTime('+' . User::ACCESS_TOKEN_LIVE . ' seconds'),
                    '/',
                    null, // dominio (null = automatico)
                    true, // secure
                    true, // httpOnly
                    'Lax' // SameSite
                );

                // Imposta cookie HttpOnly + Secure per refresh token (30 giorni)
                $refreshCookie = new Cookie(
                    'jwt_refresh_token',
                    $refreshToken,
                    new \DateTime('+30 days'), // 30 giorni
                    '/',
                    null, // dominio (null = automatico)
                    true, // secure
                    true, // httpOnly
                    'Lax' // SameSite
                );

                return [$refreshCookie, $accessCookie];
    }
}

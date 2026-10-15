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

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Denylist dei JWT revocati (anti VM1 — improper logout).
 *
 * Vedi CookieJwtAuthenticator (controllo ad ogni richiesta), UsersController::logout()
 * e ::refreshToken() (inserimento), PurgeRevokedTokensCommand (pulizia righe scadute).
 */
class RevokedTokensTable extends Table
{
    /**
     * Initialize method.
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('revoked_tokens');
        $this->setDisplayField('jti');
        $this->setPrimaryKey('id');
    }

    /**
     * Il token identificato da $jti è stato revocato?
     *
     * @param string|null $jti jti del token da verificare
     * @return bool
     */
    public function isRevoked(?string $jti): bool
    {
        if (empty($jti)) {
            return false;
        }

        return $this->exists(['jti' => $jti]);
    }

    /**
     * Aggiunge un token alla denylist (idempotente sul jti).
     *
     * @param string|null $jti jti del token da revocare
     * @param string|null $userId proprietario (audit) — UUID come users.id
     * @param string $type 'access' | 'refresh'
     * @param \DateTimeInterface|int|string|null $expiresAt scadenza originale del token (claim exp)
     * @return bool true se revocato (o già presente)
     */
    public function revoke(?string $jti, ?string $userId, string $type = 'access', $expiresAt = null): bool
    {
        if (empty($jti)) {
            return false;
        }
        if ($this->exists(['jti' => $jti])) {
            return true;
        }

        if ($expiresAt instanceof \DateTimeInterface) {
            $expires = $expiresAt->format('Y-m-d H:i:s');
        } elseif (is_numeric($expiresAt)) {
            $expires = date('Y-m-d H:i:s', (int)$expiresAt);
        } elseif (is_string($expiresAt) && $expiresAt !== '') {
            $expires = date('Y-m-d H:i:s', strtotime($expiresAt));
        } else {
            // Scadenza ignota: trattiamo come finestra massima dell'access token.
            $expires = date('Y-m-d H:i:s', time() + \App\Model\Entity\User::REFRESH_TOKEN_MONTH_LIVE);
        }

        $now = date('Y-m-d H:i:s');
        $entity = $this->newEntity([
            'jti' => $jti,
            'user_id' => $userId,
            'token_type' => $type,
            'expires_at' => $expires,
            'revoked_at' => $now,
            'created' => $now,
        ]);

        return (bool)$this->save($entity);
    }

    /**
     * Elimina le righe relative a token già scaduti (non più utili in denylist).
     *
     * @return int numero di righe eliminate
     */
    public function purgeExpired(): int
    {
        return $this->deleteAll(['expires_at <' => date('Y-m-d H:i:s')]);
    }
}

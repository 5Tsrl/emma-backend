<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Denylist dei token revocati (anti VM1 — improper logout / JWT non invalidato).
 *
 * L'access token JWT è validato solo da firma + exp: dopo il logout un token
 * catturato resta valido fino a scadenza. Qui registriamo il `jti` dei token
 * revocati (al logout e alla rotazione del refresh) così che
 * CookieJwtAuthenticator possa rifiutarli ad ogni richiesta. Le righe scadute
 * (expires_at < now) vengono ripulite dal comando `bin/cake purge_revoked_tokens`.
 *
 * Solo DDL: creazione tabella, nessuna manipolazione dati.
 */
class CreateRevokedTokens extends AbstractMigration
{
    /**
     * Crea la tabella revoked_tokens (denylist dei JWT revocati).
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('revoked_tokens');
        // jti del token revocato (claim JWT). Univoco: una sola riga per token.
        $table->addColumn('jti', 'string', ['limit' => 64, 'null' => false]);
        // Proprietario del token (per audit/pulizia mirata). UUID come users.id.
        // Nullable: i token legacy/di servizio potrebbero non essere associabili.
        $table->addColumn('user_id', 'string', ['limit' => 36, 'null' => true, 'default' => null]);
        // 'access' | 'refresh'.
        $table->addColumn('token_type', 'string', ['limit' => 16, 'null' => false, 'default' => 'access']);
        // Scadenza originale del token: dopo questo istante la riga è inutile
        // (il token sarebbe comunque scaduto) e può essere ripulita.
        $table->addColumn('expires_at', 'datetime', ['null' => false]);
        $table->addColumn('revoked_at', 'datetime', ['null' => false]);
        $table->addColumn('created', 'datetime', ['null' => true, 'default' => null]);

        // Lookup per jti ad ogni richiesta autenticata: deve essere unico e indicizzato.
        $table->addIndex(['jti'], ['unique' => true, 'name' => 'idx_revoked_tokens_jti']);
        // Usato dalla pulizia periodica.
        $table->addIndex(['expires_at'], ['name' => 'idx_revoked_tokens_expires']);

        $table->create();
    }
}

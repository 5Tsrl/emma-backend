<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Allarga users.token e users.api_token da 255 a 512 caratteri.
 *
 * Gli access token JWT ora includono i claim `jti` e `iat` (anti VM1): la stringa
 * codificata supera i 255 caratteri e verrebbe troncata silenziosamente al
 * salvataggio in users.token, rendendo il token non decodificabile. Questa
 * migrazione va eseguita PRIMA di emettere i nuovi token.
 *
 * Solo DDL (changeColumn): nessuna manipolazione dati nello stesso step.
 */
class WidenUserTokenColumns extends AbstractMigration
{
    /**
     * Allarga token e api_token a 512 caratteri.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('users')
            ->changeColumn('token', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->changeColumn('api_token', 'string', ['limit' => 512, 'null' => true, 'default' => null])
            ->update();
    }

    /**
     * Ripristina la larghezza originale di 255 caratteri.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('users')
            ->changeColumn('token', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->changeColumn('api_token', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}

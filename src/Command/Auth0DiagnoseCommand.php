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
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use SecretField\Crypto\SecretBoxCipher;

/**
 * Diagnostica perché un token Auth0 non risolve un utente in whoami.
 *
 * Replica ESATTAMENTE la lookup di EncryptedOrmResolver (email cifrata in modo
 * deterministico con SecretField.key) e dice in un colpo solo se il problema è:
 *  - chiave SECRET_FIELD_KEY mancante o diversa da quella che ha cifrato il db
 *  - migrazione di cifratura non eseguita (email in chiaro)
 *  - utente assente / non attivo
 *
 * Uso (dentro il container backend):
 *   HTTP_HOST=maremma.5t.torino.it bin/cake auth0_diagnose admin@nomail.com
 */
class Auth0DiagnoseCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addArgument('email', [
            'help' => "Email dell'utente Auth0 (claim https://.../email del token)",
            'required' => true,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $email = (string)$args->getArgument('email');

        $io->out('<info>== Auth0 / SecretField diagnose ==</info>');
        $io->out('Email cercata: ' . $email);
        $io->hr();

        // 1) Chiave runtime (mostro solo un fingerprint, mai la chiave in chiaro)
        $key = (string)Configure::read('SecretField.key');
        if ($key === '') {
            $io->err('<error>SECRET_FIELD_KEY: VUOTA</error> — la ricerca avverrà in chiaro.');
        } else {
            $io->out(sprintf(
                'SECRET_FIELD_KEY: presente (len=%d, sha256=%s…)',
                strlen($key),
                substr(hash('sha256', $key), 0, 12)
            ));
        }

        // 2) Stato migrazione di cifratura
        try {
            $conn = ConnectionManager::get('default');
            $row = $conn->execute(
                'SELECT version FROM phinxlog WHERE version = 20260510093000'
            )->fetch('assoc');
            $io->out('Migrazione 20260510093000 (cifratura email): '
                . ($row ? '<success>ESEGUITA</success>' : '<warning>NON eseguita</warning>'));
        } catch (\Throwable $e) {
            $io->warning('Impossibile leggere phinxlog: ' . $e->getMessage());
        }

        $io->hr();

        // 3) Lookup deterministica identica a EncryptedOrmResolver
        $encrypted = $email;
        if ($key !== '') {
            $cipher = new SecretBoxCipher($key, 'sf1$', true, false);
            $encrypted = (string)$cipher->encrypt($email);
        }
        $io->out('Valore cifrato per la lookup: ' . substr($encrypted, 0, 16) . '…');

        $users = TableRegistry::getTableLocator()->get('Users');

        $byEncrypted = $users->find()->where(['email' => $encrypted])->first();
        $byPlain = $users->find()->where(['email' => $email])->first();

        $io->hr();
        $io->out('<info>Esito lookup:</info>');
        $io->out(' - match su email CIFRATA : ' . ($byEncrypted ? "TROVATO (id={$byEncrypted->id}, active={$byEncrypted->active})" : 'nessuno'));
        $io->out(' - match su email IN CHIARO: ' . ($byPlain ? "TROVATO (id={$byPlain->id}, active={$byPlain->active})" : 'nessuno'));

        $io->hr();
        $io->out('<info>Diagnosi:</info>');
        if ($byEncrypted) {
            if (!$byEncrypted->active) {
                $io->warning('Utente trovato ma NON attivo: whoami lo rifiuta. Attivare l\'utenza.');
            } else {
                $io->success('La lookup Auth0 funziona: chiave e dati sono allineati. Il 401 ha altra causa.');
            }
        } elseif ($byPlain) {
            $io->warning('Email memorizzata IN CHIARO ma il resolver cifra la lookup → eseguire la migrazione 20260510093000 (oppure la chiave era assente quando si è cifrato).');
        } elseif ($key === '') {
            $io->err('Nessun match e chiave assente: se a db le email sono cifrate (sf1$…), impostare SECRET_FIELD_KEY con la chiave usata in cifratura.');
        } else {
            $io->err('Nessun match con chiave presente: o l\'utente non esiste su questo db, oppure SECRET_FIELD_KEY NON coincide con quella che ha cifrato le email. Verificare la chiave / ri-cifrare.');
        }

        return static::CODE_SUCCESS;
    }
}

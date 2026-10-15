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

namespace App\Command;

use App\Notification\userExpirationNotification;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

/**
 * TestExpirationUsers command.
 *
 * Eseguire via cron 1 volta al giorno.
 * Questo comando:
 * - Trova gli utenti attivi con scadenza tra 30 giorni e manda una mail di conferma
 * - Disabilita gli utenti la cui scadenza è passata
 */
class TestExpirationUsersCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('test', [
                'help' => 'Modalità test: non manda davvero le mail, solo output a console',
                'boolean' => true,
            ])
            ->addOption('days', [
                'help' => 'Numero di giorni prima della scadenza per inviare il promemoria (default: 30)',
                'default' => '30',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->Users = TableRegistry::getTableLocator()->get('Users');
        $test = $args->getOption('test');
        $days = (int)$args->getOption('days');

        $io->out('=== Test Expiration Users Command ===');
        $io->out('Modalità test: ' . ($test ? 'SI' : 'NO'));
        $io->out("Giorni preavviso: $days");
        $io->out('');

        // 1. Trova utenti con scadenza tra $days giorni (promemoria)
        $this->sendExpirationWarnings($io, $test, $days);

        $io->out('');

        // 2. Disabilita utenti scaduti
        $this->disableExpiredUsers($io, $test);

        $io->out('');
        $io->success('Comando completato.');
    }

    /**
     * Trova gli utenti attivi con scadenza tra $days giorni e manda email di promemoria
     */
    protected function sendExpirationWarnings(ConsoleIo $io, bool $test, int $days): void
    {
        $io->out("--- Invio promemoria scadenza ($days giorni) ---");

        $targetDate = FrozenDate::now()->addDays($days);

        $users = $this->Users->find()
            ->where([
                'Users.active' => 1,
                'Users.email IS NOT' => null,
                'Users.expiration_date <=' => $targetDate->format('Y-m-d'),
                'Users.role NOT IN' => ['admin', 'user'],
            ])
            ->all();

        if ($users->count() == 0) {
            $io->out('Nessun utente con scadenza tra ' . $days . ' giorni.');

            return;
        }

        $io->out('Trovati ' . $users->count() . ' utenti con scadenza il ' . $targetDate->format('d/m/Y'));

        foreach ($users as $user) {
            $io->out("  - {$user->email} (scadenza: {$user->expiration_date->format('d/m/Y')})");

            if (!$test) {
                // Genera token per conferma rinnovo (usa lo stesso meccanismo del reset password)
                $token = $user->getToken($user->id, DAY * 35); // Token valido 35 giorni

                // Salva il token nello user per la verifica successiva
                $user->token = $token;
                $user->token_expires = date('Y-m-d H:i:s', time() + DAY * 35);

                if ($this->Users->save($user)) {
                    // Invia notifica
                    $n = new userExpirationNotification($user, $token, $user->expiration_date);
                    $n->toMail();
                    $io->success("    Mail inviata a {$user->email}");
                } else {
                    $io->error("    Errore nel salvare token per {$user->email}");
                }
            }
        }
    }

    /**
     * Disabilita utenti la cui data di scadenza è passata
     */
    protected function disableExpiredUsers(ConsoleIo $io, bool $test): void
    {
        $io->out('--- Disabilitazione utenti scaduti ---');

        $today = FrozenDate::now();

        $expiredUsers = $this->Users->find()
            ->where([
                'Users.active' => 1,
                'Users.expiration_date IS NOT' => null,
                'Users.expiration_date <' => $today->format('Y-m-d'),
                'Users.role NOT IN' => ['admin', 'user'],
            ])
            ->all();

        if ($expiredUsers->count() == 0) {
            $io->out('Nessun utente scaduto da disabilitare.');

            return;
        }

        $io->out('Trovati ' . $expiredUsers->count() . ' utenti scaduti');

        $ids = [];
        foreach ($expiredUsers as $user) {
            $io->out("  - {$user->email} (scaduto il: {$user->expiration_date->format('d/m/Y')})");
            $ids[] = $user->id;
        }

        if (!$test) {
            $this->Users->updateAll(
                ['active' => 0],
                ['id IN' => $ids]
            );
            $io->success('Utenti disabilitati: ' . count($ids));
        }
    }
}

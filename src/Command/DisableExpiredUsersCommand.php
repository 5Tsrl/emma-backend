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

use App\Notification\userDisabledNotification;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\FrozenDate;
use Cake\ORM\TableRegistry;

/**
 * DisableExpiredUsers command.
 *
 * Disabilita (active = 0) gli utenti la cui expiration_date è passata,
 * escludendo i ruoli admin e user.
 * Eseguire via cron 1 volta al giorno.
 */
class DisableExpiredUsersCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Disabilita gli utenti con data di scadenza passata (esclusi admin e user).')
            ->addOption('test', [
                'help' => 'Modalità test: mostra gli utenti che verrebbero disabilitati senza effettuare modifiche',
                'boolean' => true,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->Users = TableRegistry::getTableLocator()->get('Users');
        $test = (bool)$args->getOption('test');
        $today = FrozenDate::now();

        $io->out('=== Disable Expired Users Command ===');
        $io->out('Modalità test: ' . ($test ? 'SI' : 'NO'));
        $io->out('Data odierna: ' . $today->format('d/m/Y'));
        $io->out('');

        $expiredUsers = $this->Users->find()
            ->where([
                'Users.active' => 1,
                'Users.expiration_date IS NOT' => null,
                'Users.expiration_date <' => $today->format('Y-m-d'),
                'Users.role NOT IN' => ['admin', 'user'],
            ])
            ->all();

        if ($expiredUsers->count() === 0) {
            $io->out('Nessun utente scaduto da disabilitare.');

            return static::CODE_SUCCESS;
        }

        $io->out('Trovati ' . $expiredUsers->count() . ' utenti scaduti');

        $ids = [];
        foreach ($expiredUsers as $user) {
            $io->out("  - {$user->email} (ruolo: {$user->role}, scaduto il: {$user->expiration_date->format('d/m/Y')})");
            $ids[] = $user->id;
        }

        if (!$test) {
            $this->Users->updateAll(
                ['active' => 0],
                ['id IN' => $ids]
            );
            $io->success('Utenti disabilitati: ' . count($ids));

            // Invia email di notifica agli utenti disabilitati
            foreach ($expiredUsers as $user) {
                if (!empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $n = new userDisabledNotification($user);
                        $n->toMail();
                        $io->out("    Mail inviata a {$user->email}");
                    } catch (\Exception $e) {
                        $io->error("    Errore invio mail a {$user->email}: " . $e->getMessage());
                    }
                }
            }
        } else {
            $io->warning('Modalità test: nessuna modifica effettuata.');
        }

        return static::CODE_SUCCESS;
    }
}

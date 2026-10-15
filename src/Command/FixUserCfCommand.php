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

use App\Model\Table\UsersTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * FixUserCf command.
 *
 * Bonifica i Codici Fiscali sporchi nella tabella users (ruoli/città/aziende
 * finiti in users.cf per via di import HR mal mappati o form abbonamenti).
 *
 * Per ogni utente con un cf NON vuoto che non rispetta ^[A-Z0-9]{16}$:
 *   1) RECUPERO: se l'employee collegato ha role_description che è un CF valido,
 *      lo sposta in users.cf e azzera quel role_description.
 *   2) ALTRIMENTI: imposta users.cf = NULL (spazzatura non recuperabile).
 *
 * Idempotente. Usa updateAll() per non far scattare la validazione (i nuovi
 * valori sono comunque validi: CF a 16 caratteri o NULL).
 *
 * Uso (per ogni sito):
 *   bin/cake fix_user_cf --test     # dry-run, mostra solo i conteggi
 *   bin/cake fix_user_cf            # applica le modifiche
 */
class FixUserCfCommand extends Command
{
    /**
     * Pattern SQL di un CF valido (16 caratteri alfanumerici). REGEXP MySQL è
     * case-insensitive con le collation di default, coerente con UsersTable::isValidCf().
     */
    private const CF_REGEXP = '^[A-Z0-9]{16}$';

    /**
     * @var \Cake\ORM\Table
     */
    protected $Users;

    /**
     * @var \Cake\ORM\Table
     */
    protected $Employees;

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Bonifica i Codici Fiscali non validi in users.cf (recupera da employees.role_description quando possibile, altrimenti azzera).')
            ->addOption('test', [
                'help' => 'Modalità test: mostra cosa verrebbe modificato senza scrivere nulla',
                'boolean' => true,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $this->Users = $this->fetchTable('Users');
        $this->Employees = $this->fetchTable('Employees');
        $test = (bool)$args->getOption('test');

        $io->out('=== Fix User CF Command ===');
        $io->out('Modalità test: ' . ($test ? 'SI (nessuna modifica)' : 'NO'));
        $io->hr();

        // Condizione (SQL raw: REGEXP non è un operatore riconosciuto dal query builder).
        $invalidCfRaw = "cf IS NOT NULL AND cf <> '' AND cf NOT REGEXP '" . self::CF_REGEXP . "'";

        $totalInvalid = $this->Users->find()->where([$invalidCfRaw])->count();
        $io->out("Utenti con CF non valido: $totalInvalid");

        if ($totalInvalid === 0) {
            $io->success('Niente da bonificare.');

            return static::CODE_SUCCESS;
        }

        // 1) RECUPERO da employees.role_description (se è un CF valido) verso users.cf,
        //    ma solo per utenti che hanno ancora un cf non valido.
        $recoveredUserIds = [];
        $employees = $this->Employees->find()
            ->contain(['Users'])
            ->where(["Employees.role_description REGEXP '" . self::CF_REGEXP . "'"])
            ->all();

        foreach ($employees as $emp) {
            $user = $emp->user ?? null;
            if ($user === null) {
                continue;
            }
            // Non toccare chi ha già un CF valido.
            if (UsersTable::isValidCf($user->cf) && trim((string)$user->cf) !== '') {
                continue;
            }
            $cf = strtoupper(trim((string)$emp->role_description));
            $recoveredUserIds[] = $user->id;
            $io->verbose("  RECUPERO {$user->email}: role_description '{$emp->role_description}' -> cf");
            if (!$test) {
                $this->Users->updateAll(['cf' => $cf], ['id' => $user->id]);
                $this->Employees->updateAll(['role_description' => null], ['id' => $emp->id]);
            }
        }
        $io->out('CF recuperati da role_description: ' . count($recoveredUserIds));

        // 2) AZZERA i restanti cf non validi (esclusi quelli appena recuperati).
        $nullConditions = [$invalidCfRaw];
        if (!empty($recoveredUserIds)) {
            $nullConditions['id NOT IN'] = $recoveredUserIds;
        }
        $toNull = $this->Users->find()->where($nullConditions)->count();
        $io->out("CF da azzerare (spazzatura non recuperabile): $toNull");
        if (!$test && $toNull > 0) {
            $this->Users->updateAll(['cf' => null], $nullConditions);
        }

        if ($test) {
            $io->warning('Modalità test: nessuna modifica effettuata.');
        } else {
            $io->success('Bonifica completata. Recuperati: ' . count($recoveredUserIds) . ', azzerati: ' . $toNull);
        }

        return static::CODE_SUCCESS;
    }
}

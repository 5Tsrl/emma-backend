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

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class SetUsersActiveAndExpirationDateCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Attiva tutti gli utenti e imposta la data di scadenza per i soli utenti non admin.')
            ->addOption('expiration-date', [
                'help' => 'Data di scadenza da impostare ai non admin (formato YYYY-MM-DD)',
                'default' => '2026-12-31',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->Users = TableRegistry::getTableLocator()->get('Users');

        $expirationDate = (string)$args->getOption('expiration-date');

        $allUsersCount = $this->Users->find()->count();
        $nonAdminUsersCount = $this->Users->find()
            ->where(['role !=' => 'admin'])
            ->count();

        $this->Users->updateAll(
            ['active' => 1],
            []
        );

        $this->Users->updateAll(
            ['expiration_date' => $expirationDate],
            ['role !=' => 'admin']
        );

        $io->success("Utenti attivati: {$allUsersCount}");
        $io->success("Expiration date impostata a {$expirationDate} per {$nonAdminUsersCount} utenti non admin");

        return static::CODE_SUCCESS;
    }
}

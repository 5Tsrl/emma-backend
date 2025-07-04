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

class CreateUsersFromCompaniesCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
        ->addOption('company_type_id', [
        'help' => "Inserire l'id del tipo di azienda a cui si vuole creare la pwd (9=tpl, 4=superiori)",
        'default' => 9,
        ])
        ->addOption('pwd_prefix', [
        'help' => 'Inserire il prefisso della pwd che si vuole generare (es: orariscuole-)',
        'default' => 'orariscuole',
        ])
        ->addOption('role', [
        'help' => 'Inserire il ruolo (admin, superiori, moma)',
        'default' => 'superiori',
        ])
        ->addOption('self', [
        'help' => 'Se =1 vuol dire che può vedere solo la sua azienda, altrimenti tutte',
        'default' => 1,
        ])
        ->addOption('min_id', [
        'help' => 'Inizia a creare le pwd oltre un certo id company',
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->loadModel('Companies');
        $this->loadModel('Users');
        $company_type_id = (int)$args->getOption('company_type_id');

        //$company_type_id = 4;   //Scuole Superiori
        //$company_type_id = 9; //Aziende di TPL
        //$role = 'superiori';
        $role = $args->getOption('role');
        $self = $args->getOption('self');

        $companies = $this->Companies->find()
        ->select(['email', 'city', 'id', 'name'])
        ->where(['type' => $company_type_id])
        ->where(['email IS NOT' => null]);

        $min_id = $args->getOption('min_id');
        if (!empty($min_id)) {
            $companies
            ->where(['id >' => $min_id]);
        }
        $io->out("Password che verranno cambiate: {$companies->count()}");
        $pwd_prefix = $args->getOption('pwd_prefix');

        foreach ($companies as $c) {
            $parts = explode('@', $c->email);
            $pwd = $pwd_prefix . '-' . strtoupper($parts[0]);
            $existingUser = $this->Users->findByEmail($c->email)->first();
            if (empty($existingUser)) {
                $user = $this->Users->newEntity([
                'username' => $c->email,
                'email' => $c->email,
                'password' => $pwd,
                'active' => true,
                'role' => $role,
                'company_id' => $self == 1 ? $c->id : null,
                'first_name' => substr($c->name, 49),
                'last_name' => $c->city,
                ]);
                $action = 'creato';
            } else {
                $user = $this->Users->patchEntity($existingUser, [
                'username' => $c->email,
                'email' => $c->email,
                'password' => $pwd,
                'active' => true,
                'role' => $role,
                'company_id' => $self == 1 ? $c->id : null,
                'first_name' => substr($c->name, 49),
                'last_name' => $c->city,
                ]);
                $action = 'aggiornato';
            }
            if ($this->Users->save($user)) {
                $io->out("{$c->email} $action con successo");
            } else {
                $io->out("{$c->email} ERRORE durante la creazione");
            }
        }
    }
}

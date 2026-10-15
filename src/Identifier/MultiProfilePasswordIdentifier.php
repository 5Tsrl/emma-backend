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

namespace App\Identifier;

use Authentication\Identifier\PasswordIdentifier;
use Authentication\Identifier\Resolver\ResolverInterface;

/**
 * Identifier per il login a form, "multi-profile-aware".
 *
 * `users.username` non ha un vincolo di unicità a DB (a differenza di `email`/`cf`,
 * vincolati a livello applicativo, vedi UsersTable::buildRules()): può capitare che
 * più record Users condividano lo stesso username/email (es. lo stesso dipendente
 * con un account su più aziende, o dati storici precedenti al vincolo). Il
 * PasswordIdentifier standard restituirebbe in quel caso il primo risultato della
 * query senza nessuna garanzia su quale sia il profilo "giusto".
 *
 * Questo identifier, prima di procedere, verifica quanti record Users
 * corrispondono a username/email forniti (EncryptedOrmResolver::findAll()):
 * - 0 o 1 corrispondenza → comportamento standard (delega a PasswordIdentifier).
 * - più corrispondenze → verifica la password su ciascuna. Se una sola verifica
 *   (caso più comune: account diversi hanno password diverse), procede come se
 *   fosse l'unico match. Se più di una verifica (stessa password su più profili),
 *   NON sceglie arbitrariamente: espone l'elenco via getErrors()['multiple_profiles']
 *   così che UsersController::login() possa proporre la scelta al client (piccola
 *   modale "scegli il profilo") invece di autenticare un profilo a caso.
 *
 * Il login si completa poi su UsersController::selectProfile(), che verifica il
 * pending-login token emesso da User::getPendingLoginToken() e il profilo scelto.
 */
class MultiProfilePasswordIdentifier extends PasswordIdentifier
{
    /**
     * @inheritDoc
     */
    public function identify(array $credentials)
    {
        if (!isset($credentials[self::CREDENTIAL_USERNAME])) {
            return null;
        }

        $resolver = $this->getResolver();
        if (!method_exists($resolver, 'findAll')) {
            // Resolver senza supporto multi-profilo (findAll()): comportamento standard.
            return parent::identify($credentials);
        }

        $fields = (array)$this->getConfig('fields.' . self::CREDENTIAL_USERNAME);
        $conditions = [];
        foreach ($fields as $field) {
            $conditions[$field] = $credentials[self::CREDENTIAL_USERNAME];
        }

        $candidates = iterator_to_array($resolver->findAll($conditions, ResolverInterface::TYPE_OR));

        if (count($candidates) <= 1) {
            // Nessuna ambiguità (0 o 1 corrispondenza): comportamento standard.
            return parent::identify($credentials);
        }

        // Più record corrispondono: verifico la password su ciascuno, così un
        // tentativo con credenziali sbagliate resta un fallimento normale.
        $password = $credentials[self::CREDENTIAL_PASSWORD] ?? null;
        $matches = [];
        foreach ($candidates as $candidate) {
            if ($this->_checkPassword($candidate, $password)) {
                $matches[] = $candidate;
            }
        }

        if (count($matches) === 0) {
            return null; // credenziali non valide: nessun errore speciale
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        // Più profili condividono le stesse credenziali: propongo la scelta invece
        // di autenticare arbitrariamente il primo. Vedi UsersController::login().
        $this->_errors = [
            'multiple_profiles' => $matches,
        ];

        return null;
    }
}

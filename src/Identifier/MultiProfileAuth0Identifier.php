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

/**
 * Identifier per il login via Auth0/OAuth, "multi-profile-aware".
 *
 * Stessa logica di MultiProfilePasswordIdentifier ma senza password da verificare:
 * il claim email del token Auth0 (vedi Auth0Identifier::$_defaultConfig['dataField'])
 * può corrispondere a più record Users (stesso problema di username, vedi la classe
 * gemella). Non essendoci una credenziale aggiuntiva per restringere la scelta,
 * quando ci sono più corrispondenze la propongo SEMPRE al client tramite
 * getErrors()['multiple_profiles'], invece di autenticare arbitrariamente la prima.
 *
 * Vedi UsersController::login()/selectProfile() per il completamento del login.
 */
class MultiProfileAuth0Identifier extends Auth0Identifier
{
    /**
     * @inheritDoc
     */
    public function identify(array $credentials)
    {
        $dataField = $this->getConfig('dataField');
        if (!isset($credentials[$dataField])) {
            return null;
        }

        $resolver = $this->getResolver();
        if (!method_exists($resolver, 'findAll')) {
            // Resolver senza supporto multi-profilo (findAll()): comportamento standard.
            return parent::identify($credentials);
        }

        $conditions = [
            $this->getConfig('tokenField') => $credentials[$dataField],
        ];

        $candidates = iterator_to_array($resolver->findAll($conditions));

        if (count($candidates) <= 1) {
            // Nessuna ambiguità (0 o 1 corrispondenza): comportamento standard.
            return parent::identify($credentials);
        }

        // Più record Users condividono la stessa email: nessuna password da
        // verificare per restringere la scelta, la propongo sempre al client.
        $this->_errors = [
            'multiple_profiles' => $candidates,
        ];

        return null;
    }
}

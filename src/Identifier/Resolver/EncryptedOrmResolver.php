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

namespace App\Identifier\Resolver;

use Authentication\Identifier\Resolver\OrmResolver;
use Cake\Core\Configure;
use Cake\Log\Log;
use SecretField\Crypto\SecretBoxCipher;

class EncryptedOrmResolver extends OrmResolver
{
    /**
     * @inheritDoc
     */
    public function find(array $conditions, $type = self::TYPE_AND)
    {
        return $this->buildQuery($conditions, $type)->first();
    }

    /**
     * Come find(), ma restituisce TUTTI i record che soddisfano le condizioni invece
     * del solo primo. Usato dagli identifier "multi-profile-aware" (vedi
     * App\Identifier\MultiProfilePasswordIdentifier/MultiProfileAuth0Identifier) per
     * accorgersi che più record Users corrispondono alle stesse credenziali/allo
     * stesso claim (es. stesso username o stessa email su più aziende) prima di
     * decidere se autenticare o proporre la scelta del profilo.
     *
     * @param array $conditions Find conditions (stesso formato di find()).
     * @param string $type Condition type. Can be `AND` or `OR`.
     * @return \Cake\Datasource\ResultSetInterface
     */
    public function findAll(array $conditions, $type = self::TYPE_AND)
    {
        return $this->buildQuery($conditions, $type)->all();
    }

    /**
     * Costruisce la query condivisa da find()/findAll(): stesso finder/condizioni,
     * con l'email cifrata (SecretField) quando presente tra le condizioni.
     *
     * @param array $conditions Find conditions.
     * @param string $type Condition type. Can be `AND` or `OR`.
     * @return \Cake\ORM\Query
     */
    private function buildQuery(array $conditions, $type = self::TYPE_AND)
    {
        $table = $this->getTableLocator()->get($this->_config['userModel']);

        $query = $table->selectQuery();
        $finders = (array)$this->_config['finder'];
        foreach ($finders as $finder => $options) {
            if (is_string($options)) {
                $query->find($options);
            } else {
                $query->find($finder, $options);
            }
        }

        $where = [];
        foreach ($conditions as $field => $value) {
            if ($this->isEmailField((string)$field) && is_string($value)) {
                $value = $this->encryptEmailForLookup($value);
            }

            $field = $table->aliasField($field);
            if (is_array($value)) {
                $field = $field . ' IN';
            }
            $where[$field] = $value;
        }

        return $query->where([$type => $where]);
    }

    private function isEmailField(string $field): bool
    {
        return $field === 'email' || substr($field, -6) === '.email';
    }

    private function encryptEmailForLookup(string $email): string
    {
        $key = (string)Configure::read('SecretField.key');
        if ($key === '') {
            // Senza chiave la ricerca avviene in chiaro: se gli indirizzi a db
            // sono cifrati (prefisso 'sf1$') nessun utente verrà mai trovato.
            // È esattamente il caso che rende silenziosa l'autenticazione Auth0.
            Log::write(
                'warning',
                'SecretField.key mancante: ricerca email in chiaro, le email cifrate non verranno trovate',
                ['scope' => ['login']]
            );

            return $email;
        }

        $cipher = new SecretBoxCipher(
            $key,
            'sf1$',
            true,
            false
        );

        return (string)$cipher->encrypt($email);
    }
}

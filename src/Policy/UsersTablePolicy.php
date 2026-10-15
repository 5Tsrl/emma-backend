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

namespace App\Policy;

use Authorization\Policy\BeforeScopeInterface;
use Cake\Database\Expression\QueryExpression;

/**
 * Users table policy
 *
 * Limita l'enumerazione della rubrica utenti (index/indexNoPagination) allo scope
 * del chiamante (anti VH1 Broken Access Control).
 *
 * - admin            => nessun filtro (vede tutto)
 * - moma_area        => NESSUN accesso alla sezione Users (round 4 VH1): il
 *                       controller rifiuta già l'azione a monte
 *                       (UsersController::index()/indexNoPagination()), qui si
 *                       nega comunque (query a zero righe) per difesa in
 *                       profondità nel caso lo scope fosse raggiunto altrimenti
 * - moma (azienda)   => solo utenti della propria azienda (company_id);
 *                       company_id vuoto/nullo nega tutto (nessuna visibilità
 *                       implicita su tutta la rubrica)
 * - user             => bloccato a monte nel controller (non lista)
 */
class UsersTablePolicy implements BeforeScopeInterface
{
    /**
     * Applica lo scope azienda alla query utenti prima dello scope d'azione.
     *
     * @param \Authorization\IdentityInterface|\App\Model\Entity\User $user
     * @param \Cake\ORM\Query $query
     * @param string $action
     * @return \Cake\ORM\Query
     */
    public function beforeScope($user, $query, $action)
    {
        // L'admin (e qualsiasi ruolo senza vincolo di area/azienda) vede tutto.
        if ($user->role === 'admin') {
            return $query;
        }

        // moma_area non ha più accesso alla sezione Users (VH1 round 4): nega
        // sempre (query a zero righe), difesa in profondità.
        if ($user->role === 'moma_area') {
            return $query->where(function (QueryExpression $exp) {
                return $exp->add('1=0');
            });
        }

        // moma (e qualsiasi altro ruolo vincolato): solo la propria azienda.
        // company_id vuoto/nullo nega tutto (chiude il bug VH1: prima restituiva
        // una query non filtrata, cioè TUTTI gli utenti, invece di zero righe).
        $company_id = $user->company_id;
        if (!empty($company_id)) {
            return $query->where(['Users.company_id' => $company_id]);
        }

        return $query->where(function (QueryExpression $exp) {
            return $exp->add('1=0');
        });
    }

    /**
     * Scope per l'azione index (lo scope effettivo è applicato in beforeScope).
     *
     * @param \Authorization\IdentityInterface|\App\Model\Entity\User $user
     * @param \Cake\ORM\Query $query
     * @return \Cake\ORM\Query
     */
    public function scopeIndex($user, $query)
    {
        return $query;
    }

    /**
     * Scope per l'azione indexNoPagination (lo scope effettivo è in beforeScope).
     *
     * @param \Authorization\IdentityInterface|\App\Model\Entity\User $user
     * @param \Cake\ORM\Query $query
     * @return \Cake\ORM\Query
     */
    public function scopeIndexNoPagination($user, $query)
    {
        return $query;
    }
}

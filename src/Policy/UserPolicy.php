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

use App\Model\Entity\User;
use Authorization\IdentityInterface;
use Authorization\Policy\Result;

/**
 * User policy
 *
 * Matrice dei ruoli (anti VH1 Broken Access Control):
 * - admin            => tutti gli utenti
 * - moma             => solo gli utenti della propria azienda (company_id);
 *                       company_id vuoto/nullo NEGA tutto (nessuna visibilità
 *                       implicita), NON visibilità globale
 * - moma_area        => NESSUN accesso alla sezione Users (round 4 VH1): perde
 *                       la visibilità per area geografica che aveva in
 *                       precedenza, canView/canEdit/canChangeRole sono sempre
 *                       false, anche sul proprio profilo
 * - user / superiori => solo il proprio record
 *
 * Solo admin può vedere/modificare TUTTI gli utenti.
 */
class UserPolicy
{
    /**
     * Check if $user can view User
     *
     * @param \Authorization\IdentityInterface $user The identity.
     * @param \App\Model\Entity\User $resource The target user.
     * @return \Authorization\Policy\Result
     */
    public function canView(IdentityInterface $user, User $resource): Result
    {
        // moma_area non ha più accesso alla sezione Users (VH1 round 4): nega
        // sempre, anche sul proprio profilo. Difesa in profondità: il controller
        // blocca già l'azione a monte (UsersController::index()/find()).
        if ($user->get('role') === 'moma_area') {
            return new Result(false, 'moma-area-no-access');
        }

        // Chiunque può vedere il proprio profilo (user/superiori e ogni altro ruolo).
        if ((string)$user->getIdentifier() === (string)$resource->id) {
            return new Result(true);
        }

        // L'utente "user" può vedere solo se stesso.
        if ($user->get('role') === 'user') {
            return new Result(false, 'not-owner');
        }

        // Solo admin ha visibilità globale su tutti gli utenti.
        if ($user->get('role') === 'admin') {
            return new Result(true);
        }

        // moma: solo all'interno della propria azienda. company_id vuoto/nullo
        // nega tutto (chiude il bug VH1: prima un moma senza company_id vedeva
        // QUALSIASI utente invece di nessuno).
        if ($user->get('role') === 'moma') {
            $companyId = $user->get('company_id');
            if (!empty($companyId) && (string)$companyId === (string)$resource->company_id) {
                return new Result(true);
            }

            return new Result(false, 'not-same-company');
        }

        return new Result(false, 'forbidden');
    }

    /**
     * Check if $user can edit User
     *
     * @param \Authorization\IdentityInterface $user The identity.
     * @param \App\Model\Entity\User $resource The target user.
     * @return \Authorization\Policy\Result
     */
    public function canEdit(IdentityInterface $user, User $resource): Result
    {
        // moma_area non ha più accesso alla sezione Users (VH1 round 4): nega
        // sempre, anche sul proprio profilo. Difesa in profondità: il controller
        // blocca già l'azione edit a monte con allowRolesOnly(['admin']).
        if ($user->get('role') === 'moma_area') {
            return new Result(false, 'moma-area-no-access');
        }

        // moma è sola lettura sugli utenti (VH1 round 3): non può modificare
        // nessun record, nemmeno il proprio profilo. Può comunque cambiare la
        // propria password (changePassword) e il proprio TOS: percorsi separati,
        // non passano da qui.
        if ($user->get('role') === 'moma') {
            return new Result(false, 'moma-read-only');
        }

        // L'utente può modificare il proprio profilo.
        if ((string)$user->getIdentifier() === (string)$resource->id) {
            return new Result(true);
        }

        // Solo admin può modificare TUTTI gli utenti.
        if ($user->get('role') === 'admin') {
            return new Result(true);
        }

        return new Result(false, 'forbidden');
    }

    /**
     * Check if $user can add User
     *
     * @param \Authorization\IdentityInterface $user The identity.
     * @param \App\Model\Entity\User $resource The new user entity.
     * @return \Authorization\Policy\Result
     */
    public function canAdd(IdentityInterface $user, User $resource): Result
    {
        // Solo admin crea utenti dalla UI di gestione (gli altri flussi sono SSO/command).
        if ($user->get('role') === 'admin') {
            return new Result(true);
        }

        return new Result(false, 'forbidden');
    }

    /**
     * Check if $user can delete User
     *
     * @param \Authorization\IdentityInterface $user The identity.
     * @param \App\Model\Entity\User $resource The target user.
     * @return \Authorization\Policy\Result
     */
    public function canDelete(IdentityInterface $user, User $resource): Result
    {
        // Solo admin può eliminare utenti (VH1 round 3): moma è sola lettura.
        if ($user->get('role') === 'admin') {
            return new Result(true);
        }

        return new Result(false, 'forbidden');
    }

    /**
     * Check if $user can change the role of User
     *
     * Solo admin può cambiare il ruolo di un utente (VH1 round 4: moma_area ha
     * perso questo privilegio insieme a tutto il resto della sezione Users).
     *
     * @param \Authorization\IdentityInterface $user The identity.
     * @param \App\Model\Entity\User $resource The target user.
     * @return \Authorization\Policy\Result
     */
    public function canChangeRole(IdentityInterface $user, User $resource): Result
    {
        if ($user->get('role') === 'admin') {
            return new Result(true);
        }

        return new Result(false, 'forbidden');
    }
}

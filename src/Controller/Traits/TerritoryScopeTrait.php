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

namespace App\Controller\Traits;

use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\Query;

/**
 * Competenza territoriale dei `moma_area`: scope delle query, verifica di accesso e
 * risoluzione dei destinatari delle notifiche.
 *
 * La stessa logica (match su `areas.city` OR `areas.province`, via la belongsToMany
 * `Users.Areas` con property singolare `area`) era duplicata in più punti del codebase
 * (`PsclController::upload()`, `PsclController::addProtocollo()`, le Policy, e in origine
 * `AdhesionRequestsController`). Questa è l'estrazione della versione senza coordinate:
 * il raffinamento poligonale `ST_CONTAINS(areas.polygon, POINT(lat, lon))` NON è
 * applicabile qui, perché le richieste di adesione non hanno lat/lon — solo comune e
 * provincia dichiarati nel form.
 *
 * Richiede: `$this->Authentication` (componente) e `fetchTable()` (Controller).
 */
trait TerritoryScopeTrait
{
    /**
     * Restituisce [città, province] delle aree associate all'identità.
     *
     * @param mixed $identity L'identità corrente.
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function territoryOf($identity): array
    {
        if (!$identity) {
            return [[], []];
        }

        $areas = $this->fetchTable('Users')->Areas->find()
            ->matching('Users', function (Query $q) use ($identity) {
                return $q->where(['Users.id' => $identity->get('id')]);
            })
            ->all();

        $cities = [];
        $provinces = [];
        foreach ($areas as $area) {
            if (!empty($area->city)) {
                $cities[] = $area->city;
            }
            if (!empty($area->province)) {
                $provinces[] = $area->province;
            }
        }

        return [array_values(array_unique($cities)), array_values(array_unique($provinces))];
    }

    /**
     * Limita la query al territorio del `moma_area` corrente (admin vede tutto).
     *
     * Fail-closed: un `moma_area` senza aree assegnate non vede nulla, invece di vedere
     * tutto.
     *
     * @param \Cake\ORM\Query $query Query da limitare.
     * @param string $cityField Colonna qualificata del comune (es. 'AdhesionRequests.reference_city').
     * @param string $provinceField Colonna qualificata della provincia.
     * @return void
     */
    protected function scopeQueryToTerritory(Query $query, string $cityField, string $provinceField): void
    {
        $identity = $this->Authentication->getIdentity();
        if (!$identity || $identity->get('role') === 'admin') {
            return;
        }

        [$cities, $provinces] = $this->territoryOf($identity);
        if (empty($cities) && empty($provinces)) {
            $query->where(['1 = 0']);

            return;
        }

        $conditions = [];
        if (!empty($cities)) {
            $conditions[$cityField . ' IN'] = $cities;
        }
        if (!empty($provinces)) {
            $conditions[$provinceField . ' IN'] = $provinces;
        }
        $query->where(['OR' => $conditions]);
    }

    /**
     * True se l'identità corrente è territorialmente competente su comune/provincia.
     *
     * @param string|null $city Comune di riferimento.
     * @param string|null $province Provincia di riferimento.
     * @return bool
     */
    protected function identityCoversTerritory(?string $city, ?string $province): bool
    {
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return false;
        }
        if ($identity->get('role') === 'admin') {
            return true;
        }

        [$cities, $provinces] = $this->territoryOf($identity);

        // Confronto case-insensitive DELIBERATO: `AreasTable::beforeSave()` salva
        // name/city/province in maiuscolo, mentre il comune della richiesta arriva dal
        // form come lo ha scritto l'utente ("Bologna"). Le query SQL combaciano comunque,
        // perché la collation MySQL del progetto (utf8mb4_general_ci) è case-insensitive:
        // un confronto PHP stretto avrebbe quindi negato l'accesso a richieste che la
        // stessa query di scope include, con un 403 incoerente.
        $normalize = function (array $values): array {
            return array_map(function ($value) {
                return mb_strtoupper(trim((string)$value));
            }, $values);
        };
        $cities = $normalize($cities);
        $provinces = $normalize($provinces);

        $cityMatch = $city !== null && in_array(mb_strtoupper(trim($city)), $cities, true);
        $provinceMatch = $province !== null && in_array(mb_strtoupper(trim($province)), $provinces, true);

        return $cityMatch || $provinceMatch;
    }

    /**
     * Forbidden se comune/provincia sono fuori dal territorio dell'identità.
     *
     * @param string|null $city Comune di riferimento.
     * @param string|null $province Provincia di riferimento.
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException
     */
    protected function assertTerritoryFor(?string $city, ?string $province): void
    {
        if (!$this->identityCoversTerritory($city, $province)) {
            throw new ForbiddenException('Non sei autorizzato ad accedere alla risorsa richiesta');
        }
    }

    /**
     * Utenti `moma_area` attivi territorialmente competenti su comune/provincia.
     *
     * @param string|null $city Comune di riferimento.
     * @param string|null $province Provincia di riferimento.
     * @return \Cake\ORM\Query
     */
    protected function findTerritorialMomaArea(?string $city, ?string $province): Query
    {
        return $this->fetchTable('Users')->find()
            ->where(['Users.role' => 'moma_area', 'Users.active' => true])
            ->matching('Areas', function (Query $q) use ($city, $province) {
                $conditions = [];
                if (!empty($city)) {
                    $conditions['Areas.city'] = $city;
                }
                if (!empty($province)) {
                    $conditions['Areas.province'] = $province;
                }
                if (empty($conditions)) {
                    return $q->where(['1 = 0']);
                }

                return $q->where(['OR' => $conditions]);
            });
    }

    /**
     * Email dei valutatori: tutti gli admin attivi più i `moma_area` competenti,
     * deduplicati (case-insensitive). Se nessun moma_area combacia restano i soli admin.
     *
     * @param string|null $city Comune di riferimento.
     * @param string|null $province Provincia di riferimento.
     * @return array<string, string> Mappa email-lowercase => email originale.
     */
    protected function reviewerEmails(?string $city, ?string $province): array
    {
        $usersTable = $this->fetchTable('Users');

        $recipients = [];
        $admins = $usersTable->find()
            ->where(['Users.role' => 'admin', 'Users.active' => true])
            ->all();
        foreach ($admins as $admin) {
            if (!empty($admin->email)) {
                $recipients[strtolower($admin->email)] = $admin->email;
            }
        }

        foreach ($this->findTerritorialMomaArea($city, $province)->all() as $user) {
            if (!empty($user->email)) {
                $recipients[strtolower($user->email)] = $user->email;
            }
        }

        return $recipients;
    }
}

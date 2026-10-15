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

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * AdhesionRequestDecisions Model — storico dei voti dei valutatori (#538).
 *
 * @property \App\Model\Table\AdhesionRequestsTable&\Cake\ORM\Association\BelongsTo $AdhesionRequests
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @method \App\Model\Entity\AdhesionRequestDecision newEmptyEntity()
 * @method \App\Model\Entity\AdhesionRequestDecision get($primaryKey, $options = [])
 */
class AdhesionRequestDecisionsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('adhesion_request_decisions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('AdhesionRequests', [
            'foreignKey' => 'adhesion_request_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'decided_by',
            'joinType' => 'LEFT',
        ]);
    }
}

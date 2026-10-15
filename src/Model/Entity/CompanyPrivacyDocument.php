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

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * CompanyPrivacyDocument Entity
 *
 * Il "Modulo trattamento dati personali dei dipendenti" (requisito 3 della compliance
 * privacy RER "EMMA RE-R"): una riga per azienda, riusata per tutte le survey/anni
 * successivi. Vedi Service/CompanyPrivacyDocumentService per lo storage fuori webroot.
 *
 * Può esistere in due fasi: pendente (`adhesion_request_id` valorizzata, caricata già
 * durante `in_review`) o definitiva (`company_id` valorizzata, dopo l'approvazione) —
 * mai entrambe, mai nessuna delle due (vedi CompanyPrivacyDocumentsTable::buildRules()).
 *
 * @property int $id
 * @property int|null $company_id
 * @property int|null $adhesion_request_id
 * @property string $filename
 * @property string $path
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\AdhesionRequest $adhesion_request
 */
class CompanyPrivacyDocument extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Scritta solo da AdhesionRequestsController::employeePrivacyDocument() (flusso
     * token-authenticated, mai da un payload utente diretto): niente `path`/`filename`
     * non fidati, il controller li valorizza sempre dal risultato del servizio di
     * storage, mai da $this->request->getData(). Niente entry `adhesion_request`
     * annidata: nulla la marshala, e minimizzare la superficie di mass-assignment
     * resta la linea di questa entity.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'company_id' => true,
        'adhesion_request_id' => true,
        'filename' => true,
        'path' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
    ];

    /**
     * Campi mai serializzati verso il client: `path` è un percorso assoluto sul
     * filesystem del server e non deve mai comparire in una risposta JSON.
     *
     * @var array<int, string>
     */
    protected $_hidden = [
        'path',
    ];
}

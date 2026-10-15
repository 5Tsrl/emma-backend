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
namespace App\Indicator;

/**
 * Split Modale (modello BresciaMobilità, vedi AbstractSplitBSIndicator)
 * raggruppato per comune/quartiere di domicilio, derivato dalla domanda
 * "mappa" (geocodificata dall'utente) tramite la tabella `origins` — stessa
 * associazione `AnswersTable::belongsTo('Origins')` (chiave `user_id` +
 * `survey_id`, vedi CLAUDE.md/OriginsTable), NON un'altra domanda a scelta
 * singola come le sorelle splitBSOrarioLavorativo/ValutazioneViaggio/
 * DurataSpostamento.
 *
 * A differenza delle altre sottoclassi di AbstractSplitBSIndicator, qui i
 * valori sono liberi (comuni/quartieri geocodificati) e non noti a priori:
 * canonicalLabels() torna null e le etichette (asse X) vengono derivate dai
 * dati in AbstractSplitBSIndicator::count(), ordinate per totale utenti
 * decrescente, con "Dati non validi" sempre in coda (utenti senza
 * geocodifica/city).
 *
 * L'etichetta è "{comune}" quando il quartiere non è stato determinato (city
 * senza reverse-geocoding del quartiere, vedi Origin::addDistrict()), oppure
 * "{comune} - {quartiere}" quando disponibile.
 *
 * @package App\Indicator
 */
class splitBSComuneQuartiereIndicator extends AbstractSplitBSIndicator
{
    protected function resolveSecondaryDimension($survey_id): void
    {
        // Nessun binding aggiuntivo: il join è sulla stessa chiave usata da
        // AnswersTable::belongsTo('Origins') (user_id + survey_id), non su
        // un id di domanda secondaria.
    }

    protected function buildSecondaryJoinSql(): string
    {
        return 'LEFT JOIN origins o ON o.user_id = a.user_id AND o.survey_id = a.survey_id';
    }

    protected function getSecondaryValueExpr(): string
    {
        return "MAX(CASE WHEN o.city IS NULL OR o.city = '' THEN NULL "
            . "WHEN o.district IS NULL OR o.district = '' THEN o.city "
            . "ELSE CONCAT(o.city, ' - ', o.district) END)";
    }

    protected function mapRawToLabel($raw): string
    {
        if ($raw === null || $raw === '') {
            return 'Dati non validi';
        }

        return (string)$raw;
    }

    protected function canonicalLabels(): ?array
    {
        // Comuni/quartieri liberi: non c'è un elenco fisso di opzioni, le
        // etichette vengono derivate dai dati in count().
        return null;
    }
}

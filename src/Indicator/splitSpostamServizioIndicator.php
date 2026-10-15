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
 * Frequenza spostamenti di servizio per mezzo utilizzato (vedi
 * AbstractCrossQuestionIndicator — NON fa parte della famiglia
 * AbstractSplitBSIndicator/split modale casa-lavoro: incrocio semplice fra
 * due domande `type=single` indipendenti).
 *
 * Domanda asse X: "spostam-servizio" (id 601 nei seed 5t) — frequenza degli
 * spostamenti per motivi di servizio. Domanda serie: "frequenza-servizio"
 * (id 596).
 *
 * ATTENZIONE — nome domanda fuorviante: nonostante il `name` DB
 * "frequenza-servizio", la sua `description` ("Con quale modalità ti sposti
 * prevalentemente durante gli spostamenti di servizio?") e le sue opzioni
 * (mezzi di trasporto) rivelano che è la domanda MEZZO DI TRASPORTO per gli
 * spostamenti di servizio, non una domanda sulla frequenza — quella è invece
 * "spostam-servizio" (l'asse X di questo indicatore). Non lasciarsi
 * ingannare dal nome nel manutenere questa classe.
 *
 * getLabels() restituisce le 3 opzioni canoniche di "spostam-servizio" (asse
 * X) più il bucket 'Dati non validi'; getSeriesLabels() il nome di ciascuna
 * serie (mezzo di trasporto per spostamenti di servizio), ordinate per
 * totale utenti decrescente.
 *
 * @package App\Indicator
 */
class splitSpostamServizioIndicator extends AbstractCrossQuestionIndicator
{
    /**
     * Opzioni canoniche della domanda "spostam-servizio" (vedi
     * config/Seeds/QuestionsSeed.php, id 601).
     */
    private const PRIMARY_CANONICAL_LABELS = [
        'Mai od occasionalmente (non più di 2 o 3 volte al mese)',
        'Frequentemente (qualche giorno a settimana)',
        'Sempre (tutti o quasi tutti i giorni della mia settimana lavorativa)',
    ];

    /**
     * Opzioni canoniche della domanda "frequenza-servizio" (vedi
     * config/Seeds/QuestionsSeed.php, id 596 — nome fuorviante, è in realtà
     * la domanda mezzo di trasporto, vedi doc-comment di classe).
     */
    private const SECONDARY_CANONICAL_LABELS = [
        'A piedi (> 5min)',
        'Bicicletta muscolare / elettrica, cargo-bike o monopattino',
        'Trasporto pubblico (autobus, metropolitana, treno)',
        "Condivisione dell'autovettura con i colleghi (car pooling)",
        'Autoveicolo o motociclo',
    ];

    protected function resolvePrimaryQuestionId($survey_id): int
    {
        return (int)$this->resolveQuestionIdByNameOrConfig(
            'spostam-servizio',
            ['spostam-servizio', 'spostam_servizio'],
            $survey_id
        );
    }

    protected function resolveSecondaryQuestionId($survey_id): int
    {
        return (int)$this->resolveQuestionIdByNameOrConfig(
            'frequenza-servizio',
            ['frequenza-servizio', 'frequenza_servizio'],
            $survey_id
        );
    }

    protected function primaryCanonicalLabels(): array
    {
        return array_merge(self::PRIMARY_CANONICAL_LABELS, ['Dati non validi']);
    }

    protected function secondaryCanonicalLabels(): array
    {
        return self::SECONDARY_CANONICAL_LABELS;
    }
}

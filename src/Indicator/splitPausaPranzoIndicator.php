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
 * Abitudini pausa pranzo per mezzo utilizzato (vedi AbstractCrossQuestionIndicator
 * — NON fa parte della famiglia AbstractSplitBSIndicator/split modale
 * casa-lavoro: incrocio semplice fra due domande `type=single` indipendenti).
 *
 * Domanda asse X: "pausa-pranzo" (id 576 nei seed 5t) — abitudine durante la
 * pausa pranzo. Domanda serie: "mezzo-pranzo" (id 577) — mezzo di trasporto
 * usato prevalentemente durante la pausa pranzo (mostrata in survey solo
 * condizionatamente a una specifica risposta di "pausa-pranzo", ma questo è
 * irrilevante per il conteggio: il LEFT JOIN gestisce la mancanza di risposta
 * come 'Dati non validi').
 *
 * getLabels() restituisce le 4 opzioni canoniche di "pausa-pranzo" (asse X)
 * più il bucket 'Dati non validi'; getSeriesLabels() il nome di ciascuna
 * serie (mezzo di trasporto durante la pausa pranzo), ordinate per totale
 * utenti decrescente.
 *
 * @package App\Indicator
 */
class splitPausaPranzoIndicator extends AbstractCrossQuestionIndicator
{
    /**
     * Opzioni canoniche della domanda "pausa-pranzo" (vedi
     * config/Seeds/QuestionsSeed.php, id 576).
     */
    private const PRIMARY_CANONICAL_LABELS = [
        'Rimango in sede (es. mensa aziendale)',
        'Pranzo fuori sede',
        'Torno a casa',
        'Non faccio la pausa pranzo',
    ];

    /**
     * Opzioni canoniche della domanda "mezzo-pranzo" (vedi
     * config/Seeds/QuestionsSeed.php, id 577).
     */
    private const SECONDARY_CANONICAL_LABELS = [
        'A piedi (> 5min)',
        'Bicicletta muscolare / elettrica, cargo-bike o monopattino',
        'Trasporto pubblico (autobus, metropolitana, tram, treno)',
        "Condivisione dell'autovettura con i colleghi (car pooling)",
        'Autoveicolo o motociclo',
    ];

    protected function resolvePrimaryQuestionId($survey_id): int
    {
        return (int)$this->resolveQuestionIdByNameOrConfig(
            'pausa-pranzo',
            ['pausa-pranzo', 'pausa_pranzo'],
            $survey_id
        );
    }

    protected function resolveSecondaryQuestionId($survey_id): int
    {
        return (int)$this->resolveQuestionIdByNameOrConfig(
            'mezzo-pranzo',
            ['mezzo-pranzo', 'mezzo_pranzo'],
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

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
 * raggruppato per tipologia di orario di lavoro (domanda "orario-lavorativo",
 * ID 564 nei dati seed 5t).
 *
 * getLabels() restituisce le 3 opzioni canoniche della domanda (asse X), più
 * il bucket "Dati non validi" per risposte mancanti/non riconosciute;
 * getSeriesLabels() il nome di ciascuna serie (modalità di trasporto),
 * ordinate per totale utenti decrescente.
 *
 * @package App\Indicator
 */
class splitBSOrarioLavorativoIndicator extends AbstractSplitBSIndicator
{
    /**
     * Opzioni canoniche della domanda "orario-lavorativo" (vedi
     * config/Seeds/QuestionsSeed.php, id 564).
     */
    private const CANONICAL_LABELS = [
        'Orario fisso e/o con flessibilità oraria',
        'Orario a turnazione fissa',
        'Orario a turnazione variabile',
    ];

    protected function resolveSecondaryDimension($survey_id): void
    {
        $question_id = $this->resolveQuestionIdByNameOrConfig(
            'orario-lavorativo',
            ['orario-lavorativo', 'orario_lavorativo'],
            $survey_id
        );
        $this->bindings['secondary_question_id'] = (int)$question_id;
    }

    protected function buildSecondaryJoinSql(): string
    {
        $surveyJoin = isset($this->bindings['survey_id']) ? ' AND d.survey_id = :survey_id' : '';

        return "LEFT JOIN answers d ON d.user_id = a.user_id AND d.question_id = :secondary_question_id{$surveyJoin}";
    }

    protected function getSecondaryValueExpr(): string
    {
        return 'MAX(JSON_UNQUOTE(d.answer))';
    }

    protected function mapRawToLabel($raw): string
    {
        if ($raw === null || $raw === '') {
            return 'Dati non validi';
        }

        return in_array($raw, self::CANONICAL_LABELS, true) ? $raw : 'Dati non validi';
    }

    protected function canonicalLabels(): ?array
    {
        return array_merge(self::CANONICAL_LABELS, ['Dati non validi']);
    }
}

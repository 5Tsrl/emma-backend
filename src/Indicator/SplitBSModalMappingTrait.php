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
 * Unica sorgente della mappatura "mezzi -> macro-categoria" dello split modale
 * Brescia (tabella cliente "Split modale.xlsx"), condivisa da splitBSIndicator,
 * AbstractSplitBSIndicator e splitBSKMIndicator (quest'ultimo non estende la
 * base astratta, da qui un trait). Vedi docs/dev-notes/indicators-modal-split.md.
 *
 * Pipeline SQL in due passi:
 *  1. ogni TAPPA (riga di $.modes) diventa un token di una lettera
 *     (splitBsLegTokenSql) leggendo selTransportmean + type_auto/type_bus/
 *     type_bike/type_mono (colonne in splitBsLegColumnsSql);
 *  2. i token distinti del rispondente sono concatenati in `all_means`
 *     (splitBsAllMeansSql, es. "A+T"), poi splitBsCategoryCaseSql() sceglie la
 *     macro-categoria per precedenza.
 *
 * Token: W=a piedi, B=bici/monopattino/bike sharing/Scooter, A=auto
 * conducente/aziendale/moto, P=auto passeggero/carpooling, S=car sharing/taxi,
 * N=navetta, T=bus/tram/metro/treno, X=valore sconosciuto (finisce in "altro").
 *
 * Il confronto è sempre su LOWER(): non dipende dalla collation della colonna
 * JSON_TABLE (case-insensitive su MySQL 8 di default, ma non garantito).
 */
trait SplitBSModalMappingTrait
{
    /**
     * Elenco COLUMNS (JSON_TABLE) con tutti i campi letti dalla mappatura.
     *
     * @return string
     */
    protected static function splitBsLegColumnsSql(): string
    {
        return <<<'SQL'
selTransportmean VARCHAR(50) PATH '$.selTransportmean',
                                        type_auto VARCHAR(100) PATH '$.type_auto',
                                        type_bus VARCHAR(100) PATH '$.type_bus',
                                        type_bike VARCHAR(100) PATH '$.type_bike',
                                        type_mono VARCHAR(100) PATH '$.type_mono'
SQL;
    }

    /**
     * Espressione SQL che classifica una tappa (alias JSON_TABLE `modes`) in un
     * token. NULL se la tappa non ha selTransportmean.
     *
     * @return string
     */
    protected static function splitBsLegTokenSql(): string
    {
        return <<<'SQL'
CASE
    WHEN modes.selTransportmean IS NULL OR TRIM(modes.selTransportmean) = '' THEN NULL
    WHEN LOWER(TRIM(modes.selTransportmean)) = 'on_foot' THEN 'W'
    WHEN LOWER(modes.selTransportmean) LIKE '%taxi%'
        OR LOWER(modes.selTransportmean) LIKE '%car sharing%'
        OR LOWER(modes.selTransportmean) LIKE '%carsharing%'
        OR LOWER(COALESCE(modes.type_auto, '')) LIKE '%car sharing%'
        OR LOWER(COALESCE(modes.type_auto, '')) LIKE '%carsharing%'
        OR LOWER(COALESCE(modes.type_auto, '')) LIKE '%taxi%' THEN 'S'
    WHEN LOWER(modes.selTransportmean) LIKE 'auto%'
        OR LOWER(modes.selTransportmean) LIKE 'moto%' THEN
        CASE
            WHEN LOWER(COALESCE(modes.type_auto, '')) LIKE '%passegger%'
                OR LOWER(COALESCE(modes.type_auto, '')) LIKE '%carpooling%' THEN 'P'
            ELSE 'A'
        END
    WHEN LOWER(modes.selTransportmean) LIKE '%navetta%' THEN 'N'
    WHEN LOWER(modes.selTransportmean) LIKE '%bus%'
        OR LOWER(modes.selTransportmean) LIKE '%tram%'
        OR LOWER(modes.selTransportmean) LIKE '%metro%'
        OR LOWER(modes.selTransportmean) LIKE '%treno%'
        OR LOWER(modes.selTransportmean) LIKE '%train%' THEN
        CASE
            WHEN LOWER(COALESCE(modes.type_bus, '')) LIKE '%navetta%' THEN 'N'
            ELSE 'T'
        END
    WHEN LOWER(modes.selTransportmean) LIKE '%bike%'
        OR LOWER(modes.selTransportmean) LIKE '%scooter%'
        OR LOWER(modes.selTransportmean) LIKE '%monopattino%' THEN 'B'
    ELSE 'X'
END
SQL;
    }

    /**
     * Aggregato per utente (da usare in SELECT ... GROUP BY a.user_id): token
     * distinti ordinati, separati da '+'. "A piedi" (W) è scartato se esiste
     * almeno un altro mezzo; NULL se nessuna tappa ha un mezzo.
     *
     * @return string
     */
    protected static function splitBsAllMeansSql(): string
    {
        $leg = self::splitBsLegTokenSql();

        return <<<SQL
CASE
                                    WHEN COUNT({$leg}) = 0 THEN NULL
                                    WHEN COUNT(CASE WHEN ({$leg}) <> 'W' THEN 1 END) = 0 THEN 'W'
                                    ELSE GROUP_CONCAT(
                                        DISTINCT CASE WHEN ({$leg}) <> 'W' THEN ({$leg}) END
                                        ORDER BY ({$leg}) SEPARATOR '+'
                                    )
                                END
SQL;
    }

    /**
     * CASE (senza alias) che mappa `all_means` (token concatenati) nella
     * macro-categoria. Precedenza, prima regola vera vince: car sharing/taxi ->
     * altro; navetta; (auto|passeggero) + TPL; auto conducente; passeggero;
     * TPL; bici; a piedi; resto -> altro (rete di sicurezza).
     *
     * @return string
     */
    protected static function splitBsCategoryCaseSql(): string
    {
        return <<<'SQL'
CASE
                    /* Car sharing / Taxi (da soli o combinati) */
                    WHEN all_means LIKE '%S%' THEN 'altro'
                    /* Navetta (con qualsiasi altro mezzo) */
                    WHEN all_means LIKE '%N%' THEN 'navetta'
                    /* Auto conducente/moto o passeggero + TPL */
                    WHEN (all_means LIKE '%A%' OR all_means LIKE '%P%') AND all_means LIKE '%T%'
                        THEN 'Auto, moto o Auto come passeggero o in carpooling + TPL'
                    WHEN all_means LIKE '%A%' THEN 'Auto come conducente o moto'
                    WHEN all_means LIKE '%P%' THEN 'Auto come passeggero o in carpooling'
                    WHEN all_means LIKE '%T%' THEN 'TPL'
                    WHEN all_means LIKE '%B%' THEN 'bici o monopattino'
                    WHEN all_means = 'W' THEN 'aPiedi'
                    /* Valore sconosciuto (token X): rete di sicurezza */
                    ELSE 'altro'
                END
SQL;
    }
}

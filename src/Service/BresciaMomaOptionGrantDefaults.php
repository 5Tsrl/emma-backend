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
 * @author    Claude Code
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

namespace App\Service;

use Cake\Database\Connection;

/**
 * Brescia #369: default `questions_surveys.moma_can_edit_options` grants on the
 * Brescia employee-questionnaire TEMPLATE (survey 452).
 *
 * Extracted from the MigrationBS data migration so the logic is unit-testable
 * against the fixture DB. All table names are UNQUALIFIED: the SQL runs on
 * whatever schema the given connection points at (site config / HTTP_HOST).
 *
 * Safety rules: the template is matched on id AND exact name (otherwise nothing
 * is touched); only rows of survey 452 are ever written; grants are limited to
 * single/multiple/array questions; idempotent.
 */
class BresciaMomaOptionGrantDefaults
{
    public const TEMPLATE_SURVEY_ID = 452;

    public const TEMPLATE_SURVEY_NAME = 'Questionario Imprese e PP.AA. del Comune di Brescia - nuova versione';

    /**
     * Questions where moma may hide options: the docx Questionario_dipendenti.docx matrix (26)
     * plus 582/584 ("quando piove"/"quando c'e' il sole"): Brescia Mobilita asked to keep them
     * selectable because not every company has e.g. "Navetta aziendale"; hiding it is safe
     * because only option index 5 ("Autovettura o motociclo") activates 583/585.
     * NOTE: nothing is ever revoked here (an earlier version revoked 582/584 by mistake).
     *
     * @var array<int>
     */
    public const GRANT_QUESTION_IDS = [
        563, 564, 565, 566, 567, 568, 569, 570, 571, 572, 573, 574, 575,
        578, 579, 582, 583, 584, 585, 587, 589, 590, 591, 592, 597, 598, 599, 618,
    ];

    /**
     * Apply the defaults.
     *
     * Counts are rows actually CHANGED (MySQL rowCount semantics), so a second
     * run reports 0.
     *
     * @param \Cake\Database\Connection $connection Connection of the target DB.
     * @return array{granted: int, skipped: bool}
     */
    public function apply(Connection $connection): array
    {
        $found = $connection->execute(
            'SELECT id FROM surveys WHERE id = :id AND name = :name LIMIT 1',
            ['id' => self::TEMPLATE_SURVEY_ID, 'name' => self::TEMPLATE_SURVEY_NAME],
            ['id' => 'integer', 'name' => 'string']
        )->fetch('assoc');

        if (!$found) {
            return ['granted' => 0, 'skipped' => true];
        }

        // Ids come from int constants, so interpolation is safe.
        $grantIds = implode(',', array_map('intval', self::GRANT_QUESTION_IDS));

        $granted = $connection->execute(
            'UPDATE questions_surveys qs INNER JOIN questions q ON qs.question_id = q.id '
            . 'SET qs.moma_can_edit_options = 1 '
            . 'WHERE qs.survey_id = :sid AND qs.question_id IN (' . $grantIds . ') '
            . "AND q.type IN ('single', 'multiple', 'array')",
            ['sid' => self::TEMPLATE_SURVEY_ID],
            ['sid' => 'integer']
        )->rowCount();

        return ['granted' => $granted, 'skipped' => false];
    }
}

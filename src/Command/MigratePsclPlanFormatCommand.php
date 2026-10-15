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

namespace App\Command;

use App\Model\Table\PsclTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

/**
 * MigratePsclPlanFormat command.
 *
 * Rewrites the legacy nested `pscl.plan` JSON (`plan[legacyPillarId] =
 * [measure entries...]`, one bucket per historic pillar id 1-10) into the new
 * flat format: `plan[1]` becomes a SINGLE array of `{value, pillar_id,
 * measure_id, measure_slug, weight, year}` entries, where `pillar_id` is now
 * the REAL `measures.id` of the parent axis (0 for an axis entry itself),
 * instead of the legacy slug-parsed pillar id. See CLAUDE.md /
 * docs/dev-notes/survey-measure-reorg.md ("Flat PSCL plan format").
 *
 * Idempotent: a row already in the new format carries `plan[0]['plan_format']
 * >= 2` and is skipped on a re-run. `plan[0]` (the service/metadata bucket:
 * protocolNumber, protocolDate, data_adozione, protocol_override, ...) is
 * preserved untouched except for the added marker.
 *
 * Unmarked-flat guard: a plan produced by the normal create/edit flow (never
 * stamped with `plan_format`, see `Pscl::cleanPlan()`) can be structurally
 * identical to a legacy plan with only its first bucket compiled — both are
 * `{0:{...},1:[...]}`. Before flattening, `migrateRow()` calls
 * `PsclTable::isAlreadyFlatPlan()`: if the plan has no bucket beyond 0/1 AND
 * at least one `plan[1]` entry already has `pillar_id === 0` (an axis
 * pseudo-measure, which only the flat format ever produces), the row is
 * already flat — it is stamped with the marker and counted as
 * `rows_skipped_flat_unmarked`, WITHOUT running `flattenLegacyPlan()` against
 * it. Skipping this guard silently corrupts the row: every entry's
 * `pillar_id` gets overwritten with the id of whichever axis happens to sit
 * at bucket key 1 (see the real `pscl` id=9 case in
 * `docs/dev-notes/survey-measure-reorg.md`).
 *
 * `year` is copied from the pscl row's own `year` column ONCE, at migration
 * time, per an explicit product decision: no ongoing reconciliation is
 * performed afterwards (an admin may later edit a pillar's `year` in the plan
 * UI and it will not be re-synced against `pscl.year`).
 *
 * Scope: this command only touches the `pscl` table. The legacy
 * `offices.PSCL` JSON column (used for the `year = 'TUTTI'` case, see
 * `PsclController::edit()`) is a separate, older storage channel and is
 * intentionally NOT migrated here — a known follow-up, see the dev-notes.
 *
 * Run-order (deliberate "big bang", see dev-notes): this is the FIRST plan
 * format the code assumes, unlike the pillars/sections reorg above (where the
 * data command ran before the schema migration while old columns still
 * existed). Here it's the reverse: the backend code already reads/writes the
 * flat format the moment it's deployed. Run this command IMMEDIATELY after
 * deploying, before any user creates/edits a PSCL plan — an old-format row
 * saved through the (already-flat-only) `PsclController::edit()` between
 * deploy and this command's run would collide with the "legacy pillar id 1"
 * bucket key ambiguity (both a legacy bucket-per-pillar plan and an
 * un-marked new-format plan look identical: `{0:{...},1:[...]}`).
 * `version_tag='template'` rows are exempt (always flat by construction,
 * skipped unconditionally) since templates did not exist pre-migration.
 *
 * Options:
 *   --dry-run   Roll back the transaction at the end (no persistent change).
 *   --verbose   Print a per-row trace.
 */
class MigratePsclPlanFormatCommand extends Command
{
    /**
     * Marker value written to plan[0]['plan_format'] once a row has been
     * migrated to the flat format. Bump if the flat format ever changes shape
     * again, so re-running this command (or a future migration) can tell rows
     * apart.
     */
    protected const CURRENT_FORMAT_VERSION = 2;

    /**
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $verbose = false;

    /**
     * Aggregate counters for the summary.
     *
     * @var array<string,int>
     */
    protected $counters = [
        'rows_total' => 0,
        'rows_skipped_already_migrated' => 0,
        'rows_skipped_flat_unmarked' => 0,
        'rows_skipped_empty' => 0,
        'rows_migrated' => 0,
        'axis_entries_created' => 0,
        'measure_entries_migrated' => 0,
        'orphan_buckets_warnings' => 0,
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Rewrite pscl.plan from the legacy nested (bucket-per-pillar) format to the ' .
                'new flat format (plan[1] = single array of ' .
                '{value,pillar_id,measure_id,measure_slug,weight,year} entries, pillar_id = ' .
                'real axis measure_id). Idempotent + transactional.'
            )
            ->addOption('dry-run', [
                'help' => 'Run everything inside a transaction then roll back (no persistent change).',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('verbose', [
                'help' => 'Print a per-row trace.',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->dryRun = (bool)$args->getOption('dry-run');
        $this->verbose = (bool)$args->getOption('verbose');

        $Pscl = TableRegistry::getTableLocator()->get('Pscl');
        $Measures = TableRegistry::getTableLocator()->get('Measures');

        $io->out('<info>MigratePsclPlanFormat</info>' . ($this->dryRun ? ' (DRY-RUN)' : ''));

        // Legacy pillar id -> axis Measure (type=0, slug='pillar-<legacyId>'),
        // see the "Dual pillar_id model" note in the dev-notes.
        $axisByLegacyId = [];
        foreach ($Measures->find()->where(['type' => 0])->toArray() as $axis) {
            $legacyId = (int)str_replace('pillar-', '', (string)$axis->slug);
            $axisByLegacyId[$legacyId] = $axis;
        }

        // measure_id -> Measure, for weight/slug lookups while migrating child entries.
        $measuresById = [];
        foreach ($Measures->find()->toArray() as $measure) {
            $measuresById[$measure->id] = $measure;
        }

        $connection = $Pscl->getConnection();

        try {
            $connection->begin();

            foreach ($Pscl->find()->all() as $pscl) {
                $this->counters['rows_total']++;
                $this->migrateRow($io, $pscl, $Pscl, $axisByLegacyId, $measuresById);
            }

            if ($this->dryRun) {
                $connection->rollback();
                $io->out('<warning>DRY-RUN: transaction rolled back, no changes persisted.</warning>');
            } else {
                $connection->commit();
                $io->out('<success>Committed.</success>');
            }
        } catch (\Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollback();
            }
            $io->err('<error>Aborted: ' . $e->getMessage() . '</error>');
            $io->err($e->getTraceAsString());

            return self::CODE_ERROR;
        }

        $this->printSummary($io);

        return self::CODE_SUCCESS;
    }

    /**
     * Migrate a single pscl row's plan in place (mutates and saves $pscl).
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param \App\Model\Entity\Pscl $pscl The row to migrate.
     * @param \App\Model\Table\PsclTable $Pscl The Pscl table (for save()).
     * @param array<int,\App\Model\Entity\Measure> $axisByLegacyId Legacy pillar id => axis Measure.
     * @param array<int,\App\Model\Entity\Measure> $measuresById measure_id => Measure.
     * @return void
     * @throws \Exception When the row cannot be saved.
     */
    protected function migrateRow(
        ConsoleIo $io,
        $pscl,
        $Pscl,
        array $axisByLegacyId,
        array $measuresById
    ): void {
        $plan = $pscl->plan;
        if (!is_array($plan) || empty($plan)) {
            $this->counters['rows_skipped_empty']++;

            return;
        }

        // Template rows (version_tag='template', see PsclTable::findTemplates())
        // are a brand-new concept introduced alongside the flat format itself:
        // they are ALWAYS already flat by construction (built by
        // PsclController::saveTemplate()), never legacy nested data. Treating
        // one as a legacy bucket-per-pillar plan would misinterpret its own
        // plan[1] flat array as "legacy pillar id 1"'s children.
        if ($pscl->version_tag === 'template') {
            $this->counters['rows_skipped_already_migrated']++;

            return;
        }

        $meta = is_array($plan[0] ?? null) ? $plan[0] : [];
        if ((int)($meta['plan_format'] ?? 1) >= self::CURRENT_FORMAT_VERSION) {
            $this->counters['rows_skipped_already_migrated']++;

            return;
        }

        // Unmarked-flat guard (see class docblock): a plan saved through the
        // normal create/edit flow never carries plan_format, so it can be
        // structurally identical to a legacy plan with only bucket 1
        // compiled. Detect it BEFORE flattenLegacyPlan() runs — that function
        // has no way to tell the two apart and would silently overwrite every
        // entry's pillar_id with whichever axis sits at bucket key 1.
        if (PsclTable::isAlreadyFlatPlan($plan)) {
            $meta['plan_format'] = self::CURRENT_FORMAT_VERSION;
            $pscl->plan = [0 => $meta, 1 => is_array($plan[1] ?? null) ? $plan[1] : []];
            $pscl->setDirty('plan', true);

            if (!$Pscl->save($pscl)) {
                throw new \Exception(
                    "Impossibile salvare il pscl #{$pscl->id}: " . json_encode($pscl->getErrors())
                );
            }

            $this->counters['rows_skipped_flat_unmarked']++;

            if ($this->verbose) {
                $io->out(sprintf(
                    '  pscl #%d (company=%s, office=%s, year=%s): piano già piatto ma senza marker, ' .
                    'marcato plan_format=%d SENZA riscrittura.',
                    $pscl->id,
                    $pscl->company_id,
                    $pscl->office_id,
                    $pscl->year,
                    self::CURRENT_FORMAT_VERSION
                ));
            }

            return;
        }

        // Shared conversion (also used by MigrateOfficePsclToPlanCommand for
        // the legacy offices.PSCL column) — see PsclTable::flattenLegacyPlan().
        [$newMeta, $entries] = PsclTable::flattenLegacyPlan(
            $plan,
            $axisByLegacyId,
            $measuresById,
            $pscl->year,
            function ($legacyPillarId) use ($io, $pscl) {
                $this->counters['orphan_buckets_warnings']++;
                $io->warning(
                    "  pscl #{$pscl->id}: nessuna misura-asse trovata per il pillar storico " .
                    "$legacyPillarId, le sue misure restano con pillar_id=0."
                );
            }
        );

        $newMeta['plan_format'] = self::CURRENT_FORMAT_VERSION;
        $pscl->plan = [0 => $newMeta, 1 => $entries];
        // Dedup safety net (should be a no-op on well-formed source data).
        $pscl->cleanPlan();
        $pscl->setDirty('plan', true);

        if (!$Pscl->save($pscl)) {
            throw new \Exception(
                "Impossibile salvare il pscl #{$pscl->id}: " . json_encode($pscl->getErrors())
            );
        }

        $this->counters['rows_migrated']++;
        $axisEntryCount = 0;
        foreach ($entries as $entry) {
            if (($entry['pillar_id'] ?? null) === 0) {
                $axisEntryCount++;
            }
        }
        $this->counters['axis_entries_created'] += $axisEntryCount;
        $this->counters['measure_entries_migrated'] += count($entries) - $axisEntryCount;

        if ($this->verbose) {
            $io->out(sprintf(
                '  pscl #%d (company=%s, office=%s, year=%s): %d voci nel nuovo piano piatto.',
                $pscl->id,
                $pscl->company_id,
                $pscl->office_id,
                $pscl->year,
                count($entries)
            ));
        }
    }

    /**
     * Print the final summary.
     *
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function printSummary(ConsoleIo $io): void
    {
        $io->out('');
        $io->out('<info>Summary' . ($this->dryRun ? ' (DRY-RUN, rolled back)' : '') . '</info>');
        $io->out(sprintf('  Righe pscl totali:                %d', $this->counters['rows_total']));
        $io->out(sprintf('  Già nel nuovo formato (saltate):   %d', $this->counters['rows_skipped_already_migrated']));
        $io->out(sprintf(
            '  Piano già piatto senza marker (saltate): %d',
            $this->counters['rows_skipped_flat_unmarked']
        ));
        $io->out(sprintf('  Piano vuoto (saltate):             %d', $this->counters['rows_skipped_empty']));
        $io->out(sprintf('  Righe migrate:                     %d', $this->counters['rows_migrated']));
        $io->out(sprintf('  Voci-asse (pillar) create:         %d', $this->counters['axis_entries_created']));
        $io->out(sprintf('  Voci-misura migrate:               %d', $this->counters['measure_entries_migrated']));
        $io->out(sprintf('  Bucket orfani (asse non trovato):  %d', $this->counters['orphan_buckets_warnings']));
    }
}

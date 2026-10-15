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
 * MigrateOfficePsclToPlan command.
 *
 * Moves the legacy `offices.PSCL` JSON column (used for the `year = 'TUTTI'`
 * — "all years" — case, see `PsclController::edit()`) into a proper `pscl`
 * table row (`company_id`, `office_id`, `survey_id = NULL`, `year IS NULL`),
 * converted to the flat plan format via the SAME shared conversion as
 * `MigratePsclPlanFormatCommand` (`PsclTable::flattenLegacyPlan()`) — the
 * legacy `offices.PSCL` content is shaped exactly like a legacy `pscl.plan`
 * (bucket-per-historic-pillar-id), just stored in a different column.
 * See CLAUDE.md / docs/dev-notes/survey-measure-reorg.md ("office.PSCL ->
 * pscl.plan migration").
 *
 * Idempotent: an office is skipped if its `PSCL` column is null/empty, OR if
 * a `pscl` row for that office already exists with `year IS NULL` AND
 * `plan[0]['plan_format'] >= 2` (already migrated — find-or-create by
 * `(company_id, office_id, year IS NULL)`, so re-running updates in place
 * rather than duplicating).
 *
 * Run this BEFORE the follow-up Phinx migration that drops `offices.PSCL` —
 * same strict ordering rationale as `MigratePsclPlanFormatCommand` (the data
 * command needs the old column to still exist). Unlike that command, this one
 * does NOT need to run "immediately after deploy" — `offices.PSCL` is only
 * read as a fallback by legacy code paths already being replaced in this same
 * change, so there is no live write path left that could reintroduce data
 * into the old column once this backend version is deployed.
 *
 * Options:
 *   --dry-run   Roll back the transaction at the end (no persistent change).
 *   --verbose   Print a per-row trace.
 */
class MigrateOfficePsclToPlanCommand extends Command
{
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
     * @var array<string,int>
     */
    protected $counters = [
        'offices_total' => 0,
        'offices_skipped_empty' => 0,
        'offices_skipped_already_migrated' => 0,
        'offices_migrated' => 0,
        'pscl_rows_created' => 0,
        'pscl_rows_updated' => 0,
        'orphan_buckets_warnings' => 0,
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(
                'Move offices.PSCL (legacy year=TUTTI plan) into a pscl row ' .
                '(office_id, year IS NULL), converted to the flat plan format. ' .
                'Idempotent + transactional. Run BEFORE the migration dropping offices.PSCL.'
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

        $Offices = TableRegistry::getTableLocator()->get('Offices');
        $Pscl = TableRegistry::getTableLocator()->get('Pscl');
        $Measures = TableRegistry::getTableLocator()->get('Measures');

        $io->out('<info>MigrateOfficePsclToPlan</info>' . ($this->dryRun ? ' (DRY-RUN)' : ''));

        $axisByLegacyId = [];
        foreach ($Measures->find()->where(['type' => 0])->toArray() as $axis) {
            $legacyId = (int)str_replace('pillar-', '', (string)$axis->slug);
            $axisByLegacyId[$legacyId] = $axis;
        }

        $measuresById = [];
        foreach ($Measures->find()->toArray() as $measure) {
            $measuresById[$measure->id] = $measure;
        }

        $connection = $Offices->getConnection();

        try {
            $connection->begin();

            foreach ($Offices->find()->where(['PSCL IS NOT' => null])->all() as $office) {
                $this->counters['offices_total']++;
                $this->migrateOffice($io, $office, $Offices, $Pscl, $axisByLegacyId, $measuresById);
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
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @param \App\Model\Entity\Office $office Office with a non-null PSCL column.
     * @param \App\Model\Table\OfficesTable $Offices Offices table.
     * @param \App\Model\Table\PsclTable $Pscl Pscl table.
     * @param array<int,\App\Model\Entity\Measure> $axisByLegacyId Legacy pillar id => axis Measure.
     * @param array<int,\App\Model\Entity\Measure> $measuresById measure_id => Measure.
     * @return void
     * @throws \Exception When the row cannot be saved.
     */
    protected function migrateOffice(
        ConsoleIo $io,
        $office,
        $Offices,
        $Pscl,
        array $axisByLegacyId,
        array $measuresById
    ): void {
        $legacyPlan = $office->PSCL;
        if (!is_array($legacyPlan) || empty($legacyPlan)) {
            $this->counters['offices_skipped_empty']++;

            return;
        }

        $pscl = $Pscl->findAllYearsForOffice($office->company_id, $office->id);
        $isNew = $pscl === null;
        if ($isNew) {
            $pscl = $Pscl->newEmptyEntity();
            $pscl->company_id = $office->company_id;
            $pscl->office_id = $office->id;
            $pscl->survey_id = null;
            $pscl->year = null;
        }

        $existingMeta = is_array($pscl->plan[0] ?? null) ? $pscl->plan[0] : [];
        if (!$isNew && (int)($existingMeta['plan_format'] ?? 1) >= self::CURRENT_FORMAT_VERSION) {
            $this->counters['offices_skipped_already_migrated']++;

            return;
        }

        [$meta, $entries] = PsclTable::flattenLegacyPlan(
            $legacyPlan,
            $axisByLegacyId,
            $measuresById,
            null, // year is always NULL here: this IS the "all years" bucket.
            function ($legacyPillarId) use ($io, $office) {
                $this->counters['orphan_buckets_warnings']++;
                $io->warning(
                    "  office #{$office->id}: nessuna misura-asse trovata per il pillar storico " .
                    "$legacyPillarId, le sue misure restano con pillar_id=0."
                );
            }
        );

        $meta['plan_format'] = self::CURRENT_FORMAT_VERSION;
        $pscl->plan = [0 => $meta, 1 => $entries];
        $pscl->cleanPlan();
        $pscl->setDirty('plan', true);

        if (!$Pscl->save($pscl)) {
            throw new \Exception(
                "Impossibile salvare il pscl per l'office #{$office->id}: " . json_encode($pscl->getErrors())
            );
        }

        $this->counters['offices_migrated']++;
        $this->counters[$isNew ? 'pscl_rows_created' : 'pscl_rows_updated']++;

        if ($this->verbose) {
            $io->out(sprintf(
                '  office #%d (company=%s) -> pscl #%d: %d voci nel nuovo piano piatto.',
                $office->id,
                $office->company_id,
                $pscl->id,
                count($entries)
            ));
        }
    }

    /**
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return void
     */
    protected function printSummary(ConsoleIo $io): void
    {
        $io->out('');
        $io->out('<info>Summary' . ($this->dryRun ? ' (DRY-RUN, rolled back)' : '') . '</info>');
        $io->out(sprintf('  Offices con PSCL non nullo:        %d', $this->counters['offices_total']));
        $io->out(sprintf('  PSCL vuoto (saltate):              %d', $this->counters['offices_skipped_empty']));
        $io->out(sprintf(
            '  Già nel nuovo formato (saltate):   %d',
            $this->counters['offices_skipped_already_migrated']
        ));
        $io->out(sprintf('  Offices migrati:                   %d', $this->counters['offices_migrated']));
        $io->out(sprintf('  Righe pscl create:                 %d', $this->counters['pscl_rows_created']));
        $io->out(sprintf('  Righe pscl aggiornate:             %d', $this->counters['pscl_rows_updated']));
        $io->out(sprintf('  Bucket orfani (asse non trovato):  %d', $this->counters['orphan_buckets_warnings']));
    }
}

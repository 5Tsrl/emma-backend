<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\ORM\Entity;

/**
 * Pscl Entity
 *
 * @property int $id
 * @property string|null $version_tag
 * @property int|null $company_id
 * @property int|null $office_id
 * @property int|null $survey_id
 * @property array|null $plan
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\Office $office
 * @property \App\Model\Entity\Survey $survey
 */
class Pscl extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'version_tag' => true,
        'company_id' => true,
        'office_id' => true,
        'survey_id' => true,
        'plan' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'office' => true,
        'survey' => true,
    ];

    public function checkComplete($year, $company_id, $office_id): bool
    {
        $folder_path = $this->folderPath($year, $company_id, $office_id);
        $dir = new Folder($folder_path);
        $files = $dir->find('*.*');

        return !empty($files);
    }

    public function folderName($year, $company_id, $office_id)
    {
        if ($year == 'TUTTI') {
            return $company_id . '/' . $office_id;
        } else {
            if ($office_id == 'null') {
                return $year . '/' . $company_id;
            } else {
                return $year . '/' . $company_id . '/' . $office_id;
            }
        }
    }

    public function folderPath($year, $company_id, $office_id)
    {
        $folderName = $this->folderName($year, $company_id, $office_id);

        return WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . $folderName;
    }

    /**
     * Ripulisce il piano da voci vuote o duplicate.
     *
     * Formato del piano (dal 2026, "flat plan format" — vedi CLAUDE.md /
     * docs/dev-notes/survey-measure-reorg.md, sezione "Flat PSCL plan format"):
     *   plan[0] = bucket di servizio (metadati: protocolNumber, protocolDate,
     *             data_adozione, protocol_override, ...) — MAI toccato qui.
     *   plan[1] = array piatto di voci `{value, pillar_id, measure_id,
     *             measure_slug, weight, year}`. Una voce con `pillar_id == 0`
     *             è essa stessa un asse/pillar (misura type=0); le voci con
     *             `pillar_id > 0` sono misure figlie e `pillar_id` è il
     *             `measure_id` reale dell'asse padre (non più il vecchio id
     *             numerico "storico" 1-10 derivato dallo slug).
     * A differenza del vecchio formato annidato (un bucket per pillar), qui
     * non esiste più ambiguità: `pillar_id` è un campo della singola voce, non
     * più una chiave di bucket. Quindi il dedup è una semplice "ultima voce
     * vince" per `measure_id` (così le voci aggiunte per la config corrente
     * delle misure sovrascrivono quelle obsolete), mantenendo la posizione
     * della prima occorrenza.
     */
    public function cleanPlan()
    {
        $plan = $this->plan;
        if (!is_array($plan) || empty($plan)) {
            return;
        }

        // Legacy nested (bucket-per-pillar) format: plan[2]..plan[10] hold
        // additional per-pillar entries alongside plan[1] (pre-
        // MigratePsclPlanFormatCommand shape). Blindly assuming plan[1] is the
        // complete entry set here would silently drop buckets 2-10 the moment
        // this runs (e.g. from a supposedly read-only XLSX export). We can't
        // rely on plan[0]['plan_format'] alone to detect this: that marker is
        // only ever written by the migrate commands, never by the normal
        // create/edit flow, so an un-migrated legacy row and a brand-new flat
        // row can both lack it. The reliable signal is structural: a flat plan
        // never has bucket keys beyond 0 and 1. No-op (rather than truncate)
        // when extra buckets are found — run MigratePsclPlanFormatCommand
        // first to flatten this row.
        foreach (array_keys($plan) as $bucketKey) {
            if ((int)$bucketKey > 1) {
                return;
            }
        }

        $meta = $plan[0] ?? [];
        $entries = is_array($plan[1] ?? null) ? $plan[1] : [];

        $byMeasureId = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['measure_id'])) {
                continue;
            }
            $byMeasureId[$entry['measure_id']] = $entry;
        }

        $this->plan = [0 => $meta, 1 => array_values($byMeasureId)];
    }
}

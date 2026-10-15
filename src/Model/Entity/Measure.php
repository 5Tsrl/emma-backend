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

use App\Indicator\emission0;
use Cake\Core\Configure;
use Cake\I18n\I18n;
use Cake\ORM\Entity;

/**
 * Measure Entity
 *
 * @property int $id
 * @property string|null $slug
 * @property string|null $name
 * @property string|null $description
 * @property string|null $img
 * @property string|null $target
 * @property int|null $type
 * @property int|null $weight
 *
 * Note: the former `pillar_id` column is dropped. An axis/pillar is now a
 * Measure of type=0; child measures sort right after it by the `weight` column.
 */
class Measure extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        'slug' => true,
        'name' => true,
        'description' => true,
        'img' => true,
        'target' => true,
        'type' => true,
        'weight' => true,
        'service_url' => true,
        'inputs' => true,
        'indicator' => true,
        'labels' => true,
    ];
    private $pscl_measures = [];

    public function __construct($id = [], $table = null, $ds = null)
    {
        parent::__construct($id, $table, $ds);
        // Carica le misure PSCL dalla cartella, eventualmente "pinnate" all'anno.
        // The optional Configure key 'PsclMeasuresYear' (set by the controller for a
        // requested year) lets past years (< the per-brand cutover, opt-in only, see
        // resolveMeasuresPath) load a historical snapshot.
        // If the site has no measures.php (e.g. a brand without PSCL impact config),
        // degrade to an empty map instead of fataling: listing DB measures (and the
        // whole PSCL page) must keep working. Impact calc falls back to emission0.
        $path = self::resolveMeasuresPath(Configure::read('PsclMeasuresYear'));
        if (is_file($path)) {
            $m = require $path;
            $this->pscl_measures = $m['Measures'] ?? [];
        } else {
            $this->pscl_measures = [];
        }
    }

    /**
     * Resolve the measures.php path, optionally pinned to a historical year.
     *
     * Historicization is OPT-IN per brand, read from Configure key
     * 'PsclCutoverYear' (env PSCL_CUTOVER_YEAR). 0/unset disables historicization
     * entirely for that brand: it always serves the default
     * WWW_ROOT/{sitedir}/measures.php regardless of the requested year. Only the
     * 5t brand sets a real cutover year (2027) in its site .env; for that brand,
     * years >= the cutover (or no year) use the new default flat type=0/weight
     * structure, while past years (< cutover) prefer
     * WWW_ROOT/{sitedir}/{year}/measures.php if it exists (typically a symlink to
     * the old pillar config), else fall back to default. Forward-compatible hook:
     * the year folders may not exist yet.
     *
     * @param int|string|null $year The requested PSCL year, or null for current.
     * @return string Absolute path to the measures.php file to load.
     */
    public static function resolveMeasuresPath($year = null): string
    {
        $sitedir = Configure::read('sitedir');
        $default = WWW_ROOT . $sitedir . DS . 'measures.php';

        if ($year === null || $year === '' || $year === 'TUTTI') {
            return $default;
        }

        $cutover = (int)Configure::read('PsclCutoverYear');
        $yearInt = (int)$year;
        // Historicization is opt-in: a brand with no cutover configured (0) never
        // pins a historical snapshot, regardless of the requested year. Only past
        // years (< cutover) may pin a historical snapshot when opted in.
        if ($cutover > 0 && $yearInt > 0 && $yearInt < $cutover) {
            $pinned = WWW_ROOT . $sitedir . DS . $yearInt . DS . 'measures.php';
            if (is_file($pinned)) {
                return $pinned;
            }
        }

        return $default;
    }

    /**
     * Resolve the locale-specific labels-only measures.php path, parallel to
     * resolveMeasuresPath()'s year-historicization mechanism but keyed on locale
     * instead of year.
     *
     * This file is OPTIONAL and, unlike the base measures.php, is expected to
     * contain only a ['Measures' => [id => ['labels' => [...]]]] structure (no
     * 'indicator' closures) — it exists purely to translate PSCL indicator field
     * labels for non-default locales. There is no locale path for the default
     * locale itself: the base WWW_ROOT/{sitedir}/measures.php file already IS the
     * default-locale (Italian) content.
     *
     * @param string $locale The locale to resolve a labels file for (e.g. 'fr').
     * @return string|null Absolute path to the locale's measures.php file, or null
     *   if the given locale is the default locale (no locale-specific file applies).
     */
    public static function resolveLocaleMeasuresPath(string $locale): ?string
    {
        $defaultLocale = Configure::read('App.defaultLocale') ?? 'it';
        if ($locale === $defaultLocale) {
            return null;
        }

        $sitedir = Configure::read('sitedir');

        return WWW_ROOT . $sitedir . DS . $locale . DS . 'measures.php';
    }

    // PSCL

    public function calculateImpactPscl($id, $series)
    {
        $measure_indicator = $this->pscl_measures[$id]['indicator'];
        try {
            if (!isset($series['days'])) {
                $series['days'] = 0;
            }
            if (!isset($series['users'])) {
                $series['users'] = 0;
            }
            if (!isset($series['distance'])) {
                $series['distance'] = 0;
            }
            $r = $measure_indicator($series);
        } catch (\Exception $e) {
            $e0 = new emission0();
            $r = $e0->getOutput();
        }

        return $r;
    }

    public function getPsclLabels($id)
    {
        // Cache key MUST vary by locale: without this, a French request could
        // populate the cache and a subsequent Italian request (or vice versa)
        // would silently be served the wrong language's labels.
        $locale = I18n::getLocale();
        $cacheKey = 'pscl_labels_' . $id . '_' . $locale;
        $r = \Cake\Cache\Cache::read($cacheKey);

        if (empty($r)) {
            $r = null;
            $localePath = self::resolveLocaleMeasuresPath($locale);
            if ($localePath !== null && is_file($localePath)) {
                $localeMeasures = require $localePath;
                $r = $localeMeasures['Measures'][$id]['labels'] ?? null;
            }
            // Fall back to the base (default-locale) file's labels when there is
            // no locale-specific file, or it has no entry for this measure id.
            if ($r === null) {
                $r = $this->pscl_measures[$id]['labels'] ?? null;
            }
            \Cake\Cache\Cache::write($cacheKey, $r);
        }

        return $r;
    }
}

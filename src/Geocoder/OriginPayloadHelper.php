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
namespace App\Geocoder;

/**
 * Shared helper for the map question ("origine-spostamenti") answer payload.
 *
 * `extractOriginRowData()` centralizes the logic previously duplicated between
 * `AnswersController::update()` and `OriginsController::importFromAnswersForSurvey()`
 * for pulling origin fields + a computed geohash out of a decoded map answer.
 *
 * `stripOriginPii()` removes `origin.address`/`origin.lat`/`origin.lon` from a
 * decoded map answer before it is persisted to `answers.answer` — those fields
 * only ever need to survive long enough to build the `Origins` row (which
 * itself already discards `address`/`lat`/`lon` on save, see
 * `OriginsTable::beforeSave()`). `origin.district`/`postal_code`/`city`/
 * `province` and every other payload key (`destination`, `modes`, `geojson`)
 * are left untouched. When the stripped coordinates were usable, a coarse
 * `origin.geohash` is added to the stored answer as well (derived the same
 * way, and at the same precision, as the `Origins.geohash` column), so the
 * approximate location survives in the answer payload itself even though the
 * precise coordinates do not.
 */
class OriginPayloadHelper
{
    /**
     * Sentinel values meaning "not geocoded" — never encode these into a geohash.
     */
    private const UNGEOCODED_SENTINELS = [0, -1, '0', '-1', 0.0, -1.0];

    /**
     * Whether a (lat, lon) pair is usable for geohash encoding — both present,
     * non-empty, and not one of the "not geocoded" sentinel values.
     *
     * @param mixed $lat Latitude, in whatever type the decoded payload holds.
     * @param mixed $lon Longitude, in whatever type the decoded payload holds.
     * @return bool
     */
    private static function hasUsableCoords($lat, $lon): bool
    {
        return $lat !== null && $lon !== null && $lat !== '' && $lon !== ''
            && !in_array($lat, self::UNGEOCODED_SENTINELS, true)
            && !in_array($lon, self::UNGEOCODED_SENTINELS, true);
    }

    /**
     * Extract Origins-row fields (postal_code/province/city/district + geohash)
     * from a decoded map-answer `origin` sub-object.
     *
     * Accepts either a stdClass (from `json_decode($json)`) or an array (from
     * `json_decode($json, true)`) — both current call sites decode differently.
     * Does not include `address`: it is never persisted on an Origins row, see
     * `OriginsTable::beforeSave()`.
     *
     * @param object|array|null $decodedOrigin The decoded `origin` sub-object.
     * @return array<string, mixed> Empty when the input has no usable lat/lon.
     */
    public static function extractOriginRowData($decodedOrigin): array
    {
        if (empty($decodedOrigin)) {
            return [];
        }

        $o = is_array($decodedOrigin) ? (object)$decodedOrigin : $decodedOrigin;

        $row = [
            'postal_code' => $o->postal_code ?? null,
            'city' => $o->city ?? null,
            'province' => $o->province ?? null,
            'district' => $o->district ?? null,
        ];

        $lat = $o->lat ?? null;
        $lon = $o->lon ?? null;

        if (self::hasUsableCoords($lat, $lon)) {
            $row['geohash'] = GeohashHelper::encode((float)$lat, (float)$lon);
        }

        return $row;
    }

    /**
     * Return a copy of a decoded map answer with `origin.address`/`lat`/`lon`
     * removed. When the removed coordinates were usable and no `geohash` is
     * already present, a coarse `origin.geohash` is added in their place
     * (same precision as `Origins.geohash`, since both go through
     * `GeohashHelper::encode()`'s default precision). Idempotent — safe to
     * call on an already-stripped payload (a payload with no lat/lon left but
     * an existing geohash is never re-derived/overwritten), and on a payload
     * with no `origin` key at all.
     *
     * @param object|array $decodedAnswer The fully decoded answer payload.
     * @return object|array Same shape (object in, object out; array in, array out).
     */
    public static function stripOriginPii($decodedAnswer)
    {
        if (empty($decodedAnswer)) {
            return $decodedAnswer;
        }

        $isObject = is_object($decodedAnswer);
        $answer = $isObject ? (array)$decodedAnswer : $decodedAnswer;

        if (isset($answer['origin'])) {
            $originIsObject = is_object($answer['origin']);
            $origin = $originIsObject ? (array)$answer['origin'] : $answer['origin'];

            $lat = $origin['lat'] ?? null;
            $lon = $origin['lon'] ?? null;
            if (empty($origin['geohash']) && self::hasUsableCoords($lat, $lon)) {
                $origin['geohash'] = GeohashHelper::encode((float)$lat, (float)$lon);
            }

            unset($origin['address'], $origin['lat'], $origin['lon']);

            $answer['origin'] = $originIsObject ? (object)$origin : $origin;
        }

        return $isObject ? (object)$answer : $answer;
    }
}

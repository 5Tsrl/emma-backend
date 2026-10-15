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

namespace App\Validation;

/**
 * Validatori per identificativi fiscali italiani (metodi statici puri).
 *
 * ⚠️ AMBITO D'USO — leggere prima di riusare altrove.
 * Questa classe è nata per il **modulo di adesione** (form pubblico anonimo che crea
 * utenti e aziende reali) e applica le regole VERE, incluso il carattere di controllo.
 * Il resto della piattaforma valida il codice fiscale con `^[A-Z0-9]{16}$`
 * (UsersTable::isValidCf, FixUserCfCommand, src/extra/Abbonamenti sul frontend), quindi
 * accetta anche `AAAAAAAAAAAAAAAA`. Quella regola NON va irrigidita "di passaggio":
 * l'esistenza di `FixUserCfCommand` dimostra che in produzione ci sono già CF non validi
 * e una regola stretta romperebbe login/import esistenti. Estendere questi validatori al
 * resto della piattaforma richiede prima una bonifica dei dati: è una decisione separata.
 *
 * Nessun metodo lancia eccezioni: input non stringa/vuoto restituisce sempre false, così
 * si possono usare direttamente come `rule` di un Validator Cake.
 */
class ItalianFiscalValidator
{
    /**
     * Lettere ammesse per il mese di nascita nel codice fiscale (gennaio..dicembre).
     *
     * @var string
     */
    private const MONTH_LETTERS = 'ABCDEHLMPRST';

    /**
     * Omocodia: quando due persone generano lo stesso CF, le cifre vengono sostituite
     * da lettere secondo questa mappa (0→L, 1→M, ... 9→V). Va invertita prima di
     * validare gli intervalli numerici (anno/giorno/codice comune).
     *
     * @var array<string, string>
     */
    private const OMOCODIA_MAP = [
        'L' => '0', 'M' => '1', 'N' => '2', 'P' => '3', 'Q' => '4',
        'R' => '5', 'S' => '6', 'T' => '7', 'U' => '8', 'V' => '9',
    ];

    /**
     * Tabella dei valori per i caratteri in posizione DISPARI (1-based) del CF.
     *
     * @var array<string, int>
     */
    private const CIN_ODD = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /**
     * Normalizza un valore in stringa maiuscola senza spazi, o null se inutilizzabile.
     *
     * @param mixed $value Valore grezzo.
     * @return string|null
     */
    private static function normalize($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $clean = strtoupper(preg_replace('/\s+/', '', (string)$value) ?? '');

        return $clean !== '' ? $clean : null;
    }

    /**
     * Codice fiscale di PERSONA FISICA: 16 caratteri, struttura completa e carattere
     * di controllo (CIN) verificato. Gestisce l'omocodia.
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidPersonalCf($value): bool
    {
        $cf = self::normalize($value);
        if ($cf === null || strlen($cf) !== 16) {
            return false;
        }

        // Struttura: 6 lettere, 2 anno, 1 mese, 2 giorno, 1 lettera comune, 3 comune, 1 CIN.
        // Le posizioni numeriche accettano anche le lettere di omocodia.
        $pattern = '/^[A-Z]{6}'
            . '[0-9LMNPQRSTUV]{2}'
            . '[' . self::MONTH_LETTERS . ']'
            . '[0-9LMNPQRSTUV]{2}'
            . '[A-Z]'
            . '[0-9LMNPQRSTUV]{3}'
            . '[A-Z]$/';
        if (!preg_match($pattern, $cf)) {
            return false;
        }

        // Il giorno va verificato dopo aver sciolto l'omocodia: 01-31 (uomo) o 41-71 (donna).
        $day = (int)self::undoOmocodia(substr($cf, 9, 2));
        $isValidDay = ($day >= 1 && $day <= 31) || ($day >= 41 && $day <= 71);
        if (!$isValidDay) {
            return false;
        }

        return self::computeCin($cf) === substr($cf, 15, 1);
    }

    /**
     * Codice fiscale di ENTE/società: 11 cifre con lo stesso checksum della partita IVA.
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidOrganizationCf($value): bool
    {
        return self::isValidVat($value);
    }

    /**
     * Codice fiscale valido sia per persona fisica (16) sia per ente (11).
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidAnyCf($value): bool
    {
        return self::isValidPersonalCf($value) || self::isValidOrganizationCf($value);
    }

    /**
     * Partita IVA italiana: 11 cifre con checksum (algoritmo di Luhn "all'italiana",
     * raddoppio delle posizioni pari 1-based).
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidVat($value): bool
    {
        $vat = self::normalize($value);
        if ($vat === null) {
            return false;
        }
        // Tollera il prefisso paese "IT" che spesso viene incollato dagli utenti.
        if (strpos($vat, 'IT') === 0 && strlen($vat) === 13) {
            $vat = substr($vat, 2);
        }
        if (!preg_match('/^[0-9]{11}$/', $vat)) {
            return false;
        }
        // 11 zeri supera il checksum ma non è una partita IVA reale.
        if ($vat === '00000000000') {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = (int)$vat[$i];
            // Posizioni pari in notazione 1-based => indici dispari 0-based.
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        $check = (10 - ($sum % 10)) % 10;

        return $check === (int)$vat[10];
    }

    /**
     * Codice ATECO: `NN`, `NN.N`, `NN.NN`, `NN.NN.N` o `NN.NN.NN`.
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidAteco($value): bool
    {
        $ateco = self::normalize($value);
        if ($ateco === null) {
            return false;
        }

        return (bool)preg_match('/^[0-9]{2}(\.[0-9]{1,2}){0,2}$/', $ateco);
    }

    /**
     * Numero di telefono italiano, tollerante su prefisso internazionale e separatori
     * (spazi, punti, trattini, parentesi, barre).
     *
     * @param mixed $value Valore da validare.
     * @return bool
     */
    public static function isValidItalianPhone($value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        $raw = (string)$value;
        // Solo separatori ammessi oltre a cifre e un eventuale + iniziale.
        if (!preg_match('/^\+?[0-9\s.\-()\/]+$/', $raw)) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        // Scarta il prefisso internazionale italiano se presente.
        if (strpos($digits, '0039') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '39') === 0 && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        $len = strlen($digits);

        return $len >= 6 && $len <= 15;
    }

    /**
     * Restituisce il carattere di controllo atteso per un prefisso di 15 caratteri.
     *
     * Utile per costruire codici fiscali coerenti in fixture e test (i valori inventati
     * a mano non superano il checksum: es. il prefisso `BNCGLI85B45F205` richiede `W`,
     * non `Y`). Non è un generatore di identità: serve solo a rendere validi i dati di
     * prova.
     *
     * @param mixed $first15 I primi 15 caratteri del codice fiscale.
     * @return string|null Il carattere atteso, o null se il prefisso non è utilizzabile.
     */
    public static function checkCharFor($first15): ?string
    {
        $prefix = self::normalize($first15);
        if ($prefix === null || strlen($prefix) !== 15 || !preg_match('/^[A-Z0-9]{15}$/', $prefix)) {
            return null;
        }

        return self::computeCin($prefix . 'X');
    }

    /**
     * Sostituisce le lettere di omocodia con le cifre corrispondenti.
     *
     * @param string $chunk Porzione numerica del CF.
     * @return string
     */
    private static function undoOmocodia(string $chunk): string
    {
        return strtr($chunk, self::OMOCODIA_MAP);
    }

    /**
     * Calcola il carattere di controllo (CIN) sui primi 15 caratteri del CF.
     *
     * Il CIN si calcola sui caratteri COSÌ COME SCRITTI (comprese le lettere di
     * omocodia), non sulla versione con le cifre ripristinate.
     *
     * @param string $cf Codice fiscale di 16 caratteri, già normalizzato.
     * @return string Il carattere atteso in sixteenth position.
     */
    private static function computeCin(string $cf): string
    {
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $char = $cf[$i];
            if (($i + 1) % 2 === 1) {
                // Posizione dispari (1-based): tabella dedicata.
                $sum += self::CIN_ODD[$char] ?? 0;
            } elseif ($char >= '0' && $char <= '9') {
                // Posizione pari: cifra = valore, lettera = posizione alfabetica.
                $sum += (int)$char;
            } else {
                $sum += ord($char) - ord('A');
            }
        }

        return chr(ord('A') + ($sum % 26));
    }
}

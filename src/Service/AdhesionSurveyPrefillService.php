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

namespace App\Service;

/**
 * Pre-compilazione dei questionari azienda/sede (`companies.survey`, `offices.survey`)
 * con i dati della richiesta di adesione, al momento dell'approvazione.
 *
 * Servizio puro: nessun accesso a DB o I/O. Gemello lato frontend:
 * `5t.vue/src/services/adesione-prefill.js` (tenere allineate le tabelle di mappatura).
 *
 * Regole:
 * - le chiavi RER obbligatorie (`src/theme/rer/data/required-fields.js` del frontend,
 *   `generalAziendaRequiredFields` / `sedeAziendaRequiredFields`) senza una sorgente
 *   affidabile sono scritte come `null`: `Company::checkComplete()` e
 *   `Office::checkComplete()` considerano incompleto un survey con valori null, quindi
 *   un'azienda pre-compilata ma non rivista dal valutatore NON risulta completa;
 * - `data_nomina_mm` e `nome_mobility_manager` non hanno sorgente: mai indovinati;
 * - `mm_relationship` (codici `dipendente`/`consulente`/`altro`) e' tradotto nelle
 *   etichette del form; `altro` non ha un'opzione corrispondente e resta null.
 */
class AdhesionSurveyPrefillService
{
    /**
     * Valore di `company_types.survey_template` per il questionario universita'.
     */
    public const TEMPLATE_UNI = 'uni';

    /**
     * Chiavi obbligatorie del questionario azienda (generale).
     *
     * @var array<string>
     */
    public const COMPANY_REQUIRED_KEYS = [
        'nome', 'codice_ateco', 'indirizzo', 'comune', 'nr_sedi', 'nr_dipendenti',
        'nome_mobility_manager', 'data_nomina_mm', 'mm_nome_cognome', 'mm_email',
        'mm_telefono', 'pec_azienda', 'rapporto_lavorativo',
    ];

    /**
     * Chiavi obbligatorie del questionario sede.
     *
     * @var array<string>
     */
    public const OFFICE_REQUIRED_KEYS = [
        'ul_nr_dipendenti', 'ul_dipendenti_flessibili', 'ul_dipendenti_turnazione',
        'az_sosta_bici', 'trasporto_aziendale', 'az_sosta', 'az_sosta_moto', 'az_veicoli',
    ];

    /**
     * Survey azienda dai dati della richiesta.
     *
     * @param array $data Payload `adhesion_requests.data`.
     * @param int|string $companyType `company_types.survey_template` (stringa, `uni`
     *   seleziona le etichette universitarie); un intero (id tipo) non identifica
     *   l'universita' e vale come azienda.
     * @return array
     */
    public function companySurvey(array $data, int|string $companyType): array
    {
        $first = $this->str($data['mm_first_name'] ?? null);
        $last = $this->str($data['mm_last_name'] ?? null);
        $fullName = trim(($first ?? '') . ' ' . ($last ?? ''));

        $survey = [
            'nome' => $this->str($data['company_name'] ?? null),
            'indirizzo' => $this->address($data),
            'codice_ateco' => $this->str($data['ateco'] ?? null),
            'comune' => $this->city($data),
            'cap' => $this->str($data['legal_address_cap'] ?? null),
            'nr_dipendenti' => $this->int($data['num_employees'] ?? null),
            'pec_azienda' => $this->str($data['pec'] ?? null),
            'mm_nome_cognome' => $fullName !== '' ? $fullName : null,
            'mm_email' => $this->str($data['mm_email'] ?? null),
            'mm_telefono' => $this->str($data['mm_phone'] ?? null),
            'rapporto_lavorativo' => $this->relationship(
                $data['mm_relationship'] ?? null,
                is_string($companyType) && strtolower($companyType) === self::TEMPLATE_UNI
            ),
        ];

        return $this->withRequiredNulls($survey, self::COMPANY_REQUIRED_KEYS);
    }

    /**
     * Survey della sede legale auto-creata dai dati della richiesta.
     *
     * @param array $data Payload `adhesion_requests.data`.
     * @return array
     */
    public function officeSurvey(array $data): array
    {
        $survey = [
            'ul_sede_filiale' => 'Sede legale o principale',
            'ul_comune' => $this->city($data),
            'ul_cap' => $this->str($data['legal_address_cap'] ?? null),
            'ul_indirizzo' => $this->address($data),
            'ul_nr_dipendenti' => $this->int($data['num_employees'] ?? null),
        ];

        return $this->withRequiredNulls($survey, self::OFFICE_REQUIRED_KEYS);
    }

    /**
     * @param array $survey Survey parziale.
     * @param array<string> $requiredKeys Chiavi che devono esistere (null se senza sorgente).
     * @return array
     */
    private function withRequiredNulls(array $survey, array $requiredKeys): array
    {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $survey)) {
                $survey[$key] = null;
            }
        }

        return $survey;
    }

    /**
     * @param mixed $value Valore grezzo.
     * @return string|null Stringa trimmata, null se vuota/non scalare.
     */
    private function str(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param mixed $value Valore grezzo.
     * @return int|null
     */
    private function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\s*\d+\s*$/', $value)) {
            return (int)$value;
        }
        if (is_float($value) && $value >= 0 && floor($value) === $value) {
            return (int)$value;
        }

        return null;
    }

    /**
     * Via + civico, come enable() per la colonna `address`.
     *
     * @param array $data Payload.
     * @return string|null
     */
    private function address(array $data): ?string
    {
        $address = trim(
            (string)($this->str($data['legal_address_street'] ?? null) ?? '') . ' ' .
            (string)($this->str($data['legal_address_number'] ?? null) ?? '')
        );

        return $address !== '' ? $address : null;
    }

    /**
     * Comune della sede legale, con ripiego sul comune di riferimento.
     *
     * @param array $data Payload.
     * @return string|null
     */
    private function city(array $data): ?string
    {
        return $this->str($data['legal_address_city'] ?? null)
            ?? $this->str($data['reference_city'] ?? null);
    }

    /**
     * @param mixed $code Codice `mm_relationship`.
     * @param bool $uni Questionario universita'.
     * @return string|null
     */
    private function relationship(mixed $code, bool $uni): ?string
    {
        return match ($this->str($code)) {
            'dipendente' => $uni ? "Dipendente dell'università" : 'Dipendente aziendale',
            'consulente' => 'Consulente esterno',
            default => null,
        };
    }
}

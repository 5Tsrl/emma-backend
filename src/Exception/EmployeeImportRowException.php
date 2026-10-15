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
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

namespace App\Exception;

use Exception;

/**
 * Thrown by EmployeesTable::import() when one or more fields of an imported
 * HR row fail validation. Carries the full per-field failure list (collected
 * across ALL fields present in the site's EmployeeImportFields, not just the
 * first one hit) so EmployeesController::import() can surface a structured
 * `errors: [{field, message}, ...]` array to the frontend, in addition to the
 * legacy single `errorMsg` string (built by joining every message here).
 */
class EmployeeImportRowException extends Exception
{
    /**
     * @var array<int, array{field: string, message: string}>
     */
    private array $errors;

    /**
     * @param array<int, array{field: string, message: string}> $errors One entry per failed field.
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message = implode('; ', array_map(fn ($e) => $e['message'], $errors));
        parent::__construct($message);
    }

    /**
     * @return array<int, array{field: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

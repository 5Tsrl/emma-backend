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
namespace App\Identifier;

use Authentication\Identifier\TokenIdentifier;

/**
 * Jwt Subject aka "sub" identifier.
 *
 * This is mostly a convenience class that just overrides the defaults of the
 * TokenIdentifier.
 */
class Auth0Identifier extends TokenIdentifier
{
    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
    'tokenField' => 'email',
    'dataField' => 'https://emma.5t.torino.it/email',
    'resolver' => 'Authentication.Orm',
    ];
}

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

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Ripulisce la denylist dei token revocati (revoked_tokens) eliminando le righe
 * relative a token ormai scaduti: dopo `expires_at` il token sarebbe comunque
 * rifiutato da firma/exp, quindi la riga non serve più.
 *
 * Da schedulare via cron, es. giornaliero:
 *   bin/cake purge_revoked_tokens
 */
class PurgeRevokedTokensCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Elimina dalla denylist i token revocati ormai scaduti.');

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $deleted = $this->fetchTable('RevokedTokens')->purgeExpired();
        $io->success(sprintf('Pulizia completata: %d token scaduti rimossi dalla denylist.', $deleted));

        return static::CODE_SUCCESS;
    }
}

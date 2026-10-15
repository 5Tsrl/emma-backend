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
use Cake\Core\Configure;

/**
 * Trova (e, con --delete, rimuove) le cartelle di file rimaste orfane dopo la
 * cancellazione di richieste di adesione o aziende avvenuta FUORI dal controller
 * (SQL diretto, FK CASCADE, deleteAll): il controller ripulisce da solo, questi no.
 *
 * Scansiona SOLO, sotto `ROOT/<filesPath>/<sitedir>`:
 *  - `adesioni/<n>`                                   se la richiesta <n> non esiste;
 *  - `company_privacy_documents/pending-request-<n>`  se la richiesta <n> non esiste;
 *  - `company_privacy_documents/<n>`                  se l'azienda <n> non esiste.
 *
 * Il sito è scelto da `HTTP_HOST` (che determina `sitedir`/`filesPath`):
 *   sudo -u www-data HTTP_HOST=rer.drupalvm.test bin/cake adhesion_orphan_files [--delete]
 *
 * Guardie: nomi esatti (`^\d+$`, `^pending-request-\d+$`), realpath sotto la cartella
 * base, symlink mai seguiti, cartelle modificate negli ultimi --min-age-minutes saltate,
 * --delete rifiutato se la tabella di riferimento è vuota (salvo --force), si rimuovono
 * solo file regolari e poi la cartella (una sottocartella o un elemento non regolare
 * viene segnalato e la cartella lasciata intatta).
 */
class AdhesionOrphanFilesCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(
            'Elenca (dry-run, default) o rimuove con --delete le cartelle di file di richieste '
            . 'di adesione/aziende non più presenti nel DB. Il sito è scelto da HTTP_HOST.'
        );
        $parser->addOption('delete', [
            'help' => 'Rimuove davvero i file orfani (senza: solo elenco).',
            'boolean' => true,
        ]);
        $parser->addOption('force', [
            'help' => 'Con --delete, procede anche se adhesion_requests/companies sono vuote.',
            'boolean' => true,
        ]);
        $parser->addOption('min-age-minutes', [
            'help' => 'Salta le cartelle modificate da meno di N minuti (default 60).',
            'default' => '60',
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $delete = (bool)$args->getOption('delete');
        $force = (bool)$args->getOption('force');
        $minAge = max(0, (int)$args->getOption('min-age-minutes'));

        $filesPath = Configure::read('filesPath');
        if (!is_string($filesPath) || $filesPath === '') {
            $io->error('filesPath non configurato: nulla da fare.');

            return static::CODE_ERROR;
        }
        $base = ROOT . DS . $filesPath . DS . (string)Configure::read('sitedir');

        $requestIds = $this->existingIds('AdhesionRequests');
        $companyIds = $this->existingIds('Companies');
        if ($delete && !$force && (!$requestIds || !$companyIds)) {
            $io->error(
                'adhesion_requests o companies risultano vuote: probabile DB/sito sbagliato. '
                . '--delete rifiutato (usa --force per procedere comunque).'
            );

            return static::CODE_ERROR;
        }

        $io->out(($delete ? 'DELETE' : 'DRY-RUN') . ' - base: ' . $base);

        $candidates = array_merge(
            $this->scan($base . 'adesioni', '/^(\d+)$/', $requestIds),
            $this->scan($base . 'company_privacy_documents', '/^pending-request-(\d+)$/', $requestIds),
            $this->scan($base . 'company_privacy_documents', '/^(\d+)$/', $companyIds),
        );

        $cutoff = time() - $minAge * 60;
        $orphans = $files = $bytes = $kept = $recent = 0;
        foreach ($candidates as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime === false || $mtime > $cutoff) {
                $recent++;
                $io->out(sprintf('SKIP (recente) %s', $dir));
                continue;
            }
            $entries = @scandir($dir);
            if ($entries === false) {
                $kept++;
                $io->out(sprintf('SKIP (non leggibile) %s', $dir));
                continue;
            }
            $regular = [];
            $unexpected = false;
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . DS . $entry;
                if (is_file($path) && !is_link($path)) {
                    $regular[$path] = (int)@filesize($path);
                } else {
                    $unexpected = true;
                    $io->out(sprintf('  elemento non regolare/sottocartella: %s', $path));
                }
            }
            $orphans++;
            $files += count($regular);
            $bytes += array_sum($regular);
            $io->out(sprintf('%s  file: %d  byte: %d', $dir, count($regular), array_sum($regular)));
            if ($unexpected) {
                $kept++;
                $io->out('  cartella LASCIATA (contiene elementi non regolari)');
                continue;
            }
            if ($delete) {
                foreach (array_keys($regular) as $path) {
                    @unlink($path);
                }
                if (!@rmdir($dir)) {
                    $io->out('  rimozione cartella fallita (permessi?)');
                }
            }
        }

        $io->out(sprintf(
            'Totale: %d cartelle orfane, %d file, %d byte; saltate per eta: %d; lasciate/illeggibili: %d.%s',
            $orphans,
            $files,
            $bytes,
            $recent,
            $kept,
            $delete ? '' : ' (dry-run: nulla rimosso, usa --delete)'
        ));

        return static::CODE_SUCCESS;
    }

    /**
     * @param string $alias Alias della tabella.
     * @return array<int, true> Mappa id => true.
     */
    private function existingIds(string $alias): array
    {
        $ids = $this->fetchTable($alias)->find()->select(['id'])->disableHydration()->all();
        $map = [];
        foreach ($ids as $row) {
            $map[(int)$row['id']] = true;
        }

        return $map;
    }

    /**
     * Cartelle-candidate orfane: nome che combacia esattamente con $pattern, id assente
     * dal DB, non symlink, realpath sotto la cartella base.
     *
     * @param string $baseDir Cartella da scansionare (un solo livello).
     * @param string $pattern Regex con un gruppo che cattura l'id.
     * @param array<int, true> $existing Id ancora presenti nel DB.
     * @return array<string>
     */
    private function scan(string $baseDir, string $pattern, array $existing): array
    {
        $realBase = realpath($baseDir);
        if ($realBase === false || is_link($baseDir) || !is_dir($realBase)) {
            return [];
        }
        $names = @scandir($realBase);
        if ($names === false) {
            return [];
        }
        $found = [];
        foreach ($names as $name) {
            if (!preg_match($pattern, $name, $m)) {
                continue;
            }
            $path = $realBase . DS . $name;
            if (is_link($path) || !is_dir($path)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false || dirname($real) !== $realBase) {
                continue;
            }
            if (isset($existing[(int)$m[1]])) {
                continue;
            }
            $found[] = $real;
        }

        return $found;
    }
}

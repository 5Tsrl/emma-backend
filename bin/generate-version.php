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

$runCommand = static function (string $command, string $fallback = ''): string {
    $output = @shell_exec($command);

    if (!is_string($output)) {
        return $fallback;
    }

    $value = trim($output);

    return $value !== '' ? $value : $fallback;
};

$date = date('y.m.d');
$buildNumber = $runCommand('git rev-list --count HEAD 2>/dev/null', '0');
$gitHash = $runCommand('git rev-parse --short HEAD 2>/dev/null', 'unknown');
$version = sprintf('v%s.%s', $date, $buildNumber);

$payload = [
    'version' => $version,
    'date' => $date,
    'build' => $buildNumber,
    'git_hash' => $gitHash,
    'generated_at' => date(DATE_ATOM),
];

$targetFile = dirname(__DIR__) . '/webroot/version.json';
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    fwrite(STDERR, "Unable to encode version metadata to JSON.\n");
    exit(1);
}

$result = file_put_contents($targetFile, $json . PHP_EOL);

if ($result === false) {
    fwrite(STDERR, sprintf('Unable to write version file at %s.%s', $targetFile, PHP_EOL));
    exit(1);
}

fwrite(STDOUT, sprintf('Version file generated: %s (%s)%s', $version, $targetFile, PHP_EOL));

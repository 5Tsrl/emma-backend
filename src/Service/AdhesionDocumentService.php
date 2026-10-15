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

use App\Model\Entity\AdhesionRequest;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\View\ViewBuilder;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Documenti del modulo di adesione: generazione del PDF di riepilogo (Step 2 della
 * specifica) e gestione del PDF firmato digitalmente ricaricato dal referente.
 *
 * Scelte di implementazione:
 *
 * - **PDF via WeasyPrint**, come già fa `src/Exporter/PdfExporter.php`: nel repo non c'è
 *   una libreria PHP di conversione HTML→PDF (dompdf/mpdf/CakePdf non sono installati);
 *   TCPDF c'è ma solo come dipendenza di FPDI per la timbratura. Si rende una view Cake in
 *   HTML e si invoca il binario.
 *   NB: deliberatamente NON si estende `MdExporter`/`PdfExporter` — `PdfExporter::generatePSCL()`
 *   non chiama `parent::generatePSCL()` e presuppone che `pscl.html` esista già, un
 *   accoppiamento a due passi che qui sarebbe solo una trappola.
 *
 * - **Storage fuori da webroot** (`ROOT/{filesPath}/{sitedir}/adesioni/{id}/`), come i
 *   documenti PSCL: questi file contengono dati personali di più persone e non devono
 *   essere raggiungibili via URL diretto, ma solo tramite le azioni di download.
 *
 * - **Nessuna verifica crittografica della firma** (decisione di progetto): si accettano
 *   PAdES (`.pdf`) e CAdES (`.p7m`), si validano mime/estensione/dimensione e si registra
 *   il formato. La verifica del certificato è fuori ambito e nel repo non esiste alcun
 *   supporto (nessuna libreria, nessun openssl_pkcs7).
 */
class AdhesionDocumentService
{
    /**
     * Dimensione massima accettata per il PDF firmato (20 MB).
     *
     * Nel backend NESSUN upload esistente controlla la dimensione lato server (il limite
     * di 30 MB del PSCL è solo client-side in `src/components/Pscl/Upload.vue`): qui il
     * controllo c'è, perché l'endpoint è pubblico.
     *
     * @var int
     */
    public const MAX_SIGNED_BYTES = 20 * 1024 * 1024;

    /**
     * Formati di firma ammessi: estensione => [formato, mime accettati].
     *
     * @var array<string, array{format: string, mimes: array<int, string>}>
     */
    public const ALLOWED_SIGNED_TYPES = [
        'pdf' => [
            'format' => 'pades',
            'mimes' => ['application/pdf', 'application/x-pdf'],
        ],
        'p7m' => [
            'format' => 'cades',
            // I .p7m vengono spesso riconosciuti in modo generico da finfo.
            'mimes' => [
                'application/pkcs7-mime',
                'application/x-pkcs7-mime',
                'application/pkcs7',
                'application/octet-stream',
            ],
        ],
    ];

    /**
     * Dimensione massima del documento di identità facoltativo (10 MB).
     *
     * @var int
     */
    public const MAX_IDENTITY_BYTES = 10 * 1024 * 1024;

    /**
     * Tipi ammessi per il documento di identità: estensione => [estensione canonica di
     * salvataggio, mime reali accettati (finfo)]. Il mime reale decide: un `.jpg` che in
     * realtà è un PDF/HTML/testo viene rifiutato.
     *
     * @var array<string, array{ext: string, mimes: array<int, string>}>
     */
    public const ALLOWED_IDENTITY_TYPES = [
        'pdf' => ['ext' => 'pdf', 'mimes' => ['application/pdf', 'application/x-pdf']],
        'jpg' => ['ext' => 'jpg', 'mimes' => ['image/jpeg']],
        'jpeg' => ['ext' => 'jpg', 'mimes' => ['image/jpeg']],
        'png' => ['ext' => 'png', 'mimes' => ['image/png']],
    ];

    /**
     * Prefisso del file di identità nella cartella della richiesta: distinto da
     * `modulo-adesione.*` e `firmato_*`, così non può collidere né essere sovrascritto.
     *
     * @var string
     */
    public const IDENTITY_PREFIX = 'identita_';

    /**
     * Nome del file di riepilogo generato.
     *
     * @var string
     */
    public const SUMMARY_FILENAME = 'modulo-adesione.pdf';

    /**
     * Cartella (fuori webroot) dei documenti di una richiesta.
     *
     * @param int $requestId Id della richiesta.
     * @return string Path assoluto, senza separatore finale.
     */
    public function folderPath(int $requestId): string
    {
        return ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir')
            . 'adesioni' . DS . $requestId;
    }

    /**
     * Path del PDF di riepilogo da firmare.
     *
     * @param int $requestId Id della richiesta.
     * @return string
     */
    public function summaryPath(int $requestId): string
    {
        return $this->folderPath($requestId) . DS . self::SUMMARY_FILENAME;
    }

    /**
     * Path del PDF firmato caricato, o null se non ancora presente.
     *
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta.
     * @return string|null
     */
    public function signedPath(AdhesionRequest $request): ?string
    {
        if (empty($request->signed_pdf_filename)) {
            return null;
        }
        $path = $this->folderPath((int)$request->id) . DS . $request->signed_pdf_filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Genera (o rigenera) il PDF di riepilogo della richiesta.
     *
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta, con `people` caricate.
     * @return string Path assoluto del PDF generato.
     * @throws \RuntimeException Se la conversione non produce un PDF.
     */
    public function generateSummary(AdhesionRequest $request): string
    {
        $folder = $this->folderPath((int)$request->id);
        $this->ensureFolder($folder);

        $html = $this->renderSummaryHtml($request);
        $htmlPath = $folder . DS . 'modulo-adesione.html';
        $pdfPath = $this->summaryPath((int)$request->id);

        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException('Impossibile scrivere l\'HTML di riepilogo in ' . $htmlPath);
        }

        $binary = Configure::read('WeasyprintPath') ?: '/usr/bin/weasyprint';
        $command = escapeshellcmd($binary) . ' ' . escapeshellarg($htmlPath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
        $output = shell_exec($command);

        if (!file_exists($pdfPath) || filesize($pdfPath) === 0) {
            Log::write('error', 'Adhesion PDF generation failed: ' . (string)$output, ['scope' => ['adhesion']]);
            throw new RuntimeException('Generazione del PDF di riepilogo non riuscita');
        }

        return $pdfPath;
    }

    /**
     * Rende il template del riepilogo in HTML.
     *
     * Tutti i valori passano da `h()` nel template: il contenuto arriva da un form
     * pubblico e non deve poter iniettare markup nel documento.
     *
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta.
     * @return string
     */
    protected function renderSummaryHtml(AdhesionRequest $request): string
    {
        $builder = new ViewBuilder();
        $builder->setTemplatePath('pdf')
            ->setTemplate('adhesion_summary')
            ->disableAutoLayout();

        $view = $builder->build([
            'request' => $request,
            'data' => (array)$request->data,
            'people' => $request->people ?? [],
            'generatedAt' => new \Cake\I18n\FrozenTime(),
        ]);

        return $view->render();
    }

    /**
     * Valida e salva il PDF firmato caricato dal referente.
     *
     * Doppio controllo mime (finfo sul file reale) + estensione, come
     * `MeasuresController::resolveUploadedImage()`, più il controllo di dimensione.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Il file caricato.
     * @param int $requestId Id della richiesta.
     * @return array{filename: string, size: int, mime: string, format: string}
     * @throws \RuntimeException Con un messaggio utente-friendly in caso di rifiuto.
     */
    public function storeSignedFile(?UploadedFileInterface $file, int $requestId): array
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($file ? $file->getError() : UPLOAD_ERR_NO_FILE));
        }

        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw new RuntimeException('Il file caricato è vuoto');
        }
        if ($size > self::MAX_SIGNED_BYTES) {
            throw new RuntimeException(sprintf(
                'Il file supera la dimensione massima consentita di %d MB',
                (int)(self::MAX_SIGNED_BYTES / 1024 / 1024)
            ));
        }

        $clientName = (string)$file->getClientFilename();
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_SIGNED_TYPES[$extension])) {
            throw new RuntimeException('Sono ammessi solo file .pdf (PAdES) o .p7m (CAdES)');
        }

        // Mime reale del file, non quello dichiarato dal client.
        $tmpPath = $file->getStream()->getMetadata('uri');
        $detected = null;
        if (is_string($tmpPath) && file_exists($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmpPath) ?: null;
                finfo_close($finfo);
            }
        }
        $allowedMimes = self::ALLOWED_SIGNED_TYPES[$extension]['mimes'];
        if ($detected !== null && !in_array($detected, $allowedMimes, true)) {
            throw new RuntimeException('Il contenuto del file non corrisponde all\'estensione dichiarata');
        }

        $folder = $this->folderPath($requestId);
        $this->ensureFolder($folder);

        // Nome sanificato: mai il nome del client così com'è.
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($clientName, PATHINFO_FILENAME)) ?? 'documento';
        $filename = 'firmato_' . uniqid() . '_' . substr($safeBase, 0, 60) . '.' . $extension;

        $file->moveTo($folder . DS . $filename);

        return [
            'filename' => $filename,
            'size' => $size,
            'mime' => $detected ?? ($allowedMimes[0] ?? 'application/octet-stream'),
            'format' => self::ALLOWED_SIGNED_TYPES[$extension]['format'],
        ];
    }

    /**
     * Path del documento di identità caricato, o null se assente. Il nome è validato
     * contro il prefisso/charset attesi: un valore anomalo in DB non deve mai poter
     * puntare fuori dalla cartella della richiesta.
     *
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta.
     * @return string|null
     */
    public function identityPath(AdhesionRequest $request): ?string
    {
        $name = (string)$request->identity_doc_filename;
        if ($name === '' || !preg_match('/^' . self::IDENTITY_PREFIX . '[a-f0-9]{16}\.(pdf|jpg|png)$/', $name)) {
            return null;
        }
        $path = $this->folderPath((int)$request->id) . DS . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * Valida e salva il documento di identità facoltativo (un solo file per richiesta,
     * fronte e retro insieme). Estensione + mime REALE (finfo) + dimensione; fail-closed
     * se finfo non è disponibile. Il nome è casuale (mai quello del client). Un file
     * precedente viene rimosso.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Il file caricato.
     * @param int $requestId Id della richiesta.
     * @return array{filename: string, size: int, mime: string}
     * @throws \RuntimeException Con un messaggio utente-friendly in caso di rifiuto.
     */
    public function storeIdentityFile(?UploadedFileInterface $file, int $requestId): array
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($file ? $file->getError() : UPLOAD_ERR_NO_FILE));
        }

        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw new RuntimeException('Il file caricato è vuoto');
        }
        if ($size > self::MAX_IDENTITY_BYTES) {
            throw new RuntimeException(sprintf(
                'Il file supera la dimensione massima consentita di %d MB',
                (int)(self::MAX_IDENTITY_BYTES / 1024 / 1024)
            ));
        }

        $extension = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_IDENTITY_TYPES[$extension])) {
            throw new RuntimeException('Sono ammessi solo file .pdf, .jpg o .png');
        }
        $type = self::ALLOWED_IDENTITY_TYPES[$extension];

        $tmpPath = $file->getStream()->getMetadata('uri');
        $detected = null;
        if (is_string($tmpPath) && file_exists($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmpPath) ?: null;
                finfo_close($finfo);
            }
        }
        if ($detected === null || !in_array($detected, $type['mimes'], true)) {
            throw new RuntimeException('Il contenuto del file non corrisponde all\'estensione dichiarata');
        }

        $folder = $this->folderPath($requestId);
        $this->ensureFolder($folder);

        // NON si rimuove qui il file precedente: se lo spostamento o il salvataggio della
        // riga falliscono, il documento valido già caricato deve restare (il chiamante
        // chiama purgeIdentityFile($id, $nuovoNome) solo DOPO il salvataggio riuscito).
        $filename = self::IDENTITY_PREFIX . bin2hex(random_bytes(8)) . '.' . $type['ext'];
        $file->moveTo($folder . DS . $filename);

        return ['filename' => $filename, 'size' => $size, 'mime' => $detected];
    }

    /**
     * Elimina dal disco ogni file di identità della richiesta (glob sul prefisso: copre
     * anche eventuali file rimasti orfani). Non tocca il resto della cartella.
     *
     * @param int $requestId Id della richiesta.
     * @param string|null $keepFilename Nome (basename) di un file da NON toccare: il
     *   documento appena salvato, quando si eliminano quelli che sostituisce.
     * @return void
     */
    public function purgeIdentityFile(int $requestId, ?string $keepFilename = null): void
    {
        $folder = $this->folderPath($requestId);
        foreach ((array)glob($folder . DS . self::IDENTITY_PREFIX . '*') as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            if ($keepFilename !== null && basename($file) === $keepFilename) {
                continue;
            }
            @unlink($file);
        }
    }

    /**
     * Elimina UN solo file di identità, identificato dal nome (solo se ha la forma
     * attesa): serve a scartare il file appena caricato quando il salvataggio della
     * riga fallisce, senza toccare il documento valido caricato in precedenza.
     *
     * @param int $requestId Id della richiesta.
     * @param string $filename Nome generato da storeIdentityFile().
     * @return void
     */
    public function deleteIdentityFile(int $requestId, string $filename): void
    {
        if (!preg_match('/^' . self::IDENTITY_PREFIX . '[a-f0-9]{16}\.(pdf|jpg|png)$/', $filename)) {
            return;
        }
        $path = $this->folderPath($requestId) . DS . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Rimuove i documenti di una richiesta (usato quando la richiesta viene eliminata).
     *
     * @param int $requestId Id della richiesta.
     * @return void
     */
    public function deleteFolder(int $requestId): void
    {
        $folder = $this->folderPath($requestId);
        if (!is_dir($folder)) {
            return;
        }
        foreach ((array)glob($folder . DS . '*') as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($folder);
    }

    /**
     * Crea la cartella di destinazione se serve.
     *
     * @param string $folder Path assoluto.
     * @return void
     * @throws \RuntimeException Se la cartella non è creabile.
     */
    protected function ensureFolder(string $folder): void
    {
        if (is_dir($folder)) {
            return;
        }
        if (!mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('Impossibile creare la cartella dei documenti: ' . $folder);
        }
    }

    /**
     * Messaggio leggibile per i codici di errore di upload di PHP.
     *
     * @param int $error Codice UPLOAD_ERR_*.
     * @return string
     */
    protected function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Il file caricato è troppo grande';
            case UPLOAD_ERR_PARTIAL:
                return 'Il caricamento del file è stato interrotto: riprovare';
            case UPLOAD_ERR_NO_FILE:
                return 'Nessun file caricato';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return 'Errore del server nel salvataggio del file: riprovare più tardi';
            default:
                return 'Caricamento del file non riuscito';
        }
    }
}

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

use Cake\Core\Configure;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Storage del "Modulo trattamento dati personali dei dipendenti" (requisito 3 della
 * compliance privacy RER "EMMA RE-R"): un documento PER AZIENDA, firmato dal legale
 * rappresentante e caricato tramite il link permanente `/adesione/completa/:token`
 * (AdhesionRequestsController::employeePrivacyDocument()), poi mostrato ai dipendenti
 * all'inizio della compilazione del questionario
 * (SurveysController::employeePrivacyDocument()).
 *
 * Imita deliberatamente il pattern di Service/AdhesionDocumentService (storage FUORI
 * webroot, doppio controllo mime+estensione, PAdES `.pdf` / CAdES `.p7m`, nessuna
 * verifica crittografica della firma) senza estenderlo: è un'entità diversa (per
 * azienda, non per singola richiesta di adesione) con un ciclo di vita diverso — questo
 * documento è riusato per tutte le survey/anni futuri. La riga DB viene cancellata in
 * CASCADE dall'undo di una richiesta di adesione solo mentre è ancora pendente
 * (`adhesion_request_id` valorizzata, FK `ON DELETE CASCADE`); una volta ri-agganciata
 * all'azienda (`company_id`) sopravvive a qualunque undo della richiesta originaria.
 *
 * Ciclo di vita a due fasi (vedi anche CompanyPrivacyDocumentsTable):
 * - **pendente**: caricato durante `in_review`, quando non esiste ancora un
 *   `company_id` — vive in `pendingFolderPath()`, una cartella "pending-request-{id}"
 *   separata da qualunque cartella numerica di azienda.
 * - **definitivo**: all'approvazione, `relocateToCompany()` sposta il file nella
 *   cartella reale dell'azienda e la riga DB viene ri-agganciata
 *   (AdhesionRequestsController::enable()).
 */
class CompanyPrivacyDocumentService
{
    /**
     * Dimensione massima accettata (20 MB, come il PDF firmato dell'adesione).
     *
     * @var int
     */
    public const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * Formati ammessi: estensione => [formato, mime accettati].
     *
     * @var array<string, array{format: string, mimes: array<int, string>}>
     */
    public const ALLOWED_TYPES = [
        'pdf' => [
            'format' => 'pades',
            'mimes' => ['application/pdf', 'application/x-pdf'],
        ],
        'p7m' => [
            'format' => 'cades',
            'mimes' => [
                'application/pkcs7-mime',
                'application/x-pkcs7-mime',
                'application/pkcs7',
                'application/octet-stream',
            ],
        ],
    ];

    /**
     * Cartella (fuori webroot) dei documenti di un'azienda.
     *
     * @param int $companyId Id dell'azienda.
     * @return string Path assoluto, senza separatore finale.
     */
    public function folderPath(int $companyId): string
    {
        return ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir')
            . 'company_privacy_documents' . DS . $companyId;
    }

    /**
     * Cartella (fuori webroot) del documento PENDENTE di una richiesta di adesione non
     * ancora approvata. Il prefisso "pending-request-" (stringa, mai numerico da solo)
     * garantisce che non possa mai collidere con una cartella-azienda esistente, i cui
     * nomi sono puramente numerici.
     *
     * @param int $adhesionRequestId Id della richiesta di adesione.
     * @return string Path assoluto, senza separatore finale.
     */
    public function pendingFolderPath(int $adhesionRequestId): string
    {
        return ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir')
            . 'company_privacy_documents' . DS . 'pending-request-' . $adhesionRequestId;
    }

    /**
     * Valida e salva il documento caricato per un'azienda, sostituendo l'eventuale
     * versione precedente.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Il file caricato.
     * @param int $companyId Id dell'azienda.
     * @return array{filename: string, path: string, size: int, mime: string, format: string}
     * @throws \RuntimeException Con un messaggio utente-friendly in caso di rifiuto.
     */
    public function storeFile(?UploadedFileInterface $file, int $companyId): array
    {
        return $this->storeInFolder($file, $this->folderPath($companyId));
    }

    /**
     * Valida e salva il documento caricato per una richiesta di adesione ancora in
     * attesa di approvazione (`in_review`), quando non esiste ancora un `company_id`
     * a cui agganciarlo.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Il file caricato.
     * @param int $adhesionRequestId Id della richiesta di adesione.
     * @return array{filename: string, path: string, size: int, mime: string, format: string}
     * @throws \RuntimeException Con un messaggio utente-friendly in caso di rifiuto.
     */
    public function storeFileForRequest(?UploadedFileInterface $file, int $adhesionRequestId): array
    {
        return $this->storeInFolder($file, $this->pendingFolderPath($adhesionRequestId));
    }

    /**
     * Valida e salva il documento caricato nella cartella indicata, sostituendo
     * l'eventuale versione precedente della stessa cartella.
     *
     * Doppio controllo mime (finfo sul file reale) + estensione, come
     * AdhesionDocumentService::storeSignedFile(), più il controllo di dimensione.
     * L'ordine è importante: la purge della cartella avviene SOLO dopo che il file in
     * ingresso ha superato tutte le validazioni, altrimenti un upload rifiutato
     * cancellerebbe un documento valido già presente.
     *
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Il file caricato.
     * @param string $folder Cartella di destinazione (azienda o pendente).
     * @return array{filename: string, path: string, size: int, mime: string, format: string}
     * @throws \RuntimeException Con un messaggio utente-friendly in caso di rifiuto.
     */
    protected function storeInFolder(?UploadedFileInterface $file, string $folder): array
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($file ? $file->getError() : UPLOAD_ERR_NO_FILE));
        }

        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw new RuntimeException('Il file caricato è vuoto');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(sprintf(
                'Il file supera la dimensione massima consentita di %d MB',
                (int)(self::MAX_BYTES / 1024 / 1024)
            ));
        }

        $clientName = (string)$file->getClientFilename();
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension])) {
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
        $allowedMimes = self::ALLOWED_TYPES[$extension]['mimes'];
        if ($detected !== null && !in_array($detected, $allowedMimes, true)) {
            throw new RuntimeException('Il contenuto del file non corrisponde all\'estensione dichiarata');
        }

        $this->ensureFolder($folder);

        // Un solo documento per cartella: rimuove la versione precedente prima di
        // salvare la nuova ("sostituzione"), per non lasciare file orfani sul disco.
        $this->purgeFolder($folder);

        // Nome sanificato: mai il nome del client così com'è.
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($clientName, PATHINFO_FILENAME)) ?? 'documento';
        $filename = 'trattamento_dati_' . uniqid() . '_' . substr($safeBase, 0, 60) . '.' . $extension;
        $path = $folder . DS . $filename;

        $file->moveTo($path);

        return [
            'filename' => $filename,
            'path' => $path,
            'size' => $size,
            'mime' => $detected ?? ($allowedMimes[0] ?? 'application/octet-stream'),
            'format' => self::ALLOWED_TYPES[$extension]['format'],
        ];
    }

    /**
     * Rimuove il documento eventualmente già presente per l'azienda (nessuna riga DB
     * coinvolta: solo i file fisici della cartella dedicata).
     *
     * @param int $companyId Id dell'azienda.
     * @param bool $removeDir Se true rimuove anche la cartella (azienda cancellata);
     *   false (default) la lascia per la sostituzione di un documento.
     * @return void
     */
    public function deleteExisting(int $companyId, bool $removeDir = false): void
    {
        $this->purgeFolder($this->folderPath($companyId), $removeDir);
    }

    /**
     * Rimuove il documento pendente (se presente) e la sua cartella, per una richiesta
     * di adesione che non verrà mai approvata (rifiutata e poi cancellata). Nessuna
     * riga DB coinvolta: quella la gestisce già la FK `ON DELETE CASCADE`. No-op
     * silenzioso se la cartella non esiste.
     *
     * Mirror di AdhesionDocumentService::deleteFolder().
     *
     * @param int $adhesionRequestId Id della richiesta di adesione.
     * @return void
     */
    public function deletePendingFolder(int $adhesionRequestId): void
    {
        $this->purgeFolder($this->pendingFolderPath($adhesionRequestId), true);
    }

    /**
     * Sposta il documento pendente di una richiesta di adesione nella cartella
     * definitiva della sua azienda, al momento dell'approvazione.
     *
     * Non lancia mai eccezioni: il chiamante (AdhesionRequestsController::enable(),
     * DOPO il commit della transazione DB) deve poter loggare e proseguire senza far
     * fallire l'approvazione per un problema di filesystem. Ritorna `null` se il file
     * sorgente non esiste o lo spostamento fallisce — in quel caso il chiamante lascia
     * `path` invariato (punta ancora alla cartella pendente, resta comunque leggibile).
     *
     * Purga per prima la cartella di destinazione (D5: un'azienda che aderisce di
     * nuovo può già avere un documento precedente) prima di spostarci dentro quello
     * nuovo, poi rimuove la cartella pendente ormai svuotata.
     *
     * @param int $adhesionRequestId Id della richiesta di adesione.
     * @param int $companyId Id dell'azienda di destinazione.
     * @param string $currentPath Path assoluto attuale del file (cartella pendente).
     * @return string|null Il nuovo path assoluto, o `null` se lo spostamento non è riuscito.
     */
    public function relocateToCompany(int $adhesionRequestId, int $companyId, string $currentPath): ?string
    {
        if (!is_file($currentPath)) {
            return null;
        }

        $destinationFolder = $this->folderPath($companyId);
        try {
            $this->ensureFolder($destinationFolder);
        } catch (RuntimeException $e) {
            return null;
        }

        // D5: un'eventuale documento già presente per l'azienda (re-adesione) va
        // rimosso prima di spostarci dentro il nuovo — altrimenti resterebbe un file
        // orfano, e il vincolo "un solo documento per azienda" varrebbe solo a livello
        // DB, non su disco.
        $this->purgeFolder($destinationFolder);

        $newPath = $destinationFolder . DS . basename($currentPath);
        if (!@rename($currentPath, $newPath)) {
            return null;
        }

        $this->purgeFolder($this->pendingFolderPath($adhesionRequestId), true);

        return $newPath;
    }

    /**
     * Rimuove tutti i file di una cartella (e, opzionalmente, la cartella stessa se
     * rimasta vuota). No-op silenzioso se la cartella non esiste.
     *
     * @param string $folder Path assoluto.
     * @param bool $removeDir Se true, rimuove anche la cartella dopo averla svuotata.
     * @return void
     */
    protected function purgeFolder(string $folder, bool $removeDir = false): void
    {
        if (!is_dir($folder)) {
            return;
        }
        foreach ((array)glob($folder . DS . '*') as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }
        if ($removeDir) {
            @rmdir($folder);
        }
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

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\User;
use App\Notification\protocolloNotification;
use App\Notification\psclNotification;
use App\Notification\psclUploadNotification;
use Brescia\Notification\altriDocumentiUploadNotification;
use Brescia\Notification\decretoNominaUploadNotification;
use Brescia\Notification\templateRiscontroUploadNotification;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Pheanstalk\Exception;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeName;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

/**
 * Pscl Controller
 *
 * @property \App\Model\Table\PsclTable $Pscl
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class PsclController extends AppController
{
    use \App\Exporter\UtilityExporterTrait;

    /**
     * View method
     *
     * `year == 'TUTTI'` ("tutti gli anni") used to be stored in the legacy
     * `offices.PSCL` column, bypassing the `pscl` table entirely. That column
     * is gone (moved to a `pscl` row with `year IS NULL` by
     * `bin/cake migrate_office_pscl_to_plan`, see CLAUDE.md / dev-notes
     * "office.PSCL -> pscl.plan migration") — TUTTI is now just the
     * `year IS NULL` case of the SAME `pscl` table lookup as any other year,
     * mirroring the unification already done in edit(). Companion fix to that
     * change: edit() was updated when the legacy column was retired but this
     * read-side method was missed, so it was still reading a (by-then
     * nonexistent) `offices.PSCL` for every TUTTI view.
     *
     * @param null $company_id a cui si riferisce il pscl
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($company_id = null, $office_id = null, $year = null)
    {
        $pscl = null;

        if (!empty($office_id)) {
            $pscl = $this->Pscl->find()->where(['company_id' => $company_id]);
            $pscl = $pscl->where($this->getYearCondition($year));
            if ($office_id != 'null') {
                $pscl->where(['office_id' => $office_id]);
            } else {
                $pscl->where(['office_id IS NULL']);
            }
            $pscl = $pscl->first();

            if (empty($pscl)) {
                throw new NotFoundException(__('PSCL of this Office Not found'));
            }
        }
        $this->set('pscl', $pscl);
        $this->viewBuilder()->setOption('serialize', ['pscl']);
    }

    public function viewById($pscl_id = null)
    {
        $pscl = $this->Pscl->get($pscl_id, [
            'contain' => [
                    'Companies',
                    'Offices' => ['fields' => ['name']],
                    'Surveys' => ['fields' => ['name', 'year']],
            ] ,
        ]);

        if (empty($pscl)) {
            throw new NotFoundException(__('PSCL Not found'));
        }

        $this->set('pscl', $pscl);
        $this->viewBuilder()->setOption('serialize', ['pscl']);
    }

    /**
     * Edit method
     *
     * `year == 'TUTTI'` ("tutti gli anni") used to be stored in the legacy
     * `offices.PSCL` column, bypassing the `pscl` table entirely. That column
     * is gone (moved to a `pscl` row with `year IS NULL` by
     * `bin/cake migrate_office_pscl_to_plan`, see CLAUDE.md / dev-notes
     * "office.PSCL -> pscl.plan migration") — TUTTI is now just the
     * `year IS NULL` case of the SAME code path as any other year, so the two
     * branches that used to exist here (TUTTI vs. real year) are unified.
     * TUTTI still requires an office_id (unchanged behavior: a company-wide
     * "all years" plan was never supported).
     *
     * @param null $company_id a cui si riferisce il pscl
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($company_id = null, $office_id = null, $survey_id = null)
    {
        $success = true;

        $year = $this->request->getData('year');
        $isTutti = $year == 'TUTTI';

        if ($this->request->is('post')) {
            if ($isTutti && $office_id == 'null') {
                // Un piano "TUTTI" (tutti gli anni) richiede sempre una sede.
                $success = false;
            } else {
                $pscl = $this->Pscl->find()->where(['company_id' => $company_id]);
                $pscl = $isTutti ? $pscl->where(['year IS' => null]) : $pscl->where(['year' => $year]);
                if ($office_id != 'null') {
                    $pscl = $pscl->where(['office_id' => $office_id]);
                } else {
                    $pscl = $pscl->where(['office_id IS NULL']);
                }
                $pscl = $pscl->first();
                $isNewPscl = $pscl === null;
                if ($isNewPscl) {
                    $pscl = $this->Pscl->newEmptyEntity();
                    if ($office_id != 'null') {
                        $pscl->office_id = $office_id;
                    }
                    $pscl->company_id = $company_id;
                    $pscl->year = $isTutti ? null : $year;
                }
                $pscl->survey_id = $survey_id;

                $postedPlan = $this->request->getData('pscl');
                $templateId = $this->request->getData('template_id');
                $copyFromYear = $this->request->getData('copy_from_year');
                $copyFromPsclId = $this->request->getData('copy_from_pscl_id');
                // Un nuovo pscl senza piano posto dal client può essere seminato
                // da un template salvato (insieme ordinato di assi/misure, vedi
                // PsclTable::buildPlanFromTemplate()), copiando il piano
                // dell'anno precedente (buildPlanFromPreviousYear()) o un
                // qualunque altro pscl di riferimento scelto dall'utente nel
                // dialog "storico" (buildPlanFromPsclId()), invece che partire
                // vuoto. In tutti i casi il campo `year` di ogni asse/misura
                // resta vuoto: l'utente deve compilarlo esplicitamente per
                // poter validare il piano (nessun valore precompilato).
                if ($isNewPscl && empty($postedPlan) && !empty($templateId)) {
                    $entries = $this->Pscl->buildPlanFromTemplate((int)$templateId);
                    $pscl->plan = [0 => [], 1 => $entries];
                } elseif ($isNewPscl && empty($postedPlan) && !empty($copyFromYear)) {
                    $entries = $this->Pscl->buildPlanFromPreviousYear($company_id, $office_id, $copyFromYear);
                    $pscl->plan = [0 => [], 1 => $entries];
                } elseif ($isNewPscl && empty($postedPlan) && !empty($copyFromPsclId)) {
                    $entries = $this->Pscl->buildPlanFromPsclId((int)$copyFromPsclId);
                    $pscl->plan = [0 => [], 1 => $entries];
                } elseif ($isNewPscl && empty($postedPlan)) {
                    // Nessuna scelta esplicita del client (nessun `pscl`,
                    // `template_id`, `copy_from_year` o `copy_from_pscl_id`):
                    // ricado sul template di default dell'azienda
                    // (companies.pscl_template_id, impostabile solo da admin,
                    // vedi CompaniesController::edit()), se configurato,
                    // altrimenti costruisco il piano vuoto dal catalogo
                    // Measures corrente (tutti i valori a 0).
                    $companies = $this->getTableLocator()->get('Companies');
                    $company = $companies->find()
                        ->select(['id', 'pscl_template_id'])
                        ->where(['id' => $company_id])
                        ->first();
                    $defaultTemplateId = $company->pscl_template_id ?? null;
                    $entries = !empty($defaultTemplateId)
                        ? $this->Pscl->buildPlanFromTemplate((int)$defaultTemplateId)
                        : $this->Pscl->buildPlanFromMeasures();
                    $pscl->plan = [0 => [], 1 => $entries];
                } else {
                    $pscl->plan = $postedPlan;
                }

                // Un piano inviato dal client nella forma nidificata legacy
                // (bucket per pilastro, chiavi > 1) non va salvato: cleanPlan()
                // è un no-op su quella forma e il piano verrebbe persistito
                // invariato. Va prima migrato con migrate_pscl_plan_format.
                if (is_array($postedPlan)) {
                    foreach (array_keys($postedPlan) as $bucketKey) {
                        if ((int)$bucketKey > 1) {
                            throw new BadRequestException(
                                __('Formato del piano PSCL non supportato (struttura legacy). '
                                . 'Eseguire la migrazione del piano.')
                            );
                        }
                    }
                }

                //Prima di salvare il PSCL lo ripulisco degli elementi vuoti e doppi
                $pscl->cleanPlan();
                if ($this->Pscl->save($pscl)) {
                    $success = true;
                    $this->set('pscl_id', $pscl->id);
                } else {
                    $success = false;
                }
            }
        }
        $this->set('success', $success);
        $this->viewBuilder()->setOption('serialize', ['success', 'pscl_id']);
    }

    /**
     * templates method - elenco dei template di piano PSCL disponibili
     * (righe pscl con version_tag='template'), usato dal picker mostrato alla
     * creazione di un nuovo piano. Distinto dal template grafico docx gestito
     * da uploadTemplate().
     *
     * @return void
     */
    public function templates()
    {
        $rows = $this->Pscl->findTemplates()->toArray();
        $templates = array_map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->plan[0]['name'] ?? 'Template #' . $t->id,
            ];
        }, $rows);

        $this->set(compact('templates'));
        $this->viewBuilder()->setOption('serialize', ['templates']);
    }

    /**
     * getTemplate method - dettaglio di un singolo template (id, name, entries),
     * usato dalla pagina admin pscl/templates per caricare un template esistente
     * da modificare. A differenza di viewById() (generico, nessuno scoping),
     * questa azione risponde SOLO per righe version_tag='template', così non può
     * essere usata per leggere il piano di una company/office/survey reale.
     *
     * @param int|string $id Id della riga pscl di tipo template.
     * @return void
     */
    public function getTemplate($id)
    {
        $template = $this->Pscl->find()
            ->where(['id' => $id, 'version_tag' => 'template'])
            ->first();

        if ($template === null) {
            $this->set('errorMsg', __('Template non trovato.'));
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);

            return;
        }

        $entries = is_array($template->plan[1] ?? null) ? $template->plan[1] : [];
        $this->set('errorMsg', false);
        $this->set('id', $template->id);
        $this->set('name', $template->plan[0]['name'] ?? 'Template #' . $template->id);
        $this->set('entries', $entries);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'id', 'name', 'entries']);
    }

    /**
     * saveTemplate method - crea/aggiorna (admin) un template di piano PSCL.
     * Body: {id?: int, name: string, entries: [{pillar_id, measure_id,
     * measure_slug, weight}, ...]} — `entries` è l'insieme ordinato di assi
     * (pillar_id=0)/misure costruito dalla pagina admin pscl/templates.
     *
     * @return void
     */
    public function saveTemplate()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $id = $this->request->getData('id');
        $name = (string)$this->request->getData('name');
        $entries = (array)$this->request->getData('entries');

        if (!empty($id)) {
            $template = $this->Pscl->find()
                ->where(['id' => $id, 'version_tag' => 'template'])
                ->first();
            if ($template === null) {
                $this->set('errorMsg', __('Template non trovato.'));
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }
        } else {
            $template = $this->Pscl->newEmptyEntity();
            $template->version_tag = 'template';
        }

        $normalized = array_values(array_map(function ($e) {
            return [
                'value' => 0,
                'pillar_id' => (int)($e['pillar_id'] ?? 0),
                'measure_id' => (int)($e['measure_id'] ?? 0),
                'measure_slug' => $e['measure_slug'] ?? null,
                'weight' => (int)($e['weight'] ?? 0),
                'year' => null,
            ];
        }, $entries));

        $template->plan = [0 => ['name' => $name], 1 => $normalized];
        $template->setDirty('plan', true);

        if ($this->Pscl->save($template)) {
            $this->set('errorMsg', false);
            $this->set('template_id', $template->id);
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'template_id']);

            return;
        }

        $errors = $template->getErrors();
        $errorMsg = empty($errors)
            ? __('Impossibile salvare il template.')
            : json_encode($errors, JSON_UNESCAPED_UNICODE);
        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    /**
     * deleteTemplate method - elimina (admin) un template di piano PSCL.
     *
     * @param int|string $id Id della riga pscl di tipo template.
     * @return void
     */
    public function deleteTemplate($id)
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post', 'delete']);

        $template = $this->Pscl->find()
            ->where(['id' => $id, 'version_tag' => 'template'])
            ->first();

        if ($template === null) {
            $this->set('errorMsg', __('Template non trovato.'));
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);

            return;
        }

        $errorMsg = $this->Pscl->delete($template)
            ? false
            : __('Impossibile eliminare il template.');

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    /**
     * history method - elenco dei PSCL "reali" (esclusi i template) già
     * esistenti per un'azienda, opzionalmente filtrato per sede — usato dal
     * dialog "scegli da quale PSCL di riferimento partire" mostrato sulla
     * pagina Interventi quando l'azienda ha già PSCL nel passato (vedi
     * PsclTable::findPastForCompany()/buildPlanFromPsclId()). Ogni voce porta
     * il nome della sede (o null per un piano "tutta l'azienda") così il
     * frontend non deve fare una seconda chiamata per risolverlo.
     *
     * @param mixed $company_id Id azienda.
     * @param mixed $office_id Id sede, oppure 'null' per includere tutte le sedi.
     * @return void
     */
    public function history($company_id, $office_id = 'null')
    {
        $this->allowWhoCanSeeCompanyOnly($company_id);

        $rows = $this->Pscl->findPastForCompany($company_id, $office_id)
            ->contain(['Offices' => ['fields' => ['id', 'name']]])
            ->toArray();

        $history = array_map(function ($p) {
            return [
                'id' => $p->id,
                'year' => $p->year,
                'office_id' => $p->office_id,
                'office_name' => $p->office->name ?? null,
            ];
        }, $rows);

        $this->set(compact('history'));
        $this->viewBuilder()->setOption('serialize', ['history']);
    }

    public function count()
    {
        $this->allowRolesOnly(['admin', 'moma', 'user', 'moma_area']);
        $this->Offices = $this->getTableLocator()->get('Offices');

        $count =  $this->Offices->find()->where(['PSCL IS NOT' => null])->count();
        $this->set(compact('count'));
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    public function uploadTemplate()
    {
        $this->allowRolesOnly(['moma', 'admin', 'moma_area']);

        if ($this->request->is('POST')) {
            $t = $this->request->getData();
            if ($t != 'scuola') {
                $t == 'azienda';
            }

            $attachment = $this->request->getData('file');

            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            // Sanitized, non-unique (deterministic): DocxExporter/HtmlExporter read
            // this file back later by the exact same 'modello-pscl-<target>.docx'
            // name (target === company_types.survey_template, 'azienda'|'scuola'),
            // so it must stay stable - see AppController::sanitizeUploadFilename().
            $target = $this->sanitizeUploadFilename((string)($t['target'] ?? ''), false);

            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                $extension = strtolower($parts['extension'] ?? '');

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMimeType = $finfo !== false ? finfo_file($finfo, $fname) : false;
                if ($finfo !== false) {
                    finfo_close($finfo);
                }
                // .docx is an OOXML/zip container: depending on the libmagic
                // version installed, finfo may report either MIME type for it.
                $allowedMimeTypes = [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                ];

                if ($extension == 'docx' && in_array($detectedMimeType, $allowedMimeTypes, true)) {
                    $targetPath = ROOT . DS . Configure::read('filesPath')  . DS . Configure::read('sitedir') . '/modello-pscl-' . $target . '.docx';
                    // $attachment->moveTo($targetPath);
                    move_uploaded_file($fname, $targetPath);
                    $this->Flash->success('Documento caricato con successo');

                    return;
                } else {
                    $this->Flash->success('Il documento non è stato caricato: dev\'essere di tipo docx');

                    return;
                }
            }
            $this->Flash->success('Il documento non è stato caricato a causa di un errore: ' . $error);

            return;
        }
    }

    /**
     * Carica le immagini di un PSCL
     *
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @param mixed $ignore_office
     * @param mixed $year
     * @return void
     * @throws \App\Controller\ForbiddenException
     * @throws \App\Controller\InvalidArgumentException
     */
    public function uploadImages($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $this->allowRolesOnly(['moma', 'admin', 'moma_area']);

        if ($this->request->is('POST')) {
            $t = $this->request->getData();

            $attachment = $this->request->getData('file');

            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            // Sanitized, non-unique (deterministic): the frontend (md-editor.vue)
            // references the uploaded image back by the original client-side
            // filename it already knows (files[0].name), so the stored name must
            // stay stable - see AppController::sanitizeUploadFilename(). svg is
            // intentionally excluded from the allowed types below (stored-XSS
            // vector - SVG can embed <script>).
            $safeName = $this->sanitizeUploadFilename($name, false);

            $class = $this->Pscl->getExporterClass('md');
            $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);
            $folderName = $e->resultPath($year);
            $targetPath = "$folderName/images/$safeName";

            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                $extension = strtolower($parts['extension'] ?? '');
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMimeType = $finfo !== false ? finfo_file($finfo, $fname) : false;
                if ($finfo !== false) {
                    finfo_close($finfo);
                }
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

                if (
                    in_array($extension, $allowedExtensions, true)
                    && in_array($detectedMimeType, $allowedMimeTypes, true)
                ) {
                    // $attachment->moveTo($targetPath);
                    move_uploaded_file($fname, $targetPath);
                    $response = [
                        'message' => 'Immagine caricata con successo',
                    ];
                    $this->set(compact('response'));
                    $this->viewBuilder()->setOption('serialize', ['response']);

                    return;
                } else {
                    $response = [
                        'message' => 'Immagine non caricata: dev\'essere di tipo jpg, jpeg, png o webp',
                    ];
                    $this->set(compact('response'));
                    $this->viewBuilder()->setOption('serialize', ['response']);

                    return;
                }
            }
            $response = [
                'message' => 'Immagine non caricata a causa di un errore: ' . $error,
            ];
            $this->set(compact('response'));
            $this->viewBuilder()->setOption('serialize', ['response']);

            return;
        }
    }

    private function getDirectoryTree($dir)
    {
        $result = [];

        // Apri la directory
        $files = scandir($dir);

        // Usa natsort per l'ordinamento naturale
        natsort($files);

        foreach ($files as $file) {
            // Ignora le directory speciali "." e ".."
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Costruisci il percorso completo del file o della directory
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            // Se è una directory, chiama ricorsivamente la funzione
            if (is_dir($path)) {
                $result[$file] = $this->getDirectoryTree($path);
            } else {
                // Se è un file, aggiungilo all'array
                $result[] = $file;
            }
        }

        return $result;
    }

    /**
     * getFolder method
     *
     * @param mixed $company_id id dell'azienda
     * @param mixed $office_id id dell'ufficio
     * @param mixed $survey_id id della survey
     * @param mixed $ignore_office Whether to ignore the office in the PSCL generation
     * @param mixed $year   l'anno di riferimento
     * @return void
     */
    public function getFolder($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $this->company_id = $company_id;
        $this->office_id = $office_id;
        $this->survey_id = $survey_id;
        $this->ignore_office = $ignore_office;
        $this->year = $year;

        //TODO: Questo medoto dovrebbe andare nella classe MDExporter?
        try {
            $class = $this->Pscl->getExporterClass('md');
            $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);
            $folderName = $e->resultPath($year);
            $directoryTree = $this->getDirectoryTree($folderName);
            //se directoryTree è array vuoto, chiama la generazione del PSCL
            if (count($directoryTree) == 0) {
                $this->report('md', $company_id, $office_id, $survey_id, $ignore_office, $year, 999);
                $directoryTree = $this->getDirectoryTree($folderName);
            }
        } catch (CakeException $e) {
            $this->log('Classe MD non esistente', 'error');
            $directoryTree = [];
        }

        $this->set('directoryTree', $directoryTree);
        $this->viewBuilder()->setOption('serialize', ['directoryTree']);
    }

    public function upload($company_id, $office_id, $year, $pathName = '')
    {

        $this->allowRolesOnly(['moma', 'admin', 'moma_area']);
        $this->Offices = $this->getTableLocator()->get('Offices');
        $folderName = $this->Pscl->getFolderName($company_id, $office_id, $year, $pathName);
        Cache::delete("pscl-$folderName", 'long');

        if ($this->request->is('POST')) {
            $attachment = $this->request->getData('pscldoc');
            $parts = pathinfo($attachment['name']);
            $allowedExtensions = ['pdf'];
            $allowedMimeTypes = ['application/pdf'];
            $fileExtension = strtolower($parts['extension']);
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];

            if ($error != 0) {
                $success = false;
                switch ($error) {
                    case UPLOAD_ERR_INI_SIZE:
                        $msg = "Errore durante il caricamento del file $name. Il file supera la dimensione massima consentita dal server.";
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $msg = "Errore durante il caricamento del file $name. Il file supera la dimensione massima consentita dal form.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $msg = "Errore durante il caricamento del file $name. Il file è stato caricato solo parzialmente.";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $msg = 'Nessun file è stato caricato.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $msg = "Errore durante il caricamento del file $name. Cartella temporanea non trovata.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $msg = "Errore durante il caricamento del file $name. Impossibile scrivere il file su disco.";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $msg = "Errore durante il caricamento del file $name. Un'estensione di PHP ha interrotto il caricamento.";
                        break;
                    default:
                        $msg = "Errore sconosciuto durante il caricamento del file $name.";
                }

                Log::info($msg);

                $this->set('success', $success);
                $this->set('msg', $msg);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);

                return;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);
            if (!in_array($fileExtension, $allowedExtensions) || !in_array($detectedMimeType, $allowedMimeTypes)) {
                $success = false;
                $msg = 'Il file deve essere in formato PDF.';
                $this->set('success', $success);
                $this->set('msg', $msg);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);

                return;
            } else {
                $folder_path = $this->Pscl->getfolderPath($year, $company_id, $office_id, $pathName);
                $folder = new Folder($folder_path, true, 0777);
                // Sanitized (CWE-22): $name is the raw client-supplied filename -
                // see AppController::sanitizeUploadFilename(). Deterministic (no
                // uniqid), matching the existing date-prefixed short_fname naming
                // convention already relied on by the download-link/email flow below.
                $short_fname = date('Y-m-d') . '-' . $this->sanitizeUploadFilename($name, false);
                $filename = $folder_path . '/' . $short_fname;

                if (move_uploaded_file($fname, $filename)) {
                    $success = true;
                    $msg = "Il file $short_fname a stato caricato correttamente";
                    Log::info($msg);
                    //Compongo la notifica
                    $identity = $this->Authentication->getIdentity();
                    $toUser = $identity->get('email');
                    // $office = $this->Offices->findById($office_id)->contain(['Companies'])->first();
                    // use methond from usercontroler to get moma area users
                    if ($office_id == 'null') {
                        $this->Companies = $this->getTableLocator()->get('Companies');
                        $company = $this->Companies->find()->where(['Companies.id' => $company_id])->contain('Offices')->first();
                        $offices = $company->offices;
                        // $users = $this->Companies->Users->find('all')->where(['Users.role' => 'moma_area'])
                        //     ->matching('Areas', function ($q) use ($offices) {
                        //         foreach ($offices as $office) {
                        //             $q->orWhere(['OR' => [['Areas.province' => $office->province], ['Areas.city' => $office->city],
                        //             ["ST_CONTAINS(Areas.polygon,POINT('$office->lat', '$office->lon'))" ]]]);
                        //         }
                        //         return $q;
                        //         // return $q->where(['OR' => [['Areas.province' => $office->province], ['Areas.city' => $office->city],
                        //         // ["ST_CONTAINS(Areas.polygon,POINT('$office->lat', '$office->lon'))" ]]]);
                        //     });
                        $users = $this->Companies->Users->find('all')
                            ->where(['Users.role' => 'moma_area'])
                            ->matching('Areas', function ($q) use ($offices) {
                                return $q->where(function ($exp) use ($offices) {
                                    $conditions = [];
                                    foreach ($offices as $office) {
                                        $orConditions = [];
                                        if ($office->province) {
                                            $orConditions['Areas.province'] = $office->province;
                                        }
                                        if ($office->city) {
                                            $orConditions['Areas.city'] = $office->city;
                                        }
                                        if ($office->lat && $office->lon) {
                                            $orConditions[] = "ST_CONTAINS(Areas.polygon, POINT('{$office->lat}', '{$office->lon}'))";
                                        }
                                        if (!empty($orConditions)) {
                                            $conditions[] = $exp->or_($orConditions);
                                        }
                                    }

                                    return $exp->or_($conditions);
                                });
                            });
                    } else {
                        $office = $this->Offices->findById($office_id)->contain(['Companies'])->first();
                        $company = $office->company;
                        $users = $this->Offices->Companies->Users->find('all')->where(['Users.role' => 'moma_area'])
                            ->matching('Areas', function ($q) use ($office) {
                                $orConditions = [];
                                if ($office->province) {
                                    $orConditions['Areas.province'] = $office->province;
                                }
                                if ($office->city) {
                                    $orConditions['Areas.city'] = $office->city;
                                }
                                if ($office->lat && $office->lon) {
                                    $orConditions[] = "ST_CONTAINS(Areas.polygon, POINT('{$office->lat}', '{$office->lon}'))";
                                }

                                return $q->where(['OR' => $orConditions]);
                            });
                    }
                    $fname = substr($filename, strlen(WWW_ROOT));
                    $identity = $this->Authentication->getIdentity();
                    if ($pathName == 'Template_Riscontro') {
                        // get moma users from company_id
                        $momaUsers = $this->Offices->Companies->Users->find('all')->where(['company_id' => $company_id, 'role' => 'moma']);
                        // get email
                        $toUser = $momaUsers->first()->email;
                        // get token
                        $token = $momaUsers->first()->token;
                        $n = new templateRiscontroUploadNotification($toUser, $office_id, $short_fname, '', $company, $year);
                        //Invio la notifica
                        $n->toMail();
                    } elseif ($pathName == 'Decreto_Nomina') {
                        $user = new User();
                        $token = $user->getToken($identity);
                        $n = new decretoNominaUploadNotification($toUser, $office_id, $short_fname, $token, $company, $year);
                        //Invio la notifica
                        $n->toMail();
                    } elseif ($pathName == 'Altri_Documenti') {
                        $user = new User();
                        $token = $user->getToken($identity);
                        $n = new altriDocumentiUploadNotification($toUser, $office_id, $short_fname, $token, $company, $year);
                        //Invio la notifica
                        $n->toMail();
                    } else {
                        $user = new User();
                        $token = $user->getToken($identity);
                        $n = new psclUploadNotification($toUser, $office_id, $short_fname, $token, $company, $year);
                        //Invio la notifica
                        $n->toMail();
                    }

                    $users = $users->toArray();
                    foreach ($users as $user) {
                        // Un utente moma_area senza email (colonna nullable) farebbe
                        // fallire Message::validateEmail() con un 500 dopo che il file
                        // e' gia' stato salvato, bloccando anche le notifiche agli altri.
                        if (empty($user->email)) {
                            Log::warning("PSCL upload: utente {$user->id} senza email, notifica saltata");
                            continue;
                        }
                        // $users[] = $user->email;
                        if ($pathName == 'Template_Riscontro') {
                            $n = new templateRiscontroUploadNotification($user->email, $office_id, $short_fname, '', $company, $year);
                            $n->toMail();
                        } elseif ($pathName == 'Decreto_Nomina') {
                            $n = new decretoNominaUploadNotification($user->email, $office_id, $short_fname, '', $company, $year);
                            $n->toMail();
                        } elseif ($pathName == 'Altri_Documenti') {
                            $n = new altriDocumentiUploadNotification($user->email, $office_id, $short_fname, '', $company, $year);
                            $n->toMail();
                        } else {
                            $n = new psclUploadNotification($user->email, $office_id, $short_fname, $token, $company, $year);
                            $n->toMail();
                        }
                    }
                } else {
                    $success = false;
                    $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id)";
                    Log::info($msg);
                }

                $this->set('success', $success);
                $this->set('msg', $msg);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);
            }
        }
    }

    public function download($company_id, $office_id, $fname, $year = 'TUTTI', $pathName = '')
    {
        try {
            $folder_path = $this->Pscl->getfolderPath($year, $company_id, $office_id, $pathName);
            $file_path = $folder_path . DS . $fname;

            if (file_exists($file_path)) {
                return $this->response->withfile($file_path, ['download' => true]);
            }

            $this->log('File non trovato: ' . $file_path, 'error');

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'msg' => 'Impossibile trovare il file: ' . $fname,
                ]));
        } catch (\Exception $e) {
            $this->log('Errore durante il download del file: ' . $e->getMessage(), 'error');

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'msg' => 'Errore durante il download del file: ' . $e->getMessage(),
                ]));
        }
    }

    // create a function to create a zip last file from and array fo companies and offices

    public function downloadPSCLZip($year)
    {
        $zip = new ZipArchive();
        $filename = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . 'pscl.zip';
        // delete file if exists
        if (file_exists($filename)) {
            unlink($filename);
        }
        $zip->open($filename, ZipArchive::CREATE);
        $companies = $this->request->getData();
        // companies is and array of objects compose by company_id and office_id
        foreach ($companies as $company) {
            // if offices is null, then skip
            if (empty($company['offices'])) {
                continue;
            }
            foreach ($company['offices'] as $office) {
                // select last file from folder
                $folderName = $company['id'] . '/' . $office;
                $folder_path = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . $year . '/' . $folderName;
                $dir = new Folder($folder_path);
                $files = $dir->find('.*');
                if (!empty($files)) {
                    $file = $dir->find(end($files));
                    if ($file) {
                        $zip->addFile($folder_path . '/' . $file[0], $file[0]);
                    }
                }
            }
        }
        $zip->close();
        if (!is_readable($filename)) {
            throw new \Exception('Failed to create zip file');
        }

        $response = $this->response->withFile(
            $filename,
            ['download' => true, 'name' => 'companies.zip']
        );

        // debug($response);

        return $response;
    }

    public function index($company_id, $office_id, $year, $pathName = '')
    {
        $files = $this->Pscl->getFiles($company_id, $office_id, $year, $pathName);
        $this->set(compact('files'));
        $this->viewBuilder()->setOption('serialize', ['files']);
    }

    /**
     * getFile method - get a file from the filesystem in the right pscl
     * ${company_id}/${office_id}/${survey_id}/${ignore_office}/${year}/${filename}
     *
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @param mixed $ignore_office
     * @return void
     * @throws \InvalidArgumentException
     */
    public function getFile($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $filename = $this->request->getQuery('fname');
        $format = pathinfo($filename, PATHINFO_EXTENSION);
        if ($format == 'md' || $format = 'html') {
            $format = 'md';
        }
        $class = $this->Pscl->getExporterClass($format);
        $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);

        $resultPath = $e->resultPath($year);
        $file = $this->Pscl->getFile($resultPath, $filename);
        $this->set(compact('file'));
        $this->viewBuilder()->setOption('serialize', 'file');
    }

    /**
     * saveFile method - save a file in the filesystem in the right pscl
     *
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @param mixed $ignore_office
     * @return void
     * @throws \InvalidArgumentException
     */
    public function saveFile($company_id, $office_id, $survey_id, $ignore_office, $year)
    {
        $filename = $this->request->getQuery('fname');
        $format = pathinfo($filename, PATHINFO_EXTENSION);
        $class = $this->Pscl->getExporterClass($format);
        $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);

        $resultPath = $e->resultPath($year);
        $data = $this->request->getData('content');
        $file = $this->Pscl->saveFile($resultPath, $filename, $data);
        $this->set(compact('file'));
        $this->viewBuilder()->setOption('serialize', ['file']);
    }

    /**
     * Restituisce l'url dove posso trovare uno specifico file in formarto $format
     *
     * @param mixed $format md, html2, html
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @param mixed $ignore_office
     * @param mixed $year
     * @param mixed $step
     * @return void
     */
    public function getUrl($format, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)
    {
        $class = $this->Pscl->getExporterClass($format);
        $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);

        $resultPath = $e->resultPath($year);
        $resultPath = str_replace(WWW_ROOT, Router::url('/', true), $resultPath);
        $this->set('url', $resultPath);
        $this->viewBuilder()->setOption('serialize', ['url']);
    }

    //Genera il PSCL per un'azienda.
    //Se $ignore_office è true, non viene generato il PSCL per la sede ma in generale per l'azienda

    public function report($format, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)
    {
        //Controllo che $company_id, $office_id, $survey_id siano validi (esistano e siano correlati)
        // controllo dal filtro anno
        if ($year == 'null') {
            $this->Offices = $this->getTableLocator()->get('Offices');
            $office = $this->Offices->get($office_id, [
                'contain' => ['Companies'],
            ]);
            if (empty($office)) {
                throw new NotFoundException(__('Sede {0} non trovata', $office_id));
            }
            if ($office->company_id != $company_id) {
                throw new NotFoundException(__("L'ufficio {0} non appartiene all'azienda {1}", $office_id, $company_id));
            }
        }
        //Controllo i privilegi dell'utente corrente
        $this->allowWhoCanSeeCompanyOnly($company_id);

        //Controllo che il questionario esista e appartenga all'azienda
        $this->Surveys = $this->getTableLocator()->get('Surveys');
        $survey = $this->Surveys->get($survey_id);
        if (empty($survey)) {
            throw new NotFoundException(__('Il questionario {0} non esiste', $survey_id));
        }
        if ($survey->company_id != $company_id) {
            throw new NotFoundException(__("Il questionario {0} non appartiene all'azienda {1}", $survey_id, $company_id));
        }

        //Massimoi: 30/12/2023
        // ---> Questa è la vera chiamata al formattatore
        //Il penultimo parametro serve per dire agli indicatori di non considerare la sede
        //Si potrebbe mettere office_id = null, ma non funziona perchè il PSCL è legato alla sede
        //Restituisce la classe giusta per questo formattatore
        $class = $this->Pscl->getExporterClass($format);
        $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);
        //Provo prima a mettere in coda l'operazione di generazione del PSCL
        if ($step == 999 || !$this->enqueue("App\Command\GeneratePsclCommand", $format, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)) {
            //Se non sta girando beanstalk allora provo a fare in linea
            $response = $e->generatePSCL($this->response);

            return $response;
        } else {
            //Se invece sta girando beanstalk allora restituisco un messaggio di attesa
            $message = 'Il PSCL è in fase di generazione. Attendi qualche minuto';
            $this->set(compact('message'));
            $this->viewBuilder()->setOption('serialize', ['message']);
        }
    }

    public function generateXlsx($company_id, $office_id, $survey_id, $year)
    {
        // $date = new DateTime();
        // $r = $date->format('Y-m-d');

        $this->Monitorings = $this->getTableLocator()->get('Monitorings');

        // Pillars are no longer a separate model: derive axis groups (type=0
        // measures, each with its child measures) from the flat measures table.
        // The group id is the original pillar id parsed from the axis slug
        // 'pillar-<id>' so PSCL plan keys (PSCL[pillar_id]) still match.
        $pillars = $this->derivePillarsFromMeasures();

        $xlsxPath = WWW_ROOT . Configure::read('sitedir') . 'modello_misure_pscl.xlsx';
        if (!file_exists($xlsxPath)) {
            throw new NotFoundException(__('Il file modello_misure_pscl.xlsx non esiste: {0}', [$xlsxPath]));
        }
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($xlsxPath);
        $sheet = $spreadsheet->getActiveSheet();
        if ($office_id != 'null') {
            $this->Offices = $this->getTableLocator()->get('Offices');
            $office = $this->Offices->find()
                ->contain(['Companies'])
                ->where(['Offices.id' => $office_id])
                ->firstOrFail();
            $sheet->setCellValue('B1', $office->company->name);
            //il PSLC non vive più nell'office ma ha una tabella a sè
            //$PSCL = $office->PSCL;
        } else {
            $this->Companies = $this->getTableLocator()->get('Companies');
            $company = $this->Companies->find()
                ->where(['Companies.id' => $company_id])
                ->firstOrFail();
            $sheet->setCellValue('B1', $company->name);
        }

        //Leggo il PSCL dalla tabella corretta
        $conditions = ['company_id' => $company_id, 'year' => $year];
        if ($office_id == 'null') {
            $conditions['office_id IS'] = null;
        } else {
            $conditions['office_id'] = $office_id;
        }

        $pscl = $this->Pscl->find()->where($conditions)->first();

        // No PSCL plan saved for this company/office/year: nothing to export.
        // Return a clean 404 instead of fataling on ->cleanPlan() of null.
        if ($pscl === null) {
            throw new NotFoundException(__('Nessun PSCL per azienda {0} / sede {1} / anno {2}', [$company_id, $office_id, $year]));
        }

        //Pulisco il piano da eventuali misure doppie
        $pscl->cleanPlan();
        $this->Pscl->save($pscl);

        //Per retrocompatibilità devo cercare anche i monitoraggi con pscl_id null
        /*$monitorings = $this->Monitorings->find()
            ->where(['Monitorings.objective' => true]);
        if (!(empty($year) || $year == 'TUTTI')) {
            $monitorings = $monitorings->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                if ($office_id == 'null') {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                } else {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                }
            });
        } elseif ($year == 'TUTTI') {
            $monitorings = $monitorings->where(['Monitorings.office_id' => $office_id, 'pscl_id IS NULL']);
        }
        $obj = $monitorings
            ->where(['Monitorings.measure_id' => $measure->id])
            ->order(['Monitorings.dt DESC'])
            ->first();

        if (empty($obj) || empty($obj->jvalues)) {
            continue;
        } */

        //Estraggo gli obiettivi (il più recente in alto per gestire eventuali duplicati)
        $objectives = $this->Monitorings->find()
            ->where([
                'Monitorings.objective' => true,
                'Monitorings.pscl_id' => $pscl->id,
            ])
            ->orderDesc('modified')
            ->toArray();

        $monitorings = $this->Monitorings->find()
            ->where([
                'Monitorings.pscl_id' => $pscl->id,
                'Monitorings.objective' => false,
            ])
            ->orderDesc('modified')
            ->toArray();

        $PSCL = $pscl->plan;

        // Formato piatto (dal 2026): plan[1] è un array unico di voci
        // {value, pillar_id, measure_id, ...}, non più un bucket per pillar
        // (vedi CLAUDE.md / docs/dev-notes/survey-measure-reorg.md, "Flat PSCL
        // plan format"). Indicizzo per measure_id: il match resta corretto a
        // prescindere da come è cambiato pillar_id nel tempo.
        $entriesByMeasureId = [];
        foreach ((is_array($PSCL[1] ?? null) ? $PSCL[1] : []) as $entry) {
            if (is_array($entry) && isset($entry['measure_id'])) {
                $entriesByMeasureId[$entry['measure_id']] = $entry;
            }
        }

        $row = 3;
        // Configure::write('debug', 0);
        if (!empty($PSCL)) {
            foreach ($pillars as $pillar) {
                $row += 2;
                //We convert every row to a flat array
                $sheet->setCellValue("A$row", "{$pillar->id} - {$pillar->name}");
                foreach ($pillar->measures as $measure) {
                    $ms = $entriesByMeasureId[$measure->id] ?? null;
                    if (
                        is_array($ms) &&
                        isset($ms['measure_id']) &&
                        isset($ms['value']) &&
                        $ms['measure_id'] == $measure->id &&
                        !empty($ms['value'])
                    ) { // awful, store that as an object measure_id => measure !!!
                            $row++;
                            $sheet->insertNewRowBefore($row + 1);
                            $sheet->setCellValue("B$row", $measure->name);

                            $m = $measure->id;
                            $obj = Hash::extract($objectives, "{n}[measure_id=$m]");
                            $obj = !empty($obj) ? $obj[0] : null;
                        if (empty($obj) || empty($obj->jvalues)) {
                            continue;
                        }
                            $val = $obj->jvalues;
                            $emission = $measure->calculateImpactPscl($measure->id, $val);

                            $sheet->setCellValue("C$row", $this->_v($val, 'days'));
                            $sheet->setCellValue("E$row", $this->_v($val, 'users'));
                            $sheet->setCellValue("F$row", $this->_v($val, 'note'));
                            $sheet->setCellValue("G$row", $this->_v($val, 'distance'));
                            $sheet->setCellValue("H$row", intval($this->_v($val, 'days')) * intval($this->_v($val, 'users')));
                            $sheet->setCellValue("I$row", $emission['riduzione_km_gg_auto'] ?? 0);
                            $sheet->setCellValue("K$row", $this->_v($val, 'cost'));
                            $sheet->setCellValue("M$row", $this->_v($val, 'save_company'));
                            $sheet->setCellValue("O$row", intval($this->_v($val, 'save_employee')) * intval($this->_v($val, 'days')));

                            // TODO costi e risparmi
                            $sheet->setCellValue("P$row", $emission['CO2']);
                            $sheet->setCellValue("Q$row", $emission['NOx']);
                            $sheet->setCellValue("R$row", $emission['PM10']);

                            $mon = Hash::extract($monitorings, "{n}[measure_id=$m]");
                            $mon = !empty($mon) ? $mon[0] : null;
                        if (empty($mon) || empty($mon->jvalues)) {
                            continue;
                        }

                            $val_m = $mon->jvalues;

                            $sheet->setCellValue("S$row", $this->_v($val_m, 'days'));
                            $sheet->setCellValue("U$row", $this->_v($val_m, 'users'));
                            $sheet->setCellValue("V$row", $this->_v($val_m, 'note'));
                            $sheet->setCellValue("W$row", $this->_v($val_m, 'distance'));
                            $sheet->setCellValue("X$row", intval($this->_v($val_m, 'days')) * intval($this->_v($val_m, 'users')));
                            $sheet->setCellValue("Y$row", intval($this->_v($val_m, 'distance')) * intval($this->_v($val_m, 'days')) * intval($this->_v($val_m, 'users')));

                            // TODO costi e risparmi
                            $emission_m = $measure->calculateImpactPscl($measure->id, $val_m);

                            $sheet->setCellValue("Z$row", $emission_m['CO2']);
                            $sheet->setCellValue("AA$row", $emission_m['NOx']);
                            $sheet->setCellValue("AB$row", $emission_m['PM10']);
                            // $sheet->setCellValue("AD$row", $emission_m['NOx']*25.4);
                            // $sheet->setCellValue("AE$row", $emission_m['PM10']*27);
                    }
                }
            }
        }
        if ($office_id == 'null') {
            $filename = TMP . "pscl-new-{$company_id}.xls";
            $downloadName = "pscl-{$company_id}.xls";
        } else {
            $filename = TMP . "pscl-new-{$office->company->id}-{$office->id}.xlsx";
            $downloadName = "pscl-{$office->company->name}-{$office->name}.xlsx";
        }

        ob_end_clean();
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    private function _v($arr, $key, $default = 0)
    {
        if (isset($arr[$key])) {
            return $arr[$key];
        }

        return $default;
    }

    /**
     * Build pillar-like groups from the flat measures table.
     *
     * Pillars are no longer a separate model: an axis is a Measure with type=0,
     * with child measures (type 1/2) following it by `weight`. Returns a list of
     * stdClass groups shaped like the old Pillars->contain(['Measures']) result:
     * each has `id`, `name` and a `measures` array of Measure entities. The group
     * `id` is the original pillar id parsed from the axis slug ('pillar-<id>') so
     * the PSCL plan keys (PSCL[pillar_id]) still match.
     *
     * @return array<int,\stdClass>
     */
    private function derivePillarsFromMeasures(): array
    {
        $groups = [];
        try {
            $Measures = $this->getTableLocator()->get('Measures');
            $measures = $Measures->find('ordered')->toArray();
        } catch (\Exception $e) {
            \Cake\Log\Log::warning('derivePillarsFromMeasures failed: ' . $e->getMessage());

            return $groups;
        }

        $current = null;
        foreach ($measures as $m) {
            if ((int)$m->type === 0) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = new \stdClass();
                $current->id = (int)str_replace('pillar-', '', (string)$m->slug);
                $current->name = $m->name;
                $current->description = $m->description;
                $current->measures = [];
                continue;
            }
            if ($current === null) {
                $current = new \stdClass();
                $current->id = 0;
                $current->name = '';
                $current->description = '';
                $current->measures = [];
            }
            $current->measures[] = $m;
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    public function pushTo($storage, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)
    {
        //Al momento gestisco solo lo storage nextcloud, se necessario generalizzare
        if ($storage == 'nextcloud') {
            $this->enqueue("NextcloudStorage\Command\pushCommand", $storage, $company_id, $office_id, $survey_id, $ignore_office, $year, $step);
            $this->set('success', true);
            $this->set('message', 'Il PSCL è pronto per essere caricato sullo storage esterno');
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }

    public function pullFrom($storage, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)
    {
        //Al momento gestisco solo lo storage nextcloud, se necessario generalizzare
        if ($storage == 'nextcloud') {
            $this->enqueue("NextcloudStorage\Command\pullCommand", $storage, $company_id, $office_id, $survey_id, $ignore_office, $year, $step);
            $this->set('success', true);
            $this->set('message', 'Il PSCL è pronto per essere importato dallo storage esterno');
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }

    private function isBeanstalkdRunning($host = '127.0.0.1', $port = 11300)
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);

            return true; // Connection successful, Beanstalkd is running
        } else {
            return false; // Connection failed, Beanstalkd is not running
        }
    }

    /**
     * Mette in coda un task per la generazione offline dell'esportazione
     *
     * @param mixed $command
     * @param mixed $format
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @return void
     */
    public function enqueue($command, $format, $company_id, $office_id, $survey_id, $ignore_office, $year, $step)
    {
        // Connect to Beanstalkd server
        if (!$this->isBeanstalkdRunning()) {
            Log::write('info', "No beanstalk, do $command: $format without a queue for $company_id $office_id $survey_id");

            return false;
        }

        $sitedir = Configure::read('sitedir');
        $pheanstalk = Pheanstalk::create('127.0.0.1');
        $tube       = new TubeName("$sitedir-pscl-exporter");

        // Queue a Job
        $pheanstalk->useTube($tube);
        $job = $pheanstalk->put(json_encode([
            'command' => $command,
            'format' => $format,
            'company_id' => $company_id,
            'office_id' => $office_id,
            'survey_id' => $survey_id,
            'ignore_office' => $ignore_office,
            'year' => $year,
        ]));
        $this->setQueueStatus($company_id, $office_id, $survey_id, $job->getId(), $step);
        Log::write('info', "$command: $format in queue for $company_id $office_id $survey_id, job_id: {$job->getId()}");

        return true;
    }

    public function getQueueStatus($company_id, $office_id, $survey_id)
    {
        $lock = [];
        $message = [];

        $job_ids = Cache::read("beanstalk-pscl-batch-$company_id-$office_id-$survey_id", 'long');
        if (empty($job_ids)) {
            $lock = [-1, -1, -1, -1, -1];
            $message[] = 'Non eseguito';
        } else {
            $pheanstalk = Pheanstalk::create('127.0.0.1');

            foreach ($job_ids as $key => $job_id) {
                if ($job_id > 0) {
                    $jobId = new JobId($job_id);
                    try {
                        $job = $pheanstalk->peek($jobId);
                        $lock[$key] = $job_id; //Working
                        $message[$key] = 'In lavorazione';
                    } catch (Exception $e) {
                        $lock[$key] = 0; //Finished
                        $message[$key] = 'Completato';
                    }
                }
            }
        }
        $this->set('lock', $lock);
        $this->set('message', $message);
        $this->viewBuilder()->setOption('serialize', ['message', 'lock']);
    }

    public function setQueueStatus($company_id, $office_id, $survey_id, $job_id, $step)
    {
        $lock = [-1, -1, -1, -1, -1];
        $job_ids = Cache::read("beanstalk-pscl-batch-$company_id-$office_id-$survey_id", 'long');
        $job_ids[$step] = $job_id;
        foreach ($job_ids as $key => $value) {
            $lock[$key] = $job_ids[$key];
        }
        Cache::write("beanstalk-pscl-batch-$company_id-$office_id-$survey_id", $lock, 'long');
    }

    // sent email notification to moma users

    public function sentPsclNotification()
    {
        $this->allowRolesOnly(['admin']);
        try {
            $emailData = $this->request->getData();
            $toUser = new User();
            $toUser->email = $emailData['email'];
            // $toUser=Configure::read('MailAdmin');
            $subject = $emailData['notify_subject'];
            $mustacheTemplate = $emailData['notify_messagge'];
            $mustacheVars = [
                'nome' => "<b>{$emailData['name']}</b>",
                'azienda' => "<b>{$emailData['company']}</b>",
            ];
            $n = new psclNotification($toUser, $subject, $mustacheTemplate, $mustacheVars);
            $n->toMail();
            $this->set('success', true);
            $this->set('message', 'Email inviata correttamente');
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        } catch (\Exception $e) {
            $this->set('success', false);
            $this->set('message', $e->getMessage());
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }

    /**
     * Add protocol stamp to PSCL PDF file
     * Regenerates the PDF with protocol number and date stamp overlay
     *
     * @param string $year Company ID
     * @param string $office_id Office ID
     * @param string $fname Filename
     * @param string $year Protocol number from Sicra
     * @param string $protocolDate Protocol date from Sicra (format: dd/mm/yyyy hh:mm:ss)
     * @param string $year Year filter (default: 'TUTTI')
     * @return void
     */
    private function getYearCondition($year): array
    {
        if ($year === 'TUTTI') {
            return ['year IS' => null];
        }

        return ['year' => (int)$year];
    }

    private function isProtocolOverrideEnabled($company_id, $year): bool
    {
        $records = $this->Pscl->find()
            ->where(['company_id' => $company_id])
            ->where($this->getYearCondition($year))
            ->all();

        foreach ($records as $pscl) {
            if (!is_array($pscl->plan)) {
                continue;
            }

            if (!empty($pscl->plan[0]['protocol_override'])) {
                return true;
            }
        }

        return false;
    }

    private function areProtocolTasksComplete($company_id): bool
    {
        $companies = $this->getTableLocator()->get('Companies');
        $company = $companies->get($company_id, ['contain' => ['Offices']]);

        $companyComplete = $company->checkComplete();

        $officesComplete = true;
        foreach ($company->offices as $office) {
            if (!$office->checkComplete()) {
                $officesComplete = false;

                break;
            }
        }

        $originsComplete = true;
        foreach ($company->offices as $office) {
            if ($office->numEmployees() == 0 || $office->numEmployees() == null) {
                $originsComplete = false;

                break;
            }
        }

        $surveys = $this->getTableLocator()->get('Surveys');
        $survey = $surveys->find()
            ->where(['company_id' => $company_id])
            ->order(['created' => 'DESC'])
            ->first();
        $surveyComplete = !empty($survey) && $survey->checkComplete();

        return $companyComplete && $officesComplete && $originsComplete && $surveyComplete;
    }

    public function addProtocollo($company_id, $office_id, $fname, $year = 'TUTTI')
    {
        if (!$this->isProtocolOverrideEnabled($company_id, $year) && !$this->areProtocolTasksComplete($company_id)) {
            $this->set('success', false);
            $this->set('code', 'TASKS_INCOMPLETE');
            $this->set('message', 'Completare i passi da compiere prima di inviare al protocollo.');
            $this->viewBuilder()->setOption('serialize', ['success', 'code', 'message']);

            return;
        }

        $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
        if ($office_id != 'null') {
            $pscl = $pscl->where(['office_id' => $office_id]);
        } else {
            $pscl = $pscl->where(['office_id IS NULL']);
        }
        $pscl = $pscl->first();
        // get company name
        $company = $this->Pscl->Companies->find()->where(['Companies.id' => $company_id])->first();
        $company_name = $company->name;
        // get sede name
        if ($office_id != 'null') {
            $office = $this->Pscl->Offices->find()->where(['Offices.id' => $office_id])->first();
            $office_name = $office->name;
        } else {
            $office_name = 'N/A';
        }
        // check protocolNumber and protocolDate inside of pscl->plan
        if (isset($pscl->plan[0]['protocolNumber']) && isset($pscl->plan[0]['protocolDate'])) {
            $msg = [
                'success' => true,
                'message' => 'PSCL già protocollato con Numero: ' . $pscl->plan[0]['protocolNumber'] . ' Data: ' . $pscl->plan[0]['protocolDate'],
            ];
            $this->set('success', $msg['success']);
            $this->set('message', $msg['message']);

            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $folder_path = $this->Pscl->getfolderPath($year, $company_id, $office_id);

        $dir = new Folder($folder_path);
        $decodedName = urldecode($fname);
        $pattern = '^' . preg_quote($decodedName, '/') . '$';
        $files = $dir->find($pattern);

        if (empty($files)) {
            throw new NotFoundException(__('File {0} not found in {1}', [$fname, $folder_path]));
        }

        $filePath = $folder_path . DS . $files[0];

        // Check file size and reject files larger than 30MB
        $fileSize = filesize($filePath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        // Reject files larger than 30MB
        if ($fileSizeMB > 30) {
            $msg = [
                'success' => false,
                'message' => "Il file è troppo grande ({$fileSizeMB}MB). Dimensione massima: 30MB.",
            ];
            $this->set('success', $msg['success']);
            $this->set('message', $msg['message']);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // Load and encode PDF
        $pdfContent = file_get_contents($filePath);
        $base64Pdf = base64_encode($pdfContent);
        unset($pdfContent); // Free immediately

        $identity = $this->Authentication->getIdentity();
        $toUser = $identity->get('email');

        // insert protocol via SOAP (override number/date if service returns them)
        try {
            $protocollo = new \App\Protocollo\Protocollo();
            // Prepare XML data for protocol registration
            $allegatiXml = $protocollo->buildAllegatiXml($base64Pdf, $comento = 'PSCL Protocol Registration', $nomeAllegato = $fname);
            unset($base64Pdf); // Free base64 immediately after building XML

            $protocolloXml = $protocollo->buildProtocolloXml("Trasmissione PSCL 2025 - $company_name - $office_name", $identity, $allegatiXml);
            // Don't log large XML strings - causes memory issues
            Log::write('debug', 'Protocollo XML size: ' . strlen($protocolloXml) . ' bytes');

            $soapResult = $protocollo->inserisciProtocolloEAnagraficheString($protocolloXml, $allegatiXml);
            unset($allegatiXml, $protocolloXml); // Free XML strings after SOAP call

            Log::write('debug', 'Protocollo SOAP result: ' . print_r($soapResult, true));

            if (!empty($soapResult['numeroProtocollo'])) {
                $protocolNumber = (string)$soapResult['numeroProtocollo'];
            }
            if (!empty($soapResult['dataProtocollo'])) {
                try {
                    $dt = new \DateTimeImmutable((string)$soapResult['dataProtocollo']);
                    $protocolDate = $dt->format('d/m/Y H:i');
                } catch (\Throwable $e) {
                    // Fallback: use as-is
                    $protocolDate = (string)$soapResult['dataProtocollo'];
                }
            }
        } catch (\Throwable $e) {
            Log::write('error', 'Protocollo SOAP error: ' . $e->getMessage());

            // Free memory before returning
            if (isset($base64Pdf)) {
                unset($base64Pdf);
            }
            if (isset($allegatiXml)) {
                unset($allegatiXml);
            }
            if (isset($protocolloXml)) {
                unset($protocolloXml);
            }

            $msg = [
                'success' => false,
                'message' => 'Protocollo SOAP error: ' . $e->getMessage(),
            ];
            $this->set('success', $msg['success']);
            $this->set('message', $msg['message']);

            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        // $protocolNumber="274453";
        // $protocolDate="29/05/2024 10:30";
        $msgStamp = $this->addStamp($protocolNumber, $protocolDate, $filePath, null, $folder_path, $files);
        // $this->set('success', $msg['success']);
        // $this->set('message', $msg['message']);

        // $this->viewBuilder()->setOption('serialize', ['success', 'message']);

        // add protocolNumber and protocolDate inside of pscl->plan
        $pscl->plan[0]['protocolNumber'] = $protocolNumber;
        $pscl->plan[0]['protocolDate'] = $protocolDate;
        $pscl->plan[0]['protocolFileName'] = $fname;
        $pscl->setDirty('plan', true);
        // save plan
        try {
            $this->Pscl->save($pscl);
            $success = true;
        } catch (\Exception $e) {
            $success = false;
            Log::write('error', 'Failed to save PSCL plan: ' . $e->getMessage());
        }
        //Compongo la notifica

        $this->Offices = $this->getTableLocator()->get('Offices');
        // use methond from usercontroler to get moma area users
        if ($office_id == 'null') {
            $this->Companies = $this->getTableLocator()->get('Companies');
            $company = $this->Companies->find()->where(['Companies.id' => $company_id])->contain('Offices')->first();
            $offices = $company->offices;
            $users = $this->Companies->Users->find('all')
                ->where(['Users.role' => 'moma_area'])
                ->matching('Areas', function ($q) use ($offices) {
                    return $q->where(function ($exp) use ($offices) {
                        $conditions = [];
                        foreach ($offices as $office) {
                            $orConditions = [];
                            if ($office->province) {
                                $orConditions['Areas.province'] = $office->province;
                            }
                            if ($office->city) {
                                $orConditions['Areas.city'] = $office->city;
                            }
                            if ($office->lat && $office->lon) {
                                $orConditions[] = "ST_CONTAINS(Areas.polygon, POINT('{$office->lat}', '{$office->lon}'))";
                            }
                            if (!empty($orConditions)) {
                                $conditions[] = $exp->or_($orConditions);
                            }
                        }

                        return $exp->or_($conditions);
                    });
                });
        } else {
            $office = $this->Offices->findById($office_id)->contain(['Companies'])->first();
            $company = $office->company;
            $users = $this->Offices->Companies->Users->find('all')->where(['Users.role' => 'moma_area'])
                ->matching('Areas', function ($q) use ($office) {
                    $orConditions = [];
                    if ($office->province) {
                        $orConditions['Areas.province'] = $office->province;
                    }
                    if ($office->city) {
                        $orConditions['Areas.city'] = $office->city;
                    }
                    if ($office->lat && $office->lon) {
                        $orConditions[] = "ST_CONTAINS(Areas.polygon, POINT('{$office->lat}', '{$office->lon}'))";
                    }

                    return $q->where(['OR' => $orConditions]);
                });
        }
        $identity = $this->Authentication->getIdentity();
        $user = new User();
        $token = $user->getToken($identity);
        $n = new protocolloNotification($toUser, $office_id, $fname, $token, $company, $year, $protocolNumber, $protocolDate);
        //Invio la notifica
        $n->toMail();

        $users = $users->toArray();
        foreach ($users as $user) {
            // $users[] = $user->email;
            $n = new protocolloNotification($user->email, $office_id, $fname, $token, $company, $year, $protocolNumber, $protocolDate);
            $n->toMail();
        }
        $msg = [
            'success' => true,
            'message' => 'Protocollo aggiunto correttamente e notifiche inviate agli utenti MOMA area.',
        ];
        $this->set('success', $msg['success']);
        $this->set('message', $msg['message']);

        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    // add stamp

    private function addStamp($protocolNumber, $protocolDate, $filePath, $base64Pdf, $folder_path, $files)
    {
        // Note: $base64Pdf parameter kept for compatibility but not used (we read from file directly)

        // Use FPDI (TCPDF extension) to add stamp overlay to existing PDF
        try {
            // Check if FPDI is available
            if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
                throw new \Exception('FPDI library not found. Install via: composer require setasign/fpdi');
            }

            // Create a new PDF with the stamp overlay using FPDI
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator('MOMA System');
            $pdf->SetAuthor('MOMA');
            $pdf->SetTitle('PSCL with Protocol Stamp');

            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Set margins to 1
            $pdf->SetMargins(1, 1, 1);
            $pdf->SetAutoPageBreak(false, 0);

            // Import pages from original PDF
            // if error occurs here, likely the PDF is corrupted or encrypted do not import and create empty stamp PDF

            $pageCount = 1;
            $tplId = null;
            $hasError = false;

            try {
                $pageCount = $pdf->setSourceFile($filePath);
            } catch (\Throwable $e) {
                Log::write('error', 'Failed to read PDF file: ' . $e->getMessage());
                $hasError = true;
            }

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Add a page
                $pdf->AddPage();

                // Import the page from original PDF only if no error occurred
                if (!$hasError) {
                    try {
                        $tplId = $pdf->importPage($pageNo);
                        $pdf->useTemplate($tplId, 0, 0, 210, 297, true);
                    } catch (\Throwable $e) {
                        Log::write('error', 'Failed to import PDF page: ' . $e->getMessage());
                        // Continue with blank page
                    }
                }

                // Add protocol stamp on first page only
                if ($pageNo === 1) {
                    // Set blue color for stamp
                    // Set blue color for stamp
                    $pdf->SetDrawColor(10, 0, 255);      // RGB: Blue
                    $pdf->SetLineWidth(0.5);

                    // Vertical stamp dimensions (appears on left side of page)
                    $stampX = 0;      // Near left edge (A4 = 210mm)
                    $stampY = 297 / 2 - 35;       // Start vertically centered
                    $stampW = 15;       // Width of stamp box
                    $stampH = 70;      // Height of stamp box

                    // Main stamp border rectangle
                    $pdf->Rect($stampX, $stampY, $stampW, $stampH, 'D');

                    // Section heights
                    $sectionH = $stampH / 4;
                    $sectionW = $stampW - 5;

                    // ===== SECTION 1: Top (Letter "P" with blue background) =====
                    $pdf->SetFillColor(255, 255, 255); // white background
                    $pdf->Rect($stampX, $stampY, $sectionW, $sectionH, 'D');
                    // Draw "P" letter in Blue, rotated 90 degrees
                    $pdf->SetTextColor(10, 0, 255);
                    $pdf->SetFont('helvetica', 'B', 16);

                    $pdf->StartTransform();
                    $pdf->Rotate(90, $stampX + $sectionW / 2, $stampY + $sectionH / 2);
                    $pdf->SetXY($stampX + $sectionW / 2 - 2, $stampY + $sectionH / 2 - 3);
                    $pdf->Cell(5, 5, 'E', 0, 0, 'C');
                    $pdf->StopTransform();

                    // ===== SECTION 2: Middle (COMUNE BRESCIA) =====
                    $pdf->SetDrawColor(10, 0, 255);
                    $pdf->Line($sectionW, $stampY + $sectionH, $sectionW, $stampY + $stampH);

                    $pdf->SetTextColor(10, 0, 255);
                    $pdf->SetFont('helvetica', '', 10);

                    $pdf->StartTransform();
                    $pdf->Rotate(90, $stampX + $sectionW / 2, $stampY + $stampH - $sectionH / 2 - 2);
                    $pdf->SetXY($stampX + $sectionW / 2 + 3, $stampY + $stampH - $sectionH / 2 - 6);
                    $pdf->Cell(12, 3, 'COMUNE DI BRESCIA', 0, 0, 'C');
                    $pdf->SetXY($stampX + $sectionW / 2 + 4, $stampY + $stampH - $sectionH / 2 - 2);
                    $pdf->Cell(12, 3, 'Comune di Brescia - PG', 0, 0, 'C');
                    $pdf->StopTransform();

                    // ===== SECTION 3: Bottom (Protocollo info) =====
                    // $pdf->SetDrawColor(10, 0, 255);
                    // $pdf->Line($stampX, $stampY + 2 * $sectionH, $stampX + $stampW, $stampY + 2 * $sectionH);

                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(10, 0, 255);

                    $pdf->StartTransform();
                    $pdf->Rotate(90, $stampX + $sectionW / 2, $stampY + $stampH - $sectionH / 2 - 2);
                    $pdf->SetXY($stampX + $sectionW / 2 + 9, $stampY + $stampH - $sectionH / 2 + 3);
                    $pdf->Cell(30, 2, "Protocollo N. {$protocolNumber} del {$protocolDate}", 0, 0, 'C');

                    // Restore graphic state
                    $pdf->StopTransform();
                }
            }

            // Save the new PDF over a new file
            $newFilePath = $folder_path . DS . 'pscl_protocollo_' . $files[0];
            $pdf->Output($newFilePath, 'F');

            $msg = [
                'success' => true,
                'message' => 'Protocol stamp added successfully',
            ];

            return $msg;
        } catch (\Exception $e) {
            Log::write('error', 'Protocol stamp error: ' . $e->getMessage());
            $msg = [
                'success' => false,
                'message' => 'Failed to add protocol stamp: ' . $e->getMessage(),
            ];

            return $msg;
        }
    }

    // remove protocol only admin

    public function removeProtocollo($company_id, $office_id, $year = 'TUTTI')
    {
        $this->allowRolesOnly(['admin']);
        $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
        if ($office_id != 'null') {
            $pscl = $pscl->where(['office_id' => $office_id]);
        } else {
            $pscl = $pscl->where(['office_id IS NULL']);
        }
        $pscl = $pscl->first();
        // add protocolNumber and protocolDate inside of pscl->plan
        $pscl->plan[0]['protocolNumber'] = null;
        $pscl->plan[0]['protocolDate'] = null;
        $pscl->setDirty('plan', true);
        // save plan
        try {
            $this->Pscl->save($pscl);
            $success = true;
        } catch (\Exception $e) {
            $success = false;
            Log::write('error', 'Failed to save PSCL plan: ' . $e->getMessage());
        }
        $msg = [
            'success' => true,
            'message' => 'Protocollo rimosso correttamente.',
        ];
        $this->set('success', $msg['success']);
        $this->set('message', $msg['message']);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    public function getProtocolOverride($company_id, $year = 'TUTTI')
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area', 'user']);

        $protocolOverride = $this->isProtocolOverrideEnabled($company_id, $year);

        $this->set(compact('protocolOverride'));
        $this->viewBuilder()->setOption('serialize', ['protocolOverride']);
    }

    public function setProtocolOverride($company_id, $year = 'TUTTI', $enabled = '0')
    {
        $this->allowRolesOnly(['admin']);

        $protocolOverride = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        $yearCondition = $this->getYearCondition($year);
        $psclRecords = $this->Pscl->find()
            ->where(['company_id' => $company_id])
            ->where($yearCondition)
            ->all();

        if ($psclRecords->isEmpty()) {
            $saved = false;
        } else {
            $saved = true;
            foreach ($psclRecords as $pscl) {
                $plan = is_array($pscl->plan) ? $pscl->plan : [];
                if (!isset($plan[0]) || !is_array($plan[0])) {
                    $plan[0] = [];
                }
                $plan[0]['protocol_override'] = $protocolOverride;
                $pscl->plan = $plan;
                $pscl->setDirty('plan', true);
                if (!$this->Pscl->save($pscl)) {
                    $saved = false;

                    break;
                }
            }
        }

        $this->set('success', (bool)$saved);
        $this->set('protocolOverride', $protocolOverride);
        $this->set('message', $saved ? 'Flag override protocollo aggiornato.' : 'Nessun PSCL trovato per anno e azienda selezionati.');
        $this->viewBuilder()->setOption('serialize', ['success', 'protocolOverride', 'message']);
    }

    // delete file

    public function deleteFile($company_id, $office_id, $fname, $year = 'TUTTI', $pathName = '')
    {
        $this->allowRolesOnly(['admin', 'moma_area', 'moma']);
        $folder_path = $this->Pscl->getfolderPath($year, $company_id, $office_id, $pathName);

        $dir = new Folder($folder_path);
        $files = $dir->find($fname);

        if (empty($files)) {
            throw new NotFoundException(__('File {0} not found in {1}', [$fname, $folder_path]));
        }

        $filePath = $folder_path . DS . $files[0];
        try {
            unlink($filePath);
            $msg = [
                'success' => true,
                'message' => 'File eliminato correttamente.',
            ];
            $folderName = $this->Pscl->getFolderName($company_id, $office_id, $year, $pathName);
            Cache::delete("pscl-$folderName", 'long');
        } catch (\Exception $e) {
            $msg = [
                'success' => false,
                'message' => 'Errore durante l\'eliminazione del file: ' . $e->getMessage(),
            ];
        }
        $this->set('success', $msg['success']);
        $this->set('message', $msg['message']);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Save the adoption date of the PSCL
     *
     * @param int $company_id
     * @param int $office_id
     * @param int $year
     * @return void
     */
    public function saveDataAdozione($company_id, $office_id, $year)
    {
        $this->allowRolesOnly(['admin', 'moma_area', 'moma']);

        $success = false;
        $message = '';

        if ($this->request->is('post')) {
            $data_adozione = $this->request->getData('data_adozione_pscl');

            // Find or create the PSCL record
            $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
            if ($office_id != 'null' && $office_id != null) {
                $pscl = $pscl->where(['office_id' => $office_id]);
            } else {
                $pscl = $pscl->where(['office_id IS NULL']);
            }
            $pscl = $pscl->first();

            // add data_adozione to pscl->plan
            $pscl->plan[0]['data_adozione'] = $data_adozione;
            $pscl->setDirty('plan', true);

            if ($this->Pscl->save($pscl)) {
                $success = true;
                $message = 'Data di adozione salvata con successo.';
            } else {
                $success = false;
                $message = 'Errore durante il salvataggio della data di adozione.';
            }
        }

        $this->set('success', $success);
        $this->set('message', $message);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Get the adoption date of the PSCL
     *
     * @param int $company_id
     * @param int $office_id
     * @param int $year
     * @return void
     */
    public function getDataAdozione($company_id, $office_id, $year)
    {
        $this->allowRolesOnly(['admin', 'moma_area', 'moma']);

        $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
        if ($office_id != 'null' && $office_id != null) {
            $pscl = $pscl->where(['office_id' => $office_id]);
        } else {
            $pscl = $pscl->where(['office_id IS NULL']);
        }
        $pscl = $pscl->first();

        $data_adozione = $pscl && isset($pscl->plan[0]['data_adozione']) ? $pscl->plan[0]['data_adozione'] : null;

        $this->set('data_adozione', $data_adozione);
        $this->viewBuilder()->setOption('serialize', ['data_adozione']);
    }
}

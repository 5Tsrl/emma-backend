<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\User;
use App\Notification\psclUploadNotification;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Exception\CakeException;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\Routing\Router;
use InvalidArgumentException;
use Pheanstalk\Exception;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeName;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;
use App\Notification\psclNotification;

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
     * @param null $company_id a cui si riferisce il pscl
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($company_id = null, $office_id = null, $year = null)
    {
        $this->Offices = $this->getTableLocator()->get('Offices');
        $pscl = null;

        if (!empty($office_id)) {
            if ($year != 'TUTTI') {
                $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
                if ($office_id != 'null') {
                    $pscl->where(['office_id' => $office_id]);
                } else {
                    $pscl->where(['office_id IS NULL']);
                }
                $pscl = $pscl->first();
            } else {
                if ($office_id != 'null') {
                    $pscl = $this->Offices->get($office_id, [
                        'contain' => ['Companies'],
                    ]);
                }
            }

            if (empty($pscl)) {
                throw new NotFoundException('PSCL of this Office Not found');
            }
        }
        $this->set('pscl', $pscl);
        $this->viewBuilder()->setOption('serialize', ['pscl']);
    }

    /**
     * Edit method
     *
     * @param null $company_id a cui si riferisce il pscl
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($company_id = null, $office_id = null, $survey_id = null)
    {
        $this->Offices = $this->getTableLocator()->get('Offices');
        $success = true;

        $year = $this->request->getData('year');
        if ($this->request->is('post')) {
            if ($year == 'TUTTI') {
                if ($office_id != 'null') {
                    $office = $this->Offices->get($office_id, [
                        'contain' => ['Companies'],
                    ]);
                    $office->PSCL = $this->request->getData('pscl');
                    if ($this->Offices->save($office)) {
                        $success = true;
                    } else {
                        $success = false;
                    }
                } else {
                    // return pscl not possible without office_id
                    $success = false;
                }
            } else {
                $pscl = $this->Pscl->find()->where(['company_id' => $company_id, 'year' => $year]);
                if ($office_id != 'null') {
                    $pscl = $pscl->where(['office_id' => $office_id]);
                } else {
                    $pscl = $pscl->where(['office_id IS NULL']);
                }
                $pscl = $pscl->first();
                if ($pscl == null) {
                    $pscl = $this->Pscl->newEmptyEntity();
                    if ($office_id != 'null') {
                        $pscl->office_id = $office_id;
                    }
                    $pscl->company_id = $company_id;
                    $pscl->year = $year;
                }
                $pscl->survey_id = $survey_id;
                $pscl->plan = $this->request->getData('pscl');
                if ($this->Pscl->save($pscl)) {
                    $success = true;
                } else {
                    $success = false;
                }
            }
        }
        $this->set('success', $success);
        $this->viewBuilder()->setOption('serialize', ['success']);
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
            $target = $t['target'];

            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                if (strtolower($parts['extension']) == 'docx') {
                    $targetPath = WWW_ROOT . Configure::read('sitedir') . '/modello-pscl-' . $target . '.docx';
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
     * @param mixed $company_id 
     * @param mixed $office_id 
     * @param mixed $survey_id 
     * @param mixed $ignore_office 
     * @param mixed $year 
     * @return void 
     * @throws ForbiddenException 
     * @throws InvalidArgumentException 
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
            

            $class = $this->Pscl->getExporterClass('md');
            $e = new $class($company_id, $office_id, $survey_id, $ignore_office, $year);
            $folderName = $e->resultPath($year);
            $targetPath = "$folderName/images/$name";
            
            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                if (in_array(strtolower($parts['extension']), ['jpg', 'jpeg', 'png','webp','svg'])) {
                    
                    // $attachment->moveTo($targetPath);
                    move_uploaded_file($fname, $targetPath);
                    $response = [
                        'message' => 'Immagine caricata con successo'
                    ];                    
                    $this->set(compact('response'));
                    $this->viewBuilder()->setOption('serialize', ['response']);
                    return;
                    
                } else {
                    $response = [
                        'message' => 'Immagine non caricata: dev\'essere di tipo jpg, jpeg, png, svg o webp'
                    ];                    
                    $this->set(compact('response'));
                    $this->viewBuilder()->setOption('serialize', ['response']);
                    return;
                }
            }
            $response = [
                'message' => 'Immagine non caricata a causa di un errore: ' . $error
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

    public function upload($company_id, $office_id, $year)
    {

        $this->allowRolesOnly(['moma', 'admin', 'moma_area']);
        $this->Offices = $this->getTableLocator()->get('Offices');
        if ($year == 'TUTTI') {
            Cache::delete("pscl-$company_id-$office_id", 'long');
            $folderName = $company_id . '/' . $office_id;
        } else {
            if ($office_id == 'null') {
                Cache::delete("pscl-$year-$company_id", 'long');
                $folderName = $year . '/' . $company_id;
            } else {
                Cache::delete("pscl-$year-$company_id-$office_id", 'long');
                $folderName = $year . '/' . $company_id . '/' . $office_id;
            }
        }

        if ($this->request->is('POST')) {
            // $folderName = $company_id . '/' . $office_id;

            $attachment = $this->request->getData('pscldoc');
            $parts = pathinfo($attachment['name']);
            $allowedExtensions = ['pdf'];
            $allowedMimeTypes = ['application/pdf'];
            $fileExtension = strtolower($parts['extension']);
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);
            if (!in_array($fileExtension, $allowedExtensions) || !in_array($detectedMimeType, $allowedMimeTypes)) {
                $success = false;
                $msg = "Il file deve essere in formato PDF.";
                $this->set('success', $success);
                $this->set('msg', $msg);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);
                return;
            }elseif ($error != 0) {
                $success = false;
                switch ($error) {
                    case 1:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_INI_SIZE";
                        break;
                    case 2:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_FORM_SIZE";
                        break;
                    case 3:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_PARTIAL";
                        break;
                    case 4:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_NO_FILE";
                        break;
                    case 6:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_NO_TMP_DIR";
                        break;
                    case 7:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_CANT_WRITE";
                        break;
                    case 8:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id). Error type UPLOAD_ERR_EXTENSION";
                        break;
                    default:
                        $msg = "Errore durante il caricamento del file $fname ($company_id, $office_id)";
                }

                Log::info($msg);

                $this->set('success', $success);
                $this->set('msg', $msg);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);
            } else {
                $folder_path = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . $folderName;

                $folder = new Folder($folder_path, true, 0777);
                $short_fname = date('Y-m-d') . '-' . $name;
                $filename = $folder_path . '/' . $short_fname;

                if (move_uploaded_file($fname, $filename)) {
                    $success = true;
                    $msg = "Il file $filename a stato caricato correttamente";
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
                                        $conditions[] = $exp->or_([
                                            ['Areas.province' => $office->province],
                                            ['Areas.city' => $office->city],
                                            ["ST_CONTAINS(Areas.polygon, POINT('$office->lat', '$office->lon'))"],
                                        ]);
                                    }

                                    return $exp->or_($conditions);
                                });
                            });
                    } else {
                        $office = $this->Offices->findById($office_id)->contain(['Companies'])->first();
                        $company = $office->company;
                        $users = $this->Offices->Companies->Users->find('all')->where(['Users.role' => 'moma_area'])
                            ->matching('Areas', function ($q) use ($office) {
                                return $q->where(['OR' => [
                                    ['Areas.province' => $office->province],
                                    ['Areas.city' => $office->city],
                                    ["ST_CONTAINS(Areas.polygon,POINT('$office->lat', '$office->lon'))"],
                                ]]);
                            });
                    }
                    $fname = substr($filename, strlen(WWW_ROOT));
                    $identity = $this->Authentication->getIdentity();
                    $user = new User();
                    $token = $user->getToken($identity);
                    $n = new psclUploadNotification($toUser, $office_id, $short_fname, $token, $company, $year);
                    //Invio la notifica
                    $n->toMail();

                    $users = $users->toArray();
                    foreach ($users as $user) {
                        // $users[] = $user->email;
                        $n = new psclUploadNotification($user->email, $office_id, $short_fname, $token, $company, $year);
                        $n->toMail();
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

    public function download($company_id, $office_id, $fname, $year = 'TUTTI')
    {
        // $folderName = $company_id . '/' . $office_id;
        if ($year == 'TUTTI') {
            Cache::delete("pscl-$company_id-$office_id", 'long');
            $folderName = $company_id . '/' . $office_id;
        } else {
            if ($office_id == 'null') {
                Cache::delete("pscl-$year-$company_id", 'long');
                $folderName = $year . '/' . $company_id;
            } else {
                Cache::delete("pscl-$year-$company_id-$office_id", 'long');
                $folderName = $year . '/' . $company_id . '/' . $office_id;
            }
        }

        $folder_path = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . $folderName;
        $dir = new Folder($folder_path);
        $file = $dir->find($fname);
        if ($file) {
            return $this->response->withfile($folder_path . '/' . $file[0], ['download' => true]);
        }
        throw new NotFoundException('Impossibile trovare il file', 1);
    }

    // create a function to create a zip last file from and array fo companies and offices

    public function downloadPSCLZip($year)
    {
        $zip = new ZipArchive();
        $filename = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . 'pscl.zip';
        // delete file if exists
        if (file_exists($filename))
            unlink($filename);
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
                $folder_path = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' .$year.'/'. $folderName;
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

    public function index($company_id, $office_id, $year)
    {
        $files = $this->Pscl->getFiles($company_id, $office_id, $year);
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
                throw new NotFoundException("Sede $office_id non trovata");
            }
            if ($office->company_id != $company_id) {
                throw new NotFoundException("L'ufficio $office_id non appartiene all'azienda $company_id");
            }
        }
        //Controllo i privilegi dell'utente corrente
        $this->allowWhoCanSeeCompanyOnly($company_id);

        //Controllo che il questionario esista e appartenga all'azienda
        $this->Surveys = $this->getTableLocator()->get('Surveys');
        $survey = $this->Surveys->get($survey_id);
        if (empty($survey)) {
            throw new NotFoundException("Il questionario $survey_id non esiste");
        }
        if ($survey->company_id != $company_id) {
            throw new NotFoundException("Il questionario $survey_id non appartiene all'azienda $company_id");
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

        $this->Pillars = $this->getTableLocator()->get('Pillars');
        $this->Monitorings = $this->getTableLocator()->get('Monitorings');

        $pillars = $this->Pillars->find()
            ->contain(['Measures'])
            ->toArray();

        $spreadsheet = IOFactory::load(WWW_ROOT . Configure::read('sitedir') .  '/modello_misure_pscl.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        if ($office_id != 'null') {
            $this->Offices = $this->getTableLocator()->get('Offices');
            $office = $this->Offices->find()
                ->contain(['Companies'])
                ->where(['Offices.id' => $office_id])
                ->firstOrFail();
            $sheet->setCellValue('B1', $office->company->name);
            $PSCL = $office->PSCL;
        } else {
            $this->Companies = $this->getTableLocator()->get('Companies');
            $company = $this->Companies->find()
                ->where(['Companies.id' => $company_id])
                ->firstOrFail();
            $sheet->setCellValue('B1', $company->name);
        }
        $monitorings = $this->Monitorings->find()
            ->where(['Monitorings.objective' => true]);
        if (!(empty($year) || $year == 'TUTTI')) {
            $monitorings = $monitorings->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                if ($office_id == 'null') {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                } else {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                }
            });

            $PSCL = $monitorings->first();
            if (isset($PSCL->_matchingData['Pscl'])) {
                $PSCL = $PSCL->_matchingData['Pscl']->plan;
            } else {
                $PSCL = [];
            }
        }

        $row = 3;
        Configure::write('debug', 0);

        foreach ($pillars as $pillar) {
            $row += 2;
            //We convert every row to a flat array
            $sheet->setCellValue("A$row", "{$pillar->id} - {$pillar->name}");
            foreach ($pillar->measures as $measure) {
                foreach ($PSCL[$pillar->id] as $ms) {
                    if ($ms['measure_id'] == $measure->id && $ms['value']) { // awful, store that as an object measure_id => measure !!!
                        $row++;
                        $sheet->insertNewRowBefore($row + 1);
                        $sheet->setCellValue("B$row", $measure->name);

                        $monitorings = $this->Monitorings->find()
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
                        }

                        $val = $obj->jvalues;
                        $emission = $measure->calculateImpactPscl($measure->id, $val);

                        $sheet->setCellValue("C$row", $this->_v($val, 'days'));
                        $sheet->setCellValue("E$row", $this->_v($val, 'users'));
                        $sheet->setCellValue("F$row", $this->_v($val, 'note'));
                        $sheet->setCellValue("G$row", $this->_v($val, 'distance'));
                        $sheet->setCellValue("H$row", $this->_v($val, 'days') * $this->_v($val, 'users'));
                        $sheet->setCellValue("I$row", $emission['riduzione_km_gg_auto'] ?? 0);
                        $sheet->setCellValue("K$row", $this->_v($val, 'cost'));
                        $sheet->setCellValue("M$row", $this->_v($val, 'save_company'));
                        $sheet->setCellValue("O$row", $this->_v($val, 'save_employee') * $this->_v($val, 'days'));

                        // TODO costi e risparmi


                        $sheet->setCellValue("P$row", $emission['CO2']);
                        $sheet->setCellValue("Q$row", $emission['NOx']);
                        $sheet->setCellValue("R$row", $emission['PM10']);

                        $monitorings_m = $this->Monitorings->find()
                            ->where(['Monitorings.measure_id' => $measure->id])
                            // ->where(['Monitorings.office_id' => $office_id])
                            // ->where(['Monitorings.survey_id' => $survey_id])
                            ->where(['Monitorings.objective' => false]);

                        if (!(empty($year) || $year == 'TUTTI')) {
                            $monitorings_m = $monitorings_m->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                                if ($office_id == 'null') {
                                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                                } else {
                                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                                }
                            });
                        } elseif ($year == 'TUTTI') {
                            $monitorings_m = $monitorings_m->where(['Monitorings.office_id' => $office_id, 'pscl_id IS NULL']);
                        }
                        $obj_m = $monitorings_m->order(['Monitorings.dt DESC'])
                            ->first();

                        if (empty($obj_m) || empty($obj_m->jvalues)) {
                            continue;
                        }

                        $val_m = $obj_m->jvalues;

                        $sheet->setCellValue("S$row", $this->_v($val_m, 'days'));
                        $sheet->setCellValue("U$row", $this->_v($val_m, 'users'));
                        $sheet->setCellValue("V$row", $this->_v($val_m, 'note'));
                        $sheet->setCellValue("W$row", $this->_v($val_m, 'distance'));
                        $sheet->setCellValue("X$row", $this->_v($val_m, 'days') *  $this->_v($val_m, 'users'));
                        $sheet->setCellValue("Y$row", $this->_v($val_m, 'distance') * $this->_v($val, 'days') * $this->_v($val_m, 'users'));

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
            $filename = TMP . "pscl-new-{$company_id}.xlsx";
        } else {
            $filename = TMP . "pscl-new-{$office->company->id}-{$office->id}.xlsx";
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);

        // Return response object to prevent controller from trying to render
        // a view.
        if ($office_id == 'null') {
            return $this->response->withFile(
                $filename,
                [
                    'download' => false,
                    'name' => "PSCL-{$company->name}.xlsx",

                ]
            );
        } else {
            return $this->response->withFile(
                $filename,
                [
                    'download' => false,
                    'name' => "PSCL-{$office->company->name}-{$office->name}.xlsx",
                ]
            );
        }
    }

    private function _v($arr, $key, $default = 0)
    {
        if (isset($arr[$key])) {
            return $arr[$key];
        }

        return $default;
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
    {   $this->allowRolesOnly(['admin']);
        try{
            $emailData = $this->request->getData();
            $toUser = new User();
            $toUser->email=$emailData['email'];
            // $toUser=Configure::read('MailAdmin');
            $subject=$emailData['notify_subject'];
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
        }catch(\Exception $e){
            $this->set('success', false);
            $this->set('message', $e->getMessage());
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }



}

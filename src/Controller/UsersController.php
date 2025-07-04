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


namespace App\Controller;

use App\Model\Entity\User;
use Cake\Core\Configure;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Exception;
use Notifications\Notification\forgotPasswordNotification;
use Cake\Cache\Cache;
use Cake\Log\Log;



class UsersController extends AppController
{
    /**
     * Initialize.
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['login', 'logout', 'requestResetPassword', 'register', 'organizations', 'translators', 'changePassword', 'whoami']);
        $this->loadComponent('Paginator');
    }

    public function index()
    {
        $identity = $this->Authentication->getIdentity();
        $myRole = $identity->get('role');
        $conditions = [];

        $role = $this->request->getQuery('role');
        if (!empty($role)) {
            $conditions['Users.role'] = $role;
        }

        $office_id = $this->request->getQuery('office_id');
        if (!empty($office_id)) {
            $conditions['Users.office_id'] = $office_id;
        }

        $company_id = $this->request->getQuery('company_id');
        //L'utente con company_id può vedere solo la sua azienda
        if ($identity->get('company_id')) {
            $company_id = $identity->get('company_id');
        }
        if (!empty($company_id)) {
            $conditions['Users.company_id'] = $company_id;
        }
        $survey_id = $this->request->getQuery('survey_id');
        if (!empty($survey_id)) {
            $conditions['SurveyParticipants.survey_id'] = $survey_id;
        }
        $search = $this->request->getQuery('search');
        if (!empty($search)) {
            $conditions['or'] = [
                ['CONCAT(first_name, " ", last_name) LIKE' => "%$search%"],
                ['Users.email LIKE' => "%$search%"],
            ];
        }
        $geocoded = $this->request->getQuery('geocoded');
        if (!empty($geocoded)) {
            $conditions['geocoded'] = $geocoded;
        }

        if ($identity->get('subcompany')) {
            $subcompany = $identity->get('subcompany');
            $conditions['subcompany'] = $subcompany;
        }
        $year = $this->request->getQuery('year');

        //L'utente user non può avere la lista degli utenti
        if ($myRole != 'user') {
            $user_q = $this->Users->index($conditions, $year);
        }

        try {
            $users = $this->paginate($user_q);
        } catch (NotFoundException $e) {
            // Do something here like redirecting to first or last page.
            // $this->request->getAttribute('paging') will give you required info.
            $users = [];
        }
        $pagination = $this->Paginator->getPagingParams();
        $this->set(compact('users', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['users', 'pagination']);
    }

    //TODO: l'utente deve poter modificare il suo profilo, gli altri ruoli anche i profili altrui
    //TODO: assicurarsi che non cancelli la password

    public function edit($user_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        // Log the login activity
        $ipAddress = $this->request->clientIp();
        $userAgent = $this->request->getHeaderLine('User-Agent');
        // url
        $url = $this->request->getHeaderLine('Referer');
        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            $message = sprintf(
                'User %s (ID: %d) with role %s from URL %s, IP %s, ',
                $identity->username,
                $identity->id,
                $identity->role,
                $url,
                $ipAddress,
            );
        }else{
                $message = sprintf(
                    'Logged out url %s from IP %s, ',
                    $url,
                    $ipAddress
                );
            }
        $user = $this->Users->findById($user_id)
            ->first();

        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            $identity = $this->Authentication->getIdentity();
            // Only superuser can set is_superuser=true
            if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
                $dt['is_superuser'] = false;
            }
            if (empty($d['passsword'])) {
                unset($dt['password']);
            }
            $user = $this->Users->patchEntity($user, $dt);
            // role change
            if ($user->role !== $dt['ruolo'] && $identity->role == 'admin') {
                $user->role = $dt['ruolo'];
                $message = $message.'Ruolo cambiato da ' . $user->role . ' a ' . $dt['ruolo'].', per il utenti con email: '.$user->email.' ';
            }
            //Campi con nomi sbagliati
            $user->first_name = $dt['nome'];
            $user->last_name = $dt['cognome'];
            
            // $user->years = json_encode($dt['years']);
            

            if ($this->Users->save($user)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The user has been saved.'));
                    Log::write('info', $message.'Utente salvato con successo', ['scope' => ['permits']]);
                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg = 'User info saved';
                    Log::write('info', $message.$msg, ['scope' => ['permits']]);
                    $this->set(compact('msg'));
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('User info cannot be saved. Please, try again.'));
                    Log::write('info', $message.'Utente non salvato', ['scope' => ['permits']]);
                } else {
                    $msg = 'Impossibile salvare';
                    Log::write('info', $message.$msg, ['scope' => ['permits']]);
                    throw new InternalErrorException($msg);
                }
            }
        }

        if (!$this->request->is('json')) {
            $users = $this->Users->find('list', ['limit' => 200]);
            $this->set(compact('users'));
            $this->viewBuilder()->setOption('serialize', ['users']);
        }

        $this->set(compact('user'));
        $this->viewBuilder()->setOption('serialize', ['user']);
    }

    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->find('all', [
                            'conditions' => ['id' => $id],
                        ])->contain('Batches')->first();
        // delete batch row
        $this->Users->Batches->delete($user->batches[0]);
        // Log the permits activity delete user
        // url
        $url = $this->request->getHeaderLine('Referer');
        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            $message = sprintf(
                'User %s (ID: %d) with role %s from URL %s, user %s:',
                $identity->username,
                $identity->id,
                $identity->role,
                $url,
                $user->username
            );
        }else{
                $message = sprintf(
                    'url %s from IP %s, user %s: ',
                    $url,
                    $user->username
                );
            }

        if ($this->Users->delete($user)) {
            if (!$this->request->is('json')) {
                $this->Flash->success(__('The User has been deleted.'));
                Log::write('info', $message.'Utente cancellato con successo', ['scope' => ['permits']]);
                return $this->redirect(['action' => 'index']);
            } else {
                $msg = 'The User has been deleted.';
                Log::write('info', $message.$msg, ['scope' => ['permits']]);
                $this->set(compact('msg'));
                $this->viewBuilder()->setOption('serialize', ['msg']);
            }
        } else {
            if (!$this->request->is('json')) {
                $this->Flash->error(__('The User could not be deleted. Please, try again.'));
                Log::write('info', $message.'Utente non cancellato', ['scope' => ['permits']]);
            } else {
                $msg = 'User not deleted';
                Log::write('info', $message.$msg, ['scope' => ['permits']]);
                throw new InternalErrorException($msg);
            }
        }

        if (!$this->request->is('json')) {
            return $this->redirect(['action' => 'index']);
        }
    }

    public function whoami()
    {
        $this->Authorization->skipAuthorization();
        $identity = $this->Authentication->getIdentity();
        //$identity = $this->getRequest()->getAttribute('identity');
        // Log the login activity
        $ipAddress = $this->request->clientIp();
        $userAgent = $this->request->getHeaderLine('User-Agent');
        // url
        $url = $this->request->getHeaderLine('Referer');
        if (is_null($identity)) {
            $request = $this->getRequest();
            $message = sprintf(
                'Email %s try logged in url %s from IP %s with user agent %s ',
                $request->getdata()['email'],
                $url,
                $ipAddress,
                $userAgent
                
            );
            Log::write('info', "Utente non autenticato, puoi accedere di nuovo?".$message, ['scope' => ['login']]);
            throw new UnauthorizedException('Utente non autenticato, puoi accedere di nuovo?');
        } else {
            $message = sprintf(
                'User %s (ID: %d) with role %s logged in from URL %s, IP %s, User Agent %s',
                $identity->username,
                $identity->id,
                $identity->role,
                $url,
                $ipAddress,
                $userAgent
            );
            Log::write('info', $message, ['scope' => ['login']]);
            $id = $identity->get('id');
            $user = $this->Users->findById($id)
                ->select([
                    'id', 'email', 'first_name', 'last_name', 'role', 'company_id', 'username', 'tos_date',
                    'mobile', 'cf', 'badge_number',
                ])
                ->first();
        }
        $this->set(compact('user'));
        $this->viewBuilder()->setOption('serialize', 'user');
    }

    public function getMySurvey(): void
    {
        $identity = $this->Authentication->getIdentity();
        //$identity = $this->getRequest()->getAttribute('identity');
        if (is_null($identity)) {
            throw new UnauthorizedException('Utente non autenticato, puoi accedere di nuovo?');
        }

        $id = $identity->get('id');
        $this->loadModel('SurveyParticipants');

        $survey = $this->SurveyParticipants->getLatestSurveyForUser($id);

        $data = [
            'participant_id' => $survey->id,
            'survey_id' => $survey->survey_id,
        ];

        $this->set(compact('data'));
        $this->viewBuilder()->setOption('serialize', 'data');
    }

    public function login()
    {
        // $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $request = $this->getRequest();
            $service = $request->getAttribute('authentication');
            if (!$service) {
                throw new \UnexpectedValueException('Authentication service not found in this request');
                Log::write('info', 'Authentication service not found in this request', ['scope' => ['login']]);
            }

            $result = $service->getResult();
            // Log the login activity
            $ipAddress = $this->request->clientIp();
            $userAgent = $this->request->getHeaderLine('User-Agent');
            // url
            $url = $this->request->getHeaderLine('Referer');
            if ($result->isValid()) {
                $entity = $result->getData();

                $identity = $entity->get('id');
                $user = new User();
                $token = $user->getToken($identity);
                $this->set('data', $token);
                $this->viewBuilder()->setOption('serialize', ['data']);
                $message = sprintf(
                    'User %s (ID: %d) with role %s logged in url %s from IP %s with user agent %s ',
                    $entity->username,
                    $entity->id,
                    $entity->role,
                    $url,
                    $ipAddress,
                    $userAgent
                    
                );
                if (!$this->getRequest()->is('json')) {
                    $r = $this->request->getQuery('redirect');
                    if (!empty($r)) {
                        Log::write('info', "Redirecting to $r".$message, ['scope' => ['login']]);
                        return $this->redirect($r);
                    } else {
                        if ($entity->role != 'admin') {
                            $this->Flash->error('Solo gli amministratori possono accedere a questa sezione');
                            $entity_status = json_encode($entity);
                            
                            
                            Log::write('info', "Solo gli amministratori possono accedere a questa sezione: $message $entity_status", ['scope' => ['login']]);
                            return $this->redirect('/users/login');
                        } else {
                            Log::write('info', 'Redirecting to /', ['scope' => ['login']]);
                            Log::write('info', $message, ['scope' => ['login']]);
                            return $this->redirect('/');
                        }
                    }
                    Log::write('info', 'error row 268 userController'.$message, ['scope' => ['login']]);
                    return;
                }
                
                Log::write('info', $message, ['scope' => ['login']]);
            } else {
                $message = sprintf(
                    'Email %s try logged in url %s from IP %s with user agent %s ',
                    $request->getdata()['email'],
                    $url,
                    $ipAddress,
                    $userAgent
                    
                );
                $list = $result->getErrors();
                $e = '';
                foreach ($list as $l) {
                    $r = $l;
                    if (count($r) > 0) {
                        $e .= ' ' . $this->recursive_implode($r, ', ');
                    }
                }
                Log::write('info', "Utente e/o password sbagliati: $e ".$message, ['scope' => ['login']]);
                throw new UnauthorizedException("Utente e/o password sbagliati: $e");
            }
        }
    }

    /**
     * Recursively implodes an array with optional key inclusion
     *
     * Example of $include_keys output: key, value, key, value, key, value
     *
     * @access  public
     * @param   array   $array         multi-dimensional array to recursively implode
     * @param   string  $glue          value that glues elements together
     * @param   bool    $include_keys  include keys before their values
     * @param   bool    $trim_all      trim ALL whitespace from string
     * @return  string  imploded array
     */
    public function recursive_implode(array $array, $glue = ',', $include_keys = false, $trim_all = true)
    {
        $glued_string = '';

        // Recursively iterates array and adds key/value to glued string
        array_walk_recursive($array, function ($value, $key) use ($glue, $include_keys, &$glued_string) {
            $include_keys and $glued_string .= $key . $glue;
            $glued_string .= $value . $glue;
        });

        // Removes last $glue from string
        strlen($glue) > 0 and $glued_string = substr($glued_string, 0, -strlen($glue));

        // Trim ALL whitespace
        $trim_all and $glued_string = preg_replace("/(\s)/ixsm", '', $glued_string);

        return (string)$glued_string;
    }

    public function changePassword(): void
    {
        
        $token = $this->request->getQuery('token');
        $identity = $this->Authentication->getIdentity();
        if (is_null($identity)) {
            throw new UnauthorizedException('Utente non autenticato');
        }
        $form_data = $this->request->input('json_decode');
        $usertoken = new User();
        if(!Empty($form_data->userId) && $form_data->userId != $identity->get('token')){
            $user = $this->Users->findById($form_data->userId)
            ->first();
            if(empty($user)){
                if(empty($identity->get('token'))){
                    throw new UnauthorizedException('Token non valido');
                }else{
                    throw new NotFoundException("L'utente richiesto non esiste");
                }
            }
        }else{
            $user = $this->Users->findById($identity->get('id'))
            ->first();
            if($token != $user->token){
                throw new UnauthorizedException('Token non valido, richiedi un nuovo reset della password');
            }elseif (time() > $user->token_expires->getTimestamp()) {
                throw new UnauthorizedException('Token scaduto, richiedi un nuovo reset della password');
            }

        }

        if (empty($user)) {
            throw new NotFoundException("L'utente richiesto non esiste");
        }
        if ($this->request->is(['patch', 'post', 'put'])) {
            $id = $identity->get('id');
            $myRole = $identity->get('role');

            //O sono moma o admin, oppure sono me stesso
            if ($user->id == $id || in_array($myRole, ['moma', 'admin'])) {
                
                if ($form_data->pwd == $form_data->pwd2) {
                    $user->password = $form_data->pwd;
                    $user->token = null; // reset token after password change
                    $user->token_expires = null; // reset token_expires after password change
                    if ($this->Users->save($user)) {
                        if ($this->request->isJson()) {
                            $msg = "Password salvata con successo per l'utente {$user->username}";
                            $this->set(compact('msg'));
                            $this->viewBuilder()->setOption('serialize', ['msg']);
                        } else {
                            $this->Flash->success('password cambiata con successo');
                            $this->redirect(['controller' => 'Users', 'action' => 'index']);
                        }
                    } else {
                        throw new Exception('Impossibile salvare la nuova password');
                    }
                }else{
                    throw new Exception('Le password non corrispondono');
                }
            }
        }

        $this->set(compact('user'));
    }

    public function requestResetPassword()
    {
        //$this->autoRender = false;
        $email = $this->request->getData('email');
        $referer = $this->referer('/', false);

        //Cerco nel db se c'è uno user con questa mail, se no fallisco
        try {
            $user = $this->Users->findByEmail($email)->firstOrFail();
        } catch (\Throwable $e) {
            throw new NotFoundException("Nessun utente con la mail: $email");
        }

        //Se c'è uno user con questa mail preparo una notifica allo user con il token a changePassword        
        $data = $user->requestResetPassword($user, $referer);
        $msg = $data['msg'];
        // save user with token and token_expires
        $user->token = $data['token'];
        $user->token_expires = date("Y-m-d H:i:s",time() + MINUTE * 20); // 20 minutes from now
        if (!$this->Users->save($user)) {
            throw new InternalErrorException('Impossibile salvare il token per il reset della password');
        }
        $this->set(compact('msg'));
        $this->set('_serialize', ['msg']);
    }

    public function import()
    {
        $this->allowRolesOnly(['admin']);
        //TODO: Rendere parametrico
        $pwd_prefix = 'orariscuole';

        //Leggo i dati del form
        $users = $this->request->getData('users');
        $role = $this->request->getData('role');

        //Ogni riga un indirizzo email
        $user_rows = explode(PHP_EOL, $users);
        $msg = '';
        $identity = $this->Authentication->getIdentity();
        foreach ($user_rows as $u) {
            $parts = explode('@', $u);
            $pwd = $pwd_prefix . '-' . strtoupper($parts[0]);
            $existingUser = $this->Users->findByEmail($u)->first();

            $userData = [
                'username' => $u,
                'email' => $u,
                'password' => $pwd,
                'role' => $role,
                'active' => true,
            ];
            // Only superuser can create another superuser
            if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
                $userData['is_superuser'] = false;
            } else if ($this->request->getData('is_superuser')) {
                $userData['is_superuser'] = true;
            }
            if (empty($existingUser)) {
                $user = $this->Users->newEntity($userData);
                $action = 'creato';
            } else {
                $user = $this->Users->patchEntity($existingUser, $userData);
                $action = 'aggiornato';
            }
            if ($this->Users->save($user)) {
                $msg .= "$u $action con successo" . PHP_EOL;
            } else {
                $msg .= ("$u ERRORE durante la creazione");
            }
        }

        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function logout()
    {
        $identity = $this->Authentication->getIdentity();
        $ipAddress = $this->request->clientIp();
        $userAgent = $this->request->getHeaderLine('User-Agent');
        $url = $this->request->getHeaderLine('Referer');
        $request = $this->getRequest();
        if ($identity) {
            $message = sprintf(
                'User %s (ID: %d) with role %s logged out from URL %s, IP %s, User Agent %s',
                $identity->username,
                $identity->id,
                $identity->role,
                $url,
                $ipAddress,
                $userAgent
            );
            Log::write('info', $message, ['scope' => ['login']]);
        }else{
            // $identity=$this->Authentication->getIdentity();
            $message = sprintf(
                'Logged out url %s from IP %s with user agent %s ',
                // $request->getdata()['email'],
                $url,
                $ipAddress,
                $userAgent
            );
            Log::write('info', $message, ['scope' => ['login']]);
        }

        $this->Authentication->logout();
        if (!$this->request->is('json')) {
            return $this->redirect(['controller' => 'Users', 'action' => 'login']);
        }
        $msg = 'Logout con successo';
        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function add()
    {
        $this->allowRolesOnly(['admin']);
        $u = $this->request->getData();
        $u['username'] = $u['email'];
        $u['active'] = 1;
        $identity = $this->Authentication->getIdentity();
        // Only superuser can create another superuser
        if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
            $u['is_superuser'] = false;
        }
        $user = $this->Users->newEntity($u);
        // Log the permits activity add user
        // url
        $url = $this->request->getHeaderLine('Referer');
        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            $message = sprintf(
                'User %s (ID: %d) with role %s from URL %s,',
                $identity->username,
                $identity->id,
                $identity->role,
                $url,
            );
        }else{
                $message = sprintf(
                    'url %s from IP %s,',
                    $url,
                );
            }

        $msg = '';
        if ($this->Users->save($user)) {
            $msg .= "{$u['email']} creato con successo" . PHP_EOL;
            Log::write('info', $message.$msg, ['scope' => ['permits']]);
        } else {
            $msg .= ("{$u['email']} ERRORE durante la creazione");
            Log::write('info', $message.$msg, ['scope' => ['permits']]);
        }

        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function updateTos()
    {
        $identity = $this->Authentication->getIdentity();
        if (is_null($identity)) {
            throw new UnauthorizedException('Utente non autenticato');
        } else {
            $id = $identity->get('id');
            $user = $this->Users->findById($id)->first();
            $user->tos_date = date('Y-m-d');
            if ($this->Users->save($user)) {
                $msg = 'Utente salvato con successo';
            } else {
                $msg = "Impossibile aggiornare il termini per l'utente";
            }
        }
        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function getOrigin($id = null)
    {
        $user = $this->Users->get($id);

        $origin = $this->Users->find()->innerJoinWith('Origins')
            ->select()
            ->contain(['Origins'])
            ->where(['Origins.user_id' => $user->id]);

        $this->set(compact('origin'));
        $this->viewBuilder()->setOption('serialize', ['origin']);
    }

    public function getGuide()
    {
        $url = Configure::read('sitedir') . '/guida.pdf';
        $response = $this->response->withFile($url, ['download' => true, 'name' => 'guida.pdf']);
        // Return the response to prevent controller from trying to render
        // a view.
        return $response;
    }

    public function uploadGuide()
    {
        if ($this->request->is('POST')) {
            $t = $this->request->getData();
            $attachment = $this->request->getData('file');

            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);

            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                if (strtolower($parts['extension']) == 'pdf') {
                    if ($detectedMimeType != 'application/pdf') {
                        $this->Flash->error('Il file caricato non è un PDF valido');
                        return;
                    }
                    $targetPath = WWW_ROOT . Configure::read('sitedir') . '/guida.pdf';
                    move_uploaded_file($fname, $targetPath);
                    $this->Flash->success('Documento caricato con successo');

                    return;
                } else {
                    $this->Flash->error('Il documento non è stato caricato: dev\'essere di tipo pdf');

                    return;
                }
            }
            $this->Flash->error('Il documento non è stato caricato a causa di un errore: ' . $error);

            return;
        }
    }

    // create a method that change username, email, firist_mname and last_name to anonimus  for all users with role user

    public function anonymize($company_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        //Se la richiesta è in post
        if ($this->request->is('post')) {
            $company_id = $this->request->getData('company_id');
            $count_users = $this->Users->anonymize($company_id);
            $msg = $count_users . ' utenti anonimizzati con successo';
            $this->Flash->success($msg);
            $this->redirect(['controller' => 'Users', 'action' => 'anonymize', $company_id]);
        }

        //Se la richiesta è in get non faccio nulla
        if ($this->request->is('get')) {
            $msg = 'Attenzione Operazione irreversibile. Verranno cancellati tutti i dati relativi agli utenti nel database e sostituiti con dati anonimi.';
        }

        $companies = $this->Users->Companies->find('list', ['limit' => 2000, 'order' => ['name' => 'ASC'], 'empty' => 'Tutte le aziende']);
        $this->set(compact('companies'));
        $this->set(compact('msg'));
        $this->set(compact('company_id'));
        $this->viewBuilder()->setOption('serialize', ['msg','company_id']);
    }

    public function uploadFaq()
    {
        if ($this->request->is('POST')) {
            $t = $this->request->getData();
            $attachment = $this->request->getData('file');

            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);

            if ($error == 0) {
                // $name = $attachment->getClientFilename();
                $parts = pathinfo($name);
                if (strtolower($parts['extension']) == 'pdf') {
                    if ($detectedMimeType != 'application/pdf') {
                        $this->Flash->error('Il file caricato non è un PDF valido');
                        return;
                    }
                    $targetPath = WWW_ROOT . Configure::read('sitedir') . '/faq.pdf';
                    move_uploaded_file($fname, $targetPath);
                    $this->Flash->success('Documento caricato con successo');

                    return;
                } else {
                    $this->Flash->error('Il documento non è stato caricato: dev\'essere di tipo pdf');

                    return;
                }
            }
            $this->Flash->error('Il documento non è stato caricato a causa di un errore: ' . $error);

            return;
        }
    }

    public function getFaq()
    {
            $url = Configure::read('sitedir') . '/faq.pdf';
            $response = $this->response->withFile($url, ['download' => true, 'name' => 'guida.pdf']);
            // Return the response to prevent controller from trying to render
            // a view.
            return $response;
    }

    public function find($id)
    {
        // $user = $this->Users->find()->contain(['Employees'])
        // ->where(['Users.id' => $id])
        //     ->first();
            $user = $this->Users->get($id, [
                'contain' => ['Employees','Origins'],
            ]);
        $this->set(compact('user'));
        $this->viewBuilder()->setOption('serialize', ['user']);
    }
    // change role of user
    public function changeRole($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma_area']);
        $user = $this->Users->get($id);
        $role = $this->request->getData('role');
        $user->role = $role;
        if ($this->Users->save($user)) {
            $message = 'Ruolo cambiato con successo';
        } else {
            $message = 'Impossibile cambiare il ruolo';
        }
        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Utente non autorizzato');
        }
        $user_id = $identity->get('id');
        // remove cache
        Cache::delete("companies-$user_id", 'long');
        $this->set(compact('message'));
        $this->viewBuilder()->setOption('serialize', ['message']);
    }
}

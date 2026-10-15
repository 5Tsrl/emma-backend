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
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;

class UsersController extends AppController
{
    /**
     * Initialize.
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['login', 'logout', 'requestResetPassword', 'register', 'organizations', 'translators', 'changePassword', 'whoami', 'refreshToken', 'confirmRenew', 'selectProfile']);
        $this->loadComponent('Paginator');
    }

    /**
     * Blocca le azioni di gestione password sui siti che usano solo un IdP
     * esterno (es. RER via IAM/SPID): PASSWORD_MANAGEMENT="false" nel .env del sito.
     */
    private function assertPasswordManagementEnabled(): void
    {
        if (Configure::read('PasswordManagement') === false) {
            throw new ForbiddenException(__('La gestione delle password è disabilitata su questo sito'));
        }
    }

    public function index()
    {
        $identity = $this->Authentication->getIdentity();
        $myRole = $identity->get('role');

        // moma_area non ha più accesso alla sezione Users (VH1): 403 esplicito,
        // prima ancora di costruire la query/applyScope() (UsersTablePolicy::beforeScope()).
        if ($myRole === 'moma_area') {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }

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
            // Note: When SecretField is enabled, email search works differently:
            // - Exact email matches (e.g., "user@example.com") are encrypted and matched exactly
            // - Partial text searches only work on first_name/last_name
            $conditions['search'] = $search;
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
        $users = [];
        $pagination = [];
        if ($myRole != 'user') {
            $user_q = $this->Users->index($conditions, $year);
            // Scope per azienda/area (anti VH1): moma/moma_area non possono
            // enumerare l'intera rubrica. Vedi UsersTablePolicy::beforeScope().
            $this->Authorization->applyScope($user_q);

            try {
                $users = $this->paginate($user_q);
            } catch (NotFoundException $e) {
                // Do something here like redirecting to first or last page.
                // $this->request->getAttribute('paging') will give you required info.
                $users = [];
            }
            $pagination = $this->Paginator->getPagingParams();

            // Se il filtro per anno non restituisce risultati, riprova senza anno
            if (!empty($year) && isset($pagination['Users']['count']) && $pagination['Users']['count'] == 0) {
                $user_q = $this->Users->index($conditions, null);
                $this->Authorization->applyScope($user_q);
                try {
                    $users = $this->paginate($user_q);
                } catch (NotFoundException $e) {
                    $users = [];
                }
                $pagination = $this->Paginator->getPagingParams();
            }
        } else {
            // L'utente "user" non lista alcun utente: niente da autorizzare a livello query.
            $this->Authorization->skipAuthorization();
        }
        $this->set(compact('users', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['users', 'pagination']);
    }

    public function indexNoPagination()
    {
        $identity = $this->Authentication->getIdentity();
        $myRole = $identity->get('role');

        // moma_area non ha più accesso alla sezione Users (VH1): 403 esplicito,
        // prima ancora di costruire la query/applyScope() (UsersTablePolicy::beforeScope()).
        if ($myRole === 'moma_area') {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }

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
            // Note: When SecretField is enabled, email search works differently:
            // - Exact email matches (e.g., "user@example.com") are encrypted and matched exactly
            // - Partial text searches only work on first_name/last_name
            $conditions['search'] = $search;
        }

        $geocoded = $this->request->getQuery('geocoded');
        if (!empty($geocoded)) {
            $conditions['geocoded'] = $geocoded;
        }

        if ($identity->get('subcompany')) {
            $subcompany = $identity->get('subcompany');
            $conditions['subcompany'] = $subcompany;
        }

        $ids = $this->request->getQuery('ids');
        if (!empty($ids)) {
            $conditions['Users.id IN'] = $ids;
        }

        $year = $this->request->getQuery('year');

        $users = [];
        if ($myRole != 'user') {
            $user_q = $this->Users->index($conditions, $year);
            // Scope per azienda/area (anti VH1). Vedi UsersTablePolicy::beforeScope().
            $this->Authorization->applyScope($user_q);
            $users = $user_q->all();
        } else {
            // L'utente "user" non lista alcun utente.
            $this->Authorization->skipAuthorization();
        }

        $this->set(compact('users'));
        $this->viewBuilder()->setOption('serialize', ['users']);
    }

    //TODO: l'utente deve poter modificare il suo profilo, gli altri ruoli anche i profili altrui
    //TODO: assicurarsi che non cancelli la password

    public function edit($user_id = null)
    {
        // moma è sola lettura sugli utenti (VH1 round 3); moma_area non ha più
        // accesso alla sezione Users (VH1 round 4): 403 al gate del ruolo, prima
        // ancora di raggiungere Authorization->authorize() più sotto.
        $this->allowRolesOnly(['admin']);
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
        } else {
            $message = sprintf(
                'Logged out url %s from IP %s, ',
                $url,
                $ipAddress
            );
        }
        $user = $this->Users->findById($user_id)
            ->first();

        if (empty($user)) {
            throw new NotFoundException(__("L'utente richiesto non esiste"));
        }

        // Verifica di accesso puntuale: moma può modificare solo utenti della propria
        // azienda; ognuno può modificare il proprio profilo. Vedi UserPolicy::canEdit().
        $this->Authorization->authorize($user, 'edit');

        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            $identity = $this->Authentication->getIdentity();

            // La password non si modifica da questa action (vedi changePassword()).
            unset($dt['password']);

            // L'entity blocca role/is_superuser/company_id/office_id al mass-assignment:
            // patchEntity non può più scalare i privilegi. Admin può però impostare
            // company_id e office_id esplicitamente.
            $accessibleFields = [];
            if ($identity && $identity->role === 'admin') {
                $accessibleFields = [
                    'company_id' => true,
                    'office_id' => true,
                ];
            }
            $user = $this->Users->patchEntity($user, $dt, ['accessibleFields' => $accessibleFields]);

            // is_superuser: solo un superuser può impostarlo (assegnazione esplicita,
            // l'entity lo blocca dal patch).
            if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
                $user->is_superuser = false;
            } elseif (array_key_exists('is_superuser', $dt)) {
                $user->is_superuser = (bool)$dt['is_superuser'];
            }

            // Cambio ruolo: solo admin, solo verso un ruolo canonico valido.
            // Lettura transitoria sia di 'role' (corretto) sia di 'ruolo' (legacy FE).
            $requestedRole = $dt['role'] ?? $dt['ruolo'] ?? null;
            if (
                $identity
                && $identity->role === 'admin'
                && $requestedRole !== null
                && in_array($requestedRole, User::ROLES, true)
            ) {
                $oldRole = $user->getOriginal('role');
                if ($requestedRole !== $oldRole) {
                    $user->role = $requestedRole;
                    $message = $message . 'Ruolo cambiato da ' . $oldRole . ' a ' . $requestedRole . ', per l\'utente con email: ' . $user->email . ' ';
                }
            }
            //Campi con nomi sbagliati (il FE invia nome/cognome)
            if (array_key_exists('nome', $dt)) {
                $user->first_name = $dt['nome'];
            }
            if (array_key_exists('cognome', $dt)) {
                $user->last_name = $dt['cognome'];
            }

            // $user->years = json_encode($dt['years']);


            if ($this->Users->save($user)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The user has been saved.'));
                    Log::write('info', $message . 'Utente salvato con successo', ['scope' => ['permits']]);

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg = 'User info saved';
                    Log::write('info', $message . $msg, ['scope' => ['permits']]);
                    $this->set(compact('msg'));
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('User info cannot be saved. Please, try again.'));
                    Log::write('info', $message . 'Utente non salvato', ['scope' => ['permits']]);
                } else {
                    $errors = $user->getErrors();
                    $flatErrors = $this->flattenErrors($errors);
                    $msg = __('{0} ERRORE durante la creazione: {1}', $user['email'], (empty($flatErrors) ? __('Errore di validazione') : implode('; ', $flatErrors)));
                    Log::write('info', $message . $msg, ['scope' => ['permits']]);
                    $this->response = $this->response->withStatus(422);
                    $this->set(compact('msg', 'errors'));
                    $this->viewBuilder()->setOption('serialize', ['msg', 'errors']);

                    return;
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
        // moma è sola lettura sugli utenti (VH1 round 3): solo admin elimina.
        $this->allowRolesOnly(['admin']);

        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->find('all', [
            'conditions' => ['id' => $id],
        ])->contain('Batches')->first();
        // delete batch row
        if (!empty($user->batches)) {
            $this->Users->Batches->delete($user->batches[0]);
        }
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
        } else {
            $message = sprintf(
                'url %s from IP %s, user %s: ',
                $url,
                $user->username
            );
        }

        if ($this->Users->delete($user)) {
            if (!$this->request->is('json')) {
                $this->Flash->success(__('The User has been deleted.'));
                Log::write('info', $message . 'Utente cancellato con successo', ['scope' => ['permits']]);

                return $this->redirect(['action' => 'index']);
            } else {
                $msg = 'The User has been deleted.';
                Log::write('info', $message . $msg, ['scope' => ['permits']]);
                $this->set(compact('msg'));
                $this->viewBuilder()->setOption('serialize', ['msg']);
            }
        } else {
            if (!$this->request->is('json')) {
                $this->Flash->error(__('The User could not be deleted. Please, try again.'));
                Log::write('info', $message . 'Utente non cancellato', ['scope' => ['permits']]);
            } else {
                $msg = 'User not deleted';
                Log::write('info', $message . $msg, ['scope' => ['permits']]);
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
                $request->getdata()['email'] ?? 'unknown',
                $url,
                $ipAddress,
                $userAgent
            );
            Log::write('info', 'Utente non autenticato, puoi accedere di nuovo?' . $message, ['scope' => ['login']]);
            throw new UnauthorizedException(__('Utente non autenticato, puoi accedere di nuovo?'));
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
                    'id',
                    'email',
                    'first_name',
                    'last_name',
                    'role',
                    'company_id',
                    'office_id',
                    'username',
                    'tos_date',
                    'privacy_consent_ack_date',
                    'mobile',
                    'cf',
                    'badge_number',
                    'active',
                    'token',
                    'refresh_token',
                ])
                ->first();

            if (!$user->active) {
                Log::write('info', "Whoami rejected: user {$user->email} is not active", ['scope' => ['login']]);
                throw new UnauthorizedException(__('Utente non attivo'));
            }
        }
        $this->set(compact('user'));
        $this->viewBuilder()->setOption('serialize', 'user');
    }

    public function getMySurvey(): void
    {
        $identity = $this->Authentication->getIdentity();
        //$identity = $this->getRequest()->getAttribute('identity');
        if (is_null($identity)) {
            throw new UnauthorizedException(__('Utente non autenticato, puoi accedere di nuovo?'));
        }

        $id = $identity->get('id');
        $this->SurveyParticipants = TableRegistry::getTableLocator()->get('SurveyParticipants');

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
                $user = $this->Users->get($identity);

                // Anti-ambiguità post-autenticazione: anche quando l'identifier ha
                // già risolto un solo profilo (es. account diversi con la stessa
                // email ma password diverse, che si "risolvono da soli" a livello
                // di identifier), verifico se esistono ALTRI record Users che
                // condividono la stessa email o lo stesso Codice Fiscale (se non
                // vuoto) — caso non coperto dal controllo pre-autenticazione, che
                // confronta solo sul campo di login. Vedi UsersTable::findSiblingProfiles().
                $siblings = $this->Users->findSiblingProfiles($user);
                if (count($siblings) > 1) {
                    $this->respondWithProfileChoice($siblings, $url, $ipAddress, $userAgent);

                    return;
                }

                $this->finalizeLogin($user, 'Form Authentication');

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
                        Log::write('info', "Redirecting to $r" . $message, ['scope' => ['login']]);

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
                    Log::write('info', 'userController' . $message, ['scope' => ['login']]);

                    return;
                }

                Log::write('info', $message, ['scope' => ['login']]);

                return; // $this->response;
            } else {
                $message = sprintf(
                    'Email %s try logged in url %s from IP %s with user agent %s ',
                    $request->getdata()['email'],
                    $url,
                    $ipAddress,
                    $userAgent
                );
                $list = $result->getErrors();

                // Più record Users corrispondono alle stesse credenziali (vedi
                // App\Identifier\MultiProfilePasswordIdentifier): non è un fallimento,
                // propongo la scelta del profilo al client invece del generico
                // "Utente e/o password sbagliati" costruito più sotto.
                $matches = $this->extractMultipleProfilesError($list);
                if (!empty($matches)) {
                    $this->respondWithProfileChoice($matches, $url, $ipAddress, $userAgent);

                    return;
                }

                $e = '';
                foreach ($list as $l) {
                    if (!is_array($l)) {
                        $r = [$l];
                    } else {
                        $r = $l;
                    }

                    if (count($r) > 0) {
                        $e .= ' ' . $this->recursive_implode($r, ', ');
                    }
                }
                Log::write('info', "Utente e/o password sbagliati: $e " . $message, ['scope' => ['login']]);
                throw new UnauthorizedException(__('Utente e/o password sbagliati: {0}', $e));
            }
        }
    }

    /**
     * Completa il login per un utente già identificato: emette i cookie httpOnly
     * (access + refresh token), salva lo user con i nuovi token e prepara la
     * risposta JSON `data` (stesso formato storico, vedi commento sotto).
     *
     * Condiviso da login() (identificazione diretta, un solo profilo) e
     * selectProfile() (scelta esplicita tra più profili corrispondenti).
     *
     * @param \App\Model\Entity\User $user Utente da autenticare.
     * @param string $logLabel Prefisso descrittivo per il log (es. "Form Authentication").
     * @return void
     */
    private function finalizeLogin(User $user, string $logLabel): void
    {
        if (!$user->active) {
            Log::write('info', "Login rejected: user {$user->email} is not active", ['scope' => ['login']]);
            throw new UnauthorizedException(__('Utente non attivo'));
        }

        //Ottengo i cookie per l'utente corrente
        [$refreshCookie, $accessCookie] = $user->getHttpOnlyCookies();
        //Aggiorno lo user a db con i token
        $this->Users->save($user);

        // Aggiungi i cookie alla response
        $this->response = $this->response
            ->withCookie($accessCookie)
            ->withCookie($refreshCookie);

        $this->log("$logLabel successful for user {$user->email} - Access and Refresh tokens generated", 'info');

        //@massimoi - 19/3/2026 - Tolgo i campi che non voglio restituire al client
        unset($user->password);
        unset($user->refresh_token);
        unset($user->refresh_token_expires);
        unset($user->additional_data);
        unset($user->secret);
        unset($user->secret_verified);
        unset($user->mobile);

        // Allinea l'identità in sessione al profilo effettivamente scelto. Il
        // Session authenticator è il PRIMO della catena (vedi Application::
        // getAuthenticationService()) e ha precedenza sul cookie JWT: se il form
        // login aveva già risolto e persistito in sessione un altro profilo con la
        // stessa email (caso "siblings" / password diverse), senza questo le
        // richieste successive continuerebbero a risolvere l'utente sbagliato
        // anche se il JWT emesso contiene l'id giusto.
        $this->Authentication->setIdentity($user);

        //Risposta per retrocompilazione, non dovrebbe più servire
        $data = [
            'access_token' => $user->token,
            'refresh_token' => $user->refresh_token,
            'user' => $user,
            'success' => true,
        ];
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    /**
     * Cerca ricorsivamente la chiave 'multiple_profiles' nell'array di errori
     * restituito da Result::getErrors(). Serve perché l'errore viene popolato
     * dall'identifier (vedi MultiProfilePasswordIdentifier/MultiProfileAuth0Identifier)
     * ma arriva al controller annidato sotto la chiave dell'alias con cui
     * l'identifier è stato caricato (Authentication\Identifier\IdentifierCollection::getErrors()).
     *
     * @param array $errors Errori grezzi restituiti da Result::getErrors().
     * @return \App\Model\Entity\User[]|null Gli utenti tra cui scegliere, o null se non ambiguo.
     */
    private function extractMultipleProfilesError(array $errors): ?array
    {
        if (isset($errors['multiple_profiles']) && is_array($errors['multiple_profiles'])) {
            return $errors['multiple_profiles'];
        }
        foreach ($errors as $value) {
            if (is_array($value)) {
                $found = $this->extractMultipleProfilesError($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Risponde al client con la lista dei profili tra cui scegliere e un
     * pending-login token da rimandare a selectProfile(). Nessun cookie viene
     * emesso a questo punto: il login si completa solo dopo la scelta esplicita.
     *
     * @param \App\Model\Entity\User[] $matches Profili le cui credenziali sono state verificate.
     * @param string $url Referer della richiesta (solo per log).
     * @param string $ipAddress IP del client (solo per log).
     * @param string $userAgent User agent del client (solo per log).
     * @return void
     */
    private function respondWithProfileChoice(array $matches, string $url, string $ipAddress, string $userAgent): void
    {
        $profiles = $this->buildProfileChoices($matches);
        $pendingToken = User::getPendingLoginToken(array_column($profiles, 'id'));

        Log::write(
            'info',
            sprintf(
                'Login ambiguo: %d profili corrispondono per url %s da IP %s con user agent %s',
                count($profiles),
                $url,
                $ipAddress,
                $userAgent
            ),
            ['scope' => ['login']]
        );

        $data = [
            'multipleProfiles' => true,
            'pendingToken' => $pendingToken,
            'profiles' => $profiles,
        ];
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    /**
     * Costruisce, a partire dagli utenti candidati, l'elenco minimo di campi
     * "di contesto" (azienda/sede/ruolo) da mostrare nella modale di scelta del
     * profilo — senza esporre dati sensibili (password, token, ecc. restano fuori).
     *
     * @param \App\Model\Entity\User[] $users Utenti candidati.
     * @return array<int, array<string, mixed>>
     */
    private function buildProfileChoices(array $users): array
    {
        return $this->Users->buildProfileChoices($users);
    }

    /**
     * Completa il login dopo che il client ha scelto uno dei profili proposti da
     * login() (vedi respondWithProfileChoice()). Il pending-login token elenca già
     * i soli profili con credenziali verificate: non serve far reinserire nulla
     * all'utente, basta convalidare che la scelta sia tra quelli proposti.
     *
     * Endpoint: POST /users/select-profile.json
     * Body: {"pendingToken": "...", "userId": 123}
     */
    public function selectProfile(): void
    {
        $this->request->allowMethod(['post']);

        $pendingToken = $this->request->getData('pendingToken');
        $userId = $this->request->getData('userId');

        $decoded = User::validatePendingLoginToken($pendingToken);
        if (!$decoded) {
            throw new UnauthorizedException(__('La scelta del profilo è scaduta, ripeti il login'));
        }

        if (empty($userId) || !in_array((string)$userId, $decoded->profiles, true)) {
            throw new UnauthorizedException(__('Profilo selezionato non valido'));
        }

        // contain('Areas'): come findForAuthentication(). Senza, l'identità messa in sessione da
        // finalizeLogin() non ha `area` e SurveysTablePolicy::scopeIndex() va in TypeError (500)
        // per un moma_area che passa dalla scelta del profilo.
        $user = $this->Users->find()->contain('Areas')->where(['Users.id' => $userId])->first();
        if (empty($user)) {
            throw new NotFoundException(__("L'utente richiesto non esiste"));
        }

        $ipAddress = $this->request->clientIp();
        $userAgent = $this->request->getHeaderLine('User-Agent');
        $url = $this->request->getHeaderLine('Referer');

        $this->finalizeLogin($user, 'Profile selection');

        Log::write(
            'info',
            sprintf(
                'User %s (ID: %d) with role %s selected profile from url %s from IP %s with user agent %s',
                $user->username,
                $user->id,
                $user->role,
                $url,
                $ipAddress,
                $userAgent
            ),
            ['scope' => ['login']]
        );
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
        $this->assertPasswordManagementEnabled();

        $token = $this->request->getQuery('token');
        $identity = $this->Authentication->getIdentity();
        if (is_null($identity)) {
            throw new UnauthorizedException(__('Utente non autenticato'));
        }
        $form_data = $this->request->input('json_decode');
        $usertoken = new User();
        if (!empty($form_data->userId) && $form_data->userId != $identity->get('token')) {
            $user = $this->Users->findById($form_data->userId)
                ->first();
            if (empty($user)) {
                if (empty($identity->get('token'))) {
                    throw new UnauthorizedException(__('Token non valido'));
                } else {
                    throw new NotFoundException(__("L'utente richiesto non esiste"));
                }
            }
        } else {
            $user = $this->Users->findById($identity->get('id'))
                ->first();
            if ($token != $user->token) {
                throw new UnauthorizedException(__('Token non valido, richiedi un nuovo reset della password'));
            } elseif (time() > $user->token_expires->getTimestamp()) {
                throw new UnauthorizedException(__('Token scaduto, richiedi un nuovo reset della password'));
            }
        }

        if (empty($user)) {
            throw new NotFoundException(__("L'utente richiesto non esiste"));
        }
        if ($this->request->is(['patch', 'post', 'put'])) {
            $id = $identity->get('id');
            $myRole = $identity->get('role');

            // moma è sola lettura sugli utenti (VH1 round 3): può cambiare solo
            // la propria password. Reset della password altrui: solo admin.
            if ($user->id == $id || in_array($myRole, ['admin'])) {
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
                        throw new Exception(__('Impossibile salvare la nuova password'));
                    }
                } else {
                    throw new Exception(__('Le password non corrispondono'));
                }
            }
        }

        $this->set(compact('user'));
    }

    public function requestResetPassword()
    {
        $this->request->allowMethod(['post']);
        $this->assertPasswordManagementEnabled();
        //$this->autoRender = false;
        $email = $this->request->getData('email');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException(__('Indirizzo email non valido'));
        }
        $referer = $this->referer('/', false);

        //Cerco nel db se c'è uno user con questa mail, se no fallisco
        try {
            $user = $this->Users->findByEmail($email)->firstOrFail();
        } catch (\Throwable $e) {
            throw new NotFoundException(__('Nessun utente con la mail: {0}', $email));
        }

        //Se c'è uno user con questa mail preparo una notifica allo user con il token a changePassword
        $data = $user->requestResetPassword($user, $referer);
        $msg = $data['msg'];
        // save user with token and token_expires
        $user->token = $data['token'];
        $user->token_expires = date('Y-m-d H:i:s', time() + DAY * 2); // 2 days from now
        if (!$this->Users->save($user)) {
            throw new InternalErrorException(__('Impossibile salvare il token per il reset della password'));
        }
        $this->set(compact('msg'));
        $this->set('_serialize', ['msg']);
    }

    /**
     * Conferma che l'utente è ancora attivo e rinnova l'account di un anno.
     * Usa il token generato dal command TestExpirationUsersCommand.
     */
    public function confirmRenew(): void
    {
        $token = $this->request->getQuery('token');

        if (empty($token)) {
            throw new UnauthorizedException(__('Token mancante'));
        }

        // Trova l'utente con questo token
        $user = $this->Users->find()
            ->where(['Users.token' => $token])
            ->first();

        if (empty($user)) {
            throw new NotFoundException(__('Token non valido o utente non trovato'));
        }

        // Verifica che il token non sia scaduto
        if ($user->token_expires && time() > $user->token_expires->getTimestamp()) {
            throw new UnauthorizedException(__('Token scaduto'));
        }

        // Aggiunge 1 anno alla data di scadenza
        if ($user->expiration_date) {
            $newExpiration = $user->expiration_date->addYear();
        } else {
            $newExpiration = new \Cake\I18n\FrozenDate('+1 year');
        }

        $user->expiration_date = $newExpiration;
        $user->token = null; // Invalida il token usato
        $user->token_expires = null;

        if ($this->Users->save($user)) {
            $msg = 'Il tuo account è stato rinnovato con successo. Nuova scadenza: ' . $newExpiration->format('d/m/Y');
            $this->set(compact('msg'));
            $this->viewBuilder()->setOption('serialize', ['msg']);
        } else {
            throw new InternalErrorException(__('Impossibile salvare il rinnovo dell\'account'));
        }
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
                'expiration_date' => date('Y-m-d', strtotime('+365 days')),
            ];
            // Action riservata ad admin: può impostare esplicitamente role/password
            // che l'entity blocca al mass-assignment dalla UI self-service.
            $accessibleFields = ['role' => true, 'password' => true];
            if (empty($existingUser)) {
                $user = $this->Users->newEntity($userData, ['accessibleFields' => $accessibleFields]);
                $action = 'creato';
            } else {
                $user = $this->Users->patchEntity($existingUser, $userData, ['accessibleFields' => $accessibleFields]);
                $action = 'aggiornato';
            }
            // Only superuser can create another superuser (assegnazione esplicita).
            if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
                $user->is_superuser = false;
            } elseif ($this->request->getData('is_superuser')) {
                $user->is_superuser = true;
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

            // Invalidate JWT token and refresh token in database if needed
            $userId = $identity->get('id');
            $user = $this->Users->get($userId);
            $user->token = null;
            $user->token_expires = null;
            $user->refresh_token = null;
            $user->refresh_token_expires = null;
            $this->Users->save($user);

            // Anti VM1: revoca server-side dei token correnti inserendone il jti nella
            // denylist. Annullare le colonne a DB non basta perché l'access token è
            // validato solo da firma+exp: un token catturato resterebbe valido fino a
            // scadenza. Il jti dei token correnti è preso dai cookie (o dall'header).
            $revoked = $this->fetchTable('RevokedTokens');
            $accessJwt = $request->getCookie('jwt_token');
            if (empty($accessJwt)) {
                $authHeader = $request->getHeaderLine('Authorization');
                if (stripos($authHeader, 'Bearer ') === 0) {
                    $accessJwt = substr($authHeader, 7);
                }
            }
            foreach ([[$accessJwt, 'access'], [$request->getCookie('jwt_refresh_token'), 'refresh']] as [$jwt, $type]) {
                $payload = User::decodeToken($jwt);
                if ($payload && isset($payload->jti)) {
                    $revoked->revoke($payload->jti, (string)$userId, $type, $payload->exp ?? null);
                }
            }
        } else {
            $message = sprintf(
                'Logged out url %s from IP %s with user agent %s ',
                $url,
                $ipAddress,
                $userAgent
            );
            Log::write('info', $message, ['scope' => ['login']]);
        }

        // Clear authentication
        $this->Authentication->logout();

        // Delete httponly cookies (both access and refresh token)
        $accessCookie = new Cookie(
            'jwt_token',
            '',
            new \DateTime('-1 day'), // Data passata per eliminare
            '/',
            null, // dominio (null = automatico)
            true, // secure
            true, // httpOnly
            'Lax' // SameSite
        );

        $refreshCookie = new Cookie(
            'jwt_refresh_token',
            '',
            new \DateTime('-1 day'), // Data passata per eliminare
            '/',
            null, // dominio (null = automatico)
            true, // secure
            true, // httpOnly
            'Lax' // SameSite
        );

        // Rimuovi entrambi i cookie
        $this->response = $this->response
            ->withExpiredCookie($accessCookie)
            ->withExpiredCookie($refreshCookie);

        if (!$this->request->is('json')) {
            return $this->redirect(['controller' => 'Users', 'action' => 'login']);
        }
        $msg = 'Logout con successo';
        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    /**
     * Refresh JWT access token using refresh token
     * Endpoint: POST /users/refresh-token
     * Body: {"refresh_token": "..."}
     * Returns: {"access_token": "...", "refresh_token": "..."}
     */
    public function refreshToken()
    {
        $this->request->allowMethod(['post']);

        $ipAddress = $this->request->clientIp();
        $userAgent = $this->request->getHeaderLine('User-Agent');
        $url = $this->request->getHeaderLine('Referer');

        // Try to get refresh token from request body first, then from HttpOnly cookie
        $refreshToken = $this->request->getData('refresh_token');

        if (empty($refreshToken)) {
            $refreshToken = $this->request->getCookie('jwt_refresh_token');
        }

        if (empty($refreshToken)) {
            Log::write('warning', "Refresh token missing from IP $ipAddress", ['scope' => ['login']]);
            throw new UnauthorizedException('Refresh token mancante');
        }

        // Validate the refresh token
        $decoded = User::validateRefreshToken($refreshToken);

        if (!$decoded) {
            Log::write('warning', "Invalid refresh token from IP $ipAddress", ['scope' => ['login']]);
            throw new UnauthorizedException('Refresh token non valido o scaduto');
        }

        $userId = $decoded->sub;

        // Get user from database
        $user = $this->Users->find()
            ->where(['id' => $userId])
            ->first();

        if (!$user) {
            Log::write('warning', "User not found for refresh token (ID: $userId) from IP $ipAddress", ['scope' => ['login']]);
            throw new NotFoundException('Utente non trovato');
        }

        // Verify the refresh token matches what's stored in database
        if ($user->refresh_token !== $refreshToken) {
            Log::write('warning', "Refresh token mismatch for user {$user->username} (ID: $userId) from IP $ipAddress", ['scope' => ['login']]);
            throw new UnauthorizedException('Impossibile accedere, verifica le credenziali');
        }

        // Check if refresh token has expired in database
        if ($user->refresh_token_expires && $user->refresh_token_expires->getTimestamp() < time()) {
            Log::write('warning', "Expired refresh token for user {$user->username} (ID: $userId) from IP $ipAddress", ['scope' => ['login']]);
            throw new UnauthorizedException('Refresh token scaduto');
        }

        // Anti VM1: revoca il vecchio refresh token (rotazione) così che, se catturato,
        // non sia riutilizzabile dopo il refresh.
        $oldDecoded = User::decodeToken($refreshToken);
        if ($oldDecoded && isset($oldDecoded->jti)) {
            $this->fetchTable('RevokedTokens')->revoke($oldDecoded->jti, (string)$userId, 'refresh', $oldDecoded->exp ?? null);
        }

        // Generate new access token (vita breve, allineato al cookie)
        $accessToken = $user->getToken($userId, User::ACCESS_TOKEN_LIVE);

        // Generate new refresh token (rotate refresh tokens)
        $newRefreshToken = $user->getRefreshToken($userId);

        // Save new refresh token to database
        $user->refresh_token = $newRefreshToken;
        $user->refresh_token_expires = date('Y-m-d H:i:s', time() + User::REFRESH_TOKEN_MONTH_LIVE);

        if (!$this->Users->save($user)) {
            Log::write('error', "Failed to save new refresh token for user {$user->username} (ID: $userId)", ['scope' => ['login']]);
            throw new InternalErrorException('Impossibile salvare il nuovo refresh token');
        }

        // Set new HttpOnly cookies
        $accessCookie = new Cookie(
            'jwt_token',
            $accessToken,
            new \DateTime('+' . User::ACCESS_TOKEN_LIVE . ' seconds'), // allineato a ACCESS_TOKEN_LIVE
            '/',
            null, // dominio (null = automatico)
            true, // secure
            true, // httpOnly
            'Lax' // SameSite
        );

        $refreshCookie = new Cookie(
            'jwt_refresh_token',
            $newRefreshToken,
            new \DateTime('+30 days'), // 30 giorni
            '/',
            null, // dominio (null = automatico)
            true, // secure
            true, // httpOnly
            'Lax' // SameSite
        );

        // Add cookies to response
        $this->response = $this->response
            ->withCookie($accessCookie)
            ->withCookie($refreshCookie);

        $message = sprintf(
            'User %s (ID: %d) refreshed token from URL %s, IP %s, User Agent %s',
            $user->username,
            $user->id,
            $url,
            $ipAddress,
            $userAgent
        );
        Log::write('info', $message, ['scope' => ['login']]);

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];

        $this->set(compact('data'));
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    public function add()
    {
        $this->allowRolesOnly(['admin']);
        $u = $this->request->getData();
        // Senza gestione password l'utente nasce con una password casuale non
        // comunicata a nessuno: potrà accedere solo tramite IdP esterno (IAM/SPID).
        if (Configure::read('PasswordManagement') === false) {
            unset($u['confirm_password']);
            $u['password'] = bin2hex(random_bytes(32));
        }
        $u['username'] = $u['email'];
        if (!isset($u['active'])) {
            $u['active'] = true;
        }
        if (!isset($u['expiration_date'])) {
            $u['expiration_date'] = date('Y-m-d', strtotime('+365 days'));
        }
        // Ruolo di default 'user' se assente (validato da UsersTable::validationDefault()).
        if (empty($u['role'])) {
            $u['role'] = 'user';
        }
        $identity = $this->Authentication->getIdentity();
        // is_superuser non è mass-assignabile: lo gestiamo esplicitamente sotto.
        unset($u['is_superuser']);
        // Action riservata ad admin: può impostare esplicitamente i campi che
        // l'entity blocca al mass-assignment dalla UI self-service.
        $user = $this->Users->newEntity($u, [
            'accessibleFields' => [
                'role' => true,
                'company_id' => true,
                'office_id' => true,
                'password' => true,
            ],
        ]);
        // Autorizzazione puntuale (admin-only). Vedi UserPolicy::canAdd().
        $this->Authorization->authorize($user, 'add');
        // Only superuser can create another superuser (assegnazione esplicita).
        if (empty($identity) || empty($identity->is_superuser) || !$identity->is_superuser) {
            $user->is_superuser = false;
        } elseif ($this->request->getData('is_superuser')) {
            $user->is_superuser = true;
        }
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
        } else {
            $message = sprintf(
                'url %s from IP %s,',
                $url,
            );
        }

        $msg = '';
        if ($this->Users->save($user)) {
            $msg .= "{$u['email']} creato con successo" . PHP_EOL;
            Log::write('info', $message . $msg, ['scope' => ['permits']]);
            $this->set(compact('msg'));
            $this->viewBuilder()->setOption('serialize', ['msg']);
        } else {
            $errors = $user->getErrors();
            $flatErrors = $this->flattenErrors($errors);
            $msg .= __('{0} ERRORE durante la creazione: {1}', $u['email'], (empty($flatErrors) ? __('Errore di validazione') : implode('; ', $flatErrors)));
            Log::write('info', $message . $msg, ['scope' => ['permits']]);
            $this->response = $this->response->withStatus(422);
            $this->set(compact('msg', 'errors'));
            $this->viewBuilder()->setOption('serialize', ['msg', 'errors']);
        }
    }

    public function updateTos()
    {
        $identity = $this->Authentication->getIdentity();
        if (is_null($identity)) {
            throw new UnauthorizedException(__('Utente non autenticato'));
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

    /**
     * Registra la presa visione, una tantum per utente, della modale di consenso
     * privacy mostrata al login (RER — vedi docs/dev-notes/rer-privacy-consent.md).
     *
     * Semanticamente distinto da updateTos()/tos_date (accettazione ToS in fase di
     * registrazione, con chiamanti nei plugin Testcommuters/Abbonamenti da non
     * toccare). `privacy_consent_ack_date` NON è in User::$_accessible: viene
     * impostato qui direttamente sull'entity, come tos_date in updateTos().
     *
     * Deliberatamente diverso da updateTos() nella gestione errori: manca
     * l'identità autenticata → eccezione (unica condizione bloccante). Se il save
     * fallisce per un altro motivo si risponde comunque con esito positivo: il
     * frontend chiude la modale a prescindere ("fail silently, re-prompt al
     * prossimo accesso" — decisione di prodotto), quindi niente retry/gestione
     * errori elaborata qui.
     *
     * @return void
     */
    public function acknowledgePrivacyConsent()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->Authentication->getIdentity();
        if (is_null($identity)) {
            throw new UnauthorizedException(__('Utente non autenticato'));
        }

        $id = $identity->get('id');
        $user = $this->Users->findById($id)->first();
        $user->privacy_consent_ack_date = date('Y-m-d H:i:s');
        $this->Users->save($user);

        $msg = 'Consenso privacy registrato con successo';
        $this->set(compact('msg'));
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function getOrigin($id = null)
    {
        $user = $this->Users->get($id);

        // First get the user with origins
        $userWithOrigins = $this->Users->find()
            ->contain(['Origins'])
            ->where(['Users.id' => $user->id])
            ->first();

        // Load the Surveys table directly
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');

        // Then manually load surveys for each origin that has a survey_id
        if ($userWithOrigins && $userWithOrigins->origins) {
            foreach ($userWithOrigins->origins as $origin) {
                if ($origin->survey_id) {
                    // Surveys ha il Translate behavior: qualifico `id` (join i18n → ambiguità).
                    $survey = $this->Surveys->find()
                        ->select(['Surveys.id', 'name', 'company_id', 'year'])
                        ->where(['Surveys.id' => $origin->survey_id])
                        ->first();
                    $origin->survey = $survey ? $survey->toArray() : null;
                } else {
                    $origin->survey = null;
                }

                // Remove any flattened survey fields that might be auto-added
                unset($origin->{'survey.id'});
                unset($origin->{'survey.name'});
                unset($origin->{'survey.company_id'});
                unset($origin->{'survey.year'});
            }
        }

        $origin = $userWithOrigins;
        $this->set(compact('origin'));
        $this->viewBuilder()->setOption('serialize', ['origin']);
    }

    public function getGuide()
    {
        $url = ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . 'guida.pdf';
        $response = $this->response->withFile($url, ['download' => true, 'name' => 'guida.pdf']);
        // Return the response to prevent controller from trying to render
        // a view.
        return $response;
    }

    public function uploadGuide()
    {
        if ($this->request->is('POST')) {
            $attachment = $this->request->getData('file');
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];

            if ($error !== UPLOAD_ERR_OK) {
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
                Log::error($msg);
                $this->Flash->error('Il documento non è stato caricato a causa di un errore: ' . $msg);

                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);

            $parts = pathinfo($name);
            if (strtolower($parts['extension']) !== 'pdf' || $detectedMimeType !== 'application/pdf') {
                $success = false;
                $msg = 'Il file caricato non è un PDF valido.';
                Log::warning($msg . " (File: $name, Mime: $detectedMimeType)");
                $this->Flash->error('Il file caricato non è un PDF valido');

                return;
            }

            $targetPath = ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . 'guida.pdf';
            $folder = new Folder(dirname($targetPath), true, 0777);
            if (move_uploaded_file($fname, $targetPath)) {
                $success = true;
                $msg = 'Documento caricato con successo';
                $this->Flash->success('Documento caricato con successo');

                return;
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
        $this->viewBuilder()->setOption('serialize', ['msg', 'company_id']);
    }

    public function uploadFaq()
    {
        if ($this->request->is('POST')) {
            $attachment = $this->request->getData('file');
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];

            if ($error !== UPLOAD_ERR_OK) {
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
                Log::error($msg);
                $this->Flash->error('Il documento non è stato caricato a causa di un errore: ' . $msg);

                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);

            $parts = pathinfo($name);

            if (strtolower($parts['extension']) !== 'pdf' || $detectedMimeType !== 'application/pdf') {
                $success = false;
                $msg = 'Il file caricato non è un PDF valido.';
                Log::warning($msg . " (File: $name, Mime: $detectedMimeType)");
                $this->Flash->error('Il file caricato non è un PDF valido');

                return;
            }

            $targetPath = ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . '/faq.pdf';
            $folder = new Folder(dirname($targetPath), true, 0777);
            if (move_uploaded_file($fname, $targetPath)) {
                $success = true;
                $msg = 'Documento caricato con successo';
                $this->Flash->success('Documento caricato con successo');

                return;
            }
            $this->Flash->error('Il documento non è stato caricato a causa di un errore: ' . $error);

            return;
        }
    }

    public function getFaq()
    {
        $url = ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . '/faq.pdf';
        $response = $this->response->withFile($url, ['download' => true, 'name' => 'guida.pdf']);
        // Return the response to prevent controller from trying to render
        // a view.
        return $response;
    }

    public function find($id)
    {
        $identity = $this->Authentication->getIdentity();
        if (empty($identity)) {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }

        // moma_area non ha più accesso alla sezione Users (VH1 round 4): 403
        // esplicito, prima ancora di raggiungere allowWhoCanSeeCompanyOnly()
        // (helper condiviso con altri controller, quindi non modificabile qui —
        // vedi UsersController per il dettaglio).
        if ($identity->get('role') === 'moma_area') {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }

        $user = $this->Users->get($id, [
            'contain' => ['Employees', 'Origins'],
        ]);

        // Controllo di accesso (anti VH1): l'utente "user" può leggere solo il
        // proprio record; moma solo gli utenti della propria azienda, con
        // company_id vuoto/nullo che nega tutto (chiude il bug VH1: un moma senza
        // company_id vedeva prima QUALSIASI utente tramite allowWhoCanSeeCompanyOnly());
        // gli altri ruoli secondo la visibilità azienda/area di allowWhoCanSeeCompanyOnly().
        if ($identity->get('role') === 'user') {
            if ((string)$identity->get('id') !== (string)$id) {
                throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
            }
        } elseif ($identity->get('role') === 'moma') {
            $companyId = $identity->get('company_id');
            if (empty($companyId) || (string)$companyId !== (string)$user->company_id) {
                throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
            }
        } else {
            $this->allowWhoCanSeeCompanyOnly($user->company_id);
        }

        $this->set(compact('user'));
        $this->viewBuilder()->setOption('serialize', ['user']);
    }

    // change role of user

    public function changeRole($id = null)
    {
        // moma_area non ha più accesso alla sezione Users (VH1 round 4): solo
        // admin può cambiare ruolo.
        $this->allowRolesOnly(['admin']);
        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new UnauthorizedException(__('Utente non autorizzato'));
        }

        $user = $this->Users->get($id);
        // Controllo di accesso puntuale (chi può cambiare ruolo). Vedi UserPolicy::canChangeRole().
        $this->Authorization->authorize($user, 'changeRole');

        $role = $this->request->getData('role');
        // Rifiuta valori di ruolo arbitrari (anti escalation VH1).
        if (!in_array($role, User::ROLES, true)) {
            throw new BadRequestException(__('Ruolo non valido'));
        }
        // Solo admin può assegnare il ruolo admin. Con allowRolesOnly(['admin'])
        // più sopra questo ramo è ormai irraggiungibile (solo admin arriva qui),
        // lasciato come difesa in profondità.
        if ($role === 'admin' && $identity->get('role') !== 'admin') {
            throw new ForbiddenException(__('Non sei autorizzato ad assegnare il ruolo admin'));
        }

        $user->role = $role;
        if ($this->Users->save($user)) {
            $message = 'Ruolo cambiato con successo';
        } else {
            $message = 'Impossibile cambiare il ruolo';
        }
        $user_id = $identity->get('id');
        // remove cache
        Cache::delete("companies-$user_id", 'long');
        $this->set(compact('message'));
        $this->viewBuilder()->setOption('serialize', ['message']);
    }

    /**
     * Flatten CakePHP validation errors into readable strings
     *
     * @param array $errors
     * @return array<string>
     */
    private function flattenErrors(array $errors): array
    {
        $out = [];
        $walker = function ($val, $key) use (&$out) {
            if (is_array($val)) {
                foreach ($val as $rule => $msg) {
                    if (is_string($msg)) {
                        $out[] = "$key: $msg";
                    } elseif (is_array($msg)) {
                        foreach ($msg as $m) {
                            if (is_string($m)) {
                                $out[] = "$key: $m";
                            }
                        }
                    }
                }
            }
        };
        array_walk($errors, $walker);

        return array_values(array_unique($out));
    }
}

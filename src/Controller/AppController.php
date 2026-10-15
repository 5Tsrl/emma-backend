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

use Authorization\AuthorizationServiceInterface;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    use \Crud\Controller\ControllerTrait;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
        $this->Authentication->allowUnauthenticated([
            'getConfig',
        ]);

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');

        $this->loadComponent('Crud.Crud', [
            'actions' => [
                'Crud.Index',
                'Crud.Add',
                'Crud.Edit',
                'Crud.View',
                'Crud.Delete',
            ],
            'listeners' => [
                'Crud.Api',
                'Crud.ApiPagination',
            ],
        ]);

        /*
             * Enable the following component for recommended CakePHP form protection settings.
             * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
             */
        //$this->loadComponent('FormProtection');
        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        // AuthorizationMiddleware sets the 'authorization' request attribute on every
        // real (or PHPUnit-simulated) HTTP request, so this is always true there.
        // It's false only when a Controller is instantiated outside the middleware
        // stack entirely - e.g. cakephp-ide-helper's `annotate controllers` reflects
        // on a bare controller to generate IDE/LSP annotation stubs. Skip the call
        // there instead of throwing, rather than gating on PHP_SAPI (which would also
        // match PHPUnit, since it runs as a CLI process even when simulating requests
        // through the full middleware stack).
        if ($this->getRequest()->getAttribute('authorization') instanceof AuthorizationServiceInterface) {
            $this->Authorization->skipAuthorization();
        }

        /*     //Se la richiesta è json invece di fare redirect devo mandare un'eccezione
        if ($this->request->is('json')) {
          $result = $this->Authentication->getResult();
          if ($result->getStatus() == "FAILURE_CREDENTIALS_MISSING") {
            throw new UnauthorizedException("Autenticazione Non presente");
          }
        } */
    }

    /**
     * 404 brand-safe se il requisito 3 della compliance privacy RER "EMMA RE-R"
     * (documento aziendale sul trattamento dati dei dipendenti) non è attivo su questo
     * sito. Condiviso tra AdhesionRequestsController::employeePrivacyDocument()
     * (upload token-authenticated) e SurveysController::employeePrivacyDocument()
     * (lettura pubblica) e SurveysController::sendNotifications() (send-gate) — stesso
     * pattern di AdhesionRequestsController::assertModuleEnabled().
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    protected function assertEmployeePrivacyDocEnabled(): void
    {
        if (!Configure::read('EmployeePrivacyDocGate')) {
            throw new NotFoundException();
        }
    }

    // consente l'accesso solo ai ruoli specificati:

    protected function allowRolesOnly(array $roles)
    {
        $accessGranted = true;
        $identity = $this->Authentication->getIdentity();
        if (empty($identity)) {
            $accessGranted = false;
        } else {
            $role = $identity->get('role');
            $accessGranted = in_array($role, $roles);
        }
        if (!$accessGranted) {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }
    }

    // consente l'accesso solo a chi può vedere una data azienda

    protected function allowWhoCanSeeCompanyOnly($company_id)
    {
        $identity = $this->Authentication->getIdentity();
        $accessGranted = true;
        if (empty($identity)) {
            $accessGranted = false;
        } else {
            $role = $identity->get('role');
            $user_company_id = $identity->get('company_id');
            $isAdmin = $role == 'admin';
            $isMomaAzienda = $role == 'moma' && !empty($user_company_id);
            $isMomaArea = $role == 'moma' && empty($user_company_id);
            $isMoma_area = $role == 'moma_area';

            $accessGranted = $isAdmin || $isMomaArea || $isMoma_area || ($isMomaAzienda && $user_company_id == $company_id);
        }
        if (!$accessGranted) {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }
    }

    protected function allowWhoCanSeeSurveyOnly($survey_id)
    {
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $survey = $this->Surveys->get($survey_id);
        if (empty($survey)) {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }
        $c = $survey['company_id'];
        $this->allowWhoCanSeeCompanyOnly($c);
    }

    /**
     * Sanitizes user-controlled input for safe use as a single filesystem
     * path segment, preventing path traversal (CWE-22) when a file-write
     * path is built by concatenating client-supplied data - a raw uploaded
     * filename ($_FILES[...]['name'] / PSR-7 getClientFilename()), or any
     * other client-controlled path fragment such as a POSTed "target"/"type"
     * selector.
     *
     * `basename()` first drops any leading directory component, then every
     * character outside [A-Za-z0-9._-] is replaced with '_' - this also
     * neutralizes a bare '..' sequence, since without a '/' separator it can
     * no longer escape the current directory. Mirrors the pattern already
     * used by MeasuresController::resolveUploadedImage().
     *
     * @param string $clientFilename Raw, untrusted filename/path-segment.
     * @param bool $unique When true (default), prefixes the sanitized name
     *  with `uniqid()` - appropriate for a freshly-stored uploaded file that
     *  is always looked up again via a directory listing/DB reference, never
     *  by reconstructing its original client-supplied name. Pass `false`
     *  when the result must instead stay deterministic/stable because some
     *  other part of the system re-derives the exact same path segment from
     *  the same input later (e.g. a fixed "target" selector read back by a
     *  template exporter, or a filename a caller reconstructs client-side).
     * @return string Sanitized path segment, safe to concatenate into a file path.
     */
    protected function sanitizeUploadFilename(string $clientFilename, bool $unique = true): string
    {
        $safeName = (string)preg_replace('/[^A-Za-z0-9._-]/', '_', basename($clientFilename));

        return $unique ? uniqid() . '_' . $safeName : $safeName;
    }

    /**
     * Pass a configuration from backend to frontend.
     * Usecase: the chartcolors are defined only in the backend so that the frontend can get it
     *
     * @param mixed $what //The settings.php parameter you want to read from the configuration
     * @return \App\Controller\json value of the configure settings
     * @throws \Cake\Http\Exception\MethodNotAllowedException  (the settings you want to allow should be in the whitelist)
     * @throws \App\Controller\InvalidArgumentException
     */
    public function getConfig($what)
    {
        $whiteList = Configure::read('WhitelistConfigurations');

        if (!in_array($what, $whiteList)) {
            //throw not allowed exception
            throw new MethodNotAllowedException("The requested config $what is not allowed");
        }

        $res = Configure::read($what);
        // Enable caching
        $this->response = $this->response->withCache('-1 minute', '+1 days');
        $this->set('data', $res);
        $this->viewBuilder()->setOption('serialize', ['data']);
    }
}

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

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\MethodNotAllowedException;

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

        /*
             * Enable the following component for recommended CakePHP form protection settings.
             * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
             */
        //$this->loadComponent('FormProtection');
        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        $this->Authorization->skipAuthorization();

        /*     //Se la richiesta è json invece di fare redirect devo mandare un'eccezione
        if ($this->request->is('json')) {
          $result = $this->Authentication->getResult();
          if ($result->getStatus() == "FAILURE_CREDENTIALS_MISSING") {
            throw new UnauthorizedException("Autenticazione Non presente");
          }
        } */
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
            throw new ForbiddenException('Non sei autorizzato ad accedere alla risorsa richiesta');
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
            throw new ForbiddenException('Non sei autorizzato ad accedere alla risorsa richiesta');
        }
    }

    protected function allowWhoCanSeeSurveyOnly($survey_id)
    {
        $this->loadModel('Surveys');
        $survey = $this->Surveys->get($survey_id);
        if (empty($survey)) {
            throw new ForbiddenException('Non sei autorizzato ad accedere alla risorsa richiesta');
        }
        $c = $survey['company_id'];
        $this->allowWhoCanSeeCompanyOnly($c);
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

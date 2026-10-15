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

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Database\Query;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\HttpException;
use Cake\I18n\FrozenTime;
use Cake\I18n\I18n;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Questions Controller
 *
 * @property \App\Model\Table\QuestionsTable $Questions
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class QuestionsController extends AppController
{
    private $Answers;
    private $Surveys;

    /**
     * Initialize.
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'getTranslations',
        ]);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'getTranslations',
                ]
            );
        }
        $this->loadComponent('Paginator');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $query = $this->Questions->find()
            ->contain('Surveys', function (Query $q) {
                return $q
                ->select(['Surveys.id','name','year']);
                // ->limit(2000);
            })
            ->contain('Answers', function (Query $q) {
                return $q
                ->select(['question_id','count' => $q->func()->count('question_id')])
                ->group(['question_id']);
            })
            ->select(['Questions.id','name', 'description', 'options']);

        // $this->set(compact('questions'));
        // $this->viewBuilder()->setOption('serialize', ['questions']);
        $questions = $this->paginate($query);
        $pagination = $this->Paginator->getPagingParams();
        $this->set(compact('questions', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['questions','pagination']);
    }

    public function list()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $questions = $this->Questions->find()
            ->select(['Questions.id','name', 'description','moma_area'])
            ->order(['name' => 'ASC']);
        $this->Authorization->applyScope($questions);
        $this->set(compact('questions'));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    /**
     * View method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $question = $this->Questions->get($id, [
            //'contain' => ['Answers'],
        ]);

        $this->set(compact('question'));
        $this->viewBuilder()->setOption('serialize', ['question']);
    }

    /**
     * Brescia #522: `type='section'` questions (the survey section headers) can be
     * created/renamed by admin only. Throws 403 for any other role when either the
     * submitted payload type or the STORED type of the question is 'section'.
     *
     * @param mixed $payloadType `type` value from the request payload (may be null).
     * @param mixed $storedType `type` currently stored in the DB (null for a new question).
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException
     */
    private function assertCanManageSection($payloadType, $storedType = null): void
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity !== null && $identity->get('role') === 'admin') {
            return;
        }
        if ($payloadType === 'section' || $storedType === 'section') {
            throw new ForbiddenException(__('Solo un amministratore può creare o rinominare le sezioni'));
        }
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $question = $this->Questions->newEmptyEntity();
        if ($this->request->is('post')) {
            $this->assertCanManageSection($this->request->getData('type'));
            $question = $this->Questions->patchEntity($question, $this->request->getData());
            if ($this->Questions->save($question)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The question has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->set(compact('question'));
                    $this->viewBuilder()->setOption('serialize', ['question']);
                }
            }
            $this->Flash->error(__('The question could not be saved. Please, try again.'));
        }
        $this->set(compact('question'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     *
     * DA RIVEDERE - NON FUNZIONA SULL'EDIT (FA SEMPRE ADD), INOLTRE LA FIRMA DEL METODO LATO VUE
     * E' CAMBIATA (NON PASSO L'ID NELL'URL)
     * AL MOMENTO USO editFromSurvey() (veid sotto)
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        if (!empty($id)) {
            $question = $this->Questions->find()
                ->where(['Questions.id' => $id])
                ->contain(['Surveys'])
                ->first();
            $this->assertCanManageSection($this->request->getData('type'), $question['type'] ?? null);

            if (
                (isset($question['survey']['opening_date']) && $question['survey']['opening_date'] > date('Y-m-d H:i:s')) ||
                (isset($question['survey']['closing_date']) && $question['survey']['closing_date'] < date('Y-m-d H:i:s'))
            ) {
                throw new Exception('Survey is closed');
            }
        } else {
            $this->assertCanManageSection($this->request->getData('type'));
            $question = $this->Questions->newEntity($this->request->getData(), ['associated' => ['Surveys._joinData']]);
        }

        //Devo salvare prima la domanda da sola, se no non salva l'associazione
        $this->Questions->save($question);

        if ($this->request->is(['patch', 'post', 'put'])) {
            if (is_array($this->request->getData('options'))) {
                $question->options = json_encode($this->request->getData('options'));
            }
            if (!empty($this->request->getData('_joinData'))) {
                $jd = $this->request->getData('_joinData');
                if (!empty($jd['survey_id'])) {
                    // $question->surveys = ['_ids' => [$jd['survey_id']]]; - non funziona in nessun variante
                    $survey = $this->Questions->Surveys->findById($jd['survey_id'])->first();
                    $this->Questions->Surveys->link($question, [$survey]);
                    // section_id removed: section grouping is now inline type='section' questions.
                }
            }

            if ($this->Questions->save($question, ['associated' => ['Surveys._joinData']])) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The question has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->set(compact('question'));
                    $this->viewBuilder()->setOption('serialize', ['question']);

                    return;
                }
            }
            if (!$this->request->is('json')) {
                $this->Flash->error(__('The question could not be saved. Please, try again.'));
            } else {
                throw new Exception('Errore durante il salvataggio della domanda');
            }
        }
        $this->set(compact('question'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin']);

        $this->request->allowMethod(['post', 'delete']);
        $question = $this->Questions->get($id);
        // search if the question is used in any conditions of other questions where conditions is a json
        $conditions = $this->Questions->find()
            ->where(function ($exp, $query) use ($id) {
                return $exp->add($query->newExpr('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question")) = ' . (int)$id));
            });
        // debug($conditions); die;
        $conditionsCount = $conditions->count();
        $this->Flash->success(__("Cleared conditions for {$conditionsCount} questions."));
        // // save conditions as empty
        foreach ($conditions as $condition) {
            $condition->conditions = '';
            $this->Questions->save($condition);
        }

        // empty conditions of the questionsurveys
        $questionSurveys = $this->Questions->QuestionsSurveys->find()
            ->where(function ($exp, $query) use ($id) {
                return $exp->add($query->newExpr('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question")) = ' . (int)$id));
            });
        // count the number of questionsurveys with conditions
        $questionSurveysCount = $questionSurveys->count();
        $this->Flash->success(__("Cleared conditions for {$questionSurveysCount} questionsurveys."));
        foreach ($questionSurveys as $questionSurvey) {
            $questionSurvey->conditions = '';
            $this->Questions->QuestionsSurveys->save($questionSurvey);
        }

        if ($this->Questions->delete($question)) {
            $this->Flash->success(__('The question has been deleted.'));
        } else {
            $this->Flash->error(__('The question could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * converte le domande da normalized a multiple
     *
     * NON USATO
     */
    /*public function convert_multi($id)
    {
      $this->Questions->normalizeOptions($id);
      $this->autoRender = false;
      $this->Flash->success('Domanda Multi convertita in tipo Array');

      return $this->redirect($this->referer());
    }*/

    /**
     * restituisce le domande (complete di opzioni) utilizzate come filtri delle origin
     */
    public function originFilters()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        // Stessa guardia di unused()/special() qui sotto: la nested
        // QuestionsSurveys usa find('translations'), che sotto una locale
        // diversa dalla default sostituirebbe `options` con la riga i18n
        // tradotta (stringa pipe-delimited) invece di lasciarla canonica.
        // Vedi CLAUDE.md "Part 16" / docs/dev-notes/questions-surveys-options-string-corruption.md.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        $questions = $this->request->getQuery('q');
        $questionsArray = explode(',', $questions);
        $survey_id = $this->request->getQuery('survey_id');        if ($survey_id == 'null' || $survey_id == 'undefined') {
            $filters = $this->Questions
            ->find()
            ->where([
                'id IN' => $questionsArray,
            ])
            ->toArray();
        } else {
            $filters = $this->Questions
            ->find()
            ->contain([
                'QuestionsSurveys' => function ($query) use ($survey_id) {
                    return $query->find('translations')->where(['survey_id' => $survey_id]);
                },
            ])
            ->where([
                'id IN' => $questionsArray,
            ])
            ->toArray();
        }

        $this->set('filters', array_map(function ($filter) use ($survey_id) {
            if ($survey_id == 'null' || $survey_id == 'undefined') {
                $filter['options'] = $filter['options'];
            } else {
                if (!empty($filter->questions_surveys[0]['options'])) {
                    $filter['options'] = $filter->questions_surveys[0]['options'];
                }
            }
            if ($filter['type'] == 'multiple') {
                // ottieni le effettive opzioni
                $o = $filter['options'][0];
                if (is_array($o) || is_object($o)) {
                    $opts = [];
                    foreach ($filter['options'] as $o) {
                        $opts = array_merge($opts, array_keys((array)$o));
                    }
                    $filter['options'] = array_unique($opts);
                }
            }

            return $filter;
        }, $filters));
        $this->viewBuilder()->setOption('serialize', ['filters']);
    }

    public function unused($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        // Regressione i18n (Part 16, seconda occorrenza della stessa classe di
        // bug del Part 15): QuestionsTable::unused() usa find('translations'),
        // che sotto una locale diversa dalla default (es. Accept-Language: fr)
        // fa sì che il Translate behavior sostituisca le colonne base
        // (`description`/`long_description`/`options`) con la riga i18n
        // tradotta invece che lasciarle canoniche italiane — per `options`
        // (colonna json array) questo restituisce una STRINGA pipe-delimited
        // al posto dell'array, rompendo silenziosamente la validazione
        // `Array.isArray(options)` del frontend (edit.vue::submitQuestion()).
        // Stesso pattern di guardia già presente in editFromSurvey()/edit().
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        $this->set('questions', $this->Questions->unused($survey_id));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    public function special($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        // Stessa guardia di unused() qui sopra — special() ha la stessa
        // find('translations') non protetta. Vedi CLAUDE.md "Part 16".
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        $this->set('questions', $this->Questions->special($survey_id));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    /**
     * @param mixed $id  -> id della domanda per cui voglio recuperare le traduzioni
     * @return \App\Controller\json version of the translations
     */
    public function getTranslations($id, $survey_id)
    {
        // Regressione i18n (defense-in-depth): getTranslations() usa find('translations'),
        // che sotto una locale diversa dalla default potrebbe fare sì che il Translate
        // behavior sostituisca la colonna `options` (JSON array) con una stringa
        // pipe-delimited, rompendo silenziosamente la validazione del frontend.
        // Questo metodo non è attualmente il live trigger del bug (i call site in
        // edit.vue/fill.vue sono commentati), ma il guard previene il riemergere della
        // landmine se un call site viene ri-abilitato. Stesso pattern già presente in
        // unused(), special(), e editFromSurvey().
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->QuestionSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $this->QuestionSurveys->setLocale($defaultLocale);

        $question = $this->Questions->find('translations')
            ->where(['Questions.id' => $id])
            ->first();

        $t = $question->_translations;
        // Part 11: generalizzato da solo 'en' a 'en'+'fr'. `empty($t[$lang]->…)`
        // resta sicuro anche quando una lingua non ha alcuna riga i18n
        // (`$t[$lang]` è null in quel caso: `empty()` valuta a true senza errore).
        foreach (['en', 'fr', 'de'] as $lang) {
            if (!empty($t[$lang]->options) && !is_array($t[$lang]->options)) {
                $t[$lang]->options = json_decode($t[$lang]->options);
            }
        }

        $this->QuestionSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $qs = $this->QuestionSurveys->find('translations')
            ->where(['survey_id' => $survey_id])
            ->where(['question_id' => $id])
            ->first();
        $tqs = $qs->_translations;
        foreach (['en', 'fr', 'de'] as $lang) {
            if (!empty($tqs[$lang]->options) && !is_array($tqs[$lang]->options)) {
                $tqs[$lang]->options = json_decode($tqs[$lang]->options);
            }
        }
        $this->set(compact('t'));
        $this->set(compact('tqs'));
        $this->viewBuilder()->setOption('serialize', ['t','tqs']);
    }

    public function editFromSurvey()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $userId = $identity->get('id');
        $company_id = $identity->get('company_id');
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        // Regressione i18n (Part 11): senza questo guard, sotto una lingua
        // diversa dalla default (es. fr, con Accept-Language: fr), il Translate
        // behavior instrada get()/patchEntity()/save() su `description`/
        // `long_description`/`options` (Questions e QuestionsSurveys) come righe
        // i18n di traduzione invece che sulle colonne base — l'utente scrive il
        // testo canonico italiano nel campo "principale" e questo finisce come
        // riga i18n fr (mai scritto nella colonna base, ed è la causa più
        // probabile delle righe fr "options" pipe-delimited spurie già presenti
        // in DB). Stesso pattern del guard in SurveysController::edit(). Le
        // traduzioni esplicite continuano a passare da `->translation($lang)`,
        // indipendente dalla locale della tabella. Vedi CLAUDE.md i18n.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        if ($this->request->is(['post', 'put'])) {
            $errorMsg = false;
            try {
                $data_all = $this->request->getData();
                $globalmodification = $data_all['globalmodification'];
                $data = $data_all['question'];
                $this->assertCanManageSection($data['type'] ?? null);
                // Una patch globale della sola domanda (es. rename di una sezione:
                // {id, type:'section', description}) può arrivare SENZA
                // questions_surveys: in quel caso non c'è riga di collegamento da
                // gestire e survey_id resta null.
                $survey_id = $data['questions_surveys'][0]['survey_id'] ?? null;
                $question_id = $data['id'];

                // if (!empty($data['long_description']) && !is_array($data['long_description'])) {
                //     $data['long_description'] =array('description' => $data['long_description']);
                // }
                if (!empty($data['id'])) {
                    // Bugfix (Part 17, stessa classe di bug del Part 15/16): un
                    // plain get() NON carica `_translations`. Il flusso "aggiungi
                    // domanda speciale" del frontend (submitNewQuestionFromSpecial())
                    // rispedisce sempre indietro le traduzioni EN/FR ESISTENTI del
                    // catalogo (popolate perché special()/unused() usano
                    // find('translations')), anche se l'utente non le ha toccate.
                    // Su un'entity senza `_translations` pre-caricate, il marshalling
                    // automatico di patchEntity() (TranslateStrategyTrait::buildMarshalMap)
                    // costruisce sotto-entity di traduzione NUOVE invece di aggiornare
                    // quelle esistenti, e save() tenta un INSERT che collide con la riga
                    // i18n già presente (es. domande speciali 560/561/562, che hanno già
                    // righe i18n EN per description/long_description/options) ->
                    // SQLSTATE[23000] duplicate entry su i18n.I18N_LOCALE_FIELD. Stesso
                    // pattern già corretto altrove in questo file/tabella: vedi
                    // unused(), special() e il blocco Questions_spos qui sotto.
                    $question = $this->Questions->find('translations')
                        ->where(['Questions.id' => $data['id']])
                        ->first();
                    if (empty($question)) {
                        throw new \Exception("Domanda {$data['id']} non trovata");
                    }
                    // Brescia #522: a non-admin cannot rename/patch an existing section
                    $this->assertCanManageSection($data['type'] ?? null, $question['type'] ?? null);
                    // domanda esistente, verifica che l'utente sia abilitato a modificarla
                    if ($isMomaAzienda && $question['creator_id'] != $userId) { // altimenti può sempre se ha un altro ruolo
                        // non posso modificare nulla della domanda (solo i joinData)
                        $data = [
                            'id' => $data['id'],
                            '_joinData' => $data['_joinData'],
                        ];
                    }
                } else { // nuova domanda, aggiungi il creator_id
                    $question = $this->Questions->newEmptyEntity();
                    $data['creator_id'] = $userId;
                }
                // $joinData = $data['questions_surveys'][0];
                unset($data['_joinData']); // per tranquillità
                // Bugfix (deployment fr): il payload arriva spesso con un
                // `_locale`/`_i18n` residuo di una GET precedente — `unused()`/
                // `special()` usano find('translations'), che sotto
                // Accept-Language: fr timbra `_locale:"fr"` sull'entity
                // restituita (EavStrategy::rowMapper), e il frontend spreadda
                // l'intera domanda nel payload di save (question._locale =
                // "fr"). Una entity con `_locale` valorizzato VINCE SEMPRE sul
                // `setLocale($defaultLocale)` di tabella impostato sopra: è la
                // stessa TranslateStrategyTrait::setLocale() di CakePHP a
                // documentarlo ("in case an entity has a `_locale` property
                // set, that locale will win over the locale set via this
                // method"). patchEntity() da solo rimetterebbe quindi la
                // entity in fr e beforeSave() proverebbe a instradare
                // `options` (colonna json, array PHP) come riga i18n
                // `content` (colonna stringa) => "Cannot convert value of
                // type `array` to string". Stessa esposizione per
                // l'associazione annidata questions_surveys[0]. Rimossi qui
                // PRIMA di patchEntity(); belt-and-suspenders subito dopo
                // patchEntity() più sotto (vedi CLAUDE.md i18n).
                unset($data['_locale'], $data['_i18n']);
                if (isset($data['questions_surveys'][0]) && is_array($data['questions_surveys'][0])) {
                    unset($data['questions_surveys'][0]['_locale'], $data['questions_surveys'][0]['_i18n']);
                    // `moma_can_edit_options`/`disabled_options` must ONLY ever be
                    // settable via SurveysController::toggleQuestionOptionsPermission()/
                    // setQuestionDisabledOptions() — never through this general
                    // question-edit save (privilege escalation / grant bypass
                    // otherwise). Same "strip before patchEntity()" treatment as
                    // `_locale`/`_i18n` above. Belt-and-suspenders: also blocked at
                    // the entity level, see QuestionsSurvey::$_accessible.
                    unset(
                        $data['questions_surveys'][0]['moma_can_edit_options'],
                        $data['questions_surveys'][0]['disabled_options']
                    );
                    // Brescia #523: `compulsory`/`hidden` are likewise admin-only here
                    // (hide/compulsory are changed through SurveysController::
                    // toggleQuestionVisibility()/toggleQuestionCompulsory(), which
                    // enforce the rule). Without this a moma could bypass those guards
                    // through the general question-edit save.
                    if (($role ?? null) !== 'admin') {
                        unset(
                            $data['questions_surveys'][0]['compulsory'],
                            $data['questions_surveys'][0]['hidden']
                        );
                    }
                }
                // Part 11: skip-empty deve avvenire PRIMA di patchEntity() — il
                // Translate behavior marshalla automaticamente `_translations`
                // dentro patchEntity() (TranslateStrategyTrait::buildMarshalMap):
                // crea comunque una sotto-entity di traduzione per ogni lingua
                // presente nel payload, indipendentemente dal contenuto dei
                // campi. Un controllo skip-empty fatto SOLO nel ciclo manuale
                // sotto (dopo patchEntity) arriva troppo tardi per evitare quella
                // sotto-entity (e quindi la riga i18n) dall'essere creata —
                // filtrare i dati grezzi qui è l'unico punto che previene
                // davvero una riga i18n "vuota". Di conseguenza una traduzione
                // non può oggi essere CANCELLATA dall'editor (limite noto, vedi
                // CLAUDE.md).
                //
                // IMPORTANTE: la STESSA marshalling automatica si applica anche
                // all'associazione questions_surveys — se il payload include
                // `question.questions_surveys[0]._translations`, patchEntity()
                // la marshalla con la Translate behavior PROPRIA di
                // QuestionsSurveysTable (non letta da nessun ciclo manuale sopra
                // o sotto, che leggono solo la chiave letterale `en`/`fr`).
                // Scoperto dal vivo (2026-07-14): un valore vuoto lì ha
                // SOVRASCRITTO una traduzione EN preesistente e reale con una
                // stringa vuota — non solo "crea una riga vuota", ma può
                // CORROMPERE dati esistenti. Filtrata qui per lo stesso motivo.
                if (isset($data['questions_surveys'][0]['_translations'])) {
                    $data['questions_surveys'][0]['_translations'] = $this->stripEmptyTranslations(
                        $data['questions_surveys'][0]['_translations']
                    );
                }
                $data['_translations'] = $this->stripEmptyTranslations($data['_translations'] ?? null);
                // Bugfix (Part 17, continuazione): find('translations') da solo
                // (vedi sopra) NON basta quando la riga i18n preesistente ha
                // `content = ''`. `QuestionsTable` ha `allowEmptyTranslations =>
                // false` (Part 15): questo aggiunge una condizione `content !=
                // ''` DIRETTAMENTE sull'associazione hasMany `i18n` usata sia da
                // find('translations') per il caricamento di `_translations` SIA
                // dal meccanismo di dedup di salvataggio del Translate behavior
                // (EavStrategy::bundleTranslatedFields()/findExistingTranslations()
                // — stessa associazione, stessa condizione). Una riga i18n
                // preesistente ma con contenuto vuoto è quindi invisibile a
                // ENTRAMBI i lati, indipendentemente da get() vs
                // find('translations'): quando l'utente compila per la prima
                // volta quella traduzione (valore NON vuoto in arrivo), l'ORM
                // non può individuare la riga esistente e tenta sempre un
                // INSERT, che collide con essa (SQLSTATE 23000 duplicate entry).
                // Verificato empiricamente con test dedicato prima di questo fix
                // (fallisce senza, passa con). Soluzione: per ogni traduzione
                // ancora presente nel payload dopo lo skip-empty sopra,
                // aggiorniamo qui DIRETTAMENTE l'eventuale riga i18n
                // preesistente tramite la tabella I18n "nuda" (senza Translate
                // behavior, quindi senza il filtro content != '') e la
                // rimuoviamo da $data['_translations'] così patchEntity()/save()
                // non tenta più di marshallarla: il Translate behavior gestisce
                // da solo, correttamente, solo le traduzioni genuinamente nuove
                // (nessuna riga preesistente => nessun rischio di collisione).
                if (!empty($data['id']) && !empty($data['_translations'])) {
                    $data['_translations'] = $this->syncExistingTranslationRows(
                        (int)$data['id'],
                        'Questions',
                        $data['_translations']
                    );
                }
                $question = $this->Questions->patchEntity($question, $data);

                // Belt-and-suspenders (vedi nota sopra): anche se `_locale`
                // non sopravvive più nel payload grezzo, forziamo
                // esplicitamente la entity — e la sua associazione
                // questions_surveys[0], se presente — sulla locale di
                // default DOPO patchEntity(). Così un `_locale` che fosse
                // comunque sopravvissuto (es. un futuro campo
                // mass-assignabile diverso, o un refactor di questo metodo)
                // non può più dirottare il save canonico verso una riga i18n.
                $question->set('_locale', $defaultLocale, ['setter' => false]);
                if (isset($question['questions_surveys'][0])) {
                    $question['questions_surveys'][0]->set('_locale', $defaultLocale, ['setter' => false]);
                }

                // Gestione della traduzione: array [en|fr|de] => ['campo' => traduzione, ...].
                // Part 11: esteso da solo 'en' a 'en'+'fr'. Ridondante con il
                // marshalling automatico di cui sopra (stessa sotto-entity,
                // stesso valore) ma innocuo — mantenuto per coerenza con la
                // struttura preesistente del metodo.
                foreach (['en', 'fr', 'de'] as $lang) {
                    if (!isset($data['_translations'][$lang])) {
                        continue;
                    }
                    foreach ($data['_translations'][$lang] as $field => $value) {
                        $question->translation($lang)->$field = $value;
                    }
                }
                // Tutta la gestione questions_surveys (join per-survey) si applica SOLO
                // se il payload la include. Una patch globale della sola domanda (es.
                // rename della description di una sezione: {id, type:'section',
                // description}) non tocca alcuna riga di collegamento.
                $hasJoinData = isset($data['questions_surveys'][0]);

                // NOTE (Part 11): questo blocco legge una chiave letterale
                // dell'entity ($question['questions_surveys'][0]['en'/'fr']), NON
                // $data['_translations'][$lang] come sopra. Verificato dal vivo con
                // una save reale (payload con questions_surveys[0].fr = {...}): il
                // blocco SI attiva — il marshaller di CakePHP imposta qualunque
                // chiave non mappata dell'array associato come proprietà dinamica
                // dell'entity, quindi isset(...) risulta true e la traduzione viene
                // scritta. Non è quindi "codice morto" in senso stretto. Però il
                // payload REALE inviato dal frontend (question.service.js/edit.vue)
                // non popola mai questa chiave letterale — annida sempre le
                // traduzioni sotto `_translations` — quindi nell'uso applicativo
                // normale questo ramo è oggi IRRAGGIUNGIBILE. Esteso a 'fr'/'de' per
                // coerenza/difesa in profondità nel caso un payload futuro la
                // valorizzi (es. una chiamata API diretta).
                foreach (['en', 'fr', 'de'] as $lang) {
                    if (!$hasJoinData || !isset($question['questions_surveys'][0][$lang])) {
                        continue;
                    }
                    foreach ($question['questions_surveys'][0][$lang] as $field => $value) {
                        if (trim((string)$value) === '') {
                            continue;
                        }
                        $question['questions_surveys'][0]->translation($lang)->$field = $value;
                    }
                }

                // salva/aggiorna il relativo record questions_surveys
                // $connection = ConnectionManager::get('default');

                // $results = $connection->execute('SELECT id FROM questions_surveys WHERE survey_id = :survey_id AND question_id = :question_id', [
                //     'survey_id' => $joinData['survey_id'],
                //     'question_id' => $question['id'],
                // ])->fetchAll('assoc');
                // $this->Questions->QuestionsSurveys->find
                // // verifica che l'id sia corretto
                // if (!empty($results)) {
                //     if (!empty($joinData['id'])) {
                //         if ($joinData['id'] != $results[0]['id']) { // check di sicurezza
                //             throw new \Exception("L'associazione con la domanda non è corretta");
                //         }
                //     }
                //     $joinData['id'] = $results[0]['id']; // in realtà è solo 1 sempre
                // }

                // if (empty($joinData['question_id'])) {  // inutile, ma per sicurezza
                //     $joinData['question_id'] = empty($question['id']) ? 1 : $question['id'];
                // }

                if ($hasJoinData && empty($question['questions_surveys'][0]['id'])) { // insert
                    // normalizza "compulsory" e "hidden"
                    // $joinData['compulsory'] = $joinData['compulsory'] ? 1 : 0;
                    // $joinData['hidden'] = $joinData['hidden'] ? 1 : 0;
                    // Part 11: $lang non è più una singola variabile di scope (loop
                    // en/fr qui sopra) — insertQuestionSurvey() ora itera entrambe
                    // le lingue internamente, quindi il valore passato qui non è
                    // più significativo; mantenuto per compatibilità di firma.
                    $question = $this->insertQuestionSurvey($question, 'en');
                }
                //  else {
                //     // rimuovi compulsory e hidden (che sono gestiti a parte), salvo in pratica solo la section_id
                //     // normalizza "compulsory" e "hidden"
                //     $joinData['compulsory'] = $joinData['compulsory'] ? 1 : 0;
                //     $joinData['hidden'] = $joinData['hidden'] ? 1 : 0;
                //     $this->updateQuestionSurvey($joinData, $survey_id, $question_id, $lang, $globalmodification);
                // }

                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }

                //Se la domanda è proprio origine spostamenti, creo le domande che sono impostate in Questions_spos oppure fa update
                if (isset($data['id']) && $data['id'] == Configure::read('Questions.origine_spostamenti')) {
                    $q_maps = Configure::read('Questions_spos');
                    foreach ($q_maps as $q_map_id) {
                        // salva/aggiorna il relativo record questions_surveys
                        //$joinData = $data["_joinData"];

                        if (!empty($q_map_id)) {
                            // $question = $this->Questions->find('translations')->where(['id'=>$q_map_id]);
                            $question_map = $this->Questions->find('translations')->where(['Questions.id' => $q_map_id])->contain([
                                'QuestionsSurveys' => function ($query) use ($survey_id) {
                                    return $query->find('translations')->where(['survey_id' => $survey_id]);
                                },
                            ])->first();
                            if (empty($question_map)) {
                                $msg = "QuestionsUpdate: Impossibile aggiornare la domanda $q_map_id, per l'utente $userId, dell'azienda $company_id";
                                Log::write('error', $msg);
                                // throw new \Exception("Domanda {$q_map_id} non trovata");
                            } else {
                                // $connection = ConnectionManager::get('default');
                                // $results = $connection->execute('SELECT id FROM questions_surveys WHERE survey_id = :survey_id AND question_id = :question_id', [
                                //     'survey_id' => $joinData['survey_id'],
                                //     'question_id' => $question['id'],
                                // ])->fetchAll('assoc');
                                // // verifica che l'id sia corretto
                                // if (!empty($results)) {
                                //     $joinData['id'] = $results[0]['id']; // in realtà è solo 1 sempre
                                // } else {
                                //     $joinData['id'] = null;
                                // }

                                // $joinData['question_id'] = $question['id'];

                                if (empty($question_map['questions_surveys'][0]['id'])) { // insert
                                    // normalizza "compulsory" e "hidden"
                                    // $joinData['compulsory'] = 1;
                                    // $joinData['hidden'] = 1;
                                    // $connection->insert('questions_surveys', $joinData);
                                    $question_map['questions_surveys'][0] = $this->Questions->QuestionsSurveys->newEmptyEntity();
                                    $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                                    $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                                    $question_map['questions_surveys'][0]['hidden'] = $question_map['hidden'] != null ? $question_map['hidden'] : 1;
                                    $question_map['questions_surveys'][0]['compulsory'] = $question_map['compulsory'] != null ? $question_map['compulsory'] : 0;
                                    // Part 11: vedi nota sopra, $lang non è più una
                                    // singola variabile di scope qui.
                                    $question_map = $this->insertQuestionSurvey($question_map, 'en');
                                } else {
                                    $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                                }
                                // else {
                                //     // rimuovi compulsory e hidden (che sono gestiti a parte), salvo in pratica solo la section_id
                                //     $joinData['compulsory'] = 1;
                                //     $joinData['hidden'] = 1;
                                //     $this->updateQuestionSurvey($joinData, $survey_id, $question_id,$lang,$globalmodification);

                                //     if(!$globalmodification){
                                //         $this->updateQuestionSurvey($joinData, $survey_id, $question_id,$lang,$globalmodification);
                                //     }
                                //     //$connection->update('questions_surveys', $joinData, ['id' => $joinData['id']]);
                                // }

                                if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                                }
                            }
                        }
                    }
                }
            } catch (HttpException $e) {
                // 403/404 raised by the authorization guards above must reach the
                // client as a real HTTP error, not be swallowed into errorMsg.
                throw $e;
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    /**
     * Part 11 (i18n en+fr): strips empty/whitespace field VALUES out of a raw
     * `_translations` payload (`[lang => [field => value]]`) before it reaches
     * `patchEntity()`. Necessary because the Translate behavior's
     * `buildMarshalMap()` auto-marshals `_translations` INSIDE `patchEntity()`,
     * creating a translation sub-entity for every language key present
     * regardless of field content — a skip-empty check applied only AFTER
     * `patchEntity()` is too late to stop that sub-entity (and its i18n row)
     * from being persisted on save. `SurveysController::edit()` has its own
     * private copy of this same logic (no shared base class for it).
     *
     * @param mixed $translations The raw `_translations` value from request data.
     * @return array The filtered translations, with any language left empty
     *   after filtering removed entirely.
     */
    private function stripEmptyTranslations($translations): array
    {
        if (!is_array($translations)) {
            return [];
        }
        foreach ($translations as $lang => $fields) {
            if (!is_array($fields)) {
                unset($translations[$lang]);
                continue;
            }
            foreach ($fields as $field => $value) {
                if (trim((string)$value) === '') {
                    unset($translations[$lang][$field]);
                }
            }
            if (empty($translations[$lang])) {
                unset($translations[$lang]);
            }
        }

        return $translations;
    }

    /**
     * Part 17: directly UPDATEs any i18n row that already exists for the given
     * (model, foreign_key, locale, field) — bypassing the Translate behavior's
     * `i18n` association entirely, which (when the table sets
     * `allowEmptyTranslations => false`, as `QuestionsTable` does — see Part 15)
     * carries a baked-in `content != ''` condition. That condition makes an
     * EXISTING but empty-content i18n row invisible to BOTH `find('translations')`
     * reads and the behavior's own save-time dedup
     * (`EavStrategy::bundleTranslatedFields()`/`findExistingTranslations()`,
     * the same association). When a user later fills in a translation the ORM
     * therefore always attempts an INSERT for that row and collides with the
     * pre-existing (empty) one — `SQLSTATE[23000] Duplicate entry
     * '<locale>-<model>-<foreign_key>-<field>'`. Verified live against
     * `demo_5t_moma_angelcake`: the special catalog questions (560/561/562)
     * carry exactly this shape (empty 'en' `description`/`long_description`
     * i18n rows). Any (lang, field) pair whose row is updated here is removed
     * from the returned array so `patchEntity()`/`save()` never re-marshals it
     * through the Translate behavior — genuinely NEW translations (no
     * pre-existing row) are left untouched and continue to flow through the
     * normal ORM path, which already handles that case correctly.
     *
     * @param int $foreignKey The owning record's id (`i18n.foreign_key`).
     * @param string $model The i18n `model` value (e.g. `'Questions'`).
     * @param array $translations `_translations` shaped as `[lang => [field => value]]`,
     *   already passed through `stripEmptyTranslations()`.
     * @return array The same shape, with any (lang, field) pair that already had
     *   an existing row removed (that row was updated directly).
     */
    private function syncExistingTranslationRows(int $foreignKey, string $model, array $translations): array
    {
        $i18n = TableRegistry::getTableLocator()->get('I18n');
        foreach ($translations as $lang => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $existing = $i18n->find()
                    ->where([
                        'model' => $model,
                        'foreign_key' => $foreignKey,
                        'locale' => $lang,
                        'field' => $field,
                    ])
                    ->first();
                if (empty($existing)) {
                    continue;
                }
                $existing->content = (string)$value;
                if (!$i18n->save($existing)) {
                    throw new \Exception("Impossibile aggiornare la traduzione $lang/$field per $model#$foreignKey");
                }
                unset($translations[$lang][$field]);
            }
            if (empty($translations[$lang])) {
                unset($translations[$lang]);
            }
        }

        return $translations;
    }

    //Aggiorna la tabella di passaggio questions_surveys

    private function insertQuestionSurvey($question, $lang)
    {
        // $this->QuestionSurveys = $this->getTableLocator()->get('QuestionsSurveys');

        // Belt-and-suspenders (stessa vulnerabilità `_locale` documentata in
        // editFromSurvey()): questo metodo scrive campi CANONICI (options,
        // conditions, description, long_description) sull'associazione
        // questions_surveys[0] appena creata — se quella entity portasse
        // ancora una `_locale` diversa dalla default (es. un futuro chiamante
        // che non passa dal guard di editFromSurvey), il save la
        // instraderebbe come riga i18n invece che come colonna base. Forziamo
        // qui la locale di default indipendentemente da chi ha chiamato
        // questo metodo.
        if (isset($question['questions_surveys'][0])) {
            $question['questions_surveys'][0]->set('_locale', I18n::getDefaultLocale(), ['setter' => false]);
        }
        // $qs = $this->QuestionSurveys->newEmptyEntity();
        // $question['questions_surveys'][0]->survey_id = $survey_id;
        $question['questions_surveys'][0]['question_id'] = $question['id'];
        // $question['questions_surveys'][0]->section_id = $question['section_id'];
        // $question['questions_surveys'][0]->weight = $question['weight'];
        // $question['questions_surveys'][0]->hidden = $question['hidden'];
        $question['questions_surveys'][0]->compulsory_answer = $question['compulsory_answer'];
        $question['questions_surveys'][0]['options'] = $question['options'];
        $question['questions_surveys'][0]['conditions'] = $question['conditions'];
        $question['questions_surveys'][0]['description'] = $question['description'];
        $question['questions_surveys'][0]['long_description'] = $question['long_description'];

        // $question_trans = $this->QuestionSurveys->find('translations')
        //     ->where(['id' => $joinData['id']])
        //     ->first();
        // Part 11: la riga `$lang = 'en';` qui shadowava il parametro $lang del
        // metodo (bug reale — la lingua richiesta dal chiamante era ignorata).
        // Rimossa: ora si itera su tutte le lingue supportate, salvando le
        // traduzioni della DOMANDA (Questions._translations) sulla riga
        // questions_surveys appena creata. Skip-empty per non creare righe i18n
        // vuote (una traduzione non può oggi essere cancellata dall'editor).
        foreach (['en', 'fr', 'de'] as $lang) {
            if (!isset($question['_translations'][$lang])) {
                continue;
            }
            // if array
            if (!is_array($question['_translations'][$lang])) {
                $translations = $question['_translations'][$lang]->toArray();
            } else {
                $translations = $question['_translations'][$lang];
            }
            foreach ($translations as $field => $value) {
                if (trim((string)$value) === '') {
                    continue;
                }
                $question['questions_surveys'][0]->translation($lang)->$field = $value;
            }
        }

        return $question;
        // if (!$this->QuestionSurveys->save($qs)) {
        //     $msg = "Impossibile salvare le opzioni per questo questionario";
        // } else {
        //     $msg = "Salvataggio opzioni per questo questionario ok";
        // }
        // $this->set('msg', $msg);
        // $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    //Elimina le domande doppie ed inutili dal database

    public function pulisciDoppie()
    {
        //delete FROM questions where id not in  (select question_id from answers);
        $subquery = $this->Questions->find()
                           ->select(['Questions.id']);
        $subquery1 = $this->fetchTable('questions_surveys')->find()
                           ->select(['question_id']);

        // $result = $this->Questions->Answers->find()->where(['id NOT IN' => $subquery]);
        /* $result = $this->Questions->find()
                    ->where(['id NOT IN' => $subquery]); */
        //print_r($result->toArray());
        // $result = $this->Questions->find()
        //             ->where(['id NOT IN' => $subquery1]);


        try {
            $result1 = $this->Questions->deleteAll(['id NOT IN' => $subquery1]);
            $this->Flash->success("La pulizia è stata effettuata. $result1 domande cancellate non associato ad alcun questionario.");
            $result = $this->Questions->Answers->deleteAll(['question_id NOT IN' => $subquery]);
            Cache::clearGroup('dashboard');
            $this->Flash->success("La pulizia è stata effettuata. $result risposte di domande cancellate.");
        } catch (\Exception $e) {
            $this->Flash->error('Errore durante la cancellazione delle domande doppie.');
        }

        $q = $this->Questions->find();
        $qs = $q->select(['name','count' => $q->func()->count('name')])->group('name')
        ->having(['count >' => 1]);
        $i = 1;
        $q_count_1 = $this->Questions->find()->select(['name','count' => $q->func()->count('name')])->group('name')
        ->having(['count >' => 1])->toArray();
        $this->Flash->success('La pulizia è stata effettuata. Il nome di ' . array_sum(array_column($q_count_1, 'count')) . ' domande duplicate fu modificato.');
        foreach ($qs as $r) {
            if ($r->name == 'Nuova Domanda' || $r->name == '2-01' || $r->name == '2.03' || $r->name == '2.01' || $r->name == 'ID: 188') {
                // $q_dups=Configure::read("Questions_dup");
                //     foreach($q_dups as $q_dup){
                //         $question= $this->Questions->get($q_dup);

                //     }
                $questions = $this->Questions->find()->where(['name ' => $r->name]);
                foreach ($questions as $question) {
                    // $i=$i+1;
                    $name_split = explode(' ', $question->description);

                    $name = '';
                    foreach ($name_split as $name_word) {
                        if (strpos($name_word, "'")) {
                            $name_word = substr($name_word, strpos($name_word, "'") + 1, 20);
                        }
                        $name_word = preg_replace('/[^A-Za-z0-9\-]/', '', $name_word);
                        if (strlen($name_word) > 3) {
                            $name = strtolower($name . $name_word);

                            if (strlen($name) < 20) {
                                $name = $name . '-';
                            } else {
                                break;
                            }
                        }
                    }
                    if (substr(substr($name, 0, 20), -1, 1) == '-') {
                        $name = substr(substr($name, 0, 20), 0, -1);
                    }

                    if (is_null($this->Questions->find()->where(['name' => substr($name, 0, 20)])->first())) {
                        $question->name = substr($name, 0, 20);
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    } else {
                        while (!is_null($this->Questions->find()->where(['name' => substr($name, 0, 19) . $i])->first())) {
                            $i = $i + 1;
                        }
                        $question->name = substr($name, 0, 19) . $i;
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $i = 1;
                    }
                }
            } else {
                $questions = $this->Questions->find()->where(['name ' => $r->name]);
                if ($r->count > 2) {
                    foreach ($questions as $question) {
                        $question->name = substr(strtolower(str_replace(' ', '-', $r->name)), 0, 18) . $i;
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $i = $i + 1;
                    }
                    $i = 1;
                } else {
                    $question = $questions->first();
                    $question->name = substr(strtolower(str_replace(' ', '-', $r->name)), 0, 19) . $i;
                    if (!$this->Questions->save($question)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }
            }

            // $articles->save($entity);
        }
        // $questions= $this->Questions->find()
        //             ->where([['name ' => $this->Questions->find()->identifier('name')],['id <' => $this->Questions->find()->identifier('id')]]);

        $q_ch = $this->Questions->find()->where(['name not REGEXP' => '^[a-z].*$']);
        $q_count = $this->Questions->find()->select(['count' => $this->Questions->find()->func()->count('*')])
        ->where(['name not REGEXP' => '^[a-z].*$'])->first();
        $this->Flash->success("La pulizia è stata effettuata. Il nome di $q_count->count domande fu modificato.");

        foreach ($q_ch as $question) {
            $name_split = explode(' ', $question->description);

            $name = '';
            foreach ($name_split as $name_word) {
                if (strpos($name_word, "'")) {
                    $name_word = substr($name_word, strpos($name_word, "'") + 1, 20);
                }
                $name_word = preg_replace('/[^A-Za-z0-9\-]/', '', $name_word);
                if (strlen($name_word) > 3) {
                    $name = strtolower($name . $name_word);

                    if (strlen($name) < 20) {
                        $name = $name . '-';
                    } else {
                        break;
                    }
                }
            }
            if (substr(substr($name, 0, 20), -1, 1) == '-') {
                $name = substr(substr($name, 0, 20), 0, -1);
            }

            if (is_null($this->Questions->find()->where(['name' => substr($name, 0, 20)])->first())) {
                $question->name = substr($name, 0, 20);
                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
            } else {
                $i = 1;
                while (!is_null($this->Questions->find()->where(['name' => substr($name, 0, 19) . $i])->first())) {
                    $i = $i + 1;
                }
                $question->name = substr($name, 0, 19) . $i;
                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
            }

            // $articles->save($entity);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Union method
     *
     * @param null $remove_question_id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function union($remove_question_id, $destination_question_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $remove_question_id = $this->request->getData('remove_question_id');
            $destination_question_id = $this->request->getData('destination_question_id');
            $this->UnionRollback = TableRegistry::getTableLocator()->get('UnionRollback');
            if (
                empty($this->UnionRollback->find()->where(['OR' => [['destination_question_id' => $destination_question_id],
                ['remove_question_id' => $destination_question_id]]])->toarray())
                && ($remove_question_id != $destination_question_id)
            ) {
                $this->QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
                $subquery = $this->QuestionsSurveys->find();
                $subquery
                ->select(['survey_id'])
                ->where(['question_id' => $destination_question_id]);
                $r_destination = $subquery->toArray();

                $query = $this->QuestionsSurveys->find();
                $query
                ->select(['survey_id'])
                ->where(['question_id' => $remove_question_id]);
                // debug($query);
                $r_remove = $query->toArray(); // Execute the query and get the result set
                $result = array_intersect($r_destination, $r_remove);
                if (empty($result)) {
                    $answers = $this->Questions->Answers->find()->where(['question_id' => $remove_question_id]);
                    $answers_destinations = $this->Questions->Answers->find()->where(['question_id' => $destination_question_id]);
                    if (!empty($answers->toArray()) && !empty($answers_destinations->toArray())) {
                        $query = $this->QuestionsSurveys->find();
                        $query->select([
                            'id_questions' => $query->func()->group_concat(['QuestionsSurveys.id' => 'identifier'])]);
                        $query->where(['question_id' => $remove_question_id]);
                        $query->group(['question_id']);

                        // Execute the query and fetch the result
                        $result = $query->toArray();

                        // Access the 'id_questions' column from the result
                        $idQuestions = $result[0]['id_questions'];
                        $query = $this->Questions->Answers->find();
                        $query->select([
                            'id_questions' => $query->func()->group_concat(['id' => 'identifier'])]);
                        $query->where(['question_id' => $remove_question_id]);
                        $query->group(['question_id']);
                        // Execute the query and fetch the result
                        $result = $query->toArray();

                        // Access the 'id_questions' column from the result
                        $idAnswers = $result[0]['id_questions'];
                        $q = $this->Questions->get($remove_question_id)->toArray();
                        $q_des = $this->Questions->get($destination_question_id);
                        $now = FrozenTime::now();
                        $union = $this->UnionRollback->newEntity([
                            'date' => $now,
                            'remove_question_id' => $remove_question_id,
                            'destination_question_id' => $destination_question_id,
                            'questions_survey_id' => $idQuestions,
                            'answers_id' => $idAnswers,
                            'remove_question' => $q,
                            'name_union_questions' => 'Remove: ' . $q['name'] . ' / Destination: ' . $q_des->name . '',
                        ]);

                        if ($this->UnionRollback->save($union)) {
                            $this->Flash->success(__('The union has been saved.'));
                        } else {
                            $this->Flash->error(__('The union could not be saved. Please, try again.'));
                        }
                        $qs_survey = $this->QuestionsSurveys->find()->where(['question_id' => $remove_question_id]);
                        foreach ($qs_survey as $qs) {
                            $qs->question_id = $destination_question_id;
                            if (!$this->QuestionsSurveys->save($qs)) {
                                throw new \Exception('Si è verificato un errore durante il salvataggio');
                            }
                        }

                        foreach ($answers as $a) {
                            $existingAnswer = $this->Questions->Answers->find()
                            ->where([
                                'question_id' => $destination_question_id,
                                'user_id' => $a->user_id,
                            ])
                            ->first();
                            if ($existingAnswer) {
                                // deleted answer
                                $this->Questions->Answers->delete($a);
                            } else {
                                // update answer
                                $a->question_id = $destination_question_id;
                                // $a->answer= array_map('trim', $a->answer);
                                if (!$this->Questions->Answers->save($a)) {
                                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                                }
                            }
                        }
                        $question = $this->Questions->get($remove_question_id);
                        if ($this->Questions->delete($question)) {
                            $this->Flash->success(__('The question has been deleted.'));
                        } else {
                            $this->Flash->error(__('The question could not be deleted. Please, try again.'));
                        }
                        // delete conditions of remove question
                         // search if the question is used in any conditions of other questions where conditions is a json
                        $conditions = $this->Questions->find()
                        ->where(function ($exp, $query) use ($remove_question_id) {
                            return $exp->add($query->newExpr('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question")) = ' . (int)$remove_question_id));
                        });
                        // debug($conditions); die;
                        $conditionsCount = $conditions->count();
                        $this->Flash->success(__("Cleared conditions for {$conditionsCount} questions."));
                        // // save conditions as empty
                        foreach ($conditions as $condition) {
                            $condition->conditions = '';
                            $this->Questions->save($condition);
                        }

                        // empty conditions of the questionsurveys
                        $questionSurveys = $this->Questions->QuestionsSurveys->find()
                            ->where(function ($exp, $query) use ($remove_question_id) {
                                return $exp->add($query->newExpr('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question")) = ' . (int)$remove_question_id));
                            });
                        // count the number of questionsurveys with conditions
                        $questionSurveysCount = $questionSurveys->count();
                        $this->Flash->success(__("Cleared conditions for {$questionSurveysCount} questionsurveys."));
                        foreach ($questionSurveys as $questionSurvey) {
                            $questionSurvey->conditions = '';
                            $this->Questions->QuestionsSurveys->save($questionSurvey);
                        }

                        return $this->redirect(['action' => 'index']);
                    } else {
                        $questions = $this->Questions->find();
                        $this->Flash->error(__('This union is not possible because the questions have not answers. Please, try again.'));
                        $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                        $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
                    }
                } else {
                    $questions = $this->Questions->find();
                    $this->Flash->error(__('This union is not possible because the questions have the same survey ' . implode(',', $result) . '. Please, try again.'));
                    $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                    $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
                }
            } else {
                $questions = $this->Questions->find();
                $this->Flash->error(__('This union already exists or the union is not valid. Please, try again.'));
                $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
            }
        } else {
            $questions = $this->Questions->find();
            $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
            $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
        }
    }

    /**
     * Rollback method
     *
     * @param null $destination_question_id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function rollback($destination_question_id, $rollback_questions_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->UnionRollback = TableRegistry::getTableLocator()->get('UnionRollback');

        if ($this->request->is(['patch', 'post', 'put'])) {
            // $remove_question_id = $this->request->getData('remove_question_id');
            $rollback_questions_id = $this->request->getData('rollback_questions_id');

            $union = $this->UnionRollback->find()->where(['id' => $rollback_questions_id])->first();
            if ($union) {
                $q = $this->Questions->newEntity($union->remove_question);
                if (!$this->Questions->save($q)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
                foreach (explode(',', $union->questions_survey_id) as $id) {
                    $this->QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
                    $q_survey = $this->QuestionsSurveys->find()->where(['QuestionsSurveys.id' => $id])->first();
                    $q_survey->question_id = $union->remove_question_id;
                    if (!$this->QuestionsSurveys->save($q_survey)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }
                foreach (explode(',', $union->answers_id) as $id) {
                    $answer = $this->Questions->Answers->find()->where(['id' => $id])->first();
                    $answer->question_id = $union->remove_question_id;
                    if (!$this->Questions->Answers->save($answer)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }

                if ($this->UnionRollback->delete($union)) {
                    $this->Flash->success(__('The union has been deleted.'));
                } else {
                    $this->Flash->error(__('The union could not be deleted. Please, try again.'));
                }

                $unionrollback = $this->UnionRollback->find();
                $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
                $this->set(compact('unionrollback', 'union_id', 'rollback_questions_id'));
                $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','rollback_questions_id']);
            } else {
                $unionrollback = $this->UnionRollback->find();
                $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
                $this->set(compact('unionrollback', 'union_id', 'destination_question_id'));
                $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','destination_question_id']);
            }
        } else {
            $unionrollback = $this->UnionRollback->find();
            $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
            $this->set(compact('unionrollback', 'union_id', 'rollback_questions_id'));
            $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','rollback_questions_id']);
        }
    }

    // create a funtion to correct the conditions of the questions

    public function correctConditions()
    {
        try {
            $questions = $this->Questions->find()->contain('QuestionsSurveys')
            // ->contain([
            //     'QuestionsSurveys' => function ($query) {
            //         return $query->find('translations');
            //     }
            // ]);
            ->where(['conditions is not' => null,
                    'id NOT IN' =>  Configure::read('Questions_spos'),
                    'id IS NOT' =>  Configure::read('Questions.origine_spostamenti')]);
                 $count = 0;
            foreach ($questions as $question) {
                if (empty($question->conditions)) {
                    continue;
                }
                $conditions = $question->conditions;
                if (!is_array($conditions)) {
                    $conditions = json_decode($conditions, true);
                }
                $question_condition = $this->Questions->find()->where(['Questions.id' => $conditions['question']]);
                $options = $question_condition->first()->options;
                $values = $conditions['value'];
                // from arrays options and values find indexes
                $indices = [];

                foreach ($values as $value) {
                    $index = array_search($value, $options);
                    if ($index !== false) {
                        $indices[] = $index;
                    }
                }
                if (!empty($indices)) {
                    $conditions['value'] = $indices;

                    $question->conditions = $conditions;
                    if (!$this->Questions->save($question)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                    $count++;
                    foreach ($question->questions_surveys as $qs) {
                        $qs->conditions = $question->conditions;

                        if (!$this->Questions->QuestionsSurveys->save($qs)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    }
                }

                // $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->where(['question_id' => $question->id]);
            }
                 $this->Flash->success(__('The conditions have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The conditions could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // correct empty options, description and long_description in questions_surveys

    public function correctQuestionsSurveys($id = null)
    {
        // Stessa guardia di unused()/special()/originFilters(): questo metodo
        // legge `options`/`description`/`long_description` CANONICI da
        // Questions per riscriverli su QuestionsSurveys - sotto una locale
        // diversa dalla default il Translate behavior sostituirebbe quei
        // valori con la riga i18n tradotta (es. `options` come stringa
        // pipe-delimited al posto dell'array), e save() li persisterebbe
        // corrotti nella colonna base. Vedi
        // docs/dev-notes/questions-surveys-options-string-corruption.md.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        try {
            $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->where([

                    'question_id' => (int)$id,

            ]);
            $count = 0;
            foreach ($questions_surveys as $qs) {
                    $question = $this->Questions->find('translations')->where(['Questions.id' => $qs['question_id']])->first();

                    $qs['options'] = $question['options'];
                    $qs['description'] = $question['description'];
                    $qs['long_description'] = $question['long_description'];
                    // $qs['section_id'] = $question['section_id'];
                    // $qs['weight'] = $question['weight'];
                    $qs['conditions'] = $question['conditions'];
                    $lang = 'en';
                if (isset($question['_translations'][$lang])) {
                    // if array
                    if (!is_array($question['_translations'][$lang])) {
                        $translations = $question['_translations'][$lang]->toArray();
                    } else {
                        $translations = $question['_translations'][$lang];
                    }
                    foreach ($translations as $field => $value) {
                        // if ($field == 'options') {
                        //     $qs->translation($lang)->$field = json_encode($value);
                        // } else {
                            // $qs->_translations[$lang]->$field = $value;
                            $qs->translation($lang)->$field = $value;
                        // }
                    }
                }

                if (!$this->Questions->QuestionsSurveys->save($qs)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
                    $count++;
            }
            $this->Flash->success(__('The questions surveys have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // delete questionsurveys from deleted questions

    public function deleteQuestionsSurveys()
    {
        try {
            $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->contain('Questions')
            ->where(['Questions.id IS' => null]);
            $count = 0;
            foreach ($questions_surveys as $qs) {
                if (!$this->Questions->QuestionsSurveys->delete($qs)) {
                    throw new \Exception('Si è verificato un errore durante la cancellazione');
                }
                $count++;
            }
            $this->Flash->success(__('The questions surveys have been deleted. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be deleted. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // add Questions_spos to surveys that don't have it

    public function addQuestionsSpos()
    {
        try {
            $questions = $this->Questions->QuestionsSurveys->find()
            ->where(['question_id' =>  Configure::read('Questions.origine_spostamenti')]);
            $count = 0;
            foreach ($questions as $question) {
                $q_maps = Configure::read('Questions_spos');
                $survey_id = $question->survey_id;
                // find Questions_spos that are not in the survey
                foreach ($q_maps as $q_map_id) {
                    $question_map = $this->Questions->find('translations')->where(['Questions.id' => $q_map_id])->contain([
                        'QuestionsSurveys' => function ($query) use ($survey_id) {
                            return $query->find('translations')->where(['survey_id' => $survey_id]);
                        },
                    ])->first();
                    if (empty($question_map['questions_surveys'][0])) {
                        // $question_map = $this->Questions->find('translations')->where(['id'=>$q_map_id])->first();
                        $question_map['questions_surveys'][0] = $this->Questions->QuestionsSurveys->newEmptyEntity();
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        $question_map['questions_surveys'][0]['weight'] = $question['weight'];
                        $question_map['questions_surveys'][0]['hidden'] = 1;
                        $question_map['questions_surveys'][0]['compulsory'] = 1;
                        $question_map = $this->insertQuestionSurvey($question_map, 'en');
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $count++;
                    } else {
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    }
                }
            }
            $this->Flash->success(__('The questions surveys have been added. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be added. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // correct translations of questions

    public function correctTranslations()
    {
        try {
            $lang = 'en';
            I18n::setLocale($lang);
            // $questions = $this->Questions->find()->contain([
            //         'QuestionsSurveys' => function ($query) {
            //             return $query->find();
            //         }
            //     ])->where(['options is not' => null]);
            $questions = $this->Questions->find()->contain('QuestionsSurveys')->where(['Questions.options is not' => null]);
            $count = 0;
            foreach ($questions as $question) {
                // if not array
                if (!is_array($question['options'])) {
                    $optionsArray = json_decode($question['options'], true);
                    // if array
                    if (is_array($optionsArray)) {
                        $question['options'] = implode('|', $optionsArray);
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $count++;
                        foreach ($question->questions_surveys as $qs) {
                            $qs['options'] = $question['options'];

                            if (!$this->Questions->QuestionsSurveys->save($qs)) {
                                throw new \Exception('Si è verificato un errore durante il salvataggio');
                            }
                        }
                    }
                }
            }
            $this->Flash->success(__('The translations have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The translations could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // change state moma_area field

    public function changeStateMomaArea()
    {
        try {
            $data = $this->request->getData();
            $state = $data['enabled'];
            $question_id = $data['question_id'];
            $question = $this->Questions->changeState($question_id, $state);
            $errorMsg = false;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }

        $this->set(compact('question', 'errorMsg'));
        $this->viewBuilder()->setOption('serialize', ['question', 'errorMsg']);
    }

    public function listMapQuestions($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);
        try {
            $questions = $this->Questions->getMapQuestions($survey_id);
            $errorMsg = false;
        } catch (Exception $e) {
            $questions = null;
            $errorMsg = $e->getMessage();
        }

        $this->set(compact('questions', 'errorMsg'));
        $this->viewBuilder()->setOption('serialize', ['questions', 'errorMsg']);
    }
}

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

namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\I18n\I18n;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use Exception;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Surveys Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 * @property \App\Model\Table\AnswersTable&\Cake\ORM\Association\HasMany $Answers
 * @property \App\Model\Table\SurveyDispathConfigsTable&\Cake\ORM\Association\HasOne $SurveyDispatchConfigs
 * @property \App\Model\Table\SurveyParticipantsTable&\Cake\ORM\Association\HasMany $SurveyParticipants
 * @method \App\Model\Entity\Survey newEmptyEntity()
 * @method \App\Model\Entity\Survey newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Survey[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Survey get($primaryKey, $options = [])
 * @method \App\Model\Entity\Survey findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Survey patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Survey[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Survey|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Survey saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SurveysTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Translate', ['fields' => ['welcome']]);

        $this->setTable('surveys');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->belongsToMany('Questions');

        $this->belongsTo('Companies', [
        'foreignKey' => 'company_id',
        ]);
        // office_id: scope 'sede' per i questionari dinamici Azienda/Scuola/Uni
        // (null = scope 'generale' a livello azienda). Vedi
        // findOrCreateCompanyQuestionnaire().
        $this->belongsTo('Offices', [
        'foreignKey' => 'office_id',
        ]);
        $this->belongsTo('Users', [
        'foreignKey' => 'user_id',
        ]);
        $this->hasMany('Answers', [
        'foreignKey' => 'survey_id',
        'dependent' => true,  //In questo modo cancello le risposte quando cancello la survey
        ]);
        $this->hasOne('SurveyDeliveryConfigs', [
        'className' => 'SurveyDeliveryConfigs',
        'foreignKey' => 'survey_id',
        'dependent' => true,
        ]);
        $this->hasMany('SurveyParticipants', [
        'dependent' => true,
        ]);
        $this->belongsToMany('Questions', [
            'through' => 'QuestionsSurveys',
        ]);
        // origins
        $this->hasMany('Origins', [
            'foreignKey' => 'survey_id',
            'dependent' => true,
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
        ->integer('id')
        ->allowEmptyString('id', null, 'create');

        $validator
        ->scalar('name')
        ->maxLength('name', 255)
        ->allowEmptyString('name');

        $validator
        ->scalar('version_tag')
        ->maxLength('version_tag', 45)
        ->allowEmptyString('version_tag');

        $validator
        ->scalar('description')
        ->allowEmptyString('description');

        $validator
        ->date('date')
        ->allowEmptyDate('date');

        $validator
        ->date('start_date')
        ->allowEmptyDate('start_date');

        $validator
        ->date('end_date')
        ->allowEmptyDate('end_date');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['company_id'], 'Companies'));

        return $rules;
    }

    public function importExcel(string $fname)
    {
        $inputFileName = WWW_ROOT . 'Moma/' . $fname;
        $reader = IOFactory::createReaderForFile($inputFileName);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($inputFileName);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, false, false, true);

        return $sheetData;
    }

  // ATTENZIONE! DA USARE SOLO IN FASE DI CREAZIONE DI toId (METODO DISTRUTTIVO!)

    public function cloneQuestions($fromId, $toId)
    {
      // questions_surveys non è facilmente manipolabile perchè non ha entities o tables collegate, usa
    //   // query diretta
    //     $connection = ConnectionManager::get('default');
    //   // 1. cancella le questions di toId (se esistono già)
    //     $connection->delete('questions_surveys', ['survey_id' => $toId]);
    //   // 2. carica le questions di fromId
    //     $results = $connection->execute('SELECT * FROM questions_surveys WHERE survey_id = :survey_id', [
    //     'survey_id' => $fromId,
    //     ])->fetchAll('assoc');
    //   // 3. crea le nuove questions per toId
    //     foreach ($results as $row) {
    //         $row['id'] = null;
    //         $row['survey_id'] = $toId;
    //         $connection->insert('questions_surveys', $row);
    //     }
        $this->Questions->QuestionsSurveys->cloneQuestions($fromId, $toId);
    }

    public function createNewEmptySurvey($company, ?int $year = null)
    {
        $survey = $this->newEntity([
        'name' => $company['name'],
        'company_id' => $company['id'],
        'version_tag' => '',
        'date' => date('Y-m-d'),
        'year' => $year ?? (int)date('Y'),
        ]);
        if ($this->save($survey)) {
            return $survey['id'];
        } else {
            throw new Exception('Errore durante la generazione della survey');
        }
    }

    public function createNewSurveyFromTemplate($company, $template_id, ?int $year = null)
    {
        $survey = $this->newEntity([
        'name' => $company['name'],
        'company_id' => $company['id'],
        'version_tag' => '',
        'date' => date('Y-m-d'),
        'year' => $year ?? (int)date('Y'),
        ]);
        if ($this->save($survey)) {
            $this->cloneQuestions($template_id, $survey['id']);

            return $survey['id'];
        } else {
            throw new Exception('Errore durante la generazione della survey');
        }
    }

    public function fromCompanyId($company_id, $year = null)
    {
        $conditions = ['company_id' => $company_id];
        // Anno opzionale: senza, comportamento storico (primo questionario dell'azienda)
        if (!empty($year)) {
            $conditions['year'] = (int)$year;
        }
        // ORDER BY esplicito: "primo questionario" = id piu' basso, deterministico
        $survey = $this->find()
        ->where($conditions)
        ->order(['Surveys.id' => 'ASC'])
        ->first();
        $survey_id = null;
        if (!empty($survey)) {
            $survey_id = $survey->id;
        }

        return $survey_id;
    }

    public function countParticipants($survey_id)
    {
        $survey_participants_num = $this->SurveyParticipants->find()->where([
        'survey_id' => $survey_id,
        ])->count();

        return $survey_participants_num;
    }

    /**
     * version_tag ammessi per i questionari dinamici di profilo azienda/scuola/
     * università (distinti dal sentinel 'template' usato per i questionari
     * "normali" riutilizzabili). Vedi findOrCreateCompanyQuestionnaire().
     *
     * @var string[]
     */
    public const COMPANY_QUESTIONNAIRE_TYPES = ['Azienda', 'Scuola', 'Uni'];

    /**
     * Scope supportati per un questionario di profilo: 'generale' (azienda,
     * office_id NULL) o 'sede' (una specifica sede, office_id valorizzato).
     *
     * @var string[]
     */
    public const COMPANY_QUESTIONNAIRE_SCOPES = ['generale', 'sede'];

    /**
     * Trova (o crea, clonando dal template configurato) l'istanza di
     * questionario dinamico Azienda/Scuola/Uni per un'azienda (scope
     * 'generale', $officeId null) o per una sua sede (scope 'sede', $officeId
     * valorizzato) in un dato anno.
     *
     * Rende questi questionari omogenei ai questionari "normali": stessa
     * tabella `surveys` (version_tag='Azienda'|'Scuola'|'Uni'), stesso motore
     * di domande dinamiche/risposte (Questions/QuestionsSurveys/Answers) al
     * posto dei vecchi componenti Vue statici + blob JSON in
     * companies.survey/offices.survey.
     *
     * Idempotente: chiamate successive per la stessa tupla
     * (company_id, office_id, version_tag, year) restituiscono sempre la
     * stessa survey, senza duplicati. Alla primissima creazione, se esiste
     * già un blob legacy (companies.survey oppure offices.survey, compilato
     * dai vecchi componenti Vue statici prima della conversione), lo importa
     * come risposte iniziali via seedAnswersFromLegacyBlob() così il nuovo
     * form dinamico si pre-compila invece di apparire vuoto.
     *
     * @param int $companyId Id azienda.
     * @param int|null $officeId Id sede (null = questionario 'generale' a livello azienda).
     * @param string $versionTag Uno tra self::COMPANY_QUESTIONNAIRE_TYPES.
     * @param int $year Anno di riferimento.
     * @return \Cake\Datasource\EntityInterface Riga `surveys` (nuova o già esistente).
     * @throws \InvalidArgumentException Se $versionTag non è uno dei tipi supportati.
     * @throws \RuntimeException Se manca la configurazione del template o il salvataggio fallisce.
     */
    public function findOrCreateCompanyQuestionnaire(
        int $companyId,
        ?int $officeId,
        string $versionTag,
        int $year
    ) {
        if (!in_array($versionTag, self::COMPANY_QUESTIONNAIRE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'version_tag non valido: %s (atteso uno tra: %s)',
                $versionTag,
                implode(', ', self::COMPANY_QUESTIONNAIRE_TYPES)
            ));
        }

        $conditions = [
            'Surveys.company_id' => $companyId,
            'Surveys.version_tag' => $versionTag,
            'Surveys.year' => $year,
        ];
        if ($officeId === null) {
            $conditions['Surveys.office_id IS'] = null;
        } else {
            $conditions['Surveys.office_id'] = $officeId;
        }

        $existing = $this->find()->where($conditions)->order(['created' => 'DESC'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $scope = $officeId === null ? 'generale' : 'sede';
        $templateId = $this->resolveCompanyQuestionnaireTemplateId($versionTag, $scope);
        $company = $this->Companies->get($companyId);

        return $this->getConnection()->transactional(
            function () use ($companyId, $officeId, $versionTag, $year, $scope, $templateId, $company) {
                $surveyName = $company->name ?? 'Azienda #' . $companyId;
                $survey = $this->newEntity([
                    'name' => trim(sprintf('%s - %s %d', $surveyName, $versionTag, $year)),
                    'company_id' => $companyId,
                    'office_id' => $officeId,
                    'version_tag' => $versionTag,
                    'date' => date('Y-m-d'),
                    'year' => $year,
                ]);
                if (!$this->save($survey)) {
                    throw new RuntimeException(__('Errore durante la creazione del questionario {0}', $versionTag));
                }

                $this->cloneQuestions($templateId, $survey->id);

                // Partecipante unico "compilatore" del questionario di profilo: non è
                // un dipendente invitato, mirror di come addAnonParticipant() gestisce
                // le survey senza un vero elenco di partecipanti (SurveysController).
                $this->SurveyParticipants = TableRegistry::getTableLocator()->get('SurveyParticipants');
                $participantId = $this->SurveyParticipants->add([
                    'email' => Text::uuid() . '@email.invalid',
                    'first_name' => 'Questionario',
                    'last_name' => $versionTag,
                ], $survey->id);

                $participant = $this->SurveyParticipants->find()
                    ->where(['id' => $participantId, 'survey_id' => $survey->id])
                    ->first();

                if ($participant !== null) {
                    $legacyBlob = $scope === 'generale'
                        ? (array)($company->survey ?? [])
                        : (array)($this->Offices->get($officeId)->survey ?? []);
                    $this->seedAnswersFromLegacyBlob((int)$survey->id, (string)$participant->user_id, $legacyBlob);
                }

                return $survey;
            }
        );
    }

    /**
     * Id del partecipante (survey_participants.id) del questionario di
     * profilo Azienda/Scuola/Uni identificato da $surveyId — sempre uno
     * solo, creato da findOrCreateCompanyQuestionnaire(). Usato dal
     * controller per restituire al frontend {survey_id, participant_id}
     * pronti per il generico AnswersController::update().
     *
     * @param int $surveyId Id survey.
     * @return string|null
     */
    public function resolveCompanyQuestionnaireParticipantId(int $surveyId): ?string
    {
        $this->SurveyParticipants = TableRegistry::getTableLocator()->get('SurveyParticipants');
        $participant = $this->SurveyParticipants->find()
            ->where(['survey_id' => $surveyId])
            ->order(['id' => 'ASC'])
            ->first();

        return $participant->id ?? null;
    }

    /**
     * Risolve l'id della survey "template" (version_tag='template') da cui
     * clonare un questionario Azienda/Scuola/Uni, da
     * Configure::read('CompanyQuestionnaireTemplates').
     *
     * @param string $versionTag Uno tra self::COMPANY_QUESTIONNAIRE_TYPES.
     * @param string $scope Uno tra self::COMPANY_QUESTIONNAIRE_SCOPES.
     * @return int
     * @throws \RuntimeException Se non è configurato un template per (versionTag, scope).
     */
    protected function resolveCompanyQuestionnaireTemplateId(string $versionTag, string $scope): int
    {
        $templateId = Configure::read("CompanyQuestionnaireTemplates.{$versionTag}.{$scope}");
        if (empty($templateId) || !is_numeric($templateId)) {
            throw new RuntimeException(__(
                'Nessun template configurato per il questionario {0}/{1}. ' .
                'Configurare CompanyQuestionnaireTemplates in config/app.php ' .
                '(o via env COMPANY_QUESTIONNAIRE_TEMPLATES).',
                $versionTag,
                $scope
            ));
        }

        return (int)$templateId;
    }

    /**
     * Ricostruisce il blob JSON "legacy" (stessa forma di companies.survey /
     * offices.survey compilati dai vecchi componenti Vue statici) a partire
     * dalle risposte dinamiche (Answers) di un questionario Azienda/Scuola/
     * Uni. Il nome della domanda (questions.name) deve coincidere col nome
     * storico del campo (es. 'ul_nr_dipendenti'): mapping per identità,
     * nessuna tabella di conversione separata. Permette a
     * Company::checkComplete()/Office::checkComplete(), agli Exporter
     * (Html/Docx/MdExporter::fillOfficeSurvey) e a CompanySurveyHistory di
     * continuare a funzionare invariati (vedi
     * SurveysController::syncCompanyQuestionnaire()).
     *
     * @param int $surveyId Id della survey (istanza Azienda/Scuola/Uni).
     * @return array<string, mixed>
     */
    public function assembleLegacySurveyBlob(int $surveyId): array
    {
        $questionsSurveysTable = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        // Questions/QuestionsSurveys hanno il Translate behavior: sotto locale
        // non di default il join con `i18n` rende `id` ambiguo e, se raggiunto
        // via contain() a strategia 'join', può non tradurre affatto. Questa è
        // una lettura canonica (i nomi macchina delle domande, non testo
        // traducibile): forzo sempre la lingua di default, come già fatto
        // altrove per le stesse ragioni (vedi CLAUDE.md, sezione i18n).
        $questionsSurveysTable->setLocale(I18n::getDefaultLocale());
        $questionsSurveysTable->Questions->setLocale(I18n::getDefaultLocale());

        $rows = $questionsSurveysTable->find()
            ->where(['QuestionsSurveys.survey_id' => $surveyId])
            ->contain(['Questions' => ['strategy' => 'select']])
            ->all();

        $questionNames = [];
        foreach ($rows as $row) {
            if (!empty($row->question) && !empty($row->question->name)) {
                $questionNames[$row->question_id] = $row->question->name;
            }
        }

        if (empty($questionNames)) {
            return [];
        }

        $answersTable = TableRegistry::getTableLocator()->get('Answers');
        $answers = $answersTable->find()
            ->select(['question_id', 'answer'])
            ->where(['survey_id' => $surveyId])
            ->all();

        $blob = [];
        foreach ($answers as $answer) {
            $name = $questionNames[$answer->question_id] ?? null;
            if ($name === null) {
                // Risposta orfana: la domanda è stata rimossa dal catalogo
                // dopo la compilazione. Ignorata (non deve rompere il blob).
                continue;
            }
            $blob[$name] = $this->decodeCompanyQuestionnaireAnswerValue($answer->answer);
        }

        return $blob;
    }

    /**
     * Pre-compila le risposte (Answers) di un questionario Azienda/Scuola/
     * Uni appena creato con i valori già presenti nel blob legacy
     * (companies.survey / offices.survey) inserito dai vecchi componenti Vue
     * statici PRIMA della conversione — così la prima apertura del nuovo
     * form dinamico non appare vuota. Non sovrascrive mai risposte già
     * presenti per quell'utente/survey nel nuovo motore.
     *
     * @param int $surveyId Id della survey.
     * @param string $userId Id dell'utente partecipante (answers.user_id).
     * @param array<string, mixed> $legacyBlob Blob legacy (chiave = nome storico campo).
     * @return int Numero di risposte create.
     */
    public function seedAnswersFromLegacyBlob(int $surveyId, string $userId, array $legacyBlob): int
    {
        if (empty($legacyBlob)) {
            return 0;
        }

        $questionsSurveysTable = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $questionsSurveysTable->setLocale(I18n::getDefaultLocale());
        $questionsSurveysTable->Questions->setLocale(I18n::getDefaultLocale());

        $rows = $questionsSurveysTable->find()
            ->where(['QuestionsSurveys.survey_id' => $surveyId])
            ->contain(['Questions' => ['strategy' => 'select']])
            ->all();

        $answersTable = TableRegistry::getTableLocator()->get('Answers');
        $created = 0;
        foreach ($rows as $row) {
            $name = $row->question->name ?? null;
            if ($name === null || !array_key_exists($name, $legacyBlob)) {
                continue;
            }
            $value = $legacyBlob[$name];
            if ($value === null) {
                continue;
            }

            $exists = $answersTable->exists([
                'survey_id' => $surveyId,
                'user_id' => $userId,
                'question_id' => $row->question_id,
            ]);
            if ($exists) {
                continue;
            }

            $answer = $answersTable->newEntity([
                'survey_id' => $surveyId,
                'user_id' => $userId,
                'question_id' => $row->question_id,
                'answer' => is_scalar($value) ? (string)$value : json_encode($value),
            ]);
            if ($answersTable->save($answer)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Decodifica un valore answers.answer per assembleLegacySurveyBlob():
     * se è una stringa JSON valida (multiple/array/map salvano così, vedi
     * AnswersController::update()) la ridecodifica, altrimenti la lascia
     * invariata (valore scalare semplice, es. text/number/single).
     *
     * @param string|null $raw Valore grezzo di answers.answer.
     * @return mixed
     */
    private function decodeCompanyQuestionnaireAnswerValue(?string $raw)
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}

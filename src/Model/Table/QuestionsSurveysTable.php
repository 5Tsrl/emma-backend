<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class QuestionsSurveysTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('questions_surveys');
        // allowEmptyTranslations=false: an empty-content en/fr i18n row must NOT
        // shadow the canonical base column on read. This is the fix for the
        // map-question `long_description.mapqs` config disappearing under
        // Accept-Language: fr/en — see CLAUDE.md "Part 15" for the full story.
        $this->addBehavior('Translate', [
            'fields' => ['description', 'long_description', 'options'],
            'allowEmptyTranslations' => false,
        ]);

        $this->belongsTo('Surveys');
        $this->belongsTo('Questions');
    }

    // clone questions from a survey to another
    public function cloneQuestions($surveyId, $newSurveyId)
    {
        // Regressione i18n (stessa classe di bug del Part 15/16/17): senza
        // questo guard, sotto una locale diversa dalla default (es.
        // Accept-Language: fr), il Translate behavior sostituisce le colonne
        // base (description/long_description/options) con la riga i18n
        // tradotta invece di lasciarle canoniche - toArray() cattura quei
        // valori sostituiti e patchEntity()/save() li scrive come colonna
        // base delle NUOVE righe questions_surveys, clonando permanentemente
        // contenuto tradotto (spesso una stringa pipe-delimited al posto
        // dell'array JSON per `options`) al posto del canonico. Vedi
        // docs/dev-notes/questions-surveys-options-string-corruption.md.
        $defaultLocale = \Cake\I18n\I18n::getDefaultLocale();
        $this->setLocale($defaultLocale);

        $questionsSurveys = $this->find('translations')->where(['survey_id' => $surveyId]);
        foreach ($questionsSurveys as $questionsSurvey) {
            $newQuestionsSurvey = $this->newEmptyEntity();
            $oldQuestionsSurvey = $questionsSurvey->toArray();
            // remove id and survey_id
            unset($oldQuestionsSurvey['id']);
            unset($oldQuestionsSurvey['survey_id']);
            // `moma_can_edit_options`/`disabled_options` are $_accessible=false on the
            // entity (they may only be written by the dedicated SurveysController
            // endpoints), so a plain patchEntity() silently drops them and a company
            // survey cloned from a template loses the admin's moma grants (Brescia
            // #496). The clone is a trusted server-side copy: opt those two fields in
            // for THIS call only, never touch the entity's $_accessible.
            $newQuestionsSurvey = $this->patchEntity($newQuestionsSurvey, $oldQuestionsSurvey, [
                'accessibleFields' => ['moma_can_edit_options' => true, 'disabled_options' => true],
            ]);
            $newQuestionsSurvey->survey_id = $newSurveyId;
            $this->save($newQuestionsSurvey);
        }
    }
}

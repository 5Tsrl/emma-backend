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
        $this->addBehavior('Translate', ['fields' => ['description', 'long_description', 'options']]);

        $this->belongsTo('Surveys');
        $this->belongsTo('Questions');
    }

    // clone questions from a survey to another
    public function cloneQuestions($surveyId, $newSurveyId)
    {
        $questionsSurveys = $this->find('translations')->where(['survey_id' => $surveyId]);
        foreach ($questionsSurveys as $questionsSurvey) {
            $newQuestionsSurvey = $this->newEmptyEntity();
            $oldQuestionsSurvey = $questionsSurvey->toArray();
            // remove id and survey_id
            unset($oldQuestionsSurvey['id']);
            unset($oldQuestionsSurvey['survey_id']);
            $newQuestionsSurvey = $this->patchEntity($newQuestionsSurvey, $oldQuestionsSurvey);
            $newQuestionsSurvey->survey_id = $newSurveyId;
            $this->save($newQuestionsSurvey);
        }
    }
}

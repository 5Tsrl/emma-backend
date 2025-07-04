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
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\I18n\I18n;
// HTTP_HOST=demo.api.5t.impronta48.it bin/cake update_questions
// HTTP_HOST=demo.api.mobility48.it bin/cake update_questions
// HTTP_HOST=api.5t.impronta48.it bin/cake update_questions
// HTTP_HOST=api.mobility48.it bin/cake update_questions

class UpdateQuestionsCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->loadModel('Questions');
        $this->deleteQuestionsSurveys($io);
        $io->out('QuestionsSurveys deleted');
        $this->addQuestionsSpos($io);
        $io->out('Questions_spos added');
        $this->correctConditions($io);
        $io->out('Conditions corrected');
        $this->correctQuestionsSurveys($io);
        $io->out('QuestionsSurveys corrected');
        $this->correctTranslations($io);
        $io->out('Translations corrected');
        // $this->loadModel('Answers');
        // $type = 'array';

        // $questions = $this->Questions->find()
        // ->where(['type' => $type]);

        // foreach ($questions as $q) {
        //     $o = $q->options;

        //     $groups = null;
        //     if (isset($o['groups'])) {
        //         $groups = $o['groups'];
        //     }

        //     $labels = [];

        //     $io->out("Questions {$q->name} ");
        //     if (is_array($groups)) {
        //         foreach ($groups as $g) {
        //             $io->out("\t Label {$g['label']} ");
        //             $labels[]  = $g['label'];
        //         }
        //     }

        //     $answers = $this->Answers->find()
        //     ->where(['question_id' => $q->id]);

        //     foreach ($answers as $a) {
        //         //$io->out("\t\t Answer {$a->id} ready to be converted");
        //         $ad = '';
        //         if (!is_null($a->answer)) {
        //             $ad = $a->answer;
        //             $keys = array_keys($ad);
        //             //Potrei fare un controllo più accurato, ma scommetto che questo basta - massimoi 20/2/21
        //             //https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential/4254008#4254008
        //             if ($type == 'array') {
        //                 if (isset($keys[0]) && is_numeric($keys[0])) {
        //                     $newAnswer = [];
        //                     foreach ($ad as $k => $option) {
        //                         if ($k !== 'Altro' && $labels[$k] != 'Altro') {
        //                             $newAnswer[$labels[$k]] = $option;
        //                         }
        //                     }
        //                     $a->answer = $newAnswer;
        //                     if ($this->Answers->save($a)) {
        //                         $io->out("\t\t Salvataggio {$a->id} OK");
        //                     } else {
        //                         $io->out("\t\t Salvataggio {$a->id} KO");
        //                     }
        //                 }
        //             }
        //             if ($type == 'single' && is_array($ad)) {
        //                 $io->out("\t\t Answer {$a->id} to question {$q->id} is multiple");
        //             }
        //             if (($type == 'multiple' || $type == 'array') && !is_array($ad)) {
        //                 $io->out("\t\t Answer {$a->id} to question {$q->id} is single");
        //             }
        //         }

        //         //$io->out("\t\t Answer {$ad} converted OK");
        //     }
        // }
    }

    // create a funtion to correct the conditions of the questions

    public function correctConditions($io)
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
                $question_condition = $this->Questions->find()->where(['id' => $conditions['question']]);
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
                 $io->out('The conditions have been corrected. Total: ' . $count);
                 // $this->Flash->success(__('The conditions have been corrected. Total: '.$count));
        } catch (\Exception $e) {
            $io->out('The conditions could not be corrected. Please, try again.' . $e->getMessage());
            // $this->Flash->error(__('The conditions could not be corrected. Please, try again.'.$e->getMessage()));
        }
    }

    // correct empty options, description and long_description in questions_surveys

    public function correctQuestionsSurveys($io)
    {
        try {
            $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->where([

                    'description IS' => null,
                    'long_description IS' => null,
                    'options IS' => null,
                    // 'question_id NOT IN' =>  Configure::read('Questions_spos'),
                    'question_id IS NOT' =>  Configure::read('Questions.origine_spostamenti'),

            ]);
            $count = 0;
            foreach ($questions_surveys as $qs) {
                    $question = $this->Questions->find('translations')->where(['id' => $qs['question_id']])->first();
                if (!empty($question['options'])) {
                    $qs['options'] = $question['options'];
                }
                if (!empty($question['description'])) {
                    $qs['description'] = $question['description'];
                }
                if (!empty($question['long_description'])) {
                    $qs['long_description'] = $question['long_description'];
                }
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
            $io->out('The questions surveys have been corrected. Total: ' . $count);
            // $this->Flash->success(__('The questions surveys have been corrected. Total: '.$count));
        } catch (\Exception $e) {
            $io->out('The questions surveys could not be corrected. Please, try again.' . $e->getMessage());
            // $this->Flash->error(__('The questions surveys could not be corrected. Please, try again.'.$e->getMessage()));
        }
    }

    // delete questionsurveys from deleted questions

    public function deleteQuestionsSurveys($io)
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
            $io->out('The questions surveys have been deleted. Total: ' . $count);
            // $this->Flash->success(__('The questions surveys have been deleted. Total: '.$count));
        } catch (\Exception $e) {
            $io->out('The questions surveys could not be deleted. Please, try again.' . $e->getMessage());
            // $this->Flash->error(__('The questions surveys could not be deleted. Please, try again.'.$e->getMessage()));
        }
    }

    // add Questions_spos to surveys that don't have it

    public function addQuestionsSpos($io)
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
                    $question_map = $this->Questions->find('translations')->where(['id' => $q_map_id])->contain([
                        'QuestionsSurveys' => function ($query) use ($survey_id) {
                            return $query->find('translations')->where(['survey_id' => $survey_id]);
                        },
                    ])->first();
                    if (empty($question_map['questions_surveys'][0])) {
                        // $question_map = $this->Questions->find('translations')->where(['id'=>$q_map_id])->first();
                        $question_map['questions_surveys'][0] = $this->Questions->QuestionsSurveys->newEmptyEntity();
                        $question_map['questions_surveys'][0]['section_id'] = $question['section_id'];
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                        $question_map['questions_surveys'][0]['hidden'] = 1;
                        $question_map['questions_surveys'][0]['compulsory'] = 1;
                        $question_map = $this->insertQuestionSurvey($question_map, 'en');
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $count++;
                    } else {
                        $question_map['questions_surveys'][0]['section_id'] = $question['section_id'];
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    }
                }
            }
            $io->out('The questions surveys have been added. Total: ' . $count);
            // $this->Flash->success(__('The questions surveys have been added. Total: '.$count));
        } catch (\Exception $e) {
            $io->out('The questions surveys could not be added. Please, try again.' . $e->getMessage());
            // $this->Flash->error(__('The questions surveys could not be added. Please, try again.'.$e->getMessage()));
        }
    }

    // correct translations of questions

    public function correctTranslations($io)
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
                if (!is_array($question['options']) && $question['type'] != 'array') {
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
            $io->out('The translations have been corrected. Total: ' . $count);
            // $this->Flash->success(__('The translations have been corrected. Total: '.$count));
        } catch (\Exception $e) {
            $io->out('The translations could not be corrected. Please, try again.' . $e->getMessage());
            // $this->Flash->error(__('The translations could not be corrected. Please, try again.'.$e->getMessage()));
        }
    }

    //Aggiorna la tabella di passaggio questions_surveys
    private function insertQuestionSurvey($question, $lang)
    {
        // $this->QuestionSurveys = $this->getTableLocator()->get('QuestionsSurveys');

        // $qs = $this->QuestionSurveys->newEmptyEntity();
        // $question['questions_surveys'][0]->survey_id = $survey_id;
        $question['questions_surveys'][0]['question_id'] = $question['id'];
        // $question['questions_surveys'][0]->section_id = $question['section_id'];
        // $question['questions_surveys'][0]->weight = $question['weight'];
        // $question['questions_surveys'][0]->hidden = $question['hidden'];
        // $question['questions_surveys'][0]->compulsory = $question['compulsory'];
        $question['questions_surveys'][0]['options'] = $question['options'];
        $question['questions_surveys'][0]['conditions'] = $question['conditions'];
        $question['questions_surveys'][0]['description'] = $question['description'];
        $question['questions_surveys'][0]['long_description'] = $question['long_description'];

        // $question_trans = $this->QuestionSurveys->find('translations')
        //     ->where(['id' => $joinData['id']])
        //     ->first();
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
                    $question['questions_surveys'][0]->translation($lang)->$field = $value;
                // }
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
}

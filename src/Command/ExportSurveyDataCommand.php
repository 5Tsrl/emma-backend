<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ExportSurveyData command.
 */
class ExportSurveyDataCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    // Define the default table. This allows you to use `fetchTable()` without any argument.
    protected $defaultTable = 'Answers';

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        // $parser->addArguments([
        //     'survey_id' => ['help' => 'The ID of survey', 'required' => false],
        //     'all' => ['help' => 'If export all questions is true, if only some questions if false', 'required' => false,'boolean' => false],
        //     'office_id' => ['help' => 'The ID of office', 'required' => false],
        //     'company_id' => ['help' => 'The ID of company', 'required' => false],
        //     'subcompany' => ['help' => 'The subcompany', 'required' => false],
        //     'questions_id'  => ['help' => 'The ID of questions', 'required' => false],
        // ]);
        $parser
            ->addOption('all', [
                'help' => "se true ignore l'ufficio nel calcolod degli indicatori",
                'default' => false,
            ])
            ->addOption('survey_id', [
                'help' => 'id del questionario',
                'required' => false,
            ])
            ->addOption('company_id', [
                'help' => "id dell'azienda",
                'required' => false,
            ])
            ->addOption('office_id', [
                'help' => 'id della sede',
                'required' => false,
            ])
            ->addOption('subcompany', [
                'help' => 'id della subazienda',
                'required' => false,
            ])
            ->addOption('questions_id', [
                'help' => 'id delle domande',
                'required' => false,
                'default' => null,
            ])
            ->addOption('allAnswers', [
                'help' => 'if true, export all answers, if false export only completed surveys',
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        try {
            // $io->createFile('/var/www/html/logs/errorexport.log', 'ExportSurveyDataCommand started', true);
            $log = "ExportSurveyDataCommand started\n";
            // set_time_limit(180);
            $this->Answers = $this->fetchTable();
            $log=$log."Answers fetched\n";
            $questions_id = array_map('intval', explode(',', $args->getOption('questions_id')));
            $survey_id = array_map('intval', explode(',', $args->getOption('survey_id')));
            // $all = (bool)$args->getOption('all');
            $all = filter_var($args->getOption('all'), FILTER_VALIDATE_BOOLEAN);
            $office_id = (int)$args->getOption('office_id');
            $company_id = (int)$args->getOption('company_id');
            $subcompany = (int)$args->getOption('subcompany');
            // $allAnswers = (bool)$args->getOption('allAnswers');
            $allAnswers = filter_var($args->getOption('allAnswers'), FILTER_VALIDATE_BOOLEAN);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->getColumnDimension('A')->setWidth(38);
            $sheet->getColumnDimension('B')->setWidth(20);
            $log=$log."spreadsheet created\n";
            $users = $this->Answers->find()
                ->select(['Answers.user_id','Answers.survey_id'])
                ->contain(['Surveys', 'Questions'])
                ->where(['survey_id IN' => $survey_id])
                //->limit(100)
                ->distinct()
                ->order(['survey_id']);
            $log=$log."users fetched \n";
            //Filtro solo per gli utenti che hanno i miei attributi
            if ($office_id) {
                $users->matching('Users', function ($q) use ($office_id) {
                    return $q->where(['Users.office_id' => $office_id]);
                });
            }
            if ($company_id) {
                $users->matching('Users', function ($q) use ($company_id) {
                    return $q->where(['Users.company_id' => $company_id]);
                });
            }
            if ($subcompany) {
                $users->matching('Users', function ($q) use ($subcompany) {
                    return $q->where(['Users.subcompany' => $subcompany]);
                });
            }

            $this->loadModel('Questions_surveys');
            $this->loadModel('Sections');

            $sections_weight = $this->Sections->find()
                ->select(['id', 'weight'])
                ->toList();
            if ($all) {
                $questions_used = $this->getExportQuestions($survey_id, $all);
            } else {
                $questions_in_survey = $this->Questions_surveys->find()
                ->select(['question_id', 'weight', 'section_id', 'options'])
                ->where(['survey_id IN' => $survey_id, 'question_id IN' => $questions_id])
                ->distinct()
                ->toList();

                $questions_used = $this->Answers->Questions->QuestionsSurveys->find()
                    ->select(['Questions.section_id', 'Questions.id', 'Questions.name', 'Questions.description', 'Questions.type'
                    ,'QuestionsSurveys.weight','QuestionsSurveys.options','QuestionsSurveys.section_id'])
                    ->contain(['Questions'])
                    ->where(['QuestionsSurveys.survey_id IN' => $survey_id, 'QuestionsSurveys.question_id IN' => $questions_id])
                    ->distinct()
                    ->toList();

                // $questions_used = $this->questions_used_in_survey($questions, $questions_in_survey);
            }

            $questions_name = [];
            $questions_description = [];
            $questions_options = ['id', 'Completato','Survey'];

            usort($sections_weight, function ($a, $b) {
                return $a->weight - $b->weight;
            });

            $sections = $sections_weight;

            foreach ($sections as $section) {
                $section_questions = array_filter($questions_used, function ($question) use ($section) {
                    return $question->section_id === $section->id;
                });

                if (empty($section_questions)) {
                    continue;
                }

                usort($section_questions, function ($a, $b) {
                    return $a->weight - $b->weight;
                });

                $flag_for_array_questions = 0;
                foreach ($section_questions as $question) {
                    if (
                        strtolower($question->question->type) != 'multiple' &&
                            strtolower($question->question->type) != 'array' &&
                            strtolower($question->question->type) != 'map'
                    ) {
                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, $question->question->description);
                        array_push($questions_options, $question->question->name);
                    } elseif (strtolower($question->question->type) == 'map') { //Risposta mappa
                        //TODO: Qui devo esplodere i titoli della mappa
                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'citta');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'cap');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'provincia');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'lat-origine');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'lon-origine');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'sede');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'lat-sede');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'lon-sede');

                        array_push($questions_name, $question->question->name);
                        array_push($questions_description, 'o/d del dipendente');
                        array_push($questions_options, 'subcompany');
                    } else { //Risposta multipla o array
                        $flag_for_array_questions = 0;
                        foreach ($question->options as $option) {
                            if (is_array($option) && $question->question->type === 'array') {
                                $flag_for_array_questions = 1;
                                foreach ($option as $group_option) {
                                    array_push($questions_name, $question->question->name);
                                    array_push($questions_description, $question->question->description);
                                    if (isset($group_option['label'])) {
                                        array_push($questions_options, $group_option['label']);     //original
                                    } else {
                                        array_push($questions_options, $group_option);  // change
                                    }
                                }
                                array_push($questions_name, $question->question->name);
                                array_push($questions_description, $question->question->description);
                                array_push($questions_options, 'Altro');
                            } else { //risposta multipla
                                if ($flag_for_array_questions == 1) {
                                    $flag_for_array_questions = 0;
                                    continue;
                                }
                                if (is_array($option)) {
                                    $question_option_to_string = implode(', ', $option);
                                    array_push($questions_name, $question->question->name);
                                    array_push($questions_description, $question->question->description);
                                    array_push($questions_options, $question_option_to_string);
                                    continue;
                                }
                                //TODO: Verificare perchè in mezzi_motivo si disallineano name, description e options
                                array_push($questions_name, $question->question->name);
                                array_push($questions_description, $question->question->description);
                                array_push($questions_options, $option);
                            }
                        }
                    }
                }
            }

            $sheet->fromArray(
                $questions_name,
                null,
                'D1'
            );

            $row = 3;
            $col = 'A';
            foreach ($questions_options as $question_option) {
                $sheet->setCellValue("$col$row", $question_option);
                $col++;
            }

            $row = 2;
            $col = 'D';
            foreach ($questions_description as $question_description) {
                $sheet->setCellValue("$col$row", $question_description);
                $description_length = strlen($question_description);
                if ($description_length < 15) {
                    $sheet->getColumnDimension($col)->setWidth($description_length);
                } else {
                    $sheet->getColumnDimension($col)->setWidth($description_length / 2);
                }
                $col++;
            }

            if (empty($users)) {
                return;
            }
            //Genero una riga per ogni utente con le risposte
            $current_row = 4;
            foreach ($users as $user) {
                $complete = $this->surveyDataHelper($user->survey_id, $questions_name, $questions_options, $user->user_id, $sheet, $current_row, $allAnswers);
                if ($complete == false) {
                    continue;
                } else {
                    $current_row++;
                }
            }

            $writer = new Xlsx($spreadsheet);
            $log=$log.'writer created /n';
            $pr = $writer->save(TMP . 'out.xlsx');
            $log=$log.'pr= '.$pr.'/n';
            // if survey_id is array, then the name of the file is all_surveys.xlsx
            if (is_array($survey_id)) {
                $survey_id = 'all_surveys';
            }
            // // Inside your method, before calling withFile()
            // if (!($this->response instanceof Response)) {
            //     $this->response = new Response();
            // }
            // $response = $this->response->withFile(
            //     TMP . 'out.xlsx',
            //     ['download' => true, 'name' => "$survey_id.xlsx"]
            // );

            // return $response;
            $io->createFile(LOGS.'errorexport.log', $log, true);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->log($errorMsg, 'error');
            $log=$log.' '.$errorMsg;
            $io->createFile(LOGS.'errorexport.log', $log, true);
        }
    }

    /**
     * Retrieves the export questions for a survey.
     *
     * @param int|null $survey_id The ID of the survey. Defaults to null.
     * @param bool $all Determines whether to retrieve all questions or not. Defaults to false.
     * @return void
     */
    public function getExportQuestions($survey_id = null, $all = false)
    {
        if ($survey_id == null) {
            // $survey_id = $this->request->getData();
            $this->loadModel('Questions_surveys');
        }
        $questions_in_survey = $this->Questions_surveys->find()
                    ->select(['question_id', 'weight', 'section_id','options'])
                    ->where(['survey_id IN' => $survey_id])
                    ->distinct();
        if (!$all) {
            $questions = $this->Answers->find()->select(['Questions.id', 'Questions.name', 'Questions.description'])
            ->contain(['Surveys', 'Questions'])
            ->where(['survey_id IN' => $survey_id])
            ->distinct();
            $questions_used = $this->questions_used_in_survey($questions, $questions_in_survey);
            // $this->set('questions',$questions_used);
            // $this->viewBuilder()->setOption('serialize', ['questions']);
            // $this->render('export_questions');
        } else {
            // $questions = $this->Answers->find()
            // ->select(['Questions.section_id', 'Questions.id', 'Questions.name', 'Questions.description', 'Questions.type'])
            // ->contain(['Surveys', 'Questions'])
            // ->where(['survey_id IN' => $survey_id])
            // ->distinct();
            $questions = $this->Answers->Questions->QuestionsSurveys->find()
                    ->select(['Questions.section_id', 'Questions.id', 'Questions.name', 'Questions.description', 'Questions.type'
                    ,'QuestionsSurveys.weight','QuestionsSurveys.options','QuestionsSurveys.section_id'])
                    ->contain(['Questions'])
                    ->where(['QuestionsSurveys.survey_id IN' => $survey_id])
                    ->distinct()
                    ->toList();
            // return $this->questions_used_in_survey($questions, $questions_in_survey);
            return $questions;
        }
    }

    public function questions_used_in_survey($questions, $questions_in_survey)
    {

        $questions_used = [];

        foreach ($questions_in_survey as $question_weight) {
            foreach ($questions as $question) {
                if ($question_weight->question_id == $question->question->id) {
                    $question->question['section_id'] = $question_weight->section_id;

                    if ($question_weight->weight != null) {
                        $question->question['weight'] = $question_weight->weight;
                    } else {
                        $question->question['weight'] = 0;
                    }

                    array_push($questions_used, $question);
                }
            }
        }

        return $questions_used;
    }

    private function surveyDataHelper($survey_id, $questions_name, $questions_options, $user_id, $sheet, $current_row, $allAnswers)
    {
        try {
            $user_answers = $this->Answers->find()
                ->select(['Answers.user_id', 'Answers.created', 'Answers.answer',
                    'Questions.name', 'Questions.options', 'Questions.type',
                    'Users.email', 'Users.subcompany'])
                ->contain([
                    'Questions' => [
                        'fields' => ['name','type','options'],
                    ],
                    'Users' => [
                        'fields' => ['id','email','subcompany','role'],
                        'Offices' => ['fields' => ['id','name','lat','lon']],
                        'SurveyParticipants' => ['fields' => ['id','survey_id','user_id','survey_completed_at']],
                    ],
                ])
                ->where([
                    'Answers.survey_id' => $survey_id,
                    'Answers.user_id' => $user_id,
                ]);
                // log the allAnswers value
                // $this->log((string)$allAnswers, 'info');
            if (!$allAnswers) {
                $user_answers = $user_answers->matching('Users.SurveyParticipants', function ($q) {
                    return $q->where(['SurveyParticipants.survey_completed_at IS NOT NULL']);
                });
            }

            //debug($user_answers);
                // ->all();
                //->order('Questions.name'); non serve ordinare per risparmiare 0,5 secondi
            $survey = $this->Answers->Surveys->findById($survey_id)->first();
            $first = true;
            if (empty($user_answers->toArray())) {
                return false;
            }
            foreach ($user_answers as $answer) {
                if (empty($answer->answer)) {
                    continue;
                }
                if ($first) {
                    // if survey is anonymous, then the user email is not shown
                    if ($survey->sending_mode == 'z' && $answer->user->role != 'user') {
                        $sheet->setCellValue([1, $current_row], $answer->user->id . '@email.invalid');
                    } else {
                        $sheet->setCellValue([1, $current_row], $answer->user->email);
                    }
                    $sheet->setCellValue([2, $current_row], $answer->user->survey_participants[0]->survey_completed_at);
                    $sheet->setCellValue([3, $current_row], $survey->name);
                    $first = false;
                }

                $question_column = 4;
                // if($answer->question->name=='mezzi-usati'){
                //     $this->log('mezzi-usati','error');
                // }
                $index = array_search($answer->question->name, $questions_name);
                // prova
                if ($index === false) {
                    continue;
                }
                $question_column  += $index;
                if (is_array($answer->answer)) {
                    foreach ($answer->answer as $value) {
                        if (is_string($value) && substr($value, 0, 1) === '=') {
                            $this->log('The answer starting with "=" is a formula in Excel: (' . $value . ') in column:' . $question_column . 'row:' . $current_row, 'error');
                            continue;
                        }
                        // Process the value here
                    }
                } else {
                    $answerValue = $answer->answer;
                    if (is_string($answerValue) && substr($answerValue, 0, 1) === '=') {
                        $this->log('The answer starting with "=" is a formula in Excel: (' . $answerValue . ') in column:' . $question_column . 'row:' . $current_row, 'error');
                        continue;
                    }
                    // Process the value here
                }

                switch ($answer->question->type) {
                    case 'single':
                        if (is_array($answer->answer)) {
                            $sheet->setCellValue([$question_column, $current_row], $answer->answer[0]);
                            continue 2;
                        }
                        $sheet->setCellValue([$question_column, $current_row], $answer->answer);
                        break;

                    case 'map':
                        $dec = $answer->answer;

                        //Citta
                        if (isset($dec['origin']['city'])) {
                            $sheet->setCellValue([$question_column++, $current_row], strtolower($dec['origin']['city']));
                        } else {
                            $question_column++;
                        }

                        //Cap
                        if (isset($dec['origin']['postal_code'])) {
                            $sheet->setCellValue([$question_column++, $current_row], $dec['origin']['postal_code']);
                        } else {
                            $question_column++;
                        }
                        //Provincia
                        if (isset($dec['origin']['province'])) {
                            $sheet->setCellValue([$question_column++, $current_row], strtoupper($dec['origin']['province']));
                        } else {
                            $question_column++;
                        }

                        //Lat-origine
                        if (isset($dec['origin']['lat'])) {
                            $sheet->setCellValue([$question_column++, $current_row], $dec['origin']['lat']);
                        } else {
                            $question_column++;
                        }

                        //Lon-origine
                        if (isset($dec['origin']['lon'])) {
                            $sheet->setCellValue([$question_column++, $current_row], $dec['origin']['lon']);
                        } else {
                            $question_column++;
                        }

                        //Sede
                        if (isset($answer->user->office->name)) {
                            $sheet->setCellValue([$question_column++, $current_row], $answer->user->office->name);
                        } else {
                            $question_column++;
                        }

                        //Lat-sede
                        if (isset($answer->user->office->lat)) {
                            $sheet->setCellValue([$question_column++, $current_row], $answer->user->office->lat);
                        } else {
                            $question_column++;
                        }

                        //Lon-sede
                        if (isset($answer->user->office->lon)) {
                            $sheet->setCellValue([$question_column++, $current_row], $answer->user->office->lon);
                        } else {
                            $question_column++;
                        }

                        //subcompany
                        if (isset($answer->user->subcompany)) {
                            $sheet->setCellValue([$question_column++, $current_row], $answer->user->subcompany);
                        } else {
                            $question_column++;
                        }

                        /* if (isset($dec->destination->office_id)) {
                            $sheet->setCellValueByColumnAndRow($question_column, $current_row, $dec->destination->office_id);
                        } else {
                            $question_column++;
                        } */
                        break;

                    case 'array':
                        $options_length = count($answer->question->options['groups']);
                        if (!is_array($answer->answer)) {
                            continue 2;
                        }
                        // foreach ($answer->answer as $ans) {
                        //     $temp_col = $question_column;
                        //     for ($i = $index + 2; $i < $index + 2 + $options_length; $i++) {
                        //         $user_chose = strval($questions_options[$i]);
                        //         $user_question = strval(array_search($ans, $answer->answer));
                        //         if (substr($user_chose, 0, 6) === substr($user_question, 0, 6)) {
                        //             $sheet->setCellValueByColumnAndRow($temp_col,$current_row, $ans);
                        //             unset($answer->answer[$user_question]);
                        //             break;
                        //         }
                        //         $temp_col++;
                        //     }
                        // }
                        $temp_col = $question_column;
                        foreach ($answer->answer as $key => $ans) {
                            if (!is_int($key)) {
                                $temp_col = $temp_col + 1;
                            } else {
                                $temp_col = $question_column + $key;
                            }
                            $sheet->setCellValue([$temp_col, $current_row], $ans);
                            // $temp_col++;
                        }
                        break;

                    case 'text':
                        if ($answer->question->type === 'text') {
                            $temp_col = $question_column;
                            $sheet->setCellValue([$temp_col, $current_row], $answer->answer);
                            continue 2;
                        }
                        break;

                    default:
                        $options_length = 0;
                        if (is_array($answer->question->options)) {
                            $options_length = count($answer->question->options);
                        }
                        $count = 0;

                        if (!is_array($answer->answer)) {
                            $no_ans = 1;
                            $temp_col = $question_column;
                            for ($i = $index + 2; $i < $index + 2 + $options_length; $i++) {
                                $user_chose = $questions_options[$i];
                                if (substr($user_chose, 0, 6) === substr($answer->answer, 0, 6)) {
                                    $sheet->setCellValue([$temp_col, $current_row], $answer->answer);
                                    $count++;
                                    if ($count == $no_ans) {
                                        break;
                                    }
                                }
                                $temp_col++;
                            }
                        } else {
                            $no_ans = count($answer->answer);
                            foreach ($answer->answer as $ans) {
                                $temp_col = $question_column;
                                for (
                                    $i = $index + 3;
                                    $i < $index + 3 + $options_length;
                                    $i++
                                ) {
                                    $user_chose = $questions_options[$i];
                                    if (!is_null($user_chose) && !is_null($ans) && strtolower(substr($user_chose, 0, 107)) === strtolower(substr($ans, 0, 107))) {
                                        $sheet->setCellValue([$temp_col, $current_row], $ans);
                                        $count++;
                                        if (
                                            $count == $no_ans
                                        ) {
                                            break;
                                        }
                                    }
                                    $temp_col++;
                                }
                            }
                        }
                        break;
                }
            }

            return true;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->log($errorMsg, 'error');
        }
    }
}

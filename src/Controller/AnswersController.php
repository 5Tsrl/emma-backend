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

use App\Indicator\baseIndicator;
use App\Indicator\RangeIndicator;
use App\Model\Entity\Answer;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Pheanstalk\Exception;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeName;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Cake\Utility\Text;

/**
 * Answers Controller
 *
 * @property \Moma\Model\Table\AnswersTable $Answers
 * @method \Moma\Model\Entity\Answer[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class AnswersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->skipAuthorization();
        $this->Authentication->allowUnauthenticated(['update']);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'update',
                ]
            );
        }
    }

    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $surveys = $this->Answers->Surveys->find('all', [
            'contain' => ['Companies'],
        ]);
        $this->set(compact('surveys'));
    }

    /**
     * View method
     *
     * @param null $survey_id Answer id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($survey_id, $user_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        //Elenco degli utenti che hanno compilato questa survey
        $result  = $this->Answers->find()
            ->distinct(['user_id'])
            ->select(['user_id'])
            ->where(['survey_id' => $survey_id])
            ->order('user_id')
            ->all();

        if (empty($result)) {
            throw new NotFoundException('Nessuna risposta per questo questionario');
        }

        $user_list = [];
        foreach ($result as $u) {
            $user_list[] = $u->user_id;
        }

        //Se non mi passi l'utente prendo il primo che ha compilato
        if (empty($user_id)) {
            $user_id = reset($user_list);
        }

        if (!in_array($user_id, $user_list)) {
            throw new NotFoundException('Questo utente non ha compilato questo questionario');
        }

        $answer = $this->Answers->find()
            ->contain(['Users', 'Questions'])
            ->where([
                'survey_id' => $survey_id,
                'user_id' => $user_id,
            ]);

        $i = array_search($user_id, $user_list);
        $prev = $user_list[$i - 1] ?? null;
        $next = $user_list[$i + 1] ?? null;
        $this->set(compact('answer', 'user_id', 'survey_id', 'user_list', 'prev', 'next'));
        $this->viewBuilder()->setOption('serialize', ['answer', 'user_id', 'survey_id', 'user_list', 'prev', 'next']);
    }

    /**
     * countAnswers - restituisce il numero di riposte ad una domanda
     *
     * @param [type] $question_id - id della domanda
     * @return void
     */
    public function count($question_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $c = $this->Answers->find()
            ->where(['question_id' => $question_id])
            ->count();
        $this->set('count', $c);
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    /**
     * update - aggiorna tutte le risposte di un certo questionario, per un certo utente
     *
     * @param int $survey_id
     * @param string $participant_id
     * @return void
     */
    public function update($survey_id, $participant_id): void
    {
        // metodo pubblico, deve essere sempre accessibile

        if ($this->request->is(['patch', 'post', 'put'])) {
            $errorMsg = false;
            try {
                $this->loadModel('SurveyParticipants');
                $participant = $this->SurveyParticipants->find()
                    ->where([
                        'id' => $participant_id,
                        'survey_id' => $survey_id,
                    ])
                    ->first();

                if (empty($participant)) {
                    throw new \Exception('Partecipante non trovato o non associato al questionario selezionato');
                }
                $user_id = $participant->user_id;
                $survey_id = $participant->survey_id;

                $result = $this->request->getData();
                $tot = count($result);
                for ($qid = 0; $qid < $tot; $qid++) {
                    if (!empty($result[$qid]) && !is_null($result[$qid])) {
                        $r = $result[$qid];
                        $a = $this->Answers->find()
                            ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $qid])
                            ->first();

                        if (empty($a)) {
                            $a = new Answer([
                                'question_id' => $qid, 'survey_id' => $survey_id, 'user_id' => $user_id,
                            ]);
                        }
                        $a->answer = $r;

                        if ($qid == Configure::read('Questions.origine_spostamenti')) {
                            $a->answer = json_decode($r);
                            $res = json_decode($r);
                            $this->loadModel('Users');
                            $a_user = $this->Users->find()
                                ->where([ 'id' => $user_id])
                                ->first();
                            if (is_null($a_user->office_id)) {
                                $a_user->office_id = $res->destination->office_id;
                            }
                            if (!$this->Users->save($a_user)) {
                                $info = $a_user->getErrors();
                                //TODO: Mostrare messaggio in caso di errore di salvataggio
                                $msg = "AnswersUpdate: Impossibile aggiornare la domanda $a_user, per l'utente $user_id, nel questionario $survey_id: $info";
                                Log::write('error', $msg);
                            }

                            //TODO: Gestire la insert/update di origin
                            //Cerco se c'è già una origin per questo utente e questo questionario
                            $this->loadModel('Origins');
                            $origin = $this->Origins->find()
                                        ->where([
                                            'user_id' => $user_id,
                                            'survey_id' => $survey_id,
                                            'company_id' => $a_user->company_id,
                                            ])
                                        ->first();
                            //Se non c'è già una origin la creo vuota
                            if (empty($origin)) {
                                $origin = $this->Origins->newEmptyEntity();
                            }
                            $origin->address = $res->origin->address;
                            $origin->postal_code = $res->origin->postal_code;
                            $origin->city = $res->origin->city;
                            $origin->province = $res->origin->province;
                            $origin->lon = $res->origin->lon;
                            $origin->lat = $res->origin->lat;
                            $origin->user_id = $user_id;
                            $origin->survey_id = $survey_id;
                            $origin->company_id = $a_user->company_id;

                            //Metto tutti i dati al posto giusto e poi salvo
                            if (!$this->Origins->save($origin)) {
                                $info = $origin->getErrors();
                                //TODO: Mostrare messaggio in caso di errore di salvataggio
                                $msg = "AnswersUpdate: Impossibile aggiornare l'origin l'utente $user_id, nel questionario $survey_id: $info";
                                Log::write('error', $msg);
                            }

                            $modes = $res->modes;
                            $n_modes = count($modes);
                            $distance = 0;
                            $cost = 0;
                            $max = 0;
                            $mezzi = [];
                            foreach ($modes as $mode) {
                                $distance += $mode->distance;
                                $cost += $mode->cost;
                                if ($max < (float)$mode->distance) {
                                    $max = $mode->distance;
                                    if ($mode->selTransportmean == 'Bike') {
                                        $max_mode = 'Bici';
                                    } elseif ($mode->selTransportmean == 'Scooter') {
                                        $max_mode = 'Monopattino';
                                    } elseif ($mode->selTransportmean == 'Train') {
                                        $max_mode = 'Treno';
                                    } elseif ($mode->selTransportmean == 'On_foot') {
                                        $max_mode = 'Piedi';
                                    } else {
                                        $max_mode = $mode->selTransportmean;
                                    }
                                }
                                if ($mode->selTransportmean == 'Auto') {
                                    if ($mode->type_auto == 'Personale' || $mode->type_auto == 'Personal') {
                                        array_push($mezzi, $mode->selTransportmean);
                                        $auto = array_filter($modes, function ($mode) {
                                            return $mode->selTransportmean == 'Auto';
                                        });
                                    } else {
                                        array_push($mezzi, $mode->type_auto);
                                    }
                                } elseif ($mode->selTransportmean == 'Moto') {
                                    if ($mode->type_moto == 'Personale' || $mode->type_moto == 'Personal') {
                                        array_push($mezzi, $mode->selTransportmean);
                                        $moto = array_filter($modes, function ($mode) {
                                            return $mode->selTransportmean == 'Moto';
                                        });
                                    } else {
                                        if ($mode->type_moto == 'Moto or scooter sharing') {
                                            array_push($mezzi, 'Moto o scooter sharing');
                                        } else {
                                            array_push($mezzi, $mode->type_moto);
                                        }
                                    }
                                } elseif ($mode->selTransportmean == 'Bike') {
                                    if ($mode->type_bike == 'Personale' || $mode->type_bike == 'Personal') {
                                        array_push($mezzi, 'Bici');
                                        $bici = array_filter($modes, function ($mode) {
                                            return $mode->selTransportmean == 'Bike';
                                        });
                                    } else {
                                        array_push($mezzi, $mode->type_bike);
                                    }
                                } elseif ($mode->selTransportmean == 'Scooter') {
                                    if ($mode->type_mono == 'Personale' || $mode->type_mono == 'Personal') {
                                        array_push($mezzi, 'Monopattino');
                                        $mono = array_filter($modes, function ($mode) {
                                            return $mode->selTransportmean == 'Scooter';
                                        });
                                    } else {
                                        array_push($mezzi, $mode->type_mono);
                                    }
                                } elseif ($mode->selTransportmean == 'Train') {
                                    if (
                                        $mode->type_treno == 'Servizio ferroviario Trenitalia' ||
                                        $mode->type_treno == 'Servizio ferroviario Trenitalia/GTT' ||
                                        $mode->type_treno == 'Trenitalia train service'
                                    ) {
                                        array_push($mezzi, 'Treno');
                                        $treno = array_filter($modes, function ($mode) {
                                            return $mode->selTransportmean == 'Train';
                                        });
                                    } else {
                                        if ($mode->type_treno == 'Other train services ((Trenord, Italo, High Speed Train, etc)') {
                                            array_push($mezzi, 'Servizio ferroviario altri vettori (Trenord, Italo, Alta Velocità, ...)');
                                        } else {
                                            array_push($mezzi, $mode->type_treno);
                                        }
                                    }
                                } elseif ($mode->selTransportmean == 'On_foot') {
                                    array_push($mezzi, 'Piedi');
                                } else {
                                    array_push($mezzi, $mode->selTransportmean);
                                }
                            }
                            $q_d_id = Configure::read('Questions_spos.quale_distanza');
                            $c_s_id = Configure::read('Questions_spos.costo_spostamento');
                            $nr_modes_id = Configure::read('Questions_spos.nr_mezzi');
                            $q_m_id = Configure::read('Questions_spos.mezzo');
                            $q_am_id = Configure::read('Questions_spos.mezzi');
                            $q_sede_id = Configure::read('Questions_spos.sede_mappa');
                            if (!is_null($q_sede_id)) {
                                $a_q_sede = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_sede_id])
                                ->first();
                                if (empty($a_q_sede)) {
                                    $a_q_sede  = new Answer([
                                        'question_id' => $q_sede_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_q_sede->answer = $res->destination->office_name;
                                $result[$q_sede_id] = $res->destination->office_name;
                                if (!$this->Answers->save($a_q_sede)) {
                                    $info = $a_q_sede->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_sede_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.quale_distanza non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }
                            if (!is_null($q_d_id)) {
                                $a_q_d = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_d_id])
                                ->first();
                                if (empty($a_q_d)) {
                                    $a_q_d  = new Answer([
                                        'question_id' => $q_d_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_q_d->answer = $distance;
                                if (!$this->Answers->save($a_q_d)) {
                                    $info = $a_q_d->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_d_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.quale_distanza non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }
                            if (!is_null($c_s_id)) {
                                $a_c_s = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $c_s_id])
                                ->first();
                                if (empty($a_c_s)) {
                                    $a_c_s = new Answer([
                                        'question_id' => $c_s_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_c_s->answer = $cost;
                                if (!$this->Answers->save($a_c_s)) {
                                    $info = $a_c_s->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $c_s_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.costo_spostamento non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }
                            if (!is_null($nr_modes_id)) {
                                $a_nr = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $nr_modes_id])
                                ->first();
                                if (empty($a_nr)) {
                                    $a_nr = new Answer([
                                        'question_id' => $nr_modes_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_nr->answer = $n_modes;
                                if (!$this->Answers->save($a_nr)) {
                                    $info = $a_nr->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $nr_modes_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.nr_mezzi non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }
                            if (!is_null($q_m_id)) {
                                $a_m = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_m_id])
                                ->first();
                                if (empty($a_m)) {
                                    $a_m = new Answer([
                                        'question_id' => $q_m_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_m->answer = $max_mode;
                                // $result[$q_m_id]=$max_mode;
                                if (!$this->Answers->save($a_m)) {
                                    $info = $a_m->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_m_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.mezzo non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }
                            if (!is_null($q_am_id)) {
                                $a_am = $this->Answers->find()
                                ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_am_id])
                                ->first();
                                if (empty($a_am)) {
                                    $a_am = new Answer([
                                        'question_id' => $q_am_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                    ]);
                                }
                                $a_am->answer = $mezzi;
                                $result[$q_am_id] = $mezzi;
                                if (!$this->Answers->save($a_am)) {
                                    $info = $a_am->getErrors();
                                    //TODO: Mostrare messaggio in caso di errore di salvataggio
                                    $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_am_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                    Log::write('error', $msg);
                                }
                            } else {
                                $msg = 'AnswersUpdate: La domanda Questions_spos.mezzo non esiste nella tabella question sul database';
                                Log::write('error', $msg);
                            }

                            if (!empty($auto)) {
                                $q_auto_ids = ['emissioni_auto' => Configure::read('Questions_spos.emissioni_auto'),
                                'cilindrata_auto' => Configure::read('Questions_spos.cilindrata_auto'),
                                'alimentazione_auto' => Configure::read('Questions_spos.alimentazione_auto'),
                                'quale_distanza_auto' => Configure::read('Questions_spos.quale_distanza_auto'),
                                'tipo_auto' => Configure::read('Questions_spos.tipo_auto'),
                                ];

                                foreach ($q_auto_ids as $name_auto => $q_auto_id) {
                                    if (!is_null($q_auto_id)) {
                                        $a_auto = $this->Answers->find()
                                        ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_auto_id])
                                        ->first();
                                        if (empty($a_auto)) {
                                            $a_auto  = new Answer([
                                                'question_id' => $q_auto_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                            ]);
                                        }
                                        switch ($q_auto_id) {
                                            case Configure::read('Questions_spos.emissioni_auto'):
                                                $a_auto->answer = reset($auto)->emissioni_auto;
                                                $result[$q_auto_id] = reset($auto)->emissioni_auto;
                                                break;
                                            case Configure::read('Questions_spos.cilindrata_auto'):
                                                $a_auto->answer = reset($auto)->cilindratauto;
                                                $result[$q_auto_id] = reset($auto)->cilindratauto;
                                                break;
                                            case Configure::read('Questions_spos.alimentazione_auto'):
                                                $a_auto->answer = reset($auto)->alimentazioneauto;
                                                $result[$q_auto_id] = reset($auto)->alimentazioneauto;
                                                break;
                                            case Configure::read('Questions_spos.quale_distanza_auto'):
                                                $a_auto->answer = reset($auto)->distance;
                                                $result[$q_auto_id] = reset($auto)->distance;
                                                break;
                                            case Configure::read('Questions_spos.tipo_auto'):
                                                $a_auto->answer = reset($auto)->type_auto;
                                                $result[$q_auto_id] = reset($auto)->type_auto;
                                                break;
                                        }

                                        if (!$this->Answers->save($a_auto)) {
                                            $info = $a_auto->getErrors();
                                            //TODO: Mostrare messaggio in caso di errore di salvataggio
                                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_auto_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                            Log::write('error', $msg);
                                        }
                                    } else {
                                        $msg = "AnswersUpdate: La domanda Questions_spos.{$name_auto} non esiste nella tabella question sul database";
                                        Log::write('error', $msg);
                                    }
                                }
                            }

                            if (!empty($moto)) {
                                $q_moto_ids = ['emissioni_moto' => Configure::read('Questions_spos.emissioni_moto'),
                                'cilindrata_moto' => Configure::read('Questions_spos.cilindrata_moto'),
                                'alimentazione_moto' => Configure::read('Questions_spos.alimentazione_moto'),
                                'tipo_moto' => Configure::read('Questions_spos.tipo_moto'),
                                ];

                                foreach ($q_moto_ids as $name_moto => $q_moto_id) {
                                    if (!is_null($q_moto_id)) {
                                        $a_moto = $this->Answers->find()
                                        ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_moto_id])
                                        ->first();
                                        if (empty($a_moto)) {
                                            $a_moto  = new Answer([
                                                'question_id' => $q_moto_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                            ]);
                                        }

                                        switch ($q_moto_id) {
                                            case Configure::read('Questions_spos.emissioni_moto'):
                                                $a_moto->answer = reset($moto)->emissioni_moto;
                                                $result[$q_moto_id] = reset($moto)->emissioni_moto;
                                                // if(!is_null($a_moto->answer)){
                                                //     continue;
                                                // }else{
                                                // $a_moto->answer = reset($moto)->annoauto;
                                                // }
                                                break;
                                            case Configure::read('Questions_spos.cilindrata_moto'):
                                                $a_moto->answer = reset($moto)->cilindratmoto;
                                                $result[$q_moto_id] = reset($moto)->cilindratmoto;
                                                break;
                                            case Configure::read('Questions_spos.alimentazione_moto'):
                                                $a_moto->answer = reset($moto)->alimentazionemoto;
                                                $result[$q_moto_id] = reset($moto)->alimentazionemoto;
                                                break;
                                            case Configure::read('Questions_spos.tipo_moto'):
                                                $a_moto->answer = reset($moto)->type_moto;
                                                $result[$q_moto_id] = reset($moto)->type_moto;
                                                break;
                                        }
                                        if (!$this->Answers->save($a_moto)) {
                                            $info = $a_moto->getErrors();
                                            //TODO: Mostrare messaggio in caso di errore di salvataggio
                                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_moto_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                            Log::write('error', $msg);
                                        }
                                    } else {
                                        $msg = "AnswersUpdate: La domanda Questions_spos.{$name_moto} non esiste nella tabella question sul database";
                                        Log::write('error', $msg);
                                    }
                                }
                            }
                            if (!empty($bici)) {
                                $q_bici_ids = ['tipo_bici' => Configure::read('Questions_spos.tipo_bici'),
                                ];

                                foreach ($q_bici_ids as $name_bici => $q_bici_id) {
                                    if (!is_null($q_bici_id)) {
                                        $a_bici = $this->Answers->find()
                                        ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_bici_id])
                                        ->first();
                                        if (empty($a_bici)) {
                                            $a_bici = new Answer([
                                                'question_id' => $q_bici_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                            ]);
                                        }
                                        switch ($q_bici_id) {
                                            case Configure::read('Questions_spos.tipo_bici'):
                                                $a_bici->answer = reset($bici)->type_bike;
                                                $result[$q_bici_id] = reset($bici)->type_bike;
                                                break;
                                        }
                                        if (!$this->Answers->save($a_bici)) {
                                            $info = $a_bici->getErrors();
                                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_bici_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                            Log::write('error', $msg);
                                        }
                                    } else {
                                        $msg = "AnswersUpdate: La domanda Questions_spos.{$name_bici} non esiste nella tabella question sul database";
                                        Log::write('error', $msg);
                                    }
                                }
                            }
                            if (!empty($mono)) {
                                $q_mono_ids = ['tipo_mono' => Configure::read('Questions_spos.tipo_mono'),
                                ];

                                foreach ($q_mono_ids as $name_mono => $q_mono_id) {
                                    if (!is_null($q_mono_id)) {
                                        $a_mono = $this->Answers->find()
                                        ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_mono_id])
                                        ->first();
                                        if (empty($a_mono)) {
                                            $a_mono = new Answer([
                                                'question_id' => $q_mono_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                            ]);
                                        }
                                        switch ($q_mono_id) {
                                            case Configure::read('Questions_spos.tipo_mono'):
                                                $a_mono->answer = reset($mono)->type_mono;
                                                $result[$q_mono_id] = reset($mono)->type_mono;
                                                break;
                                        }
                                        if (!$this->Answers->save($a_mono)) {
                                            $info = $a_mono->getErrors();
                                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_mono_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                            Log::write('error', $msg);
                                        }
                                    } else {
                                        $msg = "AnswersUpdate: La domanda Questions_spos.{$name_mono} non esiste nella tabella question sul database";
                                        Log::write('error', $msg);
                                    }
                                }
                            }
                            if (!empty($treno)) {
                                $q_treno_ids = ['tipo_treno' => Configure::read('Questions_spos.tipo_treno'),
                                ];

                                foreach ($q_treno_ids as $name_treno => $q_treno_id) {
                                    if (!is_null($q_treno_id)) {
                                        $a_treno = $this->Answers->find()
                                        ->where(['survey_id' => $survey_id, 'user_id' => $user_id, 'question_id' => $q_treno_id])
                                        ->first();
                                        if (empty($a_treno)) {
                                            $a_treno = new Answer([
                                                'question_id' => $q_treno_id, 'survey_id' => $survey_id, 'user_id' => $user_id,
                                            ]);
                                        }
                                        switch ($q_treno_id) {
                                            case Configure::read('Questions_spos.tipo_treno'):
                                                $a_treno->answer = reset($treno)->type_treno;
                                                $result[$q_treno_id] = reset($treno)->type_treno;
                                                break;
                                        }
                                        if (!$this->Answers->save($a_treno)) {
                                            $info = $a_treno->getErrors();
                                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $q_treno_id, per l'utente $user_id, nel questionario $survey_id: $info";
                                            Log::write('error', $msg);
                                        }
                                    } else {
                                        $msg = "AnswersUpdate: La domanda Questions_spos.{$name_treno} non esiste nella tabella question sul database";
                                        Log::write('error', $msg);
                                    }
                                }
                            }
                        }

                        if (!$this->Answers->save($a)) {
                            $info = $a->getErrors();
                            //TODO: Mostrare messaggio in caso di errore di salvataggio
                            $msg = "AnswersUpdate: Impossibile aggiornare la domanda $qid, per l'utente $user_id, nel questionario $survey_id: $info";
                            Log::write('error', $msg);
                        }
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->set('result', $result);
            $this->viewBuilder()->setOption('serialize', ['errorMsg','result']);
        }
    }

    public function countResponders()
    {
        $this->allowRolesOnly(['admin', 'moma', 'user','moma_area']);

        $query = $this->Answers->find();

        $count = $query->select([
            'count' => $query->func()->count('DISTINCT user_id'),
        ])
            ->toArray()[0]['count'];

        $this->set(compact('count'));
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    public function getIndicator($indicator_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'user','moma_area']);

        $company_id = (int)$this->request->getQuery('company_id');
        $survey_id = (int)$this->request->getQuery('survey_id');
        $office_id = $this->request->getQuery('office_id');
        $default_sort = $this->request->getQuery('default_sort');
        $answer = $this->request->getQuery('answer');

        $identity = $this->Authentication->getIdentity();
        if ($identity->get('company_id')) {
            $company_id = (int)$identity->get('company_id');
        }
        if ($identity->get('office_id')) {
            $office_id  =  (int)$identity->get('office_id');
        }
        $subcompany  =   $identity->get('subcompany');

        $all_params = $this->request->getQueryParams();
        $filters = array_filter(
            $all_params,
            fn($flt) => str_starts_with($flt, 'filter_'),
            ARRAY_FILTER_USE_KEY
        );

        //Devo ricavare la survey di quell'azienda
        if (!empty($company_id) && empty($survey_id)) {
            $survey_id = (int)$this->Answers->Surveys->fromCompanyId($company_id);
        }

        if (!empty($office_id)) {
            $of = $this->Answers->Surveys->Users->find()
                        ->select(['office_id'])
                        ->where([
                            'office_id' => $office_id,
                        ])
                        ->first();
            if (empty($of)) {
                $office_id = null;
            }
        }

        $class = '\\App\\Indicator\\' . $indicator_id . 'Indicator';
        if (!class_exists($class)) {
            if (
                $indicator_id == 'costo-spostamento' or $indicator_id == 'quale-distanza' or $indicator_id == 'quale-distanza-auto' or $indicator_id == 'spesa-spostamento'
                or $indicator_id == 'distanza-totale'
            ) {
                $objIndicator = new RangeIndicator($indicator_id, $survey_id, $office_id, $filters, $subcompany);
            } else {
                $objIndicator = new baseIndicator($indicator_id, $survey_id, $office_id, $filters, $subcompany);
            }
        } else {
            $objIndicator = new $class($survey_id);    //Creo una classe dinamica con il nome passato come parametro
        }

        if (empty($answer) && !is_numeric($answer)) {
            $objIndicator->count($default_sort);
            $labels = $objIndicator->getLabels();
            $series = $objIndicator->getSeries();
            $Type = $objIndicator->getQuestionType();

            $this->set(compact('labels', 'series', 'Type'));
            $this->viewBuilder()->setOption('serialize', ['labels', 'series', 'Type']);
        } else {
            $list = $objIndicator->getList($answer);
            //Anonimizzato
            if (!empty($survey_id)) {
                $survey = $this->Answers->Surveys->findById($survey_id)->first();
            }
            if ($survey && $survey->sending_mode == 'z') {
                $faces = '👧🧒👦👩🧑👨🧑';
                foreach ($list as &$l) {
                    if (isset($l['email'])) {
                        $l['email'] = $this->anonymize($l['email']);
                    } else {
                        $l['email'] = '';
                    }
                    if (isset($l['first_name'])) {
                        $l['first_name'] = $this->anonymize($l['first_name']) ?? '';
                    } else {
                        $l['first_name'] = '';
                    }
                    if (isset($l['last_name'])) {
                        $l['last_name'] = 'Anonimizzato ' .  mb_substr($faces, rand(1, mb_strlen($faces) - 1), 1) ?? '';
                    } else {
                        $l['last_name'] = '';
                    }
                }
            }

            $this->set(compact('list'));
            $this->viewBuilder()->setOption('serialize', ['list']);
        }
    }

    private function anonymize($s)
    {
        if (empty($s)) {
            return '';
        }

        if ($l = strpos($s, '@')) {
            return substr($s, 0, 2) . '****' . substr($s, $l);
        } else {
            return substr($s, 0, 2) . '****' . substr($s, 20);
        }
    }

    public function completeAnswers($survey_id, $user_id)
    {
        $q = $this->Answers->find()
            ->innerJoinWith('Questions')  //Forse non è necessario riportare tutte le domande per ogni risposta, valuto di toglierlo
            ->leftJoinWith('Origins')
            ->leftJoinWith('Users')
            ->enableAutoFields(true)
            ->all();
    }

    public function usersList($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        $company_id = $identity->get('company_id');
        $office_id  =   $identity->get('office_id');
        $subcompany  =   $identity->get('subcompany');

        $result  = $this->Answers->find()
            ->distinct(['Answers.user_id']) // remeber to remove full group by -> SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));
            ->select([
                'Answers.user_id', 'Users.first_name', 'Users.last_name', 'Answers.modified',
                'Origins.lat', 'Origins.lon', 'Origins.geocoded_at', 'Origins.address',
            ])
            ->contain(['Origins','Users'])
            ->where(['Answers.survey_id' => $survey_id])
            ->order('Answers.modified DESC');

        //Filtro solo per gli utenti che hanno i miei attributi
        if ($office_id) {
            $result->matching('Users', function ($q) use ($office_id) {
                return $q->where(['Users.office_id' => $office_id]);
            });
        }
        if ($company_id) {
            $result->matching('Users', function ($q) use ($company_id) {
                return $q->where(['Users.company_id' => $company_id]);
            });
        }
        if ($subcompany) {
            $result->matching('Users', function ($q) use ($subcompany) {
                return $q->where(['Users.subcompany' => $subcompany]);
            });
        }

        $this->set('users', $result);
        $this->viewBuilder()->setOption('serialize', ['users']);
    }

    public function delete($survey_id, $user_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $this->request->allowMethod(['post', 'delete']);

        if (
            $this->Answers->deleteAll(
                ['survey_id' => $survey_id, 'user_id' => $user_id]
            )
        ) {
            $msg = 'Risposte eliminate con successo';
            $w = "getStats-$survey_id";
            // delete the cache
            Cache::delete($w, 'long');
        } else {
            $msg = 'Impossibile eliminare queste risposte';
        }

        $this->set('msg', $msg);
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    public function export($survey_id)
    {
        $answers = $this->Answers->find()
            ->where(['survey_id' => $survey_id])
            ->contain(['Questions'])
            ->order(['user_id', 'survey_id']);

        //Contiene i titoli delle domande
        $questions = [];

        $current_u = null;
        $result = [];
        foreach ($answers as $a) {
            if ($current_u != $a->user_id) {
                $current_u = $a->user_id;
            }
            $this->addQuestion($questions, $a->question->name, $a->question);
            if ($a->question->type == 'array') {
                foreach ($a->answer as $k => $v) {
                    $this->addQuestion($questions, $a->question->name . "/$k", $a->question);
                    $result[$current_u][$a->question->name . "/$k"] = $v;
                }
            } else {
                $result[$current_u][$a->question->name] = $this->decodeAnswer($a, $questions[$a->question->name]);
            }
        }

        //Semplifico le domande mettendo solo il nome
        foreach ($questions as $k => $q) {
            $questions[$k] = $k;
        }
        $this->set(compact('questions'));
        $this->set('answers', $result);
    }

    private function decodeAnswer($a, $q)
    {
        if ($q->type == 'single' || $q->type == 'text') {
            return $a->answer;
        }
        if ($q->type == 'multiple') {
            if (is_array($a->answer)) {
                return implode(',', $a->answer);
            }

            return $a->answer;
        }
    }

    private function addQuestion(&$questions, $q_id, $q)
    {
        if (!array_key_exists($q_id, $questions)) {
            $questions[$q_id] = $q;

            return;
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

    public function exportSurveyData($survey_id = null, $all = true, $allAnswers = true)
    {
        $this->allowRolesOnly(['admin', 'moma', 'user', 'moma_area']);
        $identity = $this->Authentication->getIdentity();
        $company_id = $identity->get('company_id');
        $office_id  =   $identity->get('office_id');
        $subcompany  =   $identity->get('subcompany');
        if ($this->request->getData('surveys') != null) {
            $survey_id = $this->request->getData('surveys');
            $questions_id = $this->request->getData('questions');
        } else {
            $questions_id = null;
        }

        $this->enqueue("App\Command\ExportSurveyDataCommand", $company_id, $office_id, $survey_id, $all, $subcompany, $allAnswers, $step = 0, $questions_id);
        $message = 'Il file  è in fase di generazione. Attendi qualche minuto';
        $this->set(compact('message'));
        $this->viewBuilder()->setOption('serialize', ['message']);
    }

    public function exportMapQuestion($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'user', 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        // $company_id = $identity->get('company_id');
        // $office_id  =   $identity->get('office_id');
        // $subcompany  =   $identity->get('subcompany');

        set_time_limit(180);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(20);

        $users = $this->Answers->find()
            ->select(['Answers.user_id'])
            ->contain(['Surveys', 'Questions'])
            ->where(['survey_id' => $survey_id,'question_id' => Configure::read('Questions.origine_spostamenti')])
            //->limit(100)
            ->distinct();

        //Filtro solo per gli utenti che hanno i miei attributi
        // if ($office_id) {
        //     $users->matching('Users', function ($q) use ($office_id) {
        //         return $q->where(['Users.office_id' => $office_id]);
        //     });
        // }
        // if ($company_id) {
        //     $users->matching('Users', function ($q) use ($company_id) {
        //         return $q->where(['Users.company_id' => $company_id]);
        //     });
        // }
        // if ($subcompany) {
        //     $users->matching('Users', function ($q) use ($subcompany) {
        //         return $q->where(['Users.subcompany' => $subcompany]);
        //     });
        // }


        // $this->loadModel('Questions_surveys');
        // $this->loadModel('Sections');

        // $sections_weight = $this->Sections->find()
        //     ->select(['id', 'weight'])
        //     ->toList();

        // $questions_in_survey = $this->Questions_surveys->find()
        //     ->select(['question_id', 'weight', 'section_id'])
        //     ->where(['survey_id' => $survey_id])
        //     ->distinct()
        //     ->toList();


        // $questions = $this->Answers->find()
        //     ->select(['Questions.section_id', 'Questions.id', 'Questions.name', 'Questions.description', 'Questions.options', 'Questions.type'])
        //     ->contain(['Surveys', 'Questions'])
        //     ->where(['survey_id' => $survey_id])
        //     ->distinct()
        //     ->toList();

        // $questions_used = $this->questions_used_in_survey($questions, $questions_in_survey);

        $questions_name = [];
        $questions_description = [];
        $questions_options = ['id', 'Completato'];
        $questions_index = ['id', 'Completato'];

        // usort($sections_weight, function ($a, $b) {
        //     return $a->weight - $b->weight;
        // });

        // $sections = $sections_weight;

        // foreach ($sections as $section) {

        //     $section_questions = array_filter($questions_used, function ($question) use ($section) {
        //         return ($question->question->section_id === $section->id);
        //     });

        //     if (empty($section_questions)) {
        //         continue;
        //     }

        //     usort($section_questions, function ($a, $b) {
        //         return $a->question->weight - $b->question->weight;
        //     });

        //     $flag_for_array_questions = 0;
        //     foreach ($section_questions as $question) {
        //         if (strtolower($question->question->type) != "multiple" &&
        //                 strtolower($question->question->type) != "array" &&
        //                 strtolower($question->question->type) != "map") {
        //             array_push($questions_name, $question->question->name);
        //             array_push($questions_description, $question->question->description);
        //             array_push($questions_options, $question->question->name);
        //         } elseif( strtolower($question->question->type) == "map") { //Risposta mappa
        //             //TODO: Qui devo esplodere i titoli della mappa
                    array_push(
                        $questions_name,
                        'mappa',
                        'mappa',
                        'mappa',
                        'mappa',
                        'mappa'
                    );
                    array_push(
                        $questions_description,
                        'o/d del dipendente',
                        'o/d del dipendente',
                        'o/d del dipendente',
                        'o/d del dipendente',
                        'o/d del dipendente',
                        'mezzo'
                    );
                    array_push(
                        $questions_options,
                        'citta',
                        'cap',
                        'provincia',
                        'lat-origine',
                        'lon-origine',
                        'sede',
                        'lat-sede',
                        'lon-sede',
                        'subcompany',
                        'Bici',
                        'Distanza',
                        'Costo',
                        'Tipo di Bici',
                        'Bus/Tram',
                        'Distanza',
                        'Costo',
                        'Treno',
                        'Distanza',
                        'Costo',
                        'tipo di Treno',
                        'Metro',
                        'Distanza',
                        'Costo',
                        'Piedi',
                        'Distanza',
                        'Costo',
                        'Monopattino',
                        'Distanza',
                        'Costo',
                        'tipo di Monopattino',
                        'Auto',
                        'Distanza',
                        'Costo',
                        'Tipo di auto',
                        'Tipo Conducente',
                        'Classe Ambientale',
                        'Cilindrata',
                        "L'alimentazione",
                        'Moto',
                        'Distanza',
                        'Costo',
                        'Tipo di moto',
                        'Classe Ambientale',
                        'Cilindrata',
                        "L'alimentazione"
                    );
                    array_push(
                        $questions_index,
                        'citta',
                        'cap',
                        'provincia',
                        'lat-origine',
                        'lon-origine',
                        'sede',
                        'lat-sede',
                        'lon-sede',
                        'subcompany',
                        'Bike',
                        'Distanza',
                        'Costo',
                        'Tipo di Bici',
                        'Bus/Tram',
                        'Distanza',
                        'Costo',
                        'Train',
                        'Distanza',
                        'Costo',
                        'tipo di Treno',
                        'Metro',
                        'Distanza',
                        'Costo',
                        'On_foot',
                        'Distanza',
                        'Costo',
                        'Monopattino',
                        'Distanza',
                        'Costo',
                        'tipo di Monopattino',
                        'Auto',
                        'distance',
                        'cost',
                        'type_auto',
                        'type_personale',
                        'alimentazioneauto',
                        'emissioni_auto',
                        'cilindratauto',
                        'Moto',
                        'Distanza',
                        'Costo',
                        'Tipo di moto',
                        'Classe Ambientale',
                        'Cilindrata',
                        "L'alimentazione"
                    );

                    // array_push($questions_name, "mappa");
                    // array_push($questions_description, "o/d del dipendente");
                    // array_push($questions_options, "cap");

                    // array_push($questions_name, "mappa");
                    // array_push($questions_description, "o/d del dipendente");
                    // array_push($questions_options, "provincia");

                    // array_push($questions_name, "mappa");
                    // array_push($questions_description, "o/d del dipendente");
                    // array_push($questions_options, "sede");

                    // array_push($questions_name, "mappa");
                    // array_push($questions_description, "o/d del dipendente");
                    // array_push($questions_options, "subcompany");
        //         } else { //Risposta multipla o array
        //             $flag_for_array_questions = 0;
        //             foreach ($question->question->options as $option) {
        //                 if (is_array($option) && $question->question->type === "array") {
        //                     $flag_for_array_questions = 1;
        //                     foreach ($option as $group_option) {
        //                         array_push($questions_name, $question->question->name);
        //                         array_push($questions_description, $question->question->description);
        //                         if (isset($group_option['label'])){
        //                             array_push($questions_options, $group_option['label']);     //original
        //                         } else{
        //                             array_push($questions_options, $group_option);  // change
        //                         }
        //                     }
        //                 } else { //risposta multipla
        //                     if ($flag_for_array_questions == 1) {
        //                         $flag_for_array_questions = 0;
        //                         continue;
        //                     }
        //                     if (is_array($option)) {
        //                         $question_option_to_string = implode(', ', $option);
        //                         array_push($questions_name, $question->question->name);
        //                         array_push($questions_description, $question->question->description);
        //                         array_push($questions_options, $question_option_to_string);
        //                         continue;
        //                     }
        //                     //TODO: Verificare perchè in mezzi_motivo si disallineano name, description e options
        //                     array_push($questions_name, $question->question->name);
        //                     array_push($questions_description, $question->question->description);
        //                     array_push($questions_options, $option);
        //                 }
        //             }
        //         }
        //     }
        // }

        $sheet->fromArray(
            $questions_name,
            null,
            'C1'
        );

        $row = 3;
        $col = 'A';
        foreach ($questions_options as $question_option) {
            $sheet->setCellValue("$col$row", $question_option);
            $col++;
        }

        $row = 2;
        $col = 'C';
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
            $complete = $this->answersMapQuestion($survey_id, $questions_name, $questions_options, $user->user_id, $sheet, $current_row, $questions_index);
            if ($complete == false) {
                continue;
            } else {
                $current_row++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save(TMP . 'out.xlsx');

        $response = $this->response->withFile(
            TMP . 'out.xlsx',
            ['download' => true, 'name' => "$survey_id.xlsx"]
        );

        return $response;
    }

    private function answersMapQuestion($survey_id, $questions_name, $questions_options, $user_id, $sheet, $current_row, $questions_index)
    {
        $user_answers = $this->Answers->find()
            ->select(['Answers.user_id', 'Answers.created', 'Answers.answer',
                        'Questions.name', 'Questions.options', 'Questions.type',
                        'Users.email', 'Users.subcompany'])
            ->contain(['Questions' => [
                            'fields' => ['name','type','options'],
                        ],
                        'Users' => [
                            'fields' => ['id','email','subcompany'],
                            'Offices' => ['fields' => ['id','name','lat','lon']],
                        ],
                    ])
            ->where(['Answers.survey_id' => $survey_id, 'Answers.user_id' => $user_id,'Answers.question_id' => Configure::read('Questions.origine_spostamenti')])
            ->matching('Users.SurveyParticipants', function ($q) {
                return $q->where(['SurveyParticipants.survey_completed_at IS NOT NULL']);
            });
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
                $sheet->setCellValue([2, $current_row], $answer->created);
                $first = false;
            }

            $question_column = 3;
            // $index = array_search($answer->question->name, $questions_name);
            $index = 0;
            // prova
            if ($index === false) {
                continue;
            }
            $question_column  += $index;

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

                     //Subcompany
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
                    if (is_array($dec) && isset($dec['modes'])) {
                        foreach ($dec['modes'] as $modes) {
                            foreach ($modes as $key => $mode) {
                                if ($key == 'selTransportmean') {
                                    $index = array_search($mode, $questions_index);
                                    if ($index !== false) {
                                        $question_column = $index + 1;
                                    }
                                } elseif (isset($mode['selTransportmean']) && $mode['selTransportmean'] == 'Auto') {
                                    $index = array_search($key, $questions_index);
                                    if ($index !== false) {
                                        $question_column = $index + 1;
                                    }
                                }
                                if ($mode == 'Bike') {
                                    $mode = 'Bici';
                                } elseif ($mode == 'Scooter') {
                                    $mode = 'Monopattino';
                                } elseif ($mode == 'Train') {
                                    $mode = 'Treno';
                                } elseif ($mode == 'On_foot') {
                                    $mode = 'Piedi';
                                }
                                $sheet->setCellValue([$question_column++, $current_row], $mode);
                            }
                        }
                    }
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
                    foreach ($answer->answer as $ans) {
                        $sheet->setCellValue([$temp_col, $current_row], $ans);
                        $temp_col++;
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
                                $i = $index + 2;
                                $i < $index + 2 + $options_length;
                                $i++
                            ) {
                                $user_chose = $questions_options[$i];
                                if (substr($user_chose, 0, 107) === substr($ans, 0, 107)) {
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
            $this->allowRolesOnly(['admin', 'moma', 'user', 'moma_area']);
        if ($survey_id == null) {
            $survey_id = $this->request->getData();
            $this->loadModel('Questions_surveys');
        }
            $questions_in_survey = $this->Questions_surveys->find()
                        ->select(['question_id', 'weight', 'section_id'])
                        ->where(['survey_id IN' => $survey_id])
                        ->distinct();
        if (!$all) {
            $questions = $this->Answers->find()->select(['Questions.id', 'Questions.name', 'Questions.description'])
            ->contain(['Surveys', 'Questions'])
            ->where(['survey_id IN' => $survey_id])
            ->distinct();
            $questions_used = $this->questions_used_in_survey($questions, $questions_in_survey);
            $this->set('questions', $questions_used);
            $this->viewBuilder()->setOption('serialize', ['questions']);
            // $this->render('export_questions');
        } else {
            $questions = $this->Answers->find()
            ->select(['Questions.section_id', 'Questions.id', 'Questions.name', 'Questions.description', 'Questions.options', 'Questions.type'])
            ->contain(['Surveys', 'Questions'])
            ->where(['survey_id IN' => $survey_id])
            ->distinct();

            return $this->questions_used_in_survey($questions, $questions_in_survey);
        }
    }

    private function isBeanstalkdRunning($host = '127.0.0.1', $port = 11300)
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);

            return true; // Connection successful, Beanstalkd is running
        } else {
            return false; // Connection failed, Beanstalkd is not running
        }
    }

    /**
     * Mette in coda un task per la generazione offline dell'esportazione
     *
     * @param mixed $command
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $survey_id
     * @param mixed $all
     * @return void
     */
    public function enqueue($command, $company_id, $office_id, $survey_id, $all, $subcompany, $allAnswers, $step, $questions_id = null)
    {
        // Connect to Beanstalkd server
        if (!$this->isBeanstalkdRunning()) {
            if (is_array($survey_id)) {
                $survey_id = 'totall';
            }
            Log::write('info', "No beanstalk, do $command: without a queue for $company_id $office_id $survey_id");

            return false;
        }

        $sitedir = Configure::read('sitedir');
        $pheanstalk = Pheanstalk::create('127.0.0.1');
        $tube       = new TubeName("$sitedir-pscl-exporter");

        // Queue a Job
        $pheanstalk->useTube($tube);
        $job = $pheanstalk->put(
            data: json_encode([
                'command' => $command,
                'all' => $all,
                'allAnswers' => $allAnswers,
                'survey_id' => $survey_id,
                'questions_id' => $questions_id,
                'company_id' => $company_id,
                'office_id' => $office_id,
                'subcompany' => $subcompany,
                ], JSON_THROW_ON_ERROR),
            priority: Pheanstalk::DEFAULT_PRIORITY,
            delay: 30,
            timeToRelease: 60
        );
        $this->setQueueStatus($survey_id, $job->getId(), $step);
        if (is_array($survey_id)) {
            $survey_id = 'totall';
        }
        Log::write('info', "$command: in queue for $company_id $office_id $survey_id, job_id: {$job->getId()}");

        return true;
    }

    public function setQueueStatus($survey_id, $job_id, $step)
    {
        $lock = [-1,-1,-1,-1,-1];
        // if survey_id is array change value to totall
        if (is_array($survey_id)) {
            $survey_id = 'totall';
        }
        $job_ids = Cache::read("beanstalk-survey-exporter-batch-$survey_id", 'long');
        $job_ids[$step] = $job_id;
        foreach ($job_ids as $key => $value) {
            $lock[$key] = $job_ids[$key];
        }
        Cache::write("beanstalk-survey-exporter-batch-$survey_id", $lock, 'long');
    }

    public function getQueueStatus($survey_id)
    {
        $lock = [];
        $message = [];
        if (is_array($survey_id) || $survey_id === 'null') {
            $survey_id = 'totall';
        }
        $job_ids = Cache::read("beanstalk-survey-exporter-batch-$survey_id", 'long');
        if (empty($job_ids)) {
            $lock = [-1, -1, -1, -1, -1];
            $message[] = 'Non eseguito';
        } else {
            $pheanstalk = Pheanstalk::create('127.0.0.1');

            foreach ($job_ids as $key => $job_id) {
                if ($job_id > 0) {
                    $jobId = new JobId($job_id);
                    try {
                        $job = $pheanstalk->peek($jobId);
                        $lock[$key] = $job_id; //Working
                        $message[$key] = 'In lavorazione';
                    } catch (Exception $e) {
                        $lock[$key] = 0; //Finished
                        $message[$key] = 'Completato';
                        $response = $this->response->withFile(
                            TMP . 'out.xlsx',
                            ['download' => true, 'name' => "$survey_id.xlsx"]
                        );
                        $this->set('lock', $lock);
                        $this->set('message', $message);
                        $this->viewBuilder()->setOption('serialize', ['message', 'lock']);

                        return $response;
                    }
                }
            }
        }
        $this->set('lock', $lock);
        $this->set('message', $message);
        $this->viewBuilder()->setOption('serialize', ['message', 'lock']);
    }
    // generate deleted users by survey_id
    public function generateDeletedUsers($survey_id = null)
    {
        $this->allowRolesOnly(['admin']);
        try {
            $result  = $this->Answers->find()->notMatching('Users', function ($q) {
                return $q;
            })
                ->select(['Answers.user_id', 'Answers.survey_id'])
                ->where(['Answers.survey_id' => $survey_id])
                ->distinct()
                ->limit(1000);
            // if result empty then return
            $GenUsersCount= $result->count();
            if(empty($GenUsersCount)) {
                $errorMsg = 'Nessun utente cancellato trovato per il sondaggio selezionato';
                $this->set(compact('errorMsg'));
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);
                return;
            }
            
            
            $survey = $this->Answers->Surveys
            ->find()
            ->where(['id' => $survey_id])
            ->first();
            if (empty($survey)) {
                throw new NotFoundException("Survey $survey not found");
            }
            $company_id = $survey->company_id;
            //Genero un utente anonimo per questa azienda per ogni partecipante mancante
            foreach ($result as $row) {
                $user_id = $row->user_id;
                $user = $this->Answers->Users->newEntity([
                'id' => $user_id,
                'username' => "$user_id@email.invalid",
                'email' => "$user_id@email.invalid",
                'password' => 'fake unguessable password (no string could have this as hashed value)',
                'first_name' => 'Partecipante',
                'last_name' => 'Anonimo',
                'active' => 0,
                'role' => 'user',
                'company_id' => $company_id,
                'years' => [(string)$survey['year']],
                ]);
                $this->Answers->Users->save($user);
                $surveyParticipant = $this->Answers->Users->SurveyParticipants->newEmptyEntity();
                $surveyParticipant->survey_id = (int)$survey_id;
                $surveyParticipant->user_id = $row->user_id;
                $surveyParticipant->id = Text::uuid();
                $this->Answers->Users->SurveyParticipants->save($surveyParticipant);
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->set(compact('message', 'errorMsg'));
            $this->viewBuilder()->setOption('serialize', [ 'errorMsg']);
            return;
        }
        $errorMsg = "Generati $GenUsersCount utenti cancellati per il sondaggio $survey_id";
        $this->set(compact('errorMsg'));
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);



    }

}

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

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Companies Model
 */
class CompanySurveyHistoryTable extends Table
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

        $this->setTable('company_survey_history');
        $this->setPrimaryKey('id');

        $this->belongsTo('Companies', [
            'className' => 'Companies',
            'foreignKey' => 'company_id',
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

    /**
     * Ultima riga di storia survey a livello azienda per un dato anno (office_id NULL).
     *
     * Usata sia da CompaniesController::checkTasks() (strict per anno) sia da view()
     * (con fallback opzionale). L'anno è applicato solo se numerico.
     *
     * @param int $companyId Id azienda.
     * @param string|int|null $year Anno di riferimento.
     * @param string $type Tipo di survey storica (default 'generale').
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function latestCompanyEntry(int $companyId, $year, string $type = 'generale'): ?EntityInterface
    {
        $conditions = [
            'company_id' => $companyId,
            'office_id IS' => null,
            'type' => $type,
        ];
        if (is_numeric($year)) {
            $conditions['year'] = (int)$year;
        }

        return $this->find()
            ->where($conditions)
            ->order(['created' => 'DESC'])
            ->first();
    }

    /**
     * Ultima riga di storia survey a livello sede per un dato anno ('sede' o 'aggregated').
     *
     * @param int $companyId Id azienda.
     * @param int $officeId Id sede.
     * @param string|int|null $year Anno di riferimento.
     * @param string $type Tipo di survey storica ('sede' o 'aggregated').
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function latestOfficeEntry(int $companyId, int $officeId, $year, string $type): ?EntityInterface
    {
        $conditions = [
            'company_id' => $companyId,
            'office_id' => $officeId,
            'type' => $type,
        ];
        if (is_numeric($year)) {
            $conditions['year'] = (int)$year;
        }

        return $this->find()
            ->where($conditions)
            ->order(['created' => 'DESC'])
            ->first();
    }

    /**
     * Normalizza un anno a 4 cifre (1900-2999). Null se non valido.
     *
     * @param mixed $year Valore grezzo.
     * @return int|null
     */
    public static function parseYear($year): ?int
    {
        if (is_int($year)) {
            $year = (string)$year;
        }
        if (!is_string($year) || !preg_match('/^[12][0-9]{3}$/', $year)) {
            return null;
        }

        return (int)$year;
    }

    /**
     * Anni distinti (decrescenti) con almeno una riga di storia per il foglio indicato.
     *
     * @param int $companyId Id azienda.
     * @param int|null $officeId Id sede (null = foglio azienda, type 'generale').
     * @param string $type 'generale', 'sede' o 'aggregated'.
     * @return array<int>
     */
    public function sheetYears(int $companyId, ?int $officeId, string $type): array
    {
        $query = $this->find()
            ->select(['year'])
            ->distinct(['year'])
            ->where(['company_id' => $companyId, 'type' => $type])
            ->order(['year' => 'DESC'])
            ->disableHydration();
        $query->where($officeId === null ? ['office_id IS' => null] : ['office_id' => $officeId]);

        return array_map(fn($r) => (int)$r['year'], $query->all()->toList());
    }

    /**
     * Copia (solo storia) la risposta di un foglio dall'anno sorgente all'anno di destinazione:
     * aggiorna la riga (company, office, anno, type) se esiste, altrimenti la crea.
     * Non tocca companies.survey / offices.survey.
     *
     * @param int $companyId Id azienda.
     * @param int|null $officeId Id sede (null = foglio azienda).
     * @param string $type 'generale', 'sede' o 'aggregated'.
     * @param int $fromYear Anno sorgente.
     * @param int $toYear Anno di destinazione.
     * @return \Cake\Datasource\EntityInterface|null La riga di destinazione, null se manca la sorgente.
     */
    public function copySheetYear(
        int $companyId,
        ?int $officeId,
        string $type,
        int $fromYear,
        int $toYear
    ): ?EntityInterface {
        $source = $officeId === null
            ? $this->latestCompanyEntry($companyId, $fromYear, $type)
            : $this->latestOfficeEntry($companyId, $officeId, $fromYear, $type);
        if (empty($source)) {
            return null;
        }

        return $this->getConnection()->transactional(function () use ($source, $companyId, $officeId, $type, $toYear) {
            $target = $officeId === null
                ? $this->latestCompanyEntry($companyId, $toYear, $type)
                : $this->latestOfficeEntry($companyId, $officeId, $toYear, $type);
            if (empty($target)) {
                $target = $this->newEmptyEntity();
                $target->company_id = $companyId;
                $target->office_id = $officeId;
                $target->year = $toYear;
                $target->type = $type;
            }
            $target->answer = $source->answer;

            return $this->saveOrFail($target);
        });
    }
}

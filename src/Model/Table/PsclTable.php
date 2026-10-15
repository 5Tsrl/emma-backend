<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

/**
 * Pscl Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 * @property \App\Model\Table\SurveysTable&\Cake\ORM\Association\BelongsTo $Surveys
 * @method \App\Model\Entity\Pscl newEmptyEntity()
 * @method \App\Model\Entity\Pscl newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Pscl[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Pscl get($primaryKey, $options = [])
 * @method \App\Model\Entity\Pscl findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Pscl patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Pscl[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Pscl|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Pscl saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PsclTable extends Table
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

        $this->setTable('pscl');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);
        $this->belongsTo('Offices', [
            'foreignKey' => 'office_id',
        ]);
        $this->belongsTo('Surveys', [
            'foreignKey' => 'survey_id',
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
            ->scalar('version_tag')
            ->maxLength('version_tag', 45)
            ->allowEmptyString('version_tag');

        $validator
            ->integer('company_id')
            ->allowEmptyString('company_id');

        $validator
            ->integer('office_id')
            ->allowEmptyString('office_id');

        $validator
            ->integer('survey_id')
            ->allowEmptyString('survey_id');

        $validator
            ->allowEmptyString('plan');

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
        $rules->add($rules->existsIn('company_id', 'Companies'), ['errorField' => 'company_id']);
        $rules->add($rules->existsIn('office_id', 'Offices'), ['errorField' => 'office_id']);
        $rules->add($rules->existsIn('survey_id', 'Surveys'), ['errorField' => 'survey_id']);

        return $rules;
    }

    public function getFiles($company_id, $office_id, $year = 'TUTTI', $pathName = '')
    {
        if ($year == 'TUTTI') {
            if ($pathName != '') {
                $files = Cache::read("pscl-$company_id/$office_id/$pathName", 'long');
            } else {
                $files = Cache::read("pscl-$company_id/$office_id", 'long');
            }
        } else {
            if ($office_id == 'null') {
                if ($pathName != '') {
                    $files = Cache::read("pscl-$year/$company_id/$pathName", 'long');
                } else {
                    $files = Cache::read("pscl-$year/$company_id", 'long');
                }
            } else {
                if ($pathName != '') {
                    $files = Cache::read("pscl-$year/$company_id/$office_id/$pathName", 'long');
                } else {
                    $files = Cache::read("pscl-$year/$company_id/$office_id", 'long');
                }
            }
        }

        $folderName = $this->getFolderName($company_id, $office_id, $year, $pathName);
        if (empty($files)) {
            $folder_path =  ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . '/PSCL/' . $folderName;
            $dir = new Folder($folder_path);
            $files = $dir->find('.*', true);
            if ($year == 'TUTTI') {
                if ($pathName != '') {
                    Cache::write("pscl-$company_id/$office_id/$pathName", $files, 'long');
                } else {
                    Cache::write("pscl-$company_id-$office_id", $files, 'long');
                }
            } else {
                if ($office_id == 'null') {
                    if ($pathName != '') {
                        Cache::write("pscl-$year/$company_id/$pathName", $files, 'long');
                    } else {
                        Cache::write("pscl-$year-$company_id", $files, 'long');
                    }
                } else {
                    if ($pathName != '') {
                        Cache::write("pscl-$year/$company_id/$office_id/$pathName", $files, 'long');
                    } else {
                        Cache::write("pscl-$year-$company_id-$office_id", $files, 'long');
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Estrae un file dal filesystem per il PSCL
     *
     * @param mixed $folder_path
     * @param mixed $file
     * @param mixed $year
     * @param mixed $file
     * @return string|false
     * @throws \InvalidArgumentException
     */
    public function getFile($folder_path, $file)
    {
        $file_path = $folder_path . '/' . $file;
        $file = file_get_contents($file_path);

        return $file;
    }

    /**
     * Salva un file nel filesystem per il PSCL
     *
     * @param mixed $folder_path
     * @param mixed $file
     * @param mixed $data
     * @param mixed $file
     * @param mixed $data
     * @return int|false
     * @throws \InvalidArgumentException
     */
    public function saveFile($folder_path, $file, $data)
    {
        $file_path = $folder_path . '/' . $file;
        $file = file_put_contents($file_path, $data);

        return $file;
    }

    /**
     * Ottiene il nome del folder per un PSCL
     *
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $year
     * @return string
     */
    public function getFolderName($company_id, $office_id, $year, $pathName = '')
    {
        if ($year == 'TUTTI') {
            if ($pathName != '') {
                $folderName = $company_id . '/' . $office_id . '/' . $pathName;
            } else {
                $folderName = $company_id . '/' . $office_id;
            }
        } else {
            if ($office_id == 'null') {
                if ($pathName != '') {
                    $folderName = $year . '/' . $company_id . '/' . $pathName;
                } else {
                    $folderName = $year . '/' . $company_id;
                }
            } else {
                if ($pathName != '') {
                    $folderName = $year . '/' . $company_id . '/' . $office_id . '/' . $pathName;
                } else {
                    $folderName = $year . '/' . $company_id . '/' . $office_id;
                }
            }
        }

        return $folderName;
    }

    public function getExporterClass($format)
    {
        //Verifico che il formato sia corretto, altrimenti prendo Html
        //TODO: leggere i formati da un file di configurazione
        if (!in_array($format, Configure::read('Exporter.extensions', ['html', 'docx']))) {
            $format = 'html';
        }
        $format = Inflector::camelize($format);
        //$format = ucfirst(strtolower($format));

        //Uso il formattatore corretto
        $class = '\\App\\Exporter\\' . $format . 'Exporter';
        if (!class_exists($class)) {
            throw new \Exception('Formato non supportato');
        }

        return $class;
    }

    public function getFolderPath($year, $company_id, $office_id, $pathName = '')
    {
        $folderName = $this->getFolderName($company_id, $office_id, $year, $pathName);

        return ROOT . DS . Configure::read('filesPath') . DS . Configure::read('sitedir') . '/PSCL/' . $folderName;
    }

    /**
     * Elenco dei template di piano PSCL (righe con version_tag='template').
     *
     * Un template è un insieme ORDINATO di assi (measure type=0)/misure —
     * distinto dal template grafico docx gestito da uploadTemplate() — che
     * viene copiato (vedi buildPlanFromTemplate()) in un nuovo record pscl al
     * momento della creazione. company_id/office_id/survey_id/year restano
     * NULL per queste righe; il nome del template vive in `plan[0]['name']`
     * (stesso bucket di servizio usato per i metadati di protocollo/adozione).
     *
     * @return \Cake\ORM\Query
     */
    public function findTemplates()
    {
        return $this->find()
            ->where(['version_tag' => 'template'])
            ->order(['id' => 'ASC']);
    }

    /**
     * Costruisce un piano piatto (bucket plan[1]) a partire da un template
     * salvato, azzerando ogni `value`.
     *
     * CORREZIONE (2026-07-28, vedi CLAUDE.md/dev-notes "Flat PSCL plan
     * format"): `year` viene lasciato SEMPRE `null`, anche quando si conosce
     * già l'anno del nuovo pscl — decisione di prodotto invertita rispetto
     * alla versione precedente (che lo valorizzava una tantum): l'utente deve
     * compilare esplicitamente l'anno di ogni asse/misura per poter validare
     * il piano, non deve trovarselo già precompilato.
     *
     * @param int $templateId Id della riga pscl di tipo template.
     * @return array<int,array<string,mixed>>
     * @throws \Cake\Datasource\Exception\RecordNotFoundException Se il template non esiste.
     */
    public function buildPlanFromTemplate(int $templateId): array
    {
        $template = $this->find()
            ->where(['id' => $templateId, 'version_tag' => 'template'])
            ->firstOrFail();

        $entries = is_array($template->plan[1] ?? null) ? $template->plan[1] : [];

        return array_values(array_map(function ($entry) {
            $entry['value'] = 0;
            $entry['year'] = null;

            return $entry;
        }, $entries));
    }

    /**
     * Costruisce un piano piatto (bucket plan[1]) direttamente dal catalogo
     * Measures corrente (assi type=0 + misure figlie ordinate per `weight`),
     * usato quando un nuovo pscl viene creato senza selezionare un template.
     * Rispecchia la struttura prodotta da MigratePsclPlanFormatCommand.
     * `year` è sempre `null` (vedi CORREZIONE su buildPlanFromTemplate()).
     *
     * @return array<int,array<string,mixed>>
     */
    public function buildPlanFromMeasures(): array
    {
        $Measures = \Cake\ORM\TableRegistry::getTableLocator()->get('Measures');
        $measures = $Measures->find('ordered')->toArray();

        $entries = [];
        $currentAxisId = 0;
        foreach ($measures as $measure) {
            $isAxis = (int)$measure->type === 0;
            $entries[] = [
                'value' => 0,
                'pillar_id' => $isAxis ? 0 : $currentAxisId,
                'measure_id' => $measure->id,
                'measure_slug' => $measure->slug,
                'weight' => $measure->weight,
                'year' => null,
            ];
            if ($isAxis) {
                $currentAxisId = $measure->id;
            }
        }

        return $entries;
    }

    /**
     * Stato di compilazione di un piano PSCL (bucket plan[1]) per un anno: funzione pura.
     *
     * Regole (il frontend le replica esattamente, vedi 5t.vue `pscl-plan-status.service.js`):
     *  - una voce con `value` <= 0 (o non numerico) e' ignorata, TRANNE una voce asse per
     *    `missing_year`: nei dati reali gli assi hanno value 0 e contano se hanno misure value>0;
     *  - `pillar_id === 0` => voce ASSE (il suo `measure_id` e' l'id dell'asse), altrimenti
     *    voce MISURA il cui `pillar_id` e' l'id (measure_id) dell'asse padre;
     *  - `missing_year`: un asse con almeno una misura value>0 e `year` null/'' + ogni
     *    misura value>0 con `year` null/'';
     *  - `unconfirmed`: ogni voce (asse o misura) value>0, `year` valorizzato e diverso
     *    (confronto come stringa) dall'anno richiesto.
     * Le voci non hanno nome: lo aggiunge il chiamante (vedi measureNames()).
     *
     * @param array<mixed> $plan JSON `pscl.plan` ($plan[1] = lista piatta di voci).
     * @param int $year Anno selezionato.
     * @return array{missing_year: array<int,array<string,mixed>>, unconfirmed: array<int,array<string,mixed>>}
     */
    public function planStatus(array $plan, int $year): array
    {
        $missing = [];
        $unconfirmed = [];
        $entries = $plan[1] ?? null;
        if (!is_array($entries)) {
            return ['missing_year' => [], 'unconfirmed' => []];
        }

        $isEmpty = fn($y): bool => $y === null || $y === '';
        // Assi che hanno almeno una misura selezionata (value>0).
        $axesWithMeasures = [];
        foreach ($entries as $e) {
            if (!is_array($e) || (float)($e['value'] ?? 0) <= 0) {
                continue;
            }
            if ((int)($e['pillar_id'] ?? 0) !== 0) {
                $axesWithMeasures[(int)$e['pillar_id']] = true;
            }
        }

        foreach ($entries as $e) {
            if (!is_array($e) || !isset($e['measure_id'])) {
                continue;
            }
            $isAxis = (int)($e['pillar_id'] ?? 0) === 0;
            $selected = (float)($e['value'] ?? 0) > 0;
            $entryYear = $e['year'] ?? null;
            $item = ['measure_id' => (int)$e['measure_id'], 'type' => $isAxis ? 'axis' : 'measure', 'year' => $entryYear];
            if ($isEmpty($entryYear)) {
                // Asse: conta se ha almeno una misura selezionata, a prescindere dal proprio
                // `value` (nei dati reali le voci asse hanno value 0). Misura: solo se value>0.
                if ($isAxis ? isset($axesWithMeasures[(int)$e['measure_id']]) : $selected) {
                    $missing[] = $item;
                }
            } elseif ($selected && (string)$entryYear !== (string)$year) {
                $unconfirmed[] = $item;
            }
        }

        return ['missing_year' => $missing, 'unconfirmed' => $unconfirmed];
    }

    /**
     * Risolve `measure_id => nome` dalla tabella Measures (tradotto secondo il locale
     * corrente, Translate behavior), con fallback a `slug` e poi a '#id'.
     *
     * @param array<int,int|string> $ids Id delle misure/assi.
     * @param array<int,string> $slugs Slug di fallback indicizzati per measure_id.
     * @return array<int,string>
     */
    public function measureNames(array $ids, array $slugs = []): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $names = [];
        if (!empty($ids)) {
            $rows = \Cake\ORM\TableRegistry::getTableLocator()->get('Measures')->find()
                ->select(['Measures.id', 'Measures.name', 'Measures.slug'])
                ->where(['Measures.id IN' => $ids])
                ->all();
            foreach ($rows as $row) {
                $names[(int)$row->id] = (string)($row->name ?: $row->slug);
            }
        }
        foreach ($ids as $id) {
            if (empty($names[$id])) {
                $names[$id] = !empty($slugs[$id]) ? (string)$slugs[$id] : '#' . $id;
            }
        }

        return $names;
    }

    /**
     * Costruisce un piano piatto (bucket plan[1]) copiando le voci del piano
     * dell'anno precedente per lo stesso company/office (opzione "copia dallo
     * scorso anno" nella creazione guidata di un nuovo pscl). A differenza di
     * buildPlanFromTemplate()/buildPlanFromMeasures(), qui `value` NON viene
     * azzerato (si copiano le misure/valori scelti l'anno scorso) — ma `year`
     * viene comunque riportato a `null` su ogni voce, per lo stesso motivo:
     * l'utente deve ri-valorizzare esplicitamente l'anno.
     *
     * @param int|string|null $company_id Id azienda.
     * @param int|string|null $office_id Id sede, oppure 'null'/null per il piano di azienda.
     * @param int|string $previousYear Anno del piano da cui copiare.
     * @return array<int,array<string,mixed>> Vuoto se non esiste un piano per l'anno precedente.
     */
    public function buildPlanFromPreviousYear($company_id, $office_id, $previousYear): array
    {
        $conditions = ['company_id' => $company_id, 'year' => $previousYear];
        if ($office_id !== null && $office_id !== 'null') {
            $conditions['office_id'] = $office_id;
        } else {
            $conditions['office_id IS'] = null;
        }

        $previous = $this->find()->where($conditions)->first();
        if ($previous === null || !is_array($previous->plan[1] ?? null)) {
            return [];
        }

        return array_values(array_map(function ($entry) {
            $entry['year'] = null;

            return $entry;
        }, $previous->plan[1]));
    }

    /**
     * Costruisce un piano piatto (bucket plan[1]) copiando le voci di uno
     * specifico pscl di riferimento (qualunque company/office/anno, non
     * necessariamente lo stesso company/office o l'anno immediatamente
     * precedente) — usato dal dialog "scegli da quale PSCL di riferimento
     * partire" mostrato quando l'azienda ha già PSCL nel passato (vedi
     * PsclController::history()/PsclController::edit(), parametro
     * `copy_from_pscl_id`). Sibling di buildPlanFromPreviousYear(): stessa
     * regola, `value` NON viene azzerato ma `year` viene sempre riportato a
     * `null` su ogni voce (l'utente deve ri-valorizzarlo esplicitamente).
     *
     * Come buildPlanFromPreviousYear(), legge solo il bucket `plan[1]`
     * (formato piatto corrente): un pscl ancora nel vecchio formato nidificato
     * per-pillar (bucket 2+) restituirebbe solo le voci del bucket 1 — stesso
     * limite noto già presente nel sibling, nessuna gestione retroattiva qui.
     *
     * @param int $pscl_id Id della riga pscl di riferimento.
     * @return array<int,array<string,mixed>> Vuoto se il pscl non esiste o non ha un piano piatto.
     */
    public function buildPlanFromPsclId(int $pscl_id): array
    {
        $reference = $this->find()->where(['id' => $pscl_id])->first();
        if ($reference === null || !is_array($reference->plan[1] ?? null)) {
            return [];
        }

        return array_values(array_map(function ($entry) {
            $entry['year'] = null;

            return $entry;
        }, $reference->plan[1]));
    }

    /**
     * Elenco dei PSCL "reali" (esclusi i template, version_tag='template') già
     * esistenti per un'azienda — opzionalmente filtrato per sede — usato dal
     * dialog "scegli da quale PSCL di riferimento partire" mostrato alla
     * creazione guidata di un nuovo piano (vedi PsclController::history()).
     * Ordinato per anno decrescente (i pscl "TUTTI", year IS NULL, in coda).
     *
     * Colonne qualificate con l'alias `Pscl.` (non bare): il chiamante
     * (PsclController::history()) fa il `contain(['Offices'])` per risolvere
     * il nome sede in un'unica query, e sia `pscl` che `offices` hanno una
     * colonna `company_id` — un `WHERE company_id = ...` non qualificato
     * diventa ambiguo non appena il join è presente (stessa classe di bug
     * della qualificazione `id` per le tabelle Translate-enabled, vedi
     * CLAUDE.md, ma qui per un JOIN reale, non per il behavior Translate).
     *
     * @param int|string $company_id Id azienda.
     * @param int|string|null $office_id Id sede, oppure null/'null' per includere tutte le sedi.
     * @return \Cake\ORM\Query
     */
    public function findPastForCompany($company_id, $office_id = null)
    {
        $query = $this->find()
            ->where(['Pscl.company_id' => $company_id])
            ->where(['OR' => ['Pscl.version_tag IS' => null, 'Pscl.version_tag !=' => 'template']])
            ->order(['Pscl.year' => 'DESC']);

        if ($office_id !== null && $office_id !== 'null') {
            $query->where(['Pscl.office_id' => $office_id]);
        }

        return $query;
    }

    /**
     * Trova il pscl "TUTTI" (tutti gli anni, `year IS NULL`) di un company/
     * office — la riga che sostituisce la vecchia colonna `offices.PSCL`
     * (vedi CLAUDE.md/dev-notes "office.PSCL -> pscl.plan migration"). A
     * differenza di getId(), non filtra su `survey_id` (office.PSCL non è mai
     * stato associato a un questionario).
     *
     * @param int|string|null $company_id Id azienda.
     * @param int|string|null $office_id Id sede, oppure 'null'/null per il piano di azienda.
     * @return \App\Model\Entity\Pscl|null
     */
    public function findAllYearsForOffice($company_id, $office_id)
    {
        $conditions = ['year IS' => null];
        if ($office_id !== null && $office_id !== 'null') {
            $conditions['office_id'] = $office_id;
        } else {
            $conditions['office_id IS'] = null;
        }
        if ($company_id !== null && $company_id !== 'null') {
            $conditions['company_id'] = $company_id;
        }

        return $this->find()->where($conditions)->first();
    }

    /**
     * Converte un piano legacy annidato per-pillar (`plan[legacyPillarId] =
     * [misure...]`) nel nuovo formato piatto (`plan[1]` = un unico array di
     * voci). Logica condivisa da MigratePsclPlanFormatCommand (righe
     * `pscl.plan` storiche) e MigrateOfficePsclToPlanCommand (colonna
     * `offices.PSCL` storica) — vedi CLAUDE.md/dev-notes "Flat PSCL plan
     * format" per il contratto completo.
     *
     * @param array<int|string,mixed> $legacyPlan Il vecchio plan annidato (incl. bucket 0 di servizio).
     * @param array<int,\App\Model\Entity\Measure> $axisByLegacyId Legacy pillar id => Measure asse (type=0).
     * @param array<int,\App\Model\Entity\Measure> $measuresById measure_id => Measure.
     * @param int|string|null $year Anno da assegnare ad ogni voce (una tantum, nessuna riconciliazione).
     * @param callable|null $onOrphanBucket Callback opzionale invocata con il legacy pillar id quando
     *   non si trova l'asse corrispondente (per logging/contatori lato chiamante).
     * @return array{0: array<string,mixed>, 1: array<int,array<string,mixed>>} `[meta, entries]`.
     */
    public static function flattenLegacyPlan(
        array $legacyPlan,
        array $axisByLegacyId,
        array $measuresById,
        $year,
        ?callable $onOrphanBucket = null
    ): array {
        $meta = is_array($legacyPlan[0] ?? null) ? $legacyPlan[0] : [];
        $seenAxisIds = [];
        $entries = [];

        foreach ($legacyPlan as $bucketKey => $bucketValue) {
            if ((int)$bucketKey === 0 || !is_array($bucketValue)) {
                continue;
            }

            $legacyPillarId = (int)$bucketKey;
            $axis = $axisByLegacyId[$legacyPillarId] ?? null;

            if ($axis === null) {
                if ($onOrphanBucket !== null) {
                    $onOrphanBucket($legacyPillarId);
                }
                $parentMeasureId = 0;
            } else {
                $parentMeasureId = $axis->id;
                if (!isset($seenAxisIds[$axis->id])) {
                    $entries[] = [
                        'value' => 0,
                        'pillar_id' => 0,
                        'measure_id' => $axis->id,
                        'measure_slug' => $axis->slug,
                        'weight' => $axis->weight,
                        'year' => $year,
                    ];
                    $seenAxisIds[$axis->id] = true;
                }
            }

            foreach ($bucketValue as $measureEntry) {
                if (!is_array($measureEntry) || !isset($measureEntry['measure_id'])) {
                    continue;
                }
                $measureId = $measureEntry['measure_id'];
                $measure = $measuresById[$measureId] ?? null;

                $entries[] = [
                    'value' => $measureEntry['value'] ?? 0,
                    'pillar_id' => $parentMeasureId,
                    'measure_id' => $measureId,
                    'measure_slug' => $measure->slug ?? ($measureEntry['measure_slug'] ?? null),
                    'weight' => $measure->weight ?? ($measureEntry['weight'] ?? 999999),
                    'year' => $year,
                ];
            }
        }

        usort($entries, function ($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return [$meta, $entries];
    }

    /**
     * Rileva se un piano è già nel formato piatto (`plan[1]` = un unico array
     * di voci) anche se privo del marker `plan[0]['plan_format']` — il caso
     * di OGNI pscl creato/modificato tramite il normale flusso create/edit,
     * dato che quel marker viene scritto SOLO dai comandi di migrazione (vedi
     * `Pscl::cleanPlan()`). Senza questo controllo un piano piatto non
     * marcato è strutturalmente indistinguibile, per
     * `MigratePsclPlanFormatCommand`, da un piano legacy annidato con un solo
     * bucket compilato (`{0:{...},1:[...]}`) e verrebbe erroneamente
     * interpretato da `flattenLegacyPlan()` come "bucket storico pillar id
     * 1", sovrascrivendo il `pillar_id` reale di OGNI voce con quello del
     * solo asse 1 — vedi il caso reale `pscl` id=9 in
     * `docs/dev-notes/survey-measure-reorg.md`.
     *
     * Discriminante strutturale (nessun marker richiesto), ENTRAMBE le
     * condizioni devono valere:
     *  1. il piano non ha bucket oltre lo 0 e l'1 — un piano legacy con più
     *     di un asse compilato ha SEMPRE bucket 2+, un piano piatto non ne ha
     *     mai (stesso segnale già usato da `Pscl::cleanPlan()`);
     *  2. almeno una voce di `plan[1]` ha già `pillar_id === 0` (marcatore di
     *     voce-asse: solo il formato piatto produce voci-asse dentro l'unico
     *     array; il formato legacy annidato non ne emette mai, l'asse è
     *     implicito nella chiave del bucket, mai un valore di `pillar_id`).
     * Un piano legacy con un solo asse compilato soddisfa la (1) ma mai la
     * (2): le sue voci hanno `pillar_id` uguale al piccolo id storico del
     * bucket (1-10), mai 0.
     *
     * @param array<int|string,mixed> $plan Il piano da ispezionare (bucket 0 incluso).
     */
    public static function isAlreadyFlatPlan(array $plan): bool
    {
        foreach (array_keys($plan) as $bucketKey) {
            if ((int)$bucketKey > 1) {
                return false;
            }
        }

        $entries = is_array($plan[1] ?? null) ? $plan[1] : [];
        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['pillar_id'] ?? null) === 0) {
                return true;
            }
        }

        return false;
    }

    public function getId($company_id, $office_id, $survey_id, $year)
    {
        if ($office_id == 'null') {
            $pscl = $this->find()
            ->where(['company_id' => $company_id])
            ->andWhere(['office_id IS' => null])
            ->andWhere(['survey_id' => $survey_id]);
        } else {
            $pscl = $this->find()
                ->where(['company_id' => $company_id])
                ->andWhere(['office_id' => $office_id])
                ->andWhere(['survey_id' => $survey_id]);
        }

        if ($year != 'TUTTI' && $year != null) {
            $pscl = $pscl->andWhere(['year' => $year]);
        }
        $pscl = $pscl->first();

        if ($pscl) {
            return $pscl->id;
        } else {
            return null;
        }
    }
}

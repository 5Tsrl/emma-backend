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

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * Measures Controller
 *
 * Pillars are no longer a separate model: an axis/pillar is a Measure with
 * type=0, ordered (with its child measures) by the `weight` column. The saved
 * PSCL plan JSON still keeps a `pillar_id` key, derived at runtime from the
 * owning axis-measure (slug = 'pillar-'+id). See CLAUDE.md.
 *
 * @property \App\Model\Table\MeasuresTable $Measures
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class MeasuresController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Index method
     *
     * Returns the flat measure list ordered by `weight` (type-inclusive: 0/1/2).
     * A type=0 row is an axis header; following type 1/2 rows attach until the
     * next type=0. Accepts an optional `?year=`: historicization is OPT-IN per
     * brand (Configure PsclCutoverYear — 0/unset disables it entirely; only 5t
     * sets 2027 in its site .env). For an opted-in brand, years >= the cutover
     * (or no year) use the new default structure; earlier years with a pinned
     * snapshot under {sitedir}/{year}/ serve the historical (old-pillar)
     * structure. Brands that never opt in always serve the default structure.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $year = $this->request->getQuery('year');

        // For an opted-in brand, past years (< the per-brand cutover) with a pinned
        // snapshot expose the historical measures.php structure so old PSCL plans
        // still render. For the DB-backed listing we always return the current flat
        // list ordered by weight; the year hint is forwarded so the Measure entity
        // loads the right PSCL config (a no-op for brands that never opt in).
        if ($year !== null && $year !== '' && $year !== 'TUTTI') {
            Configure::write('PsclMeasuresYear', $year);
        }

        if (!$this->request->is('json')) {
            $measures = $this->paginate($this->Measures->find('ordered'));
        } else {
            $measures = $this->Measures->find('ordered');
        }
        $this->set(compact('measures'));
        $this->viewBuilder()->setOption('serialize', ['measures']);
    }

    /**
     * View method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $measure = $this->Measures->get($id);

        // "5 assi invece di 10 pilastri": pillar_id non esiste più come colonna.
        // Derivo pillar_id + un oggetto pillar {id,name} dall'asse (type=0) che
        // precede questa misura per weight, così i consumatori legacy che leggono
        // measure.pillar_id / measure.pillar.name (es. Action.vue) continuano a
        // funzionare e il piano salvato resta nella forma pscl[pillar_id][measure_id].
        $axis = $this->Measures->find()
            ->where([
                'type' => 0,
                'weight <=' => $measure->weight ?? 0,
            ])
            ->orderDesc('weight')
            ->orderDesc('Measures.id')
            ->first();
        if ($axis !== null) {
            $pillarId = (int)str_replace('pillar-', '', (string)$axis->slug);
            $measure->pillar_id = $pillarId;
            $measure->pillar = ['id' => $pillarId, 'name' => $axis->name];
        } else {
            $measure->pillar_id = null;
            $measure->pillar = null;
        }

        $this->set(compact('measure'));
        $this->viewBuilder()->setOption('serialize', ['measure']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->allowRolesOnly(['admin']);

        $isJson = $this->request->is('json') || $this->request->getParam('_ext') === 'json';

        $measure = $this->Measures->newEmptyEntity();
        if ($this->request->is('post')) {
            if ($isJson) {
                // JSON create: only touch the known scalar fields (works for a
                // type=0 axis too). `img` is never mass-assigned from client data -
                // it is only ever set below from resolveUploadedImage(), a
                // server-derived value.
                $editableFields = [
                    'name', 'description', 'type', 'slug', 'weight',
                    'target', 'service_url', 'indicator', 'labels',
                ];
                $measure = $this->Measures->patchEntity(
                    $measure,
                    $this->request->getData(),
                    ['fields' => $editableFields]
                );

                try {
                    $imgPath = $this->resolveUploadedImage();
                    if ($imgPath !== null) {
                        $measure->img = $imgPath;
                    }
                } catch (\Exception $e) {
                    $this->set('errorMsg', $e->getMessage());
                    $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                    return;
                }

                if ($this->Measures->save($measure)) {
                    $this->set('errorMsg', false);
                    $this->set('measure', $measure);
                    $this->viewBuilder()->setOption('serialize', ['errorMsg', 'measure']);

                    return;
                }

                $errors = $measure->getErrors();
                $errorMsg = empty($errors)
                    ? __('The measure could not be saved. Please, try again.')
                    : json_encode($errors, JSON_UNESCAPED_UNICODE);
                $this->set('errorMsg', $errorMsg);
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }

            $data = $this->request->getData();
            unset($data['img']);
            $measure = $this->Measures->patchEntity($measure, $data);

            try {
                $imgPath = $this->resolveUploadedImage();
                if ($imgPath !== null) {
                    $measure->img = $imgPath;
                }
            } catch (\Exception $e) {
                $this->Flash->error($e->getMessage());
                $this->set(compact('measure'));

                return;
            }

            if ($this->Measures->save($measure)) {
                $this->Flash->success(__('The measure has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The measure could not be saved. Please, try again.'));
        }
        $this->set(compact('measure'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin']);

        $isJson = $this->request->is('json') || $this->request->getParam('_ext') === 'json';

        $measure = $this->Measures->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            // `img` must never come from client-supplied string data - it is only
            // ever set below from resolveUploadedImage(), and only when a file was
            // actually uploaded (no upload = keep the existing image untouched).
            unset($dt['img']);

            if ($isJson) {
                // JSON rename/patch: only touch the known scalar fields (works for
                // a type=0 axis too).
                $editableFields = [
                    'name', 'description', 'weight', 'slug',
                    'target', 'service_url', 'indicator', 'labels',
                ];
                $measure = $this->Measures->patchEntity($measure, $dt, ['fields' => $editableFields]);

                try {
                    $imgPath = $this->resolveUploadedImage();
                    if ($imgPath !== null) {
                        $measure->img = $imgPath;
                    }
                } catch (\Exception $e) {
                    $this->set('errorMsg', $e->getMessage());
                    $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                    return;
                }

                if ($this->Measures->save($measure)) {
                    $this->set('errorMsg', false);
                    $this->set('measure', $measure);
                    $this->viewBuilder()->setOption('serialize', ['errorMsg', 'measure']);

                    return;
                }

                $errors = $measure->getErrors();
                $errorMsg = empty($errors)
                    ? __('The measure could not be saved. Please, try again.')
                    : json_encode($errors, JSON_UNESCAPED_UNICODE);
                $this->set('errorMsg', $errorMsg);
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }

            $measure = $this->Measures->patchEntity($measure, $dt);

            try {
                $imgPath = $this->resolveUploadedImage();
                if ($imgPath !== null) {
                    $measure->img = $imgPath;
                }
            } catch (\Exception $e) {
                $this->Flash->error($e->getMessage());
                $this->set(compact('measure'));

                return;
            }

            if ($this->Measures->save($measure)) {
                $this->Flash->success(__('The measure has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The measure could not be saved. Please, try again.'));
        }
        $this->set(compact('measure'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post', 'delete']);

        $isJson = $this->request->is('json') || $this->request->getParam('_ext') === 'json';

        $measure = $this->Measures->get($id);

        // A type=0 measure is an axis header. It may only be deleted when its band
        // (weight > thisAxis.weight AND weight < nextAxisWeight) contains no child
        // measures — otherwise the children would be orphaned into the previous axis.
        if ((int)$measure->type === 0) {
            $thisWeight = (int)($measure->weight ?? 0);

            $nextAxis = $this->Measures->find()
                ->where(['type' => 0, 'weight >' => $thisWeight])
                ->orderAsc('weight')
                ->orderAsc('Measures.id')
                ->first();
            $nextAxisWeight = $nextAxis !== null ? (int)($nextAxis->weight ?? 0) : PHP_INT_MAX;

            $childrenConditions = ['weight >' => $thisWeight];
            if ($nextAxisWeight !== PHP_INT_MAX) {
                $childrenConditions['weight <'] = $nextAxisWeight;
            }
            $childCount = $this->Measures->find()->where($childrenConditions)->count();

            if ($childCount > 0) {
                $errorMsg = __('Impossibile eliminare l\'asse: rimuovi prima le misure dell\'asse.');
                if ($isJson) {
                    $this->set('errorMsg', $errorMsg);
                    $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                    return;
                }
                $this->Flash->error($errorMsg);

                return $this->redirect(['action' => 'index']);
            }
        }

        if ($this->Measures->delete($measure)) {
            if ($isJson) {
                $this->set('errorMsg', false);
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }
            $this->Flash->success(__('The measure has been deleted.'));
        } else {
            if ($isJson) {
                $this->set('errorMsg', __('The measure could not be deleted. Please, try again.'));
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }
            $this->Flash->error(__('The measure could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Reorder method - flat re-weight of the whole measure list.
     *
     * Admin-only. Body: `{"order":[<measureId>, ...]}` = the full flat order (each
     * type=0 axis header followed by its child measures). Transactionally sets
     * `measures.weight = index*10` for each id in `order`. Ids not found are ignored.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function reorder()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = false;
        try {
            $order = (array)$this->request->getData('order');

            $this->Measures->getConnection()->transactional(function () use ($order) {
                foreach (array_values($order) as $index => $measureId) {
                    $measure = $this->Measures->find()->where(['Measures.id' => $measureId])->first();
                    if ($measure === null) {
                        continue;
                    }
                    $measure->weight = $index * 10;
                    if (!$this->Measures->save($measure)) {
                        throw new \Exception('Impossibile aggiornare il peso della misura ' . $measureId);
                    }
                }
            });
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            \Cake\Log\Log::error('Error in Measures::reorder: ' . $e->getMessage());
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    public function getPsclMeasureLabels($id)
    {
        // Year-aware: for an opted-in brand, a requested ?year= earlier than the
        // per-brand cutover pins the historical labels via the Measure entity's
        // measures.php resolution (no-op for brands that never opt in).
        $year = $this->request->getQuery('year');
        if ($year !== null && $year !== '' && $year !== 'TUTTI') {
            Configure::write('PsclMeasuresYear', $year);
        }
        $labels = $this->Measures->get($id)->getPsclLabels($id);
        $this->set(compact('labels'));
        $this->viewBuilder()->setOption('serialize', ['labels']);
    }

    public function getPsclMeasureImpacts($company_id, $office_id, $survey_id, $year)
    {
        $impacts = [];

        // Year-aware PSCL config: for an opted-in brand, past years (< the per-brand
        // cutover) load the pinned measures.php snapshot when available (no-op for
        // brands that never opt in).
        if ($year !== null && $year !== '' && $year !== 'TUTTI') {
            Configure::write('PsclMeasuresYear', $year);
        }

        $this->Monitorings = TableRegistry::getTableLocator()->get('Monitorings');

        // Initialized unconditionally: for $year != 'TUTTI', ->matching('Pscl', ...)
        // below is an inner join keyed on monitorings.pscl_id, which stays NULL for
        // any monitoring row recorded before pscl_id was populated on save. When
        // that join matches nothing, $monitorings stays empty and the branch at
        // line ~451 never assigns $pscl — leaving isset($pscl[...]) below to
        // silently evaluate false on an undefined variable instead of on a
        // deterministic "no PSCL plan found" state.
        $pscl = [];

        $monitorings_query = $this->Monitorings->find();
        // ->where(['Monitorings.office_id' => $office_id]);
        if ($year != 'TUTTI') {
            // $monitorings_query->where(['year' => $year]);
            $monitorings_query->contain(['Pscl'])->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                if ($office_id == 'null') {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                } else {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                }
            });
            // $pscl = $this->reducePSCLToActive($monitorings_query->pscl->toArray());
        } else {
            // where survey_id

            $monitorings_query->where(['Monitorings.office_id' => $office_id, 'pscl_id IS NULL']);
            if ($office_id != null) {
                // year=TUTTI: legacy offices.PSCL is gone, replaced by the
                // "all years" pscl row (office_id, year IS NULL) - see
                // PsclTable::findAllYearsForOffice() / CLAUDE.md dev-notes.
                $Pscl = TableRegistry::getTableLocator()->get('Pscl');
                $tuttiPscl = $Pscl->findAllYearsForOffice($company_id, $office_id);
                $pscl = $tuttiPscl !== null ? $this->reducePSCLToActive($tuttiPscl->plan) : [];
            }
        }

        $monitorings = $monitorings_query->toArray();
        $all_series = $monitorings;
        if ($year != 'TUTTI' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        }

        // Iterate measures directly (no Pillars model). type=0 rows are axis
        // headers; for each measure of type 1/2 we keep emitting a pillar_id
        // derived from the nearest preceding type=0 axis (slug='pillar-'+id) so
        // the existing PSCL plan JSON (pscl[pillar_id][measure_id]) keeps working.
        $measures = $this->Measures->find('ordered')->toArray();

        foreach ($measures as $measure) {
            if ((int)$measure->type === 0) {
                // Axis header: nothing to monitor, just used to derive pillar_id.
                continue;
            }
            $measure_monitorings = array_filter($all_series, function ($series) use ($measure) {
                return $series->measure_id === $measure->id;
            });
            if (isset($pscl[$measure->id])) {
                $tmp = [];
                foreach ($measure_monitorings as $measure_monitoring) {
                    $values = $measure_monitoring->jvalues;
                    $emissions = $this->Measures->get($measure->id)->calculateImpactPscl($measure->id, $values);

                    if ($emissions == null) {
                        break;
                    }
                    array_unshift($emissions, $measure_monitoring->name);

                    $emissions['name'] = $emissions[0];
                    unset($emissions[0]);

                    array_push($tmp, $emissions);
                }
                if (!empty($tmp)) {
                    $impacts[$measure->id] = $tmp;
                }
            }
        }

        // Lets a frontend caller distinguish "no monitoring recorded yet for
        // this office/year" (hasMonitorings=false) from a genuinely empty
        // impacts result for a measure that does have monitoring data.
        $hasMonitorings = !empty($monitorings);

        $this->set(compact('impacts', 'hasMonitorings'));
        $this->viewBuilder()->setOption('serialize', ['impacts', 'hasMonitorings']);
    }

    /**
     * exportStructure - exports the pillars+measures structure as JSON.
     *
     * Walks the flat measure list ordered by weight: a type=0 row opens an axis,
     * following type 1/2 rows attach to it until the next type=0. Modeled on the
     * survey exportSchema/importSchema. Does NOT export measures.php indicator
     * closures (brand-local PHP on disk) — only DB columns.
     *
     * @return \Cake\Http\Response
     */
    public function exportStructure()
    {
        $this->allowRolesOnly(['admin']);

        $measures = $this->Measures->find('ordered')->toArray();

        $axes = [];
        $current = null;
        foreach ($measures as $m) {
            if ((int)$m->type === 0) {
                if ($current !== null) {
                    $axes[] = $current;
                }
                $current = [
                    'slug' => $m->slug,
                    'name' => $m->name,
                    'description' => $m->description,
                    'weight' => $m->weight,
                    'measures' => [],
                ];
                continue;
            }

            $measureData = [
                'slug' => $m->slug,
                'name' => $m->name,
                'description' => $m->description,
                'img' => $m->img,
                'target' => $m->target,
                'type' => $m->type,
                'service_url' => $m->service_url,
                'weight' => $m->weight,
                'indicator' => $m->indicator,
                'labels' => $m->labels,
            ];

            if ($current === null) {
                // Measure before any axis: place it in a synthetic "no-axis" bucket
                // so it is not silently dropped.
                $current = [
                    'slug' => null,
                    'name' => null,
                    'description' => null,
                    'weight' => $m->weight,
                    'measures' => [],
                ];
            }
            $current['measures'][] = $measureData;
        }
        if ($current !== null) {
            $axes[] = $current;
        }

        $export = [
            'version' => 1,
            'exported_at' => date('c'),
            'source_brand' => Configure::read('sitedir'),
            'axes' => $axes,
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->response = $this->response
            ->withType('application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="pscl-structure.json"')
            ->withStringBody((string)$json);

        return $this->response;
    }

    /**
     * importStructure - imports a pillars+measures structure JSON.
     *
     * Transactional; upserts by `slug` (preserves existing measure ids so PSCL
     * history survives). Sets `weight` from payload. Reports created/updated/
     * skipped. Does NOT touch measures.php indicator closures.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function importStructure()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        try {
            $schema = $this->readImportPayload();

            if (empty($schema['axes']) || !is_array($schema['axes'])) {
                throw new \Exception('Struttura JSON non valida: manca il campo axes');
            }

            $connection = $this->Measures->getConnection();
            $connection->transactional(function () use ($schema, &$created, &$updated, &$skipped) {
                foreach ($schema['axes'] as $axis) {
                    // An axis is itself a type=0 measure (slug='pillar-N').
                    if (!empty($axis['slug'])) {
                        $this->upsertMeasureBySlug([
                            'slug' => $axis['slug'],
                            'name' => $axis['name'] ?? null,
                            'description' => $axis['description'] ?? null,
                            'type' => 0,
                            'weight' => $axis['weight'] ?? null,
                            'service_url' => '',
                        ], $created, $updated, $skipped);
                    }

                    foreach (($axis['measures'] ?? []) as $measureData) {
                        if (empty($measureData['slug'])) {
                            $skipped++;
                            continue;
                        }
                        $this->upsertMeasureBySlug([
                            'slug' => $measureData['slug'],
                            'name' => $measureData['name'] ?? null,
                            'description' => $measureData['description'] ?? null,
                            'img' => $measureData['img'] ?? null,
                            'target' => $measureData['target'] ?? null,
                            'type' => $measureData['type'] ?? 1,
                            'service_url' => $measureData['service_url'] ?? '',
                            'weight' => $measureData['weight'] ?? null,
                            'indicator' => $measureData['indicator'] ?? null,
                            'labels' => $measureData['labels'] ?? null,
                        ], $created, $updated, $skipped);
                    }
                }
            });
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            \Cake\Log\Log::error('Error in importStructure: ' . $e->getMessage());
        }

        $this->set('errorMsg', $errorMsg);
        $this->set('created', $created);
        $this->set('updated', $updated);
        $this->set('skipped', $skipped);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'created', 'updated', 'skipped']);
    }

    /**
     * Read the import payload from an uploaded file or the raw JSON body.
     *
     * @return array<string,mixed>
     * @throws \Exception When the payload cannot be parsed.
     */
    private function readImportPayload(): array
    {
        $file = $this->request->getUploadedFile('structure');
        if (!empty($file) && $file->getError() === UPLOAD_ERR_OK) {
            $json = $file->getStream()->getContents();
        } else {
            $json = (string)$this->request->getBody();
        }

        if ($json === '') {
            throw new \Exception('Nessun file caricato o corpo della richiesta vuoto');
        }

        $schema = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('File JSON non valido: ' . json_last_error_msg());
        }

        return $schema;
    }

    /**
     * Resolves an uploaded `img` file (PSR-7 API, same convention as
     * OfficesController::import()) into the relative path to persist on
     * `measures.img` (e.g. "measures/<safe-filename>"), moving the file into
     * webroot/img/measures/.
     *
     * Returns null when no file was supplied - a normal, non-error case for
     * both add() (no image yet) and edit() (keep the existing image untouched).
     * Validates the upload is really an image via both MIME sniffing (finfo)
     * and the client-supplied extension, mirroring the belt-and-suspenders PDF
     * check in UsersController::uploadGuide().
     *
     * @return string|null Relative path (e.g. "measures/<safe-filename>"), or null when no file was uploaded.
     * @throws \Exception When a file was supplied but is not a valid image.
     */
    private function resolveUploadedImage(): ?string
    {
        $file = $this->request->getUploadedFile('img');
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $tmpPath = $file->getStream()->getMetadata('uri');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $extension = strtolower((string)pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));

        if (
            !in_array($detectedMimeType, $allowedMimeTypes, true)
            || !in_array($extension, $allowedExtensions, true)
        ) {
            throw new \Exception(__('Il file caricato non è un\'immagine valida.'));
        }

        $safeFilename = uniqid() . '_' . preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            (string)$file->getClientFilename()
        );
        $file->moveTo(WWW_ROOT . 'img' . DIRECTORY_SEPARATOR . 'measures' . DIRECTORY_SEPARATOR . $safeFilename);

        return "measures/$safeFilename";
    }

    /**
     * Upsert a measure by slug, preserving the existing id when found.
     *
     * @param array<string,mixed> $data Measure data (includes slug).
     * @param int $created Reference to the created counter.
     * @param int $updated Reference to the updated counter.
     * @param int $skipped Reference to the skipped counter.
     * @return void
     */
    private function upsertMeasureBySlug(array $data, int &$created, int &$updated, int &$skipped): void
    {
        $existing = $this->Measures->find()->where(['slug' => $data['slug']])->first();
        if ($existing) {
            $existing = $this->Measures->patchEntity($existing, $data);
            if ($this->Measures->save($existing)) {
                $updated++;
            } else {
                $skipped++;
            }

            return;
        }

        $measure = $this->Measures->newEntity($data);
        if ($this->Measures->save($measure)) {
            $created++;
        } else {
            $skipped++;
        }
    }

    private function reducePSCLToActive($pscl)
    {
        $result = [];
        if (empty($pscl)) {
            return $result;
        }
        array_shift($pscl);
        foreach ($pscl as $ms) {
            foreach ($ms as $m) {
                if (isset($m['pillar_id']) && isset($m['measure_id'])) {
                    if ($m['value']) {
                        $result[$m['measure_id']] = $m;
                    }
                }
            }
        }

        return $result;
    }
}

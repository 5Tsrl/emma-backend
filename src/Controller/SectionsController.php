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

use Cake\I18n\I18n;

/**
 * Sections Controller
 *
 * "5 assi invece di 10 pilastri": the `sections` table and `Sections` model
 * are retired. Only `index()` survives — it is the sole action the frontend
 * still calls (the survey builder's section catalog, GET /sections.json).
 * The old view/add/edit/delete scaffold actions were deleted (Phase 14): they
 * referenced the default `$this->Sections` model, which no longer exists and
 * would fatal if hit; nothing in the frontend ever called them.
 */
class SectionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['index']);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'index',
                ]
            );
        }
    }

    /**
     * Index method
     *
     * "5 assi invece di 10 pilastri": sections are now Questions of type='section'.
     * This returns the full catalog of section-questions (id + title) so the
     * survey builder can list ALL available sections (even ones not yet used in a
     * given survey) and let the admin organise questions into any of them.
     * `description` carries the section title and is kept CANONICAL (Italian)
     * regardless of the request's Accept-Language locale (Part 11): this is the
     * builder's global admin catalog, and the inline section-rename affordance
     * (PromptModal → editQuestionFromSurvey) patches this same `description`
     * globally — if the find ran under a non-default locale (e.g. fr), the
     * Translate behavior would both localise the read AND risk a later fr rename
     * overwriting the canonical Italian title. FR display of section headers is
     * a frontend concern, resolved from the survey's in-context header-question
     * `_translations` (a separate, already locale-safe read path). Ordered by id
     * (which, by migration insertion order, follows the original section weight
     * order).
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $sections = [];
        try {
            $Questions = $this->fetchTable('Questions');
            $Questions->setLocale(I18n::getDefaultLocale());
            $rows = $Questions->find()
                ->where(['Questions.type' => 'section'])
                ->orderAsc('Questions.id')
                ->all();
            foreach ($rows as $r) {
                $sections[] = [
                    'id' => $r->id,
                    'name' => $r->description,
                    'description' => $r->description,
                ];
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('SectionsController::index: ' . $e->getMessage());
            $sections = [];
        }

        $this->set(compact('sections'));
        $this->viewBuilder()->setOption('serialize', ['sections']);
    }
}

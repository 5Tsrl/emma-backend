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

use Migrations\AbstractSeed;

/**
 * SectionsI18n seed.
 *
 * Semina le traduzioni francesi (fr) e inglesi (en) del campo `description`
 * delle sezioni dei questionari nella tabella EAV `i18n` usata dal Translate
 * behavior (vedi `QuestionsTable::initialize` ->
 * `addBehavior('Translate', ['fields' => ['description', 'long_description', 'options']])`).
 *
 * IMPORTANTE - modello e campo:
 * Le sezioni NON sono un'entità a sé stante: sono righe di `questions` con
 * `type = 'section'`. Il `model` su `i18n` è quindi 'Questions' (referenceName
 * del behavior, cioè il nome della classe Table senza il suffisso 'Table'),
 * NON 'Sections'. Si traduce solo `description` (il titolo leggibile
 * dall'utente); il campo `name` (es. 'sec-1', 'sec-2'...) è il codice
 * macchina della sezione e non va tradotto.
 *
 * Gli id (foreign_key) sono quelli delle 11 sezioni sul DB `demo_emma_rer`
 * (brand RER): 713-723. Non sono portabili su altri DB (a differenza di
 * CompanyTypesI18nSeed, i cui id rispecchiano un seed condiviso) perché le
 * sezioni sono create per-survey/per-brand e i loro id variano da DB a DB.
 *
 * Idempotente: rimuove le righe fr/en esistenti PRIMA di reinserirle, così
 * può essere rieseguito senza creare duplicati.
 *
 * ATTENZIONE - scoping della DELETE (a differenza di CompanyTypesI18nSeed):
 * la tabella `questions`/`i18n` contiene già ~290 traduzioni reali per
 * altre domande (model='Questions', field='description') per ciascuna
 * lingua. La DELETE qui sotto è perciò filtrata esplicitamente su
 * `foreign_key IN (713,714,715,716,717,718,719,720,721,722,723)`: una
 * DELETE non scopata su model/field, come quella di CompanyTypesI18nSeed
 * (sicura lì solo perché CompanyTypes è un modello piccolo e interamente
 * di proprietà di questo seed), cancellerebbe anche le traduzioni di tutte
 * le altre domande già seminate in precedenza. Non rimuovere questo filtro.
 *
 * Esecuzione: bin/cake migrations seed --seed SectionsI18nSeed
 */
class SectionsI18nSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * @return void
     */
    public function run()
    {
        // foreign_key => [fr, en] (gli id rispecchiano le sezioni su demo_emma_rer):
        // 713/722 Anagrafica, 714 Il tuo lavoro,
        // 715 Il tuo spostamento abituale casa-lavoro,
        // 716 Spostamenti in inverno ed estate, 717 Propensione al cambiamento,
        // 718 Iniziative e proposte, 719 Spostamenti di servizio,
        // 720 Propensione al cambiamento2, 721 Dati Personali,
        // 723 Osservazioni e Suggerimenti.
        $translations = [
            713 => ['fr' => 'Informations personnelles', 'en' => 'Personal details'],
            714 => ['fr' => 'Votre travail', 'en' => 'Your job'],
            715 => [
                'fr' => 'Votre déplacement habituel domicile-travail',
                'en' => 'Your usual home-to-work commute',
            ],
            716 => ['fr' => 'Déplacements en hiver et en été', 'en' => 'Trips in winter and summer'],
            717 => ['fr' => 'Propension au changement', 'en' => 'Willingness to change'],
            718 => ['fr' => 'Initiatives et propositions', 'en' => 'Initiatives and proposals'],
            719 => ['fr' => 'Déplacements de service', 'en' => 'Business trips'],
            720 => ['fr' => 'Propension au changement 2', 'en' => 'Willingness to change 2'],
            721 => ['fr' => 'Données personnelles', 'en' => 'Personal data'],
            722 => ['fr' => 'Informations personnelles', 'en' => 'Personal details'],
            723 => [
                'fr' => 'Observations et suggestions',
                'en' => 'Observations and suggestions',
            ],
        ];

        $ids = implode(', ', array_keys($translations));

        $rows = [];
        foreach ($translations as $foreignKey => $locales) {
            foreach ($locales as $locale => $content) {
                $rows[] = [
                    'locale' => $locale,
                    'model' => 'Questions',
                    'foreign_key' => $foreignKey,
                    'field' => 'description',
                    'content' => $content,
                ];
            }
        }

        // Pulisco le righe fr/en esistenti SOLO per queste 11 sezioni
        // (idempotenza mirata: vedi ATTENZIONE sopra sul perché la DELETE
        // deve restare scopata su questi foreign_key e non su model/field).
        foreach (['fr', 'en'] as $locale) {
            $this->execute(
                "DELETE FROM i18n WHERE model = 'Questions' AND field = 'description' "
                . "AND locale = '$locale' AND foreign_key IN ($ids)"
            );
        }

        $this->table('i18n')->insert($rows)->save();
    }
}

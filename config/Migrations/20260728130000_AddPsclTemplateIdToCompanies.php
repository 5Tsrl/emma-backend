<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge `companies.pscl_template_id` (nullable, indicizzato).
 *
 * Punta a una riga `pscl` con `version_tag='template'` (vedi
 * PsclTable::findTemplates()/buildPlanFromTemplate()): è il template di piano
 * PSCL usato AUTOMATICAMENTE quando la pagina Interventi crea un nuovo PSCL
 * per questa azienda senza che l'utente ne scelga uno esplicitamente (vedi
 * PsclController::edit(), branch di fallback aggiunto insieme a questa
 * colonna). Modificabile solo da admin (vedi CompaniesController::edit()) —
 * gli altri ruoli vedono il campo disabled lato frontend. Il valore di
 * default assegnato alla creazione di una nuova azienda viene letto da
 * `Configure::read('Pscl.defaultTemplateId')` (config/app.php).
 *
 * Additiva e retrocompatibile: le aziende esistenti restano con
 * pscl_template_id NULL, nessun comportamento cambia finché non viene
 * valorizzato esplicitamente.
 */
class AddPsclTemplateIdToCompanies extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('companies')
            ->addColumn('pscl_template_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => true,
            ])
            ->addIndex(['pscl_template_id'])
            ->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('companies')
            ->removeIndexByName('pscl_template_id')
            ->removeColumn('pscl_template_id')
            ->update();
    }
}

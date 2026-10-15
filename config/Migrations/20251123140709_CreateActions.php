<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateActions extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('actions');
        $table->addColumn('pscl_id', 'integer', ['null' => false]);
        $table->addColumn('measure_id', 'integer', ['null' => false]);
        $table->addColumn('title', 'string');
        $table->addColumn('description', 'text');
        $table->addColumn('detailed_description', 'text');
        $table->addColumn('level', 'integer', ['null' => true]);
        $table->addColumn('priority', 'integer', ['null' => true]);
        $table->addColumn('benefits_employees_time', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_costs', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_safety', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_comfort', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_social', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_other', 'boolean', ['default' => false]);
        $table->addColumn('benefits_employees_other_text', 'string', ['null' => true]);
        $table->addColumn('benefits_company_regolarita', 'boolean', ['default' => false]);
        $table->addColumn('benefits_company_dedizione', 'boolean', ['default' => false]);
        $table->addColumn('benefits_company_image', 'boolean', ['default' => false]);
        $table->addColumn('benefits_company_other', 'boolean', ['default' => false]);
        $table->addColumn('benefits_company_other_text', 'string', ['null' => true]);
        $table->addColumn('benefits_community_emissions', 'boolean', ['default' => false]);
        $table->addColumn('benefits_community_congestion', 'boolean', ['default' => false]);
        $table->addColumn('benefits_community_safety', 'boolean', ['default' => false]);
        $table->addColumn('benefits_community_other', 'boolean', ['default' => false]);
        $table->addColumn('benefits_community_other_text', 'string', ['null' => true]);
        $table->addColumn('usage_methods', 'text');
        $table->addColumn('target_audience', 'text');
        $table->addColumn('timeline', 'text');
        $table->addColumn('economic_resources', 'text');
        $table->addColumn('human_resources', 'text');
        $table->addColumn('instrumental_resources', 'text');
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->create();
    }
}

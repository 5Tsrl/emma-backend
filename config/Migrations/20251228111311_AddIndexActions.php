<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddIndexActions extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('actions')) {
            return;
        }

        // Keep the lowest id for each measure/pscl pair before enforcing uniqueness.
        $this->execute(
            'DELETE a1
             FROM actions a1
             INNER JOIN actions a2
               ON a1.measure_id = a2.measure_id
              AND a1.pscl_id = a2.pscl_id
              AND a1.id > a2.id'
        );

        $table = $this->table('actions');
        if (!$table->hasIndex(['measure_id', 'pscl_id'])) {
            $table->addIndex(['measure_id', 'pscl_id'], ['unique' => true, 'name' => 'idx_measure_pscl']);
            $table->update();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('actions')) {
            return;
        }

        $table = $this->table('actions');
        if ($table->hasIndex(['measure_id', 'pscl_id'])) {
            $table->removeIndex(['measure_id', 'pscl_id']);
            $table->update();
        }
    }
}

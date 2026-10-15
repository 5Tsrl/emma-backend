<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddIndexMonitoring extends AbstractMigration
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
            $table = $this->table('monitorings');
                $table->addIndex(['measure_id', 'pscl_id'], ['unique' => false, 'name' => 'idx_measure_pscl_monitoring']);
                $table->update();
    }
}

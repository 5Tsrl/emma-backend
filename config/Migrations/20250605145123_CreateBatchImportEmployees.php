<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateBatchImportEmployees extends AbstractMigration
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
        // Create batches table
        $batches = $this->table('batches', ['id' => false, 'primary_key' => ['id']]);
        $batches->addColumn('id', 'uuid', [
            'default' => null,
            'null' => false,
        ]);
        $batches->addColumn('username', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $batches->addColumn('user_id', 'char', [
            'default' => null,
            'null' => false,
            'limit' => 36,
        ]);
        $batches->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $batches->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $batches->addIndex(['user_id']);
        $batches->create();

        // Create batches_users table (junction table)
        $batchesUsers = $this->table('batches_users');
        $batchesUsers->addColumn('user_id', 'char', [
            'default' => null,
            'null' => false,
            'limit' => 36,
        ]);
        $batchesUsers->addColumn('batch_id', 'uuid', [
            'default' => null,
            'null' => false,
        ]);
        $batchesUsers->addIndex(['user_id']);
        $batchesUsers->addIndex(['batch_id']);
        $batchesUsers->addIndex(['user_id', 'batch_id'], ['unique' => true]);
        $batchesUsers->create();
    }
}

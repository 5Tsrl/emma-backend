<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddMomaAreaToQuestions extends AbstractMigration
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
        $table = $this->table('questions');
        if (!$table->hasColumn('moma_area')) {
            $table->addColumn('moma_area', 'boolean', [
                'default' => 0,
                'null' => false,
            ]);
            $table->update();
        }
    }
}

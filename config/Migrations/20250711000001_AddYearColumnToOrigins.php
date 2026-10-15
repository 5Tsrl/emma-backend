<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddYearColumnToOrigins extends AbstractMigration
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
        $table = $this->table('origins');
        $table->addColumn('year', 'integer', [
            'default' => null,
            'null' => true,
            'limit' => 4,
            'signed' => false,
            'comment' => 'Year field matching surveys table format',
            'after' => 'distance'
        ]);
        $table->update();
    }
}

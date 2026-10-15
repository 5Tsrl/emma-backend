<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddRefreshTokenToUsers extends AbstractMigration
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
        $table = $this->table('users');
        $table->addColumn('refresh_token', 'string', [
            'default' => null,
            'limit' => 500,
            'null' => true,
            'after' => 'token_expires'
        ]);
        $table->addColumn('refresh_token_expires', 'datetime', [
            'default' => null,
            'null' => true,
            'after' => 'refresh_token'
        ]);
        $table->update();
    }
}

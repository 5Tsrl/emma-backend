<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateGroups extends AbstractMigration
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
        $table = $this->table('groups');
        $table->addColumn('alias', 'string', [
            'limit' => 100,
            'null' => false,
        ]);
        $table->addColumn('name', 'string', [
            'limit' => 45,
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('redirect', 'string', [
            'limit' => 255,
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();

        // Aggiungi ruoli admin, moma, user, moma-area, superiori
        $this->execute("INSERT INTO `groups` (`alias`, `name`, `redirect`) VALUES
            ('admin', 'Admin', '/'),
            ('moma', 'Moma', '/'),
            ('user', 'User', '/'),
            ('moma-area', 'Moma Area', '/'),
            ('superiori', 'Superiori', '/')
        ");
    }
}

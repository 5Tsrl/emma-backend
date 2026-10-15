<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddExpirationDateToUsers extends AbstractMigration
{
	public function up(): void
	{
		$table = $this->table('users');
		$table->addColumn('expiration_date', 'date', [
			'default' => null,
			'null' => true,
			'after' => 'refresh_token_expires',
			'comment' => 'Data di scadenza account utente'
		]);
		$table->update();
	}

	public function down(): void
	{
		$table = $this->table('users');
		$table->removeColumn('expiration_date');
		$table->update();
	}
}

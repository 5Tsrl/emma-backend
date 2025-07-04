<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * BatchesUsersFixture
 */
class BatchesUsersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'user_id' => '48c09e21-acea-45ec-9b5e-28593d002080',
                'batch_id' => 'ecc5ca4f-67bb-445e-b45e-8286d2b47b9b',
            ],
        ];
        parent::init();
    }
}

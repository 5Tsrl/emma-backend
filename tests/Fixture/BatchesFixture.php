<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * BatchesFixture
 */
class BatchesFixture extends TestFixture
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
                'id' => '9c5d4126-2012-4450-a92f-83cef9a87072',
                'username' => 'Lorem ipsum dolor sit amet',
                'user_id' => 'c944be4e-3673-4d7b-b80f-aa5e7e2123c6',
                'created' => '2025-06-05 17:24:48',
                'modified' => '2025-06-05 17:24:48',
            ],
        ];
        parent::init();
    }
}

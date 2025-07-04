<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AreasFixture
 */
class AreasFixture extends TestFixture
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
                'city' => 'Lorem ipsum dolor sit amet',
                'province' => 'Lo',
                'polygon' => 'Lorem ipsum dolor sit amet',
            ],
        ];
        parent::init();
    }
}

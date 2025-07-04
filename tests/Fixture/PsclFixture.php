<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PsclFixture
 */
class PsclFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public $table = 'pscl';
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
                'version_tag' => 'Lorem ipsum dolor sit amet',
                'company_id' => 1,
                'office_id' => 1,
                'survey_id' => 1,
                'plan' => '',
                'created' => '2023-12-29 11:01:22',
                'modified' => '2023-12-29 11:01:22',
            ],
        ];
        parent::init();
    }
}

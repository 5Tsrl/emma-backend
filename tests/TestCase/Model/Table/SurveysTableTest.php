<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SurveysTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SurveysTable Test Case
 */
class SurveysTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\SurveysTable
     */
    protected $Surveys;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.Surveys',
        'app.Companies',
        'app.Answers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('Surveys') ? [] : ['className' => SurveysTable::class];
        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Surveys);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

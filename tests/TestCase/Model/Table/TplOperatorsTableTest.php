<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TplOperatorsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TplOperatorsTable Test Case
 */
class TplOperatorsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TplOperatorsTable
     */
    protected $TplOperators;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.TplOperators',
        'app.Companies',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('TplOperators') ? [] : ['className' => TplOperatorsTable::class];
        $this->TplOperators = $this->fetchTable('TplOperators', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->TplOperators);

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

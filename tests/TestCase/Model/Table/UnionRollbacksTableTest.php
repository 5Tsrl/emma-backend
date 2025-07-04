<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UnionRollbacksTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\UnionRollbacksTable Test Case
 */
class UnionRollbacksTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\UnionRollbacksTable
     */
    protected $UnionRollbacks;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.UnionRollbacks',
        'app.QuestionsSurveys',
        'app.Answers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('UnionRollbacks') ? [] : ['className' => UnionRollbacksTable::class];
        $this->UnionRollbacks = $this->getTableLocator()->get('UnionRollbacks', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->UnionRollbacks);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\UnionRollbacksTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\UnionRollbacksTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

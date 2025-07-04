<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BatchesUsersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\BatchesUsersTable Test Case
 */
class BatchesUsersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\BatchesUsersTable
     */
    protected $BatchesUsers;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.BatchesUsers',
        'app.Users',
        'app.Batches',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('BatchesUsers') ? [] : ['className' => BatchesUsersTable::class];
        $this->BatchesUsers = $this->getTableLocator()->get('BatchesUsers', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->BatchesUsers);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\BatchesUsersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\BatchesUsersTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

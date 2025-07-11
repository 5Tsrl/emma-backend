<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TimetablesTable;
use Cake\TestSuite\TestCase;

/**
 * Moma\Model\Table\TimetablesTable Test Case
 */
class TimetablesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Moma\Model\Table\TimetablesTable
     */
    protected $Timetables;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'Timetables',
        'Offices',
        'Users',
        'Timeslot',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Timetables') ? [] : ['className' => TimetablesTable::class];
        $this->Timetables = $this->fetchTable('Timetables', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Timetables);

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

<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TimeslotsTable;
use Cake\TestSuite\TestCase;

/**
 * Moma\Model\Table\TimeslotsTable Test Case
 */
class TimeslotsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Moma\Model\Table\TimeslotsTable
     */
    protected $Timeslots;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'Timeslots',
        'Timetables',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Timeslots') ? [] : ['className' => TimeslotsTable::class];
        $this->Timeslots = $this->fetchTable('Timeslots', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Timeslots);

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

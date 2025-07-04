<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MonitoringsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MonitoringsTable Test Case
 */
class MonitoringsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\MonitoringsTable
     */
    protected $Monitorings;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.Monitorings',
        'app.Measures',
        'app.Offices',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Monitorings') ? [] : ['className' => MonitoringsTable::class];
        $this->Monitorings = $this->fetchTable('Monitorings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Monitorings);

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

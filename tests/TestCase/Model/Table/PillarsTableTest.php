<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PillarsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PillarsTable Test Case
 */
class PillarsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PillarsTable
     */
    protected $Pillars;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.Pillars',
        'app.Measures',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Pillars') ? [] : ['className' => PillarsTable::class];
        $this->Pillars = $this->fetchTable('Pillars', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Pillars);

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
}

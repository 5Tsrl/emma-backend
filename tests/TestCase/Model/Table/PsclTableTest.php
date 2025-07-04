<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PsclTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PsclTable Test Case
 */
class PsclTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PsclTable
     */
    protected $Pscl;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Pscl',
        'app.Companies',
        'app.Offices',
        'app.Surveys',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Pscl') ? [] : ['className' => PsclTable::class];
        $this->Pscl = $this->getTableLocator()->get('Pscl', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Pscl);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\PsclTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\PsclTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

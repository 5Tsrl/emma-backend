<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Model\Entity\questions;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Entity\questions Test Case
 */
class questionsTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Entity\questions
     */
    protected $questions;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->questions = new questions();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->questions);

        parent::tearDown();
    }
}

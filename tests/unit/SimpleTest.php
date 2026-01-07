<?php
/**
 * Simple Smoke Test
 *
 * Verifies PHPUnit and Brain\Monkey are working correctly.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SimpleTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Verify PHPUnit is working
     */
    public function test_phpunit_works() {
        $this->assertTrue(true);
    }

    /**
     * Verify Brain\Monkey can mock WordPress functions
     */
    public function test_brain_monkey_works() {
        Functions\when('wp_remote_get')->justReturn(['body' => 'test']);
        
        $result = wp_remote_get('http://example.com');
        
        $this->assertEquals(['body' => 'test'], $result);
    }

    /**
     * Verify basic PHP functionality
     */
    public function test_basic_php() {
        $arr = ['foo' => 'bar'];
        $this->assertArrayHasKey('foo', $arr);
        $this->assertEquals('bar', $arr['foo']);
    }
}

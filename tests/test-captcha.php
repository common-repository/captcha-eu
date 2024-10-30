<?php
/**
 * @covers \KMM\Flattable\Core
 */
use CAPTCHA\Plugin\Core;

class FlattableTestDB
{
    public $prefix = 'wptest';

    public function query($sql)
    {
    }

    public function get_results($r)
    {
    }

    public function prepare($data)
    {
    }
}

class TestFlattable extends \WP_UnitTestCase
{
    public function setUp(): void
    {
        // setup a rest server
        parent::setUp();
        $this->core = new Core('i18n');
    }

    /**
     * @test
     */
    public function dummy_test()
    {
        $this->assertNull(null);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}

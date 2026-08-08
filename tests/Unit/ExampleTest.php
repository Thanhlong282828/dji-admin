<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_runtime_random_bytes_have_requested_length(): void
    {
        $this->assertSame(8, strlen(random_bytes(8)));
    }
}

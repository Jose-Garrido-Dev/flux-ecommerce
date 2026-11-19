<?php

namespace Tests\Unit\Support;

use App\Support\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_validates_ruts(): void
    {
        $this->assertTrue(Rut::isValid('12.345.678-5'));
        $this->assertTrue(Rut::isValid('123456785'));
        $this->assertTrue(Rut::isValid('11.111.111-1'));
        $this->assertTrue(Rut::isValid('10.000.013-k'));
        $this->assertFalse(Rut::isValid('12.345.678-9'));
        $this->assertFalse(Rut::isValid('abc'));
        $this->assertFalse(Rut::isValid(''));
    }

    public function test_formats_rut(): void
    {
        $this->assertSame('12.345.678-5', Rut::format('123456785'));
        $this->assertSame('10.000.013-K', Rut::format('10000013k'));
    }
}

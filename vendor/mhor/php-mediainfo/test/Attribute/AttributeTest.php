<?php

namespace Mhor\MediaInfo\Test\Attribute;

use Mhor\MediaInfo\Attribute\Cover;
use Mhor\MediaInfo\Attribute\Duration;
use Mhor\MediaInfo\Attribute\Mode;
use Mhor\MediaInfo\Attribute\Rate;
use Mhor\MediaInfo\Attribute\Size;

class AttributeTest extends \PHPUnit_Framework_TestCase
{
    public function testCover()
    {
        $cover = new Cover('binary_string');
        $this->assertEquals('binary_string', $cover->getBinaryCover());
        $this->assertSame('binary_string', (string) $cover);
    }

    public function testDuration()
    {
        $duration = new Duration(1000);
        $this->assertEquals(1000, $duration->getMilliseconds());
        $this->assertSame('1000', (string) $duration);
    }

    public function testMode()
    {
        $mode = new Mode('short', 'full');
        $this->assertEquals('short', $mode->getShortName());
        $this->assertEquals('full', $mode->getFullName());
        $this->assertSame('short', (string) $mode);
    }

    public function testRate()
    {
        $rate = new Rate(15555, '15.55 Mo');
        $this->assertEquals(15555, $rate->getAbsoluteValue());
        $this->assertEquals('15.55 Mo', $rate->getTextValue());
        $this->assertSame('15.55 Mo', (string) $rate);
    }

    public function testSize()
    {
        $size = new Size(42);
        $this->assertEquals(42, $size->getBit());
        $this->assertSame('42', (string) $size);
    }
}

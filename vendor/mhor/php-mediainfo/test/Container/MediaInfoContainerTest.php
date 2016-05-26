<?php

use Mhor\MediaInfo\Container\MediaInfoContainer;
use Mhor\MediaInfo\Type\Audio;
use Mhor\MediaInfo\Type\General;

class MediaInfoContainerTest extends \PHPUnit_Framework_TestCase
{
    private function createContainer()
    {
        $mediaInfoContainer = new MediaInfoContainer();
        $general = new General();

        $general->set('Format', 'MPEG Audio');
        $general->set('Duration', '1mn 20s');

        $audio = new Audio();

        $audio->set('Format', 'MPEG Audio');
        $audio->set('Bit rate', '56.0 Kbps');

        $mediaInfoContainer->add($audio);
        $mediaInfoContainer->add($general);

        return $mediaInfoContainer;
    }

    public function testToJson()
    {
        $mediaInfoContainer = $this->createContainer();

        $data = json_encode($mediaInfoContainer);

        $this->assertRegExp('/^\{.+\}$/', $data);
    }

    public function testToJsonType()
    {
        $mediaInfoContainer = $this->createContainer();

        $data = json_encode($mediaInfoContainer->getGeneral());

        $this->assertRegExp('/^\{.+\}$/', $data);
    }

    public function testToArray()
    {
        $mediaInfoContainer = $this->createContainer();

        $array = $mediaInfoContainer->__toArray();

        $this->assertArrayHasKey('version', $array);
    }

    public function testToArrayType()
    {
        $mediaInfoContainer = $this->createContainer();

        $array = $mediaInfoContainer->getGeneral()->__toArray();

        $this->assertTrue(is_array($array));
    }

    public function testToXML()
    {
        $mediaInfoContainer = $this->createContainer();

        $xml = $mediaInfoContainer->__toXML();

        $this->assertInstanceOf('SimpleXMLElement', $xml);
    }

    public function testToXMLType()
    {
        $mediaInfoContainer = $this->createContainer();

        $general = $mediaInfoContainer->getGeneral();

        $xml = $general->__toXML();

        $this->assertInstanceOf('SimpleXMLElement', $xml);
    }
}

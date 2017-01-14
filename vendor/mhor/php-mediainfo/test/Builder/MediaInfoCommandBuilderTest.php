<?php

namespace Mhor\MediaInfo\Test\Builder;

use Mhor\MediaInfo\Builder\MediaInfoCommandBuilder;
use Mhor\MediaInfo\Runner\MediaInfoCommandRunner;

class MediaInfoCommandBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $filePath;

    public function setUp()
    {
        $this->filePath = __DIR__.'/../fixtures/test.mp3';
    }

    public function testBuilderCommandWithUrl()
    {
        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandRunner = $mediaInfoCommandBuilder->buildMediaInfoCommandRunner('https://example.org/');

        $equalsMediaInfoCommandRunner = new MediaInfoCommandRunner('https://example.org/');
        $this->assertEquals($equalsMediaInfoCommandRunner, $mediaInfoCommandRunner);

        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandRunner = $mediaInfoCommandBuilder->buildMediaInfoCommandRunner('http://example.org/');

        $equalsMediaInfoCommandRunner = new MediaInfoCommandRunner('http://example.org/');
        $this->assertEquals($equalsMediaInfoCommandRunner, $mediaInfoCommandRunner);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage File "non existing path" does not exist
     */
    public function testExceptionWithNonExistingFile()
    {
        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandBuilder->buildMediaInfoCommandRunner('non existing path');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Expected a filename, got ".", which is a directory
     */
    public function testExceptionWithDirectory()
    {
        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandBuilder->buildMediaInfoCommandRunner('.');
    }

    public function testBuilderCommand()
    {
        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandRunner = $mediaInfoCommandBuilder->buildMediaInfoCommandRunner($this->filePath);

        $equalsMediaInfoCommandRunner = new MediaInfoCommandRunner($this->filePath);
        $this->assertEquals($equalsMediaInfoCommandRunner, $mediaInfoCommandRunner);
    }

    public function testConfiguredCommand()
    {
        $mediaInfoCommandBuilder = new MediaInfoCommandBuilder();
        $mediaInfoCommandRunner = $mediaInfoCommandBuilder->buildMediaInfoCommandRunner(
            $this->filePath,
            array(
                'command' => '/usr/bin/local/mediainfo',
            )
        );

        $equalsMediaInfoCommandRunner = new MediaInfoCommandRunner($this->filePath, '/usr/bin/local/mediainfo');
        $this->assertEquals($equalsMediaInfoCommandRunner, $mediaInfoCommandRunner);
    }
}

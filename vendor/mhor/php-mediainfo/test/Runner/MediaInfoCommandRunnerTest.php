<?php

namespace Mhor\MediaInfo\Test\Parser;

use Mhor\MediaInfo\Runner\MediaInfoCommandRunner;

class MediaInfoCommandRunnerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $outputPath;

    /**
     * @var string
     */
    private $filePath;

    public function setUp()
    {
        $this->filePath = __DIR__.'/../fixtures/test.mp3';
        $this->outputPath = __DIR__.'/../fixtures/mediainfo-output.xml';
    }

    public function testRun()
    {
        $processMock = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->method('run')
            ->willReturn(true);

        $processMock->method('getOutput')
            ->willReturn(file_get_contents($this->outputPath));

        $processMock->method('isSuccessful')
            ->willReturn(true);

        $processBuilderMock = $this->getMockBuilder('Symfony\Component\Process\ProcessBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $processBuilderMock->method('getProcess')
            ->willReturn($processMock);

        $mediaInfoCommandRunner = new MediaInfoCommandRunner(
            $this->filePath,
            null,
            array('--OUTPUT=XML', '-f'),
            $processBuilderMock
        );

        $this->assertEquals(file_get_contents($this->outputPath), $mediaInfoCommandRunner->run());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRunException()
    {
        $processMock = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->method('run')
            ->willReturn(true);

        $processMock->method('getErrorOutput')
            ->willReturn('Error');

        $processMock->method('isSuccessful')
            ->willReturn(false);

        $processBuilderMock = $this->getMockBuilder('Symfony\Component\Process\ProcessBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $processBuilderMock->method('getProcess')
            ->willReturn($processMock);

        $mediaInfoCommandRunner = new MediaInfoCommandRunner(
            $this->filePath,
            'custom_mediainfo',
            array('--OUTPUT=XML', '-f'),
            $processBuilderMock
        );

        $mediaInfoCommandRunner->run();
    }

    public function testRunAsync()
    {
        $processMock = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock();

        $processMock->method('start')
            ->willReturn($processMock);

        $processMock->method('wait')
            ->willReturn(true);

        $processMock->method('getOutput')
            ->willReturn(file_get_contents($this->outputPath));

        $processMock->method('isSuccessful')
            ->willReturn(true);

        $processBuilderMock = $this->getMockBuilder('Symfony\Component\Process\ProcessBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $processBuilderMock->method('getProcess')
            ->willReturn($processMock);

        $mediaInfoCommandRunner = new MediaInfoCommandRunner(
            $this->filePath,
            null,
            array('--OUTPUT=XML', '-f'),
            $processBuilderMock
        );

        $mediaInfoCommandRunner->start();

        // do some stuff in between, count to 5
        $i = 0;
        do {
            $i++;
        } while ($i < 5);

        // block and complete operation
        $output = $mediaInfoCommandRunner->wait();

        $this->assertEquals(file_get_contents($this->outputPath), $output);
    }
}

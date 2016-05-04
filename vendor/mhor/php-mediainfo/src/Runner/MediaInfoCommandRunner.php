<?php

namespace Mhor\MediaInfo\Runner;

use Symfony\Component\Process\ProcessBuilder;

class MediaInfoCommandRunner
{
    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var ProcessBuilder
     */
    protected $processBuilder;

    /**
     * @var Process
     */
    protected $processAsync = null;

    /**
     * @var string
     */
    protected $command = 'mediainfo';

    /**
     * @var array
     */
    protected $arguments = array('--OUTPUT=XML', '-f');

    /**
     * @param string         $filePath
     * @param array          $arguments
     * @param ProcessBuilder $processBuilder
     */
    public function __construct(
        $filePath,
        $command = null,
        array $arguments = null,
        $processBuilder = null
    ) {
        $this->filePath = $filePath;
        if ($command !== null) {
            $this->command = $command;
        }

        if ($arguments !== null) {
            $this->arguments = $arguments;
        }

        if ($processBuilder === null) {
            $this->processBuilder = ProcessBuilder::create()
            ->setPrefix($this->command)
            ->setArguments($this->arguments);
        } else {
            $this->processBuilder = $processBuilder;
        }
    }

    /**
     * @throws \RuntimeException
     *
     * @return string
     */
    public function run()
    {
        $lc_ctype = setlocale(LC_CTYPE, 0);
        $this->processBuilder->add($this->filePath);
        $this->processBuilder->setEnv('LANG', $lc_ctype);
        $process = $this->processBuilder->getProcess();
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Asynchronously start mediainfo operation.
     * Make call to MediaInfoCommandRunner::wait() afterwards to receive output.
     */
    public function start()
    {
        $this->processBuilder->add($this->filePath);
        $this->processAsync = $this->processBuilder->getProcess();
        // just takes advantage of symfony's underlying Process framework
        // process runs in background
        $this->processAsync->start();
    }

    /**
     * Blocks until call is complete.
     *
     * @throws \Exception        If this function is called before start()
     * @throws \RuntimeException
     *
     * @return string
     */
    public function wait()
    {
        if ($this->processAsync == null) {
            throw new \Exception('You must run `start` before running `wait`');
        }

        // blocks here until process completes
        $this->processAsync->wait();

        if (!$this->processAsync->isSuccessful()) {
            throw new \RuntimeException($this->processAsync->getErrorOutput());
        }

        return $this->processAsync->getOutput();
    }
}

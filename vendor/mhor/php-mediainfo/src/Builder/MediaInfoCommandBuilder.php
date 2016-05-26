<?php

namespace Mhor\MediaInfo\Builder;

use Mhor\MediaInfo\Runner\MediaInfoCommandRunner;
use Symfony\Component\Filesystem\Filesystem;

class MediaInfoCommandBuilder
{
    /**
     * @param string $filepath
     * @param array  $configuration
     *
     * @return MediaInfoCommandRunner
     */
    public function buildMediaInfoCommandRunner($filepath, array $configuration = array())
    {
        if (filter_var($filepath, FILTER_VALIDATE_URL) === false) {
            $fileSystem = new Filesystem();

            if (!$fileSystem->exists($filepath)) {
                throw new \Exception('File doesn\'t exist');
            }

            if (is_dir($filepath)) {
                throw new \Exception('You must specify a filename, not a directory name');
            }
        }

        $configuration = $configuration + array(
            'command' => null,
        );

        return new MediaInfoCommandRunner($filepath, $configuration['command']);
    }
}

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
                throw new \Exception(sprintf('File "%s" does not exist', $filepath));
            }

            if (is_dir($filepath)) {
                throw new \Exception(sprintf(
                    'Expected a filename, got "%s", which is a directory',
                    $filepath
                ));
            }
        }

        $configuration = $configuration + array(
            'command' => null,
        );

        return new MediaInfoCommandRunner($filepath, $configuration['command']);
    }
}

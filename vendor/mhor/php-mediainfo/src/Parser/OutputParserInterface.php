<?php

namespace Mhor\MediaInfo\Parser;

use Mhor\MediaInfo\Container\MediaInfoContainer;

interface OutputParserInterface
{
    /**
     * @param $output
     */
    public function parse($output);

    /**
     * @return MediaInfoContainer
     */
    public function getMediaInfoContainer();
}

<?php

namespace Mhor\MediaInfo\Parser;

use Mhor\MediaInfo\Builder\MediaInfoContainerBuilder;
use Mhor\MediaInfo\Container\MediaInfoContainer;
use Mhor\MediaInfo\Exception\UnknownTrackTypeException;

class MediaInfoOutputParser extends AbstractXmlOutputParser
{
    /**
     * @var array
     */
    private $parsedOutput;

    /**
     * @param string $output
     */
    public function parse($output)
    {
        $this->parsedOutput = $this->transformXmlToArray($output);
    }

    /**
     * @param bool $ignoreUnknownTrackTypes Optional parameter used to skip unknown track types by passing true. The
     *                                      default behavior (false) is throw an exception on unknown track types.
     *
     * @return MediaInfoContainer
     */
    public function getMediaInfoContainer($ignoreUnknownTrackTypes = false)
    {
        if ($this->parsedOutput === null) {
            throw new \Exception('You must run `parse` before running `getMediaInfoContainer`');
        }

        $mediaInfoContainerBuilder = new MediaInfoContainerBuilder($ignoreUnknownTrackTypes);
        $mediaInfoContainerBuilder->setVersion($this->parsedOutput['@attributes']['version']);

        foreach ($this->parsedOutput['File']['track'] as $trackType) {
            try {
                if (isset($trackType['@attributes']['type'])) {
                    $mediaInfoContainerBuilder->addTrackType($trackType['@attributes']['type'], $trackType);
                }
            } catch (UnknownTrackTypeException $ex) {
                if (!$ignoreUnknownTrackTypes) {
                    // rethrow exception
                    throw $ex;
                }
                // else ignore
            }
        }

        return $mediaInfoContainerBuilder->build();
    }
}

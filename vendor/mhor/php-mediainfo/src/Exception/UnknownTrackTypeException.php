<?php

namespace Mhor\MediaInfo\Exception;

class UnknownTrackTypeException extends \Exception
{
    private $trackType = null;

    public function __construct($trackType, $code = 0)
    {
        parent::__construct(sprintf('Type doesn\'t exist: %s', $trackType), $code, null);
        $this->trackType = $trackType;
    }

    public function getTrackType()
    {
        return $this->trackType;
    }
}

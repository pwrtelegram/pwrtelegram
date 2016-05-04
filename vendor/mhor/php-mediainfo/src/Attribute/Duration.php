<?php

namespace Mhor\MediaInfo\Attribute;

class Duration implements AttributeInterface
{
    /**
     * @var int
     */
    private $milliseconds;

    /**
     * @param $duration
     */
    public function __construct($duration)
    {
        $this->milliseconds = $duration;
    }

    /**
     * @return int
     */
    public function getMilliseconds()
    {
        return $this->milliseconds;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->milliseconds;
    }
}

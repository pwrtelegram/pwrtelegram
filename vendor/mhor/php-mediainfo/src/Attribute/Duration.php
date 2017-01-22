<?php

namespace Mhor\MediaInfo\Attribute;

use Mhor\MediaInfo\DumpTrait;

class Duration implements AttributeInterface
{
    use DumpTrait;

    /**
     * @var int
     */
    private $milliseconds;

    /**
     * @param string|int $duration
     */
    public function __construct($duration)
    {
        $this->milliseconds = (int) $duration;
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

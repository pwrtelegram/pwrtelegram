<?php

namespace Mhor\MediaInfo\Attribute;

class Size implements AttributeInterface
{
    /**
     * @var int
     */
    private $bit;

    /**
     * @param int $size
     */
    public function __construct($size)
    {
        $this->bit = $size;
    }

    /**
     * @return int
     */
    public function getBit()
    {
        return $this->bit;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->bit;
    }
}

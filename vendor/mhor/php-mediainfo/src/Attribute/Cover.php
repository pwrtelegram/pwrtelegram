<?php

namespace Mhor\MediaInfo\Attribute;

use Mhor\MediaInfo\DumpTrait;

class Cover implements AttributeInterface
{
    use DumpTrait;
    /**
     * @var string
     */
    private $binaryCover;

    /**
     * @param string $cover
     */
    public function __construct($cover)
    {
        $this->binaryCover = $cover;
    }

    /**
     * @return string
     */
    public function getBinaryCover()
    {
        return $this->binaryCover;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->binaryCover;
    }
}

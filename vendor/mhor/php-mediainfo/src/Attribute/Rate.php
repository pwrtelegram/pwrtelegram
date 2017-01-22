<?php

namespace Mhor\MediaInfo\Attribute;

use Mhor\MediaInfo\DumpTrait;

class Rate implements AttributeInterface
{
    use DumpTrait;

    /**
     * @var float
     */
    private $absoluteValue;

    /**
     * @var string
     */
    private $textValue;

    /**
     * @param string|float $absoluteValue
     * @param string       $textValue
     */
    public function __construct($absoluteValue, $textValue)
    {
        $this->absoluteValue = (float) $absoluteValue;
        $this->textValue = $textValue;
    }

    /**
     * @return float
     */
    public function getAbsoluteValue()
    {
        return $this->absoluteValue;
    }

    /**
     * @return string
     */
    public function getTextValue()
    {
        return $this->textValue;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->textValue;
    }
}

<?php

namespace Mhor\MediaInfo\Attribute;

interface AttributeInterface extends \JsonSerializable
{
    /**
     * @return string
     */
    public function __toString();
}

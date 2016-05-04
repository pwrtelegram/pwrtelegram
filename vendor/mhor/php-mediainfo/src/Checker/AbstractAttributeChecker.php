<?php

namespace Mhor\MediaInfo\Checker;

abstract class AbstractAttributeChecker implements AttributeCheckerInterface
{
    /**
     * @param $attribute
     *
     * @return bool
     */
    public function isMember($attribute)
    {
        return in_array($attribute, $this->getMembersFields());
    }
}

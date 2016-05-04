<?php

namespace Mhor\MediaInfo\Checker;

interface AttributeCheckerInterface
{
    public function getMembersFields();

    public function create($value);
}

<?php

namespace Mhor\MediaInfo\Checker;

use Mhor\MediaInfo\Attribute\Cover;

class CoverChecker extends AbstractAttributeChecker
{
    /**
     * @param string $value
     *
     * @return Cover
     */
    public function create($value)
    {
        $cover = new Cover($value);

        return $cover;
    }

    /**
     * @return array
     */
    public function getMembersFields()
    {
        return array('cover_data');
    }
}

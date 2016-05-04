<?php

namespace Mhor\MediaInfo\Checker;

use Mhor\MediaInfo\Attribute\Size;

class SizeChecker extends AbstractAttributeChecker
{
    /**
     * @param array $sizes
     *
     * @return Size
     */
    public function create($sizes)
    {
        $size = new Size($sizes[0]);

        return $size;
    }

    /**
     * @return array
     */
    public function getMembersFields()
    {
        return array(
            'file_size',
            'stream_size',
        );
    }
}

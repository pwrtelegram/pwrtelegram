<?php

namespace Mhor\MediaInfo\Factory;

use Mhor\MediaInfo\Exception\UnknownTrackTypeException;
use Mhor\MediaInfo\Type as Type;

class TypeFactory
{
    const AUDIO = 'Audio';
    const IMAGE = 'Image';
    const GENERAL = 'General';
    const VIDEO = 'Video';
    const SUBTITLE = 'Text';
    const MENU = 'Menu';
    const OTHER = 'Other';

    /**
     * @param string $type
     *
     * @throws \Mhor\MediaInfo\Exception\UnknownTrackTypeException
     *
     * @return Type\AbstractType
     */
    public function create($type)
    {
        switch ($type) {
            case self::AUDIO:
                return new Type\Audio();
            case self::IMAGE:
                return new Type\Image();
            case self::GENERAL:
                return new Type\General();
            case self::VIDEO:
                return new Type\Video();
            case self::SUBTITLE:
                return new Type\Subtitle();
            case self::MENU:
                return new Type\Menu();
            case self::OTHER:
                return new Type\Other();
            default:
                throw new UnknownTrackTypeException($type);
        }
    }
}

<?php

namespace Mhor\MediaInfo\Container;

use Mhor\MediaInfo\DumpTrait;
use Mhor\MediaInfo\Type\AbstractType;
use Mhor\MediaInfo\Type\Audio;
use Mhor\MediaInfo\Type\General;
use Mhor\MediaInfo\Type\Image;
use Mhor\MediaInfo\Type\Menu;
use Mhor\MediaInfo\Type\Other;
use Mhor\MediaInfo\Type\Subtitle;
use Mhor\MediaInfo\Type\Video;

class MediaInfoContainer implements \JsonSerializable
{
    use DumpTrait;

    const GENERAL_CLASS = 'Mhor\MediaInfo\Type\General';
    const AUDIO_CLASS = 'Mhor\MediaInfo\Type\Audio';
    const IMAGE_CLASS = 'Mhor\MediaInfo\Type\Image';
    const VIDEO_CLASS = 'Mhor\MediaInfo\Type\Video';
    const SUBTITLE_CLASS = 'Mhor\MediaInfo\Type\Subtitle';
    const MENU_CLASS = 'Mhor\MediaInfo\Type\Menu';
    const OTHER_CLASS = 'Mhor\MediaInfo\Type\Other';

    /**
     * @var string
     */
    private $version;

    /**
     * @var General
     */
    private $general;

    /**
     * @var Audio[]
     */
    private $audios = array();

    /**
     * @var Video[]
     */
    private $videos = array();

    /**
     * @var Subtitle[]
     */
    private $subtitles = array();

    /**
     * @var Image[]
     */
    private $images = array();

    /**
     * @var Menu[]
     */
    private $menus = array();

    /**
     * @var Other[]
     */
    private $others = array();

    /**
     * @return General
     */
    public function getGeneral()
    {
        return $this->general;
    }

    /**
     * @return Audio[]
     */
    public function getAudios()
    {
        return $this->audios;
    }

    /**
     * @return Image[]
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * @return Menu[]
     */
    public function getMenus()
    {
        return $this->menus;
    }

    /**
     * @return Other[]
     */
    public function getOthers()
    {
        return $this->others;
    }

    /**
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return Video[]
     */
    public function getVideos()
    {
        return $this->videos;
    }

    /**
     * @return Subtitle[]
     */
    public function getSubtitles()
    {
        return $this->subtitles;
    }

    /**
     * @param General $general
     */
    public function setGeneral($general)
    {
        $this->general = $general;
    }

    /**
     * @param AbstractType $trackType
     *
     * @throws \Exception
     */
    public function add(AbstractType $trackType)
    {
        switch (get_class($trackType)) {
            case self::AUDIO_CLASS:
                $this->addAudio($trackType);
                break;
            case self::VIDEO_CLASS:
                $this->addVideo($trackType);
                break;
            case self::IMAGE_CLASS:
                $this->addImage($trackType);
                break;
            case self::GENERAL_CLASS:
                $this->setGeneral($trackType);
                break;
            case self::SUBTITLE_CLASS:
                $this->addSubtitle($trackType);
                break;
            case self::MENU_CLASS:
                $this->addMenu($trackType);
                break;
            case self::OTHER_CLASS:
                $this->addOther($trackType);
                break;
            default:
                throw new \Exception('Unknown type');
        }
    }

    /**
     * @param Audio $audio
     */
    private function addAudio(Audio $audio)
    {
        $this->audios[] = $audio;
    }

    /**
     * @param Video $video
     */
    private function addVideo(Video $video)
    {
        $this->videos[] = $video;
    }

    /**
     * @param Image $image
     */
    private function addImage(Image $image)
    {
        $this->images[] = $image;
    }

    /**
     * @param Subtitle $subtitle
     */
    private function addSubtitle(Subtitle $subtitle)
    {
        $this->subtitles[] = $subtitle;
    }

    /**
     * @param Menu $menu
     */
    private function addMenu(Menu $menu)
    {
        $this->menus[] = $menu;
    }

    /**
     * @param Other $other
     */
    private function addOther(Other $other)
    {
        $this->others[] = $other;
    }
}

<?php

namespace Mhor\MediaInfo\Type;

use Mhor\MediaInfo\DumpTrait;

abstract class AbstractType implements \JsonSerializable
{
    use DumpTrait;

    /**
     * @var array
     */
    protected $attributes = array();

    /**
     * @param $attribute
     * @param string|object $value
     *
     * @return string
     */
    public function set($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * @param string $attribute
     *
     * @return mixed
     */
    public function get($attribute = null)
    {
        if ($attribute === null) {
            return $this->attributes;
        }

        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }
    }

    /**
     * Convert the object into json.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $array = get_object_vars($this);

        if (isset($array['attributes'])) {
            $array = $array['attributes'];
        }

        return $array;
    }

    /**
     * Convert the object into array.
     *
     * @return array
     */
    public function __toArray()
    {
        return $this->jsonSerialize();
    }
}

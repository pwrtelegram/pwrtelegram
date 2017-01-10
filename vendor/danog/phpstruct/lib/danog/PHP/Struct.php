<?php

namespace danog\PHP;

/**
 * PHPStruct
 * PHP implementation of Python's struct module.
 * This library was created to help me develop a [client for the mtproto protocol](https://github.com/danog/MadelineProto).
 * The functions and the formats are exactly the ones used in python's struct (https://docs.python.org/3/library/struct.html)
 * For now custom byte size may not work properly on certain machines for the f and d formats.
 *
 * @author		Daniil Gentili <daniil@daniil.it>
 * @license		MIT license
 */
// Struct class (for static access)
class Struct
{
    private static $struct = null;

    /**
     * constructor.
     *
     * Istantiates the PHPStruct class in a static variable
     *
     * @param	$format	    Format string
     *
     */
    public static function constructor() {
        if (self::$struct == null) {
            self::$struct = new \danog\PHP\StructTools();
        }
    }
    /**
     * pack.
     *
     * Packs data into bytes
     *
     * @param	$format	    Format string
     * @param	...$data	Parameters to encode
     *
     * @return Encoded data
     */
    public static function pack($format, ...$data)
    {
        self::constructor();
        return self::$struct->pack($format, ...$data);
    }

    /**
     * unpack.
     *
     * Unpacks data into an array
     *
     * @param	$format	Format string
     * @param	$data	Data to decode
     *
     * @return Decoded data
     */
    public static function unpack($format, $data)
    {
        self::constructor();
        return self::$struct->unpack($format, $data);
    }

    /**
     * calcsize.
     *
     * Return the size of the struct (and hence of the string) corresponding to the given format.

     *
     * @param	$format	Format string
     *
     * @return int with size of the struct.
     */
    public static function calcsize($format)
    {
        self::constructor();

        return self::$struct->calcsize($format);
    }
}

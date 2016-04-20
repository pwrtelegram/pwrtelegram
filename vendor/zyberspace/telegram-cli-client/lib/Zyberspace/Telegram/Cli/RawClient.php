<?php
/**
 * Copyright 2015 Eric Enold <zyberspace@zyberware.org>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Zyberspace\Telegram\Cli;

/**
 * Raw part of the php-client for telegram-cli.
 * Takes care of the socket-connection and some helper-methods.
 */
class RawClient
{
    /**
     * The file handler for the socket-connection
     *
     * @var ressource
     */
    protected $_fp;

    /**
     * If telegram-cli returns an error, the error-message gets stored here.
     *
     * @var string
     */
    protected $_errorMessage = null;

    /**
     * If telegram-cli returns an error, the error-code gets stored here.
     *
     * @var int
     */
    protected $_errorCode = null;

    /**
     * Connects to the telegram-cli.
     *
     * @param string $remoteSocket Address of the socket to connect to. See stream_socket_client() for more info.
     *                             Can be 'unix://' or 'tcp://'.
     *
     * @throws ClientException Throws an exception if no connection can be established.
     */
    public function __construct($remoteSocket)
    {
        $this->_fp = stream_socket_client($remoteSocket);
        if ($this->_fp === false) {
            throw new ClientException('Could not connect to socket "' . $remoteSocket . '"');
        }
    }

    /**
     * Closes the connection to the telegram-cli.
     */
    public function __destruct()
    {
        fclose($this->_fp);
    }

    /**
     * Executes a command on the telegram-cli. Line-breaks will be escaped, as telgram-cli does not support them.
     *
     * @param string $command The command. Command-arguments can be passed as additional method-arguments.
     *
     * @return object|boolean Returns the answer as a json-object or true on success, false if there was an error.
     */
    public function exec($command)
    {
        $command = implode(' ', func_get_args());

        fwrite($this->_fp, str_replace("\n", '\n', $command) . PHP_EOL);

        $answer = fgets($this->_fp); //"ANSWER $bytes" or false if an error occurred
        if (is_string($answer)) {
            if (substr($answer, 0, 7) === 'ANSWER ') {
                $bytes = ((int) substr($answer, 7)) + 1; //+1 because the json-return seems to miss one byte
                if ($bytes > 0) {
                    $bytesRead = 0;
                    $jsonString = '';

                    //Run fread() till we have all the bytes we want
                    //(as fread() can only read a maximum of 8192 bytes from a read-buffered stream at once)
                    do {
                        $jsonString .= fread($this->_fp, $bytes - $bytesRead);
                        $bytesRead = strlen($jsonString);
                    } while ($bytesRead < $bytes);

                    $json = json_decode($jsonString);

                    if (!isset($json->error)) {
                        //Reset error-message and error-code
                        $this->_errorMessage = null;
                        $this->_errorCode = null;

                        //For "status_online" and "status_offline"
                        if (isset($json->result) && $json->result === 'SUCCESS') {
                            return true;
                        }

                        //Return json-object
                        return $json;
                    } else {
                        $this->_errorMessage = $json->error;
                        $this->_errorCode = $json->error_code;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns the error-message retrieved vom telegram-cli, if there is one.
     *
     * @return string|null The error-message retrieved from telegram-cli or null if there was no error.
     */
    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    /**
     * Returns the error-code retrieved vom telegram-cli, if there is one.
     *
     * @return string|null The error-message retrieved from telegram-cli or null if there was no error.
     */
    public function getErrorCode()
    {
        return $this->_errorCode;
    }

    /**
     * Escapes strings for usage as command-argument.
     * T"es't -> "T\"es\'t"
     *
     * @param string $argument The argument to escape
     *
     * @return string The escaped command enclosed by double-quotes
     */
    public function escapeStringArgument($argument)
    {
        return '"' . addslashes($argument) . '"';
    }

    /**
     * Replaces all spaces with underscores.
     *
     * @param string $peer The peer to escape
     *
     * @return string The escaped peer
     */
    public function escapePeer($peer)
    {
        return str_replace(' ', '_', $peer);
    }

    /**
     * Takes a list of peers and turns it into a format needed by the most commands that handle multiple peers.
     * Every single peer gets escaped by escapePeer().
     *
     * @param array $peerList The list of peers that shall get formated
     *
     * @return string The formated list of peers
     *
     * @uses escapePeer()
     */
    public function formatPeerList(array $peerList)
    {
        return implode(' ', array_map(array($this, 'escapePeer'), $peerList));
    }

    /**
     * Turns the given $fileName into an absolute file path and escapes him
     *
     * @param string $fileName The path to the file (can be relative or absolute)
     *
     * @return string The absolute path to the file
     */
    public function formatFileName($fileName)
    {
        return $this->escapeStringArgument(realpath($fileName));
    }
}

<?php

/*
Copyright 2016 Daniil Gentili
(https://daniil.it)

This file is part of the PWRTelegram API.
the PWRTelegram API is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
The PWRTelegram API is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.
You should have received a copy of the GNU General Public License along with the PWRTelegram API.
If not, see <http://www.gnu.org/licenses/>.
*/

if (preg_match('/^storage/', $_SERVER['HTTP_HOST'])) {
    require_once 'storage.php';
    exit;
}

ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error-index.log');
set_time_limit(0);
ignore_user_abort(1);
require_once 'src/PWRTelegram/PWRTelegram/Tools.php';
require_once 'src/PWRTelegram/PWRTelegram/API.php';
require_once 'src/PWRTelegram/PWRTelegram/Proxy.php';
require_once 'src/PWRTelegram/PWRTelegram/Main.php';
require_once '../storage_url.php';
require_once '../db_connect.php';

function parseTLTrace($trace)
{
    $t = '';
    foreach (explode(PHP_EOL, $trace) as $frame) {
        if (strpos($frame, 'db_token') === false) {
            $t .= $frame.PHP_EOL;
        }
    }

    return $t;
}

$pwrhomedir = realpath(__DIR__);
$API = new \PWRTelegram\PWRTelegram\Main($GLOBALS);

try {
    $API->run();
} catch (\danog\MadelineProto\ResponseException $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log(parseTLTrace($e->getTLTrace()));
    $API->jsonexit(['ok' => false, 'error_code' => 400, 'description' => $e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile())]);
} catch (\danog\MadelineProto\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log(parseTLTrace($e->getTLTrace()));
    $API->jsonexit(['ok' => false, 'error_code' => 400, 'description' => $e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile())."\nTL Trace: ".parseTLTrace($e->getTLTrace())]);
} catch (\danog\MadelineProto\RPCErrorException $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log(parseTLTrace($e->getTLTrace()));
    if (in_array($e->rpc, ['SESSION_REVOKED', 'AUTH_KEY_UNREGISTERED'])) {
        foreach (glob($API->madeline_path.'*') as $path) {
            unlink($path);
        }
    }
    if (preg_match('|FLOOD_WAIT_|', $e->getMessage())) {
        $n = preg_replace('|\D|', '', $e->getMessage());
        $API->jsonexit(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after '.$n, 'params' => ['retry_after' => $n]]);
    } else {
        $API->jsonexit(['ok' => false, 'error_code' => $e->getCode(), 'description' => $e->getMessage()]);
    }
} catch (\danog\MadelineProto\TL\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log(parseTLTrace($e->getTLTrace()));
    $API->jsonexit(['ok' => false, 'error_code' => 400, 'description' => $e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile())."\nTL Trace: ".parseTLTrace($e->getTLTrace())]);
} catch (\PDOException $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    $API->jsonexit(['ok' => false, 'error_code' => 400, 'description' => $e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile())]);
} catch (\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    $API->jsonexit(['ok' => false, 'error_code' => 400, 'description' => $e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile())]);
}

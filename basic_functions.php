<?php

// pwrtelegram script
// by Daniil Gentili
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

/**
 * Returns.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 * @param $json - Default is true, if set to true will json_decode the content of the url.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */
function curl($url, $json = true)
{
    // Get cURL resource
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL            => str_replace(' ', '%20', $url),
    ]);
    $res = curl_exec($curl);
    curl_close($curl);
    if ($json == true) {
        return json_decode($res, true);
    } else {
        return $res;
    }
}

/**
 * Returns true if remote file exists, false if it doesn't exist.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */
function checkurl($url)
{
    $ch = curl_init(str_replace(' ', '%20', $url));
//	curl_setopt( $ch, CURLOPT_HEADER, true );
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_exec($ch);
    $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//	error_log($url . $retcode. curl_error($ch));
    curl_close($ch);
    if ($retcode == 200) {
        return true;
    } else {
        return false;
    }
}

// Die while outputting a json error
function jsonexit($wut)
{
    die(json_encode($wut));
}

function escapeJsonString($value)
{
    $escapers = ['\\', '/', "\n", "\r", "\t", "\x08", "\x0c"];
    $replacements = ['\\\\', '\\/', '\\n', '\\r', '\\t', '\\f', '\\b'];
    $result = str_replace($escapers, $replacements, $value);

    return $result;
}

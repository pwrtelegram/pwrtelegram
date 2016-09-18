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

// logging
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error-index.log');
// Home dir
$homedir = realpath(__DIR__.'/../').'/';
$pwrhomedir = realpath(__DIR__);
// Available methods and their equivalent in tg-cli
$methods = [
    'photo'    => 'photo',
    'video'    => 'video',
    'voice'    => 'document',
    'document' => 'document',
    'sticker'  => 'document',
    'audio'    => 'audio',
    'file'     => '',
];
// Deep telegram
$deep = (bool) preg_match('/^deep/', $_SERVER['HTTP_HOST']);
$beta = (bool) preg_match('/beta/', $_SERVER['HTTP_HOST']);

// The uri without the query string
$uri = '/'.preg_replace(["/\?.*$/", "/^\//", "/[^\/]*\//"], '', $_SERVER['REQUEST_URI'], 1);
// The method
$method = '/'.strtolower(preg_replace("/.*\//", '', $uri));

// The bot's token
$token = preg_replace(["/^\/bot/", "/^\/file\/bot/", "/\/.*/"], '', $_SERVER['REQUEST_URI']);
$token .= $deep ? '/test' : '';

// The api url with the token
$url = 'https://api.telegram.org/bot'.$token;

// The file url with the token
$file_url = 'https://api.telegram.org/file/bot'.$token;

// The url of this api
$pwrtelegram_api = 'https://'.$_SERVER['HTTP_HOST'].'/';

// The url of the storage
require_once '../storage_url.php';
$pwrtelegram_storage_domain = ($deep ? 'deep' : '').($beta ? 'beta' : '').$pwrtelegram_storage_domain;
$botusername = ($deep ? $deepbotusername : $botusername);

$pwrtelegram_storage = 'https://'.$pwrtelegram_storage_domain.'/';

$REQUEST = $_REQUEST;
/*
foreach ($REQUEST as &$value) {
    $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }, $value);
}
*/
require_once 'src/pwrtelegram/pwrtelegram/Tools.php';
require_once '../db_connect.php';
$tools = new \PWRTelegram\PWRTelegram\Tools();

// If requesting a file
if (preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
    $file_uri = preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
    if ($tools->checkurl($pwrtelegram_storage.$file_uri)) {
        $file_url = $pwrtelegram_storage.$file_uri;
    } else {
        // get my username
        $me = $tools->curl($url.'/getMe')['result']['username'];
        $file_uri = preg_replace(["/^\/file\/bot[^\/]*\//", '/'.$me.'/'], '', $_SERVER['REQUEST_URI']);
        $file_path = '';
        $api_file_path = $file_url.'/'.$file_uri;
        if ($tools->checkurl($api_file_path)) {
            require_once 'db_connect.php';
            $path = str_replace('//', '/', $homedir.'/storage/'.$me.'/'.$file_uri);

            if (!(file_exists($path) && filesize($path) == $tools->curl_get_file_size($api_file_path))) {
                if (!$tools->checkdir(dirname($path))) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
                }
                set_time_limit(0);
                $fp = fopen($path, 'w+');
                $ch = curl_init(str_replace(' ', '%20', $api_file_path));
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                // write curl response to file
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                // get curl response
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            }
            $file_path = $me.'/'.$file_uri;

            if (!file_exists($path)) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file (file does not exist)."];
            }
            $file_size = filesize($path);

            $delete_stmt = $pdo->prepare('DELETE FROM dl WHERE file_path=? AND bot=?;');
            $delete = $delete_stmt->execute([$file_path, $me]);
            $insert_stmt = $pdo->prepare('INSERT INTO dl (file_path, file_size, bot, real_file_path) VALUES (?, ?, ?, ?);');
            $insert = $insert_stmt->execute([$file_path, $file_size, $me, $path]);
        }
        if ($tools->checkurl($pwrtelegram_storage.'/'.$file_path)) {
            $file_url = $pwrtelegram_storage.$file_path;
        } else {
            $file_url = $file_url.'/'.$file_uri;
        }
    }
    header('Location: '.$file_url);
    die();
}

if (isset($REQUEST['chat_id']) && preg_match('/^@/', $REQUEST['chat_id']) && $REQUEST['chat_id'] != '@') {
    require_once 'db_connect.php';
    $usernameresstmt = $pdo->prepare('SELECT id from usernames where username=?;');
    $usernameresstmt->execute([$REQUEST['chat_id']]);
    if ($usernameresstmt->rowCount() == 1) {
        $REQUEST['chat_id'] = $usernameresstmt->fetchColumn();
    } else {
        $result = $tools->curl($url.'/getchat?chat_id='.$REQUEST['chat_id']);
        if ($result['ok'] && isset($result['result']['id']) && isset($result['result']['type'])) {
            if ($result['result']['type'] == 'private') {
                $REQUEST['chat_id'] = $result['result']['id'];
            }
            $pdo->prepare('INSERT INTO usernames (username, id) VALUES (?, ?);')->execute([$REQUEST['chat_id'], $result['result']['id']]);
        } else {
            require_once 'telegram_connect.php';
            $id_result = $GLOBALS['telegram']->exec('resolve_username '.preg_replace('/^@/', '', $REQUEST['chat_id']));
            if (isset($id_result->{'peer_type'}) && isset($id_result->{'peer_id'})) {
                if ($id_result->{'peer_type'} == 'user') {
                    $REQUEST['chat_id'] = $id_result->{'peer_id'};
                } else {
                    $id_result->peer_id = '-100'.(string) $id_result->peer_id;
                }
                $pdo->prepare('INSERT INTO usernames (username, id) VALUES (?, ?);')->execute([$REQUEST['chat_id'], $id_result->{'peer_id'}]);
            }
        }
    }
}
// Else use a nice case switch
switch ($method) {
    case '/getfile':
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        if ($REQUEST['file_id'] == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No file id was provided.']);
        }
        require_once 'src/pwrtelegram/pwrtelegram/API.php';
        $API = new \PWRTelegram\PWRTelegram\API($GLOBALS);
        $tools->jsonexit($API->download($REQUEST['file_id']));
        break;
    case '/getupdates':
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        $limit = '';
        $timeout = '';
        $offset = '';
        if (isset($REQUEST['limit'])) {
            $limit = $REQUEST['limit'];
        }
        if (isset($REQUEST['offset'])) {
            $offset = $REQUEST['offset'];
        }
        if (isset($REQUEST['timeout'])) {
            $timeout = $REQUEST['timeout'];
        }
        $timeout = 1;
        if ($limit == '') {
            $limit = 100;
        }
        $response = $tools->curl($url.'/getUpdates?offset='.$offset.'&timeout='.$timeout);
        if ($response['ok'] == false) {
            $tools->jsonexit($response);
        }
        $onlyme = true;
        $notmecount = 0;
        $todo = '';
        $newresponse['ok'] = true;
        $newresponse['result'] = [];
        foreach ($response['result'] as $cur) {
            if (isset($cur['message']['chat']['id']) && $cur['message']['chat']['id'] == $botusername) {
                if (isset($cur['message']['text']) && preg_match('/^exec_this /', $cur['message']['text'])) {
                    require_once 'db_connect.php';
                    $data = json_decode(preg_replace('/^exec_this /', '', $cur['message']['text']));
                    foreach (array_keys($methods) as $curmethod) {
                        if (isset($cur['message']['reply_to_message'][$curmethod]) && is_array($cur['message']['reply_to_message'][$curmethod])) {
                            $ftype = $curmethod;
                        }
                    }

                    if ($ftype == 'photo') {
                        $file_id = $cur['message']['reply_to_message'][$ftype][0]['file_id'];
                    } else {
                        $file_id = $cur['message']['reply_to_message'][$ftype]['file_id'];
                    }
                    $update_stmt = $pdo->prepare('UPDATE ul SET file_id=?, file_type=? WHERE file_hash=? AND bot=? AND file_name=?;');
                    $update_stmt->execute([$file_id, $ftype, $data->{'file_hash'}, $data->{'bot'}, $data->{'filename'}]);
                }
                if ($onlyme) {
                    $todo = $cur['update_id'] + 1;
                }
            } else {
                $notmecount++;
                if ($notmecount <= $limit) {
                    $newresponse['result'][] = $cur;
                }
                $onlyme = false;
            }
        }
        if ($todo != '') {
            $tools->curl($url.'/getUpdates?offset='.$todo);
        }
        $tools->jsonexit($newresponse);
        break;
    case '/deletemessage':
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        if (isset($REQUEST['inline_message_id']) && $REQUEST['inline_message_id'] != '') {
            $res = $tools->curl($url.'/editMessageText?parse_mode=Markdown&text=_This message was deleted_&inline_message_id='.$REQUEST['inline_message_id']);
        } elseif (isset($REQUEST['message_id']) && isset($REQUEST['chat_id']) && $REQUEST['message_id'] != '' && $REQUEST['chat_id'] != '') {
            $res = $tools->curl($url.'/editMessageText?parse_mode=Markdown&text=_This message was deleted_&message_id='.$REQUEST['message_id'].'&chat_id='.$REQUEST['chat_id']);
        } else {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing required parameters.']);
        }
        if ($res['ok'] == true) {
            $res['result'] = 'The message was deleted successfully.';
        }
        $tools->jsonexit($res);
        break;
    case '/answerinlinequery':
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        if (!(isset($REQUEST['inline_query_id']) && $REQUEST['inline_query_id'] != '')) {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing query id.']);
        }
        if (!(isset($REQUEST['results']) && $REQUEST['results'] != '')) {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing results json array.']);
        }
        $results = json_decode($tools->escapeJsonString($REQUEST['results']), true);
        if ($results == false) {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't decode results json."]);
        }
        $newresults = [];
        foreach ($results as $number => $result) {
            if (!(isset($result[$result['type'].'_file_id']) && $result[$result['type'].'_file_id'] != '')) {
                require_once 'src/pwrtelegram/pwrtelegram/API.php';
                $API = new \PWRTelegram\PWRTelegram\API($GLOBALS);
                if ((!isset($result[$result['type'].'_url']) || $result[$result['type'].'_url'] == '') && isset($_FILES['inline_file'.$number]['error']) && $_FILES['inline_file'.$number]['error'] == UPLOAD_ERR_OK) {
                    // Let's do this!
                    $upload = $API->upload($_FILES['inline_file'.$number]['tmp_name'], $_FILES['inline_file'.$number]['name']);

                    if (isset($upload['result']['file_id']) && $upload['result']['file_id'] != '') {
                        unset($result[$result['type'].'_url']);
                        if ($result['type'] == 'file') {
                            $result['type'] = $upload['result']['file_type'];
                        }
                        $result[$result['type'].'_file_id'] = $upload['result']['file_id'];
                    }
                }
                if (isset($result[$result['type'].'_url']) && $result[$result['type'].'_url'] != '') {
                    $upload = $API->upload($result[$result['type'].'_url']);
                    if (isset($upload['result']['file_id']) && $upload['result']['file_id'] != '') {
                        unset($result[$result['type'].'_url']);
                        if ($result['type'] == 'file') {
                            $result['type'] = $upload['result']['file_type'];
                        }
                        $result[$result['type'].'_file_id'] = $upload['result']['file_id'];
                    }
                }
            }
            $newresults[] = $result;
        }
        $newparams = $REQUEST;
        $newparams['results'] = json_encode($newresults);
        $json = $tools->curl($url.'/answerinlinequery?'.http_build_query($newparams));
        $tools->jsonexit($json);
        break;
    case '/setwebhook':
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        if (isset($REQUEST['url']) && $REQUEST['url'] != '') {
            require_once 'db_connect.php';
            require_once 'src/pwrtelegram/pwrtelegram/API.php';
            $API = new \PWRTelegram\PWRTelegram\API($GLOBALS);
            $me = $tools->curl($url.'/getMe')['result']['username']; // get my username
            $insert_stmt = $pdo->prepare('DELETE FROM hooks WHERE user=?;');
            $insert_stmt->execute([$me]);
            $insert_stmt = $pdo->prepare('INSERT INTO hooks (user, hash) VALUES (?, ?);');
            $insert_stmt->execute([$me, hash('sha256', $REQUEST['url'])]);
            $count = $insert_stmt->rowCount();
            if ($count != 1) {
                $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't insert hook hash into database."]);
            }
            if (isset($_FILES['certificate']['error']) && $_FILES['certificate']['error'] == UPLOAD_ERR_OK) {
                $tools->checkdir($homedir.'/hooks');
                rename($_FILES['certificate']['tmp_file'], $homedir.'/hooks/'.$me.'.pem');
            } else {
                if (file_exists($homedir.'/hooks/'.$me.'.pem')) {
                    unlink($homedir.'/hooks/'.$me.'.pem');
                }
            }
            $hook = ['url' => $pwrtelegram_api.'bot'.$token.'/hook?hook='.urlencode($REQUEST['url'])];
        } else {
            $hook = ['url' => ''];
        }
        $hookresponse = $tools->curl($url.'/setwebhook?'.http_build_query($hook));
        $tools->jsonexit($hookresponse);

        break;
    case '/hook':
        $hook = $_GET['hook'];
        if ($token == '') {
            $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
        }
        $me = $tools->curl($url.'/getMe')['result']['username']; // get my username
        require_once 'db_connect.php';
        $test_stmt = $pdo->prepare('SELECT hash FROM hooks WHERE user=?');
        $test_stmt->execute([$me]);
        $count = $test_stmt->rowCount();
        if ($test_stmt->fetchColumn() == hash('sha256', $hook) && $count == 1) {
            $content = file_get_contents('php://input');
            $cur = json_decode($content, true);
            if (isset($cur['message']['chat']['id']) && $cur['message']['chat']['id'] == $botusername) {
                if (isset($cur['message']['text']) && preg_match('/^exec_this /', $cur['message']['text'])) {
                    require_once 'db_connect.php';
                    $data = json_decode(preg_replace('/^exec_this /', '', $cur['message']['text']));
                    foreach (array_keys($methods) as $curmethod) {
                        if (isset($cur['message']['reply_to_message'][$curmethod]) && is_array($cur['message']['reply_to_message'][$curmethod])) {
                            $ftype = $curmethod;
                        }
                    }
                    if ($ftype == 'photo') {
                        $file_id = $cur['message']['reply_to_message'][$ftype][0]['file_id'];
                    } else {
                        $file_id = $cur['message']['reply_to_message'][$ftype]['file_id'];
                    }
                    $update_stmt = $pdo->prepare('UPDATE ul SET file_id=?, file_type=? WHERE file_hash=? AND bot=? AND file_name=?;');
                    $update_stmt->execute([$file_id, $ftype, $data->{'file_hash'}, $data->{'bot'}, $data->{'filename'}]);
                }
                exit;
            } else {
                $newcur = $cur;
            }
            $data = json_encode($newcur);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $hook);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $parse = parse_url($hook);
            if (isset($parse['scheme']) && $parse['scheme'] == 'https') {
                if (file_exists($homedir.'/hooks/'.$me.'.pem')) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_CAINFO, $homedir.'/hooks/'.$me.'.pem');
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                }
            }
            $result = curl_exec($ch);
            curl_close($ch);
            //error_log('Result of webhook query is '.$result);
            $result = json_decode($result, true);
            if (is_array($result) && isset($result['method']) && $result['method'] != '' && is_string($result['method'])) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_URL, $pwrtelegram_api.'bot'.$token.'/'.$result['method']);
                unset($result['method']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $result);
                $secondresult = curl_exec($ch);
                curl_close($ch);
                //error_log('Reverse webhook command from '.$me.' returned '.$secondresult);
            }
        }
        exit;
        break;
    case '/getchat':
        require_once 'telegram_connect.php';
        $result = $tools->curl($url.'/getchat?'.http_build_query($REQUEST));
        if ($result['ok'] != true && isset($REQUEST['chat_id']) && !preg_match("/^\-100/", $REQUEST['chat_id']) && preg_match('/^[1-9][0-9]*$/', $REQUEST['chat_id'])) {
            $useresult = $GLOBALS['telegram']->exec('user_info user#'.$REQUEST['chat_id']);
            if (isset($useresult->{'peer_id'}) && $useresult->{'peer_id'} == $REQUEST['chat_id']) {
                $newresult = ['ok' => true, 'result' => ['id' => $REQUEST['chat_id'], 'type' => 'private']];
                foreach (['first_name', 'last_name', 'username'] as $key) {
                    if (isset($useresult->{$key}) && $useresult->{$key} != null) {
                        $newresult['result'][$key] = $useresult->{$key};
                    }
                }
                $result = $newresult;
            }
        }
        $tools->jsonexit($result);
        break;
}

// The sending method without the send keyword
$smethod = preg_replace(['|.*/send|', '|.*/upload|'], '', $method);
if (array_key_exists($smethod, $methods)) { // If using one of the send methods
    if ($token == '') {
        $tools->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
    }
    require_once 'src/pwrtelegram/pwrtelegram/API.php';
    $API = new \PWRTelegram\PWRTelegram\API($GLOBALS);
    $name = '';
    $forcename = false;
    $file = '';
    if (isset($_FILES[$smethod]['tmp_name']) && $_FILES[$smethod]['tmp_name'] != '') {
        $name = $_FILES[$smethod]['name'];
        $file = $_FILES[$smethod]['tmp_name'];
        $forcename = true;
    } else {
        if (isset($REQUEST[$smethod])) {
            $file = $REQUEST[$smethod];
        }
    }
    // $file is the file's path/url/id
    if (isset($REQUEST['name']) && $REQUEST['name'] != '') {
        // $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
        $name = $REQUEST['name'];
        $forcename = true;
        // $forcename is the boolean that enables or disables renaming of files
    }
    if (isset($REQUEST['file_name']) && $REQUEST['file_name'] != '') {
        // $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
        $name = $REQUEST['file_name'];
        $forcename = true;
        // $forcename is the boolean that enables or disables renaming of files
    }

    // Let's do this!
    $upload = $API->upload($file, $name, $smethod, $forcename, $REQUEST);
    if (isset($upload['ok']) && $upload['ok'] == true && preg_match('|^/send|', $method)) {
        $params = $REQUEST;
        if (isset($upload['result']['caption']) && $upload['result']['caption'] != '') {
            $params['caption'] = $upload['result']['caption'];
        }
        $params[$upload['result']['file_type']] = $upload['result']['file_id'];
        $tools->jsonexit($tools->curl($url.'/send'.$upload['result']['file_type'].'?'.http_build_query($params)));
    } else {
        $tools->jsonexit($upload);
    }
}

require 'proxy.php';

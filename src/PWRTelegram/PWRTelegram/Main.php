<?php

namespace PWRTelegram\PWRTelegram;

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
class Main extends Proxy
{
    public $pwrhomedir;

    public function __construct($vars)
    {
        foreach ($vars as $key => $val) {
            $this->{$key} = $val;
        }
        // Home dir
        chdir($this->pwrhomedir);
        $this->homedir = realpath($this->pwrhomedir.'/../').'/';
        // Available methods and their equivalent in tg-cli
        $this->methods = [
            'photo'    => 'photo',
            'video'    => 'video',
            'voice'    => 'document',
            'document' => 'document',
            'sticker'  => 'document',
            'audio'    => 'audio',
            'file'     => '',
        ];
        $this->methods_keys = array_keys($this->methods);
        // Deep telegram
        $this->deep = (bool) preg_match('/^deep/', $_SERVER['HTTP_HOST']);
        $this->beta = (bool) preg_match('/beta/', $_SERVER['HTTP_HOST']);

        // The uri without the query string
        $this->uri = '/'.preg_replace(["/\?.*$/", "/^\//", "/[^\/]*\//"], '', $_SERVER['REQUEST_URI'], 1);

        // The method
        $this->method = '/'.strtolower(preg_replace("/.*\//", '', $this->uri));

        // The bot's token
        $this->token = preg_replace(["/^\/bot/", "/^\/file\/bot/", "/\/.*/"], '', $_SERVER['REQUEST_URI']);

        // The url of this api
        $this->pwrtelegram_api = 'https://'.$_SERVER['HTTP_HOST'].'/bot'.$this->token;

        $this->token = $this->token.($this->deep ? '/test' : '');

        // The api url with the token
        $this->url = 'https://api.telegram.org/bot'.$this->token;

        // The file url with the token
        $this->file_url = 'https://api.telegram.org/file/bot'.$this->token;


        // The url of the storage
        $this->pwrtelegram_storage_domain = ($this->deep ? 'deep' : '').($this->beta ? 'beta' : '').$this->pwrtelegram_storage_domain;

        $this->botusername = ($this->deep ? $this->deepbotusername : $this->botusername);

        $this->pwrtelegram_storage = 'https://'.$this->pwrtelegram_storage_domain.'/';

        $this->REQUEST = $_REQUEST;

        /*
        foreach ($this->REQUEST as &$value) {
            $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $value);
        }
        */
    }

    public function run_file()
    {

        // If requesting a file
        if (preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
            $pwrapi_file_url = $this->pwrtelegram_storage.preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
            if ($this->checkurl($pwrapi_file_url)) {
                $this->exit_redirect($pwrapi_file_url);
            }

            // get my username
            $me = $this->curl($this->url.'/getMe')['result']['username'];
            $api_file_path = preg_replace(["/^\/file\/bot[^\/]*/", '/'.$me.'/'], '', $_SERVER['REQUEST_URI']);
            $api_file_url = $this->file_url.$api_file_path;
            $dl_file_path = '';
            if ($this->checkurl($api_file_url)) {
                $storage_path = str_replace('//', '/', $this->homedir.'/storage/'.$me.$api_file_path);
                if (!(file_exists($storage_path) && filesize($storage_path) == $this->curl_get_file_size($api_file_url))) {
                    if (!$this->checkdir(dirname($storage_path))) {
                        $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."]);
                    }
                    set_time_limit(0);
                    $fp = fopen($storage_path, 'w+');
                    $ch = curl_init(str_replace(' ', '%20', $api_file_url));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                    // write curl response to file
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    // get curl response
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                }
                if (!file_exists($storage_path)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file (file does not exist)."]);
                }

                $file_size = filesize($storage_path);
                $dl_file_path = $me.$api_file_path;

                $this->db_connect();
                $this->pdo->prepare('DELETE FROM dl WHERE file_path=? AND bot=?;')->execute([$dl_file_path, $me]);
                $this->pdo->prepare('INSERT INTO dl (file_path, file_size, bot, real_file_path) VALUES (?, ?, ?, ?);')->execute([$dl_file_path, $file_size, $me, $storage_path]);
                if ($this->checkurl($this->pwrtelegram_storage.$dl_file_path)) {
                    $this->exit_redirect($this->pwrtelegram_storage.$dl_file_path);
                }
            }
            $this->exit_redirect($api_file_url);
        }
    }

    public function run_chat_id()
    {
        if (isset($this->REQUEST['chat_id']) && preg_match('/^@/', $this->REQUEST['chat_id']) && $this->REQUEST['chat_id'] != '@') {
            $this->db_connect();
            $usernameresstmt = $this->pdo->prepare('SELECT id from usernames where username=?;');
            $usernameresstmt->execute([$this->REQUEST['chat_id']]);
            if ($usernameresstmt->rowCount() == 1) {
                $this->REQUEST['chat_id'] = $usernameresstmt->fetchColumn();
            } else {
                $id = '';
                $result = $this->curl($this->url.'/getchat?chat_id='.$this->REQUEST['chat_id']);
                if ($result['ok'] && isset($result['result']['id'])) {
                    $id = $result['result']['id'];
                } else {
                    $this->telegram_connect();
                    $id_result = $this->telegram->exec('resolve_username '.preg_replace('/^@/', '', $this->REQUEST['chat_id']));
                    if (isset($id_result->{'peer_type'}) && isset($id_result->{'peer_id'})) {
                        switch ($id_result->peer_type) {
                            case 'user':
                                $id = $id_result->peer_id;
                                break;
                            case 'channel':
                                $id = '-100'.(string) $id_result->peer_id;
                                break;
                            case 'chat':
                                $id = -$id_result->peer_id;
                                break;
                        }
                        $this->peer_type = $id_result->peer_type;
                        $this->peer_id = $id_result->peer_id;
                    }
                }
                if ($id != '') {
                    $this->pdo->prepare('INSERT INTO usernames (username, id) VALUES (?, ?);')->execute([$this->REQUEST['chat_id'], $id]);
                    $this->REQUEST['chat_id'] = $id;
                }
            }
        }
        if (!isset($this->peer_type) && !isset($this->peer_id) && isset($this->REQUEST['chat_id']) && is_numeric($this->REQUEST['chat_id'])) {
            $this->peer_type = 'user';
            $this->peer_id = $this->REQUEST['chat_id'];
            if ($this->REQUEST['chat_id'] < 0) {
                $this->peer_type = 'chat';
                $this->peer_id = -$this->REQUEST['chat_id'];
                if (preg_match('/\-100/', (string) $this->REQUEST['chat_id'])) {
                    $this->peer_type = 'channel';
                    $this->peer_id = preg_replace('/\-100/', '', (string) $this->REQUEST['chat_id']);
                }
            }
        }
    }

    public function run_methods()
    {
        // Else use a nice case switch
        switch ($this->method) {
            case '/getfile':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if ($this->REQUEST['file_id'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No file id was provided.']);
                }
                $this->jsonexit($this->download($this->REQUEST['file_id']));
                break;
            case '/getupdates':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetnotempty($this->REQUEST, 'limit')) {
                    $this->REQUEST['limit'] = 100;
                }
                $response = $this->curl($this->url.'/getUpdates?'.http_build_query($this->REQUEST));
                if (!$response['ok']) {
                    $this->jsonexit($response);
                }
                $notmecount = 0;
                $todo = '';
                $newresponse = ['ok' => true, 'result' => []];
                foreach ($response['result'] as $cur) {
                    if (isset($cur['message']['chat']['id']) && $cur['message']['chat']['id'] == $this->botusername) {
                        $this->handle_my_message($cur);
                        if ($notmecount == 0) {
                            $todo = $cur['update_id'] + 1;
                        }
                    } else {
                        $notmecount++;
                        if ($notmecount <= $this->REQUEST['limit']) {
                            $newresponse['result'][] = $cur;
                        }
                    }
                }
                if ($todo != '') {
                    $this->curl($this->url.'/getUpdates?offset='.$todo);
                }
                $this->jsonexit($newresponse);
                break;
            case '/deletemessage':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (isset($this->REQUEST['inline_message_id']) && $this->REQUEST['inline_message_id'] != '') {
                    $res = $this->curl($this->url.'/editMessageText?parse_mode=Markdown&text=_This message was deleted_&inline_message_id='.$this->REQUEST['inline_message_id']);
                } elseif (isset($this->REQUEST['message_id']) && isset($this->REQUEST['chat_id']) && $this->REQUEST['message_id'] != '' && $this->REQUEST['chat_id'] != '') {
                    $res = $this->curl($this->url.'/editMessageText?parse_mode=Markdown&text=_This message was deleted_&message_id='.$this->REQUEST['message_id'].'&chat_id='.$this->REQUEST['chat_id']);
                } else {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing required parameters.']);
                }
                if ($res['ok']) {
                    $res['result'] = 'The message was deleted successfully.';
                }
                $this->jsonexit($res);
                break;
            case '/answerinlinequery':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!(isset($this->REQUEST['inline_query_id']) && $this->REQUEST['inline_query_id'] != '')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing query id.']);
                }
                if (!(isset($this->REQUEST['results']) && $this->REQUEST['results'] != '')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing results json array.']);
                }
                $results = json_decode($this->REQUEST['results'], true);
                if ($results == false) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't decode results json."]);
                }
                $newresults = [];
                foreach ($results as $number => $result) {
                    if (!(isset($result[$result['type'].'_file_id']) && $result[$result['type'].'_file_id'] != '')) {
                        if ((!isset($result[$result['type'].'_url']) || $result[$result['type'].'_url'] == '') && isset($_FILES['inline_file'.$number]['error']) && $_FILES['inline_file'.$number]['error'] == UPLOAD_ERR_OK) {
                            // Let's do this!
                            $upload = $this->upload($_FILES['inline_file'.$number]['tmp_name'], $_FILES['inline_file'.$number]['name']);

                            if (isset($upload['result']['file_id']) && $upload['result']['file_id'] != '') {
                                unset($result[$result['type'].'_url']);
                                if ($result['type'] == 'file') {
                                    $result['type'] = $upload['result']['file_type'];
                                }
                                $result[$result['type'].'_file_id'] = $upload['result']['file_id'];
                            }
                        }
                        if (isset($result[$result['type'].'_url']) && $result[$result['type'].'_url'] != '') {
                            $upload = $this->upload($result[$result['type'].'_url']);
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
                $newparams = $this->REQUEST;
                $newparams['results'] = json_encode($newresults);
                $this->jsonexit($this->curl($this->url.'/answerinlinequery?'.http_build_query($newparams)));
                break;
            case '/setwebhook':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (isset($this->REQUEST['url']) && $this->REQUEST['url'] != '') {
                    $this->db_connect();
                    $me = $this->curl($this->url.'/getMe')['result']['username']; // get my username
                    $this->pdo->prepare('DELETE FROM hooks WHERE user=?;')->execute([$me]);
                    $insert_stmt = $this->pdo->prepare('INSERT INTO hooks (user, hash) VALUES (?, ?);');
                    $insert_stmt->execute([$me, hash('sha256', $this->REQUEST['url'])]);
                    if ($insert_stmt->rowCount() != 1) {
                        $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't insert hook hash into database."]);
                    }
                    if (isset($_FILES['certificate']['error']) && $_FILES['certificate']['error'] == UPLOAD_ERR_OK) {
                        $this->checkdir($this->homedir.'/hooks');
                        rename($_FILES['certificate']['tmp_file'], $this->homedir.'/hooks/'.$me.'.pem');
                    } else {
                        if (file_exists($this->homedir.'/hooks/'.$me.'.pem')) {
                            unlink($this->homedir.'/hooks/'.$me.'.pem');
                        }
                    }
                    $hook = ['url' => $this->pwrtelegram_api.'/hook?hook='.urlencode($this->REQUEST['url'])];
                } else {
                    $hook = ['url' => ''];
                }
                $hookresponse = $this->curl($this->url.'/setwebhook?'.http_build_query($hook));
                $this->jsonexit($hookresponse);

                break;
            case '/hook':
                $hook = $_GET['hook'];
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $me = $this->curl($this->url.'/getMe')['result']['username']; // get my username
                $this->db_connect();
                $test_stmt = $this->pdo->prepare('SELECT hash FROM hooks WHERE user=?');
                $test_stmt->execute([$me]);
                if ($test_stmt->fetchColumn() == hash('sha256', $hook) && $test_stmt->rowCount() == 1) {
                    $content = file_get_contents('php://input');
                    $cur = json_decode($content, true);
                    if (isset($cur['message']['chat']['id']) && $cur['message']['chat']['id'] == $this->botusername) {
                        $this->handle_my_message($cur);
                        exit;
                    }
                    $data = json_encode($cur);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $hook);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    $parse = parse_url($hook);
                    if (isset($parse['scheme']) && $parse['scheme'] == 'https') {
                        if (file_exists($this->homedir.'/hooks/'.$me.'.pem')) {
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                            curl_setopt($ch, CURLOPT_CAINFO, $this->homedir.'/hooks/'.$me.'.pem');
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
                        curl_setopt($ch, CURLOPT_URL, $this->pwrtelegram_api.'/'.$result['method']);
                        unset($result['method']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $result);
                        curl_exec($ch);
                        //error_log('Reverse webhook command from '.$me.' returned '.curl_exec($ch));
                        curl_close($ch);
                    }
                }
                exit;
                break;
            case '/getchat':
                $result = $this->curl($this->url.'/getchat?'.http_build_query($this->REQUEST));
                $this->telegram_connect();
                $cliresult = $this->telegram->exec($this->peer_type.'_info '.$this->peer_type.'#'.$this->peer_id);
                if (isset($cliresult->{'peer_id'})) {
                    if (!$result['ok']) {
                        $result = ['ok' => true, 'result' => ['id' => $this->REQUEST['chat_id']]];
                    }
                    foreach (['first_name', 'last_name', 'real_first_name', 'real_last_name', 'username', 'type', 'title', 'participants_count', 'kicked_count', 'description', 'online', 'date', 'share_text', 'commands', 'when'] as $key) {
                        if (isset($cliresult->{$key}) && $cliresult->{$key} !== null && !isset($result['result'][$key])) {
                            $result['result'][$key] = ($key == 'type' && $cliresult->type == 'user') ? 'private' : $cliresult->{$key};
                        }
                    }
                }
                $this->jsonexit($result);
                break;
            case '/getbackend':
            case '/getbackcosoh':
                $this->jsonexit($this->curl($this->url.'/getchat?chat_id='.$this->botusername));
                break;
        }

        // The sending method without the send keyword
        $smethod = preg_replace(['|.*/send|', '|.*/upload|'], '', $this->method);
        if (array_key_exists($smethod, $this->methods)) { // If using one of the send methods
            if ($this->token == '') {
                $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
            }
            $name = '';
            $forcename = false;
            $file = '';
            if (isset($_FILES[$smethod]['tmp_name']) && $_FILES[$smethod]['tmp_name'] != '') {
                $name = $_FILES[$smethod]['name'];
                $file = $_FILES[$smethod]['tmp_name'];
                $forcename = true;
            } else {
                if (isset($this->REQUEST[$smethod])) {
                    $file = $this->REQUEST[$smethod];
                }
            }
            // $file is the file's path/url/id
            if (isset($this->REQUEST['name']) && $this->REQUEST['name'] != '') {
                // $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
                $name = $this->REQUEST['name'];
                $forcename = true;
                // $forcename is the boolean that enables or disables renaming of files
            }
            if (isset($this->REQUEST['file_name']) && $this->REQUEST['file_name'] != '') {
                // $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
                $name = $this->REQUEST['file_name'];
                $forcename = true;
                // $forcename is the boolean that enables or disables renaming of files
            }

            // Let's do this!
            $upload = $this->upload($file, $name, $smethod, $forcename, $this->REQUEST);
            if (isset($upload['ok']) && $upload['ok'] && preg_match('|^/send|', $this->method)) {
                $params = $this->REQUEST;
                if (isset($upload['result']['caption']) && $upload['result']['caption'] != '') {
                    $params['caption'] = $upload['result']['caption'];
                }
                $params[$upload['result']['file_type']] = $upload['result']['file_id'];
                $this->jsonexit($this->curl($this->url.'/send'.$upload['result']['file_type'].'?'.http_build_query($params)));
            } else {
                $this->jsonexit($upload);
            }
        }
    }

    public function run()
    {
        $this->run_file();
        $this->run_chat_id();
        $this->run_methods();
        $this->run_proxy();
    }
}

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
    public $full_chat = [];

    public function __destruct()
    {
        if (isset($this->madeline_path)) {
            $this->madeline->API->store_db([], true);
            $this->madeline->API->reset_session();
            \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
        } elseif (isset($this->madeline)) {
            $this->madeline->API->reset_session();
            \danog\MadelineProto\Serialization::serialize($this->deep ? '/tmp/deeppwr.madeline' : '/tmp/pwr.madeline', $this->madeline);
        }
    }

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
        if ($this->token !== '') {
            $madeline_path = '/tmp/pwr_'.$this->get_me()['result']['username'].'_'.hash('sha256', $this->token).'.madeline';
            if (!file_exists($madeline_path)) {
                require 'vendor/autoload.php';
                $madeline = new \danog\MadelineProto\API(['logger' => ['logger' => 1], 'pwr' => ['pwr' => true, 'db_token' => $this->db_token, 'strict' => true]]);
                $madeline->bot_login($this->token);
                $madeline->API->get_updates_difference();
                $madeline->API->store_db([], true);
                $madeline->API->reset_session();
                \danog\MadelineProto\Serialization::serialize($madeline_path, $madeline);
            }
        }
        /*
        foreach ($this->REQUEST as &$value) {
            $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $value);
        }
        */
    }

    public function getprofilephotos($params)
    {
        if (!$this->issetandnotempty($params, 'chat_id')) {
            $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
        }
        $params['user_id'] = $params['chat_id'];
        $res = $this->curl($this->url.'/getUserProfilePhotos?'.http_build_query($params));
        if (!$res['ok']) {
            $this->madeline_connect();
                try {
            $info = $this->full_chat[$this->get_pwr_chat($params['chat_id'])];
                    } catch (\danog\MadelineProto\ResponseException $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\Exception $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\RPCErrorException $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\TL\Exception $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    }
            if (isset($info['photo'])) {
                $meres = $this->get_me()['result']; // get my username
                $me = $meres['username'];

                if (!$this->checkdir($this->homedir.'/ul/'.$me)) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
                }
                $upload = [];
                try {
                    $path = $this->madeline->download_to_dir($info['photo'], $this->homedir.'/ul/'.$me);
                    $upload = $this->upload($path, 'file', '', 'photo');
                    unlink($path);
                } catch (\danog\MadelineProto\ResponseException $e) {
                    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                    error_log($e->getTraceAsString());
                } catch (\danog\MadelineProto\Exception $e) {
                    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                    error_log($e->getTraceAsString());
                } catch (\danog\MadelineProto\RPCErrorException $e) {
                    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                    error_log($e->getTraceAsString());
                } catch (\danog\MadelineProto\TL\Exception $e) {
                    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                    error_log($e->getTraceAsString());
                }

                if (isset($upload['ok']) && $upload['ok']) {
                    $upload = $this->get_finfo($upload['result']['file_id'], true);
                    if (isset($upload['ok']) && $upload['ok']) {
                        if (!$this->issetandnotempty($params, 'offset')) {
                            $params['offset'] = 0;
                        }
                        $res = ['ok' => true, 'result' => ['total_count' => 1, 'photos' => array_slice([$upload['result']['photo']], $params['offset'])]];
                    }
                }
            }
        }

        return $res;
    }

    public function add_to_db($result, $photores = [])
    {
        if (!isset($this->db_token)) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://id.pwrtelegram.xyz/db'.$this->db_token.'/addnewgetchat');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['getchat' => json_encode($result), 'photos' => json_encode($photores)]);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    public function run_file()
    {

        // If requesting a file
        if (preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
            $pwrapi_file_url = $this->pwrtelegram_storage.preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
            if ($this->checkurl($pwrapi_file_url)) {
                $this->exit_redirect($pwrapi_file_url);
            }
        }
    }

    public function run_chat_id()
    {
        foreach (['user_id', 'chat_id', 'from_chat_id'] as $key) {
            if (isset($this->REQUEST[$key]) && preg_match('/^@/', $this->REQUEST[$key]) && $this->REQUEST[$key] != '@') {
                $user_id = json_decode(file_get_contents('https://id.pwrtelegram.xyz/DB/getid?username='.$this->REQUEST[$key]), true);
                if ($user_id['ok']) {
                    $this->REQUEST[$key] = $user_id['result'];
                } else {
                    $id = '';
                    $result = $this->curl($this->url.'/getchat?chat_id='.$this->REQUEST[$key]);
                    if ($result['ok'] && isset($result['result']['id'])) {
                        $id = $result['result']['id'];
                    } else {
                        $this->REQUEST[$key] = $this->get_pwr_chat($this->REQUEST[$key]);
                    }
                }
            }
        }
    }

    public function get_pwr_chat($id)
    {
        if (!isset($this->full_chat[$id])) {
            $this->madeline_connect($this->token);
            $full_chat = $this->madeline->get_pwr_chat($id);
            $this->full_chat[$full_chat['id']] = $full_chat;

            return $full_chat['id'];
        }

        return $this->full_chat[$id]['id'];
    }

    public function run_methods()
    {
        // Else use a nice case switch
        switch ($this->method) {
            case '/getfile':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!isset($this->REQUEST['file_id']) || $this->REQUEST['file_id'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No file id was provided.']);
                }
                $this->jsonexit($this->download($this->REQUEST['file_id']));
                break;
            case '/getupdates':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'limit')) {
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
                $this->REQUEST['parse_mode'] = 'Markdown';
                $this->REQUEST['text'] = '_This message was deleted_';
                $res = $this->curl($this->url.'/editMessageText?'.http_build_query($this->REQUEST));
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
                            $upload = $this->upload($_FILES['inline_file'.$number]['tmp_name'], 'file', $_FILES['inline_file'.$number]['name']);

                            if (isset($upload['result']['file_id']) && $upload['result']['file_id'] != '') {
                                unset($result[$result['type'].'_url']);
                                if ($result['type'] == 'file') {
                                    $result['type'] = $upload['result']['file_type'];
                                }
                                $result[$result['type'].'_file_id'] = $upload['result']['file_id'];
                            }
                        }
                        if (isset($result[$result['type'].'_url']) && $result[$result['type'].'_url'] != '') {
                            $upload = $this->upload($result[$result['type'].'_url'], 'url');
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
                    $me = $this->get_me()['result']['username'];
                    $this->pdo->prepare('DELETE FROM hooks WHERE user=?;')->execute([$me]);
                    $insert_stmt = $this->pdo->prepare('INSERT IGNORE INTO hooks (user, hash) VALUES (?, ?);');
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
                    $hook = ['url' => $this->pwrtelegram_api.'/webhook?hook='.urlencode($this->REQUEST['url'])];
                } else {
                    $hook = ['url' => ''];
                }
                $hookresponse = $this->curl($this->url.'/setwebhook?'.http_build_query($hook));
                $this->jsonexit($hookresponse);

                break;
            case '/getwebhookinfo':
                $hookinfo = $this->curl($this->url.'/getwebhookinfo?'.http_build_query($this->REQUEST));
                if (isset($hookinfo['result']['url']) && $hookinfo['result']['url'] != '') {
                    parse_str(parse_url($hookinfo['result']['url'], PHP_URL_QUERY), $url);
                    $hookinfo['result']['url'] = $url['hook'];
                }
                $this->jsonexit($hookinfo);
            case '/webhook':
            case '/hook':
                $hook = $_GET['hook'];
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $me = $this->get_me()['result']['username']; // get my username
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
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $hook);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
                    echo 'Result of webhook query is '.$result;
                    $result = json_decode($result, true);
                    if (is_array($result) && isset($result['method']) && $result['method'] != '' && is_string($result['method'])) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, $this->pwrtelegram_api.'/'.$result['method']);
                        unset($result['method']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($result));
//                        curl_exec($ch);
                        echo 'Reverse webhook command from '.$me.' returned '.curl_exec($ch);
                        curl_close($ch);
                    }
                } else {
                    $this->jsonexit(['ok' => true, 'error_code' => 400, 'description' => "Couldn't find webhook in database"]);
                }
                exit;
                break;
            case '/getchatbyfile':
                if ($this->REQUEST['file_id'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No file id was provided.']);
                }
                $result = ['user_id' => unpack('V', substr($this->base64url_decode($this->REQUEST['file_id']), 10, 4))[1]];
                $additional = $this->curl($this->url.'/getchat?chat_id='.$result['user_id']);
                if ($additional['ok']) {
                    $result['additional'] = $additional['result'];
                }
                $this->jsonexit(['ok' => true, 'result' => $result]);
            case '/getchat':
                $final_res = [];
                $result = $this->curl($this->url.'/getchat?'.http_build_query($this->REQUEST));
                if (!$result['ok']) {
                    $result = $this->curl($this->url.'/getchat?'.http_build_query($_REQUEST));
                }
                if ($result['ok']) {
                    $final_res = $result['result'];
                }
                $result = json_decode(file_get_contents('https://id.pwrtelegram.xyz/db/getchat?id='.$this->REQUEST['chat_id']), true);
                if ($result['ok']) {
                    $final_res = array_merge($result['result'], $final_res);
                }
                $full = true;
                if (isset($this->REQUEST['full'])) $full = (bool)$this->REQUEST['full'];
                $this->madeline_connect($this->token);
                    try {
                        $this->madeline->peer_isset($this->REQUEST['chat_id']) ? $this->get_pwr_chat($this->REQUEST['chat_id'], $full, true) : $this->get_pwr_chat('@'.$final_res['username'], $full, true);
                    } catch (\danog\MadelineProto\ResponseException $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\Exception $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\RPCErrorException $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    } catch (\danog\MadelineProto\TL\Exception $e) {
                        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                        error_log($e->getTraceAsString());
                    }
                if (isset($this->full_chat[$this->REQUEST['chat_id']])) {
                    $final_res = array_merge($final_res, $this->full_chat[$this->REQUEST['chat_id']]);
                }
                if (isset($final_res['photo'])) {
                    unset($final_res['photo']);
                }
                if (empty($final_res)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Chat not found']);
                }
                $result = ['ok' => true, 'result' => $final_res];
                $this->add_to_db($result, $this->getprofilephotos($this->REQUEST));
                $this->jsonexit($result);
                break;
            case '/getbackend':
            case '/getbackcosoh':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }

                $me = $this->get_me()['result']['username']; // get my peer id

                if (!$this->checkbotuser($me)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."]);
                }
                $this->jsonexit($this->curl($this->url.'/getchat?chat_id='.$this->botusername));
                break;
            case '/getmessage':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'chat_id')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
                }
                $me = $this->get_me()['result']['username']; // get my peer id

                if (!$this->checkbotuser($me)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."]);
                }
                $this->REQUEST['from_chat_id'] = $this->REQUEST['chat_id'];
                $this->REQUEST['chat_id'] = $this->botusername;

                $res = $this->curl($this->url.'/forwardmessage?'.http_build_query($this->REQUEST));
                if ($res['ok']) {
                    $res['result']['from'] = $res['result']['forward_from'];
                    $res['result']['date'] = $res['result']['forward_date'];
                    $chat_info = $this->curl($this->url.'/getChat?chat_id='.$this->REQUEST['from_chat_id']);
                    if ($chat_info['ok']) {
                        $res['result']['chat'] = $chat_info['result'];
                    }
                    if (isset($res['result']['forward_from_chat'])) {
                        unset($res['result']['forward_from_chat']);
                    }
                    unset($res['result']['forward_from']);
                    unset($res['result']['forward_date']);
                }
                $this->jsonexit($res);
                break;
            case '/unsub2broadcasts':
            case '/sub2broadcasts':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'chat_id')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
                }
                if (!$this->curl($this->url.'/getchat?chat_id='.$this->REQUEST['chat_id'])['ok']) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Invalid chat_id provided.']);
                }
                $me = $this->get_me()['result']['username']; // get my peer id
                if (!$this->issetandnotempty($this->REQUEST, 'namespace')) {
                    $this->REQUEST['namespace'] = $me.'.all';
                }
                $namespace = explode('.', str_replace(' ', '', $this->REQUEST['namespace']));
                if (count($namespace) !== 2 || $namespace[0] != $me) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Invalid namespace provided.']);
                }
                $this->REQUEST['namespace'] = implode('.', $namespace);
                $this->REQUEST['subbed'] = true;
                if ($this->method == '/unsub2broadcasts') {
                    $this->REQUEST['subbed'] = false;
                }
                $this->db_connect();
                $res = ['ok' => true, 'result' => true];
                try {
                    $this->pdo->prepare('DELETE FROM broadcast WHERE namespace=? AND chat_id=?')->execute([$this->REQUEST['namespace'], $this->REQUEST['chat_id']]);
                    $this->pdo->prepare('INSERT IGNORE INTO broadcast (namespace, chat_id, subbed) VALUES (?, ?, ?)')->execute([$this->REQUEST['namespace'], $this->REQUEST['chat_id'], $this->REQUEST['subbed']]);
                } catch (Exception $e) {
                    $res = ['ok' => false, 'error_code' => 400, 'description' => 'An error occurred while inserting the chat_id into the database.'];
                }
                $this->jsonexit($res);
                break;
            case '/sendchataction':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $duration = 5;
                if ($this->issetandnotempty($this->REQUEST, 'duration') && is_numeric($this->REQUEST['duration'])) {
                    $duration = (int) $this->REQUEST['duration'];
                    unset($this->REQUEST['duration']);
                }
                $res = $this->curl($this->url.'/sendchataction?'.http_build_query($this->REQUEST));
                $duration -= 5;
                if ($duration > 0 && $res['ok']) {
                    shell_exec('bash '.escapeshellarg($this->pwrhomedir.'/sendchataction.sh').' '.escapeshellarg($duration).' '.escapeshellarg($this->url.'/sendchataction?'.http_build_query($this->REQUEST)).'  > /dev/null 2>/dev/null &');
                }
                $this->jsonexit($res);
                break;
            case '/getprofilephotos':
                $this->jsonexit($this->getprofilephotos($this->REQUEST));
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
            $type = '';
            if (isset($_FILES[$smethod]['tmp_name']) && $_FILES[$smethod]['tmp_name'] != '') {
                $name = $_FILES[$smethod]['name'];
                $file = $_FILES[$smethod]['tmp_name'];
                $forcename = true;
                $type = 'file';
            } else {
                if (isset($this->REQUEST[$smethod])) {
                    $file = $this->REQUEST[$smethod];
                    $type = 'url';
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
            $upload = $this->upload($file, $type, $name, $smethod, $forcename, $this->REQUEST);
            if (isset($upload['ok']) && $upload['ok'] && preg_match('|^/send|', $this->method)) {
                $params = $this->REQUEST;
/*
                if (isset($upload['result']['caption']) && $upload['result']['caption'] != '') {
                    $params['caption'] = $upload['result']['caption'];
                }
*/
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

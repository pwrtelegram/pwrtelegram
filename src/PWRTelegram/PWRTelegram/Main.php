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
        if (isset($this->madeline) && is_object($this->madeline)) {
            $this->madeline->API->store_db([], true);
            $this->madeline->API->reset_session();
            \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
        }
        if (isset($this->madeline_backend) && is_object($this->madeline_backend)) {
            $this->madeline_backend->API->reset_session();
            \danog\MadelineProto\Serialization::serialize($this->madeline_backend_path, $this->madeline_backend);
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
        $this->method_normal = '/'.preg_replace("/.*\//", '', $this->uri);
        $this->method = strtolower($this->method_normal);

        // The bot's token
        $this->real_token = preg_replace(["/^\/bot/", "/^\/user/", "/^\/file\/bot/", "/\/.*/"], '', $_SERVER['REQUEST_URI']);

        // The url of this api
        $this->pwrtelegram_api = 'https://'.$_SERVER['HTTP_HOST'].'/bot'.$this->real_token;

        $this->token = $this->real_token.($this->deep ? '/test' : '');

        $exploded = explode(':', $this->real_token);
        if (count($exploded) == 2) {
            $this->bot_id = basename($exploded[0]);
        }

        // The api url with the token
        $this->url = 'https://api.telegram.org/bot'.$this->token;

        // The file url with the token
        $this->file_url = 'https://api.telegram.org/file/bot'.$this->token;

        // The url of the storage
        $this->pwrtelegram_storage_domain = ($this->deep ? 'deep' : '').($this->beta ? 'beta' : '').$this->pwrtelegram_storage_domain;

        $this->pwrtelegram_storage = 'https://'.$this->pwrtelegram_storage_domain.'/';

        $this->REQUEST = $_REQUEST;

        $default_backend = $this->deep ? $this->homedir.'/sessions/deeppwr.madeline' : $this->homedir.'/sessions/pwr.madeline';
        $this->user = preg_match("/^\/user/", $_SERVER['REQUEST_URI']);

        if ($this->real_token == '') {
            $this->madeline_backend_path = $default_backend;
            $this->madeline_path = $default_backend;
        } else {
            if ($this->user) {
                if (isset($this->bot_id)) {
                    $this->madeline_backend_path = $this->homedir.'/sessions/pwruser_'.$this->bot_id.'_'.hash('sha256', $this->real_token).'.madeline';
                    $this->madeline_path = $this->madeline_backend_path;
                    ini_set('error_log', '/tmp/'.$this->bot_id.'.log');
                    $this->backend_id = $this->bot_id;
                } else {
                    $this->madeline_backend_path = $this->homedir.'/sessions/pwrusertemp_'.hash('sha256', $this->real_token).'.madeline';
                    $this->madeline_path = $this->madeline_backend_path;
                }
            } else {
                $this->madeline_path = $this->homedir.'/sessions/pwr_'.$this->bot_id.'_'.hash('sha256', $this->real_token).'.madeline';
                $this->madeline_backend_path = $this->homedir.'/sessions/pwrbackend_'.$this->get_me()['result']['username'].'.madeline';
                ini_set('error_log', '/tmp/'.$this->bot_id.'.log');
                if (!file_exists($this->madeline_backend_path)) {
                    $this->madeline_backend_path = '';
                } else {
                    $this->backend_id = preg_replace(['|.*pwruser_|', '|_.*|'], '', readlink($this->madeline_backend_path));
                }
            }
        }
        if (!file_exists($this->madeline_path) && $this->real_token !== '') {
            if ($this->user) {
                $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Invalid user token provided']);
            }
            $this->get_me();
            require 'vendor/autoload.php';
            $madeline = new \danog\MadelineProto\API(['logger' => ['logger' => 1], 'pwr' => ['pwr' => true, 'db_token' => $this->db_token, 'strict' => true]]);
            $madeline->bot_login($this->token);
            $madeline->API->get_updates_difference();
            $madeline->API->store_db([], true);
            $madeline->API->reset_session();
            \danog\MadelineProto\Serialization::serialize($this->madeline_path, $madeline);
        }
        if ($this->real_token !== '' && $this->user) {
            $this->madeline_connect();
        }
    }

    public function getprofilephotos($params)
    {
        if ($this->real_token === '') {
            return [];
        }
        if (!$this->issetandnotempty($params, 'chat_id')) {
            $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
        }
        $params['user_id'] = $params['chat_id'];
        $res = $this->curl($this->url.'/getUserProfilePhotos?'.http_build_query($params));
        if (!$res['ok']) {
            $this->madeline_connect();
            try {
                $info = $this->full_chat[$this->get_pwr_chat($params['chat_id'])];
                $res = ['ok' => true, 'result' => ['total_count' => 1, 'photos' => [[$info['photo']]]]];
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
        }

        return $res;
    }

    public function getchat($params)
    {
        if (!isset($params['chat_id'])) {
            $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No chat_id provided']);
        }
        $final_res = ['madeline' => false];
        $result = $this->curl($this->url.'/getchat?'.http_build_query($params));
        if (!$result['ok']) {
            $result = $this->curl($this->url.'/getchat?'.http_build_query($_REQUEST));
        }
        if ($result['ok']) {
            $final_res = $result['result'];
        }
        $result = json_decode(file_get_contents('https://id.pwrtelegram.xyz/db/getchat?id='.$params['chat_id']), true);
        if ($result['ok']) {
            $final_res = array_merge($result['result'], $final_res);
        }
        $full = true;
        if (isset($params['full'])) {
            $full = (bool) $params['full'];
        }
        $this->madeline_connect();
        try {
            $this->madeline->peer_isset($params['chat_id']) ? $this->get_pwr_chat($params['chat_id'], $full, true) : $this->get_pwr_chat('@'.$final_res['username'], $full, true);
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
        if (isset($this->full_chat[$params['chat_id']])) {
            $final_res = array_merge($final_res, $this->full_chat[$params['chat_id']]);
            $final_res['madeline'] = true;
        }
        if (isset($final_res['photo'])) {
            unset($final_res['photo']);
        }
        if (empty($final_res)) {
            $result = ['ok' => false, 'error_code' => 400, 'description' => 'Chat not found'];
        } else {
            $result = ['ok' => true, 'result' => $final_res];
        }

        return $result;
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
        if ($this->real_token === '' || $this->user) {
            return false;
        }
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
                if ($user_id['ok'] && !$this->user) {
                    $this->REQUEST[$key] = $user_id['result'];
                } else {
                    $result = (!$this->user && $this->real_token !== '') ? $this->curl($this->url.'/getchat?chat_id='.$this->REQUEST[$key]) : ['ok' => false];
                    if ($result['ok'] && isset($result['result']['id'])) {
                        $this->REQUEST[$key] = $result['result']['id'];
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
            $this->madeline_connect();
            $full_chat = $this->madeline->get_pwr_chat($id);
            $this->full_chat[$full_chat['id']] = $full_chat;

            return $full_chat['id'];
        }

        return $this->full_chat[$id]['id'];
    }

    public function run_methods()
    {
        if ($this->real_token === '' && !in_array($this->method, ['/phonelogin', '/getchat'])) {
            $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'The only method that can be called without authorization is getChat and phoneLogin']);
        }
        if ($this->user && isset($this->bot_id)) {
            switch ($this->method) {
                case '/getchat':
                $result = $this->getchat($this->REQUEST);
                $this->add_to_db($result, []);
                $this->jsonexit($result);

                case '/upload':
                $this->jsonexit(['ok' => true, 'result' => $this->madeline->upload($_FILES['file']['tmp_name'], $_FILES['file']['name'])]);

                case '/getupdates':
                $updates = $this->utf8ize($this->madeline->API->get_updates($this->REQUEST));
                $this->jsonexit(['ok' => true, 'result' => $updates], JSON_UNESCAPED_UNICODE);

                case '/enablegetupdates':
                $this->madeline->API->settings['pwr']['update_handler'] = $this->madeline->API->settings['updates']['callback'];
                $this->jsonexit(['ok' => true, 'result' => true]);

                case '/disablegetupdates':
                unset($this->madeline->API->settings['pwr']['update_handler']);
                $this->jsonexit(['ok' => true, 'result' => true]);

                case '/restartworker':
                case '/setwebhook':
                if (isset($this->REQUEST['url']) && $this->REQUEST['url'] != '') {
                    $this->stop_worker();
                    if (isset($_FILES['certificate']['error']) && $_FILES['certificate']['error'] == UPLOAD_ERR_OK) {
                        $this->checkdir($this->homedir.'/hooks');
                        rename($_FILES['certificate']['tmp_file'], $this->homedir.'/hooks/'.$this->bot_id.'.pem');
                        $this->madeline->API->pem_path = $this->homedir.'/hooks/'.$this->bot_id.'.pem';
                    } else {
                        if (file_exists($this->homedir.'/hooks/'.$this->bot_id.'.pem')) {
                            if (isset($this->madeline->API->pem_path)) {
                                unset($this->madeline->API->pem_path);
                            }

                            unlink($this->homedir.'/hooks/'.$this->bot_id.'.pem');
                        }
                    }
                    $this->madeline->API->hook_url = $this->REQUEST['url'];
                    $this->madeline->API->settings['pwr']['update_handler'] = [$this->madeline->API, 'pwr_webhook'];
                    $this->madeline->API->store_db([], true);
                    $this->madeline->API->reset_session();
                    \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
                    $this->start_worker();
                    $this->jsonexit(['ok' => true, 'result' => true]);
                }

                case '/deletewebhook':
                    $this->stop_worker();
                    unset($this->madeline->API->hook_url);
                    unset($this->madeline->API->settings['pwr']['update_handler']);
                    if (isset($this->madeline->API->pem_path)) {
                        unset($this->madeline->API->pem_path);
                    }
                    $this->jsonexit(['ok' => true, 'result' => true]);

                default:
                foreach ($this->REQUEST as &$param) {
                    $json = json_decode($param, true);
                    if (is_array($json)) {
                        $param = $json;
                    }
                }
                $method = str_replace(['/', '->'], ['', '.'], $this->method_normal);
                if ($method == 'auth.logOut') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing method to call.']);
                }
                $this->jsonexit(['ok' => true, 'result' => $this->utf8ize($this->madeline->API->method_call($method, $this->REQUEST))]);
            }
        }
        // Else use a nice case switch
        switch ($this->method) {
            case '/phonelogin':
                if (!isset($this->REQUEST['phone']) || $this->REQUEST['phone'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No phone number was provided.']);
                }
                require 'vendor/autoload.php';
                $this->real_token = $this->base64url_encode(\phpseclib\Crypt\Random::string(32));
                $this->madeline_path = $this->homedir.'/sessions/pwrusertemp_'.hash('sha256', $this->real_token).'.madeline';
                $madeline = new \danog\MadelineProto\API(['logger' => ['logger' => 1], 'pwr' => ['pwr' => true, 'db_token' => $this->db_token, 'strict' => true]]);
                $madeline->API->settings['pwr']['update_handler'] = $madeline->API->settings['updates']['callback'];
                $madeline->phone_login($this->REQUEST['phone']);
                \danog\MadelineProto\Serialization::serialize($this->madeline_path, $madeline);
                $this->jsonexit(['ok' => true, 'result' => $this->real_token]);
                break;
            case '/completephonelogin':
                if (!isset($this->REQUEST['code']) || $this->REQUEST['code'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No verification code was provided.']);
                }
                $authorization = $this->madeline->complete_phone_login($this->REQUEST['code']);
                if ($authorization['_'] === 'account.noPassword') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => '2FA is enabled but no password is set.']);
                }
                if ($authorization['_'] === 'account.password') {
                    \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
                    $this->jsonexit(['ok' => false, 'error_code' => 401, 'description' => '2FA is enabled: call the complete2FALogin method with the password as password parameter (hint: '.$authorization['hint'].')']);
                }
                if ($authorization['_'] === 'account.needSignup') {
                    \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
                    $this->jsonexit(['ok' => false, 'error_code' => 401, 'description' => 'Need to signup: call the completesignup method.']);
                }
                $this->real_token = $authorization['user']['id'].':'.$this->real_token;
                unlink($this->madeline_path);
                $this->madeline_backend_path = $this->homedir.'/sessions/pwruser_'.$authorization['user']['id'].'_'.hash('sha256', $this->real_token).'.madeline';
                $this->madeline_path = $this->madeline_backend_path;
                $this->madeline->API->get_updates_difference();
                $this->madeline->API->store_db([], true);
                $this->madeline->API->reset_session();
                $this->jsonexit(['ok' => true, 'result' => $this->real_token]);
            case '/completesignup':
                if (!isset($this->REQUEST['first_name']) || $this->REQUEST['first_name'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No first name was provided.']);
                }
                $authorization = $this->madeline->complete_signup($this->REQUEST['first_name'], isset($this->REQUEST['last_name']) ? $this->REQUEST['last_name'] : '');
                $this->real_token = $authorization['user']['id'].':'.$this->real_token;
                unlink($this->madeline_path);
                $this->madeline_backend_path = $this->homedir.'/sessions/pwruser_'.$authorization['user']['id'].'_'.hash('sha256', $this->real_token).'.madeline';
                $this->madeline_path = $this->madeline_backend_path;
                $this->madeline->API->get_updates_difference();
                $this->madeline->API->store_db([], true);
                $this->madeline->API->reset_session();
                $this->jsonexit(['ok' => true, 'result' => $this->real_token]);
            case '/complete2falogin':
                if (!isset($this->REQUEST['password']) || $this->REQUEST['password'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No password was provided.']);
                }
                $authorization = $this->madeline->complete_2fa_login($this->REQUEST['password']);
                $this->real_token = $authorization['user']['id'].':'.$this->real_token;
                unlink($this->madeline_path);
                $this->madeline_backend_path = $this->homedir.'/sessions/pwruser_'.$authorization['user']['id'].'_'.hash('sha256', $this->real_token).'.madeline';
                $this->madeline_path = $this->madeline_backend_path;
                $this->madeline->API->get_updates_difference();
                $this->madeline->API->store_db([], true);
                $this->madeline->API->reset_session();
                $this->jsonexit(['ok' => true, 'result' => $this->real_token]);
            case '/setbackend':
                if (!isset($this->REQUEST['backend_token']) || $this->REQUEST['backend_token'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No verification backend token was provided.']);
                }
                $bot_id = basename(explode(':', $this->REQUEST['backend_token'])[0]);
                $backend_session = $this->homedir.'/sessions/pwruser_'.$bot_id.'_'.hash('sha256', $this->REQUEST['backend_token']).'.madeline';
                if (!file_exists($backend_session)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 404, 'description' => 'User not found']);
                }
                $dest = $this->homedir.'/sessions/pwrbackend_'.$this->get_me()['result']['username'].'.madeline';
                $this->try_unlink($dest);
                $result = symlink($backend_session, $dest);
                $this->jsonexit(['ok' => $result, 'result' => $result]);

            case '/getfile':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!isset($this->REQUEST['file_id']) || $this->REQUEST['file_id'] == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No file id was provided.']);
                }
                $this->jsonexit($this->download($this->REQUEST['file_id']));
                break;
/*
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
                $this->madeline_connect_backend();
                foreach ($response['result'] as $cur) {
                    if (isset($cur['message']['chat']['id']) && $cur['message']['chat']['id'] == $this->madeline_backend->API->datacenter->authorization['user']['id']) {
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
*/
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
                case '/setmtprotowebhook':
                if (isset($this->REQUEST['url']) && $this->REQUEST['url'] != '') {
                    $this->stop_worker();
                    if (isset($_FILES['certificate']['error']) && $_FILES['certificate']['error'] == UPLOAD_ERR_OK) {
                        $this->checkdir($this->homedir.'/hooks');
                        rename($_FILES['certificate']['tmp_file'], $this->homedir.'/hooks/'.$this->bot_id.'.pem');
                        $this->madeline->API->pem_path = $this->homedir.'/hooks/'.$this->bot_id.'.pem';
                    } else {
                        if (file_exists($this->homedir.'/hooks/'.$this->bot_id.'.pem')) {
                            if (isset($this->madeline->API->pem_path)) {
                                unset($this->madeline->API->pem_path);
                            }

                            unlink($this->homedir.'/hooks/'.$this->bot_id.'.pem');
                        }
                    }
                    $this->madeline->API->hook_url = $this->REQUEST['url'];
                    $this->madeline->API->settings['pwr']['update_handler'] = [$this->madeline->API, 'pwr_webhook'];
                    $this->madeline->API->store_db([], true);
                    $this->madeline->API->reset_session();
                    \danog\MadelineProto\Serialization::serialize($this->madeline_path, $this->madeline);
                    $this->start_worker();
                    $this->jsonexit(['ok' => true, 'result' => true]);
                }

                case '/deletemtprotowebhook':
                    $this->stop_worker();
                    unset($this->madeline->API->hook_url);
                    unset($this->madeline->API->settings['pwr']['update_handler']);
                            if (isset($this->madeline->API->pem_path)) {
                                unset($this->madeline->API->pem_path);
                            }
                    $this->jsonexit(['ok' => true, 'result' => true]);
            case '/getmtprotowebhookinfo':
                $this->jsonexit(['ok' => true, 'result' => ['url' => (isset($this->madeline->API->hook_url) ? $this->madeline->API->hook_url : ''), 'has_custom_certificate' => isset($this->madeline->API->pem_path)]]);

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
//                $this->madeline_connect_backend();
                $test_stmt = $this->pdo->prepare('SELECT hash FROM hooks WHERE user=?');
                $test_stmt->execute([$me]);
                if ($test_stmt->fetchColumn() == hash('sha256', $hook) && $test_stmt->rowCount() == 1) {
                    $content = file_get_contents('php://input');
                    $cur = json_decode($content, true);
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
                $result = $this->getchat($this->REQUEST);
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
                $this->jsonexit($this->curl($this->url.'/getchat?chat_id='.$this->get_backend_id()));
                break;
            case '/getmessage':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'chat_id')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
                }
                $this->jsonexit($this->get_message($this->REQUEST));
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
                $getchat = $this->getchat($this->REQUEST);
                $result = $this->getprofilephotos($this->REQUEST);
                $this->add_to_db($getchat, $result);
                $this->jsonexit($result);
                break;
            case '/addchatuser':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'chat_id')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing chat_id.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'user_id')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing user_id.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'fwd_limit')) {
                    $this->REQUEST['fwd_limit'] = 0;
                }
                $this->madeline_connect();
                $final_res = [];
                $result = $this->curl($this->url.'/getchat?chat_id='.$this->REQUEST['user_id']);
                if (!$result['ok']) {
                    $result = $this->curl($this->url.'/getchat?chat_id='.$_REQUEST['user_id']);
                }
                if ($result['ok']) {
                    $final_res = $result['result'];
                }
                $result = json_decode(file_get_contents('https://id.pwrtelegram.xyz/db/getchat?id='.$this->REQUEST['user_id']), true);
                if ($result['ok']) {
                    $final_res = array_merge($result['result'], $final_res);
                }
                $full = false;
                    try {
                        $this->madeline->peer_isset($this->REQUEST['user_id']) ? $this->get_pwr_chat($this->REQUEST['user_id'], $full, true) : $this->get_pwr_chat('@'.$final_res['username'], $full, true);
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
                if (isset($this->full_chat[$this->REQUEST['user_id']])) {
                    $final_res = array_merge($final_res, $this->full_chat[$this->REQUEST['user_id']]);
                }
                if (empty($final_res)) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Chat not found']);
                }
                $result = ['ok' => true, 'result' => $final_res];
                $this->add_to_db($result, $this->getprofilephotos($this->REQUEST));
                $this->REQUEST['chat_id'] = -$this->REQUEST['chat_id'];
                $this->jsonexit(['ok' => true, 'result' => $this->madeline->API->method_call('messages.addChatUser', $this->REQUEST)]);
                break;
            case '/madeline':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!$this->issetandnotempty($this->REQUEST, 'method')) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing method to call.']);
                }
                $this->madeline_connect();
                $params = [];
                if (isset($this->REQUEST['params'])) {
                    $params = json_decode($this->REQUEST['params'], true);
                    if ($params === null) {
                        $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Could not parse parameters.']);
                    }
                }
                $method = str_replace('->', '.', $this->REQUEST['method']);
                if ($method == 'auth.logOut') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Missing method to call.']);
                }
                $this->jsonexit(['ok' => true, 'result' => $this->madeline->API->method_call($method, $params)]);
                break;
            case '/sendmessage':
                if (!isset($this->REQUEST['mtproto']) || !$this->REQUEST['mtproto']) {
                    $this->run_proxy();
                    die;
                }
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                if (!isset($this->REQUEST['chat_id'])) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No chat_id was provided.']);
                }
                if (!isset($this->REQUEST['text'])) {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No text was provided.']);
                }
                $this->madeline_connect();
                if (isset($this->REQUEST['reply_markup'])) {
                    $this->REQUEST['reply_markup'] = json_decode($this->REQUEST['reply_markup'], true);
                    if ($this->REQUEST['reply_markup'] === null) {
                        $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Could not parse reply markup.']);
                    }
                }
                $this->jsonexit(['ok' => true, 'result' => $this->madeline->API->method_call('messages.sendMessage', $this->REQUEST, ['botAPI' => true])]);
                break;

                case '/upload':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $this->madeline_connect();
                $this->jsonexit(['ok' => true, 'result' => $this->madeline->upload($_FILES['file']['tmp_name'], $_FILES['file']['name'])]);

                case '/getmtprotoupdates':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $this->madeline_connect();
                $updates = $this->utf8ize($this->madeline->API->get_updates($this->REQUEST));
                $this->jsonexit(['ok' => true, 'result' => $updates], JSON_UNESCAPED_UNICODE);

                case '/enablegetmtprotoupdates':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $this->madeline_connect();
                $this->madeline->API->settings['pwr']['update_handler'] = $this->madeline->API->settings['updates']['callback'];
                $this->jsonexit(['ok' => true, 'result' => true]);

                case '/disablegetmtprotoupdates':
                if ($this->token == '') {
                    $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'No token was provided.']);
                }
                $this->madeline_connect();
                unset($this->madeline->API->settings['pwr']['update_handler']);
                $this->jsonexit(['ok' => true, 'result' => true]);
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

    public function get_message($params)
    {
        $me = $this->get_me()['result']['username']; // get my peer id
        if (!$this->checkbotuser($me)) {
            $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."]);
        }
        $this->REQUEST['from_chat_id'] = $this->REQUEST['chat_id'];
        $this->REQUEST['chat_id'] = $this->get_backend_id();

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

        return $res;
    }
}

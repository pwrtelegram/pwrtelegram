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
class API extends Tools
{
    public function __construct($params)
    {
        foreach ($params as $key => $val) {
            $this->{$key} = $val;
        }
    }

    public function db_connect()
    {
        if (!isset($this->pdo)) {
            $this->pdo = new \PDO($this->deep ? $this->deepdb : $this->db, $this->deep ? $this->deepdbuser : $this->dbuser, $this->deep ? $this->deepdbpassword : $this->dbpassword);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        }
    }

    public function telegram_connect()
    {
        if (!isset($this->telegram)) {
            require_once $this->pwrhomedir.'/vendor/autoload.php';
            $this->telegram = new \Zyberspace\Telegram\Cli\Client(['homedir' => $this->homedir, 'pwrhomedir' => $this->pwrhomedir, 'botusername' => $this->botusername], $this->deep);
        }
    }

    public function madeline_connect()
    {
        if (!isset($this->madeline)) {
            require_once $this->pwrhomedir.'/vendor/autoload.php';
            $this->madeline = \danog\MadelineProto\Serialization::deserialize($this->deep ? '/tmp/deeppwr.madeline' : '/tmp/pwr.madeline');
        }
    }

    /**
     * Download given file id and return json with error or downloaded path.
     *
     * @param $file_id - The file id of the file to download
     *
     * @return json with error or file path
     */
    public function download($file_id, $assume_timeout = true)
    {
        $result = null;
        if ($_SERVER['HTTP_HOST'] != $this->pwrtelegram_storage_domain && $assume_timeout) {
            $storage_params = [];
            foreach (['url', 'methods', 'methods_keys', 'token', 'pwrtelegram_storage', 'pwrtelegram_storage_domain', 'file_url'] as $key) {
                $storage_params[$key] = $this->{$key};
            }
            $storage_params['file_id'] = $file_id;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, $this->pwrtelegram_storage);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($storage_params));
            $result = curl_exec($ch);
            $result = json_decode($result, true);
            curl_close($ch);
            if ($result == null) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_URL, $this->pwrtelegram_storage);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($storage_params));
                $result = curl_exec($ch);
                $result = json_decode($result, true);
                curl_close($ch);
            }
            if ($result == null) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file: result is null."];
            }

            return $result;
        }
        $me = $this->get_me()['result']['username']; // get my username
        $this->db_connect();
        $selectstmt = $this->pdo->prepare('SELECT * FROM dl_new WHERE file_id=? AND bot=? LIMIT 1;');
        $selectstmt->execute([$file_id, $me]);
        $select = $selectstmt->fetch(\PDO::FETCH_ASSOC);

        if ($selectstmt->rowCount() == '1' && $this->checkurl($this->pwrtelegram_storage.$select['file_path'])) {
            $newresponse['ok'] = true;
            $newresponse['result']['file_id'] = $select['file_id'];
            $newresponse['result']['file_path'] = $select['file_path'];
            $newresponse['result']['file_size'] = $select['file_size'];

            return $newresponse;
        }
        $this->pdo->prepare('DELETE FROM dl_new WHERE file_id=? AND bot=?;')->execute([$file_id, $me]);
        unset($this->pdo);
        $path = '';

            $this->madeline_connect();
            if (!$this->checkbotuser($me)) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
            }
            $info = $this->get_finfo($file_id);

            if ($info['ok'] == false) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't forward file to download user."];
            }
            if ($info['message_id'] == '') {
                return ['ok' => false, 'error_code' => 400, 'description' => 'Reply message id is empty.'];
            }
            $file_type = $info['file_type'];
/*
            $cmd = $me.' '.$file_id.' '.$this->methods[$info['file_type']];
            if (file_exists('/tmp/'.$cmd)) {
                return ['ok' => true, 'error_code' => 202, 'description' => 'File is currently being downloaded. Please try again later.'];
            }
*/
            $result = $this->curl($this->url.'/sendMessage?reply_to_message_id='.$info['message_id'].'&chat_id='.$this->botusername.'&text='.$file_id);
            if ($result['ok'] == false) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't send file id."];
            }
            $result = $this->madeline->messages->searchGlobal(['offset_peer' => '@'.$me, 'q' => $file_id, 'offset_date' => 0, 'offset_id' => 0, 'limit' => 1]);

            if (!isset($result['messages'][0]['reply_to_msg_id'])) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file, search failed."];
            }
            $result = $this->madeline->messages->getMessages(['id' => [$result['messages'][0]['reply_to_msg_id']]]);
            if (!isset($result['messages'][0]['media'])) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file, error getting message."];
            }
            $media = $result['messages'][0]['media'];
            $info = $this->madeline->get_download_info($media);

        $file_size = $info['size'];
        $file_name = $info['name'].$info['ext'];
        $file_path = $me.'/'.$file_type.'/'.$file_name;
        $newresponse['ok'] = true;
        $newresponse['result']['file_id'] = $file_id;
        $newresponse['result']['file_path'] = $file_path;
        $newresponse['result']['file_size'] = $file_size;
        $this->db_connect();
        $this->pdo->prepare('INSERT IGNORE INTO dl_new (file_id, file_path, file_size, bot, location, mime) VALUES (?, ?, ?, ?, ?, ?);')->execute([$file_id, $file_path, $file_size, $me, json_encode($info['InputFileLocation']), $info['mime']]);

        return $newresponse;
    }

    /**
     * Gets info from file id.
     *
     * @param $file_id - The file id to recognize
     *
     * @return json with error or file info
     */
    public function get_finfo($file_id, $full_photo = false)
    {
        $me = $this->get_me()['result']['username']; // get my peer id

        if (!$this->checkbotuser($me)) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
        }
        $result = ['ok' => false];
        $count = 0;
        while (!$result['ok'] && $count < count($this->methods_keys)) {
            $result = $this->curl($this->url.'/send'.$this->methods_keys[$count].'?chat_id='.$this->botusername.'&'.$this->methods_keys[$count].'='.$file_id);
            $count++;
        }
        $count--;
        if ($full_photo) {
            return $result;
        }
        foreach ($this->methods_keys as $curmethod) {
            if (isset($result['result'][$curmethod]) && is_array($result['result'][$curmethod])) {
                $method = $curmethod;
            }
        }
        if ($result['ok']) {
            $result['message_id'] = $result['result']['message_id'];
            $result['file_type'] = $method;
            $result['file_id'] = $file_id;
            if ($result['file_type'] == 'photo') {
                $result['file_size'] = $result['result'][$method][0]['file_size'];
                if (isset($result['result'][$method][0]['file_name'])) {
                    $result['file_name'] = $result['result'][$method][0]['file_name'];
                }
            } else {
                if (isset($result['result'][$method]['file_name'])) {
                    $result['file_name'] = $result['result'][$method]['file_name'];
                }
                if (isset($result['result'][$method]['file_size'])) {
                    $result['file_name'] = $result['result'][$method]['file_size'];
                }
            }
            unset($result['result']);
        }

        return $result;
    }

    /**
     * Upload given file/URL/file id.
     *
     * @param $file - The file/URL/file to upload
     * @param $name - The file name to use when uploading, can be empty
     * (in this case the file name will be obtained from the given file path/URL)
     * @param $type - The type of file to use when uploading:
     * can be document, photo, audio, voice, sticker, file or empty
     * (in this case the type will default to file)
     * @param $forcename - Boolean, enables or disables file name forcing, defaults to false
     * If set to false the file name to be stored in the database will be set to empty and the
     * associated file id will be reused the next time a file with the same hash and with $forcename set to false is sent.
     *
     * @return json with error or file id
     */
    // public function upload($file, $uploadata = array()) {
    public function upload($file, $whattype = 'url', $name = '', $type = '', $forcename = false, $oldparams = [])
    {
        if ($file == '') {
            return ['ok' => false, 'error_code' => 400, 'description' => 'No file specified.'];
        }
        if ($name == '') {
            $file_name = basename($file);
        } else {
            $file_name = basename($name);
        }
        if (!array_key_exists($type, $this->methods)) {
            $type = '';
        }
        if ($type == '') {
            $type = 'file';
        }
        if ($forcename == '') {
            $forcename = false;
        }
        if ($forcename) {
            $name = basename($name);
        } else {
            $name = '';
        }

        $this->db_connect();
        $this->telegram_connect();
        $meres = $this->get_me()['result']; // get my username
        $me = $meres['username'];
        $mepeer = $meres['id'];

        if (!$this->checkdir($this->homedir.'/ul/'.$me)) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
        }
        $path = $this->homedir.'ul/'.$me.'/'.$file_name;
        if (file_exists($file) && $whattype == 'file') {
            $size = filesize($file);
            if ($size < 1) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download the file (file size is 0)."];
            }
            if ($size > 1610612736) {
                return ['ok' => false, 'error_code' => 400, 'description' => 'File too big.'];
            }
            if (!rename($file, $path)) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't rename file."];
            }
        } elseif (filter_var($file, FILTER_VALIDATE_URL) && $whattype == 'url') {
            if (preg_match('|^http(s)?://'.$this->pwrtelegram_storage_domain.'/|', $file)) {
                $select_stmt = $this->pdo->prepare('SELECT * FROM dl_new WHERE file_path=? AND bot=?;');
                $select_stmt->execute([preg_replace('|^http(s)?://'.$this->pwrtelegram_storage_domain.'/|', '', $file), $me]);
                $fetch = $select_stmt->fetch(\PDO::FETCH_ASSOC);
                $count = $select_stmt->rowCount();
                if ($count > 0 && isset($fetch['file_id']) && $fetch['file_id'] != '') {
                    $select_stmt = $this->pdo->prepare('SELECT * FROM ul WHERE file_id=? AND bot=?;');
                    $select_stmt->execute([$fetch['file_id'], $me]);
                    $info = $select_stmt->fetch(\PDO::FETCH_ASSOC);
                    $count = $select_stmt->rowCount();
                    if ($count > 0 && isset($info['file_id']) && $info['file_id'] != '' && isset($info['file_type']) && $info['file_type'] != '' && isset($info['file_name'])) {
                        if ($type == 'file') {
                            $type = $info['file_type'];
                        }
                        if ($type == $info['file_type'] && $name == $info['file_name']) {
                            return $info;
                        }
                    }
                }
            }
            shell_exec('wget -qQ 1610612736 -O '.escapeshellarg($path).' '.escapeshellarg($file));
            if (!file_exists($path)) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file."];
            }
            $size = filesize($path);
            if ($size < 1) {
                $this->try_unlink($path);

                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file (file size is 0)."];
            }
            if ($size > 1610612736) {
                $this->try_unlink($path);

                return ['ok' => false, 'error_code' => 400, 'description' => 'File too big.'];
            }
        } elseif (!preg_match('/[^A-Za-z0-9\-\_]/', $file) && $whattype == 'url') {
            $info = $this->get_finfo($file);
            if ($info['ok'] != true) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't get info from file id."];
            }
            if ($info['file_type'] == '') {
                return ['ok' => false, 'error_code' => 400, 'description' => 'File type is empty.'];
            }
            if ($type == 'file') {
                $type = $info['file_type'];
            }
            if ($type != $info['file_type'] || $name != '') {
                $downloadres = $this->download($file, false);
                if (!(isset($downloadres['result']['file_path']) && $downloadres['result']['file_path'] != '')) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file from file id."];
                }
                if ($name == '') {
                    $name = basename($downloadres['result']['file_path']);
                }
                $path = $this->homedir.'/ul/'.$me.'/'.$name;
                shell_exec('wget -qQ 1610612736 -O '.escapeshellarg($path).' '.escapeshellarg($this->pwrtelegram_storage.$downloadres['result']['file_path']));
                if (!file_exists($path)) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file."];
                }
                $size = filesize($path);
                if ($size < 1) {
                    $this->try_unlink($path);

                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file (file size is 0)."];
                }
                if ($size > 1610612736) {
                    $this->try_unlink($path);

                    return ['ok' => false, 'error_code' => 400, 'description' => 'File too big.'];
                }
            } else {
                return ['ok' => true, 'result' => $info];
            }
        } else {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't use the provided file id/URL."];
        }
        if (!file_exists($path)) {
            error_log($path." wasn't downloaded ".$file);

            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file."];
        }

        if ($type == 'file') {
            $mime = '';
            $ext = '';
            try {
                $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                $mediaInfoContainer = $mediaInfo->getInfo($path);
                $mime = $mediaInfoContainer->getGeneral()->get('internet_media_type');
                $audio = $mediaInfoContainer->getGeneral()->get('count_of_audio_streams');
            } catch (\RuntimeException $e) {
            }
            if ($mime == '') {
                $mime = mime_content_type($path);
            }
            $pathinfo = pathinfo($path);
            if (isset($pathinfo['extension']) && $pathinfo['extension'] != '') {
                $ext = $pathinfo['extension'];
            }
            if (preg_match('/^image\/.*/', $mime) && preg_match('/png|jpeg|jpg|bmp|tif/', $ext)) {
                $type = 'photo';
            } elseif (preg_match('/^video\/.*/', $mime) && 'mp4' == $ext && $audio > 0) {
                $type = 'video';
            } elseif ($ext == 'webp') {
                $type = 'sticker';
            } elseif (preg_match('/^audio\/.*/', $mime) && preg_match('/mp3|flac/', $ext)) {
                $type = 'audio';
            } elseif (preg_match('/^audio\/ogg/', $mime)) {
                $type = 'voice';
            } else {
                $type = 'document';
            }
        }
        $animated = false;
        $newparams = [];
        switch ($type) {
            case 'voice':
            case 'audio':
                try {
                    $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                    $mediaInfoContainer = $mediaInfo->getInfo($path);
                    $general = $mediaInfoContainer->getGeneral();
                    foreach (['performer' => 'performer', 'track_name' => 'title', 'duration' => 'duration'] as $orig => $param) {
                        try {
                            $tmpget = $general->get($orig);
                            if (is_object($tmpget)) {
                                if ($orig == 'duration') {
                                    $newparams[$param] = ceil($tmpget->getMilliseconds() / 1000);
                                    continue;
                                }
                                $newparams[$param] = $tmpget->__toString();
                            }
                        } catch (\Exception $e) {
                        }
                    }
                } catch (\RuntimeException $e) {
                }
                if (!isset($newparams['duration'])) {
                    $newparams['duration'] = shell_exec('ffprobe -show_format '.escapeshellarg($path)." 2>&1 | sed -n '/duration/s/.*=//p;s/\..*//g'  | sed 's/\..*//g' | tr -d '\n'");
                }
                break;
            case 'video':
                try {
                    $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                    $mediaInfoContainer = $mediaInfo->getInfo($path);
                    $general = $mediaInfoContainer->getGeneral();
                    foreach (['width' => 'width', 'height' => 'height', 'duration' => 'duration'] as $orig => $param) {
                        try {
                            $tmpget = $general->get($orig);
                            if (is_object($tmpget)) {
                                if ($orig == 'duration') {
                                    $newparams[$param] = ceil($tmpget->getMilliseconds() / 1000);
                                    continue;
                                }
                                $newparams[$param] = $tmpget->__toString();
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    $video = $mediaInfoContainer->getVideos();
                    foreach (['width' => 'width', 'height' => 'height'] as $orig => $param) {
                        try {
                            $tmpget = $video[0]->get($orig);
                            if (is_object($tmpget)) {
                                $newparams[$param] = $tmpget->getAbsoluteValue();
                            }
                        } catch (\Exception $e) {
                        }
                    }
                } catch (\RuntimeException $e) {
                }
                if (!isset($newparams['duration'])) {
                    $newparams['duration'] = shell_exec('ffprobe -show_format '.escapeshellarg($path)." 2>&1 | sed -n '/duration/s/.*=//p;s/\..*//g'  | sed 's/\..*//g' | tr -d '\n'");
                }
                break;
            case 'document':
                try {
                    $animated = (new \Mhor\MediaInfo\MediaInfo())->getInfo($path)->getGeneral()->get('count_of_audio_streams') == 0;
                } catch (\RuntimeException $e) {
                }
                break;
        }
        $newparams['caption'] = '';
        foreach ($newparams as $param => &$val) {
            if (isset($oldparams[$param]) && $oldparams[$param] != '') {
                $val = $oldparams[$param];
            }
        }
        $file_hash = hash_file('sha256', $path);
        unset($this->pdo);
        $this->db_connect();
        $select_stmt = $this->pdo->prepare('SELECT * FROM ul WHERE file_hash=? AND file_type=? AND bot=? AND file_name=?;');
        $select_stmt->execute([$file_hash, $type, $me, $name]);
        $fetch = $select_stmt->fetch(\PDO::FETCH_ASSOC);
        $file_id = $fetch['file_id'];

        $count = $select_stmt->rowCount();

        if ($file_id == '') {
            if (!$this->checkbotuser($me)) {
                $this->try_unlink($path);

                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
            }
            if ($size < 52428800) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:multipart/form-data']);
                curl_setopt($ch, CURLOPT_URL, $this->url.'/send'.$type.'?'.http_build_query($newparams));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['chat_id' => $this->botusername, $type => new \CURLFile($path)]);
                $result = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if ($result['ok']) {
                    foreach ($this->methods_keys as $curmethod) {
                        if (isset($result['result'][$curmethod]) && is_array($result['result'][$curmethod])) {
                            $type = $curmethod;
                        }
                    }
                    if ($type == 'photo') {
                        $fetch = end($result['result'][$type]);
                    } else {
                        $fetch = $result['result'][$type];
                    }
                    $file_id = $fetch['file_id'];
                }
            }
            unset($this->pdo);

            if ($file_id != '') {
                $this->try_unlink($path);
                $this->db_connect();
                $insert_stmt = $this->pdo->prepare('DELETE FROM ul WHERE file_id=? AND file_hash=? AND file_type=? AND bot=? AND file_name=? AND file_size=?;');
                $insert_stmt->execute([$file_id, $file_hash, $type, $me, $name, $size]);
                $insert_stmt = $this->pdo->prepare('INSERT INTO ul (file_id, file_hash, file_type, bot, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?);');
                $insert_stmt->execute([$file_id, $file_hash, $type, $me, $name, $size]);
                $count = $insert_stmt->rowCount();
                if ($count != '1') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't store data into database."];
                }
            } else {
                $this->madeline_connect();
                $inputFile = $this->madeline->upload($path);
                $mime = mime_content_type($path);
                $this->try_unlink($path);
                switch ($type) {
                    case 'photo':
                    $mtype = 'photo';
                    $media = ['_' => 'inputMediaUploadedPhoto', 'file' => $inputFile, 'mime_type' => $mime, 'caption' => $newparams['caption']];
                    break;

                    case 'document':
                    $attributes = $animated ? [['_' => 'documentAttributeAnimated']] : [];
                    break;

                    case 'video':
                    $attributes = [['_' => 'documentAttributeVideo', 'duration' => $newparams['duration'], 'w' => $newparams['width'], 'h' => $newparams['height']]];
                    break;

                    case 'voice':
                    case 'audio':
                    $attributes = [array_merge(['_' => 'documentAttributeAudio', 'duration' => $newparams['duration'], 'voice' => $type == 'voice'], $newparams)];
                    break;
                }
                if (!isset($media)) {
                    $mtype = 'document';
                    $attributes[] = ['_' => 'documentAttributeFilename', 'file_name' => $file_name];
                    $media = ['_' => 'inputMediaUploadedDocument', 'file' => $inputFile, 'mime_type' => $mime, 'attributes' => $attributes, 'caption' => $newparams['caption']];
                }
                $peer = 'user#'.$mepeer;
                $payload = 'exec_this '.json_encode(['file_hash' => $file_hash, 'bot' => $me, 'filename' => $name]);
                $result = $this->madeline->messages->sendMessage(['peer' => $peer, 'message' => $payload]);
                if (!isset($result['id'])) {
                    return ['ok' => false, 'error_code' => 400, 'description' => 'Message id of text message is empty.'];
                }

                $result = $this->madeline->messages->sendMedia(['peer' => $peer, 'media' => $media, 'reply_to_msg_id' => $result['id']]);
                $this->db_connect();
                $insert_stmt = $this->pdo->prepare('DELETE FROM ul WHERE file_hash=? AND bot=? AND file_name=? AND file_size=?;');
                $insert_stmt->execute([$file_hash, $me, $name, $size]);
                $insert_stmt = $this->pdo->prepare('INSERT INTO ul (file_hash, bot, file_name, file_size) VALUES (?, ?, ?, ?);');
                $insert_stmt->execute([$file_hash, $me, $name, $size]);
                $count = $insert_stmt->rowCount();
                if ($count != '1') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't store data into database."];
                }
                $this->curl($this->pwrtelegram_api.'/getupdates');
                $select_stmt = $this->pdo->prepare('SELECT * FROM ul WHERE file_hash=? AND bot=? AND file_name=?;');
                $select_stmt->execute([$file_hash, $me, $name]);
                $fetch = $select_stmt->fetch(\PDO::FETCH_ASSOC);
                $file_id = $fetch['file_id'];
                $type = $fetch['file_type'];
                if ($file_id == '') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't get file id. This error can be fixed by running getupdates (only trough the PWRTelegram API) and processing messages before sending another file, or if you're using webhooks remaking the setwebhook request to the PWRTelegram API."];
                }
            }
            if ($file_id == '') {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't get file id."];
            }
        } else {
            $this->try_unlink($path);
            $size = $fetch['file_size'];
        }
        $res = ['ok' => true, 'result' => ['file_size' => $size, 'file_type' => $type, 'file_id' => $file_id]];
/*
        if (isset($newparams['caption']) && $newparams['caption'] != '') {
            $res['result']['caption'] = $newparams['caption'];
        }
*/
        return $res;
    }
}

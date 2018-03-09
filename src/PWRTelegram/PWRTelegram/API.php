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
    public function db_connect()
    {
        if (!isset($this->pdo)) {
            $this->pdo = new \PDO($this->deep ? $this->deepdb : $this->db, $this->deep ? $this->deepdbuser : $this->dbuser, $this->deep ? $this->deepdbpassword : $this->dbpassword);
            $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        }
    }

    public function madeline_connect()
    {
        if (!isset($this->madeline)) {
            require_once $this->pwrhomedir.'/vendor/autoload.php';

            try {
                $this->madeline = new \danog\MadelineProto\API($this->madeline_path, ['logger' => ['logger_level' => 5], 'connection_settings' => ['all' => ['protocol' => 'tcp_abridged']]]);
                if (is_object($this->madeline)) {
                    //if (!$this->check_worker()) {
                    //    $this->start_worker();
                    //}

                    return $this->madeline;
                }
            } catch (\danog\MadelineProto\Exception $e) {
                error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                error_log($e->getTraceAsString());
            } catch (\danog\MadelineProto\RPCErrorException $e) {
                error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                error_log($e->getTraceAsString());
            } catch (\Error $e) {
                error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
                error_log($e->getTraceAsString());
            }
            error_log('RELOGIN');
            if ($this->user) {
                $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Please login again.']);
            }
            $this->madeline = new \danog\MadelineProto\API(['logger' => ['logger' => 1, 'logger_level' => 5], 'pwr' => ['pwr' => true, 'db_token' => $this->db_token, 'strict' => true], 'app_info' => ['api_id' => 6, 'api_hash' => 'eb06d4abfb49dc3eeb1aeb98ae0f581e'], 'connection_settings' => ['all' => ['protocol' => 'tcp_abridged', 'test_mode' => $this->deep]]]);
            $this->madeline->bot_login($this->real_token);
            $this->madeline->API->get_updates_difference();
            $this->madeline->API->store_db([], true);
            $this->madeline->session = $this->madeline_path;
        }
    }

    public function madeline_connect_backend()
    {
        if (!isset($this->madeline_backend)) {
            if (!file_exists($this->madeline_backend_path)) {
                $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Set a custom backend to use the PWRTelegram API. Instructions available @ https://pwrtelegram.xyz']);
            }
            require_once $this->pwrhomedir.'/vendor/autoload.php';

            $this->madeline_backend = new \danog\MadelineProto\API($this->madeline_backend_path, ['logger' => ['logger_level' => 5], 'connection_settings' => ['all' => ['protocol' => 'tcp_abridged']]]);
            if ($this->madeline_backend === false) {
                $this->jsonexit(['ok' => false, 'error_code' => 400, 'description' => 'Reset the custom backend to use the PWRTelegram API. Instructions available @ https://pwrtelegram.xyz']);
            }
        }
    }

    /**
     * Download given file id and return json with error or downloaded path.
     *
     * @param $file_id - The file id of the file to download
     *
     * @return json with error or file path
     */
    public function download($file_id, $assume_timeout = false)
    {
        $me = $this->get_me()['result']['id'];
        $this->db_connect();
        $selectstmt = $this->pdo->prepare('SELECT * FROM dl WHERE file_id=? AND bot=? LIMIT 1;');
        $selectstmt->execute([$file_id, $me]);
        $select = $selectstmt->fetch(\PDO::FETCH_ASSOC);
        if ($selectstmt->rowCount() === 1 && $this->checkurl($this->pwrtelegram_storage.$select['file_path'])) {
            $newresponse['ok'] = true;
            $newresponse['result']['file_id'] = $select['file_id'];
            $newresponse['result']['file_path'] = $select['file_path'];
            $newresponse['result']['file_size'] = $select['file_size'];

            return $newresponse;
        }
        $this->pdo->prepare('DELETE FROM dl WHERE file_id=? AND bot=?;')->execute([$file_id, $me]);
        unset($this->pdo);
        $path = '';

        if (!$this->checkbotuser()) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
        }
        $info = $this->get_finfo($file_id);
        if ($info['ok'] == false) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't forward file to download user."];
        }
        $file_type = $info['file_type'];
        $file_size = $info['file_size'];
        $file_name = $info['file_name'];
        $file_path = $me.'/'.$file_type.'/'.$file_name;
        $newresponse['ok'] = true;
        $newresponse['result']['file_id'] = $file_id;
        $newresponse['result']['file_path'] = $file_path;
        $newresponse['result']['file_size'] = $file_size;
        $this->db_connect();
        $this->pdo->prepare('INSERT IGNORE INTO dl (file_id, file_path, file_size, bot, mime) VALUES (?, ?, ?, ?, ?);')->execute([$file_id, $file_path, $file_size, $me, $info['mime_type']]);

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

        if (!$this->checkbotuser()) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
        }
        $result = ['ok' => false];
        $count = 0;
        $this->madeline_connect();
        $parsed = $this->madeline->API->unpack_file_id($file_id);
        if (in_array($parsed['type'], ['photo', 'thumbnail'])) {
            $name = $parsed['MessageMedia']['photo']['sizes'][0]['location']['volume_id'].$parsed['MessageMedia']['photo']['sizes'][0]['location']['secret'].$parsed['MessageMedia']['photo']['sizes'][0]['location']['local_id'];
            $botAPIres = $this->curl($this->url.'/getfile?file_id='.$file_id)['result'];

            return ['ok' => true, 'file_type' => 'photo', 'file_size' => $botAPIres['file_size'], 'mime_type' => 'image/jpeg', 'file_id' => $file_id, 'file_name' => 'thumb'.$name.'.jpg'];
        } else {
            $result = $this->madeline->messages->sendMedia(['peer' => $this->get_backend_id(), 'media' => ['_' => 'inputMediaDocument', 'id' => ['_' => 'inputDocument', 'id' => $parsed['MessageMedia']['document']['id'], 'access_hash' => $parsed['MessageMedia']['document']['access_hash']], 'caption' => ''], 'message' => '']);
        }
        $result = ['ok' => true, 'result' => $this->madeline->API->MTProto_to_botAPI(end($result['updates'])['message']['media'])];
        if ($full_photo) {
            return $result;
        }

        return $this->parse_finfo($result);
    }

    public function parse_finfo($result)
    {
        foreach ($this->methods_keys as $curmethod) {
            if (isset($result['result'][$curmethod]) && is_array($result['result'][$curmethod])) {
                $method = $curmethod;
            }
        }
        if ($result['ok']) {
            $result['file_type'] = $method;
            if ($result['file_type'] == 'photo') {
                $result['file_size'] = $result['result'][$method][0]['file_size'];
                if (isset($result['result'][$method][0]['file_name'])) {
                    $result['file_name'] = $result['result'][$method][0]['file_name'];
                    $result['file_id'] = $result['result'][$method][0]['file_id'];
                }
            } else {
                if (isset($result['result'][$method]['file_name'])) {
                    $result['file_name'] = $result['result'][$method]['file_name'];
                }
                if (isset($result['result'][$method]['file_size'])) {
                    $result['file_size'] = $result['result'][$method]['file_size'];
                }
                if (isset($result['result'][$method]['mime_type'])) {
                    $result['mime_type'] = $result['result'][$method]['mime_type'];
                }
                $result['file_id'] = $result['result'][$method]['file_id'];
            }
            if (!isset($result['mime_type'])) {
                $result['mime_type'] = 'application/octet-stream';
            }
            if (!isset($result['file_name'])) {
                $result['file_name'] = $result['file_id'].($method === 'sticker' ? '.webp' : '');
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

        $meres = $this->get_me()['result']; // get my username
        $me = $meres['username'];

        if (!$this->checkdir($this->homedir.'/ul/'.$me)) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
        }
        $this->madeline_connect();

        $path = $this->homedir.'ul/'.$me.'/'.$this->madeline->base64url_encode($this->madeline->random(64));
        if (file_exists($file) && $whattype == 'file') {
            $size = filesize($file);
            if ($size < 1) {
                return ['ok' => false, 'error_code' => 400, 'description' => 'Size of uploaded file is 0.'];
            }
            if ($size > 1610612736) {
                return ['ok' => false, 'error_code' => 400, 'description' => 'File too big.'];
            }
            /*if (!rename($file, $path)) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't rename file."];
            }*/
            $path = $file;
        } elseif (filter_var($file, FILTER_VALIDATE_URL) && $whattype == 'url') {
            if (preg_match('|^http(s)?://'.$this->pwrtelegram_storage_domain.'/|', $file)) {
                $this->db_connect();
                $select_stmt = $this->pdo->prepare('SELECT * FROM dl WHERE file_path=? AND bot=?;');
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

            unset($this->pdo);
            shell_exec('wget -r -np -nc --restrict-file-names=nocontrol -qQ '.(1610612736).' -O '.escapeshellarg($path).' '.escapeshellarg($file));
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
                shell_exec('wget -r -np -nc --restrict-file-names=nocontrol -qQ 1610612736 -O '.escapeshellarg($path).' '.escapeshellarg(str_replace('%2F', '/', $this->pwrtelegram_storage.urlencode($downloadres['result']['file_path']))));
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
            } catch (\Error $e) {
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
            } elseif (preg_match('/^audio\/.*/', $mime) || preg_match('/mp3|flac/', $ext)) {
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
                            if (is_object($tmpget) || is_string($tmpget)) {
                                if ($orig == 'duration') {
                                    $newparams[$param] = ceil($tmpget->getMilliseconds() / 1000);
                                    continue;
                                }
                                $newparams[$param] = $tmpget;
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
            case 'video_note':
            case 'video':
                try {
                    $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                    $mediaInfoContainer = $mediaInfo->getInfo($path);
                    $general = $mediaInfoContainer->getGeneral();
                    foreach (['width' => 'width', 'height' => 'height', 'duration' => 'duration'] as $orig => $param) {
                        try {
                            $tmpget = $general->get($orig);
                            if (is_object($tmpget) || is_string($tmpget) || is_numeric($tmpget)) {
                                if ($orig == 'duration') {
                                    $newparams[$param] = ceil($tmpget->getMilliseconds() / 1000);
                                    continue;
                                }
                                $newparams[$param] = is_object($tmpget) ? $tmpget->getAbsoluteValue() : $tmpget;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    $video = $mediaInfoContainer->getVideos();
                    foreach (['width' => 'width', 'height' => 'height'] as $orig => $param) {
                        try {
                            $tmpget = $video[0]->get($orig);
                            if (is_object($tmpget) || is_string($tmpget) || is_numeric($tmpget)) {
                                $newparams[$param] = is_object($tmpget) ? $tmpget->getAbsoluteValue() : $tmpget;
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
                    $a = (new \Mhor\MediaInfo\MediaInfo())->getInfo($path)->getGeneral();
                    if (is_object($a)) {
                        $animated = $a->get('count_of_audio_streams') == 0;
                    }
                } catch (\Exception $e) {
                } catch (\danog\MadelineProto\Exception $e) {
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
        $this->db_connect();
        $select_stmt = $this->pdo->prepare('SELECT * FROM ul WHERE file_hash=? AND file_type=? AND bot=? AND file_name=?;');
        $select_stmt->execute([$file_hash, $type, $me, $name]);
        $fetch = $select_stmt->fetch(\PDO::FETCH_ASSOC);
        $file_id = $fetch['file_id'];

        $count = $select_stmt->rowCount();
        unset($this->pdo);

        if ($file_id == '') {
            if (!$this->checkbotuser()) {
                $this->try_unlink($path);

                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't initiate chat."];
            }

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
                $inputFile = $this->madeline->upload($path, '', [$this, 'upload_callback']);
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

                    case 'video_note':
                    if ($newparams['width'] !== $newparams['height']) {
                        $newparams['width'] = $newparams['height'];
                    }
                    $attributes = [['_' => 'documentAttributeVideo', 'duration' => $newparams['duration'], 'w' => $newparams['width'], 'h' => $newparams['height'], 'round_message' => true, 'supports_streaming' => true]];
                    break;

                    case 'video':
                    $attributes = [['_' => 'documentAttributeVideo', 'duration' => $newparams['duration'], 'w' => $newparams['width'], 'h' => $newparams['height'], 'supports_streaming' => true]];
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
                $result = $this->madeline->messages->uploadMedia(['peer' => ['_' => 'inputPeerSelf'], 'media' => $media]);
                $result = $this->parse_finfo(['ok' => true, 'result' => $this->madeline->API->MTProto_to_botAPI($result)]);
                $file_id = $result['file_id'];
                $type = $result['file_type'];
                $this->db_connect();
                $insert_stmt = $this->pdo->prepare('DELETE FROM ul WHERE file_hash=? AND bot=? AND file_name=? AND file_size=?;');
                $insert_stmt->execute([$file_hash, $me, $name, $size]);
                $insert_stmt = $this->pdo->prepare('INSERT INTO ul (file_hash, bot, file_name, file_size, file_type, file_id) VALUES (?, ?, ?, ?, ?, ?);');
                $insert_stmt->execute([$file_hash, $me, $name, $size, $type, $file_id]);
                $count = $insert_stmt->rowCount();
                if ($count != '1') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't store data into database."];
                }
                if ($file_id == '') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't get file id.."];
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

    public function upload_callback($percent)
    {
        \danog\MadelineProto\Logger::log(['Upload status: '.$percent.'%'], \danog\MadelineProto\Logger::NOTICE);
        if (isset($this->REQUEST['upload_callback'])) {
            var_dump(file_get_contents($this->REQUEST['upload_callback'].$percent));
        }
    }
}

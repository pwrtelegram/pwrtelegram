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

    public function connect_db()
    {
        $this->pdo = new \PDO($this->deep ? $this->deepdb : $this->db, $this->deep ? $this->deepdbuser : $this->dbuser, $this->deep ? $this->deepdbpassword : $this->dbpassword);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function telegram_connect()
    {
        if (!isset($this->telegram)) {
            require_once $this->pwrhomedir.'/vendor/autoload.php';
            $this->telegram = new \Zyberspace\Telegram\Cli\Client($this->deep);
        }
    }

    /**
     * Download given file id and return json with error or downloaded path.
     *
     * @param $file_id - The file id of the file to download
     *
     * @return json with error or file path
     */
    public function download($file_id)
    {
        $result = null;
        if ($_SERVER['HTTP_HOST'] != $this->pwrtelegram_storage_domain) {
            $storage_params = [];
            foreach (['url', 'methods', 'token', 'pwrtelegram_storage', 'pwrtelegram_storage_domain', 'file_url'] as $key) {
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
        $meres = $this->curl($this->url.'/getMe')['result']; // get my username
        $me = $meres['username'];
        $mepeer = $meres['id'];
        $this->connect_db();
        $selectstmt = $this->pdo->prepare('SELECT * FROM dl WHERE file_id=? AND bot=? LIMIT 1;');
        $selectstmt->execute([$file_id, $me]);
        $select = $selectstmt->fetch(\PDO::FETCH_ASSOC);

        if ($selectstmt->rowCount() == '1' && $this->checkurl($this->pwrtelegram_storage.$select['file_path'])) {
            $newresponse['ok'] = true;
            $newresponse['result']['file_id'] = $select['file_id'];
            $newresponse['result']['file_path'] = $select['file_path'];
            $newresponse['result']['file_size'] = $select['file_size'];

            return $newresponse;
        }
        set_time_limit(0);
        $path = '';
        $result = $this->curl($this->url.'/getFile?file_id='.$file_id);
        if (isset($result['result']['file_path']) && $result['result']['file_path'] != '' && $this->checkurl($this->file_url.$result['result']['file_path'])) {
            $file_path = $result['result']['file_path'];
            $path = str_replace('//', '/', $this->homedir.'/'.($this->deep ? 'deep' : '').'storage/'.$me.'/'.$file_path);
            $dl_url = $this->file_url.$file_path;
            if (!(file_exists($path) && filesize($path) == $this->curl_get_file_size($dl_url))) {
                if (!$this->checkdir(dirname($path))) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
                }
                set_time_limit(0);
                $fp = fopen($path, 'w+');
                $ch = curl_init(str_replace(' ', '%20', $dl_url));
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                // write curl response to file
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                // get curl response
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            }
            $file_path = $me.'/'.$file_path;
        }

        if (!file_exists($path)) {
            $this->telegram_connect();
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
            $cmd = $me.' '.$file_id.' '.$this->methods[$info['file_type']];
            if (preg_match('/'.$cmd.'/', shell_exec('ps aux | grep -v grep')) == true) {
                return ['ok' => true, 'error_code' => 202, 'description' => 'File is already being downloaded. Please try again later.'];
            }
            $result = $this->curl($this->url.'/sendMessage?reply_to_message_id='.$info['message_id'].'&chat_id='.$this->botusername.'&text='.$file_id);
            if ($result['ok'] == false) {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't send file id."];
            }
            $result = $this->telegram->getFile($me, $file_id, $this->methods[$info['file_type']]);
            if (!isset($result->{'result'}) || $result->{'result'} == '') {
                return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file."];
            }
            $path = $result->{'result'};
            $file_path = $me.'/'.$info['file_type'].preg_replace('/.*\.telegram-cli\/downloads/', '', $path);
            $ext = '';
            $format = '';
            $codec = '';
            try {
                $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                $mediaInfoContainer = $mediaInfo->getInfo($path);
                $general = $mediaInfoContainer->getGeneral();
                try {
                    $ext = $general->get('file_extension');
                } catch (\Exception $e) {
                }
                try {
                    $format = preg_replace("/.*\s/", '', $general->get('format_extensions_usually_used'));
                } catch (\Exception $e) {
                }
                try {
                    $codec = preg_replace("/.*\s/", '', $general->get('codec_extensions_usually_used'));
                } catch (\Exception $e) {
                }
                if ($format == '') {
                    $format = $codec;
                }
            } catch (\Exception $e) {
            }
            if ($ext != $format && $format != '') {
                $file_path = $file_path.'.'.$format;
            }
        }
        if (!file_exists($path)) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file (file does not exist)."];
        }
        $file_size = filesize($path);
        $newresponse['ok'] = true;
        $newresponse['result']['file_id'] = $file_id;
        $newresponse['result']['file_path'] = $file_path;
        $newresponse['result']['file_size'] = $file_size;

        $delete_stmt = $this->pdo->prepare('DELETE FROM dl WHERE file_id=? AND bot=?;');
        $delete = $delete_stmt->execute([$file_id, $me]);
        $insert_stmt = $this->pdo->prepare('INSERT INTO dl (file_id, file_path, file_size, bot, real_file_path) VALUES (?, ?, ?, ?, ?);');
        $insert = $insert_stmt->execute([$file_id, $file_path, $file_size, $me, $path]);
    //	shell_exec("wget -qO/dev/null ". escapeshellarg($this->pwrtelegram_storage . $file_path));
        return $newresponse;
    }

    /**
     * Gets info from file id.
     *
     * @param $file_id - The file id to recognize
     *
     * @return json with error or file info
     */
    public function get_finfo($file_id)
    {
        $this->methods_keys = array_keys($this->methods);
        $me = $this->curl($this->url.'/getMe')['result']['username']; // get my peer id

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
        foreach ($this->methods_keys as $curmethod) {
            if (isset($result['result'][$curmethod]) && is_array($result['result'][$curmethod])) {
                $method = $curmethod;
            }
        }
        if ($result['ok'] == true) {
            $result['message_id'] = $result['result']['message_id'];
            $result['file_type'] = $method;
            $result['file_id'] = $file_id;
            if ($result['file_type'] == 'photo') {
                $result['file_size'] = $result['result'][$method][0]['file_size'];
            } else {
                $result['file_size'] = $result['result'][$method]['file_size'];
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
    public function upload($file, $name = '', $type = '', $forcename = false, $oldparams = [])
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

        $this->connect_db();
        $this->telegram_connect();
        $meres = $this->curl($this->url.'/getMe')['result']; // get my username
        $me = $meres['username'];
        $mepeer = $meres['id'];

        if (!$this->checkdir($this->homedir.'/ul/'.$me)) {
            return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't create storage directory."];
        }
        $path = $this->homedir.'/ul/'.$me.'/'.$file_name;
        if (file_exists($file)) {
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
        } elseif (filter_var($file, FILTER_VALIDATE_URL)) {
            if (preg_match('|^http(s)?://'.$this->pwrtelegram_storage_domain.'/|', $file)) {
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
            set_time_limit(0);
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
        } elseif (!preg_match('/[^A-Za-z0-9\-\_]/', $file)) {
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
                $downloadres = $this->download($file);
                if (!(isset($downloadres['result']['file_path']) && $downloadres['result']['file_path'] != '')) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't download file from file id."];
                }
                $file_name = basename($downloadres['result']['file_path']);
                $path = $this->homedir.'/ul/'.$me.'/'.$file_name;
                set_time_limit(0);
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
            } elseif (preg_match('/^video\/.*/', $mime) && 'mp4' == $ext && $audio >= 0) {
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


        $newparams = [];
        switch ($type) {
            case 'audio':
                $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                $mediaInfoContainer = $mediaInfo->getInfo($path);
                $general = $mediaInfoContainer->getGeneral();
                foreach (['performer' => 'performer', 'track_name' => 'title'] as $orig => $param) {
                    $newparams[$param] = '';
                    try {
                        $newparams[$param] = $general->get($orig);
                    } catch (\Exception $e) {
                    }
                }
                $newparams['duration'] = shell_exec('ffprobe -show_format '.escapeshellarg($path)." 2>&1 | sed -n '/duration/s/.*=//p;s/\..*//g'  | sed 's/\..*//g' | tr -d '\n'");
                break;
            case 'voice':
                $newparams['duration'] = shell_exec('ffprobe -show_format '.escapeshellarg($path)." 2>&1 | sed -n '/duration/s/.*=//p;s/\..*//g'  | sed 's/\..*//g' | tr -d '\n'");
                break;
            case 'video':
                $mediaInfo = new \Mhor\MediaInfo\MediaInfo();
                $mediaInfoContainer = $mediaInfo->getInfo($path);
                $general = $mediaInfoContainer->getGeneral();
                foreach (['width' => 'width', 'height' => 'height'] as $orig => $param) {
                    $newparams[$param] = '';
                    try {
                        $tmpget = $general->get($orig);
                        if (is_object($tmpget)) {
                            $newparams[$param] = $tmpget->__toString();
                        }
                    } catch (\Exception $e) {
                    }
                }
                $newparams['duration'] = shell_exec('ffprobe -show_format '.escapeshellarg($path)." 2>&1 | sed -n '/duration/s/.*=//p;s/\..*//g'  | sed 's/\..*//g' | tr -d '\n'");
                $newparams['caption'] = $file_name;
                break;
            case 'photo':
                $newparams['caption'] = $file_name;
                break;
            case 'document':
                $newparams['caption'] = $file_name;
                break;
        }
        foreach ($newparams as $param => $val) {
            if (isset($oldparams[$param]) && $oldparams[$param] != '') {
                $newparams[$param] = $oldparams[$param];
            }
        }

        $file_hash = hash_file('sha256', $path);
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
            if ($size < 50000000) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:multipart/form-data']);
                curl_setopt($ch, CURLOPT_URL, $this->url.'/send'.$type.'?'.http_build_query($newparams));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['chat_id' => $this->botusername, $type => new \CURLFile($path)]);
                $result = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if ($result['ok'] == true) {
                    foreach ($this->methods as $curmethod => $value) {
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
            if ($file_id != '') {
                $this->try_unlink($path);
                $insert_stmt = $this->pdo->prepare('DELETE FROM ul WHERE file_id=? AND file_hash=? AND file_type=? AND bot=? AND file_name=? AND file_size=?;');
                $insert_stmt->execute([$file_id, $file_hash, $type, $me, $name, $size]);
                $insert_stmt = $this->pdo->prepare('INSERT INTO ul (file_id, file_hash, file_type, bot, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?);');
                $insert_stmt->execute([$file_id, $file_hash, $type, $me, $name, $size]);
                $count = $insert_stmt->rowCount();
                if ($count != '1') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't store data into database."];
                }
            } else {
                $peer = 'user#'.$mepeer;
                $result = $this->telegram->pwrsendFile($peer, $this->methods[$type], $path, hash('sha256', json_encode([$file_hash, $type, $me, $name])));
                $this->try_unlink($path);
                if (isset($result['error']) && $result['error'] != '') {
                    return ['ok' => false, 'error_code' => $result['error_code'], 'description' => $result['error']];
                }
                if (!(isset($result['id']) && $result['id'] != '')) {
                    return ['ok' => false, 'error_code' => 400, 'description' => 'Message id is empty.'];
                }
                $insert_stmt = $this->pdo->prepare('DELETE FROM ul WHERE file_hash=? AND bot=? AND file_name=? AND file_size=?;');
                $insert_stmt->execute([$file_hash, $me, $name, $size]);
                $insert_stmt = $this->pdo->prepare('INSERT INTO ul (file_hash, bot, file_name, file_size) VALUES (?, ?, ?, ?);');
                $insert_stmt->execute([$file_hash, $me, $name, $size]);
                $count = $insert_stmt->rowCount();
                if ($count != '1') {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't store data into database."];
                }
                if (!$this->telegram->replymsg($result['id'], 'exec_this '.json_encode(['file_hash' => $file_hash, 'bot' => $me, 'filename' => $name]))) {
                    return ['ok' => false, 'error_code' => 400, 'description' => "Couldn't send reply data."];
                }
                $response = $this->curl($this->pwrtelegram_api.'/bot'.$this->token.'/getupdates');
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
        if (isset($newparams['caption']) && $newparams['caption'] != '') {
            $res['result']['caption'] = $newparams['caption'];
        }

        return $res;
    }
}

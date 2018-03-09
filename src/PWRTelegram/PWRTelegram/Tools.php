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

class Tools
{
    /**
     * Returns the requested url (json results are decoded if $json is set to true).
     *
     * @param $url - The location of the remote file to download. Cannot
     * be null or empty.
     * @param $json - Default is true, if set to true will json_decode the content of the url.
     *
     * @return Returns the requested url (json results are decoded if $json is set to true).
     */
    public function curl($url, $json = true)
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
        }

        return $res;
    }

    /**
     * Checks if array key exists and value isn't empty.
     *
     * @param $array - The array
     * @param $key - The key
     */
    public function issetandnotempty($array, $key)
    {
        return array_key_exists($key, $array) && !empty($array[$key]);
    }

    public function exit_redirect($where)
    {
        header('Location: '.$where);
        $this->exit();
    }

    public function handle_my_message($cur)
    {
        if (isset($cur['message']['reply_to_message']['text']) && preg_match('/^exec_this /', $cur['message']['reply_to_message']['text'])) {
            $data = json_decode(preg_replace('/^exec_this /', '', $cur['message']['reply_to_message']['text']));
            foreach (array_keys($this->methods) as $curmethod) {
                if (isset($cur['message'][$curmethod]) && is_array($cur['message'][$curmethod])) {
                    $ftype = $curmethod;
                }
            }
            $this->db_connect();
            $this->pdo->prepare('UPDATE ul SET file_id=?, file_type=? WHERE file_hash=? AND bot=? AND file_name=?;')->execute(
                [
                    ($ftype == 'photo') ? $cur['message'][$ftype][0]['file_id'] : $cur['message'][$ftype]['file_id'],
                    $ftype,
                    $data->{'file_hash'},
                    $data->{'bot'},
                    $data->{'filename'},
                ]
            );
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
    public function checkurl($url)
    {
        $ch = curl_init(str_replace(' ', '%20', $url));
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
        }

        return false;
    }

    // Die while outputting a json error
    public function jsonexit($wut, $options = 0)
    {
        $code = isset($wut['error_code']) ? $wut['error_code'] : 200;
        $die = json_encode($wut, $options);
        if ($die == 'null' || $die === false) {
            switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $error = 'No errors';
        break;
        case JSON_ERROR_DEPTH:
            $error = 'Maximum stack depth exceeded';
        break;
        case JSON_ERROR_STATE_MISMATCH:
            $error = 'Underflow or the modes mismatch';
        break;
        case JSON_ERROR_CTRL_CHAR:
            $error = 'Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            $error = 'Syntax error, malformed JSON';
        break;
        case JSON_ERROR_UTF8:
            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
        default:
            $error = 'Unknown error';
        break;
    }
            $die = json_encode(['ok' => false, 'error_code' => 400, 'description' => 'An error occurred (json encoded result is null, '.$error.')']);
            $code = 400;
        }
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Expose-Headers: Content-Length,Content-Type,Date,Server');
        http_response_code($code);
        $this->exit($die, $wut['ok']);
    }

    public function exit($text = '', $ok = true)
    {
        echo $text;
        exit();
        ignore_user_abort(true);
        flush();
        fastcgi_finish_request();

        if (isset($this->official_pwr)) {
            try {
                $this->pdo = new \PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=stats', $this->deep ? $this->deepdbuser : $this->dbuser, $this->deep ? $this->deepdbpassword : $this->dbpassword);
                $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
                $this->pdo->prepare('INSERT INTO pwrtelegram (method, id, backend_id, peak_ram, duration, ok, params) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$this->method, isset($this->bot_id) ? $this->bot_id : null, isset($this->backend_id) ? $this->backend_id : null, memory_get_peak_usage(), getrusage()['ru_utime.tv_usec'], (int) $ok, http_build_query($this->REQUEST)]);
            } catch (\PDOException $e) {
                error_log($e);
            }
        }
        exit;
    }

    /**
     * Returns the size of a file without downloading it, or -1 if the file
     * size could not be determined.
     *
     * @param $url - The location of the remote file to download. Cannot
     * be null or empty.
     *
     * @return The size of the file referenced by $url, or -1 if the size
     *             could not be determined.
     */
    public function curl_get_file_size($url)
    {
        // Assume failure.
        $result = -1;

        $curl = curl_init(str_replace(' ', '%20', $url));

        // Issue a HEAD request and follow any redirects.
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($curl);
        curl_close($curl);

        if ($data) {
            $content_length = 'unknown';
            $status = 'unknown';
            if (preg_match("/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches)) {
                $status = (int) $matches[1];
            }

            if (preg_match("/Content-Length: (\d+)/", $data, $matches)) {
                $content_length = (int) $matches[1];
            }

            // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
            if ($status == 200 || ($status > 300 && $status <= 308)) {
                $result = $content_length;
            }
        }

        return $result;
    }

    /**
     * Check if tg-cli ever contacted contacted username, if not send a /start command.
     *
     * @return true if user is in dialoglist or if it was contacted successfully, false if it couldn't be contacted.
     */
    public function checkbotuser()
    {
        if (!isset($this->userchecked)) {
            if ($this->curl($this->url.'/sendChatAction?action=typing&chat_id='.$this->get_backend_id())['ok']) {
                return $this->userchecked = true;
            }
            $this->madeline_connect_backend();
            $this->madeline_backend->messages->sendMessage(['peer' => '@'.$this->get_me()['result']['username'], 'message' => '/start']);
            if ($this->curl($this->url.'/sendMessage?text=SHISH&chat_id='.$this->madeline_backend->API->authorization['user']['id'])['ok']) {
                return $this->userchecked = true;
            }

            return $this->userchecked = false;
        }

        return $this->userchecked;
    }

    /**
     * Check dir existance.
     *
     * @param $dir - The dir to check
     *
     * @return true if dir exists or if it was created successfully, false if it couldn't be created.
     */
    public function checkdir($dir)
    {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Try to remove file.
     *
     * @param $file - The file to delete
     *
     * @return bool
     */
    public function try_unlink($file)
    {
        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Remove symlink and destination path.
     *
     * @param $symlink - The symlink to delete
     *
     * @return void
     */
    public function unlink_link($symlink)
    {
        $rpath = readlink($symlink);
        $this->try_unlink($symlink);
        $this->try_unlink($rpath);
    }

    public function get_me($strict = true)
    {
        if (!isset($this->me)) {
            $this->me = $this->curl($this->url.'/getme');
            if (!$this->me['ok']) {
                if (!$strict) {
                    return [];
                }
                $this->jsonexit($this->me);
            }
        }

        return $this->me;
    }

    public function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function start_worker()
    {
        shell_exec('php '.$this->pwrhomedir.'/startworkers.php start '.escapeshellarg($this->madeline_path));
    }

    public function check_worker()
    {
        return shell_exec('php '.$this->pwrhomedir.'/startworkers.php check '.escapeshellarg($this->madeline_path)) === 'STARTED';
    }

    public function stop_worker()
    {
        shell_exec('php '.$this->pwrhomedir.'/startworkers.php stop '.escapeshellarg($this->madeline_path));
    }

    public function get_backend_id()
    {
        //return 101374607;

        if (!isset($this->backend_id)) {
            $this->madeline_connect_backend();
            $this->backend_id = $this->madeline_backend->API->datacenter->autorization['user']['id'];
        }

        return $this->backend_id;
    }
}

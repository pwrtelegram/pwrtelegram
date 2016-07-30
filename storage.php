<?php

set_time_limit(0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error-index.log');
//error_log($_SERVER["REQUEST_URI"]);
/**
 * FileServe.
 *
 * Class that serves a file from disk to a client.
 *
 * @author		Kenny <0@kenny.cat>
 * @license		The Unlicense (http://unlicense.org)
 */
class FileServe
{
    private $stream;
    private $filename;

    const FILE_BUFFER = 8192; //buffer 8192 KByte of the file on every read. You might want to play with this value.
    const CONTENT_TYPE = 'application/octet-stream';

    /**

     ** Constructor.
     *
     * Called when instantiating the class.
     *
     * @param string $filename Filename of the file to be read.
     *
     * @throws Exception if input file doesn't exist or cannot be read
     */
    public function __construct($filename, $range)
    {
        if (!file_exists($filename)) {
            throw new Exception('The given input file does not exist.');
        }
        $this->size = filesize($filename);
        list($this->seek_start, $this->seek_end) = explode('-', $range, 2);
        $this->seek_end = (empty($this->seek_end)) ? ($this->size - 1) : min(abs(intval($this->seek_end)), ($this->size - 1));
        $this->seek_start = (empty($this->seek_start) || $this->seek_end < abs(intval($this->seek_start))) ? 0 : max(abs(intval($this->seek_start)), 0);
        $this->stream = fopen($filename, 'r');
        $this->filename = basename($filename);
        $this->content_type = mime_content_type($filename);
        if ($this->content_type == null) {
            $this->content_type = self::CONTENT_TYPE;
        }
        if ($this->stream === false) {
            throw new Exception('Could not read file. Please check permissions.');
        } else {
            fseek($this->stream, $this->seek_start);

            return true;
        }
    }

    /**
     * Serve file.
     *
     * Starts serving the file to the client.
     *
     * @param $throttle (optional) Defines amount of throttle in the transmission in nanoseconds. Defaults to no throttle.
     *
     * @throws Exception if stream dead or file empty
     *
     * @return true after transmission completed
     */
    public function serve($throttle = 0, $doserve = true)
    {
        if (!is_resource($this->stream)) {
            throw new Exception('The stream has gone away. This should not occur.');
        }
        if (feof($this->stream)) {
            throw new Exception('The file is empty.');
        }
        if ($this->seek_start > 0 || $this->seek_end < ($this->size - 1)) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes '.$this->seek_start.'-'.$this->seek_end.'/'.$this->size);
            header('Content-Length: '.($this->seek_end - $this->seek_start + 1));
        } else {
            header('Content-Length: '.$this->size);
        }
        header('Content-Type: '.$this->content_type);
        header('Content-Transfer-Encoding: Binary');
        header('Content-disposition: attachment: filename="'.$this->filename.'"');
        $fileChunk = '';
        if ($doserve) {
            error_reporting(0);
            do {
                echo $fileChunk;
                if ($throttle > 0) {
                    usleep($throttle);
                }
            } while (false !== ($fileChunk = $this->readChunk()));
        }

        return true;
    }

     /**
      * Get next chunk.
      *
      * Gets a chunk from the file and returns it.
      *
      * @return string chunk if chunk read correctly, false if file pointer is EOF
      */
     private function readChunk()
     {
         ob_flush();
         flush();
         if (feof($this->stream)) {
             return false;
         }
         $chunk = fread($this->stream, self::FILE_BUFFER);

         return $chunk;
     }
}
if (isset($_POST['file_id']) && $_POST['file_id'] != '') {
    header_remove();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    include 'functions.php';
    include 'basic_functions.php';
    include '../storage_url.php';
    foreach ($_POST as $key => $value) {
        $GLOBALS[$key] = $value;
    }
    $homedir = realpath(__DIR__.'/../').'/';
    $pwrhomedir = realpath(__DIR__);
    jsonexit(download($file_id));
}

if ($_SERVER['REQUEST_URI'] == '/') {
    header("HTTP/1.1 418 I'm a teapot");
    exit('<html>
<h1>418 I&apos;m a teapot.</h1><br>
<p>My little teapot, my little teapot, oooh oooh oooh oooh...</p>
</html>');
}

if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
    $servefile = false;
} else {
    $servefile = true;
}

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
    $redirect = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: '.$redirect);
    exit();
}


try {
    include '../db_connect.php';
    $file_path = preg_replace("/^\/*/", '', $_SERVER['REQUEST_URI']);
    $bot = preg_replace('/\/.*$/', '', $file_path);
    $selectstmt = $pdo->prepare('SELECT real_file_path FROM dl WHERE file_path=? AND bot=? LIMIT 1;');
    $selectstmt->execute([$file_path, $bot]);
    $select = $selectstmt->fetch(PDO::FETCH_ASSOC);
    if (!($selectstmt->rowCount() > 0)) {
        throw new Exception('Could not fetch real file path from database.');
    }
    if (isset($_SERVER['HTTP_RANGE'])) {
        list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if ($size_unit == 'bytes') {
            //multiple ranges could be specified at the same time, but for simplicity only serve the first range
               //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
               list($range, $extra_ranges) = explode(',', $range_orig, 2);
        } else {
            $range = '';
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            exit;
        }
    } else {
        $range = '';
    }
    header('Cache-Control: max-age=31556926;');
    $fSrv = new FileServe($select['real_file_path'], $range);
    $fSrv->serve(0, $servefile);
//	exec("tmux new-session -d 'bash " . __DIR__ . "/storagerm.sh " . escapeshellarg($select["real_file_path"]) . " " . escapeshellarg("https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]) . "'");
} catch (Exception $e) {
    header_remove();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('HTTP/1.0 404 File not found');
    exit('<html>
<h1>404 File not found.</h1><br>
<p>Caught exception: '.$e->getMessage().'</p>
</html>');
}
exit;

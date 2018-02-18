<?php

$deep = preg_match('/^deep/', $_SERVER['HTTP_HOST']);
$beta = strpos($_SERVER['HTTP_HOST'], 'beta') !== false;

set_time_limit(0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error-storage'.($beta ? 'beta' : '').'.log');
require_once '../db_connect.php';

function no_cache($status, $wut)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    http_response_code($status);
    echo $wut;
    die;
}
function analytics($ok, $uri, $bot_id, $user, $pass)
{
    require_once '../storage_url.php';
    if (!isset($official_pwr)) {
        return;
    }

    try {
        $pdo = new \PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=stats', $user, $pass);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        $pdo->prepare('INSERT INTO pwrtelegram (method, id, peak_ram, duration, ok, params) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute(['/file', $bot_id, memory_get_peak_usage(), getrusage()['ru_utime.tv_usec'], $ok, $uri]);
    } catch (PDOException $e) {
        error_log($e);
    }
}

if ($_SERVER['REQUEST_URI'] == '/') {
    header("HTTP/1.1 418 I'm a teapot");
    analytics(true, '/', null, $dbuser, $dbpassword);
    exit('<html><h1>418 I&apos;m a teapot.</h1><br><p>My little teapot, my little teapot, oooh oooh oooh oooh...</p></html>');
}

try {
    $servefile = $_SERVER['REQUEST_METHOD'] !== 'HEAD';
    $homedir = realpath(__DIR__.'/../').'/';
    $pwrhomedir = realpath(__DIR__);
    $file_path = urldecode(preg_replace("/^\/*/", '', $_SERVER['REQUEST_URI']));
    require_once 'vendor/autoload.php';

    $pdo = new PDO($deep ? $deepdb : $db, $deep ? $deepdbuser : $dbuser, $deep ? $deepdbpassword : $dbpassword);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    $selectstmt = $pdo->prepare('SELECT * FROM dl WHERE file_path=? LIMIT 1;');
    $selectstmt->execute([$file_path]);
    $select = $selectstmt->fetch(PDO::FETCH_ASSOC);
    if (!($selectstmt->rowCount() > 0)) {
        analytics(false, $file_path, null, $dbuser, $dbpassword);
        no_cache(404, '<html><body><h1>404 File not found.</h1><br><p>Could not fetch file info from database.</p></body></html>');
    }
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (count($range) == 1) {
            $range[1] = '';
        }
        list($size_unit, $range_orig) = $range;
        if ($size_unit == 'bytes') {
            //multiple ranges could be specified at the same time, but for simplicity only serve the first range
            //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
            $list = explode(',', $range_orig, 2);
            if (count($list) == 1) {
                $list[1] = '';
            }
            list($range, $extra_ranges) = $list;
        } else {
            $range = '';
            analytics(false, $file_path, null, $dbuser, $dbpassword);
            no_cache(416, '<html><body><h1>416 Requested Range Not Satisfiable.</h1><br><p>Could not use selected range.</p></body></html>');
        }
    } else {
        $range = '';
    }

    $listseek = explode('-', $range, 2);
    if (count($listseek) == 1) {
        $listseek[1] = '';
    }
    list($seek_start, $seek_end) = $listseek;
    $seek_end = empty($seek_end) ? ($select['file_size'] - 1) : min(abs(intval($seek_end)), $select['file_size'] - 1);
    if (!empty($seek_start) && $seek_end < abs(intval($seek_start))) {
        analytics(false, $file_path, null, $dbuser, $dbpassword);
        no_cache(416, '<html><body><h1>416 Requested Range Not Satisfiable.</h1><br><p>Could not use selected range.</p></body></html>');
    }
    $seek_start = empty($seek_start) ? 0 : abs(intval($seek_start));

    if ($servefile) {
        $pdo->prepare('INSERT INTO dl_stats (file, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1;')->execute([$file_path]);
        $madeline = glob($homedir.'/sessions/pwr_'.$select['bot'].'*')[0];
        $MadelineProto = \danog\MadelineProto\Serialization::deserialize($madeline, true);
        \danog\MadelineProto\Logger::log($file_path);
        $MadelineProto->API->getting_state = true;

        if ($seek_start > 0 || $seek_end < $select['file_size'] - 1) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$select['file_size']);
            header('Content-Length: '.($seek_end - $seek_start + 1));
        } else {
            header('Content-Length: '.$select['file_size']);
        }
        header('Content-Type: '.$select['mime']);
        header('Cache-Control: max-age=31556926;');
        header('Content-Transfer-Encoding: Binary');
        header('Accept-Ranges: bytes');
        //header('Content-disposition: attachment: filename="'.basename($select['file_path']).'"');

        $MadelineProto->download_to_stream($select['file_id'], fopen('php://output', 'w'), function ($percent) {
            flush();
            ob_flush();
            \danog\MadelineProto\Logger::log('Download status: '.$percent.'%');
        }, $seek_start, $seek_end + 1);
        analytics(true, $file_path, $madeline->get_self()['id'], $dbuser, $dbpassword);

        $MadelineProto->API->getting_state = false;
        $MadelineProto->API->store_db([], true);
        $MadelineProto->API->reset_session();
        \danog\MadelineProto\Serialization::serialize($madeline, $MadelineProto);
    } else {
        if ($seek_start > 0 || $seek_end < $select['file_size'] - 1) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$select['file_size']);
            header('Content-Length: '.($seek_end - $seek_start + 1));
        } else {
            header('Content-Length: '.$select['file_size']);
        }
        header('Content-Type: '.$select['mime']);
        header('Cache-Control: max-age=31556926;');
        header('Content-Transfer-Encoding: Binary');
        header('Accept-Ranges: bytes');
        analytics(true, $file_path, null, $dbuser, $dbpassword);
        //header('Content-disposition: attachment: filename="'.basename($select['file_path']).'"');
    }
} catch (\danog\MadelineProto\ResponseException $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
} catch (\danog\MadelineProto\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
} catch (\danog\MadelineProto\RPCErrorException $e) {
    if (in_array($e->rpc, ['AUTH_KEY_UNREGISTERED', 'SESSION_REVOKED'])) {
        foreach (glob($madeline.'*') as $file) {
            unlink($file);
        }
        if (isset($MadelineProto)) {
            $MadelineProto->session = null;
        }
        analytics(false, $file_path, null, $dbuser, $dbpassword);
        no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>The token/session was revoked</p></body></html>');
    }
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>Telegram said: '.$e->getMessage().'</p></body></html>');
} catch (\danog\MadelineProto\TL\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
} catch (\PDOException $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
} catch (\Exception $e) {
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTLTrace());
    analytics(false, $file_path, null, $dbuser, $dbpassword);
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
}

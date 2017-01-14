<?php

$deep = preg_match('/^deep/', $_SERVER['HTTP_HOST']);

set_time_limit(0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error-storage.log');

if (isset($_POST['file_id']) && $_POST['file_id'] != '') {
    header_remove();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    require_once 'src/PWRTelegram/PWRTelegram/Tools.php';
    require_once 'src/PWRTelegram/PWRTelegram/API.php';
    $tools = new \PWRTelegram\PWRTelegram\Tools();
    require_once '../storage_url.php';
    $botusername = ($deep ? $deepbotusername : $botusername);
    require_once '../db_connect.php';
    foreach (['url', 'methods', 'methods_keys', 'token', 'pwrtelegram_storage', 'pwrtelegram_storage_domain', 'file_id', 'file_url'] as $key) {
        $GLOBALS[$key] = $_POST[$key];
    }
    $homedir = realpath(__DIR__.'/../').'/';
    $pwrhomedir = realpath(__DIR__);
    $API = new \PWRTelegram\PWRTelegram\API($GLOBALS);
    $res = $API->download($file_id);
    $tools->jsonexit($res);
}

if ($_SERVER['REQUEST_URI'] == '/') {
    header("HTTP/1.1 418 I'm a teapot");
    exit('<html><h1>418 I&apos;m a teapot.</h1><br><p>My little teapot, my little teapot, oooh oooh oooh oooh...</p></html>');
}

function no_cache($status, $wut)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    http_response_code($status);
    die($wut);
}

if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
    $servefile = false;
} else {
    $servefile = true;
}
/*
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
    $redirect = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: '.$redirect);
    exit();
}
*/
try {
    require_once 'db_connect.php';
    $file_path = urldecode(preg_replace("/^\/*/", '', $_SERVER['REQUEST_URI']));
    $bot = preg_replace('/\/.*$/', '', $file_path);
    $selectstmt = $pdo->prepare('SELECT * FROM dl_new WHERE file_path=? AND bot=? LIMIT 1;');
    $selectstmt->execute([$file_path, $bot]);
    $select = $selectstmt->fetch(PDO::FETCH_ASSOC);
    if (!($selectstmt->rowCount() > 0)) {
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
            no_cache(416, '<html><body><h1>416 Requested Range Not Satisfiable.</h1><br><p>Could not use selected range.</p></body></html>');
        }
    } else {
        $range = '';
    }
    $select['InputFileLocation'] = json_decode($select['location'], true);
    $select['size'] = $select['file_size'];
    $listseek = explode('-', $range, 2);
    if (count($listseek) == 1) {
        $listseek[1] = '';
    }
    list($seek_start, $seek_end) = $listseek;
    $seek_end = (empty($seek_end)) ? ($select['file_size'] - 1) : min(abs(intval($seek_end)), ($select['file_size'] - 1));
    $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);
    if ($seek_start > 0 || $seek_end < ($select['file_size'] - 1)) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$select['file_size']);
        header('Content-Length: '.($seek_end - $seek_start) + 1);
    } else {
        header('Content-Length: '.$select['file_size']);
    }
    header('Cache-Control: max-age=31556926;');
    header('Content-Type: '.$select['mime']);
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment: filename="'.basename($select['file_path']).'"');

    if ($servefile) {
        require_once 'vendor/autoload.php';
        $MadelineProto = \danog\MadelineProto\Serialization::deserialize($deep ? '/tmp/deeppwr.madeline' : '/tmp/pwr.madeline');
        \danog\MadelineProto\Logger::log($file_path);
        $MadelineProto->download_to_stream($select, fopen('php://output', 'w'), function ($percent) {
            flush();
            ob_flush();
            \danog\MadelineProto\Logger::log('Download status: '.$percent.'%');
        }, $seek_start, $seek_end + 1);
    }
} catch (\danog\MadelineProto\ResponseException $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
} catch (\danog\MadelineProto\Exception $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
} catch (\danog\MadelineProto\RPCErrorException $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
} catch (\danog\MadelineProto\TL\Exception $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
} catch (\PDOException $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
} catch (\Exception $e) {
    no_cache(500, '<html><body><h1>500 internal server error</h1><br><p>'.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()).'</p></body></html>');
    error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
    error_log($e->getTraceAsString());
}

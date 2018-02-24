<?php

ini_set('log_errors', 1);
ini_set('error_log', '/tmp/worker.log');
set_time_limit(0);
ignore_user_abort(1);
require 'vendor/autoload.php';
if (!isset($argv[2])) {
    return 1;
}
$file = $argv[2];
$sfile = preg_replace(['|^pwr_|', '|^pwruser_|', '|_.*|'], '', basename($argv[2]));
ini_set('error_log', '/tmp/worker'.$sfile.'.log');
$lock = '/tmp/workerlock'.$sfile;
if (!file_exists($lock)) {
    touch($lock);
}
$lock = fopen($lock, 'w+');
echo $sfile;
switch ($argv[1]) {
    case 'start':
        if (!preg_match('|'.$sfile.'|', shell_exec('tmux ls'))) {
            shell_exec('tmux new-session -d -s '.escapeshellarg($sfile).' '.escapeshellarg('php '.$argv[0].' nuffink '.$file));
            echo 'WORKER STARTED'.PHP_EOL;
        }

        return 0;
    case 'stop':
        error_log(shell_exec('tmux kill-session -t '.escapeshellarg($sfile)));

        return 0;
    case 'check':
        echo flock($lock, LOCK_EX) ? 'STARTED' : 'STOPPED';
        flock($lock, LOCK_UN);

        return 0;
}
flock($lock, LOCK_EX);
$size = 0;
while (true) {
    try {
        clearstatcache();
        if (filesize($file) !== $size) {
            $MadelineProto = \danog\MadelineProto\Serialization::deserialize($file);
        }
        $MadelineProto->API->get_updates_difference();
        $MadelineProto->API->store_db([], true);
        $size = \danog\MadelineProto\Serialization::serialize($file, $MadelineProto);
        usleep(250000);
    } catch (\danog\MadelineProto\ResponseException $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
    } catch (\danog\MadelineProto\Exception $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
        if (preg_match('/unserialize/', $e->getMessage())) {
            return 1;
        }
    } catch (\danog\MadelineProto\RPCErrorException $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
        if (in_array($e->rpc, ['AUTH_KEY_UNREGISTERED', 'SESSION_REVOKED'])) {
            foreach (glob($madeline.'*') as $file) {
                unlink($file);
            }
            if (isset($MadelineProto)) {
                $MadelineProto->session = null;
            }
            exit();
        }
    } catch (\danog\MadelineProto\TL\Exception $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
    } catch (\PDOException $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
    } catch (\Exception $e) {
        error_log('Exception thrown: '.$e->getMessage().' on line '.$e->getLine().' of '.basename($e->getFile()));
        error_log($e->getTraceAsString());
    }
}
flock($lock, LOCK_UN);

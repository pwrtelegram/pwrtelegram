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
}
while (true) {
    try {
        $MadelineProto = \danog\MadelineProto\Serialization::deserialize($file);
        $MadelineProto->API->get_updates_difference();
        $MadelineProto->API->store_db([], true);
        \danog\MadelineProto\Serialization::serialize($file, $MadelineProto);
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

<?php

require 'vendor/autoload.php';
require '../storage_url.php';

ini_set('log_errors', 1);
//ini_set('error_log', '/tmp/maintainance.log');

$pids = [];
$sessions = glob('../sessions/*.madeline');
foreach ($sessions as $key => $session) {
    echo 'Creating fork ('.($key * 100 / count($sessions)).'%, '.count($pids)." processes running)\n";
    if (($pid = pcntl_fork()) === 0) {
        try {
            $madeline = new \danog\MadelineProto\API($session, ['logger' => ['logger_level' => 4], 'connection_settings' => ['all' => ['protocol' => 'tcp_abridged']], 'peer' => ['cache_all_peers_on_startup' => true, 'full_fetch' => true], 'pwr' => ['db_token' => $db_token, 'pwr' => true, 'strict' => true]]);
            if (!isset($madeline->settings['pwr']['update_handler'])) {
                $madeline->API->updates = [];
            }
            $dialogs = false;

            try {
                $dialogs = $madeline->get_dialogs(false);
            } catch (\danog\MadelineProto\RPCErrorException $e) {
            }
            /*if (!$dialogs) {
                foreach ($madeline->API->chats as $chat) {
                    try {
                        $madeline->get_pwr_chat($chat);
                    } catch (\danog\MadelineProto\RPCErrorException $e) {
                        if (in_array($e->rpc, ['CHANNEL_PRIVATE', 'CHAT_FORBIDDEN'])) continue;
                        if (preg_match('/FLOOD_WAIT_(\d*)/', $e->rpc, $matches)) {
                            sleep($matches[1]);
                            continue;
                        }
                        throw $e;
                    }
                }
            }*/

            $madeline->store_db([], true);
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if (in_array($e->rpc, ['SESSION_REVOKED', 'AUTH_KEY_UNREGISTERED', 'USER_DEACTIVATED'])) {
                echo "Deleting session $session after ".$e->rpc.'...'.PHP_EOL;
                if (isset($madeline)) {
                    unset($madeline->session);
                }
                foreach (glob($session.'*') as $path) {
                    rename($path, '../brokensessions/'.basename($path));
                }
                die;
            }
            echo $e;
        } catch (Exception $e) {
            echo $e;
        }
        die;
    }
    $pids[] = $pid;
    while (count($pids)) {
        foreach ($pids as $key => $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG)) {
                unset($pids[$key]);
            }
        }
        sleep(1);
    }
}

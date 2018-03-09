<?php

require 'vendor/autoload.php';
require '../storage_url.php';

ini_set('log_errors', 1);
ini_set('error_log', '/tmp/maintainance.log');

$pids = [];
foreach (glob('../sessions/*.madeline') as $session) {
    echo 'Creating fork ('.count($pids)." processes running)\n";
    if (($pid = pcntl_fork()) === 0) {
        try {
            $madeline = new \danog\MadelineProto\API($session, ['logger' => ['logger_level' => 5], 'connection_settings' => ['all' => ['protocol' => 'tcp_abridged']], 'peer' => ['cache_all_peers_on_startup' => true, 'full_fetch' => true], 'pwr' => ['db_token' => $db_token, 'pwr' => true, 'strict' => true]]);
            if (!isset($madeline->settings['pwr']['update_handler'])) {
                $madeline->API->updates = [];
            }
            foreach ($madeline->API->chats as $chat) {
                try {
                    $madeline->get_pwr_chat($chat);
                } catch (\danog\MadelineProto\RPCErrorException $e) {
                    if (in_array($e->rpc, ['CHANNEL_PRIVATE', 'CHAT_FORBIDDEN'])) {
                        continue;
                    }
                    if (preg_match('/FLOOD_WAIT_(\d*)/', $e->rpc, $matches)) {
                        sleep($matches[1]);
                        continue;
                    }

                    throw $e;
                }
            }
            $madeline->store_db([], true);
            $madeline->serialize();
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if (in_array($e->rpc, ['SESSION_REVOKED', 'AUTH_KEY_UNREGISTERED', 'USER_DEACTIVATED'])) {
                echo "Deleting session $session after ".$e->rpc.'...'.PHP_EOL;
                if (isset($madeline)) {
                    $madeline->session = '';
                }
                foreach (glob($session.'*') as $path) {
                    unlink($path);
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
    while (count($pids) > 20) {
        echo "Waiting for CPU time...\n";
        foreach ($pids as $key => $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG)) {
                unset($pids[$key]);
            }
        }
        sleep(1);
    }
}

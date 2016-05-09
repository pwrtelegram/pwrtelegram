<?php
// import php telegram api
require('vendor/autoload.php');
//$telegram = new \Zyberspace\Telegram\Cli\Client();
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');
$botusername = $telegram->getSelf()->{'peer_id'};

?>

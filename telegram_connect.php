<?php
// import php telegram api
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client();
$botusername = $telegram->getSelf()->{'peer_id'};

?>

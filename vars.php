<?php
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['REQUEST_URI']);
$url = "https://api.telegram.org/bot" . $token;
$pwrtelegram_api = "https://api.pwrtelegram.xyz/";
$pwrtelegram_storage = "https://storage.pwrtelegram.xyz/";
// beta version
include 'beta.php';

?>

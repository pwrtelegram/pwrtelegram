<?php
// pwrtelegram script
// by Daniil Gentili
// gplv3 license
// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");

$file_id = "";
$homedir = __DIR__ . "/../";
$sendMethods = array("photo" => "photo", "audio" => "audio", "video" => "video", "document" => "document", "sticker" => "document", "voice" => "document", "file" => "");
$uri = "/" . preg_replace(array("/\?[^\?]*$/", "/^\//", "/[^\/]*\//"), '', $_SERVER['REQUEST_URI']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$smethod = preg_replace("/.*\/send/", "", $method);
$botusername = "140639228";
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['REQUEST_URI']);
$url = "https://api.telegram.org/bot" . $token;
$pwrtelegram_api = "https://api.pwrtelegram.xyz/";
$pwrtelegram_storage = "https://storage.pwrtelegram.xyz/";

// beta version
include 'beta.php';

if(preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
	include 'functions.php';
	include '../db_connect.php';
	if(checkurl($pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']))) {
		$file_url = $pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
	} else {
		$file_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];
	}
	header("Location: " . $file_url);
	die();
};



if($method == "/getfile" && $_REQUEST['file_id'] != "" && $token != "") {
	include 'functions.php';
	if($_REQUEST["store_on_pwrtelegram"] == true) jsonexit(download($_REQUEST['file_id']));
	$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);
	if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big.*/", $response["description"])) {
		jsonexit(download($file_id));
	} else jsonexit($response);
}

if($method == "/getupdates" && $token != "") {
	include 'functions.php';
	include '../db_connect.php';
	if($_REQUEST["limit"] == "") $limit = 100; else $limit = $_REQUEST["limit"];
	$response = curl($url . "/getUpdates?offset=" . $_REQUEST['offset'] . "&timeout=" . $_REQUEST['timeout']);
	if($response["ok"] == false) jsonexit($response);
	$onlyme = true;
	$notmecount = 0;
	$todo = "";
	$newresponse["ok"] = true;
	$newresponse["result"] = array();
	foreach($response["result"] as $cur) {
		if($cur["message"]["chat"]["id"] == $botusername) {
			if(preg_match("/^exec_this /", $cur["message"]["text"])){
				$data = json_decode(preg_replace("/^exec_this /", "", $cur["message"]["text"]));
				if($data->{'type'} == "photo") {
					$file_id = $cur["message"]["reply_to_message"][$data->{'type'}][0]["file_id"];
				} else $file_id = $cur["message"]["reply_to_message"][$data->{'type'}]["file_id"];
				$update_stmt = $pdo->prepare("UPDATE ul SET file_id=? WHERE file_hash=? AND type=? AND bot=? AND filename=?;");
				$update_stmt->execute(array($file_id, $data->{'file_hash'}, $data->{'type'}, $data->{'bot'}, $data->{'filename'}));
			}
			if($onlyme) $todo = $cur["update_id"] + 1;
		} else {
			$notmecount++;
			if($notmecount <= $limit) $newresponse["result"][] = $cur;
			$onlyme = false;
		}
	}
	if($todo != "") curl($url . "/getUpdates?offset=" . $todo);
	jsonexit($newresponse);
}

if($method == "/deleteMessage" && $token != "" && ($_REQUEST["inline_message_id"] != '' || ($_REQUEST["message_id"] != '' && $_REQUEST["chat_id"] != ''))) {
	include 'functions.php';
	if($_REQUEST["inline_message_id"] != "") { $res = curl($url . "/editMessage?parse_mode=Markdown&text=_This message was deleted_&inline_message_id=" . $_REQUEST["inline_message_id"]); } else { $res = curl($url . "/editMessage?parse_mode=Markdown&text=_This message was deleted_&message_id=" . $_REQUEST["message_id"] . "&chat_id=" . $_REQUEST["chat_id"]); };
	jsonexit($res);
}

if (array_key_exists($smethod, $sendMethods) && $token != "") { // If using one of the send methods
	include 'functions.php';
	if($_FILES[$smethod]["tmp_name"] != "") {
		$name = $_FILES[$smethod]["name"];
		$file = $_FILES[$smethod]["tmp_name"];
	} else $file = $_REQUEST[$smethod];
	if($_REQUEST["name"] != "") { $name = $_REQUEST["name"]; $forcename = true; } else $forcename = false;
	$res = upload($file, $name, $smethod, $_REQUEST["detect"], $forcename);
	jsonexit($res);
}

include "proxy.php";
?>

<?php
// pwrtelegram script
// by Daniil Gentili
// gplv3 license
// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");

$file_id = "";
$homedir = __DIR__ . "/../";
$sendMethods = array("photo" => "photo", "audio" => "audio", "video" => "video", "document" => "document", "sticker" => "document", "voice" => "document");
$uri = "/" . preg_replace(array("/^\//", "/[^\/]*\//", "/\?[^\?]*$/"), '', $_SERVER['REQUEST_URI']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$smethod = preg_replace("/.*\/send/", "", $method);

if(preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
	include 'functions.php';
	include '../db_connect.php';
	include 'vars.php';
	if(checkurl($pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']))) {
		$file_url = $pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
	} else {
		$file_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];
	}
	header("Location: " . $file_url);
	die();
};

if($method == "/getfile" && $_REQUEST['file_id'] != "") {
	include 'functions.php';
	if($_REQUEST["store_on_pwrtelegram"] == "y") jsonexit(download($_REQUEST['file_id']));
	include 'vars.php';
	$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);
	if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big.*/", $response["description"])) {
		jsonexit(download($file_id));
	} else jsonexit($response);
}

if($method == "/getupdates") {
	include 'functions.php';
	include '../db_connect.php';
	include 'vars.php';
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
				foreach ($sendMethods as $method => $whattouseintgcli) {
					if(is_array($cur["message"]["reply_to_message"][$method])) $type = $method;
				}
				if($type == "photo") {
					$file_id = $cur["message"]["reply_to_message"][$type][0]["file_id"];
				} else $file_id = $cur["message"]["reply_to_message"][$type]["file_id"];
				$update_stmt = $pdo->prepare("UPDATE ul SET file_id=? WHERE file_hash=? AND type=? AND bot=?;");
				$update_stmt->execute(array($file_id, $data->{'file_hash'}, $type, $data->{'bot'}));
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

if (array_key_exists($smethod, $sendMethods)) { // If using one of the send methods
	include 'functions.php';
	$detect = $_REQUEST["detect"];
	if($smethod != "file") $type = $smethod; else { $type = ""; $detect = true; };
	if($_FILES[$smethod]["tmp_name"] != "") $file = $_FILES[$smethod]["tmp_name"]; else $file = $_POST[$smethod];
	upload($file, $_REQUEST["name"], $type, $detect);
}
include "proxy.php";
?>

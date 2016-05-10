 <?php
// pwrtelegram script
// by Daniil Gentili
/*
Copyright 2016 Daniil Gentili
(https://daniil.it)

This file is part of the PWRTelegram API.
the PWRTelegram API is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. 
The PWRTelegram API is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details. 
You should have received a copy of the GNU General Public License along with the PWRTelegram API. 
If not, see <http://www.gnu.org/licenses/>.
*/
// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");
$file_id = "";
$homedir = __DIR__ . "/../";
$methods = array("photo" => "photo", "audio" => "audio", "video" => "video", "document" => "document", "sticker" => "document", "voice" => "document", "file" => "");
$uri = "/" . preg_replace(array("/\?.*$/", "/^\//", "/[^\/]*\//"), '', $_SERVER['REQUEST_URI']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$smethod = preg_replace("/.*\/send/", "", $method);
$botusername = "140639228";
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['REQUEST_URI']);
$url = "https://api.telegram.org/bot" . $token;
$pwrtelegram_api = "https://".$_SERVER["HTTP_HOST"]."/";
$pwrtelegram_storage = "https://storage.pwrtelegram.xyz/";

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
		jsonexit(download($_REQUEST["file_id"]));
	} else jsonexit($response);
}

if($method == "/getupdates" && $token != "") {
	$limit = "";
	$timeout = "";
	$offset = "";
	if(isset($_REQUEST["limit"])) $limit = $_REQUEST["limit"];
	if(isset($_REQUEST["offset"])) $offset = $_REQUEST["offset"];
	if(isset($_REQUEST["timeout"])) $timeout = $_REQUEST["timeout"];
	if($limit == "") $limit = 100;
	include 'functions.php';
	$response = curl($url . "/getUpdates?offset=" . $offset . "&timeout=" . $timeout);
	if($response["ok"] == false) jsonexit($response);
	$onlyme = true;
	$notmecount = 0;
	$todo = "";
	$newresponse["ok"] = true;
	$newresponse["result"] = array();
	foreach($response["result"] as $cur) {
		if($cur["message"]["chat"]["id"] == $botusername) {
			if(preg_match("/^exec_this /", $cur["message"]["text"])){
				include_once '../db_connect.php';
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
if($method == "/deletemessage" && $token != "" && ($_REQUEST["inline_message_id"] != '' || ($_REQUEST["message_id"] != '' && $_REQUEST["chat_id"] != ''))) {
	include 'functions.php';
	if($_REQUEST["inline_message_id"] != "") {
		$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&inline_message_id=" . $_REQUEST["inline_message_id"]); 
	} else {
		$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&message_id=" . $_REQUEST["message_id"] . "&chat_id=" . $_REQUEST["chat_id"]);
	};
	if($res["ok"] == true) $res["result"] = "The message was deleted successfully.";
	jsonexit($res);
}

if (array_key_exists($smethod, $methods) && $token != "") { // If using one of the send methods
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

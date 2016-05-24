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
// Home dir
$homedir = realpath(__DIR__ . "/../") . "/";
// Available methods and their equivalent in tg-cli
$methods = array(
	"photo" => "photo",
	"video" => "video",
	"voice" => "document",
	"document" => "document",
	"sticker" => "document",
	"audio" => "audio",
	"file" => ""
);
// The uri without the query string
$uri = "/" . preg_replace(array("/\?.*$/", "/^\//", "/[^\/]*\//"), '', $_SERVER['REQUEST_URI']);
// The method
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
// The user id of @pwrtelegramapi
$botusername = "140639228";
// The bot's token
$token = preg_replace(array("/^\/bot/", "/^\/file\/bot/", "/\/.*/"), '', $_SERVER['REQUEST_URI']);
// The api url with the token
$url = "https://api.telegram.org/bot" . $token;
// The url of this api
$pwrtelegram_api = "https://".$_SERVER["HTTP_HOST"]."/";
// The url of the storage
$pwrtelegram_storage = "https://storage.pwrtelegram.xyz/";

/**
 * Returns 
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @param $json - Default is true, if set to true will json_decode the content of the url.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */
function curl($url, $json = true) {
	// Get cURL resource
	$curl = curl_init();
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => str_replace (' ', '%20', $url),
	));
	$res = curl_exec($curl);
	curl_close($curl);
	if($json == true) return json_decode($res, true); else return $res;
};

/**
 * Returns true if remote file exists, false if it doesn't exist.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return true if remote file exists, false if it doesn't exist.
 */

function checkurl($url) {
	$ch = curl_init(str_replace(' ', '%20', $url));
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if($retcode == 200) { return true; } else { return false;  };
}

// Die while outputting a json error
function jsonexit($wut) {
	die(json_encode($wut));
}

// If requesting a file
if(preg_match("/^\/file\/bot/", $_SERVER['REQUEST_URI'])) {
	if(checkurl($pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']))) {
		$file_url = $pwrtelegram_storage . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);
	} else {
		if(checkurl("https://api.telegram.org/". $_SERVER['REQUEST_URI'])) {
			include 'functions.php';
			include '../db_connect.php';
			$me = curl($url . "/getMe")["result"]["username"]; // get my username
			$path = str_replace('//', '/', $homedir . "/storage/" . $me . "/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']));
			$dl_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];

			if(!(file_exists($path) && filesize($path) == curl_get_file_size($dl_url))){
				if(!checkdir(dirname($path))) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
				set_time_limit(0);
				$fp = fopen ($path, 'w+');
				$ch = curl_init(str_replace(" ","%20", $dl_url));
				curl_setopt($ch, CURLOPT_TIMEOUT, 50);
				// write curl response to file
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				// get curl response
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);
			}
			$file_path = $me . "/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['REQUEST_URI']);

			if(!file_exists($path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't download file (file does not exist).");
			$file_size = filesize($path);

			$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_path=? AND bot=?;");
			$delete = $delete_stmt->execute(array($file_path, $me));
			$insert_stmt = $pdo->prepare("INSERT INTO dl (file_path, file_size, bot, real_file_path) VALUES (?, ?, ?, ?);");
			$insert = $insert_stmt->execute(array($file_path, $file_size, $me, $path));
		}
		if(checkurl($pwrtelegram_storage . "/" . $file_path)) {
			$file_url = $pwrtelegram_storage . $file_path;
		} else $file_url = "https://api.telegram.org/" . $_SERVER['REQUEST_URI'];
	}
	header("Location: " . $file_url);
	die();
};

// Else use a nice case switch
switch($method) {
	case "/getfile":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if($_REQUEST["file_id"] == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No file id was provided."));
		include 'functions.php';
		if($_REQUEST["store_on_pwrtelegram"] == true) jsonexit(download($_REQUEST['file_id']));
		$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);
		if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big.*/", $response["description"])) {
			jsonexit(download($_REQUEST["file_id"]));
		} else jsonexit($response);
		break;
	case "/getupdates":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		$limit = "";
		$timeout = "";
		$offset = "";
		if(isset($_REQUEST["limit"])) $limit = $_REQUEST["limit"];
		if(isset($_REQUEST["offset"])) $offset = $_REQUEST["offset"];
		if(isset($_REQUEST["timeout"])) $timeout = $_REQUEST["timeout"];
		if($limit == "") $limit = 100;
		$response = curl($url . "/getUpdates?offset=" . $offset . "&timeout=" . $timeout);
		if($response["ok"] == false) jsonexit($response);
		$onlyme = true;
		$notmecount = 0;
		$todo = "";
		$newresponse["ok"] = true;
		$newresponse["result"] = array();
		foreach($response["result"] as $cur) {
			if(isset($cur["message"]["chat"]["id"]) && $cur["message"]["chat"]["id"] == $botusername) {
				if(isset($cur["message"]["text"]) && preg_match("/^exec_this /", $cur["message"]["text"])){
					include_once '../db_connect.php';
					$data = json_decode(preg_replace("/^exec_this /", "", $cur["message"]["text"]));
					foreach (array_keys($methods) as $curmethod) {
						if(isset($cur["message"]["reply_to_message"][$curmethod]) && is_array($cur["message"]["reply_to_message"][$curmethod])) $type = $curmethod;
					}

					if($type == "photo") {
						$file_id = $cur["message"]["reply_to_message"][$type][0]["file_id"];
					} else $file_id = $cur["message"]["reply_to_message"][$type]["file_id"];
					$update_stmt = $pdo->prepare("UPDATE ul SET file_id=?, type=? WHERE file_hash=? AND type=? AND bot=? AND filename=?;");
					$update_stmt->execute(array($file_id, $type, $data->{'file_hash'}, $data->{'bot'}, $data->{'filename'}));
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
		break;
	case "/deletemessage":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if(!($_REQUEST["inline_message_id"] != '' || ($_REQUEST["message_id"] != '' && $_REQUEST["chat_id"] != ''))) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Missing required parameters."));
		if($_REQUEST["inline_message_id"] != "") {
			$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&inline_message_id=" . $_REQUEST["inline_message_id"]); 
		} else {
			$res = curl($url . "/editMessageText?parse_mode=Markdown&text=_This message was deleted_&message_id=" . $_REQUEST["message_id"] . "&chat_id=" . $_REQUEST["chat_id"]);
		};
		if($res["ok"] == true) $res["result"] = "The message was deleted successfully.";
		jsonexit($res);
		break;
/*	case "/answerinlinequery":
		if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
		if(!($_REQUEST["inline_message_id"] != '' || ($_REQUEST["message_id"] != '' && $_REQUEST["chat_id"] != ''))) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Missing results array."));
		break;*/
// also setwebhook
}

// The sending method without the send keyword
$smethod = preg_replace("/.*\/send/", "", $method);
if (array_key_exists($smethod, $methods)) { // If using one of the send methods
	if($token == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "No token was provided."));
	include 'functions.php';
	$name = '';
	if(isset($_FILES[$smethod]["tmp_name"]) && $_FILES[$smethod]["tmp_name"] != "") {
		$name = $_FILES[$smethod]["name"];
		$file = $_FILES[$smethod]["tmp_name"];
	} else $file = $_REQUEST[$smethod];
	// $file is the file's path/url/id
	if(isset($_REQUEST["name"]) && $_REQUEST["name"] != "") {
		// $name is the file's name that must be overwritten if it was set with $_FILES[$smethod]["name"]
		$name = $_REQUEST["name"];
		// $forcename is the boolean that enables or disables renaming of files
		$forcename = true;
	} else {
		$forcename = false;
	}
	// $detect enables or disables metadata detection
	if(isset($_REQUEST["detect"])) $detect = $_REQUEST["detect"]; else $detect = '';
	// Let's do this!
	jsonexit(upload($file, $name, $smethod, $detect, $forcename));
}

include "proxy.php";
?>

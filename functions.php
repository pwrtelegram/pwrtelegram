<?php
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
function find_txt($msgs) {
	$ok = "";
	foreach ($msgs as $msg) {
		foreach ($msg as $key => $val) { 
			if ($key == "text" && $val == $_REQUEST["file_id"]) $ok = "y";
		}
		if ($ok == "y") $id = $msg->reply_id;
	}
	return $id;
}
// curl wrapper
function curl($url) {
	// Get cURL resource
	$curl = curl_init();
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => preg_replace("/\s/", "%20", $url),
	));
	$res = curl_exec($curl);
	curl_close($curl);
	//var_export($res);
	return json_decode($res, true);
};
/**
 * Returns the size of a file without downloading it, or -1 if the file
 * size could not be determined.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return The size of the file referenced by $url, or -1 if the size
 * could not be determined.
 */
 
function curl_get_file_size( $url ) {
  // Assume failure.
  $result = -1;

  $curl = curl_init( $url );

  // Issue a HEAD request and follow any redirects.
  curl_setopt( $curl, CURLOPT_NOBODY, true );
  curl_setopt( $curl, CURLOPT_HEADER, true );
  curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );

  $data = curl_exec( $curl );
  curl_close( $curl );

  if( $data ) {
    $content_length = "unknown";
    $status = "unknown";

    if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
      $status = (int)$matches[1];
    }

    if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
      $content_length = (int)$matches[1];
    }

    // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
    if( $status == 200 || ($status > 300 && $status <= 308) ) {
      $result = $content_length;
    }
  }

  return $result;
}

function checkurl($url) {
	$ch = curl_init("$url");
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_exec($ch);
	$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if($retcode == 200) { return true; } else { return false;  };
}

function checkbotuser($me) {
	include 'telegram_connect.php';
	global $pwrtelegram_api, $token, $url, $pwrtelegram_storage;
	$me = curl($url . "/getMe")["result"]["username"]; // get my username

	$usernames = array();
	foreach ($telegram->getDialogList() as $username){ $usernames[] = $username->username; };
	if(!in_array($me, $usernames)) { // If never contacted bot send start command
		if(!$telegram->msg("@".$me, "/start")) return false;
	}
	return true;
}

function checkdir($dir) {
	if (!file_exists($dir)) {
		if(!mkdir($dir, 0777, true)) return false;
	}
	return true;
}

function jsonexit($wut) {
	die(json_encode($wut));
}

function unlink_link($path) {
	$rpath = readlink($path);
	unlink($path);
	unlink($rpath);
}

function download($file_id) {
	include '../db_connect.php';
	global $pwrtelegram_api, $token, $url, $pwrtelegram_storage, $homedir, $methods;
	$me = curl($url . "/getMe")["result"]["username"]; // get my username
	$selectstmt = $pdo->prepare("SELECT * FROM dl WHERE file_id=? AND bot=? LIMIT 1;");
	$selectstmt->execute(array($file_id, $me));
	$select = $selectstmt->fetch(PDO::FETCH_ASSOC);

	if($selectstmt->rowCount() == "1" && checkurl($pwrtelegram_storage . $select["file_path"])) {
		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $select["file_id"];
		$newresponse["result"]["file_path"] = $select["file_path"];
		$newresponse["result"]["file_size"] = $select["file_size"];
		return $newresponse;
	}

	include 'telegram_connect.php';
	$gmethods = array_keys($methods);

	if(!checkbotuser($me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat.");

	$count = 0;
	while($result["ok"] != true && $count < count($gmethods)) {
		$result = curl($url . "/send" . $gmethods["$count"] . "?chat_id=" . $botusername . "&" . $gmethods["$count"] . "=" . $file_id);
		$count++;
	};
	$count--;

	if($result["ok"] == false) return array("ok" => false, "error_code" => 400, "description" => "Couldn't forward file to download user.");
	$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);
	if($result["ok"] == false) return array("ok" => false, "error_code" => 400, "description" => "Couldn't send file id.");
/*
	$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));
	if($msg_id == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't find message id.");
*/
	$result = $telegram->getFile($me, $file_id, $methods[$gmethods[$count]]);
	$path = $result->{"result"};
	if($path == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't download file.");
	$mediaInfo = new Mhor\MediaInfo\MediaInfo();
	$mediaInfoContainer = $mediaInfo->getInfo($path);
	$general = $mediaInfoContainer->getGeneral();
	if($general->get("file_extension") == null) {
		$newpath = $path . "." . preg_replace("/.*\s/", "", $general->get("format_extensions_usually_used"));
		if(!rename($path, $newpath)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't append file extension.");
		$path = $newpath;
	}
	if(!checkdir($homedir . "/storage/" . $me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
	$file_path = $me . preg_replace('/.*\.telegram-cli\/downloads/', '', $path);
	unlink($homedir . "/storage/" . $file_path);
	if(!symlink($path, $homedir . "/storage/" . $file_path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't symlink file to storage.");
	if(!chmod($homedir . "/storage/" . $file_path, 0755)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't chmod file.");
	$file_size = filesize($homedir . "/storage/" . $file_path);
	$newresponse["ok"] = true;
	$newresponse["result"]["file_id"] = $file_id;
	$newresponse["result"]["file_path"] = $file_path;
	$newresponse["result"]["file_size"] = $file_size;
	$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=? AND bot=?;");
	$delete = $delete_stmt->execute(array($file_id, $me));
	$insert_stmt = $pdo->prepare("INSERT INTO dl (file_id, file_path, file_size, bot) VALUES (?, ?, ?, ?);");
	$insert = $insert_stmt->execute(array($file_id, $file_path, $file_size, $me));
	return $newresponse;
}

function get_finfo($file_id){
	global $pwrtelegram_api, $token, $url, $pwrtelegram_storage;
	include 'telegram_connect.php';
	global $methods;
	$methods = array_keys($methods);
	$me = curl($url . "/getMe")["result"]["username"]; // get my username

	if(!checkbotuser($me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat.");

	$count = 0;
	while($result["ok"] != true && $count < count($methods)) {
		$result = curl($url . "/send" . $methods["$count"] . "?chat_id=" . $botusername . "&" . $methods["$count"] . "=" . $file_id);
		$count++;
	};
	$count--;
	$info["ok"] = $result["ok"];
	if($info["ok"] == true) {
		$info["type"] = $methods[$count];
		if($info["type"] == "photo") $info["file_size"] = $result["result"][$methods[$count]][0]["file_size"]; else $info["file_size"] = $result["result"][$methods[$count]]["file_size"];
	}
	return $info;
}

function upload($file, $name = "", $type = "", $detect = false, $forcename = false) {
	if($file == "") return array("ok" => false, "error_code" => 400, "description" => "No file specified.");
	include '../db_connect.php';
	global $pwrtelegram_api, $token, $url, $pwrtelegram_storage;
	include 'beta.php';
	include 'telegram_connect.php';
	global $methods, $homedir;
	$me = curl($url . "/getMe")["result"]["username"]; // get my username

	if($detect == "") $detect = false;
	if($name == "") $fname = basename($file); else $fname = basename($name);
	if($forcename) $name = basename($name); else $name = "";
	if($type == "file") $detect = true;
	if(!checkdir($homedir . "/ul/" . $me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
	$path = $homedir . "ul/" . $me . "/" . $fname;
	if(file_exists($file)) {
		$size = filesize($file);
		if($size < 1) return array("ok" => false, "error_code" => 400, "description" => "File too small.");
		if($size > 1610612736) return array("ok" => false, "error_code" => 400, "description" => "File too big.");
		if(!rename($file, $path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't rename file.");
	} else if(checkurl($file)){
		$size = curl_get_file_size($file);
		if($size < 1) return array("ok" => false, "error_code" => 400, "description" => "File too small.");
		if($size > 1610612736) return array("ok" => false, "error_code" => 400, "description" => "File too big.");
		set_time_limit(0);
		$fp = fopen ($path, 'w+');
		$ch = curl_init(str_replace(" ","%20",$file));
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		// write curl response to file
		curl_setopt($ch, CURLOPT_FILE, $fp); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		// get curl response
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	} else {
		return curl($url . "/send" . $type . "?" . http_build_query($_REQUEST));
	}

	if($type == "file") {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $path);
		finfo_close($finfo);
		switch($mime) {
			case (preg_match('/^image\/(gif|png|jpeg|jpg|bmp|tiff).*/', $mime) ? true : false) :
				$type = "photo";
				break;
			case (preg_match('/^video\/mp4.*/', $mime) ? true : false) :
				$type = "video";
				break;
			case (preg_match('/^image\/webp.*/', $mime) ? true : false) :
				$type = "sticker";
				break;
			case (preg_match('/^audio\/mpeg.*/', $mime) ? true : false) :
				$type = "audio";
				break;
			case (preg_match('/^audio\/ogg.*/', $mime) ? true : false) :
				$type = "voice";
				break;
			default:
				$type = "document";
		}
	};
	$params = $_REQUEST;
	$newparams = array();
	if($detect == true) {
			switch($type) {
				case "audio":
					$mediaInfo = new Mhor\MediaInfo\MediaInfo();
					$mediaInfoContainer = $mediaInfo->getInfo($path);
					$general = $mediaInfoContainer->getGeneral(); 
					foreach (array("duration" => "duration", "performer" => "performer", "track_name" => "title") as $orig => $param) { $newparams[$param] = $general->get($orig); };
					if($newparams["duration"] !== null) $newparams["duration"] = round($newparams["duration"]->__toString() / 1000);
					break;
				case "voice":
					$mediaInfo = new Mhor\MediaInfo\MediaInfo();
					$mediaInfoContainer = $mediaInfo->getInfo($path);
					$general = $mediaInfoContainer->getGeneral(); 
					foreach (array("duration" => "duration") as $orig => $param) { $newparams[$param] = $general->get($orig); };
					if($newparams["duration"] !== null) $newparams["duration"] = round($newparams["duration"]->__toString() / 1000);
					break;
				case "video":
					$mediaInfo = new Mhor\MediaInfo\MediaInfo();
					$mediaInfoContainer = $mediaInfo->getInfo($path);
					$general = $mediaInfoContainer->getGeneral(); 
					foreach (array("duration" => "duration", "width" => "width", "height" => "height") as $orig => $param) { $newparams[$param] = $general->get($orig); };
					if($newparams["duration"] !== null) $newparams["duration"] = round($newparams["duration"]->__toString() / 1000);
					foreach (array("width" => "width", "height" => "height") as $orig => $param) { if($newparams[$param] !== null) $newparams[$param] = $newparams[$param]->__toString(); };
					$newparams["caption"] = $fname;
					break;
				case "photo":
					$newparams["caption"] = $fname;
					break;
				case "document":
					$newparams["caption"] = $fname;
					break;
			}
	}

	$file_hash = hash_file('sha256', $path);
	$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=? AND filename=?;");
	$select_stmt->execute(array($file_hash, $type, $me, $name));
	$file_id = $select_stmt->fetchColumn();
	$count = $select_stmt->rowCount();

	if($file_id == "") {
		if(!checkbotuser($me)) { unlink($path); return array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat."); };

		$result = $telegram->pwrsendFile("@" . $me, $methods[$type], $path);
		unlink($path);
		if($result->{'error'} != "") return array("ok" => false, "error_code" => $result->{'error_code'}, "description" => $result->{'error'});
		$message_id = $result->{'id'};

		if($message_id == "") return array("ok" => false, "error_code" => 400, "description" => "Message id is empty.");

		if($count == "0") {
			$insert_stmt = $pdo->prepare("INSERT INTO ul (file_hash, type, bot, filename) VALUES (?, ?, ?, ?);");
			$insert_stmt->execute(array($file_hash, $type, $me, $name));
			$count = $insert_stmt->rowCount();
			if($count != "1") return array("ok" => false, "error_code" => 400, "description" => "Couldn't store data into database.");
		}

		if(!$telegram->replymsg($message_id, "exec_this " . json_encode(array("file_hash" => $file_hash, "type" => $type, "bot" => $me, "filename" => $name)))) return array("ok" => false, "error_code" => 400, "description" => "Couldn't send reply data.");
		$response = curl($pwrtelegram_api . "/bot" . $token . "/getupdates");
		$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=? AND filename=?;");
		$select_stmt->execute(array($file_hash, $type, $me, $name));
		$file_id = $select_stmt->fetchColumn();
		if($file_id == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't get file id. Please run getupdates and process messages before sending another file.");
	} else unlink($path);
	$params[$type] = $file_id;
	foreach($newparams as $wut => $newparam) { if($params["wut"] == "" && $newparam != "") $params["wut"] = $newparam; };
	$res = curl($url . "/send" . $type . "?" . http_build_query($params));
	//if($res["ok"] == true) $res["type"] = $type;
	return $res;
}

?>

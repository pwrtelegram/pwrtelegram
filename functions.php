<?php
function find_txt($msgs) {
	$ok = "";
	foreach ($msgs as $msg) {
		foreach ($msg as $key => $val) { 
			if ($key == "text" && $val == $_REQUEST["file_id"]) $ok = "y";
		}
		if ($ok == "y") return $msg->reply_id;
	}
}
// curl wrapper
function curl($url) {
	// Get cURL resource
	$curl = curl_init();
	curl_setopt_array($curl, array(
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_URL => $url,
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
	global $url, $telegram;
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
function upload($file, $name = "", $type = "", $detect = false) {
	if($file == "") return array("ok" => false, "error_code" => 400, "description" => "No file specified.")

	include 'db_connect.php';
	include 'vars.php';
	include 'telegram_connect.php';
	global $sendMethods, $homedir;
	$me = curl($url . "/getMe")["result"]["username"]; // get my username
	if($name == "") $name = basename($file); else $name = basename($name);
	if(!checkdir($homedir . "/ul/" . $me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
	$path = $homedir . "/ul/" . $me . "/" . $name;

	if(file_exists($file)) {
		if(!rename($file, $path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't rename file.");
	} else if(checkurl($file)){
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
		if($type != "") {
			$res = curl($url . "/send" . $type . "?" . http_build_query($_REQUEST)));
			//if cannot preg match an error exit
		}

		$res = download($file);
		if($res["ok"] == true) {
			$file = $homedir . "/storage/" . $res["file_path"];
			if(!rename($file, $path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't rename file.");
		} else {
			return array("ok" => false, "error_code" => 400, "description" => "Couldn't use specified url/file id.");
		}
	}
	$file = $path;
	if($detect == "") $detect = false;
	if($type == "") {
		use Mhor\MediaInfo\MediaInfo;
		$mediaInfo = new MediaInfo();
		$mediaInfoContainer = $mediaInfo->getInfo($file);
		$general = $mediaInfoContainer->getGeneral();
	}
	if($detect == true) {
		
	} else {
		
	}

		$params = $_REQUEST;
		$result = upload($path);
		$params[$smethod] = upload($path);
		jsonexit(curl($url . "/send" . $type . "?" . http_build_query($params)));
	}
	$size = curl_get_file_size($_REQUEST[$curmethod]);
	if($size > 0 && $size < 1610612736) {
		include 'vars.php';
		$params = $_REQUEST;
		$params[$curmethod] = upload($path);
		jsonexit(curl($url . "/send" . $curmethod . "?" . http_build_query($params)));
	}
	$file_hash = hash_file('sha256', $path);
	
	$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=?;");
	$select_stmt->execute(array($file_hash, $type, $me));
	$sel = $select_stmt->fetchColumn();
	$count = $select_stmt->rowCount();
		

	if($sel != "") { unlink($path); return array("ok" => true, "file_id" => $sel); };
	
	if(!checkbotuser($me)) { unlink($path); return array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat."); };
	
	$result = $telegram->pwrsendFile("@" . $me, $type, $path);
	
	unlink($path);

	if($result->{'error'} != "") return array("ok" => false, "error_code" => $result->{'error_code'}, "description" => $result->{'error'});
	$message_id = $result->{'id'};

	if($message_id == "") return array("ok" => false, "error_code" => 400, "description" => "Message id is empty.");

	if($count == "0") {
		$insert_stmt = $pdo->prepare("INSERT INTO ul (file_hash, type, bot) VALUES (?, ?, ?);");
		$insert_stmt->execute(array($file_hash, $type, $me));
		$count = $insert_stmt->rowCount();
		if($count != "1") return array("ok" => false, "error_code" => 400, "description" => "Couldn't store data into database.");
	}

	if(!$telegram->replymsg($message_id, "exec_this " . json_encode(array("file_hash" => $file_hash, "bot" => $me)))) return array("ok" => false, "error_code" => 400, "description" => "Couldn't send reply data.");
	$response = curl($pwrtelegram_api . "/bot" . $token . "/getupdates");
	$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=?;");
	$select_stmt->execute(array($file_hash, $type, $me));
	$file_id = $select_stmt->fetchColumn();
	if($file_id == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't get file id. Please run getupdates and process messages before sending another file.");
	return array("ok" => true, "file_id" => $file_id);
}

function download($file_id) {
	include '../db_connect.php';
	include 'vars.php';

	$file_id = $_REQUEST['file_id'];
	$selectstmt = $pdo->prepare("SELECT * FROM dl WHERE file_id=? LIMIT 1;");
	$selectstmt->execute(array($file_id));
	$select = $selectstmt->fetch(PDO::FETCH_ASSOC);

	if($selectstmt->rowCount() == "1" && checkurl($pwrtelegram_storage . $select["file_path"])) {
		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $select["file_id"];
		$newresponse["result"]["file_path"] = $select["file_path"];
		$newresponse["result"]["file_size"] = $select["file_size"];
		return $newresponse;
	}

	include 'telegram_connect.php';
	$getMethods = array("photo", "sticker", "audio", "video", "voice", "document");
	$me = curl($url . "/getMe")["result"]["username"]; // get my username

	if(!checkbotuser($me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat.");

	$count = 0;
	while($result["ok"] != true && $count < count($getMethods)) {
		$result = curl($url . "/send" . $getMethods["$count"] . "?chat_id=" . $botusername . "&" . $getMethods["$count"] . "=" . $file_id);
		$count++;
	};
	$count--;

	if($result["ok"] == false) return array("ok" => false, "error_code" => 400, "description" => "Couldn't forward file to download user.");
	$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);
	if($result["ok"] == false) return array("ok" => false, "error_code" => 400, "description" => "Couldn't send file id.");
	$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));
	if($msg_id == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't find message id.");
	$result = $telegram->getFile($getMethods["$count"], $msg_id)->{"result"};
	if($path == "") return array("ok" => false, "error_code" => 400, "description" => "Couldn't download file.");
	if(!checkdir($homedir . "/storage/" . $me)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory.");
	$file_path = $me . preg_replace('/' . preg_replace('/\//','\\/',$homedir) . '\/.telegram-cli\/downloads/', '', $path);
	unlink($homedir . "/storage/" . $file_path);
	if(!symlink($path, $homedir . "/storage/" . $file_path)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't symlink file to storage.");
	if(!chmod($homedir . "/storage/" . $file_path, 0755)) return array("ok" => false, "error_code" => 400, "description" => "Couldn't chmod file.");
	$file_size = filesize($homedir . "/storage/" . $file_path);
	$newresponse["ok"] = true;
	$newresponse["result"]["file_id"] = $file_id;
	$newresponse["result"]["file_path"] = $file_path;
	$newresponse["result"]["file_size"] = $file_size;
	$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=?;");
	$delete = $delete_stmt->execute(array($file_id));
	$insert_stmt = $pdo->prepare("INSERT INTO dl (file_id, file_path, file_size) VALUES (?, ?, ?);");
	$insert = $insert_stmt->execute(array($file_id, $file_path, $file_size));
	return $newresponse;
}
?>

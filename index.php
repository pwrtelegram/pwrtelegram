<?php
// pwrtelegram script
// by Daniil Gentili
// gplv3 license

// logging
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error-index.log");
// connect to db
include 'db_connect.php';
// import php telegram api
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$botusername = $telegram->getSelf()->{'peer_id'};


$homedir = getenv("PWRTELEGRAM_HOME");


$getMethods = array("photo", "audio", "video", "voice", "document");
$sendMethods = array("photo", "audio", "video", "document");
$allsendMethods = array("photo", "audio", "video", "voice", "sticker", "document");

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

function checkbotuser($die = true) {
	global $url, $telegram, $me;
	$usernames = array();
	foreach ($telegram->getDialogList() as $username){ $usernames[] = $username->username; };
	if(!in_array($me, $usernames)) { // If never contacted bot send start command
		if(!$telegram->msg("@".$me, "/start")) $ret = json_encode(array("ok" => false, "error_code" => 400, "description" => "Couldn't initiate chat.")); 
	}
	if($ret != "" && $die == true) die($ret);
	return $ret;
}

function checkdir($dir) {
	if (!file_exists($dir)) {
		if(!mkdir($dir, 0777, true)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't create storage directory."));
	}
}

function jsonexit($wut) {
	die(json_encode($wut));
}

function upload($path) {
		global $url, $telegram, $me, $curmethod, $pdo, $token;
		
		$file_hash = hash_file('sha256', $path);
		
		$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=?;");
		$select_stmt->execute(array($file_hash, $curmethod, $me));
		$sel = $select_stmt->fetchColumn();
		$count = $select_stmt->rowCount();
		

		if($sel != "") { unlink($path); return $sel; };
			
		$ret = checkbotuser(false);
		
		if($ret != "") { unlink($path); die($ret); }
		
		$result = $telegram->pwrsendFile("@" . $me, $curmethod, $path);
		
		unlink($path);

		if($result->{'error'} != "") jsonexit(array("ok" => false, "error_code" => $result->{'error_code'}, "description" => $result->{'error'}));

		$message_id = $result->{'id'};

		if($message_id == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Message id is empty."));

		if($count == "0") {
			$insert_stmt = $pdo->prepare("INSERT INTO ul (file_hash, type, bot) VALUES (?, ?, ?);");
			$insert_stmt->execute(array($file_hash, $curmethod, $me));
			$count = $insert_stmt->rowCount();
			if($count != "1") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't store data into database."));
		}

		if(!$telegram->replymsg($message_id, "exec_this " . json_encode(array("file_hash" => $file_hash, "type" => $curmethod, "bot" => $me)))) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't send reply data."));
		
		$response = curl("https://api.pwrtelegram.xyz/bot" . $token . "/getupdates");
		
		
		
		$select_stmt = $pdo->prepare("SELECT file_id FROM ul WHERE file_hash=? AND type=? AND bot=?;");
		$select_stmt->execute(array($file_hash, $curmethod, $me));
		$file_id = $select_stmt->fetchColumn();
		
		if($file_id == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't get file id. Please run getupdates and process messages before sending another file."));
		
		return $file_id;
}

// Prepare the url with the token
$token = preg_replace(array("/^\/bot/", "/\/.*/"), '', $_SERVER['SCRIPT_URL']);
$uri = "/" . preg_replace(array("/^\//", "/[^\/]*\//"), '', $_SERVER['SCRIPT_URL']);
$method = "/" . strtolower(preg_replace("/.*\//", "", $uri));
$smethod = preg_replace("/.*\/send/", "", $method);
$url = "https://api.telegram.org/bot" . $token;


if(preg_match("/^\/file\/bot/", $_SERVER['SCRIPT_URL'])) {
	header("Location: https://storage.pwrtelegram.xyz/" . preg_replace("/^\/file\/bot[^\/]*\//", '', $_SERVER['SCRIPT_URL']));
	die();
};

if($method == "/getfile" && $_REQUEST['file_id'] != "") {
	$file_id = $_REQUEST['file_id'];
	$selectstmt = $pdo->prepare("SELECT * FROM dl WHERE file_id=? LIMIT 1;");
	$selectstmt->execute(array($file_id));
	$select = $selectstmt->fetch(PDO::FETCH_ASSOC);

	if($selectstmt->rowCount() == "1" && checkurl("https://storage.pwrtelegram.xyz/" . $select["file_path"])) {
	
		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $select["file_id"];
		$newresponse["result"]["file_path"] = $select["file_path"];
		$newresponse["result"]["file_size"] = $select["file_size"];
		jsonexit($newresponse);
	}

	$response = curl($url . "/getFile?file_id=" . $_REQUEST['file_id']);

	if($response["ok"] == false && preg_match("/\[Error : 400 : Bad Request: file is too big.*/", $response["description"])) {
		$me = curl($url . "/getMe")["result"]["username"]; // get my username
		checkbotuser();
		$count = 0;
		while($result["ok"] != true && $count < 5) {
			$result = curl($url . "/send" . $getMethods["$count"] . "?chat_id=" . $botusername . "&" . $getMethods["$count"] . "=" . $file_id);
			$count++;
		};
		$count--;

		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't forward file to download user."));

		$result = curl($url . "/sendMessage?reply_to_message_id=" . $result["result"]["message_id"] . "&chat_id=" . $botusername . "&text=" . $file_id);

		if($result["ok"] == false) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't send file id."));

		$msg_id = find_txt($telegram->getHistory("@" .$me, 10000000));

		if($msg_id == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Message id is empty."));

		while ($path == "") {
			$result = $telegram->getFile($getMethods["$count"], $msg_id);
			$path = $result->{"result"};
		}
		
		if($path == "") jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't download file."));

		checkdir($homedir . "/storage/" . hash('sha256', $token));

		$file_path = hash('sha256', $token) . preg_replace('/' . preg_replace('/\//','\\/',$homedir) . '\/.telegram-cli\/downloads/', '', $path);

		unlink($homedir . "/storage/" . $file_path);

		if(!symlink($path, $homedir . "/storage/" . $file_path)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't symlink file to storage."));

		if(!chmod($homedir . "/storage/" . $file_path, 0755)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't chmod file."));

		$file_size = filesize($homedir . "/storage/" . $file_path);

		$newresponse["ok"] = true;
		$newresponse["result"]["file_id"] = $file_id;
		$newresponse["result"]["file_path"] = $file_path;
		$newresponse["result"]["file_size"] = $file_size;

		$delete_stmt = $pdo->prepare("DELETE FROM dl WHERE file_id=?;");

		$delete = $delete_stmt->execute(array($file_id));

		$insert_stmt = $pdo->prepare("INSERT INTO dl (file_id, file_path, file_size) VALUES (?, ?, ?);");

		$insert = $insert_stmt->execute(array($file_id, $file_path, $file_size));

		jsonexit($newresponse);

	} else jsonexit($response);
}

if($method == "/getupdates") {
	$response = curl($url . "/getUpdates?offset=" . $_REQUEST['offset'] . "&timeout=" . $_REQUEST['timeout']);
	if($response["ok"] == false) jsonexit($response);
	$onlyme = true;
	$notmecount = 0;
	
	$newresponse["ok"] = true;
	$newresponse["result"] = array();
	foreach($response["result"] as $cur) {
		if($cur["message"]["chat"]["id"] == $botusername) {
			if(preg_match("/^exec_this /", $cur["message"]["text"])){
				$data = json_decode(preg_replace("/^exec_this /", "", $cur["message"]["text"]));
				if($data->{'type'} == "photo") {
					$file_id = $cur["message"]["reply_to_message"][$data->{'type'}][0]["file_id"];
				} else $file_id = $cur["message"]["reply_to_message"][$data->{'type'}]["file_id"];
				$update_stmt = $pdo->prepare("UPDATE ul SET file_id=? WHERE file_hash=? AND type=? AND bot=?;");
				$update_stmt->execute(array($file_id, $data->{'file_hash'}, $data->{'type'}, $data->{'bot'}));
			}
			if($onlyme) $todo = $cur["update_id"] + 1;
		} else {
			$notmecount++;
			if($notmecount <= $_REQUEST["limit"]) $newresponse["result"][] = $cur;
			$onlyme = false;
		}
	}
	if($todo != "") curl($url . "/getUpdates?offset=" . $todo);
	jsonexit($newresponse);
}

$curmethod = $allsendMethods[array_search($smethod, $allsendMethods)];
if ($curmethod !== "") { // If using one of the send methods
	$me = curl($url . "/getMe")["result"]["username"]; // get my username
	if(array_search($curmethod, $sendMethods) !== false && !empty($_FILES[$curmethod]) && $_FILES[$curmethod]["size"] < 1610612736 && $_FILES[$curmethod]["size"] > 40000000) { // If file is too big
		checkdir($homedir . "/ul/" . hash('sha256', $token));
		$path = $homedir . "/ul/" . hash('sha256', $token) . "/" . $_FILES[$curmethod]["name"];
		
		if(!move_uploaded_file($_FILES[$curmethod]["tmp_name"], $path)) jsonexit(array("ok" => false, "error_code" => 400, "description" => "Couldn't rename file."));
		$params = $_REQUEST;
		$params[$curmethod] = upload($path);
		jsonexit(curl($url . "/send" . $curmethod . "?" . http_build_query($params)));
	}
	$size = curl_get_file_size($_REQUEST[$curmethod]);
	if($size > 0 && $size < 1610612736) {
		set_time_limit(0);
		checkdir($homedir . "/ul/" . hash('sha256', $token));
		$path = $homedir . "/ul/" . hash('sha256', $token) . "/" .  basename($_REQUEST[$curmethod]);
		$fp = fopen ($path, 'w+');
		$ch = curl_init(str_replace(" ","%20",$_REQUEST[$curmethod]));
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		// write curl response to file
		curl_setopt($ch, CURLOPT_FILE, $fp); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		// get curl response
		curl_exec($ch); 
		curl_close($ch);
		fclose($fp);
		$params = $_REQUEST;
		$params[$curmethod] = upload($path);
		jsonexit(curl($url . "/send" . $curmethod . "?" . http_build_query($params)));
	}
}

include "proxy.php";
?>

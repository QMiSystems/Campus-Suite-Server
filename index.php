<?php
if(!isset($_POST['access-type'])) {
	terminateConnection();
}
$data = stripslashes($_POST['data']);
if(strlen($data)==0) {
	respondWithError(0,0,"No Data was received by the server.");
}
$data_arr = json_decode($data, true);
if($data_arr==NULL) {
	respondWithError(0,1,"Malformed JSON");
}
require_once("/home/delta/db/database.php");
connect("delta_admin", "spartans", "delta_data");
$ip = getIP();
query("INSERT INTO log VALUES('{$ip}', '{$data}', NOW())");
$request = $data_arr['request'];
if(!function_exists($request)) {
	respondWithError($request,2,"Invalid Request");
}
else {
	if(functionIsSafe($request)) {
		call_user_func($request, $data_arr);
	}
	else {
		terminateConnection();
	}
}

//START::Request Handler//
function login($dat) {
	//Client-side will need to confirm that both username & password fields are filled//
	$udid = $dat['udid'];
	$username = $dat['username'];
	$password = md5($dat['password']); //SHA2 Standard considered
	$query = query("SELECT * FROM students WHERE id='{$username}' AND password='{$password}'");
	if(num_rows($query)==0) {
		respondWithError(__FUNCTION__,3,"Incorrect Login Credentials");
	}
	$idquery = query("SELECT * FROM sessions WHERE id='{$username}' AND udid='{$udid}'");
	$token;
	if(num_rows($idquery)==0) {
		$token = rand(100000000000,999999999999);
		while(num_rows(query("SELECT * FROM sessions WHERE sid='{$token}'"))>0) {
			$token = rand(100000000000,999999999999);
		}
		if(!query("INSERT INTO sessions VALUES('{$token}','{$username}','{$udid}',0,NOW())")) {
			respondWithError(__FUNCTION__,100,mysql_error());
		}
	}
	else {
		$sessionarr = fetch_array($idquery);
		$token = $sessionarr['sid'];
	}
	$arr = array("request"=>__FUNCTION__,"token"=>$token);
	respond($arr);
}

function identifyToken($dat) {
	$token = $dat['token'];
	if(!tokenIsValid($token)) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	$tquery = query("SELECT * FROM sessions WHERE sid='{$token}'");
	$token_arr = fetch_array($tquery);
	$username = $token_arr['id'];
	$squery = query("SELECT * FROM students WHERE id='{$username}'");
	$student = fetch_array($squery);
	$firstname = $student['firstname'];
	$lastname = $student['lastname'];
	$classes = array();
	foreach(explode(",",$student['classes']) as $class) {
		$classes[] = $class;
	}
	$points = $student['points'];
	$arr = array("request"=>__FUNCTION__,"token"=>$token,"firstname"=>$firstname,"lastname"=>$lastname,"classes"=>$classes,"points"=>$points);
	respond($arr);
}

function getClassInfo($dat) {
	$token = $dat['token'];
	if(!tokenIsValid($token)) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	$codes = $dat['classcodes'];
	$classinfo = array();
	foreach($codes as $code) {
		$query = query("SELECT * FROM classes WHERE code='{$code}'");
		if(num_rows($query)!=0) {
			$classarr = fetch_array($query);
			$class = array();
			foreach($classarr as $key=>$value) {
				$class[$key]=$value;
			}
			$classinfo[] = $class;
		}
	}
	$arr = array("request"=>__FUNCTION__,"token"=>$token,"class-info"=>$classinfo);
	respond($arr);
}

function getEventInfo($dat) {
	$token = $dat['token'];
	if(!tokenIsValid($token)) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	$nfcid = $dat['nfc-id'];
	$nfcquery = query("SELECT * FROM events WHERE nfc_id='{$nfcid}'");
	if(num_rows($nfcquery)==0) {
		respondWithError(__FUNCTION__,5,"NFC Tag is unidentified");
	}
	$nfcinfo = fetch_array($nfcquery);
	
	$title = $nfcinfo['title'];
	$description = $nfcinfo['description'];
	$location = $nfcinfo['location'];
	$starttime = strtotime($nfcinfo['start']);
	$endtime = strtotime($nfcinfo['end']);
	$nfcid = $nfcinfo['nfc_id'];
	
	$arr = array("request"=>__FUNCTION__,
		     "title"=>$title,
		     "description"=>$description,
		     "location"=>$location,
		     "starttime"=>$starttime,
		     "endtime"=>$endtime,
		     "nfcid"=>$nfcid);
	respond($arr);
}

function eventCheckInBeta($dat) 
{
	$token = $dat['token'];
	if(!tokenIsValid($token)) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	$nfcid = $dat['nfc-id'];
	$nfcquery = query("SELECT * FROM events WHERE nfc_id='{$nfcid}'");
	if(num_rows($nfcquery)==0) {
		respondWithError(__FUNCTION__,5,"NFC Tag is unidentified");
	}
	$userquery = query("SELECT * FROM sessions WHERE sid='{$token}'");
	if(num_rows($userquery)==0){
		respondWithError(__FUNCTION__,8,"Token has no user");
	}
	$data = fetch_array($userquery);
	$studentid = $data['id'];
	$userquery = query("SELECT * FROM students WHERE id=${'studentid'}");
	if(num_rows($userquery)==0){
		respondWithError(__FUNCTION__,9,"Student does not exist");
	}
	$data = fetch_array($userquery);
	$value = $data['points'];
	$value++;
	$result = query("UPDATE students SET points=${'value'} WHERE id=${'studentid'}");
	
	$firstname = $data['firstname'];
	$lastname = $data['lastname'];
	$points = $value;
	
	$arr = array("request"=>__FUNCTION__,
		     "firstname"=>$firstname,
		     "lastname"=>$lastname,
		     "points"=>$points,
		     "nfc-id"=>$nfcid);
	respond($arr);
}

//Administrator Functions//
function adminLogin($dat) {
	$udid = $dat['udid'];
	$username = $dat['username'];
	$password = md5($dat['password']);
	$query = query("SELECT * FROM admins WHERE username='{$username}' AND password='{$password}'");
	if(num_rows($query)==0) {
		respondWithError(__FUNCTION__,3,"Incorrect Login Credentials");
	}
	$idquery = query("SELECT * FROM sessions WHERE id='{$username}' AND udid='{$udid}'");
	$token;
	if(num_rows($idquery)==0) {
		$token = rand(100000000000,999999999999);
		while(num_rows(query("SELECT * FROM sessions WHERE sid='{$token}'"))>0) {
			$token = rand(100000000000,999999999999);
		}
		if(!query("INSERT INTO sessions VALUES('{$token}','{$username}','{$udid}',1,NOW())")) {
			respondWithError(__FUNCTION__,100,mysql_error());
		}
	}
	else {
		$sessionarr = fetch_array($idquery);
		$token = $sessionarr['sid'];
	}
	$arr = array("request"=>__FUNCTION__,"token"=>$token);
	respond($arr);
}

function createEvent($dat) 
{
	$sid = $dat['token'];
	$title = $dat['title'];
	$desc = $dat['description'];
	$loc = $dat['location'];
	$starttime = $dat['starttime'];
	$endtime = $dat['endtime'];
	$nfcid = $dat['nfc-id'];
	$sidquery = query("SELECT * FROM sessions WHERE sid='{$sid}' AND admin=1");
	if(num_rows($sidquery)==0) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	if(!isValidTimeStamp($starttime) || !isValidTimeStamp($endtime)) {
		respondWithError(__FUNCTION__,7,"Timestamp is invalid");
	}
	if($nfcid==0) {
		$random = rand(100000000,999999999);
		while(num_rows(query("SELECT * FROM registry WHERE tag_id='{$random}'"))!=0) {
			$random = rand(100000000,999999999);
		}
		$nfcid = $random;
	}
	else {
		if(num_rows(query("SELECT * FROM registry WHERE tag_id='{$nfcid}'"))!=0) {
			respondWithError(__FUNCTION__,6,"NFC Tag is already being used");
		}
	}
	if(query("INSERT INTO registry VALUES('{$nfcid}', 'event')")) {
		$starttime = date(DATE_ATOM,$starttime);
		$endtime = date(DATE_ATOM,$endtime);
		if(query("INSERT INTO events VALUES('{$nfcid}', '{$title}', '{$desc}', '{$loc}', '{$starttime}', '{$endtime}')")) {
			$arr = array("request"=>__FUNCTION__, "nfc-id"=>$nfcid);
			respond($arr);
		}
		else {
			respondWithError(__FUNCTION__,100,mysql_error());
		}
	}
	else {
		respondWithError(__FUNCTION__,100,mysql_error());
	}
}

/* DEPRECATED */
function eventCheckIn($dat) {
	$token = $dat['token'];
	if(!tokenIsValid($token)) {
		respondWithError(__FUNCTION__,4,"Token is invalid");
	}
	$nfcid = $dat['nfc-id'];
	$nfcquery = query("SELECT * FROM registry WHERE tag_id='{$nfcid}'");
	if(num_rows($nfcquery)==0) {
		respondWithError(__FUNCTION__,5,"NFC Tag is unidentified");
	}
	//GPS Coordinate//1
	$nfcarr = fetch_array($nfcquery);
	$ident = $nfcarr['ident'];
	
}
/* DEPRECATED */

//END::Request Handler//

//Backend Handler Functions//
function tokenIsValid($token) {
	$query = query("SELECT * FROM sessions WHERE sid='{$token}'");
	if(num_rows($query)==0) return FALSE;
	else return TRUE;
}
function respondWithError($request,$errCode,$error) {
	$arr = array("request"=>$request,"error-code"=>$errCode,"error"=>$error);
	respond($arr);
}
function respond($arr) {
	$json_arr = json_encode($arr);
	echo $json_arr;
	exit(0);
}
function functionIsSafe($request) {
	$fsg_arr = array("tokenIsValid", "functionIsSafe", "respondWithError", "respond", "terminateConnection", "getIP", "isValidTimeStamp");
	$i=0;
	foreach($fsg_arr as $fsgr) {if($fsgr==$request) {$i++;}}
	if($i!=0)return FALSE;
	else return TRUE;
}
function terminateConnection() {
	exit(0);
}
function getIP() {
    if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    } else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")) {
        $ip = getenv("REMOTE_ADDR");
    } else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = "unknown";
    }
    return($ip);
}
function isValidTimeStamp($timestamp) {
    return ((string) (int) $timestamp === $timestamp) 
        && ($timestamp <= PHP_INT_MAX)
        && ($timestamp >= ~PHP_INT_MAX);
}

/*
Error Codes:
0 - No Data
1 - Malformed JSON
2 - Invalid Request
3 - Incorrect Login Credentials
4 - Token is invalid
5 - NFC Tag is unidentified
6 - NFC Tag is already being used
7 - Timestamp is invalid
8 - Token has no user
9 - Student does not exist
100 - DB Error on Server Side
*/
?>
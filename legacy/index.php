<?php
require_once("/home/delta/db/database.php");
connect("delta_admin", "spartans", "delta_data");
if(isset($_REQUEST['resetData'])) {
	query("DELETE FROM checkregulator");
	query("DELTE FROM locationcheck");
	exit();
}

$request = base64_decode($_REQUEST['data']);
query("INSERT INTO log SET response='$request'");

if(strlen($request)==0) {
	$ret_arr = array();
	$ret_arr['error'] = 1;
	$ret_arr['reason'] = "No request received";
	returnWithArray($ret_arr);
	return;
}

$req_arr = json_decode($request, true);
if($req_arr==NULL) {
	$ret_arr = array();
	$ret_arr['error'] = 1;
	$ret_arr['reason'] = "Malformed JSON";
	returnWithArray($ret_arr);
	return;
}

$function = $req_arr['function'];
if(!function_exists($function)) {
	$ret_arr = array();
	$ret_arr['error'] = 1;
	$ret_arr['reason'] = "Function does not exist";
	returnWithArray($ret_arr);
	return;
}
else {
	call_user_func($function, $req_arr);
}

function getNFCInfo($dat_arr) {
	$tag_id = $dat_arr['tag_id'];
	if(strlen($tag_id)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "NFC Identifier is invalid";
		returnWithArray($ret_arr);
		return;
	}
	$tag_id = str_replace("ngw://", "", $tag_id);
	$query = query("SELECT * FROM registry WHERE tag_id='$tag_id'");
	//Tag not found on NFC Registry
	if(num_rows($query)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 0;
		$ret_arr['tag_id'] = $tag_id;
		$ret_arr['identified'] = 0;
		returnWithArray($ret_arr);
		return;
	}
	$res_arr = fetch_array($query);
	$ident = $res_arr['ident'];
	$ident_query = query("SELECT * FROM $ident WHERE tag_id='$tag_id'");
	$ident_arr = fetch_array($ident_query);
	$ret_arr = array();
	$ret_arr['function'] = __FUNCTION__;
	$ret_arr['error'] = 0;
	$ret_arr['tag_id'] = $tag_id;
	$ret_arr['identified'] = 1;
	$ret_arr['ident'] = $ident;
	foreach($ident_arr as $key=>$value) {
		if($key!="tag_id") {
			$ret_arr[$key] = $value;
		}
	}
	if($ident=="location") {
		$location = $ret_arr['location'];
		$timenow = date("H:i:s");
		$cclassq = query("SELECT * FROM classes WHERE starttime<='$timenow' AND endtime>='$timenow' AND location='$location'");
		if(num_rows($cclassq)!=0) {
			$cclassq_arr = fetch_array($cclassq);
			$ret_arr['current_class_info'] = $cclassq_arr;
		}
		if(isset($dat_arr['id'])) {
			$id = $dat_arr['id'];
			$classcode = $ret_arr['current_class_info']['code'];
			$inclassquery = query("SELECT * FROM students WHERE id='$id'");
			$icqarr = fetch_array($inclassquery);
			$cc = explode(",", $icqarr['classes']);
			$points = $icqarr['points'];
			$cci = 0;
			foreach($cc as $c) {
				if($c==$classcode) $cci++;
			}
			if(num_rows($cclassq)!=0 && $cci==1) {
				$checkinquery = query("SELECT * FROM locationcheck WHERE id='$id' AND tag_id='$tag_id' AND classcode='$classcode'");
				if(num_rows($checkinquery)==0) {
					$ret_arr['id'] = $id;
					if(num_rows(query("SELECT * FROM checkregulator WHERE id='$id' AND tag_id='$tag_id' AND classcode='$classcode'"))==0) {
						query("INSERT INTO locationcheck VALUES('$id', '$tag_id', '$classcode')");
						$ret_arr['checkState'] = 1;
					}
					else {
						$ret_arr['checkState'] = 3;
					}
					$ret_arr['points'] = $points;
				}
				else {
					query("DELETE FROM locationcheck WHERE id='$id'");
					query("INSERT INTO checkregulator VALUES('$id', '$tag_id', '$classcode')");
					query("UPDATE students SET points=points+1 WHERE id='$id'");
					$ret_arr['id'] = $id;
					$ret_arr['checkState'] = 2;
					$ret_arr['points'] = $points++;
				}
			}
		}
	}
	returnWithArray($ret_arr);
	return;
}

function compareScheduleWithPeer($dat_arr) {
	$myid = $dat_arr['my_id'];
	$peerid = $dat_arr['peer_id'];
	$myquery = query("SELECT * FROM students WHERE id = '$myid'");
	$peerquery = query("SELECT * FROM students WHERE id = '$peerid'");
	$myqnum = num_rows($myquery);
	$peerqnum = num_rows($peerquery);
	if($myqnum==0 || $peerqnum==0) {
		$error = "";
		if($myqnum!=0 && $peerqnum==0) {
			$error = "My Student ID does not exist";
		}
		else if ($myqnum==0 && $peerqnum!=0) {
			$error = "Peer Student ID does not exist";
		}
		else {
			$error = "Student IDs does not exist";
		}
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = $error;
		returnWithArray($ret_arr);
		return;
	}
	$myq_arr = fetch_array($myquery);
	$peerq_arr = fetch_array($peerquery);
	$myclasses = explode(",", $myq_arr['classes']);
	$peerclasses = explode(",",$peerq_arr['classes']);
	$commonclasses = array();
	foreach($myclasses as $class) {
		foreach($peerclasses as $p_class) {
			if($class==$p_class) {
				$commonclasses[] = $p_class;
			}
		}
	}
	$ret_arr = array();
	$ret_arr['function'] = __FUNCTION__;
	$ret_arr['error'] = 0;
	$ret_arr['my_id'] = $myid;
	$ret_arr['peer_id'] = $peerid;
	$ret_arr['classes'] = $commonclasses;
	returnWithArray($ret_arr);
	return;
}

function loginWithCredentials($dat_arr) {
	$id = $dat_arr['id'];
	$password = $dat_arr['password'];
	if(strlen($id)!=9) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "Student ID must be 9 digits long";
		returnWithArray($ret_arr);
		return;
	}
	if(strlen($password)!=32) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "Malformed password paramater";
		returnWithArray($ret_arr);
		return;
	}
	$query = query("SELECT * FROM students WHERE id='$id' AND password='$password'");
	$q_arr = fetch_array($query);
	if(num_rows($query)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 0;
		$ret_arr['auth'] = 0;
		returnWithArray($ret_arr);
		return;
	}
	else {
		$sessionid = rand(100000000000,999999999999);
		while(num_rows(query("SELECT * FROM sessions WHERE sid='$sessionid'"))>0) {
			$sessionid = rand(100000000000,999999999999);
		}
		query("INSERT INTO session SET VALUES('$sessionid', '$id', now(), now())");
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 0;
		$ret_arr['auth'] = 1;
		$ret_arr['id'] = $id;
		$ret_arr['sid'] = $sessionid;
		$ret_arr['firstname'] = $q_arr['firstname'];
		$ret_arr['lastname'] = $q_arr['lastname'];
		$ret_arr['points'] = $q_arr['points'];
		returnWithArray($ret_arr);
		return;
	}
}

function getPoints($dat_arr) {
	$id = $dat_arr['id'];
	if(strlen($id)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "Student ID must be 9 digits long";
		returnWithArray($ret_arr);
		return;
	}
	$point_query = query("SELECT points FROM students WHERE id='$id'");
	$pointarr = fetch_array($point_query);
	$ret_arr = array();
	$ret_arr['function'] = __FUNCTION__;
	$ret_arr['error'] = 0;
	$ret_arr['points'] = $pointarr['points'];
	returnWithArray($ret_arr);
	return;
}

function searchForStudent($dat_arr) {
	//0 - Search using or
	//1 - Search using and
	$id = $dat_arr['id'];
	$firstname = $dat_arr['firstname'];
	$lastname = $dat_arr['lastname'];
	$search_type = (int)$dat_arr['search_type'];
	if($id==NULL && $firstname==NULL && $lastname==NULL) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "All search paramaters cannot be blank";
		returnWithArray($ret_arr);
		return;
	}
	$query;
	if($search_type==0) {
		$query = query("SELECT id, firstname, lastname FROM students WHERE id='$id' OR firstname='$firstname' OR lastname='$lastname'");
	}
	else {
		$query = query("SELECT id, firstname, lastname FROM students WHERE id='$id' AND firstname='$firstname' AND lastname='$lastname'");
	}
	$search_result;// = array();
	while($sr = fetch_array($query)) {
		$search_result = $sr;
	}
	$ret_arr = array();
	$ret_arr['function'] = __FUNCTION__;
	$ret_arr['error'] = 0;
	$ret_arr['result'] = $search_result;
	
	//Should be $ret_arr
	returnWithArray($search_result);
	return;
}

function getGoldPointValue($dat_arr) {
	$id = $dat_arr['id'];
	if(strlen($id)!=9) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "Student ID must be 9 digits long";
		returnWithArray($ret_arr);
		return;
	}
	$query = query("SELECT * FROM students WHERE id='$id'");
	if(num_rows($query)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 1;
		$ret_arr['reason'] = "Student ID does not exist";
		returnWithArray($ret_arr);
		return;
	}
	$gpquery = query("SELECT * FROM goldpoints WHERE id='$id'");
	$gpresult = fetch_array($gpquery);
	if(num_rows($gpquery)==0) {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 0;
		$ret_arr['id'] = $id;
		$ret_arr['value'] = 0;
		returnWithArray($ret_arr);
		return;
	}
	else {
		$ret_arr = array();
		$ret_arr['function'] = __FUNCTION__;
		$ret_arr['error'] = 0;
		$ret_arr['id'] = $id;
		$ret_arr['value'] = $gpresult['value'];
		returnWithArray($ret_arr);
		return;
	}
}

function returnWithArray($arr) {
	echo base64_encode(json_encode($arr));
}
?>
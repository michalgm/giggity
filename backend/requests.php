<?php
if (! isIncluded()) {
  header('Content-type: application/json');
}

set_error_handler('handleError');
require_once('config.php');

require_once 'vendor/autoload.php';





// $setgooglecal = 0;

// if ($setgooglecal) {
//   if (isset($_GET['code'])) {
//     $client->authenticate($_GET['code']);
//     $_SESSION['token'] = $client->getAccessToken();
//     print_r($_SESSION); exit();
//   } else {
//     $auth_url = $client->createAuthUrl();
//     header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
//   }
// }

if(isset($argv[1])) {
  $action = $argv[1];
  if ($action == 'addRehearsal') {
    $date = isset($argv[2]) ? $argv[2] : null;
    addRehearsal($argv[2]);
  }
  exit();
}

if (isset($_SERVER['CONTENT_TYPE']) && strstr($_SERVER['CONTENT_TYPE'],'application/json')) {
  $request = json_decode(file_get_contents('php://input'), true);
} else {
  $request = $_REQUEST;
}

if(isset($request['action'])) {
  $function = 'gigs_'.$request['action'];
  if (function_exists($function)) {
    // if ($function == 'gigs_fetchGigs') {
    //   checkRehearsals();
    // }
    $data = $function($request);
    setResponse(1, 'Success', $data);
  } else {
    trigger_error('"'.$request['action'].'" is an invalid method.', E_USER_ERROR);
  }
} else {
  trigger_error('No action specified', E_USER_ERROR);
}

function getGigTextDescription($gig, $type='email') {
  global $giggety_url;
  $gig_details = "";
  $gig['event_time'] = "$gig[start_time] - $gig[end_time]";
  $gig['band_play_time'] = "$gig[band_start] - $gig[band_end]";
  $url = "$giggety_url#/gigs/$gig[gig_id]";
  #$url = preg_replace('/backend\/requests.php/', "#/gigs/$gig[gig_id]", "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

  if($type == 'email') {
    foreach(array('description', 'date', 'event_time', 'band_play_time', 'location', 'who', 'contact', 'url') as $key) {
      $gig_details .= str_replace('_', ' ', strtoupper($key)).": $gig[$key]\n\n";
    }
    $gig_details.="---=== DETAILS ===---\n\n$gig[details]";
  } else {
    $members = gigs_fetchMembers();
    $availables = array('Yes'=>'', 'No'=>'', 'Maybe'=>'', 'Unknown'=>'');
    foreach($gig['availability'] as $member) {
      $availables[$member['available']] .= "$member[name], ";
    }
    $gig["who's_coming"] = "\n\tYes: $availables[Yes]\n\tMaybe: $availables[Maybe]\n\tNo: $availables[No]";
    foreach(array('tactical', 'musical') as $role) {
      if (isset($gig[$role]) && $gig[$role]) {
        $gig[$role] = $members[$gig[$role]]['name'];
      }
    }
    foreach(array('event_time', 'meet_time', 'band_play_time', 'location', 'tactical', 'musical', 'colors', "who's_coming", 'notes') as $key) {
      $label = str_replace('_', ' ', strtoupper($key));
      if ($gig['type'] != 'gig') {
        if (! in_array($key, array('location', 'tactical', "who's_coming", 'notes'))) { continue; }
        if ($key == 'tactical') { $label = 'FACILITATOR'; }
      }
      $gig_details .= "$label: $gig[$key]\n\n";
    }
    if ($gig['type'] == 'gig') {
      $gig_details.="---=== DETAILS ===---\n\nDESCRIPTION: $gig[description]\n\nWHO: $gig[who]\n\nCONTACT: $gig[contact]\n\nURL: $gig[url]\n\n$gig[details]";
    }
  }

  $gig_details = "Gig link: $url\n\nSet Availability:\n\tAvailable:   $url/Yes\n\tUnavailable: $url/No\n\tMaybe:       $url/Maybe\n\n$gig_details";

  return $gig_details;
}

function gigs_deleteGig($request) {
  $gig_id = dbEscape($request['gig_id']);
  $gig = gigs_fetchGig($request);
  deleteFromCalendar($gig, 'private');
  deleteFromCalendar($gig, 'public');
  dbWrite("update gigs set deleted=1 where gig_id = $gig_id");
}

function gigs_saveGig($request) {
  $gig_id = dbEscape(isset($request['gig_id']) ? $request['gig_id'] : '');
  $fields = array();
  $gig = $request['data'];
  foreach(array('title','description','date','start_time','end_time','meet_time', 'band_start','band_end','location','who','contact', 'details', 'tactical', 'musical', 'approved', 'public_description', 'notes', 'colors', 'type', 'url') as $key) {
    $gig[$key] = isset($gig[$key]) ? $gig[$key] : "";
  }
  $gig['public_title'] = isset($gig['public_title']) && $gig['public_title'] ? $gig['public_title'] : $gig['title'];
  unset($gig['availability']);
  unset($gig['gig_data']);
  $gig['type'] = isset($gig['type']) && $gig['type'] ? $gig['type'] : 'gig';
  $gig['meet_time'] = isset($gig['meet_time']) && $gig['meet_time'] ? $gig['meet_time'] : $gig['band_start'];

  if ($gig['type'] == 'rehearsal') {
    $gig['start_time'] = $gig['band_start'];
    $gig['meet_time'] = $gig['band_start'];
    $gig['end_time'] = $gig['band_end'];
  }
  foreach(array('title','description','date','start_time','end_time','meet_time', 'band_start','band_end','location','who','contact', 'tactical', 'musical', 'approved', 'public_description', 'notes', 'colors', 'type', 'url') as $key) {
    if(isset($gig[$key])) {
      if (in_array($key, array('start_time','end_time','meet_time', 'band_start','band_end'))) {
        $gig[$key] = date('H:i', strtotime($gig[$key]));
      } elseif ($key == 'date') {
        $date = str_replace('/', '-', $gig[$key]);
        $date = date_create_from_format('m-d-Y', $date) ?: date_create_from_format('Y-m-d', $date);
        //$date = date_create_from_format('Y-m-d', $gig[$key]);
        $gig[$key] = date_format($date, 'Y-m-d');
      }
      array_push($fields, "$key='".dbEscape($gig[$key])."'");
    }
  }
  if (! isset($gig['details']) || $gig['details'] == "") {
    $details = "";
    foreach(array('cause','event_history','arrestable','has_permit','cops', 'people_of_color','relevant_communities','blo_role','other_groups','sound','other') as $key) {
      $details .= strtoupper($key).": ".(isset($gig[$key]) ? $gig[$key] : '')."\n\n";
      // unset($gig[$key]);
    }
    $gig['details'] = $details;
  }
  array_push($fields, "details='".dbEscape($gig['details'])."'");
  if (isset($gig['setlist'])) {
    array_push($fields, "setlist='".dbEscape(implode(",", $gig['setlist']))."'");
  }
  //$title = dbEscape($gig['title']);
  $new_gig = 0;
  if ($gig_id) {
    $res = dbWrite("update gigs set ".implode(",", $fields)." where gig_id=$gig_id");
  } else {
    $res = dbwrite("insert into gigs set ".implode(",", $fields));
    $new_gig = 1;
    $gig_id = getInsertId();
    $gig['gig_id'] = $gig_id;
  }
  $gig = gigs_fetchGig($gig);
  saveToCalendar($gig);
  if ($gig['approved'] == 1) {
    saveToCalendar($gig, 'public');
  } elseif ($gig['approved'] == -1) {
    deleteFromCalendar($gig, 'public');
  }
  if ($new_gig && $gig['type'] == 'gig') {
    sendEmails($gig);
  }
  return $gig;
}

function gigs_fetchGigsList($request) {
  $where = "";
  $user = isset($request['user_id']) ? dbEscape($request['user_id']) : "";
  if (! isset($request['fetchAllGigs']) || $request['fetchAllGigs'] != 'true') {
    $where = " and date > DATE_SUB(NOW(), INTERVAL 1 DAY) ";
  }
  $gigs = dbLookupArray("select * from gigs where deleted = 0 $where order by date, band_start");
  foreach ($gigs as $gig_id => &$gig) {
    $gig['is_tactical'] = $gig['tactical'] == $user;
    $gig['is_musical'] = $gig['musical'] == $user;
    foreach($gig as $key => $value) {
      if (! in_array($key, ['gig_id', 'approved', 'title', 'date', 'type', 'band_start','is_tactical', 'is_musical'])) {
        unset($gig[$key]);
      }
    }
  }
  if ($user) {
    $gigs = fetchAvailability($gigs, $user);
  }
  return $gigs;
}

function gigs_fetchGig($request) {
  $gig_id = isset($request['gig_id']) ? dbEscape($request['gig_id']) : '';
  $times = "";
  foreach(array('meet_time', 'band_start', 'band_end', 'start_time', 'end_time') as $t) {
    $times .= ", time_format($t, '%H:%i') as $t ";
  }
  $gigs = dbLookupArray("select * $times from gigs where gig_id = $gig_id");
  $gigs = fetchAvailability($gigs);
  $gig = array_values($gigs);
  if (isset($gig[0]['setlist']) && is_string($gig[0]['setlist'])) {
    $gig[0]['setlist'] = array_filter(explode(",", $gig[0]['setlist']));
  }
  return $gig[0];
}

function fetchAvailability($gigs, $user=false) {
  global $addressbook_table;
  if ($gigs) {
    $where = " and a.gig_id in(".implode(',', array_keys($gigs)).")";
    if ($user) {
      $where .= " and b.id = ".dbEscape($user);
    }
    $availability = dbLookupArray("select concat(a.gig_id, '_', b.id) as a_id, a.gig_id, b.id as member_id,concat(b.firstname, ' ', b.lastname) as name,  d.available, d.comments, d.concerns, d.other
    from gigs a
    join $addressbook_table.addressbook b
    join $addressbook_table.address_in_groups c using(id)
    left join gigs_availability d on a.gig_id = d.gig_id and b.id = d.member_id
    where deleted=0 and group_id in( 3,10) $where");
    foreach($availability as $av) {
      $gig = &$gigs[$av['gig_id']];
      if(! isset($gig['availability'])) { $gig['availability'] = array(); }
      $av['available'] = $av['available'] == '' ? 'Unknown' : $av['available'];
      $gig['availability'][$av['member_id']] = $av;
    }
  }
  return $gigs;
}

function gigs_setAvailability($request) {
  $gig_id = dbEscape(isset($request['gig_id']) ? $request['gig_id'] : '');
  $availability = $request['data'];
  $member_id = dbEscape(isset($availability['member_id']) ? $availability['member_id'] : '');
  $fields = array();
  if ($member_id == '' || $gig_id == '') { trigger_error('Missing member_id or gig_id', E_USER_ERROR); };

  foreach(array('available', 'comments', 'concerns', 'other') as $key) {
    if(isset($availability[$key])) {
      array_push($fields, "$key='".dbEscape($availability[$key])."'");
    }
  }
  $res = dbwrite("insert into gigs_availability set member_id=$member_id, gig_id=$gig_id, ".implode(",", $fields)." on duplicate key update ".implode(",", $fields));
  $availability = array_values(fetchAvailability(array($gig_id=>array('gig_id'=>$gig_id))));
  $gig = gigs_fetchGig($request);
  saveToCalendar($gig);
  return $availability[0]['availability'][$member_id];
}

function gigs_fetchMembers() {
	global $addressbook_table;
	$members = dbLookupArray("select id, concat(firstname, ' ', lastname) as name, email, if(mobile= '', home, mobile) as phone, group_concat(group_name) as 'groups' from $addressbook_table.addressbook join $addressbook_table.address_in_groups b using(id) left join $addressbook_table.address_in_groups c using(id) left join $addressbook_table.group_list d on c.group_id = d.group_id and c.group_id in (6,7,8,9, null) where b.group_id in(3,10) group by id");
	return $members;
}

function gigs_fetchSongs() {
  $songs = array();
  foreach(scandir("../../../sheetmusic") as $song) {
    if ($song[0] == "." || $song == 'Solo_Scales') { continue; }
    $songs[] = str_replace("_", " ", $song);
  }
  return $songs;
}

function gigs_sendInfoEmail($request) {
  global $emails, $enableEmail;
  if (! $enableEmail) { return true; }
  foreach($emails as $email_address) {
    mail($email_address, $request['data']['subject'], $request['data']['body'], "From: BLO-bot <brassliberation@gmail.com>\r\nReply-To: blo@lists.riseup.net\r\nContent-type: text/plain; charset=utf-8\r\n");
  }
  return true;
}

function setResponse($statusCode, $statusString, $data="") {
  $response = array('statusCode'=>$statusCode, 'statusString'=>$statusString, 'data'=>$data);
  //if php version < 5.3.0 we need to emulate the object string
  if (PHP_MAJOR_VERSION <= 5 & PHP_MINOR_VERSION < 3){
    print __json_encode($response);
  } else {
    print json_encode($response);
  }

  //if ($statusCode != 1) {
  if (! isIncluded()) { exit; }
  //}
}

function handleError($errno, $errstr, $errfile, $errline, $errcontext=null) {
  global $debug;
  $debug = 1;
  $data = array();
  if ($debug) { $errstr = "'$errstr' in file $errfile line $errline "; } //(".print_r($errcontext, 1).")"; }
  setResponse($errno, "$errstr", $data);
  return true;
}


function saveToCalendar($gig, $calendartype='private') {
	// print_r($gig_details = getGigTextDescription($gig, 'calendar')); exit();

	// return; //FIXME
	global $calendars;

	extract($calendars[$calendartype]);

	if ($calendartype == 'public' && ! $gig['public_description']) { return; }

	$cal = getGoogleClient();
	$event = new Google_Service_Calendar_Event();
	$gig_details = "";
	$title = $gig['title'];
	if ($calendartype == 'private') {
		if ($gig['type'] == 'gig') {
			$prefix = 'Proposed';
			if ($gig['approved'] == 1) {
				$prefix = 'Gig';
			} else if ($gig['approved'] == -1) {
				$prefix = 'Declined';
			}
			$title = "$prefix: $title";
		} else if ($gig['tactical']) {
			$members = gigs_fetchMembers();
			$title .= ": ".$members[$gig['tactical']]['name'];
		}
		$gig_details = getGigTextDescription($gig, 'calendar');
		if (! $gig[$start_field]) { $start_field = 'band_start'; }
	} else {
		$gig_details = $gig['public_description'];
   	}

	$event->setSummary($title);
	$event->setLocation($gig['location']);
	$event->setStart(newDateTime($gig['date'], $gig[$start_field]));
	$event->setEnd(newDateTime($gig['date'], $gig[$end_field]));
	$event->setDescription($gig_details);
	$createdEvent = '';
	try {
		if ($gig[$id_field]) {
			$event->setSequence(time());
			$createdEvent = $cal->events->update($calendar_id, $gig[$id_field], $event);
		} else {
			$createdEvent = $cal->events->insert($calendar_id, $event);
			$google_gig_id = $createdEvent->getId();
			dbwrite("update gigs set $id_field = '".dbEscape($google_gig_id)."' where gig_id = $gig[gig_id]");
		}
	} catch (Exception $e) {
		//trigger_error('Error creating google calendar entry: '.$e->getMessage(), E_USER_ERROR);
	}
	if(! $createdEvent) {
		//trigger_error('Error creating google calendar entry', E_USER_ERROR);
	};
}

function deleteFromCalendar($gig, $calendartype='private') {
	global $calendars;
	extract($calendars[$calendartype]);
	if(! $gig[$id_field]) { return; }
	$cal = getGoogleClient();
	$event = new Google_Service_Calendar_Event();
	$cal->events->delete($calendar_id, $gig[$id_field]);
	dbwrite("update gigs set $id_field = '' where gig_id = $gig[gig_id]");
}

function getGoogleClient() {
	#require_once('google-api-php-client/src/Google_Client.php');
	#require_once('google-api-php-client/src/contrib/Google_CalendarService.php');

	global $google_api;
	extract($google_api);
	$client = new Google_Client();
	#$client->setUseObjects(true);
	$client->setAccessType('offline');
	$client->addScope(Google_Service_Calendar::CALENDAR);
	$client->setApplicationName($ApplicationName);
	$client->setClientId($ClientId);
	$client->setClientSecret($ClientSecret);
	$client->setRedirectUri($RedirectUri);
	$client->refreshToken($accessToken);
	$cal = new Google_Service_Calendar($client);
	return $cal;
}

function newDateTime($date, $time) {
	date_default_timezone_set('America/Los_Angeles');
	$datetime = new Google_Service_Calendar_EventDateTime();
	$date = date('c', strtotime("$date $time"));
	$datetime->setDateTime($date);
	$datetime->setTimeZone('America/Los_Angeles');
	return $datetime;
}

// function checkRehearsals() {
//   $number = 6;
//   $x = 1;
//   $default_location = 'Greenpeace Warehouse';
//   $default_time = '17:00';
//   $default_end = '20:00';
//
//   $count = fetchValue("select count(*) as count from gigs where type='rehearsal' and deleted = 0 and date > DATE_SUB(NOW(), INTERVAL 1 DAY)") ?: 0;
//   $time = time();
//   while ($x <= $number) {
//     $time = strtotime("next sunday", $time);
//     $date = date("Y-m-d", $time);
//     if ($count - $x < 0) {
//       dbwrite("insert ignore into gigs set date = '$date', type='rehearsal', title='Rehearsal', band_start = '$default_time', band_end='$default_end', location = '$default_location'");
//       $id = getInsertId();
//       $request['gig_id'] = $id;
//       $gig = gigs_fetchGig($request);
//       saveToCalendar($gig);
//     }
//     $x++;
//   }
// }

function addRehearsal($date) {
		$default_location = 'Greenpeace Warehouse';
		$default_time = '16:00';
		$default_end = '19:00';

    if (! $date) {
      $date = date('Y-m-d', strtotime('next sunday + 6 weeks'));
    }
    $count = fetchValue("select count(*) as count from gigs where type='rehearsal' and date = '$date'") ?: 0;
    if ($count == 0) {
      $request['data'] = array(
        'date'=>$date,
        'type'=>'rehearsal',
        'title'=>'Rehearsal',
        'band_start'=>$default_time,
        'band_end'=>$default_end,
        'location'=>$default_location
      );
      gigs_saveGig($request);
    }
}

function sendEmails($gig) {
  global $emails, $enableEmail;
  if (! $enableEmail) { return; }
  $gig_details = getGigTextDescription($gig, 'email');
  $title = "Proposed Gig - $gig[date] - $gig[title]";
  foreach($emails as $email_address) {
    mail($email_address, $title, $gig_details, "From: BLO-bot <brassliberation@gmail.com>\r\nReply-To: blo@lists.riseup.net\r\nContent-type: text/plain; charset=utf-8\r\n");
  }
}

function isIncluded() {
  $f = get_included_files();
  return $f[0] != __FILE__;
}

?>

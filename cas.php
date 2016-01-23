<?php
  
##
## Note:  This function uses the "header" command, which 
##        MUST be called before anything else is written
##        to the screen.  i.e. make this the first thing
##        you do.
##
##        These functions do not require arguments to be
##        passed to them
##
##        Returns variables $sUin and $sNetid
##

function getCAS($cas_base, $casIP) {
  
  ##
  ## Set CAS server variables
  ##
  
//  $cas_base = $sugar_config['CAS_Server'];
  $cas_login = "cas/login";
  $cas_check = "cas/serviceValidate";    // cas 2.0 method (xml return)
  $cas_logout = "cas/logout";

  $SERVER_NAME = $_SERVER['SERVER_NAME'];
  $REQUEST_URI = $_SERVER['REQUEST_URI'];
  $PHP_SELF = $_SERVER['PHP_SELF'];
  $QUERY_STRING = $_SERVER['QUERY_STRING'];
  
	  if (isset($_SERVER['HTTPS'])) {
		$http = "https";
	  } else {
		$http = "http";
	  }

  ##
  ## Validate through CAS
  ##

  ##
  ## Get the CAS ticket
  ##
  if (isset($_GET['ticket'])) {
    $ticket = $_GET['ticket'];
  }  else {
	  $ticket="";
  }

  ##
  ## Separate ticket from other GET variables
  ##
    
	if ( preg_match("/&ticket=/", $QUERY_STRING) || ($QUERY_STRING && !$ticket) ) {
		list ($getVars, $ticket) = split("&ticket=", $QUERY_STRING);
		
		$getVars = "?" . $getVars;
		$myService = $PHP_SELF . $getVars;
	} else {
		$myService = $PHP_SELF;
	}

	$myService = urlencode($myService);
	
  if ($ticket) {
    $file = @file("https://$cas_base/$cas_check?service=$http://$SERVER_NAME$myService&ticket=$ticket");
	//echo "<br>https://$cas_base/$cas_check?service=$http://$SERVER_NAME$myService&ticket=$ticket<br>";	

	if (!$file) {
		  $file = file("https://$casIP/$cas_check?service=$http://$SERVER_NAME$myService&ticket=$ticket");
		//	echo "<br>2<br>";		  
		}
		
		if (!$file) {  // this still needs work to include the error function and closing string
		  die("The authentication process failed to validate through CAS.");
		}
  } 
  else {
    $action="https://$cas_base/$cas_login?service=$http://$SERVER_NAME$myService";
    header("Location: $action");
  }

  ##
  ## Now parse the xml return
  ##

  // general vars
  $sNetid = "";
  $sUin = "";
  $sFail = "";
  $casNetid = "";
  $casUIN = "";
  $arItems = array();
  $itemCount = 0;  
 
  // parse xml, send to functions
  $xml_parser = xml_parser_create();
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  // loop through CAS response stream
  if ($file) {
    foreach ($file as $data) {
      if (!xml_parse($xml_parser, $data)) {
        die(sprintf("XML error: %s from CAS server at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
      }
    }
  } 
  xml_parser_free($xml_parser);
}

function startElement($parser, $name, $attrs) {
  global $curTag;
  $curTag .= "^$name";
}

function endElement($parser, $name) {
  global $curTag;
  $caret_pos = strrpos($curTag,'^');
  $curTag = substr($curTag,0,$caret_pos);
}
    
// get the xml information
function characterData($parser, $data) {
  global $curTag;
  global $sNetid, $sUin, $sFail, $casNetid, $casUIN;
  
  $netidKey = "^CAS:SERVICERESPONSE^CAS:AUTHENTICATIONSUCCESS^CAS:USER";
  $uinKey = "^CAS:SERVICERESPONSE^CAS:AUTHENTICATIONSUCCESS^CAS:ATTRIBUTES^CAS:TAMUEDUPERSONUIN";

  if ($curTag == $netidKey) {
    $sNetid = $data;
	$_SESSION['sNetid'] = $sNetid;
  $_SESSION['loginID'] = $sNetid; // store the net id data
  } elseif ($curTag == $uinKey) {  
    $sUin = $data;

  }
}

?>

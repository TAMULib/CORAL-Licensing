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

function getCAS($cas_base) {
  ##
  ## CAS URL
  ##
  $cas_base = "https://cas.tamu.edu/cas";
  ##
  ## Service Variables
  ##

  $server = $_SERVER['SERVER_NAME'];
  $path = $_SERVER['PHP_SELF'];
  $http = isset($_SERVER['HTTPS']) ? "https" : "http";
  $serviceArgs = split("&", $_SERVER['QUERY_STRING']);

  ##
  ## Get the CAS Ticket, if it has been set
  ##
  
  $ticket = isset($_GET['ticket']) ? $_GET['ticket'] : "";
  
  ##
  ## If the Ticket is set, pop the last argument(ticket) from the query string
  ##
  
  if ($ticket) {
    array_pop($serviceArgs);
  }

  ##
  ## Build the service URL; If there are still arguments in the query string,
  ## combine them and add to the end of the url. Encode the URL
  ##
  
  if (count($serviceArgs) > 0) {
    $serviceUrl = "$http://$server$path?" . join("&", $serviceArgs);
  } else {
    $serviceUrl = "$http://$server$path";
  }
  $encodedServiceUrl = urlencode($serviceUrl);

  ##
  ## If the ticket has been set, call serviceValidate or proxyValidate, depending on the
  ## ticket's 2-character prefix; else redirect to the CAS login page
  ##

  if ($ticket) {
    if (substr($ticket, 0, 2) == 'ST') {
      $file = file("$cas_base/serviceValidate?service=$encodedServiceUrl&ticket=$ticket");
    } elseif (substr($ticket, 0, 2) == 'PT') {
      $file = file("$cas_base/proxyValidate?service=$encodedServiceUrl&ticket=$ticket");
    }
    if (!$file) {  // this still needs work to include the error function and closing string
      die("The authentication process failed to validate through CAS.");
    }
  } 
  else {
    $action="$cas_base/login?service=$encodedServiceUrl";
    header("Location: $action");
    exit;
  }

  ##
  ## Debug CAS Response
  ##
  
  global $debug;
  $debug = false;
  if ($debug) {
    var_dump($file);
    echo "<pre>\n";
    echo "Debug the CAS response:\n";
    print_r($file);
   echo "</pre>\n";
  }

  ##
  ## Initialize NetID and UIN
  ##
  
  $sNetid = "";
  $sUin = "";
  
  ##
  ## Parse the CAS Response
  ##

  ##
  ## Initialize XML Parser, set callbacks
  ##
  
  $xml_parser = xml_parser_create();
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");


  ##
  ## Loop through CAS response stream
  ##
  
  if ($file) {
    foreach ($file as $data) {
      if (!xml_parse($xml_parser, $data)) {
        die(sprintf("XML error: %s from CAS server at line %d", xml_error_string(xml_get_error_code($xml_parser)), xml_get_current_line_number($xml_parser)));
      }
    }
  } 
  xml_parser_free($xml_parser);
}

##
## XML Handler Functions
##

function startElement($parser, $name, $attrs) {
  global $curTag;
  $curTag .= "^$name";
}

function endElement($parser, $name) {
  global $curTag;
  $caret_pos = strrpos($curTag,'^');
  $curTag = substr($curTag,0,$caret_pos);
}
    

##
## Set $sNetid and $sUin
##

function characterData($parser, $data) {
//echo "i am here";
  global $curTag;
  global $sNetid, $sUin;
  $netidKey = "^CAS:SERVICERESPONSE^CAS:AUTHENTICATIONSUCCESS^CAS:ATTRIBUTES^CAS:TAMUEDUPERSONNETID";
  $uinKey = "^CAS:SERVICERESPONSE^CAS:AUTHENTICATIONSUCCESS^CAS:ATTRIBUTES^CAS:TAMUEDUPERSONUIN";
  if ($curTag == $netidKey) {
//     echo "I found a key";
//     echo " $data";
    $sNetid = $data;
	  $_SESSION['Netid'] = $sNetid; // store the net id data
    $_SESSION['loginID'] = $sNetid; // store the net id data

//    echo "The session: " . $_SESSION['Netid'];
//    echo "_SESSION[loginID]" . $_SESSION['loginID'];
  }
  elseif ($curTag == $uinKey) {  
    $sUin = $data;
  }
}

?>

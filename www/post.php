<?php
/**
 * Configure MySQL with:
 * CREATE USER 'abused'@'%' IDENTIFIED BY 'abusedpass';
 * GRANT SELECT, INSERT, UPDATE ON devicemanagement.devices TO 'abused'@'%';
 * GRANT SELECT, INSERT, UPDATE ON devicemanagement.address TO 'abused'@'%';
 */

class AbusedResult {
  private $raw_xml;
  private $mac;
  private $ip;
  private $request_protocol;
  private $request;
  private $datetime;
  private $custom_fields_size;
  private $custom_fields;
  private $upnp_headers_size;
  private $upnp_headers;

  function __construct($raw_xml, $mac, $ip, $request_protocol, $request, $datetime, $custom_fields_size, $custom_fields, $upnp_headers_size, $upnp_headers) {
    $this->raw_xml = $raw_xml;
    $this->mac = $mac;
    $this->ip = $ip;
    $this->request_protocol = $request_protocol;
    $this->request = $request;
    $this->datetime = $datetime;
    $this->custom_fields_size = $custom_fields_size;
    $this->custom_fields = $custom_fields;
    $this->upnp_headers_size = $upnp_headers_size;
    $this->upnp_headers = $upnp_headers;
  }

  public function setRaw_xml($raw_xml) {
    if($this->raw_xml == null) {
      $this->raw_xml = $raw_xml;
    }
  }

  public static function parse_xml($raw_xml) {
    $parsed_xml = simplexml_load_string($raw_xml);
    if(false === $parsed_xml) {
      $msg = "Error: Failed to parse XML\n";
      printf($msg);
      error_log($msg);
      foreach(libxml_get_errors() as $error) {
        printf($error->message);
        error_log($error->message);
      }
      exit(1);
    }

    $abused_results = array();
    for($i = 0; $i < count($parsed_xml->message); $i++) {
      $abused_result = AbusedResult::parse_abused_message($parsed_xml->message[$i]);
      $abused_result->setRaw_xml($raw_xml);
      $abused_results[] = $abused_result;
    }

    return $abused_results;
  }

  public static function parse_abused_message(SimpleXMLElement $message) {

    /* Gather all custom_fields */
    $custom_fields = array();
    $custom_field_array = $message->custom_fields->custom_field;
    $custom_fields_size = count($custom_field_array);
    for($i = 0; $i < $custom_fields_size; $i++) {
      $custom_field_attributes = $custom_field_array[$i]->attributes();
      $custom_field_name = trim((string) $custom_field_attributes['name']);
      $custom_field_value = trim((string) $custom_field_array[$i]);
      $custom_fields[] = array($custom_field_name, $custom_field_value);
    }

    /* Gather all headers */
    $headers = array();
    $header_array = $message->headers->header;
    $headers_size = count($header_array);
    for($i = 0; $i < $headers_size; $i++) {
      $header_attributes = $header_array[$i]->attributes();
      $header_name = trim((string) $header_attributes['typeStr']);
      $header_value = trim((string) $header_array[$i]);
      $headers[] = array($header_name, $header_value);
    }

    /* Get request protocol */
    $request_attributes = $message->request->attributes();
    $request_protocol = trim((string) $request_attributes['protocol']);

    /* Build AbusedResult object */
    $abused_result = new AbusedResult(
      null,
      trim((string) $message->mac),
      trim((string) $message->ip),
      $request_protocol,
      trim((string) $message->request),
      trim((string) $message->datetime),
      $custom_fields_size,
      $custom_fields,
      $headers_size,
      $headers
    );

    return $abused_result;
  }

  /* Make magic getters for all fields */
  public function __get($method_name) {
    $match = preg_match("/^get([A-Za-z_]+)$/", $method_name, $matches);
    if($match) {
      try {
        return $this->$$matches[1];
      } catch(Exception $e) {
        throw new Exception(sprintf("Field '%s' does not exist", $matches[1]));
      }
    }
  }

}

/* Get the XML from the POST data */
$raw_xml = file_get_contents('php://input');

/* Parse the XML into an array of AbusedResult objects */
$abused_results = AbusedResult::parse_xml($raw_xml);

// TODO: remove me!
exit(0);

/**
 * Retrieves the IP address from the abused_results array
 */
function get_ip_from_mac($address) {
  foreach($abused_results as $abused_result) {
    if($address == $abused_result) {
      return $abused_result->getMac_address();
    }
  }
}

/**
 * Retrieves a parameter from the camera
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 * @param parameter The parameter group
 *
 * @return Returns the parameter's current value as a string
 */
function get_axis_device_parameter($address, $username, $password, $parameter) {
  $result = "";
  try {
    $ip = null;
    if($this->is_ip_address($address))
      $ip = $address;
    elseif($this->is_mmac_address($address))
      $ip = $this->get_ip_from_mac($address);
    else
      throw new Exception("The given address is not a valid IPv4, IPv6 or MAC address", 1);
    if($ip == null)
      throw new Exception("Device is not online", 1);
    $context = stream_context_create(array(
    'http' => array(
        'header'  => "Authorization: Basic " . base64_encode($username.":".$password),
        'timeout' => "5"
      )
    ));
    /* TODO: set up error handling using:
    set_error_handler(
      create_function(
        '$severity, $message, $file, $line',
        'throw new Exception($message, $severity, $severity, $file, $line);'
      )
    );
    */
    // remove '@' for debugging
    $result = @file_get_contents("http://".$ip."/axis-cgi/param.cgi?action=list".($parameter?"&group=".$parameter:""), false, $context);
    if($result === false) {
      if(isset($http_response_header) && strpos($http_response_header[0], "401"))
        throw new Exception("Wrong credentials", 1);
      throw new Exception("Connection failed", 1);
    } else if(preg_match("/^# Error:/", $result)) {
      throw new Exception($result, 1);
    }
  } catch(Exception $e) {
    throw new Exception("Controller::get_device_parameter(): ".$e->getMessage(), $e->getCode());
  }
  return trim(substr($result, strlen($parameter)+1));
}


/**
 * Checks if the passed argument is a valid MAC address
 *
 * @param $mac The MAC address to be checked
 *
 * @return Returns true if it is a valid MAC address, otherwise returns false
 */
function is_mac_address($mac) {
  if(preg_match("/^[0-9a-fA-F]{2}(?=([:.]?))(?:\\1[0-9a-fA-F]{2}){5}$/", $mac))
    return true;
  return false;
}

/**
 * Checks if the passed argument is a valid IPv4 address
 *
 * @param $ip The IPv4 address to be checked
 *
 * @return Returns true if it is a valid IPv4 address, otherwise returns false
 */
function is_ipv4_address($ip) {
  if(preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $ip)) {
    $ipParts=explode(".",$ip);
    foreach($ipParts as $ipPart) {
      if(intval($ipPart)<0 || intval($ipPart)>255)
        return false;
    }
    return true;
  }
  return false;
}

/**
 * Check if a given IPv6 address is valid
 *
 * @param $ip The IPv6 address to be checked
 *
 * @return Returns true if the given IPv6 is valid, otherwise returns false
 */
function is_ipv6_address($ip) {
  /* Exit for localhost */
  if (strlen($ip) < 3)
    return $ip == '::';
  /* Check if end part is in IPv4 format */
  if (strpos($ip, '.')) {
    $lastcolon = strrpos($ip, ':');
    if (!($lastcolon && $this->is_ipv4_address(substr($ip, $lastcolon + 1))))
      return false;
    /* Replace the IPv4 part with IPv6 dummy part */
    $ip = substr($ip, 0, $lastcolon) . ':0:0';
  }
  /* Check for uncompressed address format */
  if (strpos($ip, '::') === false) {
    return preg_match('/^(?:[a-f0-9]{1,4}:){7}[a-f0-9]{1,4}$/i', $ip);
      }
  //count colons for compressed format
  if (substr_count($ip, ':') < 8) {
    return preg_match('/^(?::|(?:[a-f0-9]{1,4}:)+):(?:(?:[a-f0-9]{1,4}:)*[a-f0-9]{1,4})?$/i', $ip);
      }
  return false;
}

/**
 * Check if a given IP address is valid
 *
 * @param $ip The IP address to be checked
 *
 * @return Returns true if the gevin IP is valid, otherwise returns false
 */
function is_ip_address($ip) {
  return $this->is_ipv4_address($ip) || $this->is_ipv6_address($ip);
}

/**
 * Enables/disables the SSH server on the device
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 * @param enable True to enable or false to disable the SSH server
 *
 * @return Returns true on success and false on failure
 */
function set_device_ssh($address, $username, $password, $enable) {
  try {
    if($enable) {
      $enable = "yes";
    } else {
      $enable = "no";
    }
    if(is_device_ssh_capable($address, $username, $password)) {
      throw new Exception("The device is not SSH capable", 1);
    }
    set_device_parameter($address, $username, $password, "Network.SSH.Enabled", $enable);
    return true;
  } catch(Exception $e) {
    throw new Exception(sprintf("set_device_ssh(): %s", $e->getMessage()), $e->getCode());
  }
  return true;
}

/**
 * Enables the SSH server on the device
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 *
 * @return Returns true on success and false on failure
 */
function enable_device_ssh($address, $username, $password) {
  try {
    return set_device_ssh($address, $username, $password, true);
  } catch(Exception $e) {
    throw new Exception(sprintf("enable_device_ssh(): %s", $e->getMessage()), $e->getCode());
  }
  return true;
}

/**
 * Disables the SSH server on the device
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 *
 * @return Returns true on success and false on failure
 */
function disable_device_ssh($address, $username, $password) {
  try {
    return set_device_ssh($address, $username, $password, false);
  } catch(Exception $e) {
    throw new Exception(sprintf("disable_device_ssh(): %s", $e->getMessage()), $e->getCode());
  }
  return true;
}

/**
 * Checks if the device firmware is SSH capable (>=LFP5)
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 *
 * @return Returns true on success and false on failure
 */
function is_device_ssh_capable($address, $username, $password) {
  try {
    $parameter = "Network.SSH.Enabled";
    get_device_parameter($address, $username, $password, $parameter);
  } catch(Exception $e) {
    if(preg_match("/# Error:/", $e->getMessage()))
      return false;
    throw new Exception(sprintf("is_device_ssh_capable(): %s", $e->getMessage()), $e->getCode());
  }
  return true;
}

/**
 * Checks if the device SSH server is enabled
 *
 * @param address The address of the device
 * @param username The username for the device
 * @param password The password for the device
 *
 * @return Returns true on success and false on failure
 */
function is_device_ssh_enabled($address, $username, $password) {
  try {
    $parameter = "Network.SSH.Enabled";
    $result = get_device_parameter($address, $username, $password, $parameter);
  } catch(Exception $e) {
    if(preg_match("/# Error:/", $e->getMessage()))
      return false;
    throw new Exception(sprintf("is_device_ssh_enabled(): %s", $e->getMessage()), $e->getCode());
  }
  if('no' == $result)
    return false;
  return true;
}

/* Try to extract specific information of
   the device in case it is an AXIS device */
$axis_device_default_username = 'root';
$axis_device_default_password = 'pass';
$axis_device_tfw_username     = 'tfwroot';
$axis_device_tfw_password     = 'tfwpass';

/* MySQL host, database and credentials */
$host     = 'localhost';
$database = 'devicemanagement';
$username = 'abused';
$password = 'abusedpass';

/* Connect to MySQL */
$h_sql = new mysqli($host, $username, $password, $database);
if ($h_sql->connect_errno) {
  printf("Failed to connect to MySQL: %s", $h_sql->connect_error);
  exit(1);
}

/* Insert or update for each AbusedResult we have received and parsed */
foreach($abused_results as $abused_result) {
  $query = sprintf("INSERT INTO `devices` VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s') ON DUPLICATE KEY UPDATE",
                 $abused_result->getMacaddress(),
                 $abused_result->getOak(),
                 $abused_result->getName(),
                 $abused_result->getOwner(),
                 $abused_result->getServer(),
                 $abused_result->getModel(),
                 $abused_result->getFirmware(),
                 $abused_result->getCredentials(),
                 $abused_result->getAway_since(),
                 $abused_result->getIp(),
                 $abused_result->getLink(),
                 $abused_result->getStatus(),
                 $abused_result->getType());
  $res = $h_sql->query($query);
  if(false === $res) {
    $msg = sprintf("Failed to query MySQL: %s", $h_sql->error); 
    printf($msg);
    error_log($msg);
  }
}

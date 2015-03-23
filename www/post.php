<?php
require_once('CapabilityManager.php');
require_once('AbusedResult.php');
require_once('SqlConnection.php');

/* Get the XML from the POST data */
$raw_xml = file_get_contents('php://input');

/* MySQL credentials */
$host     = 'localhost';
$database = 'abused';
$username = 'abused';
$password = 'abusedpass';

/* Parse the XML into an array of AbusedResult objects */
$abused_results = AbusedResult::parse_xml($raw_xml);

/* Info for extracting specific information of
   the device in case it is an AXIS device */
$axis_device_default_username = 'root';
$axis_device_default_password = 'pass';

/* Connect to MySQL */
$sqlconnection = new SqlConnection($host, $database, $username, $password);

/* Insert or update for each AbusedResult we have received and parsed */
foreach($abused_results as $abused_result) {
  try {
    $mac = $abused_result->get_mac();
    /* Igore messages that dont have a mac
       (devices that aren't on the local net)*/
    if(empty($mac)) {
      continue;
    }
    /* If ID is not a MAC address then use the
       MAC address as ID (for AXIS compatibility)*/
    $id = $abused_result->get_serial_number();
    if(!$abused_result->is_mac_address($id)) {
      $id = str_replace(':', '', $mac);
    }
    $ipv4 = $abused_result->get_ip();
    $ipv6 = NULL;
    if(!AbusedResult::is_ipv4_address($ipv4)) {
      if(AbusedResult::is_ipv6($ipv4)) {
        $ipv6 = $ipv4;
        $ipv4 = NULL;
      }
      else {
        /* Not an IP address, skip packet*/
        continue;
      }
    }
    $friendly_name = $abused_result->get_custom_field_friendlyName();

    $ssdp_message_type = NULL;
    try {
      /* Fetch the NTS header */
      $ssdp_message_type = $abused_result->get_header_nts();
      /* Explanation: Header 'NTS' is the type
         of ssdp message (ssdp::[hello|alive|bye]) */
      /* Split 'ssdp::<type>' into 'ssdp::' and '<type>'
         and only use the latter */
      
      $ssdp_message_type = explode(':', $ssdp_message_type)[1];
    } catch(Exception $e) {
      /* If message doesn't contain the ssdp type
         then it is broken, skip to next message */
      continue;
    }

    $model_name = $abused_result->get_custom_field_modelName();

    // TODO: make it work with NASes
    /* If it isn't an AXIS device then continue */
    if(false === stripos($model_name, 'axis')) {
      continue;
    }

    $cm = new CapabilityManager(($ipv4 ? $ipv4 : $ipv6),
                                $axis_device_default_username,
                                $axis_device_default_password);

    /* Fetch the firmware version from the device */
    $firmware_version = $cm->get_firmware_version();

    /* Create or retrieve existing ID for the model-firmware combination */
    $model_firmware_id = $sqlconnection->call(sprintf("call add_model_firmware_if_not_exist('%s', '%s')",
                                 $model_name,
                                 $firmware_version))[0][0]['id'];

    /* See if model-firmware combination has
       been probed for capabilities */
    $is_probed = $sqlconnection->call(sprintf("call is_model_firmware_probed('%s')", 
                                           $model_firmware_id))[0][0]['probed'] == 'yes' ? true : false;

    /* If device has not been probed for capabilities
       then probe it and fill the database */
    if(!$is_probed) {
      if($cm->has_sd_disk()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'sd_disk\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_local_storage()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'local_storage\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_pir()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'pir\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_light_control()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'light_control\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_audio()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'audio\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_mic()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'mic\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_ptz()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'ptz\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_dptz()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'dptz\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_speaker()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'speaker\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_wlan()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'wlan\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_status_led()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'status_led\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
      if($cm->has_ir()) {
        $capability_id = $sqlconnection->call('call add_capability_if_not_exist(\'ir\');')[0][0]['id'];
        $sqlconnection->call(sprintf("call add_capability_to_model_firmware('%s', '%s');",
                                     $model_firmware_id,
                                     $capability_id));
      }
    }

  } catch(Exception $e) {
    /* Do noting */
    printf("%S", $e->getMessage());
    continue;
  }

  $query = sprintf("call add_or_update_device('%s', '%s', '%s', '%s', '%s', '%s', '%s')",
                   $id,
                   $mac,
                   $ipv4,
                   $ipv6,
                   $friendly_name,
                   $model_firmware_id,
                   $ssdp_message_type);

  $sqlconnection->call($query);
}


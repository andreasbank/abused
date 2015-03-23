<?php
/**
 * Copyright (C) 2015 by Andreas Bank <andreas.mikael.bank@gmail.com>
 *
 * install.php
 * The systems database installer script.
 * Creates the database, its tables and the needed stored procedures.
 *
 */


function query($sql, $query) {
  $res = $sql->query($query);
  if(false === $res) {
    throw new Exception(sprintf("Failed to query MySQL: %s",
                                $sql->error));
  }
}

$htmlHeader =         "<!DOCTYPE html>\n<html xmlns=\"http://www.w3.org/1999/xhtml\" lang=\"en\" xml:lang=\"en\">\n";
$htmlHeader = sprintf("%s<head>\n\t<title>ABUSED MySQL Databse Installation</title>\n", $htmlHeader);
$htmlHeader = sprintf("%s\t<style type=\"text/css\">\n\ttd {\n\t\ttext-align:left;\n\t}\n\t</style>\n", $htmlHeader);
$htmlHeader = sprintf("%s</head>\n<body style=\"text-align: center;\">\n", $htmlHeader);
$htmlFooter = sprintf("%s</body>\n</html>\n", $htmlHeader);


if(isset($_GET['test_mysql'])) {
  $sql = new mysqli($_GET['h'], $_GET['u'], $_GET['p']);
  if($sql->connect_errno) {
    //header("Content-type: text/html; charset=utf-8", true);
    printf("<span style=\"color:red;\">Failed! (%s)</span>", $sql->connect_error);
  }
  else {
    //header("Content-type: text/html; charset=utf-8", true);
    printf("<span style=\"color:green;\">Success! You can now continue to the next step!</span>");
    $sql->close();
  }
}
else if(isset($_POST['mysql'])) {
  $errorMessage = "\nAn error has occurred:<br />\n";
  $sql = new mysqli($_POST['mysql_host'],
                    $_POST['mysql_username'],
                    $_POST['mysql_password']);
  if($sql->connect_errno) {
    printf("%s%s(connect)%s\n%s", $htmlHeader, $errorMessage, mysql_error(), $htmlFooter);
    exit(1);
  }
  try {

    /* Create database */
    $query = sprintf("CREATE DATABASE `%s`;", $_POST['database_name']);
    query($sql, $query);

    /* Use database */
    $sql->select_db($_POST['database_name']);

    /* Tables */

    /* capability table */
    $query =         "CREATE TABLE `capabilities` (`id` INT NOT NULL AUTO_INCREMENT,\n";
    $query = sprintf("%s`name` VARCHAR(255) NOT NULL UNIQUE,\n", $query);
    $query = sprintf("%sPRIMARY KEY(`id`)) ENGINE=InnoDB;", $query);
    query($sql, $query);

    /* model_firmware table */
    $query =         "CREATE TABLE `model_firmware` (`id` INT NOT NULL AUTO_INCREMENT,\n";
    $query = sprintf("%s`model_name` VARCHAR(255) NOT NULL,\n", $query);
    $query = sprintf("%s`firmware_version` VARCHAR(255) NOT NULL,\n", $query);
    $query = sprintf("%s`last_updated` DATETIME,\n", $query);
    $query = sprintf("%sUNIQUE KEY(`model_name`, `firmware_version`),\n", $query);
    $query = sprintf("%sPRIMARY KEY(`id`)) ENGINE=InnoDB;\n", $query);
    query($sql, $query);

    /* model_firmware_capability table */
    $query =         "CREATE TABLE `model_firmware_capability` (`model_firmware_id` INT NOT NULL,\n";
    $query = sprintf("%s`capability_id` INT NOT NULL,\n", $query);
    $query = sprintf("%sFOREIGN KEY (`model_firmware_id`)\n", $query);
    $query = sprintf("%sREFERENCES `model_firmware`(`id`),\n", $query);
    $query = sprintf("%sFOREIGN KEY (`capability_id`)\n", $query);
    $query = sprintf("%sREFERENCES `capabilities`(`id`),\n", $query);
    $query = sprintf("%sPRIMARY KEY(`model_firmware_id`, `capability_id`)) ENGINE=InnoDB;\n", $query);
    query($sql, $query);
 
    /* devices table */
    $query =         "CREATE TABLE `devices` (`id` VARCHAR(255) NOT NULL,\n";
    $query = sprintf("%s`mac` VARCHAR(255) NOT NULL,\n", $query);
    $query = sprintf("%s`ipv4` VARCHAR(15),\n", $query);
    $query = sprintf("%s`ipv6` VARCHAR(46),\n", $query);
    $query = sprintf("%s`friendly_name` VARCHAR(255),\n", $query);
    $query = sprintf("%s`model_firmware_id` INT,\n", $query);
    $query = sprintf("%s`last_update` DATETIME NOT NULL,\n", $query);
    $query = sprintf("%s`last_upnp_message` ENUM('hello', 'alive', 'bye') NOT NULL,\n", $query);
    $query = sprintf("%sFOREIGN KEY (`model_firmware_id`) REFERENCES `model_firmware` (`id`),\n", $query);
    $query = sprintf("%sPRIMARY KEY (`id`)) ENGINE=InnoDB;\n", $query);
    query($sql, $query);

    /* locked_devices table */
    $query =         "CREATE TABLE `locked_devices`(`device_id` VARCHAR(255) NOT NULL,\n";
    $query = sprintf("%s`locked` TINYINT(1) DEFAULT 1,\n", $query);
    $query = sprintf("%s`locked_by` VARCHAR(255) NOT NULL,\n", $query);
    $query = sprintf("%s`locked_date` DATETIME NOT NULL,\n", $query);
    $query = sprintf("%sFOREIGN KEY (`device_id`)\n", $query);
    $query = sprintf("%sREFERENCES `devices`(`id`)\n", $query);
    $query = sprintf("%sON DELETE CASCADE\n", $query);
    $query = sprintf("%sON UPDATE CASCADE,\n", $query);
    $query = sprintf("%sPRIMARY KEY (`device_id`, `locked_date`)) ENGINE=InnoDB;\n", $query);
    query($sql, $query);

    /* Stored Procedures */

    /* add_capability_if_not_exist */
    $query =         "CREATE PROCEDURE `add_capability_if_not_exist`(IN `v_capability_name` VARCHAR(255),";
    $query = sprintf("%s                                             IN `v_capability_group` VARCHAR(255))\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s  DECLARE found_id INT DEFAULT NULL;\n", $query);
    $query = sprintf("%s  SELECT `id` INTO found_id\n", $query);
    $query = sprintf("%s    FROM `capabilities`\n", $query);
    $query = sprintf("%s    WHERE `name`=v_capability_name\n", $query);
    $query = sprintf("%s      AND `group`=v_capability_group;\n", $query);
    $query = sprintf("%s  IF found_id IS NULL THEN\n", $query);
    $query = sprintf("%s    INSERT INTO `capabilities` (`name`, `group`)\n", $query);
    $query = sprintf("%s      VALUES(v_capability_name, v_capability_group);\n", $query);
    $query = sprintf("%s  END IF;\n", $query);
    $query = sprintf("%s  SELECT `id`\n", $query);
    $query = sprintf("%s    FROM `capabilities`\n", $query);
    $query = sprintf("%s    WHERE `name`=v_capability_name\n", $query);
    $query = sprintf("%s      AND `group`=v_capability_group;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* add_or_update_device */
    $query =         "CREATE PROCEDURE `add_or_update_device`(IN `v_id` VARCHAR(255),\n";
    $query = sprintf("%s                                        IN `v_mac` VARCHAR(17),\n", $query);
    $query = sprintf("%s                                        IN `v_ipv4` VARCHAR(15),\n", $query);
    $query = sprintf("%s                                        IN `v_ipv6` VARCHAR(46),\n", $query);
    $query = sprintf("%s                                        IN `v_friendly_name` VARCHAR(255),\n", $query);
    $query = sprintf("%s                                        IN `v_model_firmware_id` INT,\n", $query);
    $query = sprintf("%s                                        IN `v_last_upnp_message` VARCHAR(5))\n", $query);
    $query = sprintf("%s  SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s  INSERT INTO `devices` VALUES(v_id,\n", $query);
    $query = sprintf("%s                               v_mac,\n", $query);
    $query = sprintf("%s                               v_ipv4,\n", $query);
    $query = sprintf("%s                               v_ipv6,\n", $query);
    $query = sprintf("%s                               v_friendly_name,\n", $query);
    $query = sprintf("%s                               v_model_firmware_id,\n", $query);
    $query = sprintf("%s                               NOW(),\n", $query);
    $query = sprintf("%s                              v_last_upnp_message)\n", $query);
    $query = sprintf("%s   ON DUPLICATE KEY UPDATE\n", $query);
    $query = sprintf("%s     `mac`=v_mac,\n", $query);
    $query = sprintf("%s     `ipv4`=v_ipv4,\n", $query);
    $query = sprintf("%s     `ipv6`=v_ipv6,\n", $query);
    $query = sprintf("%s     `friendly_name`=v_friendly_name,\n", $query);
    $query = sprintf("%s     `model_firmware_id`=v_model_firmware_id,\n", $query);
    $query = sprintf("%s     `last_update`=NOW(),\n", $query);
    $query = sprintf("%s     `last_upnp_message`=v_last_upnp_message;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* add_model_firmware_if_not_exist */
    $query =         "CREATE PROCEDURE `add_model_firmware_if_not_exist`(IN `v_model_name` VARCHAR(255),\n";
    $query = sprintf("%s                                                   IN `v_firmware_version` VARCHAR(255))\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s DECLARE found_id INT DEFAULT NULL;\n", $query);
    $query = sprintf("%s SELECT `id` INTO found_id\n", $query);
    $query = sprintf("%s FROM `model_firmware`\n", $query);
    $query = sprintf("%s WHERE `model_name`=v_model_name\n", $query);
    $query = sprintf("%s AND `firmware_version`=v_firmware_version;\n", $query);
    $query = sprintf("%s IF found_id IS NULL THEN\n", $query);
    $query = sprintf("%s   INSERT INTO `model_firmware` (`model_name`, `firmware_version`, `last_updated`)\n", $query);
    $query = sprintf("%s   VALUES(v_model_name, v_firmware_version, NOW());\n", $query);
    $query = sprintf("%s END IF;\n", $query);
    $query = sprintf("%s SELECT `id`\n", $query);
    $query = sprintf("%s FROM `model_firmware`\n", $query);
    $query = sprintf("%s WHERE `model_name`=v_model_name\n", $query);
    $query = sprintf("%s AND `firmware_version`=v_firmware_version;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* is_model_firmware_probed */
    $query =         "CREATE PROCEDURE `is_model_firmware_probed`(IN `v_model_firmware_id` INT)\n";
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s DECLARE v_count INT DEFAULT 0;\n", $query);
    $query = sprintf("%s SELECT COUNT(*) INTO v_count\n", $query);
    $query = sprintf("%s FROM `model_firmware_capability`\n", $query);
    $query = sprintf("%s WHERE `model_firmware_id`=v_model_firmware_id;\n", $query);
    $query = sprintf("%s IF v_count > 0 THEN\n", $query);
    $query = sprintf("%s   SELECT 'yes' AS `probed`;\n", $query);
    $query = sprintf("%s ELSE\n", $query);
    $query = sprintf("%s   SELECT 'no' AS `probed`;\n", $query);
    $query = sprintf("%s END IF;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* add_capability_to_model_firmware */
    $query =         "CREATE PROCEDURE `add_capability_to_model_firmware` (IN `v_model_firmware_id` INT,\n";
    $query = sprintf("%s                                                     IN `v_capability_id` INT)\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s INSERT INTO `model_firmware_capability`\n", $query);
    $query = sprintf("%s VALUES (\n", $query);
    $query = sprintf("%s   v_model_firmware_id, v_capability_id\n", $query);
    $query = sprintf("%s );\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* delete_inactive_devices */
    $query =         "CREATE PROCEDURE `delete_inactive_devices`(IN `inactive_seconds` INT)\n";
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s DELETE FROM `devices`\n", $query);
    $query = sprintf("%s   WHERE `last_update`<(SELECT NOW()-INTERVAL inactive_seconds SECOND);\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* is_device_locked_internal */
    $query =         "CREATE PROCEDURE `is_device_locked_internal`(IN `v_device_id` VARCHAR(255),\n";
    $query = sprintf("%s                                             INOUT `is_locked` TINYINT(1))\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s SELECT `locked` INTO is_locked\n", $query);
    $query = sprintf("%s   FROM `locked_devices`\n", $query);
    $query = sprintf("%s   WHERE `device_id`=v_device_id\n", $query);
    $query = sprintf("%s     AND `locked`=1;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* lock_device */
    $query =         "CREATE PROCEDURE `lock_device_by_id`(IN `v_device_id` VARCHAR(255),\n";
    $query = sprintf("%s                               IN `v_locked_by` VARCHAR(255))\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN \n", $query);
    $query = sprintf("%s DECLARE is_locked INT DEFAULT 0;\n", $query);
    $query = sprintf("%s CALL `is_device_locked_internal`(v_device_id, is_locked);\n", $query);
    $query = sprintf("%s IF is_locked=1 THEN\n", $query);
    $query = sprintf("%s   SELECT 0 AS `success`;\n", $query);
    $query = sprintf("%s ELSE\n", $query);
    $query = sprintf("%s   INSERT INTO `locked_devices`\n", $query);
    $query = sprintf("%s     VALUES(v_device_id, 1, NOW(), v_locked_by);\n", $query);
    $query = sprintf("%s   SELECT 1 AS `success`;\n", $query);
    $query = sprintf("%s END IF;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* lock_device_by_capability */
    $query =         "CREATE PROCEDURE `lock_device_by_capability`(IN `v_capability` VARCHAR(255),\n";
    $query = sprintf("%s                                             IN `v_user` VARCHAR(255),\n", $query);
    $query = sprintf("%s                                             IN `v_age` INT)\n", $query);
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s   DECLARE v_found_id VARCHAR(12) DEFAULT NULL;\n", $query);
    $query = sprintf("%s   SELECT d.`id` INTO v_found_id\n", $query);
    $query = sprintf("%s   FROM `devices` d,\n", $query);
    $query = sprintf("%s        `model_firmware` mf,\n", $query);
    $query = sprintf("%s        `model_firmware_capability` mfc,\n", $query);
    $query = sprintf("%s        `capabilities` c\n", $query);
    $query = sprintf("%s   WHERE d.model_firmware_id=mf.id\n", $query);
    $query = sprintf("%s   AND d.`model_firmware_id`=mfc.`model_firmware_id`\n", $query);
    $query = sprintf("%s   AND mfc.`capability_id`=c.`id`\n", $query);
    $query = sprintf("%s   AND c.`name`=v_capability\n", $query);
    $query = sprintf("%s   AND d.`last_update`>(SELECT NOW()-INTERVAL v_age SECOND)\n", $query);
    $query = sprintf("%s   AND d.`id` NOT IN (\n", $query);
    $query = sprintf("%s     SELECT ld.`device_id`\n", $query);
    $query = sprintf("%s     FROM `locked_devices` ld\n", $query);
    $query = sprintf("%s     WHERE ld.`device_id`=d.`id`\n", $query);
    $query = sprintf("%s     AND ld.`locked`=1)\n", $query);
    $query = sprintf("%s   LIMIT 1;\n", $query);
    $query = sprintf("%s   IF v_found_id IS NOT NULL THEN\n", $query);
    $query = sprintf("%s     INSERT INTO `locked_devices`\n", $query);
    $query = sprintf("%s     VALUES(v_found_id, 1, v_user, NOW());\n", $query);
    $query = sprintf("%s   END IF;\n", $query);
    $query = sprintf("%s   SELECT d.`id`,\n", $query);
    $query = sprintf("%s          d.`ipv4`,\n", $query);
    $query = sprintf("%s          mf.`model_name`,\n", $query);
    $query = sprintf("%s          mf.`firmware_version`,\n", $query);
    $query = sprintf("%s          d.`last_update`\n", $query);
    $query = sprintf("%s   FROM `devices` d,\n", $query);
    $query = sprintf("%s        `model_firmware` mf,\n", $query);
    $query = sprintf("%s        `model_firmware_capability` mfc,\n", $query);
    $query = sprintf("%s        `capabilities` c\n", $query);
    $query = sprintf("%s   WHERE d.`model_firmware_id`=mf.`id`\n", $query);
    $query = sprintf("%s   AND d.`model_firmware_id`=mfc.`model_firmware_id`\n", $query);
    $query = sprintf("%s   AND mfc.`capability_id`=c.`id`\n", $query);
    $query = sprintf("%s   AND c.`name`=v_capability\n", $query);
    $query = sprintf("%s   AND d.`id`=v_found_id;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* unlock_device */
    $query =         "CREATE PROCEDURE `unlock_device`(IN `v_device_id` VARCHAR(255))\n";
    $query = sprintf("%s SQL SECURITY INVOKER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s DELETE FROM `locked_devices`\n", $query);
    $query = sprintf("%s WHERE `locked`=1\n", $query);
    $query = sprintf("%s AND `device_id`=v_device_id;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* is_device_locked */
    $query =         "CREATE PROCEDURE `is_device_locked` (IN `v_device_id` INT)";
    $query = sprintf("%s SQL SECURITY DEFINER\n", $query);
    $query = sprintf("%sBEGIN\n", $query);
    $query = sprintf("%s DECLARE is_locked TINYINT DEFAULT 0;\n", $query);
    $query = sprintf("%s SELECT `locked`\n", $query);
    $query = sprintf("%s INTO is_locked\n", $query);
    $query = sprintf("%s FROM `locked_devices`\n", $query);
    $query = sprintf("%s WHERE `device_id` = v_device_id\n", $query);
    $query = sprintf("%s AND `locked` =1;\n", $query);
    $query = sprintf("%s IF is_locked =1 THEN\n", $query);
    $query = sprintf("%s   SELECT 'yes' AS `locked`;\n", $query);
    $query = sprintf("%s ELSE\n", $query);
    $query = sprintf("%s   SELECT 'no' AS `locked`;\n", $query);
    $query = sprintf("%s END IF;\n", $query);
    $query = sprintf("%sEND;\n", $query);
    query($sql, $query);

    /* Create user */
    $query = "CREATE USER 'abused'@'%' IDENTIFIED BY 'abusedpass';";
    query($sql, $query);

    /* Grant SQL table permissions for the user */
    $query = "GRANT SELECT, INSERT, UPDATE ON `abused`.`capabilities` TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT SELECT, INSERT, UPDATE ON `abused`.`model_firmware` TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT SELECT, INSERT, UPDATE ON `abused`.`model_firmware_capability` TO 'abused'@'%';";
    query($sql, $query);
    $query = " GRANT SELECT, INSERT, UPDATE ON `abused`.`devices` TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT SELECT, INSERT, UPDATE ON `abused`.`locked_devices` TO 'abused'@'%';";
    query($sql, $query);

    /* Grant execute for SQL store procedure permission to the user */
    $query = "GRANT EXECUTE ON PROCEDURE abused.add_capability_if_not_exist TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.add_model_firmware_if_not_exist TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.is_model_firmware_probed TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.add_capability_to_model_firmware TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.add_or_update_device TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.is_device_locked TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.delete_inactive_devices TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.is_device_locked_internal TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.lock_device_by_id TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.lock_device_by_capability TO 'abused'@'%';";
    query($sql, $query);
    $query = "GRANT EXECUTE ON PROCEDURE abused.unlock_device TO 'abused'@'%';";
    query($sql, $query);

  }
  catch(Exception $e) {
    printf("SQL query failed: [%d] %s\n", $e->getCode(), $e->getMessage());
  }
}
else if(!isset($_GET['phase'])) {
  echo $htmlHeader;?>
  <script type="text/javascript">
    <!--
    function getXmlHttpRequestObject () {
      if (window.XMLHttpRequest)
        return new XMLHttpRequest();
      else if (window.ActiveXObject)
        return new ActiveXObject("Microsoft.XMLHTTP");
      else return false;
    }
    
    var conn = getXmlHttpRequestObject();

    function handleResults(theDiv, theUrl) {
      var receivedData;
      if (conn.readyState == 4) {
        receivedData = conn.responseText;
        document.getElementById(theDiv).innerHTML = receivedData;
      }
      else {
          document.getElementById(theDiv).innerHTML = "["+conn.readyState+"] pågår...";
        }
    }
  
    function ajaxIt (theDiv, theUrl) {
      if (conn === false) document.getElementById(theDiv).innerHTML = "Kunde inte skapa XMLHttpRequest";
      else
        if (conn.readyState == 4 || conn.readyState == 0) {
          // kan göras om till encoded och POST
          conn.open("GET", theUrl+"&h="+document.getElementById("h").value+"&u="+document.getElementById("u").value+"&p="+document.getElementById("p").value, true);
          conn.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8');
          conn.onreadystatechange = function () { handleResults(theDiv, theUrl); }
          conn.send(null);
        }
    }
    -->
  </script>
  <p>
    Welcome to the installation of the ABUSED database!<br />
    The installation requires that you have read and write<br />
    permittions to the MySQL server.<br />
    This process collects information<br />
    and then creates the database and associated tables.<br />
    <span style="font-style: italic;"><span style="font-weight: bold;">WARNING:</span><br />
    If you abort the process the collected information will be lost!</span>
  </p>
  <h1>Step 1 - MySQL information:</h1>
  <form method="post" action="install.php?phase=2">
  <table style="margin: auto; border: solid 1px black;">
    <tr>
      <td>MySQL server address/hostname:</td>
      <td><input type="input" id="h" name="mysql_host" value="localhost" /></td>
    </tr>
    <tr>
      <td>MySQL server port (TODO: use in test):</td>
      <td><input type="input" id="port" name="mysql_port" value="3306" /></td>
    </tr>
    <tr>
      <td>MySQL server username:</td>
      <td><input type="input" id="u" name="mysql_username" value="root" /></td>
    </tr>
    <tr>
      <td>MySQL server password:</td>
      <td><input type="password" id="p" name="mysql_password" value="" /></td>
    </tr>
    <tr>
      <td colspan="2" style="text-align: center;">
        <input onclick="javascript:ajaxIt('progressTag', 'install.php?test_mysql=1');" type="button" value="Test MySQL" />&nbsp;<input type="submit" value="Next" />
      </td>
    </tr>
  </table>
  </form>
  MySQL test:  <span id="progressTag">MySQL has not been tested!</span>
<?php
  echo $htmlFooter;
}
else if($_GET['phase'] == 2) {
  echo $htmlHeader;
?>
  <script type="text/javascript">
    function checkField() {
      return true;
    }
  </script>
  <h1>Step 2 - MySQL-structure:</h1>
  <p>
    It is not recomended to alter any information for the tables.<br />
    Valid characters are [a-z], [A-Z], [0-9], '-', and '_'.
  </p>
  <form method="post" action="install.php?phase=3">
  <table style="margin: auto; border: solid 1px black;">
    <tr><td colspan=2" style="font-weight:bold;">General settings:</td></tr>
    <tr>
      <td>MySQL database name:</td>
      <td><input type="input" name="database_name" value="abused" /></td>
    </tr>
    <tr>
      <td colspan="2" style="text-align: center;">
        <input type="submit" name="mysql" value="Create tables and stored procedures" />
        <input type="hidden" name="mysql_host" value="<?php echo $_POST['mysql_host']?>" />
        <input type="hidden" name="mysql_port" value="<?php echo $_POST['mysql_port']?>" />
        <input type="hidden" name="mysql_username" value="<?php echo $_POST['mysql_username']?>" />
        <input type="hidden" name="mysql_password" value="<?php echo $_POST['mysql_password']?>" />
      </td>
    </tr>
  </table>
  </form>
<?php
  echo $htmlFooter;
}
?>
<?php

  require_once "../lib/framework.php";

  
  //Create preferences object for current user
  $user_preferences = new cw_User_preferences($d,$_SESSION["person_id"]);

  if (!$user_preferences->check_for_table()){
    $user_preferences->recreate_table();
  }  
  
  //We expect ($_GET["service"],$_GET["pref_name"],$_GET["pref_value"] (optional - if not set, we'll return the value)
  //This script will not DELETE preferences, but it can overwrite with an empty value
  
  if (isset($_GET["service"]) && isset($_GET["pref_name"])){
    if (!isset($_GET["pref_value"])){
      //Read request (no value sent)
      echo $user_preferences->read_pref($_GET["service"],$_GET["pref_name"]);
    } else {
      //Write request (could be an empty value, too)
      $user_preferences->write_pref($_GET["service"],$_GET["pref_name"],$_GET["pref_value"]);
      echo "OK";
    }
  } else {
    echo "INVALID REQUEST";
  }  
  

  $p->nodisplay=true;
?>
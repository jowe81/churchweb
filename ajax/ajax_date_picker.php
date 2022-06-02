<?php

  require_once "../lib/framework.php";

  $upref = new cw_User_preferences($d,$_SESSION["person_id"]);
  
  //If no timestamp given, generate date picker with last-used (saved) timestamp (used by auto refresh in room_booking)  
  if (!isset($_GET["current_timestamp"])){
    $_GET["current_timestamp"]=$upref->read_pref($_GET["calling_service"],"current_timestamp"); 
  }
   
  //Generate date picker object with (passed-in or recalled) timestamp and calling_service id
  $dp = new cw_Date_picker($_GET["current_timestamp"],$_GET["calling_service"]);

  //Save timestamp with the user preferences for the passed-in service-id
  //This is important that the target service (booking system, calendar) knows what day to display
  if ($_GET["calling_service"]>0){
    $upref->write_pref($_GET["calling_service"],"current_timestamp",$_GET["current_timestamp"]); 
  }

  //For the ajax request, strictly produce the html INSIDE the wrapper
  $dp->display($date_picker_html,$date_picker_jquery); //call-by-reference
  
  //Since the jquery is echoed directly, the <script> tag must be used
  echo "<script type=\"text/javascript\">$date_picker_jquery</script>
        $date_picker_html";

  $p->nodisplay=true;
?>
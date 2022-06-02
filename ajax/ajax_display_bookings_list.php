<?php

  require_once "../lib/framework.php";

  $eh=new cw_Event_handling($a);  
  
  if ($_GET["action"]=="get_bookings_list"){
    //Establish start time
    $timestamp=time();
    if (!empty($_GET["timestamp"])){
      $timestamp=$_GET["timestamp"];      
    }
    $timestamp=getBeginningOfDay($timestamp);
    $bookings=$eh->room_bookings->get_bookings_for_timespan(getBeginningOfDay($timestamp),getEndOfDay($timestamp));
    $t="";
    $dbks=new cw_Display_room_bookings($d,$a);
    foreach ($bookings as $v){
      $t.=$dbks->format_booking_for_live_display($v,$eh,true);
    }  
    echo "
      <p style='font-size:120%;font-weight:bold;'>Room Bookings Overview for ".date('l F j, Y')."</p>
      <p>for ".$a->personal_records->get_name_first_last($a->cuid)." (current as of ".date('M j, h:i a').")</p>";
    echo $t;
  } else {
    echo "INVALID REQUEST";
  }
        
      
  $p->nodisplay=true;
  
?>
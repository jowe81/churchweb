<?php

  require_once "../lib/framework.php";

  $eh=new cw_Event_handling($a);  
  
  if ($_GET["action"]=="get_over"){
    $bookings=$eh->room_bookings->get_completed_bookings_for_timespan(getBeginningOfDay(time()),time());
    $t="";
    $dbks=new cw_Display_room_bookings($d,$a);
    foreach ($bookings as $v){
      $t.=$dbks->format_booking_for_live_display($v,$eh);
    }  
    echo $t;
  } elseif ($_GET["action"]=="get_in_progress"){
    $bookings=$eh->room_bookings->get_bookings_in_progress();
    if (is_array($bookings)){
      $bookings=array_reverse($bookings);
      $t="";
      $dbks=new cw_Display_room_bookings($d,$a);
      foreach ($bookings as $v){
        $t.=$dbks->format_booking_for_live_display($v,$eh);
      }      
    } else {
      $t="An error occurred";
    }
    echo $t;
  } elseif ($_GET["action"]=="get_next_up"){
    $bookings=$eh->room_bookings->get_upcoming_bookings_for_timespan(time(),getEndOfDay(time()));
    $t="";
    $dbks=new cw_Display_room_bookings($d,$a);
    foreach ($bookings as $v){
      $t.=$dbks->format_booking_for_live_display($v,$eh);
    }  
    echo $t;
  } elseif ($_GET["action"]=="get_latest_today"){
    if ($r=$eh->room_bookings->get_todays_latest_ending_booking()){
      $roomname=$eh->rooms->get_roomname($r["room_id"]);
      if (empty($roomname)){
        $roomname="multiple rooms";
      }
      echo date('h:i a',$r["timestamp"]+$r["duration"]).", $roomname";    
    } else {
      echo "n/a";
    }    
  } elseif ($_GET["action"]=="get_earliest_tomorrow"){
    if ($r=$eh->room_bookings->get_tomorrows_earliest_beginning_booking()){
      $roomname=$eh->rooms->get_roomname($r["room_id"]);
      if (empty($roomname)){
        $roomname="multiple rooms";
      }
      echo date('h:i a',$r["timestamp"]).", $roomname";    
    } else {
      echo "n/a";
    }    
  } elseif ($_GET["action"]=="get_no_longer_used"){
    if ($r=$eh->room_bookings->get_no_longer_used_today()){
      //Got array of roomnames
      $t="";
      foreach ($r as $room_name){
        $t.=$room_name."<br/>";
      }
      echo $t;    
    } else {
      echo "n/a";
    }    
  } elseif ($_GET["action"]=="get_currently_used"){
    if ($r=$eh->room_bookings->get_currently_used()){
      //Got array of roomnames
      $t="";
      foreach ($r as $room_name){
        $t.=$room_name."<br/>";
      }
      echo $t;    
    } else {
      echo "n/a";
    }    
  } elseif ($_GET["action"]=="get_used_later"){
    if ($r=$eh->room_bookings->get_used_later_today()){
      //Got array of roomnames
      $t="";
      foreach ($r as $room_name){
        $t.=$room_name."<br/>";
      }
      echo $t;    
    } else {
      echo "n/a";
    }    
  } else {
    echo "INVALID REQUEST";
  }
        
      
  $p->nodisplay=true;
  
?>
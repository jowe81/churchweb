<?php

  require_once "../lib/framework.php";
  
  if ($_GET["action"]=="add_service"){
    $day=substr($_POST["date"],0,2);
    $month=substr($_POST["date"],3,2);
    $year=substr($_POST["date"],6,4);
    if ($year==0){
      $year=date("Y",time());    
    }
    if (($day==0) || ($month==0)){
      $next_sunday = strtotime("next Sunday");
      $day=date("j",$next_sunday);
      $month=date("n",$next_sunday);
      $year=date("Y",$next_sunday);
    }
    $timestamp=mktime(11,0,0,$month,$day,$year);
    
    
    $eh=new cw_Event_handling($a);
    if ($y=$eh->schedule_service($timestamp)){
      echo "Scheduled service #$y: ".date("d/m/Y H:i:s",$timestamp);    
    } else {
      echo "ERROR - could not complete service scheduling.";        
    }
    
  }
  
    
  $p->nodisplay=true;
?>
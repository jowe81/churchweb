<?php

  require_once "../lib/framework.php";

  $upref=new cw_User_preferences($d,$a->cuid);
  
  $csid=$a->csid;
  
  if ($_GET["action"]=="toggle"){
    //Toggle and return new value
    $before=$upref->read_pref($csid,"show_".$_GET["dom_id"]);
    $now="CHECKED";
    if ($before=="CHECKED"){
      $now="";
    }
    $upref->write_pref($csid,"show_".$_GET["dom_id"],$now);
    echo $now;
  } elseif ($_GET["action"]=="read"){
    //Just return value
    echo $upref->read_pref($csid,"show_".$_GET["dom_id"]);  
  }
  
  
  
    
  $p->nodisplay=true;
?>
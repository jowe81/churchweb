<?php

  require_once "../lib/framework.php";
  
  $cl = new cw_Changelog($a);
  $dcl = new cw_Display_changelog($cl);  

  if ($_GET["action"]=="support_ticket"){
    if ($cl->support_ticket($_GET["ticket_id"])){
      if ($cl->supported($_GET["ticket_id"])){
        echo "1"; //Ticket is now supported      
      } else {
        echo "0"; //Ticket is now not supported            
      }
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_tickets_for_version") {
    $t=$dcl->display_tickets_for_version($_GET["version_id"],$_GET["orderby"]." ".$_GET["direction"]);
    if ($t!==false){
      $p->p($t);
    } else {
      $t.="Could not load tickets. <a href='?action='>Return</a>.";                  
    }              
    echo $t;
  } elseif ($_GET["action"]=="get_my_tickets") {
    $t=$dcl->display_tickets_for_version($_GET["version_id"],$_GET["orderby"]." ".$_GET["direction"],true);
    if ($t!==false){
      $p->p($t);
    } else {
      $t.="Could not load tickets. <a href='?action='>Return</a>.";                  
    }                    
    echo $t;
  } elseif ($_GET["action"]=="process_ticket") {
    $t=$cl->get_ticket_record($_GET["ticket_id"]);
    if ($_POST["status"]!=0){
      //Not deployment
      $t["status"]=$_POST["status"];
      $t["accepted_priority"]=$_POST["priority"];    
      if ($cl->update_ticket_record($_GET["ticket_id"],$t)){
        echo "OK";
      } else {
        echo "Error: could not update ticket record";
      }
    } else {
      //Deployment
      if ($cl->close_ticket($_GET["ticket_id"],$_POST["deployment_comment"])){
        echo "OK";
      } else {
        echo "Error: could not deploy ticket. You probably have not updated the current version.";
      }
    }
  } elseif ($_GET["action"]=="add_version") {
    if ($cl->add_version($_POST["version_name"])){
      echo "OK";
    } else {
      echo "Adding new version failed";
    }
  } else {
    echo "INVALID REQUEST";
  }
    
  $p->nodisplay=true;
?>
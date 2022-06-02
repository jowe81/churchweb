<?php

  require_once "../lib/framework.php";

  $rooms=new cw_Rooms($d);

  if (isset($_POST["action"])){
    if (($_POST["action"]=="Save") && ($a->csps>=CW_E)){
      //Process form
      $features=copy_from_POST("f_"); //from utilities_misc
      if (!$rooms->add_room($_POST["name"],$_POST["room_no"],$_POST["capacity"],$_POST["notes"],$_POST["active"],$features)){
        //Attempt to add room failed
        $p->error("Room could not be added. Room name or number might exist already.",array(CW_ROOT_WEB.$a->services->get_parents_url($a->csid)=>"Okay",CW_ROOT_WEB.$a->services->get_service_url($a->csid)=>"Try again"));            
      } else {
        //Success: Go back to the room list
        header('Location: '.CW_ROOT_WEB.$a->services->get_parents_url($a->csid));      
      }
    } else {
      //Either action was invalid, or insufficient privileges
      $p->error("Authorization problem.",array(CW_ROOT_WEB.$a->services->get_parents_url($a->csid)=>"Okay"));            
    }
  } else {
    //No $_POST: Show form
    if ($a->csps>=CW_E){
      $p->p($rooms->display_edit_form());
      $p->jquery("$('#name').focus();");    
    } else {
      //Insufficient privileges
      $p->error(CW_E,array($a->get_cspl()=>"OK"));
    }
  }  

?>
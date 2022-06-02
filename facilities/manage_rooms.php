<?php

  require_once "../lib/framework.php";

  $rooms = new cw_Rooms($d);

  //Updating data requires editor rights
  if (($_POST["action"]=="Save") && ($a->csps>=CW_E)){
    $features=copy_from_array($_POST,"piano,projector,PA-system,theatrical_lighting,air_conditioning"); //from utilities_misc
    $rooms->update_room($_POST["id"],$_POST["name"],$_POST["capacity"],$_POST["notes"],$_POST["active"],$features);
  }

  //Delete and reset operations require admin rights
  if ($a->csps>=CW_A){
    if ($_GET["action"]=="reset"){
      //Reset the entire table to default
      $rooms->recreate_tables();  
    }   
    if ($_GET["action"]=="delete"){
      //Delete selected room
      $rooms->delete_room($_GET["id"]);
      //to implement: deletion of records associated w/ the room (bookings, display preferences etc)
    }  
  }
  
  //Find out url of the edit script. This info must be passed to the table display function for linking.
  $edit_url=$a->services->get_childs_url_by_title($a->csid,"Edit Room");
  
  //Show table
  $p->p("<h3>Rooms</h3>");
  $p->p($rooms->display($edit_url,($a->csps>=CW_E),($a->csps>=CW_A)));
  $p->p("<a href='?action=reset'>Recreate table</a>&nbsp;");

?>
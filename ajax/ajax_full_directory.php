<?php

  require_once "../lib/framework.php";

  //This script displays the office version of the church directory
  
  $cdd = new cw_Display_church_directory($a);  

  if ($_GET["action"]==""){
    $filter=array("last_name"=>$_GET["last_name"],"first_name"=>$_GET["first_name"]);
    echo $cdd->display_list($filter,false); //Second param = unrestricted view (ignore indiv. privacy settings)  
  } elseif ($_GET["action"]=="display_edit_dialogue") {

  }
    
  $p->nodisplay=true;
?>
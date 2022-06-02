<?php

  require_once "../lib/framework.php";

  if ($_GET["action"]=="create_group"){
    if ($_POST["name"]!=""){
      if ($a->groups->add_group($_POST["name"],$_POST["description"])){
        echo "OK";
      } else {
        echo "Could not add group '".$_POST["name"]."'. It probably exists already.";
      }
    } else {
      echo "Cannot create group with empty name. You have to provide a name for the group.";
    }
  }
      
  $p->nodisplay=true;
?>
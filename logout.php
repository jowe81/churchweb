<?php

  /* Logout script must unset $_SESSION vars */
   
  require_once "lib/framework.php";

  if ($a->logout($_SESSION["session_id"])){
    $_SESSION=array(); //Unset all session varilables
    session_destroy(); //End php session  
    header("Location: ".CW_ROOT_WEB."login.php");
  } else {
    $p->error("<p>Logout failed.</p>");  
  }

?>
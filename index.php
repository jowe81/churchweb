<?php
 /*
    index.php will redirect to home if there's an active session,
    or to login if there is not
 */
 
 session_start();
 if (($_SESSION["session_id"]!="") && ($_SESSION["person_id"]>0)){
  //Session vars are set, so attempt to go to home
  header("Location: home.php");  
 } else {
  //Session vars are not set, go to login
  header("Location: login.php"); 
 }
?>
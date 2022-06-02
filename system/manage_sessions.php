<?php

  require_once "../lib/framework.php";

  $ss = new cw_Sessions($d);

  if (isset($_POST["action"])){
    if ($_POST["action"]=="Terminate"){
      //Delete record
      $ss->terminate_session($_POST["session_id"]);
    } else {    
      //Update record
      $ss->update_session($_POST["session_id"]);      
    }
  }

  //Reset the entire table to default
  if ($_GET["a"]=="reset"){
    $ss->recreate_tables();  
  }
  $p->p("<h3>Sessions</h3>");
  $p->p($ss->display());
  $p->p("<a href='?a=reset'>Recreate table (terminate all sessions)</a>&nbsp;");

  /*
  //THIS NOW IN system_preferences.php
  $spref=new cw_System_preferences($a);
  if ($_GET["action"]=="toggle_login"){
    if ($a->csps>=CW_A){
      $spref->toggle_login_blockade();      
    }  
  }  
  ($spref->login_blocked()==1) ? $b="<span style='color:red;'>blocked</span>" : $b="<span style='color:green;'>available</span>";     
  $p->p("<p>Login is: $b | <a href='?action=toggle_login'>toggle login availability</a></p>");
  */
?>
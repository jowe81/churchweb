<?php

  require_once "../lib/framework.php";
  
  if ($_GET["action"]=="change_password"){
    if (!(empty($_POST["old_pw"]) || empty($_POST["new_pw"]) || empty($_POST["new_pw2"]))){
      if (strlen($_POST["new_pw"])>3){
        //Minimum character amount met
        if ($_POST["new_pw"]==$_POST["new_pw2"]){
          //New passwords are same
          if ($_POST["new_pw"]!=$_POST["old_pw"]){
            //New password is not same as old
            $my_loginname=$a->users->get_loginname($a->cuid);
            if ($person_id=$a->users->check_credentials($my_loginname,$_POST["old_pw"])){
              //Old password is ok
              if ($a->users->change_password($my_loginname,$_POST["new_pw"])){
                echo "OK";
              } else {
                echo "System error: password change could not be completed";
              }                  
            } else {
              echo "Error: the current password you provided is wrong";
            }
          } else {
            echo "Password not changed: old and new password are identical";
          }
        } else {
          echo "Error: you did not repeat the new password correctly";
        }
      } else {
        echo "Error: the new password is too short";
      }    
    } else {
     echo "Error: you must fill in all three fields";
    }    
  } else {
    echo "INVALID REQUEST";
  }
        
      
  $p->nodisplay=true;
  
?>
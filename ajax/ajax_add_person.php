<?php

  require_once "../lib/framework.php";
  
  if ($_GET["action"]=="save"){
    //Create new person record
    if ($id=$a->personal_records->add_person($_POST["last_name"],$_POST["first_name"])){
      //Success
      //Add inactive default user account for new person. Find unused loginname (add laufende Nummer if first_namelast_name is taken)
      $x="";
      do {
        $loginname=$_POST["first_name"].".".$_POST["last_name"].$x;
        $x++;                      
      } while ($a->users->loginname_exists($loginname));
      //Create user
      $a->users->add_user(strtolower($loginname),"-",$id,0);
      //Add user to default group (all users)
      $a->group_memberships->grant_group_membership(1,$id,0);
      //Apply default preferences for new user
      $a->apply_default_user_preferences($id);
      echo $id; //Return id of new user      
    } else {
      echo "Could not create new person - database problem.";
    }
  }
      
  $p->nodisplay=true;
?>
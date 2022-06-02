<?php

  require_once "../lib/framework.php";

  $ud = new cw_Display_users($d,$a);  

  $loginname=$a->users->get_loginname($_GET["person_id"]); //Used a lot below

  if ($_GET["action"]==""){
    $filter=array("last_name"=>$_GET["last_name"],"first_name"=>$_GET["first_name"]);
    echo $ud->display_list($filter);  
  } elseif ($_GET["action"]=="display_edit_dialogue") {
    echo $ud->display_edit_dialogue($_GET["id"]);
  } elseif ($_GET["action"]=="add_group_membership"){
    if($a->group_memberships->grant_group_membership($_GET["group_id"],$_GET["person_id"],$a->cuid)){
      echo "OK";
    } else {
      echo "Could not add group membership";
    }
  } elseif ($_GET["action"]=="get_group_memberships"){
    //Output the group memberships for $_GET["person_id"] as JSON (goes in select)
    echo $d->select_json("SELECT groups.id,groups.name FROM groups,group_memberships WHERE groups.id=group_memberships.group_id AND group_memberships.person_id=".$_GET["person_id"]." ORDER BY groups.name;");
  } elseif ($_GET["action"]=="revoke_group_membership"){
    if($a->group_memberships->revoke_group_membership($_GET["group_id"],$_GET["person_id"])){
      echo "OK";
    } else {
      echo "Could not revoke group membership";
    }    
  } elseif ($_GET["action"]=="revoke_all_group_memberships"){
    if($a->group_memberships->revoke_all_group_memberships($_GET["person_id"])){
      echo "OK";
    } else {
      echo "Could not revoke group memberships";
    }    
  } elseif ($_GET["action"]=="add_user_permission"){
    if($a->permissions->grant_user_permission($_GET["person_id"],$_GET["service_id"],$a->cuid,$_GET["permission_type"])){
      echo "OK";
    } else {
      echo "Could not add user permission";
    }
  } elseif ($_GET["action"]=="get_direct_user_permissions"){
    //Output the direct user permissions for $_GET["person_id"] as JSON (goes in select)
    echo $d->select_json("SELECT services.title,services.id,user_permissions.type FROM services,user_permissions WHERE services.id=user_permissions.service_id AND user_permissions.person_id=".$_GET["person_id"].";");
  } elseif ($_GET["action"]=="revoke_user_permission"){
    if($a->permissions->revoke_user_permission($_GET["person_id"],$_GET["service_id"])){
      echo "OK";
    } else {
      echo "Could not revoke user permission";
    }    
  } elseif ($_GET["action"]=="revoke_all_user_permissions"){
    if($a->permissions->revoke_all_user_permissions($_GET["person_id"])){
      echo "OK";
    } else {
      echo "Could not revoke user permissions";
    }    
  } elseif ($_GET["action"]=="get_effective_privileges"){
      //Get array of service hierarchy (service records)
      $f=array();
      $a->services->get_service_hierarchy($f);
      //Get array of effective privileges (permitted services) for person
      $eff_prv=array();
      $a->find_permitted_services($_GET["person_id"],$eff_prv);
      $eff_prv_dsp=""; //HTML to display inside div
      foreach ($f as $v){
        if (isset($eff_prv[$v["id"]])){
          if ($eff_prv[$v["id"]]>=0){
            $eff_prv_dsp.=str_repeat("&nbsp;&nbsp;",($v["lvl"]-1))." ".$v["title"]." <span style='color:gray;font-size:80%;'>(".strtolower($a->permissions->int_to_full_permission_type($eff_prv[$v["id"]])).")</span><br/>";          
          }
        }
      }
      echo $eff_prv_dsp;    
  } elseif ($_GET["action"]=="change_loginname"){
    //First see if the new loginname is different from the old
    if ($loginname!=$_GET["loginname"]){
      if($a->users->change_loginname($loginname,$_GET["loginname"])){
        $a->email_login_credentials($_GET["person_id"]);//if this fails, there is at this time no feedback
        echo "OK";
      } else {
        echo "Could not change login-name. The target login-name '".$_GET["loginname"]."' is probably already taken.";
      }                                                             
    } else {
      //Original and target loginname are same. Indicate to calling js that no change was made.
      echo "NOCHANGE";
    }
  } elseif ($_GET["action"]=="get_loginname"){
    echo $a->users->get_loginname($_GET["person_id"]); 
  } elseif ($_GET["action"]=="toggle_acct_status"){
    $a->users->toggle_account_status($loginname);
    //Return the current status for javascript to reload the checkbox status
    if ($a->users->account_is_active($loginname)){
      echo "1";
    } else {
      echo "0";
    } 
  } elseif ($_GET["action"]=="generate_password"){
    if($new_password=$a->users->generate_password($loginname)){
      echo "New credentials for ".$a->personal_records->get_name_first_last($_GET["person_id"])." are: \n\nLogin: '$loginname' \nPassword: '$new_password' ";
      if ($a->email_login_credentials($_GET["person_id"])){
        echo "\n\nThe new credentials have been emailed to the user";
      } else {
        echo "\n\nWarning: the new credentials could not be emailed to the user";      
      }     
    } else {
      echo "Could not generate new password for ".$a->personal_records->get_name_first_last($_GET["person_id"]);
    }        
  }
    
  $p->nodisplay=true;
?>
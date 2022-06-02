<?php

  require_once "../lib/framework.php";

  $gd = new cw_Display_groups($d,$a);  

  if ($_GET["action"]==""){
    //Standard is to output the (filtered) table
    echo $gd->display_list($_GET["name"]);
    //In case an id has been passed, call the modal window with the edit dialogue for that id
    if ($_GET["goto"]>0){
      echo "<script type='text/javascript'> dm(".$_GET["goto"].");</script>";
    }  
  } elseif ($_GET["action"]=="display_edit_dialogue") {
    echo $gd->display_edit_dialogue($_GET["id"]);
  } elseif ($_GET["action"]=="save_details") {
    if ($a->groups->update_group($_GET["group_id"],$_POST["name"],$_POST["description"],($_POST["active"]!=""))){
      echo "OK";
    } else {
      echo "The group details could not be changed. Maybe you tried to change the name to an existing group name.";
    }
  } elseif ($_GET["action"]=="toggle_grp_status") {
    $a->groups->toggle_status($_GET["group_id"]);
    //Return the current status for javascript to reload the checkbox status
    if ($a->groups->is_active($_GET["group_id"])){
      echo "1";
    } else {
      echo "0";
    } 
  } elseif ($_GET["action"]=="get_direct_group_permissions"){
    //Output the direct group permissions for $_GET["group_id"] as JSON (goes in select)
    echo $d->select_json("SELECT services.title,services.id,group_permissions.type FROM services,group_permissions WHERE services.id=group_permissions.service_id AND group_permissions.group_id=".$_GET["group_id"].";");
  } elseif ($_GET["action"]=="add_group_permission"){
    if($a->permissions->grant_group_permission($_GET["group_id"],$_GET["service_id"],$a->cuid,$_GET["permission_type"])){
      echo "OK";
    } else {
      echo "Could not add group permission";
    }
  } elseif ($_GET["action"]=="revoke_group_permission"){
    if($a->permissions->revoke_group_permission($_GET["group_id"],$_GET["service_id"])){
      echo "OK";
    } else {
      echo "Could not revoke group permission";
    }    
  } elseif ($_GET["action"]=="revoke_all_group_permissions"){
    if($a->permissions->revoke_all_group_permissions($_GET["group_id"])){
      echo "OK";
    } else {
      echo "Could not revoke group permissions";
    }    
  } elseif ($_GET["action"]=="get_effective_privileges"){
      //Get array of service hierarchy (service records)
      $f=array();
      $a->services->get_service_hierarchy($f);
      //Get array of effective privileges (permitted services) for group
      $eff_prv=array();
      $a->find_permitted_group_services($_GET["group_id"],$eff_prv);
      $eff_prv_dsp=""; //HTML to display inside div
      foreach ($f as $v){
        if (isset($eff_prv[$v["id"]])){
          if ($eff_prv[$v["id"]]>=0){
            $eff_prv_dsp.=str_repeat("&nbsp;&nbsp;",($v["lvl"]-1))." ".$v["title"]." <span style='color:gray;font-size:80%;'>(".strtolower($a->permissions->int_to_full_permission_type($eff_prv[$v["id"]])).")</span><br/>";          
          }
        }
      }
      echo $eff_prv_dsp;    
  } elseif ($_GET["action"]=="delete_group"){
    if($_GET["group_id"]!=1){
      //This is not the default group. Okay...
      if($a->group_memberships->group_has_members($_GET["group_id"])){
        //Group has at least one member. Don't delete.
        echo "Cannot delete a group that has members";
      } else {
        if ( ($a->groups->delete_group($_GET["group_id"])) && ($a->permissions->revoke_all_group_permissions($_GET["group_id"])) ){
          echo "OK"; //Group deleted, along with group permissions that might have existed
        } else {
          echo "An error occured while trying to delete the group"; //Database problem
        }
      }    
    } else {
      echo "Cannot delete default group";
    }
  }
    
  $p->nodisplay=true;
?>
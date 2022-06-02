<?php

class cw_Group_memberships {

    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
      if (!$this->check_for_table()){
        $this->create_table();
      }
    }
    
    function check_for_table(){
      return $this->d->table_exists("group_memberships");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE group_memberships (
                          group_id INT,
                          person_id INT,
                          granted_by INT,
                          granted_at INT
                        )");
    }

    //Delete table (if extant) and re-create. 
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("group_memberships");
      }
      $res=$this->create_table();
      if ($res && $default_records){
        //Grant default user membership in default group
        $this->grant_group_membership(1,1,1);
      }
      return $res;
    }

    /* Basic group membership functions: grant, revoke, identify */

    function grant_group_membership($group_id,$person_id,$granted_by){
      if (($person_id>0) && ($group_id>0)){
        if (!$this->group_membership_exists($group_id,$person_id)){
          $e=array();
          $e["group_id"]=$group_id;
          $e["person_id"]=$person_id; //Benefactor
          $e["granted_by"]=$granted_by; //Person id of the granting admin
          $e["granted_at"]=time();
          return $this->d->insert($e,"group_memberships");
        }
        return true; //Return true if membership existed already
      }
      return false;
    }
    
    //Will revoke the group membership in question, unless it's the default group (id=1)
    function revoke_group_membership($group_id,$person_id){
      if ($group_id>1){
        return $this->d->query("DELETE FROM group_memberships WHERE group_id=$group_id AND person_id=$person_id");
      }
      return false;
    }

    //Will revoke all group memberships except the default group (id=1)
    function revoke_all_group_memberships($person_id){
      return $this->d->query("DELETE FROM group_memberships WHERE group_id>1 AND person_id=$person_id");
    }
    
    function group_membership_exists($group_id,$person_id){
      return (mysqli_num_rows($this->d->query("SELECT * FROM group_memberships WHERE group_id=$group_id AND person_id=$person_id"))>0);
    }
    
    //Returns an array of group_ids for person $person_id
    function get_memberships($person_id){
      $t=array();
      if ($res=$this->d->query("SELECT group_id FROM group_memberships WHERE person_id=$person_id")){
        while ($e=$res->fetch_assoc()){
          $t[]=$e["group_id"];
        }
      }    
      return $t;
    }

    //Returns an array of person_ids for group $group_id
    function get_memberships_for_group($group_id){
      $t=array();
      if ($res=$this->d->query("SELECT person_id FROM group_memberships WHERE group_id=$group_id")){
        while ($e=$res->fetch_assoc()){
          $t[]=$e["person_id"];
        }
      }    
      return $t;
    }
    
    function get_memberships_json($person_id){
      if ($r=$this->d->select_json("SELECT * FROM group_memberships WHERE person_id=$person_id")){
        return $r;
      }
      return false;  
    }
    
    //Does this group have any members?
    function group_has_members($group_id){
      $t=$this->get_memberships_for_group($group_id);
      return (sizeof($t)>0);
    }

    /* Display */
    
    function display(){
      $t="";
      if ($f=$this->d->get_table("group_memberships","group_id")){
        foreach ($f as $k=>$v){
          $t.="<tr>
                <td><form action='?a=' method='POST'><input type='text' name='group_id' value='".$v["group_id"]."'/></td>
                <td><input type='text' name='person_id' value='".$v["person_id"]."'/></td>
                <td><input type='text' name='granted_by' value='".$v["granted_by"]."'/></td>
                <td>
                  <input type='submit' name='action' value='Revoke'/>
                  <input type='hidden' name='id' value='".$v["group_id"]."-".$v["person_id"]."'/>
                  </form>
                </td>
              </tr>";
        }  
        $t="<table>
              <tr><td>Group ID</td><td>Person ID of member</td><td>Membership granted by?</td></tr>
              $t
            </table>";    
      }    
      return $t;
    }
}

?>
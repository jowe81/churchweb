<?php

class cw_Groups {

    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("groups");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE groups (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name char(50),
                          description char(200),
                          active TINYINT
                        )");
    }

    //Delete table (if extant) and re-create. 
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("groups");
      }
      $res=$this->create_table();
      if ($res && $default_records){      
        //Create default groups
        $this->add_group("all users","default group");
        $this->add_group("worship department","all worship volunteers");
        $this->add_group("worship leaders","worship leaders");
        $this->add_group("ministry staff","pastors");
        $this->add_group("office staff","office staff");
        $this->add_group("facility management","facility management");
        $this->add_group("janitor","janitor");
      }
      return $res;
    }

    /* Basic group functions: add, update, delete, identify */

    function add_group($name,$description="",$active=1){
      if (!$this->group_exists($name)){
        $e=array();
        $e["name"]=$name;
        $e["description"]=$description;
        $e["active"]=$active;
        return $this->d->insert($e,"groups");
      }
      return false;
    }
    
    function delete_group($id){
      return $this->d->delete($id,"groups","id");
    }
    
    function update_group($id,$name,$description,$active){
      //Get the old record to find out if there's a name change
      if ($g=$this->d->get_record("groups","id",$id)){
        //Do the update if either the name remains the same, or the new name is not extant yet
        if (($g["name"]==$name) || (($g["name"]!=$name) && (!$this->group_exists($name)))){          
          $e=array();
          $e["name"]=$name;
          $e["description"]=$description;
          $e["active"]=$active;
          return $this->d->update_record("groups","id",$id,$e);            
        }
      }
      return false;
    }

    //Return group record    
    function get_record($id){
      return $this->d->get_record("groups","id",$id);
    }
    
    function get_name($id){
      if ($g=$this->get_record($id)){
        return $g["name"];
      }
      return false;    
    }

    function get_id($name){
      if($r=$this->d->get_record("groups","name",$name)){
        return $r["id"];      
      }
      return false;    
    }
    
    function get_description($id){
      if ($g=$this->get_record($id)){
        return $g["description"];
      }
      return false;    
    }

    function group_exists($name){
      return $this->d->record_exists("groups","name",$name);
    }

    function group_id_exists($id){
      return $this->d->record_exists("groups","id",$id);
    }

    function is_active($id){
      if ($g=$this->d->get_record("groups","id",$id)){
        return ($g["active"]>0);
      }
      return false;
    }

    //Activate group
    function activate_group($id){
      if ($this->group_id_exists($id)){
        $e=array();
        $e["active"]=1;
        return $this->d->update_record("groups","id",$id,$e);
      }      
      return false;      
    }
    
    //Deactivate group
    function deactivate_group($id){
      if ($this->group_id_exists($id)){
        $e=array();
        $e["active"]=0;
        return $this->d->update_record("groups","id",$id,$e);
      }      
      return false;      
    }
    
    function toggle_status($id){
      $this->is_active($id) ? $this->deactivate_group($id) : $this->activate_group($id);
    }



    function get_table($orderby="name"){
      if ($f=$this->d->get_table("groups",$orderby)){
        return $f;
      }
      return false;
    }
    
    /* Display */
    
    function display(){
      $t="";
      if ($f=$this->d->get_table("groups","id")){
        foreach ($f as $k=>$v){
          $t.="<tr>
                <td><form action='?a=' method='POST'><input type='text' name='name' value='".$v["name"]."'/></td>
                <td><input type='text' name='description' value='".$v["description"]."'/></td>
                <td><input type='text' name='active' value='".$v["active"]."'/></td>
                <td>
                  <input type='submit' name='action' value='Save'/>
                  <input type='submit' name='action' value='Delete'/>
                  <input type='hidden' name='id' value='".$v["id"]."'/>
                  </form>
                </td>
              </tr>";
        }  
        $t="<table>
              <tr><td>Group name</td><td>Group description</td><td>Active?</td></tr>
              $t
            </table>";    
      }    
      return $t;
    }
}

?>
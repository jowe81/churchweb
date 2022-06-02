<?php

class cw_Permissions {

    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
      if (!$this->check_for_table()){
        $this->create_table();
      }
    }
    
    function check_for_table(){
      //This class has two tables
      return ($this->d->table_exists("user_permissions")) && ($this->d->table_exists("user_permissions"));
    }
    
    function create_table(){
      return (($this->d->q("CREATE TABLE user_permissions (
                          service_id INT,
                          person_id INT,
                          type TINYINT,
                          granted_by INT,
                          granted_at INT
                        )"))
                 &&       
             ($this->d->q("CREATE TABLE group_permissions (
                          service_id INT,
                          group_id INT,
                          type TINYINT,
                          granted_by INT,
                          granted_at INT
                        )")));
    }

    //Delete tables (if extant) and re-create. 
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("user_permissions");
        $this->d->drop_table("group_permissions");
      }
      $res=$this->create_table();
      if ($res && $default_records){
        //Add default admin permission for default user
        $this->grant_user_permission(1,1,0,CW_AUTH_LEVEL_ADMIN);
        //Add default group permissions for all users (default group)
        $this->grant_group_permission(1,2); //Logout (script)
        $this->grant_group_permission(1,3); //Home (script) 
        $this->grant_group_permission(1,32); //My account (node under my services)    
        $this->grant_group_permission(1,36); //My account (node under my services)    
        $this->grant_group_permission(1,10); //Ajax (node) - temporarily, to cover ajax_db and ajax_date_picker
        //Add default group permissions for worship dpt (all worship volunteers)
        $this->grant_group_permission(2,37,0,CW_E); //My commitments (node)
        $this->grant_group_permission(2,40,0,CW_V); //Service planning (node)                
        //Add default group permissions for worship leaders
        $this->grant_group_permission(3,37,0,CW_E); //My commitments (node)
        $this->grant_group_permission(3,38,0,CW_E); //Worship dpt (node)        
        //Add default group permissions for ministry staff
        $this->grant_group_permission(4,38,0,CW_E); //Worship dpt (node)        
        $this->grant_group_permission(4,6,0,CW_E); //Office (node)        
        //Add default group permissions for office staff
        $this->grant_group_permission(5,38,0,CW_A); //Worship dpt (node)        
        $this->grant_group_permission(5,6,0,CW_A); //Office (node)        
        $this->grant_group_permission(5,7,0,CW_E); //Facilities (node)        
        //Add default group permissions for facility management
        $this->grant_group_permission(6,7,0,CW_E); //Facilities (node)        
        //Add default group permissions for janitor
        $this->grant_group_permission(7,53,0,CW_V); //Display bookings (node)        
      }
      return $res;    
    }

    /* Basic user permission functions: grant, revoke, identify */

    function grant_user_permission($person_id,$service_id,$granted_by=0,$type=CW_AUTH_LEVEL_UNSPECIFIED){
      if (!$this->direct_user_permission_exists($person_id,$service_id)){
        $e=array();
        $e["person_id"]=$person_id;
        $e["service_id"]=$service_id;
        $e["type"]=$type;
        $e["granted_by"]=$granted_by; //Person id of the granting admin
        $e["granted_at"]=time();
        return $this->d->insert($e,"user_permissions");
      }
      return true; //If the permission pre-existed the task was successful
    }

    function revoke_user_permission($person_id,$service_id){
      if ($this->direct_user_permission_exists($person_id,$service_id)){
        return $this->d->query("DELETE FROM user_permissions WHERE service_id=$service_id AND person_id=$person_id");
      }    
      return true; //If the permission never existed the task was successful    
    }
    
    function revoke_all_user_permissions($person_id){
      return $this->d->query("DELETE FROM user_permissions WHERE person_id=$person_id");    
    }
    
    //Return the whole record of a direct permission
    function get_direct_user_permission_record($person_id,$service_id){
      if ($res=$this->d->query("SELECT * FROM user_permissions WHERE service_id=$service_id AND person_id=$person_id")){            
        if ($e=$res->fetch_assoc()){
          return $e;
        }
      }
      return false;    
    }
    
    function direct_user_permission_exists($person_id,$service_id){
      return (mysqli_num_rows($this->d->query("SELECT * FROM user_permissions WHERE service_id=$service_id AND person_id=$person_id"))>0);
    }
    
    /* Basic group permission functions: grant, revoke, identify */

    function grant_group_permission($group_id,$service_id,$granted_by=0,$type=CW_AUTH_LEVEL_UNSPECIFIED){
      if (!$this->direct_group_permission_exists($group_id,$service_id)){
        $e=array();
        $e["group_id"]=$group_id;
        $e["service_id"]=$service_id;
        $e["type"]=$type;
        $e["granted_by"]=$granted_by; //Person id of the granting admin
        $e["granted_at"]=time();
        return $this->d->insert($e,"group_permissions");
      }
      return true; //If the permission pre-existed the task was successful
    }

    function revoke_group_permission($group_id,$service_id){
      if ($this->direct_group_permission_exists($group_id,$service_id)){
        return $this->d->query("DELETE FROM group_permissions WHERE service_id=$service_id AND group_id=$group_id");
      }
      return true; //If the permission never existed the task was successful    
    }
    
    function revoke_all_group_permissions($group_id){
      return $this->d->query("DELETE FROM group_permissions WHERE group_id=$group_id");    
    }
    
    //Return the whole record of a direct permission
    function get_direct_group_permission_record($group_id,$service_id){
      if ($res=$this->d->query("SELECT * FROM group_permissions WHERE service_id=$service_id AND group_id=$group_id")){
        if ($e=$res->fetch_assoc()){
          return $e;
        }
      }
      return false;    
    }

    function direct_group_permission_exists($group_id,$service_id){
      return (mysqli_num_rows($this->d->query("SELECT * FROM group_permissions WHERE service_id=$service_id AND group_id=$group_id"))>0);
    }

    /* Display */
    
    //Return descriptive Character for permission type (short version)
    function int_to_permission_type($i){
      switch ($i){
        case CW_AUTH_LEVEL_UNSPECIFIED: return "-";
        case CW_AUTH_LEVEL_VIEWER: return "V";
        case CW_AUTH_LEVEL_EDITOR: return "E";
        case CW_AUTH_LEVEL_ADMIN: return "A";
      }
      return "N/A";
    }

    //Return descriptive String for permission type
    function int_to_full_permission_type($i){
      switch ($i){
        case CW_AUTH_LEVEL_UNSPECIFIED: return "Generic";
        case CW_AUTH_LEVEL_VIEWER: return "Viewer";
        case CW_AUTH_LEVEL_EDITOR: return "Editor";
        case CW_AUTH_LEVEL_ADMIN: return "Admin";
      }
      return "N/A";
    }
    
    function display(){
      $s="";
      $t="";
      if ($f=$this->d->get_table("user_permissions","person_id")){
        foreach ($f as $k=>$v){
          $s.="<tr>
                <td><form action='?a=' method='POST'>".$v["person_id"]."</td>
                <td>".$v["service_id"]."</td>
                <td>".$this->int_to_permission_type($v["type"])."</td>
                <td>".$v["granted_by"]."</td>
                <td>".date("Y/m/d H:i:s",$v["granted_at"])."</td>
                <td>
                  <input type='submit' name='action' value='Revoke user permission'/>
                  <input type='hidden' name='id' value='".$v["person_id"]."-".$v["service_id"]."'/>
                  </form>
                </td>
              </tr>";
        }  
        $s="<table>
              <tr><td>Person ID</td><td>Service ID</td><td>Type</td><td>Permission granted by?</td><td>Granted at</td></tr>
              $s
            </table>";    
      }
      if ($f=$this->d->get_table("group_permissions","group_id")){
        foreach ($f as $k=>$v){
          $t.="<tr>
                <td><form action='?a=' method='POST'>".$v["group_id"]."</td>
                <td>".$v["service_id"]."</td>
                <td>".$this->int_to_permission_type($v["type"])."</td>
                <td>".$v["granted_by"]."</td>
                <td>".date("Y/m/d H:i:s",$v["granted_at"])."</td>
                <td>
                  <input type='submit' name='action' value='Revoke group permission'/>
                  <input type='hidden' name='id' value='".$v["group_id"]."-".$v["service_id"]."'/>
                  </form>
                </td>
              </tr>";
        }  
        $t="<table>
              <tr><td>Group ID</td><td>Service ID</td><td>Type</td><td>Permission granted by?</td><td>Granted at</td></tr>
              $t
            </table>";    
      }    
      return $s.$t;
    }
}

?>
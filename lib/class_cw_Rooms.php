<?php

class cw_Rooms {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="rooms"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE rooms (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name char(100),
                          room_no char(20),
                          descriptor char(124),
                          capacity int,
                          notes TEXT,
                          active TINYINT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE guest_rooms (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name varchar(40)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE rooms_to_features (
                          room_id INT,
                          feature_id INT
                        )"))
                &&
             ($this->d->q("CREATE TABLE room_features (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          feature char(50)
                        )")));
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("rooms")){
        $this->d->drop_table("rooms");
      }
      if ($this->check_for_table("rooms_to_features")){
        $this->d->drop_table("rooms_to_features");
      }
      if ($this->check_for_table("room_features")){
        $this->d->drop_table("room_features");
      }
      if ($this->check_for_table("guest_rooms")){
        $this->d->drop_table("guest_rooms");
      }
      $res=$this->create_tables();
      if ($res && $default_records){
        //Add default features
        $this->add_room_feature("piano");
        $this->add_room_feature("projector");
        $this->add_room_feature("PA-system");
        $this->add_room_feature("theatrical lighting");
        $this->add_room_feature("air conditioning");
        //Add default room(s)
        $this->add_room("main auditorium","",700,"main gathering space of the church",1,array(1,2,3,4));
        $this->add_room("gymnasium","",400,"multi-purpose venue",1,array(2,3,4));
        $this->add_room("choir room","",20,"stage access",1,array(1));  
        $this->add_room("basement","",100,"if chairs are needed they need to be set up",1,array(1));
        $this->add_room("fireside room","",25,"",1);
        $this->add_room("youth room","",25,"",0,array(1,2));
        $this->add_room("kitchen","",0,"",0,array());
      }
      return $res;
    }

    /* "Guest" rooms  (=temporary, unregistered locations) */
    function add_guest_room($name){
      if ($name!=""){
        $e=array();
        $e["name"]=$name;
        return $this->d->insert_and_get_id($e,"guest_rooms");
      }
      return false;
    }
    
    function get_guest_roomname($id){
      $id=abs($id); //Guest ids are negative outside the database 
      if ($res=$this->d->query("SELECT name FROM guest_rooms WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r["name"];
        }
      }
      return false;
    }
    
    function get_guest_room_id($name){
      if ($res=$this->d->query("SELECT id FROM guest_rooms WHERE name=$name;")){
        if ($r=$res->fetch_assoc()){
          return ($r["id"]*(-1));
        }
      }
      return false;
    }
    
    /* Room features: add, delete, identify, retrieve */

    function add_room_feature($feature){
      if (($feature!="") && (!$this->room_feature_exists($feature))){
        $e=array();
        $e["feature"]=$feature;
        return $this->d->insert($e,"room_features");
      }
      return false;
    }
    
    function delete_room_feature($id){
      return $this->d->delete($id,"room_features","id");
    }
    
    function get_room_feature_id($feature){
      if ($e=$this->d->get_record("room_features","feature",$feature)){
        return $e["id"];
      }
      return false;
    }

    function get_room_feature_name($id){
      if ($e=$this->d->get_record("room_features","id",$id)){
        return $e["feature"];
      }
      return false;
    }
    
    function room_feature_exists($feature){
      if ($this->get_room_feature_id($feature)>0){
        return true;
      }
      return false;
    }
    
    //Returns an array with all features in the table. Keys=feature_id, values=feature 
    function get_all_room_features(){
      $r=$this->d->get_table("room_features",$id);
      $e=array();
      foreach ($r as $row){
        $e[$row["id"]]=$row["feature"];        
      }
      return $e;    
    }

    /* Basic room functions: add, update, delete, identify */
        
    //Use name and number of a room and return something like $name (#$no)
    private function get_descriptor($name,$no){
      $t="";
      if ($name!=""){
        $t=$name;
        if ($no!=""){
          $t.=" (#$no)";
        }
      } elseif ($no!="") {
        $t="#$no";      
      }    
      return $t;
    }    
        
    //Add room but ensure uniqueness of name (and, if given, number). If $features is given, then also set features. 
    function add_room($name,$room_no='',$capacity=0,$notes='',$active=1,$features=array()){
      $e=array();
      $e["name"]=$name;
      $e["room_no"]=$room_no;
      $e["descriptor"]=$this->get_descriptor($name,$room_no);
      $e["capacity"]=$capacity;
      $e["notes"]=$notes;
      $e["active"]=str_to_tinyint($active); //utilities_misc
      //Add room record
      if ((!$this->roomname_exists($name)) && ((!$this->room_no_exists($room_no)) || ($room_no=='') )){
        if ($this->d->insert($e,"rooms")){
          //Room record created
          if (count($features)>0){
            //Features have been passed in. Set them.
            return $this->set_features($this->get_id($name),$features);
          }
          return true; //No features were passed - but room record was successfully created.
        }
      }
      return false;
    }    

    //Delete room record after clearing all associated feature records    
    function delete_room($id){
      return (($this->clear_features($id)) && ($this->d->delete($id,"rooms","id")));
    }

    //Return entire room record
    function get_room_record($id){
      return ($this->d->get_record("rooms","id",$id));
    }

    //Create feature records form room. $features is array; the values are id's from table room_features
    function set_features($id,$features=array()){
      if ($this->clear_features($id)){
        foreach ($features as $v){
          $e=array();
          $e["room_id"]=$id;
          $e["feature_id"]=$v;
          $this->d->insert($e,"rooms_to_features");
        }
        return true;
      }
      return false;
    }
    
    //Return an array with feature_ids for room $id
    function get_features($id){
      if ($res=$this->d->query("SELECT feature_id FROM rooms_to_features WHERE room_id=$id ORDER BY feature_id")){
        $e=array();
        while ($f=$res->fetch_assoc()){
          $e[]=$f["feature_id"];          
        }
        return $e;
      }
      return false;
    }
    
    //Return a comma-separated string with features for this room (for display)
    function get_features_as_string($id){
      $t="";
      $e=$this->get_features($id);
      if ($e===false) { return false; } //db error
      if (count($e)>0){ //At least one feature exists
        foreach ($e as $v){
          $t.=", ".$this->get_room_feature_name($v);
        }
      }
      if ($t!=""){
        $t=substr($t,2); //Cut off first comma
      }
      return $t;
    }
    
    //Remove feature records for room with $id
    function clear_features($id){
      return $this->d->query("DELETE FROM rooms_to_features WHERE room_id=$id");    
    }

    
    //Update room record. If $features is passed in as array, features will be set (cleared on empty array).
    function update_room($id,$name,$room_no='',$capacity=0,$notes='',$active=1,$features){
      //Get existing record for room
      if ($f=$this->d->get_record("rooms","id",$id)){
        //Before updating ensure that there's either no name change or that the new name doesn't exist yet. Same for number.
        if ( (($f["name"]==$name) || (!$this->roomname_exists($name))) && (($f["room_no"]==$room_no) || (!$this->room_no_exists($room_no)) || ($room_no=='')) ){
          //OK. Update.
          $e=array();
          $e["name"]=$name;
          $e["room_no"]=$room_no;
          $e["descriptor"]=$this->get_descriptor($name,$room_no);          
          $e["capacity"]=$capacity;
          $e["notes"]=$notes;
          $e["active"]=str_to_tinyint($active); //utilities_misc
          if (is_array($features)){
            $this->set_features($this->get_id($name),$features);
          }
          return $this->d->update_record("rooms","id",$id,$e);                          
        }
      }    
      return false;    
    }
    
    //Activate room (make available for booking)
    function activate_room($id){
      if ($this->roomnname_exists($loginname)){
        $e=array();
        $e["active"]=1;
        return $this->d->update_record("rooms","id",$id,$e);
      }      
      return false;      
    }
    
    //De-Activate room (disable booking)
    function deactivate_room($id){
      if ($this->roomnname_exists($loginname)){
        $e=array();
        $e["active"]=0;
        return $this->d->update_record("rooms","id",$id,$e);
      }      
      return false;      
    }
    
    //Is room available for booking?
    function is_active($id){
      if ($r=$this->get_room_record($id)){
        if ($r["active"]>0){
          return true;
        }
      }
      return false;
    }

    //Does the room name exist already?
    function roomname_exists($name){
      return $this->d->record_exists("rooms","name",$name);          
    }

    //Does the room number exist already?
    function room_no_exists($room_no){
      return $this->d->record_exists("rooms","room_no",$room_no);          
    }
    
    //Does a room with this id exist?
    function room_id_exists($room_id){
      return $this->d->record_exists("rooms","id",$room_id);          
    }

    //Get the roomname that corresponds to $id
    function get_roomname($id){
      if ($id>0){
        if ($e=$this->d->get_record("rooms","id",$id)){
          return $e["name"];    
        }
        return false;      
      } else {
        return $this->get_guest_roomname(abs($id));
      }
    }
    
    //Get the descriptor that corresponds to $id
    function get_roomdescriptor($id){
      if ($e=$this->d->get_record("rooms","id",$id)){
        return $e["descriptor"];    
      }
      return false;
    }

    //Get the id that corresponds to $name
    function get_id($name){
      if ($e=$this->d->get_record("rooms","name",$name)){
        return $e["id"];    
      }
      return false;    
    }
    
    //Get array with all room info, order by field $orderby
    function get_all_rooms($orderby="name"){
      return $this->d->get_table("rooms",$orderby);      
    }
    
    //Return a string of name/number or both, whateveris present
    function get_name_and_number($id){
      if ($r=$this->get_room_record($id)){
        if ($r["name"]!=""){
          if ($r["room_no"]!=""){
            return $r["name"]." (#".$r["room_no"].")";
          } else {
            return $r["name"];
          }
        } else {
          if ($r["room_no"]!=""){
            return "#".$r["room_no"];
          } else {
            return "N/A";
          }
        }
      }
      return false;
    }
        
    //Display rooms as a table. $edit_url is for the edit link (if different script).
    function display($edit_url="",$display_edit_link=false,$display_delete_link=false){
      $t="";
      if ($f=$this->d->get_table("rooms",$id)){
        foreach ($f as $k=>$v){
          //Prep
          $features=ifer2($this->get_features_as_string($v["id"]),"-"); //ifer2 is in utilities_misc
          $notes=ifer2($v["notes"],'-');
          $edit_link="";
          if ($display_edit_link){ $edit_link="<a class='blink' href='".CW_ROOT_WEB.$edit_url."?id=".$v["id"]."'>edit</a>"; }
          $delete_link="";
          if ($display_delete_link){ $delete_link="<a class='blink' href='?action=delete&id=".$v["id"]."'>delete</a>"; }
          if (($v["capacity"])==0) { $v["capacity"]="N/A"; }
          //Display table row
          $t.="<tr>
                <td>".$v["name"]."</td>
                <td>".$v["room_no"]."</td>
                <td>".$v["capacity"]."</td>
                <td>$features</td>
                <td>$notes</td>
                <td>".get_yes_no($v["active"])."</td>
                <td>
                  $edit_link $delete_link
                </td>
              </tr>";
        }
        $t="<table style='width:100%'>
              <tr><th>Room name</th><th>Room number</th><th>Capacity</th><th>Amenities</th><th>Notes</th><th>Available for booking</th><th></th></tr>
              $t
            </table>";    
      }
      return $t;
    }
    
    //Display form for editing/adding a room. If $id==0, we're adding
    function display_edit_form($id=0){
      if ($id>0){
        //We're editing
        //Obtain present room info
        $e=$this->get_room_record($id);
        //Get features for this room
        $current_features=$this->get_features($id);
        $title="Edit Room Information: ".$e["name"];
      } else {
        //We're adding: start with blank form
        $e=array();
        $current_features=array();
        $title="Add Room to Booking System";
      }
      //Get array of all possible features (id=>feature)
      $all_possible_features=$this->get_all_room_features();
      
      $feature_checkboxes="";
      foreach ($all_possible_features as $k=>$v){
        $feature_checkboxes.=get_checkbox("f_".$k,$v,in_array($k,$current_features))."<br/>";
      }
      
      return ("
        <h3>$title</h3>
        <div>
          <form action=\"\" method=\"POST\">
            <table style='width:700px'>
              <tr>
                <td>
                  <table>
                    <tr>
                      <td>Room name:</td>
                      <td><input type='text' id='name' name='name' value=\"".$e["name"]."\"/></td>
                    </tr>
                    <tr>
                      <td>Room number:</td>
                      <td><input type='text' id='room_no' name='room_no' value=\"".$e["room_no"]."\"/></td>
                    </tr>
                    <tr>
                      <td>Capacity (seated):</td>
                      <td><input type='text' id='capacity' name='capacity' value=\"".$e["capacity"]."\"/></td>
                    </tr>
                    <tr>
                      <td>Notes:</td>
                      <td><textarea id='notes' name='notes'>".$e["notes"]."</textarea></td>
                    </tr>
                    <tr>
                      <td>Status:</td>
                      <td><input type='checkbox' name='active' ".get_checked($e["active"])."/> Available for booking</td>
                    </tr>
                  </table>
                </td>
                <td>
                  <table>
                    <tr>
                      <td>Amenities:</td>
                    </tr>
                    <tr>
                      <td>
                        $feature_checkboxes
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td>
                  <input type='submit' name='action' value='Save'>
                  <input type='submit' name='action' value='Discard changes'>
                  <input type='hidden' name='id' value='".$e["id"]."'/>
                </td>
              </tr>
          </form>
        </div>
        <script type='text/javascript'>
           ".jq_num_only('#capacity')
           .jq_no_dbl_quote('#room_no')
           .jq_no_dbl_quote('#name')
           .jq_no_dbl_quote('#notes')
           ."
        </script>
      ");
    }

}

?>
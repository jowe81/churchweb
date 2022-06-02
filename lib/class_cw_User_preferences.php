<?php

class cw_User_preferences {
  
    private $d; //Database access
    private $person; //The id of the person on whose preferences this instance will act
    
    function __construct($d,$person=0){
      $this->d = $d;
      $this->person=$person;
    }
    
    function check_for_table($table="user_preferences"){
      return $this->d->table_exists($table);
    }
    
    function create_table(){
      return ($this->d->q("CREATE TABLE user_preferences (
                          person INT,
                          service INT,
                          pref_name char(50),
                          pref_value char(30),
                          show_to_user tinyint,
                          description char(160),
                          expires INT,
                          INDEX (person,service)
                        )"));
    }

    //Delete table (if extant) and re-create.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("user_preferences")){
        $this->d->drop_table("user_preferences");
      }
      return $this->create_table();
    }

    /* Preference methods: write, read, delete */

    function write_pref($service,$pref_name,$pref_value,$show_to_user=false,$description="",$expires=0){
      $e=array();
      $e["person"]=$this->person;
      $e["service"]=$service;
      $e["pref_name"]=$pref_name;
      $e["pref_value"]=$pref_value;
      //If pref preexists retain show-to-user and description
      if ($res=$this->d->query("SELECT * FROM user_preferences WHERE person=$this->person AND service=$service AND pref_name='$pref_name';")){
        if ($r=$res->fetch_assoc()){
          //Record preexists. Use last two parameters from record.
          $e["show_to_user"]=$r["show_to_user"];
          $e["description"]=$r["description"];                                  
        } else {
          //Record doesn't exist yet, use show-to-user and description as passed in
          $e["show_to_user"]=$show_to_user;
          $e["description"]=$description;        
        }
      }          
      //Delete old record, if extant, and replace
      $this->delete_pref($service,$pref_name);
      return $this->d->insert($e,"user_preferences");
    }
    
    function read_pref($service,$pref_name){
      if ($res=$this->d->query("SELECT * FROM user_preferences WHERE person=$this->person AND service=$service AND pref_name='$pref_name';")){
        if ($e=$res->fetch_assoc()){
          return $e["pref_value"];
        }
      }
      return false;    
    }
    
    function delete_pref($service,$pref_name,$strict=true){
      //If not $strict, it is enough for the preference name to CONTAIN $pref_name
      if ($strict){
        $query="DELETE FROM user_preferences WHERE person=$this->person AND service=$service AND pref_name='$pref_name';";
      } else {
        $query="DELETE FROM user_preferences WHERE person=$this->person AND service=$service AND pref_name LIKE '%$pref_name%';";
      }
      return ($res=$this->d->query($query));
    }
    
    //$condition is SQL - used to clear old sync marks
    function delete_prefs_by_condition($service,$condition,$ignore_person_id=false){
      if ($ignore_person_id){
        $query="DELETE FROM user_preferences WHERE service=$service AND $condition";      
      } else {
        $query="DELETE FROM user_preferences WHERE person=$this->person AND service=$service AND $condition";            
      }
      return ($res=$this->d->query($query));
    }
        
    //Takes a service id and then (part of) a pref_name. Returns an array of preference names (key) and values (value)
    function read_multiple_prefs($service,$pref_name){
      $r=array();
      if ($res=$this->d->query("SELECT * FROM user_preferences WHERE person=$this->person AND service=$service AND pref_name LIKE '$pref_name';")){
        while($e=$res->fetch_assoc()){
          $r[$e["pref_name"]]=$e["pref_value"];
        }
      }
      return $r;          
    }
    
}

?>
<?php

class cw_System_preferences {
  
    private $auth; //Auth
    private $d; //Database access
    
    function __construct($a){
      $this->auth = $a;
      $this->d = $a->d;
    }
    
    function check_for_table($table="system_preferences"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE system_preferences (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          pref_name char(50),
                          pref_value text,
                          description char(160),
                          default_value text,
                          expires INT,
                          INDEX (pref_name)
                        )")) 
                        && 
              ($this->d->q("CREATE TABLE system_messages (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          noted_by INT,
                          noted_at INT,
                          expires INT,
                          type TINYINT,
                          subject VARCHAR(255),
                          message text,
                          INDEX (noted_at)
                        )")));
    }

    //Delete table (if extant) and re-create.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("system_preferences")){
        $this->d->drop_table("system_preferences");
      }
      if ($this->check_for_table("system_messages")){
        $this->d->drop_table("system_messages");
      }
      $created=$this->create_tables();
      if ($default_records){
        return ( (($this->write_pref("LOGIN_BLOCKED","0","Set to 1 to disable login","0")>0))
              && (($this->write_pref("SYSTEM_MESSAGE_DEFAULT_TTL",28,"System message default validity (days)",28)>0))
              && (($this->write_pref("SERVICE_PLANNING_SERVICE_LIST_OTHER_POSITIONS","6,7,8,10,59","CSL of position ids to be displayed in the services list in addition to leadership positions","6,7,8,10,59")>0))                          
              );        
      } else {
        return $created;
      }
    }

    /* Preference methods: write, read, delete */

    function write_pref($pref_name,$pref_value,$description="",$default_value=""){
      if ($e=$this->read_pref($pref_name,true)){
        /* pref exists, overwrite */
        $e["pref_name"]=$pref_name;
        $e["pref_value"]=$pref_value;
        if ($description!=""){
          $e["description"]=$description;                                  
        }
        if ($default_value!=""){
          $e["default_value"]=$default_value;                                  
        }
        ($this->d->update_record("system_preferences","id",$e["id"],$e)) ? $result=$e["id"] : $result=false;
        return $result;      
      } else {
        /* new pref */      
        $e=array();
        $e["pref_name"]=$pref_name;
        $e["pref_value"]=$pref_value;
        $e["description"]=$description;        
        $e["default_value"]=$default_value;                                  
        return $this->d->insert_and_get_id($e,"system_preferences");      
      }
    }
    
    function read_pref($pref_name,$return_full_record=false){
      if ($res=$this->d->query("SELECT * FROM system_preferences WHERE pref_name='$pref_name';")){
        if ($e=$res->fetch_assoc()){
          if ($return_full_record){
            return $e;
          } else {
            return $e["pref_value"];          
          }
        }
      }
      return false;    
    }
    
    function delete_pref($pref_name,$strict=true){
      //If not $strict, it is enough for the preference name to CONTAIN $pref_name
      if ($strict){
        $query="DELETE FROM system_preferences WHERE pref_name='$pref_name';";
      } else {
        $query="DELETE FROM system_preferences WHERE pref_name LIKE '%$pref_name%';";
      }
      return ($res=$this->d->query($query));
    }
    
    function get_all_preferences(){
      return $this->d->get_table("system_preferences","pref_name");
    }
    
    
    /* Block/unblock Login */
    
    function block_login(){
      return ($this->write_pref("LOGIN_BLOCKED","1"));
    }
    
    function unblock_login(){
      return ($this->write_pref("LOGIN_BLOCKED","0"));    
    }
    
    function toggle_login_blockade(){
      if ($this->login_blocked()){
        return $this->unblock_login();
      }
      return $this->block_login();
    }
    
    function login_blocked(){
      return ($this->read_pref("LOGIN_BLOCKED")==1);
    }
    
    /* system messages */
    
    function add_message($subject,$message,$type=0,$expires=0,$noted_by=0){
      if ((!empty($subject)) && (!empty($message))){
        $e=array();
        $e["subject"]=$subject;
        $e["message"]=$message;
        $e["type"]=$type; //0=notice, 1=warning
        $e["noted_at"]=time();
        if ($expires==0){
          //Set expiry date to default if not given. $expire=-1=never expires
          $e["expires"]=time()+$this->read_pref("SYSTEM_MESSAGE_DEFAULT_TTL");      
        } else {
          $e["expires"]=$expires;
        }
        if ($noted_by==0){
          $e["noted_by"]=$this->auth->cuid;      
        }
        return $this->d->insert_and_get_id($e,"system_messages");            
      }
      return false;
    } 
    
    function get_all_messages($orderby="noted_at DESC"){                           
      return $this->d->get_table("system_messages",$orderby);
    }
    
    //Return UNEXPIRED messages with an Id greater than $id_greater_than
    function get_messages($id_greater_than=0){
      ($id_greater_than=="") ? $id_greater_than=0 : null;
      $query="SELECT * FROM system_messages WHERE id>$id_greater_than AND expires>".time().";";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        return $t;
      }
      return false;
    }
    
    function delete_expired_messages($grace=WEEK){
      $query="DELETE FROM system_messages WHERE expires<".time()."+$grace;";
      return ($this->d->q($query));    
    }
    
    function delete_message($id){
      return $this->d->delete($id,"system_messages");
    }
        
}

?>
<?php

class cw_Auto_confirm {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="auto_confirm"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE auto_confirm (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          created_at INT,
                          valid_until INT,
                          access_code varchar(".CW_SERVICE_PLANNING_AUTO_CONFIRM_ACCESS_CODE_LENGTH."),
                          person_id INT,
                          service_id INT,
                          positions_to_services_id INT,
                          rehearsal_participants_id INT,
                          INDEX (access_code)
                        )")));                                
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("auto_confirm")){
        $this->d->drop_table("auto_confirm");
      }
      if ($this->create_tables()){
        return true;
      }
      return false;
    }

    //Generate auto confirm records
    
    function generate_auto_confirm_record_all($service_id,$person_id){
      $e=array();
      $e["created_at"]=time();
      $e["valid_until"]=time()+CW_SERVICE_PLANNING_AUTO_CONFIRM_LINK_VALIDITY;
      $e["access_code"]=$this->get_access_code();
      $e["person_id"]=$person_id;
      $e["service_id"]=$service_id;
      return $this->d->insert_and_get_id($e,"auto_confirm");      
    }    
        
    //Get record
    
    function get_record($id){
      return $this->d->get_record("auto_confirm","id",$id);
    }    
    
    function remove_record($id){
      if ($id>0){
        return ($this->d->query("DELETE FROM auto_confirm WHERE id=$id OR valid_until<".time().";"));
      }
    }
    
    /*
    function remove_records_for_person_and_service($person_id,$service_id){
      return ($this->d->query("DELETE FROM auto_confirm WHERE (person_id=$person_id AND service_id=$service_id) OR valid_until<".time().";"));
    }
    */
    
    //Get unique access code
    function get_access_code(){
      do {
        $access_code=create_sessionid(CW_SERVICE_PLANNING_AUTO_CONFIRM_ACCESS_CODE_LENGTH); //in utilities_misc.php
      } while ($this->access_code_exists($access_code));
      return $access_code;    
    }
    
    //Check uniqueness of access code
    
    function access_code_exists($access_code){
      if ($res=$this->d->query("SELECT access_code FROM auto_confirm WHERE access_code='$access_code';")){
        return ($res->num_rows>0);
      }
      return true; //If query fails it is better to provoke an error
    }

    //Check validity of code and return full auto_confirm record if match is found
    function check_access_code($access_code){
      $val=(substr($access_code,-1)==1); 
      $access_code=substr($access_code,0,CW_SERVICE_PLANNING_AUTO_CONFIRM_ACCESS_CODE_LENGTH); //The passed in var is probably 1 digit longer (with the value attached)
      if ($r=$this->d->get_record("auto_confirm","access_code",$access_code)){
        $r["val"]=$val; //Put the interpreted value in the record that is returned
        return $r;
      }
      return false;
    }
    
    //Get public links
    
    function get_value_string($value=true){
      $value ? $append="1" : $append="0";
      return $append;       
    }
    
    function get_auto_confirm_link_all($service_id,$person_id){
      if ($record=$this->get_record($this->generate_auto_confirm_record_all($service_id,$person_id))){
        return CW_FULL_PUBLIC_URL.CW_SERVICE_PLANNING_AUTO_CONFIRM_SCRIPT."?a=".$record["access_code"];
      }
    }
        
    
}

?>
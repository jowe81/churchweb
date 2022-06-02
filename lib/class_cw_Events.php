<?php

class cw_Events {
  
    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="church_services"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE events (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          created_at INT,
                          created_by INT,
                          updated_at INT,
                          updated_by INT,
                          timestamp INT,
                          duration INT,
                          person_in_charge INT,
                          is_church_event TINYINT,
                          is_rehearsal TINYINT,
                          church_service INT,
                          cat1 INT,
                          cat2 INT,
                          cat3 INT,
                          cat4 INT,
                          cat5 INT,
                          cat5plus varchar(100),
                          title varchar(160),
                          note TEXT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE event_categories (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          label varchar(50),
                          INDEX (label)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE events_to_target_audience (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          event_id INT,
                          group_id INT,
                          person_id INT,
                          INDEX (event_id)
                        )")));               
      /*
          Events
            -church_service points to church_services record. That way multiple instances of the same service can be identified
            -is_rehearsal: boolean
            -cat1-5 is direct pointer to category table, cat5plus is CSL for categories below 5
      */                                         
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("events")){
        $this->d->drop_table("events");
      }
      if ($this->check_for_table("events_to_target_audience")){
        $this->d->drop_table("events_to_target_audience");
      }
      if ($this->check_for_table("event_categories")){
        $this->d->drop_table("event_categories");
      }
      if ($this->create_tables()){
        return true;
      }
      return false;
    }

    /* events: add, update, delete, retrieve */

    function add_event($e){
      if (is_array($e)){
        $e["created_at"]=time();
        $e["updated_at"]=-1;
        return $this->d->insert_and_get_id($e,"events");
      }
      return false;
    }

    function update_event($id,$e){
      $e["updated_at"]=time();      
      return $this->d->update_record("events","id",$id,$e);
    }
    
    //Delete event from table - MAKE SURE THAT ASSOCIATED RECORDS ARE DELETED FIRST!
    function delete_event($id){
      return $this->d->delete($id,"events","id");    
    }
    
    function get_event_record($id){
      return $this->d->get_record("events","id",$id);
    }
    
    //Retrieve events that begin on or after $first_timestamp and on or before $last_timestamp
    //Filter can contain any number of additional fields to filter by, &-% filter 
    function get_events_for_timeframe($first_timestamp,$last_timestamp,$filter=array()){
      $t=array();
      //Get conditions string for filter array
      $cond=cw_Db::array_to_query_conditions($filter);
      //Conditions for time window
      $time_cond=" timestamp>=$first_timestamp AND timestamp<=$last_timestamp ";
      if ($cond!=""){
        //If there were filter conditions, they need be appended with AND
        $time_cond.=" AND ";      
      }
      if ($res=$this->d->select("events","*",$time_cond.$cond." ORDER BY timestamp")){
        while ($r=$res->fetch_assoc()){
          $t[$r["id"]]=$r;
        }
      }
      return $t;          
    }

    //Filter can contain any number of fields to filter by, &-% filter 
    function get_events($filter=array()){
      return $this->get_events_for_timeframe(0,CW_BIGGEST_TIMESTAMP,$filter);          
    }

    /* events_to_target_audience: assign groups/individuals, unassign, check association */
    
    function assign_group_to_event($event_id,$group_id){
      if (!$this->event_group_association_exists($event_id,$group_id)){
        $e=array();
        $e["event_id"]=$event_id;
        $e["group_id"]=$group_id;
        return $this->d->insert($e,"events_to_target_audience");        
      }
      return true; //Technically we're successful even if the connection exists already      
    } 

    function assign_person_to_event($event_id,$group_id){
      if (!$this->event_group_association_exists($event_id,$group_id)){
        $e=array();
        $e["event_id"]=$event_id;
        $e["group_id"]=$group_id;
        return $this->d->insert($e,"events_to_target_audience");        
      }
      return true; //Technically we're successful even if the connection exists already      
    } 
    
    function remove_group_from_event($event_id,$group_id){
      if ($id=$this->event_group_association_exists($event_id,$group_id)){
        $this->d->delete($id,"events_to_target_audience","id");
      }
    }

    function remove_person_from_event($event_id,$person_id){
      if ($id=$this->event_person_association_exists($event_id,$person_id)){
        $this->d->delete($id,"events_to_target_audience","id");
      }
    }
    
    //If the connection exists, return its id
    function event_group_association_exists($event_id,$group_id){
      if ($res=$this->d->query("SELECT * FROM events_to_target_audience WHERE event_id=$event_id AND group_id=$group_id")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }

    //If the connection exists, return its id
    function event_person_association_exists($event_id,$person_id){
      if ($res=$this->d->query("SELECT * FROM events_to_target_audience WHERE event_id=$event_id AND person_id=$person_id")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
}

?>
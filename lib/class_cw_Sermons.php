<?php

class cw_Sermons {
  
    private $d; //Database access
    
  	function __construct($d){
      $this->d = $d;
  	}
    
    function check_for_table($table="sermons"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE sermons (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(150),
                          abstract text
                        )"))
                &&        
             ($this->d->q("CREATE TABLE sermons_to_service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          sermon INT,
                          service_element INT
                        )")));
                        
      /*
      */
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("sermons")){
        $this->d->drop_table("sermons");
      }
      if ($this->check_for_table("sermons_to_service_elements")){
        $this->d->drop_table("sermons_to_service_elements");
      }
      if ($this->create_tables()){
        return true;                          
      }
      return false;
    }

    /* Sermons refs: create, delete */
    
    function add_sermon($title="",$abstract=""){
      $e=array();
      $e["title"]=$start;
      $e["abstract"]=$end;
      return $this->d->insert_and_get_id($e,"sermons");
    }
    
    function update_sermon($e,$id){
      return $this->d->update_record("sermons","id",$id,$e);    
    }
    
    function delete_sermon($id){
      //Make sure to delete assoc records first! (unassign_sermon_from_service_element)
      return $this->d->delete($id,"sermons");
    }
    
    /* Sermons to service elements */
    
    function assign_sermon_to_service_element($sermon_id,$service_element_id){
      if (($sermon_id>0) && ($service_element_id>0)){
        if (!$this->get_sermon_record_for_service_element($service_element_id)){
          //Allow only one sermon per element
          $e=array();
          $e["sermon"]=$sermon_id;
          $e["service_element"]=$service_element_id;
          return $this->d->insert($e,"sermons_to_service_elements");              
        }
      }
      return false;
    }
    
    function unassign_sermon_from_service_element($sermon_id,$service_element_id){
      if ($res=$this->d->q("DELETE FROM sermons_to_service_elements WHERE sermon=$sermon_id AND service_element=$service_element_id;")){
        return true;
      }
      return false;      
    }
    
    function get_sermon_record_for_service_element($service_element_id){
      $query="
        SELECT
          sermons.*
        FROM
          sermons,sermons_to_service_elements
        WHERE
          sermons.id=sermons_to_service_elements.sermon
        AND
          sermons_to_service_elements.service_element=$service_element_id;
      ";
      if ($res=$this->d->query($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
    }
    


}

?>
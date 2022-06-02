<?php

class cw_Changelog {

    private $d; //Database access
    public $auth;         
    public $current_version_record; //latest record in system_versions;   
    
    function __construct($auth){
      $this->d = $auth->d;
      $this->auth = $auth;
      $this->current_version_record=$this->get_current_version_record();
    }
    
    function check_for_table($table){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE system_changelog (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          status TINYINT,
                          type TINYINT,
                          title varchar(255),
                          description text,
                          service SMALLINT,
                          suggested_priority TINYINT,
                          accepted_priority TINYINT,
                          noted_on_version INT,
                          implemented_in_version INT,
                          noted_by INT,
                          noted_at INT,
                          updated_at INT,
                          updated_by INT,
                          deployment_comment text,
                          supporters text,
                          INDEX (status),
                          INDEX (implemented_in_version),
                          INDEX (service,noted_at,title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE system_versions (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          version_name varchar(40),
                          deployed_at INT,
                          comment text,
                          INDEX (deployed_at)
                        )")));                                
    }
    
    /*
      system_changelog
        status: -1=rejected, 0=deployed, 1=open, 2=in process
        type: 0 generic, 1 bug report, 2 feature request
        service: id in services table
        priorities: 0=not assigned, 1=very low, 2=low, 3=medium, 4=high, 5=critical
        version references: to system_versions.id
        noted_by: person_id
        supporters: CSL of person_ids
    
    */

    private function check_for_and_drop_tables($tables=array()){
      foreach ($tables as $v){
        if ($this->check_for_table($v)){
          $this->d->drop_table($v);
        }        
      }
    }
    
    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $tables=array("system_changelog",
                    "system_versions");
      $this->check_for_and_drop_tables($tables);
      $res=$this->create_tables();
      if ($res && $default_records){
        $this->add_version("0.1");
        $ticket=$this->add_ticket("0","New installation","",0,0,true);
        $this->close_ticket($ticket,"New installation performed ".date("F j, Y H:i:s",time()).", original ChurchWeb release date: Nov 7, 2012",0,0,true);
      }
      return $res;
    }

    /* Changelog / tickets */
    
    function add_ticket($type,$title,$description="",$service=0,$suggested_priority=0,$ignore_version_check=false){
      if ($title!=""){
        $e=array();
        $e["title"]=$title;
        $e["type"]=$type;
        $e["description"]=$description;
        $e["service"]=$service; //ID of service in question
        $e["suggested_priority"]=$suggested_priority;
        $e["noted_by"]=$this->auth->cuid;
        $e["noted_at"]=time();
        $e["status"]=1; //-1=rejected, 0=deployed, 1=open, 2=in process
        $ignore_version_check ? $version_id=1 : $version_id=$this->current_version_record["id"]; //Only after install
        if ($version_id>0){
          $e["noted_on_version"]=$version_id;
          return $this->d->insert_and_get_id($e,"system_changelog");            
        }
        return false;
      }
    }
    
    function add_generic_ticket($title,$description="",$service=0,$suggested_priority=0){
      return $this->add_ticket(0,$title,$description,$service,$suggested_priority);
    }

    function add_bug_report($title,$description="",$service=0,$suggested_priority=0){
      return $this->add_ticket(1,$title,$description,$service,$suggested_priority);
    }
    
    function add_feature_request($title,$description="",$service=0,$suggested_priority=0){
      return $this->add_ticket(2,$title,$description,$service,$suggested_priority);
    }
    
    function support_ticket($id){
      /*
        pseudo:
          if $this->auth->cuid is not in the CSL in $ticket_record["supporters"], add it; else remove it
      */
      $t=$this->get_ticket_record($id);
      if ($t["noted_by"]!=$this->auth->cuid){
        //Can't support one's own ticket
        if ($this->supported($id)){
          $t["supporters"]=csl_delete_element($t["supporters"],$this->auth->cuid);            
        } else {
          $t["supporters"]=csl_append_element($t["supporters"],$this->auth->cuid);            
        }      
      }
      return $this->update_ticket_record($id,$t,0,true);
    }
    
    //Has the current user supported the ticket?
    function supported($id){
      $t=$this->get_ticket_record($id);
      return csl_get_element_pos($t["supporters"],$this->auth->cuid);          
    }
    
    function update_ticket_record($id,$e=array(),$person_id=0,$preserve_update_info=false){
      if (!$preserve_update_info){
        $e["updated_at"]=time();
        $e["updated_by"]=$person_id;
        if ($person_id==0){
          $e["updated_by"]=$this->auth->cuid;
        }      
      }
      return $this->d->update_record("system_changelog","id",$id,$e);        
    }
    
    function get_ticket_record($id){
      return $this->d->get_record("system_changelog","id",$id);    
    }

    function get_tickets_for_version($version_id=0,$orderby=""){
      if (trim($orderby)==""){
        $orderby="service,accepted_priority,noted_at";
      }
      if ($version_id>0){
        $query="SELECT * FROM system_changelog WHERE implemented_in_version=$version_id ORDER BY $orderby";
        if ($res=$this->d->q($query)){
          $t=array();
          while ($r=$res->fetch_assoc()){
            $t[]=$r;
          }
          return $t;
        }      
      } else {
        $query="SELECT * FROM system_changelog WHERE status>0 ORDER BY $orderby";
        if ($res=$this->d->q($query)){
          $t=array();
          while ($r=$res->fetch_assoc()){
            $t[]=$r;
          }
          return $t;
        }      
      }
      return false;
    }
    
    function get_my_tickets($orderby=""){
      if (trim($orderby)==""){
        $orderby="service,accepted_priority,noted_at";
      }
      $query="SELECT * FROM system_changelog WHERE noted_by=".$this->auth->cuid." ORDER BY $orderby";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        return $t;
      }      
      return false;    
    }
        
    function close_ticket($id,$deployment_comment="",$status=0,$person_id=0,$disable_chronology_check=false){
      /* If the ticket has been noted in the current version,
         and $status is set to 0 (= mark deployed), return error - can't
         deploy ticket in same version it has been noted on 
      */
      if ($t=$this->get_ticket_record($id)){
        if ((!(($t["noted_on_version"]==$this->current_version_record["id"]) && ($status==0))) || $disable_chronology_check){
          $e=$t;
          $e["status"]=$status;
          empty($deployment_comment) ? $e["deployment_comment"]=$e["title"] : $e["deployment_comment"]=$deployment_comment; //default to original title if no comment given
          $disable_chronology_check ? $e["implemented_in_version"]=1 : $e["implemented_in_version"]=$this->current_version_record["id"];
          $this->update_ticket_record($id,$e,$person_id);
          return true; 
        } else {
          //Update current version first!
        }
      }
      return false;      
    }
    
    
    /* Versions */
    
    function add_version($version_name){
      if (($version_name!="") && (!$this->version_exists($version_name))){
        $e=array();
        $e["version_name"]=$version_name;
        $e["deployed_at"]=time();
        return $this->d->insert_and_get_id($e,"system_versions");                    
      }    
    }
    
    function version_exists($version_name){
      return ($this->get_version_id($version_name)>0);
    }
    
    function get_version_id($version_name){
      $query="SELECT id FROM system_versions WHERE version_name=\"$version_name\"";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    function get_version_record($id){
      return $this->d->get_record("system_versions","id",$id);        
    }
    
    function get_version_name($version_id){
      if ($r=$this->get_version_record($version_id)){
        return $r["version_name"];      
      }
      return false;    
    }
    
    function get_current_version_record(){
      $query="SELECT * FROM system_versions ORDER BY id desc LIMIT 1;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
    }
    
    function get_current_version_name(){
      return $this->current_version_record["version_name"];
    }    
        
    /**********************/
    
    function priority_to_str($priority){
      $t="N/A";
      switch ($priority) {
        case 0:$t="N/A"; break;
        case 1:$t="very low"; break;
        case 2:$t="low"; break;
        case 3:$t="medium"; break;
        case 4:$t="high"; break;
        case 5:$t="critical"; break;
      }
      return $t;
    }
    
    function ticket_type_to_str($type){
      $t="N/A";    
      switch ($type){
        case 0:$t="generic"; break;
        case 1:$t="bug report"; break;
        case 2:$t="feature request"; break;
      }
      return $t;      
    }
    
    function ticket_status_to_str($status,$include_color_span=false){
      $a="";
      $b="";
      if ($include_color_span){
        switch ($status){
          case  0:$c="green"; break;
          case  1:$c="orange"; break;
          case  2:$c="blue"; break;
          case  3:$c="gray"; break;
          case  -1:$c="red"; break;
        }
        $a="<span style='color:$c;'>";
        $b="</span>";
      }
      $t="N/A";
      switch ($status){
        case  0:$t=$a."deployed".$b; break;
        case  1:$t=$a."open".$b; break;
        case  2:$t=$a."in process".$b; break;
        case  3:$t=$a."hibernating".$b; break;
        case -1:$t=$a."rejected".$b; break;
      }    
      return $t;
    }

}

?>
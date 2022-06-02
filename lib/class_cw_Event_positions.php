<?php

class cw_Event_positions {
  
    private $d; //Database access
    private $vocalist_position_titles=array("singer","singer (soprano)","singer (alto)","singer (tenor)","singer (bass)");
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table($table="positions"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE positions (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(100),
                          note varchar(160),
                          type INT,
                          active BOOL DEFAULT true
                        )"))
                &&        
             ($this->d->q("CREATE TABLE position_types (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(50)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE positions_to_services (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          pos_id INT,
                          service_id INT,
                          person_id INT,
                          scheduled_at INT,
                          scheduled_by INT,
                          confirmed INT,
                          last_notification INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE positions_to_services__to_service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,             
                          pos_to_services_id INT,
                          service_element_id INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE music_packages (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,             
                          pos_to_services_id INT,
                          service_element_id INT,
                          file_id INT,
                          INDEX(service_element_id)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE positions_to_people (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,             
                          position_id INT,
                          person_id INT,
                          added_at INT,
                          dormant INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE available_volunteers (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,             
                          service_id INT,
                          position_id INT,
                          person_id INT,
                          volunteered_at INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE rehearsal_participants (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,             
                          rehearsal INT,
                          positions_to_services_id INT,
                          confirmed INT,
                          last_notification INT
                        )")));
                        
      /*
        positions
          - active: determines whether this position will be shown by the autocomplete for scheduling a person
      
        positions_to_services:
          - last_notification: timestamp of the last time the person got an email concerning this position  

        positions_to_services__to_service_elements
          - which people are EXLUDED from which service elements? (for music package generation)
          - technically redundant since the addition of music_packages 
                    
        music_packages
          - for each position in a service, and each service element (music ones, at least), a selection of an instrument (part) can be specified         
      
        positions_to_people
          - links people with positions they are able to fill or have filled in the past (generic)
          - the 'dormant' field allows a person to temporarily opt out of a potential ministry position. It's a timestamp (dormant until)
          
        available_volunteers
          - position_id links to the type of position (not pos_to_services) that a person has volunteer for in the service
          
        rehearsal_participants
          - rehearsal is event_id (link to rehearsal event)
          - position_to_services_id to include a  person  
          - last_notification: timestamp of the last time the person got an email concerning this rehearsal
      */
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("positions")){
        $this->d->drop_table("positions");
      }
      if ($this->check_for_table("position_types")){
        $this->d->drop_table("position_types");
      }
      if ($this->check_for_table("positions_to_services")){
        $this->d->drop_table("positions_to_services");
      }
      if ($this->check_for_table("positions_to_services__to_service_elements")){
        $this->d->drop_table("positions_to_services__to_service_elements");
      }
      if ($this->check_for_table("music_packages")){
        $this->d->drop_table("music_packages");
      }
      if ($this->check_for_table("positions_to_people")){
        $this->d->drop_table("positions_to_people");
      }
      if ($this->check_for_table("available_volunteers")){
        $this->d->drop_table("available_volunteers");
      }
      if ($this->check_for_table("rehearsal_participants")){
        $this->d->drop_table("rehearsal_participants");
      }
      $res=$this->create_tables();
      if ($res && $default_records){

        //Add default records
        $this->add_position_type(CW_POSITION_TYPE_SERVICE_LEADERSHIP);
        $this->add_position_type(CW_POSITION_TYPE_TECHNICAL_SUPPORT);
        $this->add_position_type(CW_POSITION_TYPE_WORSHIP_TEAM);
        $this->add_position_type(CW_POSITION_TYPE_DRAMA);
        $this->add_position_type(CW_POSITION_TYPE_OTHER);
        
        //Service leadership
        $this->add_position(CW_SERVICE_PLANNING_DESCRIPTOR_PRODUCER,1);
        $this->add_position(CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER,1);
        $this->add_position(CW_SERVICE_PLANNING_DESCRIPTOR_PREACHER,1);
        $this->add_position(CW_SERVICE_PLANNING_DESCRIPTOR_HOST,1);
        $this->add_position(CW_SERVICE_PLANNING_DESCRIPTOR_DRAMA_DIRECTOR,1);
        
        //Technical support
        $this->add_position("sound technician",2);
        $this->add_position("projector operator",2);
        $this->add_position("lighting operator",2);
        $this->add_position("camera operator",2);
        $this->add_position("video director",2);
        $this->add_position("technical assistant",2);
        
        //Music
        $this->add_position($this->vocalist_position_titles[1],3); //singer (soprano)
        $this->add_position($this->vocalist_position_titles[2],3); //singer (alto)
        $this->add_position($this->vocalist_position_titles[3],3); //singer (tenor)
        $this->add_position($this->vocalist_position_titles[4],3); //singer (bass)
        $this->add_position($this->vocalist_position_titles[0],3); //singer

        $this->add_position("pianist",3);
        $this->add_position("organist",3);
        $this->add_position("keyboard player",3);
        $this->add_position("bassist",3);
        $this->add_position("drummer",3);
        $this->add_position("percussionist",3);
        $this->add_position("guitarist (acoustic)",3);
        $this->add_position("guitarist (electric)",3);
        $this->add_position("accordion player",3);

        $this->add_position("violinist",3);
        $this->add_position("violinist [2nd]",3);
        $this->add_position("violinist [3rd]",3,0);
        $this->add_position("violist",3);
        $this->add_position("violist [2nd]",3,0);
        $this->add_position("cellist",3);
        $this->add_position("cellist [2nd]",3,0);
        $this->add_position("double bass player",3);

        $this->add_position("clarinet player",3,0);
        $this->add_position("clarinet player (A)",3,0);
        $this->add_position("clarinet player (Bb)",3);

        $this->add_position("recorder player",3);
        $this->add_position("alto recorder player",3);
        $this->add_position("tenor recorder player",3,0);
        $this->add_position("bass recorder player",3,0);

        $this->add_position("flute player",3);

        $this->add_position("saxophonist",3,0);
        $this->add_position("saxophonist (Eb)",3);

        $this->add_position("trumpet player",3);
        $this->add_position("trumpet player [2nd]",3);
        $this->add_position("trumpet player [3rd]",3,0);
        $this->add_position("trumpet player (Bb)",3);
        $this->add_position("trumpet player (Bb) [2nd]",3);
        $this->add_position("trumpet player (Bb) [3rd]",3,0);
        $this->add_position("trombone player",3);
        $this->add_position("trombone player [2nd]",3);
        $this->add_position("trombone player [3rd]",3,0);

        $this->add_position("conductor",3);
        
        
        //Drama
        $this->add_position("actor (m/f)",4);
        $this->add_position("actor (m)",4);
        $this->add_position("actress",4);
        $this->add_position("stage hand",4);
        $this->add_position("writer",4);
        
        //Other
        $this->add_position("head usher",5);
        $this->add_position("usher",5);
        $this->add_position("security chief",5);
        $this->add_position("security guard",5);
        
        /*
        
          LATER ADDITIONS BELOW
          
        */
        
        $this->add_position("front team singer",3);
        $this->add_position("ensemble singer",3);
        $this->add_position("choir singer",3);
        $this->add_position("child singer",3);
        
        
        /*
        //Add test records  (Johannes to various positions)
        $this->add_position_to_person(1,1);
        $this->add_position_to_person(1,2);
        $this->add_position_to_person(1,3);
        $this->add_position_to_person(1,4);
        $this->add_position_to_person(1,5);
        $this->add_position_to_person(1,9);
        $this->add_position_to_person(1,10);
        $this->add_position_to_person(1,11);
        $this->add_position_to_person(1,10);
        */
      }
      return $res;
    }


    function add_position_type($title){
      if (($title!="") && (!$this->position_type_exists($title))){
        $e=array();
        $e["title"]=$title;
        return $this->d->insert($e,"position_types");
      }
      return false;    
    }

    function get_position_type_id($title){
      if ($e=$this->d->get_record("position_types","title",$title)){
        return $e["id"];
      }
      return false;
    }
    
    //Return full record
    function get_position_type_record($id){
      if ($e=$this->d->get_record("position_types","id",$id)){
        return $e;
      }
      return false;    
    }

    //Return title only    
    function get_position_type_title($id){
      if ($e=$this->get_position_type_record($id)){
       return $e["title"];
      }
      return false;
    }

    //Retrieve a positions record, then find out type&title
    function get_position_type_title_by_position_id($id){
      if ($posrec=$this->get_position_record($id)){
        return $this->get_position_type_title($posrec["type"]);        
      }
      return false;
    }

    function position_type_exists($title){
      if ($this->get_position_type_id($title)>0){
        return true;
      }
      return false;
    }
    
    function get_position_types(){
      return $this->d->get_table("position_types");
    }
    
    /* Positions: add, delete, identify, retrieve */

    function add_position($title,$type=1,$active=true,$note=""){
      if (($title!="") && (!$this->position_exists($title))){
        $e=array();
        $e["title"]=$title;
        $e["type"]=$type;
        $e["note"]=$note;
        $e["active"]=$active;
        return $this->d->insert($e,"positions");
      }
      return false;
    }
    
    function delete_position($id){
      return $this->d->delete($id,"positions","id");
    }
    
    function get_position_id($title){
      if ($e=$this->d->get_record("positions","title",$title)){
        return $e["id"];
      }
      return false;
    }
    
    //Return full record
    function get_position_record($id){
      if ($e=$this->d->get_record("positions","id",$id)){
        return $e;
      }
      return false;    
    }

    //Return title only    
    function get_position_title($id){
      if ($e=$this->get_position_record($id)){
       return $e["title"];
      }
      return false;
    }
    
    //Return type id only
    function get_position_type($id){
      if ($e=$this->get_position_record($id)){
       return $e["type"];
      }
      return false;    
    }

    function position_exists($title){
      if ($this->get_position_id($title)>0){
        return true;
      }
      return false;
    }
    
    function position_is_vocalist($title){
      $result=in_array($title,$this->vocalist_position_titles);
      return $result;
    }
    
    //Returns an array with all positions in the table. If type is specified it is used to filter.
    function get_all_positions($type="",$active_positions_only=false){
      $cond="";
      if ($type!=""){
        $cond.="AND positions.type LIKE '$type%'";
      }
      if ($active_positions_only){
        $cond.=" AND positions.active=true";
      }
      $e=array();
      $query="
        SELECT 
          positions.* 
        FROM
          positions,position_types
        WHERE
          positions.type=position_types.id
        $cond
        ORDER BY position_types.id,positions.title;      
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $e[]=$r;
        }        
      }
      return $e;    
    }
    
    //Returns array with <OPTION value='$position_id'>$position_title</option>. Filter by type optional
    function get_position_options_for_select($type="",$active_positions_only=false){
      $p=$this->get_all_positions($type,$active_positions_only);
      $t="";
      foreach ($p as $v){
        $current_pos_type=$this->get_position_type($v["id"]);
        if ($last_pos_type!=$current_pos_type){
          //New position type, insert divider
          $t.="<option value='' style='color:gray;font-size:70%;' disabled='disabled'>".$this->get_position_type_title($current_pos_type)." positions </option>";                  
        }        
        $t.="<option value='".$v["id"]."'>".$v["title"]."</option>";
        $last_pos_type=$this->get_position_type($v["id"]);
      }    
      return $t;
    }
    
    function get_all_position_titles_for_js_array(){
      if ($r=$this->get_all_positions("",true)){
        $t="";
        foreach ($r as $v){
          $t.=", '".$v["title"]."'";
        }
        return substr($t,2); //cut first comma      
      }
      return false;
    }
    
    //Return an array representing a position id for each position that is scheduled in a service
    //If position_type_id is given, restrict search to that position type
    function get_positions_in_service($service_id,$position_type_id=0){
      if ($position_type_id!=0){
        $query="
          SELECT DISTINCT 
            positions.id
          FROM
            positions,position_types,positions_to_services
          WHERE
            position_types.id=positions.type
          AND 
            positions_to_services.pos_id=positions.id
          AND 
            position_types.id=$position_type_id
          AND 
            positions_to_services.service_id=$service_id
          ORDER BY
            positions.title;      
        ";
      } else {
        $query="
          SELECT DISTINCT
            positions.id
          FROM
            positions,position_types,positions_to_services
          WHERE
            position_types.id=positions.type
          AND 
            positions_to_services.pos_id=positions.id
          AND 
            positions_to_services.service_id=$service_id
          ORDER BY
            positions.title;      
        ";      
      }         
      
      $t=array();
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r["id"];
        }      
      }
      return $t;    
    }

    //Return positions_to_services records. $get_person_ids_only is never actually used at this point
    //If $only_not_disconfirmed is set, do not return those people ids who came from disconfirmed pos_to_services records
    function get_people_in_service($service_id,$position_type_id=0,$get_person_ids_only=false,$only_not_disconfirmed=false){
      $cond="";
      if ($only_not_disconfirmed){
        $cond=" AND positions_to_services.confirmed>=0 ";      
      }
      if ($position_type_id!=0){
        if ($get_person_ids_only){
          $query="
            SELECT DISTINCT 
              positions_to_services.person_id
            FROM
              positions,position_types,positions_to_services
            WHERE
              position_types.id=positions.type
            AND 
              positions_to_services.pos_id=positions.id
            AND 
              position_types.id=$position_type_id
            AND 
              positions_to_services.service_id=$service_id
            $cond
            ;
          ";              
        } else {
          $query="
            SELECT DISTINCT 
              positions_to_services.*
            FROM
              positions,position_types,positions_to_services
            WHERE
              position_types.id=positions.type
            AND 
              positions_to_services.pos_id=positions.id
            AND 
              position_types.id=$position_type_id
            AND 
              positions_to_services.service_id=$service_id
            $cond
            ORDER BY
              positions_to_services.person_id
            ;
          ";      
        }
      } else {
        $query="
          SELECT DISTINCT 
            positions_to_services.person_id
          FROM
            positions,position_types,positions_to_services
          WHERE
            position_types.id=positions.type
          AND 
            positions_to_services.pos_id=positions.id
          AND 
            positions_to_services.service_id=$service_id
          $cond
          ;
        ";            
      }   
      $t=array();
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }      
      }
      return $t;    
    }

    /* Positions to services */
            
    //If position record for a service exists w/o person_id, then the position stands as "this position needs to be filled"
    function add_position_to_service($pos_id,$service_id,$person_id,$scheduled_at=-1,$scheduled_by=0,$confirmed=false){
      //Pos_id and service_id must be given; and the same person canned fill two identical positions
      if (($pos_id>0) && ($service_id>0) && ((!$this->person_has_position_in_service($pos_id,$service_id,$person_id) || ($person_id<=0)) )){
        $e=array();
        $e["pos_id"]=$pos_id;
        $e["service_id"]=$service_id;
        $e["person_id"]=$person_id;
        $e["scheduled_at"]=$scheduled_at;
        $e["scheduled_by"]=$scheduled_by;
        $e["confirmed"]=$confirmed;
        $e["last_notification"]=-1;
        return $this->d->insert($e,"positions_to_services");                                                                                                                  
      }
    }
    
    function remove_position_from_service($pos_to_services_id){
      //Need to also remove potentially existing rehearsal_participant record
      return (
        ($res=$this->d->query("DELETE FROM positions_to_services WHERE id=$pos_to_services_id;"))
        &&
        ($res2=$this->d->query("DELETE FROM rehearsal_participants WHERE positions_to_services_id=$pos_to_services_id;"))        
      );    
    }
    
    //Mark as n/a (i.e., as not yet responded to)
    function mark_positions_to_services_record_na($id){
      return ($this->d->query("UPDATE positions_to_services SET confirmed=0 WHERE id=$id;"));    
    }
    
    function mark_positions_to_services_record_confirmed($id,$val,$overwrite_last_notified=false){
      $dbval=time();
      if ($val==0){
        $dbval=-$dbval;
      }      
      $extra="";
      if ($overwrite_last_notified){
        $extra=",last_notification=1"; //Avoid that the person gets system email when manual confirmation has already taken place
      }
      return ($this->d->query("UPDATE positions_to_services SET confirmed=$dbval $extra WHERE id=$id;"));
    }
    
    function mark_rehearsal_participant_record_na($id){
      return ($this->d->query("UPDATE rehearsal_participants SET confirmed=0 WHERE id=$id;"));
    }

    function mark_rehearsal_participant_record_confirmed($id,$val,$overwrite_last_notified=false){
      $dbval=time();
      if ($val==0){
        $dbval=-$dbval;
      }      
      $extra="";
      if ($overwrite_last_notified){
        $extra=",last_notification=1"; //Avoid that the person gets system email when manual confirmation has already taken place
      }
      return ($this->d->query("UPDATE rehearsal_participants SET confirmed=$dbval $extra WHERE id=$id;"));
    }

    //Does a record in positions_to_services exist with $pos_id, $service_id, $person_id?
    function person_has_position_in_service($pos_id,$service_id,$person_id){
      if ($res=$this->d->query("SELECT * FROM positions_to_services WHERE pos_id=$pos_id AND service_id=$service_id AND person_id=$person_id;")){
        return ($res->num_rows>0);
      }
      return false;
    }
    
        
    //Get array of records for all positions or for a given position for a service_id
    function get_positions_to_services_records($service_id,$pos_id=0,$person_id_to_exclude=0){
      $t=array();
      $cond="";
      if ($pos_id!=0){
        $cond=" AND pos_id=$pos_id";
      }
      if ($res=$this->d->query("SELECT * FROM positions_to_services WHERE service_id=$service_id AND person_id<>$person_id_to_exclude $cond;")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;        
    }
    
    //Get all positions of category $pos_type in the service (1 = service leadership positions)
    //If a person ID is passed in ALL positions for that person are returned, even if they are a different $pos_type
    function get_positions_for_service($service_id,$pos_type=1,$get_all_for_person_id=0){
      $t=array();
      $query="
        SELECT
          positions_to_services.*
        FROM
          positions_to_services
        LEFT JOIN
          positions
        ON
          positions_to_services.pos_id=positions.id ";
      if ($get_all_for_person_id>0){
        $query.="
          WHERE
            positions_to_services.service_id=$service_id
          AND
            ((positions.type=$pos_type)
            OR
            (positions_to_services.person_id=$get_all_for_person_id))
          ORDER BY
            positions.title;
        ";
      } else {
        $query.="          
          WHERE
            positions.type=$pos_type
          AND
            positions_to_services.service_id=$service_id
          ORDER BY
            positions.title;
        ";      
      }
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;            
    }
    
    //See if the service $service_id has position $pos_id scheduled. If so, return first $person_id (could be several people in this position)
    function get_person_in_service_position($service_id,$pos_id){
      $query="SELECT person_id FROM positions_to_services WHERE pos_id=$pos_id AND service_id=$service_id;";
      if ($res=$this->d->query($query)){
        if ($r=$res->fetch_assoc()){
          return $r["person_id"];
        } 
      }
      return false;    
    }
    
    //Get the records for all positions the person is scheduled for in this service
    function get_positions_to_services_records_for_person_and_service($service_id,$person_id){
      $t=array();
      if ($res=$this->d->query("SELECT * FROM positions_to_services WHERE service_id=$service_id AND person_id=$person_id;")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;            
    }
    
    function get_positions_to_services_record_by_id($id){
      $r=$this->d->get_record("positions_to_services","id",$id);
      return $r;
    }    
    
    /* positions_to_services__to_service_elements */
    /* THIS IS AN INVERSE TABLE, I.E. AN EXISTING RECORD MEANS THAT THE RELATION DOES N-O-T EXIST; ALL MUSICIANS ARE BY DEFAULT ON ALL PIECES IN A SERVICE */
    
    function unassign_position_from_service_element($pos_to_services_id,$service_element_id){
      if (($pos_to_services_id>0) && ($service_element_id>0)){
        if ($this->pos_to_services_record_is_on_service_element($pos_to_services_id,$service_element_id)){
          //Relation doesn't yet exist, go ahead
          $e=array();
          $e["pos_to_services_id"]=$pos_to_services_id;
          $e["service_element_id"]=$service_element_id;
          return $this->d->insert($e,"positions_to_services__to_service_elements");        
        } else {
          return true; //technically we're successful even though relation exists already
        }
      }
      return false; //invalid data
    }
    
    function assign_position_to_service_element($pos_to_services_id,$service_element_id){
      return ($this->d->query("DELETE FROM positions_to_services__to_service_elements WHERE pos_to_services_id=$pos_to_services_id AND service_element_id=$service_element_id;"));      
    }
    
    function pos_to_services_record_is_on_service_element($pos_to_services_id,$service_element_id){
      $query="SELECT * FROM positions_to_services__to_service_elements WHERE pos_to_services_id=$pos_to_services_id AND service_element_id=$service_element_id;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return false;
        }
      }
      return true;
    }
    
    function toggle_position_on_service_element($pos_to_services_id,$service_element_id){
      if ($this->pos_to_services_record_is_on_service_element($pos_to_services_id,$service_element_id)){
        return $this->unassign_position_from_service_element($pos_to_services_id,$service_element_id);
      } else {
        return $this->assign_position_to_service_element($pos_to_services_id,$service_element_id);      
      }
    }
    
    //Checks whether at least one pos_to_services record exists that has the position $pos_id, and is linked here to $service_element_id
    function position_is_on_service_element($pos_id,$service_element_id){
      $query="
          SELECT
            positions_to_services.service_id
          FROM
            positions_to_services,positions_to_services__to_service_elements
          WHERE
            positions_to_services.pos_id=$pos_id
           AND
            positions_to_services__to_service_elements.pos_to_services_id=positions_to_services.id
           AND
            positions_to_services__to_service_elements.service_element_id=$service_element_id;                             
      ";
      if ($res=$this->d->q($query)){
        if ($res->num_rows==0){
          return true;
        } else {
          //At least one record exists, but that does not necessarily mean that ALL the people on that position are OFF the service element - we need to check how many people occupy the position.
          //To find out, we need to get the number of pos_to_services record that have $pos_id and the same $service_id as the record(s) we found already
          $r=$res->fetch_assoc();
          $service_id=$r["service_id"];
          $query="SELECT * FROM positions_to_services WHERE pos_id=$pos_id AND service_id=".$r["service_id"].";";
          if ($res2=$this->d->q($query)){
            //If the #of occupants of the position that is OFF (res1) is lower than the total #of occupants of the position, then at least one is stil on - return true!
            if ($res->num_rows<$res2->num_rows){
              return true;
            }
          }
        }
      }
      return false;    
    }
    
    /* Music packages */
    
    function assign_partfile_to_position_and_service_element($pos_to_services_id,$service_element_id,$file_id=0){
      if (($pos_to_services_id>0) && ($service_element_id>0)){
        if ($this->clear_partfile_for_position_and_service_element($pos_to_services_id,$service_element_id)){
          if ($file_id>0){
            $e=array();
            $e["pos_to_services_id"]=$pos_to_services_id;
            $e["service_element_id"]=$service_element_id;
            $e["file_id"]=$file_id;
            return $this->d->insert_and_get_id($e,"music_packages");                   
          }
          return true; //cleared
        }
      }
      return false;  
    }
    
    function clear_partfile_for_position_and_service_element($pos_to_services_id,$service_element_id){
      if (($pos_to_services_id>0) && ($service_element_id>0)){
        $query="DELETE FROM music_packages WHERE pos_to_services_id=$pos_to_services_id AND service_element_id=$service_element_id";
        return $this->d->q($query);        
      }  
      return true;      
    }
    
    function clear_all_partfiles_for_position($pos_to_services_id){
      if ($pos_to_services_id>0){
        $query="DELETE FROM music_packages WHERE pos_to_services_id=$pos_to_services_id;";
        return $this->d->q($query);        
      }  
      return true;      
    }

    function get_partfile_for_position_and_service_element($pos_to_services_id,$service_element_id){
      $query="SELECT file_id FROM music_packages WHERE pos_to_services_id=$pos_to_services_id AND service_element_id=$service_element_id";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["file_id"];
        }
      }
      return false;
    }
    
    //$file_ids is an array with specific file_ids, keyed by service_element ids
    //file-id 0 is valid and used on elements that the person is taken off from (pos_to_services__to_service_elements)
    function apply_music_package_to_others($pos_to_services_id,$file_ids,$same_position_only=false){
      /*
        pseudo:
          identify pos_to_services_ids for target group
          clear music_package for pos_to_services_ids in question
          write full set of music_package records for pos_to_services_ids in question
      */
      if (($pos_to_services_id>0) && is_array($file_ids)){
        $applied_to=array();
        if ($pts_rec=$this->get_positions_to_services_record_by_id($pos_to_services_id)){
          if ($same_position_only){
            //Apply to other ensemble singers or pianists or whatever
            $target_pts_recs=$this->get_positions_to_services_records($pts_rec["service_id"],$pts_rec["pos_id"]);          
          } else {
            //Apply to all
            $target_pts_recs=$this->get_positions_for_service($pts_rec["service_id"],$this->get_position_type_id(CW_POSITION_TYPE_WORSHIP_TEAM));
          }
          if (is_array($target_pts_recs)){
            foreach ($target_pts_recs as $p){
              if ($p["person_id"]!=$pts_rec["person_id"]){ //Make sure to not self-apply to the source package
                $applied_to[]=$p["person_id"];
                $this->clear_all_partfiles_for_position($p["id"]);
                foreach ($file_ids as $service_element_id=>$file_id){
                  if ($file_id>0){
                    $this->assign_partfile_to_position_and_service_element($p["id"],$service_element_id,$file_id);
                    //clear pos_to_services__to_service_elements record, in case person was off that element
                    $this->assign_position_to_service_element($p["id"],$service_element_id);                                                                                      
                  } else {
                    //$file_id is 0, so take person off the service element
                    $this->unassign_position_from_service_element($p["id"],$service_element_id);                    
                  }
                }              
              }
            }         
          }               
        }
        //Return array of person_ids the package has been applied to
        return $applied_to;      
      }
      return false;
    }
    
    /* Positions to people */
    
    function add_position_to_person($person_id,$position_id){
      if (!$this->person_has_position($person_id,$position_id)){
        $e=array();
        $e["person_id"]=$person_id;
        $e["position_id"]=$position_id;
        $e["added_at"]=time();
        $this->d->insert($e,"positions_to_people");      
      }
      return true; //Technically we're successful even if relation exists already
    }

    function make_position_dormant_for_person($person_id,$position_id){
      if ($e=$this->get_positions_to_people_record($person_id,$position_id)){
        $e["dormant"]=true;
        return ($this->d->update_record("positions_to_people","id",$e["id"],$e));      
      }
      return false;
    }

    function make_position_active_for_person($person_id,$position_id){
      if ($e=$this->get_positions_to_people_record($person_id,$position_id)){
        $e["dormant"]=false;
        return ($this->d->update_record("positions_to_people","id",$e["id"],$e));      
      }
      return false;
    }
    
    function position_for_person_is_dormant($person_id,$position_id){
      if ($e=$this->get_positions_to_people_record($person_id,$position_id)){
        if ($e["dormant"]){
          return true;
        }
      }
      return false;    
    }
    
    function toggle_position_dormancy_for_person($person_id,$position_id){
      if ($this->position_for_person_is_dormant($person_id,$position_id)){
        return $this->make_position_active_for_person($person_id,$position_id);
      } else {
        return $this->make_position_dormant_for_person($person_id,$position_id);      
      } 
    }
    
    function remove_position_from_person($person_id,$position_id){
      return ($this->d->query("DELETE FROM positions_to_people WHERE person_id=$person_id AND position_id=$position_id;"));
    }
    
    function person_has_position($person_id,$position_id){
      return is_array($this->get_positions_to_people_record($person_id,$position_id));
    }
    
    function get_positions_to_people_record($person_id,$position_id){
      if ($res=$this->d->query("SELECT * FROM positions_to_people WHERE person_id=$person_id AND position_id=$position_id;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;        
    }
    
    //Get array of records
    function get_all_positions_for_person($person_id,$exclude_dormant_positions=true){
      $cond="";
      if ($exlude_dormant_positions){
        $cond=" AND positions_to_people.dormant=false";
      }
      $t="";
      $query="
        SELECT 
          positions_to_people.* 
        FROM
          positions_to_people,positions,position_types
        WHERE
          positions_to_people.person_id=$person_id
        AND 
          positions.type=position_types.id
        AND
          positions.id=positions_to_people.position_id $cond
        ORDER BY position_types.id,positions.title;      
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;              
    }

    //Is the position one of the person's ministry positions?
    function can_person_fill_position($position_id,$person_id){
      $query="SELECT * FROM positions_to_people WHERE position_id=$position_id AND person_id=$person_id";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }
      return false;    
    }

    //Get array of records
    function get_all_persons_for_position($position_id,$exclude_dormant_positions=true){
      $cond="";
      if ($exlude_dormant_positions){
        $cond=" AND dormant=false";
      }
      $t="";
      $query="
        SELECT
          positions_to_people.*
        FROM 
          positions_to_people LEFT JOIN people ON positions_to_people.person_id=people.id 
        WHERE
          positions_to_people.position_id=$position_id
        $cond
        ORDER BY
          people.first_name,people.last_name          
        ;      
      
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;              
    }
    
    /* available_volunteers */
    
    function add_volunteer($person_id,$service_id,$position_id){
      if (($person_id>0) && ($service_id>0) && ($position_id>0)){
        if (!$this->person_has_volunteered($person_id,$service_id,$position_id)){
          //Person has not yet volunteered for this position, add
          $e=array();
          $e["person_id"]=$person_id;
          $e["service_id"]=$service_id;
          $e["position_id"]=$position_id;
          $e["volunteered_at"]=time();
          return $this->d->insert($e,"available_volunteers");        
        } else {
          //Person has volunteered already - technically successful
          return true;
        }
      }      
      return false;
    }
    
    function remove_volunteer($person_id,$service_id,$position_id){
      $query="DELETE FROM available_volunteers WHERE service_id=$service_id AND person_id=$person_id AND position_id=$position_id;";
      return $this->d->q($query);
    }
    
    //Has the person volunteered for this position?
    function person_has_volunteered($person_id,$service_id,$position_id){
      $query="SELECT id FROM available_volunteers WHERE service_id=$service_id AND person_id=$person_id AND position_id=$position_id;";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }    
      return false;
    }
    
    //If person has already volunteered, undo - or else volunteer
    function toggle_volunteer($person_id,$service_id,$position_id){
      if ($this->person_has_volunteered($person_id,$service_id,$position_id)){
        return $this->remove_volunteer($person_id,$service_id,$position_id);
      } else {
        return $this->add_volunteer($person_id,$service_id,$position_id);
      }    
      return false;
    }
    
    //Return array of person_ids
    function who_has_volunteered($service_id,$position_id=0){
      if ($position_id==0){
        $cond="";
      } else {
        $cond="AND position_id=$position_id";
      }
      $query="SELECT person_id FROM available_volunteers WHERE service_id=$service_id $cond;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r["person_id"];
        }
        return $t;
      }    
      return false;            
    }

    //Return available_volunteers records
    function get_volunteers_for_service($service_id){
      $query="SELECT * FROM available_volunteers WHERE service_id=$service_id;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        return $t;
      }    
      return false;                  
    }

    //Has at least 1 person volunteered?
    function someone_has_volunteered($service_id,$position_id){
      $persons=$this->who_has_volunteered($service_id,$position_id);
      if (is_array($persons) && (sizeof($persons)>0)){
        return true;        
      }
      return false;
    }
    
    //For a total of how many positions have people have declared themselves available?     
    function get_number_of_volunteers($service_id){
      $query="SELECT id FROM available_volunteers WHERE service_id=$service_id";
      if ($res=$this->d->q($query)){
        return ($res->num_rows);
      }    
      return false;          
    }
    
    function get_available_volunteers_record($id){
      if ($e=$this->d->get_record("available_volunteers","id",$id)){
        return $e;
      }
      return false;        
    }
    
    function delete_available_volunteers_record($id){
      $this->d->delete($id,"available_volunteers");
    }
    
    
    
    /* Rehearsal participants */
    
    function add_rehearsal_participant_by_position_type($positions_to_services_id,$rehearsal_event_id){
      if (!$this->person_is_rehearsal_participant($positions_to_services_id,$rehearsal_event_id)){
        $e=array();
        $e["rehearsal"]=$rehearsal_event_id;
        $e["positions_to_services_id"]=$positions_to_services_id;
        $e["last_notification"]=-1;
        return $this->d->insert($e,"rehearsal_participants");    
      }
      return true; //technically we're successful even if person was previously a participant in this rehearsal
    }
    
    function person_is_rehearsal_participant($positions_to_services_id,$rehearsal_event_id){
      if ($res=$this->d->query("SELECT * FROM rehearsal_participants WHERE positions_to_services_id=$positions_to_services_id AND rehearsal=$rehearsal_event_id;")){
        return ($res->num_rows>0);
      }
      return false;
    }
    
    function remove_all_rehearsal_participants($rehearsal_event_id){
      if ($res=$this->d->query("DELETE FROM rehearsal_participants WHERE rehearsal=$rehearsal_event_id")){
        return true;      
      }
      return false;
    }
    
    function remove_rehearsal_participant($pos_to_services_id,$rehearsal_event_id){
      if ($res=$this->d->query("DELETE FROM rehearsal_participants WHERE positions_to_services_id=$pos_to_services_id AND rehearsal=$rehearsal_event_id;")){
        return true;      
      }
      return false;    
    }
    
    //Get all the record for one or all rehearsals this person has been scheduled for in this position (and in a particular service)
    function get_rehearsal_participant_records_by_pos_to_services($positions_to_services_id,$rehearsal_event_id=0){
      $cond="";
      if ($rehearsal_id>0){
        $cond=" AND rehearsal=$rehearsal_event_id";
      }
      $t=array();
      if ($res=$this->d->query("SELECT * FROM rehearsal_participants WHERE positions_to_services_id=$positions_to_services_id $cond;")){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      if ($rehearsal_event_id!=0){
        //If a rehearsal was specified, just return record, not an array of records
        return array_shift($t);
      }
      return $t;
    }
        
    //Get the people records for those who have been scheduled for the rehearsal, have been notified, and have NOT disconfirmed participation, 
    function get_rehearsal_participants_for_rehearsal_deletion($rehearsal_event_id){
      //We want person_id via positions_to_services
      $query="
        SELECT DISTINCT
          people.*
        FROM
          people,position_types,rehearsal_participants,positions_to_services,positions
        WHERE
          rehearsal_participants.rehearsal=$rehearsal_event_id
         AND
          rehearsal_participants.last_notification!=-1
         AND
          rehearsal_participants.positions_to_services_id=positions_to_services.id
         AND
          positions_to_services.pos_id=positions.id
         AND
          position_types.id=positions.type
         AND
          positions_to_services.person_id=people.id
         AND
          positions_to_services.confirmed>=0;      
      ";
      $t=array();
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;        
    }
    
    function get_rehearsal_participants($rehearsal_event_id,$include_pos_id=false){
      $include="";
      if ($include_pos_id){
        $include=",positions_to_services.id as pos_id";
      }
      //We want person_id via positions_to_services                   
      $query="
        SELECT
          people.id as person_id,position_types.id as position_type_id$include
        FROM
          people,position_types,rehearsal_participants,positions_to_services,positions
        WHERE
          rehearsal_participants.rehearsal=$rehearsal_event_id
         AND
          rehearsal_participants.positions_to_services_id=positions_to_services.id
         AND
          positions_to_services.pos_id=positions.id
         AND
          position_types.id=positions.type
         AND
          positions_to_services.person_id=people.id;      
      ";
      $t=array();
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      //The above query omits guests, retrieve potential guest participants now as well                   
      $query="
        SELECT
          guests.id as person_id,position_types.id as position_type_id$include
        FROM
          guests,position_types,rehearsal_participants,positions_to_services,positions
        WHERE
          rehearsal_participants.rehearsal=$rehearsal_event_id
         AND
          rehearsal_participants.positions_to_services_id=positions_to_services.id
         AND
          positions_to_services.pos_id=positions.id
         AND
          position_types.id=positions.type
         AND
          positions_to_services.person_id=-guests.id;      
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $r["person_id"]=-$r["person_id"]; //important to invert guest id!
          $t[]=$r;
        }
      }
      return $t;    
    }
    
    function mark_rehearsal_notification_as_sent($id,$time=-1){
      if ($time==-1){
        $time=time();
      }
      if ($res=$this->d->query("SELECT * FROM rehearsal_participants WHERE id=$id")){
        if ($e=$res->fetch_assoc()){
          $e["last_notification"]=$time;
          return $this->d->update_record("rehearsal_participants","id",$id,$e);      
        }
      }
      return false;      
    }
    
    function mark_position_notification_as_sent($id,$time=-1){
      if ($time==-1){
        $time=time();
      }
      if ($res=$this->d->query("SELECT * FROM positions_to_services WHERE id=$id")){
        if ($e=$res->fetch_assoc()){
          $e["last_notification"]=$time;
          return $this->d->update_record("positions_to_services","id",$id,$e);      
        }
      }
      return false;
    }
    
    //Return select options. If one comes out with $selected_ts, preselect it
    function get_favorite_practice_times_for_select($event_timestamp,$selected_ts=0){
      $t="";
      $strings=explode(",",CW_FAVORITE_PRACTICE_TIMES);
      foreach ($strings as $v){
        $ts=strtotime($v,$event_timestamp);
        $selected="";
        if ($ts==$selected_ts){
          $selected="selected='SELECTED'";
        }
        //Ignore those timestamps that are already past
        if ($ts>time()){
          $t.="<option $selected value='$ts'>".date("D M j, g:ia",$ts)."</option>";
        }
      }
      return $t;
    }
    
}

?>
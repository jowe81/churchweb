<?php

class cw_Church_services {
  
    private $d; //Database access
    
    function __construct(cw_Db $d){
      $this->d = $d;
    }
    
    function check_for_table($table="church_services"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE church_services (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          template_id INT,
                          created_at INT,
                          created_by INT,
                          updated_at INT,
                          updated_by INT,
                          updated_by_session_id varchar(32),
                          service_name varchar(150),
                          title varchar(255),
                          offset INT,
                          scheduled_duration INT,
                          actual_duration INT,
                          needed_positions VARCHAR(255),
      					  background_image INT,
      					  background_image_assigned_by INT,      		
                          background_priority TINYINT,
                          use_mdb_backgrounds TINYINT,
                          use_motion_backgrounds TINYINT,
      					  INDEX (template_id,service_name),
      					  INDEX (background_image)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          service_id INT,
                          element_nr INT,
                          element_type INT,
                          duration INT,
                          segment varchar(20),
                          label varchar(100),
                          note TEXT,
                          person_id INT,
                          other_person varchar(30),
                          confirmed TINYINT,
                          group_template_id TINYINT,
                          background INT,  
             			  background_modified_by INT,           		
                          INDEX (service_id,element_nr),
             			  INDEX (background)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE service_element_types (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title char(50),
                          meta_type char(30),
                          default_duration INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE arrangements_to_service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          arrangement INT,
                          service_element INT,
                          lyrics varchar(255),
                          INDEX(service_element,arrangement)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE group_templates (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          label varchar(50),
                          default_elements varchar(255),
                          default_position_in_charge INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE church_service_templates (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          service_name varchar(150),
                          iteration_rules varchar(255),
                          needed_positions VARCHAR(255),
                          default_duration INT,
                          default_room_bookings varchar(255),
                          default_elements varchar(255),
                          default_offset INT,
                          default_background_priority TINYINT,
                          default_use_mdb_backgrounds TINYINT,
                          default_use_motion_backgrounds TINYINT             		
                        )"))
                &&        
             ($this->d->q("CREATE TABLE conversations_to_church_services__planning (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          conversation_id INT,
                          service_id INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE files_to_service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          service_element_id INT,
                          file_id INT,
             			  INDEX(service_element_id,file_id)
                        )"))
		);
                        
                        
        /*
          Explanation of table fields
            church_services:
              updated_at - save here the timestamp of the last modification of service elements
              offset - offset in seconds from the starting time of the event with event_id
      		  background_image - mediabase reference
      		  background_image_assigned_by - person id      		
              background_priority TINYINT - true (1) if "service bg supersedes song/arr bgs" checked (in Projection settings dialogue)
              use_mdb_backgrounds TINYINT - true if "use song/arr bgs when present" checked
              use_motion_backgrounds TINYINT - true if "use motion bgs when available" checked
            service_elements:
              element_nr - chronological order within a service (i.e. within elements that belong to the same event_id)
              element_type - service_element_type (song, sermon, announcements etc)
              segment - "pre-service", "main service", "post-service" etc
              scripture_ref - a string representation of a scriptural reference - other table may have the proper type
              person_id - the person in charge of this element
              other_person - if none of the persons in the database are in charge, store person_id=0 and use this field
              confirmed - if set to false the element is pending (not implemented)
              group_template_id - save this with group headers so that the system can find out from the originating template who should be in charge of the element
             service_element_types:
              title - song, reading, offering, sermon etc.
              meta_type - for example music, or nothing for generic 
            arrangements_to_service_elements
              this connects music information to music service elements
              lyrics contains a comma-separated list of lyrics fragment ids, so that it is possible to deviate
                from the lyrics sequence in the invoked arrangement
            group_templates
              label is unique
              default_elements is csl of service_element_type ids
                examples "5,5,5" (eg three songs)
                         "17%Intro,16%Prayer (bread)%90" (following the first % is the optional label, following the second % is optional duration in seconds )
              default_position_in_charge points to positions.id
            church_service_templates
              iteration_rules: 
                day_of_week.frequency[m].csl_of_start_times;...
                 0.1.9:00,11:00,18:00;6.1.19:00 would be every Sunday at 9,11 and 6pm; and every Saturday at 7pm
                date.mm-dd.csl_of_start_times;...
                 date.12-24.16:00,18:00 would be Christmas Eve at 4pm and 6pm 
              default_room_bookings: CSL room_id.start_offset.end_offset, e.g. 1.-1800.900 means auditorium booked half hour before and quater hour after service
              default_elements:
                CSL service_element_type_id.label, eg. 17.Introduction to communion
              default_offset:
                offset in seconds
            conversations_to_church_services__planning
              assigns each service plan a conversation (planning conversation)
        */                        
        
    }

    private function check_for_and_drop_tables($tables=array()){
    	foreach ($tables as $v){
    		if ($this->check_for_table($v)){
    			$this->d->drop_table($v);
    		}
    	}
    }
    
    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){      
      $tables=array(
      		"church_services",
      		"service_elements",
      		"service_element_types",
      		"arrangements_to_service_elements",
      		"group_templates",
      		"church_service_templates",
      		"conversations_to_church_services__planning",
      		"files_to_service_elements"
      );
      $this->check_for_and_drop_tables($tables);
      $res=$this->create_tables();
      if ($res && $default_records){
        //Add default element types
        $this->add_service_element_type("Prelude","music",240);
        $this->add_service_element_type("Postlude","music",240);
        $this->add_service_element_type("Choir","music",240);
        $this->add_service_element_type("Vocal ensemble","music",240);
        $this->add_service_element_type("Song","music",240);
        $this->add_service_element_type("Presented song","music",240);
        $this->add_service_element_type("Instrumental","music",240);
        $this->add_service_element_type("Sermon","word",1800);
        $this->add_service_element_type("Scripture reading","word",120);
        $this->add_service_element_type("Benediction","word",60);        
        $this->add_service_element_type("Call to worship","word",60);        
        $this->add_service_element_type("Announcements","",300);
        $this->add_service_element_type("Welcome","",120);
        $this->add_service_element_type("Missions moment","",420);
        $this->add_service_element_type("Offering","",60);
        $this->add_service_element_type("Prayer","",180);        
        $this->add_service_element_type("(other)","",120);        
        $this->add_service_element_type("Video","media",300);
        $this->add_service_element_type("Slideshow","media",300);
        $this->add_service_element_type("Skit","drama",300);
        $this->add_service_element_type("Monologue","drama",300);
        //Add default group templates
        $this->add_group_template("Worship with singing");
        $this->update_group_template(1,array("default_elements"=>"5,5,5","default_position_in_charge"=>"2"));
        $this->add_group_template("Communion");
        $this->update_group_template(2,array("default_elements"=>"17.Introduction.120,16.Prayer (bread) and distribution,16.Prayer (cup) and distribution","default_position_in_charge"=>"3"));
        $this->add_group_template("Singen");
        $this->update_group_template(3,array("default_elements"=>"5.Lied,5.Lied,5.Lied","default_position_in_charge"=>"2"));
        //Add default service templates
        $this->add_church_service_template("English service");
        $this->update_church_service_template(1,array(
          "default_room_bookings"=>"1.900.1200",
          "default_offset"=>"60",
          "default_duration"=>"4200",
          "default_elements"=>"0.pre,1,0.main,13.Welcome/Announcements/Prayer.300,-1.1,15.Pastoral prayer/Offering.360,5,8,5,0.post,2",
          "iteration_rules"=>"0.1.11:00",
          "needed_positions"=>"2,3,4,6,7,17,20,21,23"
          )
        );
        $this->add_church_service_template("Deutscher Gottesdienst");
        $this->update_church_service_template(2,array(
          "default_room_bookings"=>"1.1200.600",
          "default_offset"=>"60",
          "default_duration"=>"3900",
          "default_elements"=>"0.pre,1.Vorspiel,0.main,13.Gruessen/Beten.240,-1.3,15.Bekanntmachungen/Gebet/Opfer.480,5.Lied,8.Predigt,5.Lied,0.post,2.Nachspiel",
          "iteration_rules"=>"0.1.9:30",
          "needed_positions"=>"2,3,4,6,7,17,18"
          )
        );
        $this->add_church_service_template("German bible study");
        $this->update_church_service_template(3,array("default_room_bookings"=>"1.900.900","default_offset"=>"0","iteration_rules"=>"3.1.9:30"));
        $this->add_church_service_template("Combined service");
        $this->update_church_service_template(4,array(
          "default_room_bookings"=>"1.900.1200",
          "default_offset"=>"90",
          "default_elements"=>"0.pre,0.main,0.post",
          "iteration_rules"=>""
          )
        );
        $this->add_church_service_template("Christmas Eve service");
        $this->update_church_service_template(5,array(
          "default_room_bookings"=>"1.900.1200",
          "default_offset"=>"120",
          "default_duration"=>"3900",
          "default_elements"=>"0.pre,0.main,0.post",
          "iteration_rules"=>"date.12-24.16:00,18:00"
          )
        );
        $this->add_church_service_template("Membership meeting");
        $this->update_church_service_template(6,array("default_room_bookings"=>"1.900.1200","default_offset"=>"120","iteration_rules"=>"1.4,19:00"));
        $this->add_church_service_template("Youth worship night");
        $this->update_church_service_template(7,array("default_room_bookings"=>"1.900.1200","default_offset"=>"120","iteration_rules"=>"1.4m.19:00"));
        $this->add_church_service_template("Service");
        $this->update_church_service_template(8,array(
          "default_room_bookings"=>"1.900.1200",
          "default_offset"=>"90",
          "default_elements"=>"0.pre,0.main,0.post",
          "iteration_rules"=>""
          )
        );
      }
      return $res;
    }

    /* church_services: declare, undeclare, identify */

    function add_church_service_record($template_id,$title,$created_by=0,$suppress_needed_positions=false){
      $template=$this->get_church_service_template_record($template_id);
      if (is_array($template)){      
        $e=array();
        $e["template_id"]=$template_id;
        $e["service_name"]=$template["service_name"]; //Copy service name from template (because template might change later)
        $e["created_at"]=time();
        $e["created_by"]=$created_by;
        $e["title"]=$title;
        $e["offset"]=$template["default_offset"];
        $e["scheduled_duration"]=$template["default_duration"];
        if (!$suppress_needed_positions){
          $e["needed_positions"]=$template["needed_positions"];        
        }
        $e["use_mdb_backgrounds"]=$template["default_use_mdb_backgrounds"];
        $e["use_motion_backgrounds"]=$template["default_use_motion_backgrounds"];
        $e["background_priority"]=$template["default_background_priority"];
        return $this->d->insert_and_get_id($e,"church_services");
      }
      return true; //If service was declared already, the operation is technically successful  
    }
    
    function update_church_service_record($id,$e,$updated_by=0){
      if (is_array($e)){
        $e["updated_at"]=time();
        $e["updated_by"]=$updated_by;
        $e["updated_by_session_id"]=$_SESSION["session_id"];
        return $this->d->update_record("church_services","id",$id,$e);      
      }      
      return false;
    }
    
    //$lib_id is a reference to media_library
    function assign_background_image_to_service($id,$lib_id,$updated_by=0){
    	if ($r=$this->get_service_record($id)){
    		$r["background_image"]=$lib_id;
    		$r["background_image_assigned_by"]=$updated_by;
    		return $this->update_church_service_record($id,$r,$updated_by);
    	}
    	return false;
    }
    
    //$lib_id is a reference to media_library
    function unassign_background_image_from_service($id){
    	if ($r=$this->get_service_record($id)){
    		$r["background_image"]=0;
    		return $this->update_church_service_record($id, $r);
    	}
    	return false;
    }
    
    
    //"touch" the service
    function mark_service_update($service_id,$updated_by=0){
      return $this->update_church_service_record($service_id,array(),$updated_by);
    }
    
    //Timestamp of latest update
    function get_last_service_update($service_id){  
      if ($r=$this->get_service_record($service_id)){
        return $r["updated_at"];
      }
      return false;    
    }

    //Person-ID of last update
    function get_last_service_update_session_id($service_id){  
      if ($r=$this->get_service_record($service_id)){
        return $r["updated_by_session_id"];
      }
      return false;    
    }

    function delete_church_service_record($id){
      return $this->d->query("DELETE FROM church_services WHERE id=$id;");
    }    

    //Does a church_services record exist for event with $event_id?
    function event_is_service($event_id){
      return ($this->get_service_id_for_event($event_id)>0);
    }
    
    //If event $event_id has been declared a service, return the id
    function get_service_id_for_event($event_id){
      if ($r=$this->d->get_record("church_services","event_id",$event_id)){
        return $r["id"];
      }
      return false;      
    }

    //Get $event_id associated with $service_id
    function get_event_id_for_service($service_id){
      if ($r=$this->d->get_record("church_services","id",$service_id)){
        return $r["event_id"];
      }
      return false;      
    }

    //Does the referenced service exist?    
    function service_exists($service_id){
      return ($r=$this->d->get_record("church_services","id",$service_id));
    }
    
    //Is the last show of this service over?
    function service_is_past($service_id){
      $query="
        SELECT timestamp,duration FROM events WHERE church_service=$service_id ORDER BY timestamp DESC LIMIT 1;      
      ";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return (($r["timestamp"]+$r["duration"]+HOUR)<time());
        }
      }
      return false;    
    }

    function get_latest_update_for_service($service_id){
      if ($r=$this->d->get_record("church_services","id",$service_id)){
        return $r["updated_at"];
      }
      return false;      
    }
    
    function get_service_record($service_id){
      return ($this->d->get_record("church_services","id",$service_id));
    }

    //Effectively remove the contents of the "needed_positions" field
    function close_all_open_positions($service_id){
      if ($r=$this->get_service_record($service_id)){
        $r["needed_positions"]="";
        return $this->d->update_record("church_services","id",$service_id,$r);
      }
      return false;    
    }
    
    //How many times is this position marked open?
    function get_number_of_open_instances_for_position($service_id,$position_id){
      if ($r=$this->get_service_record($service_id)){
        $cnt=0;
        $needed_positions=explode(',',$r["needed_positions"]);
        foreach($needed_positions as $v){
          if ($v==$position_id){
            $cnt++;
          }
        }
        return $cnt;
      }
      return false;
    }
    
    function add_open_position($service_id,$position_id){
      if ($r=$this->get_service_record($service_id)){
        $r["needed_positions"]=csl_append_element($r["needed_positions"],$position_id);
        return $this->d->update_record("church_services","id",$service_id,$r);
      }
      return false;    
    }

    function remove_open_position($service_id,$position_id){
      if ($r=$this->get_service_record($service_id)){
        $r["needed_positions"]=csl_delete_element($r["needed_positions"],$position_id);
        return $this->d->update_record("church_services","id",$service_id,$r);
      }
      return false;    
    }

    /* Service elements: add, delete, identify, retrieve */

    //Retrieve record for service element
    function get_service_element_record($service_element_id){
      if ($e=$this->d->get_record("service_elements","id",$service_element_id)){
        return $e;
      }
      return false;
    }
    
    function get_service_element_by_service_id_and_position($service_id,$pos,$return_full_record=false){
      $return_full_record ? $sel="*" : $sel="id";
      if ($res=$this->d->query("SELECT $sel FROM service_elements WHERE service_id=$service_id AND element_nr=$pos;")){
        if ($r=$res->fetch_assoc()){
          $return_full_record ? $t=$r : $t=$r["id"];
          return $t;
        }      
      }
      return false;
    }
    
    //Get array of all service element records for service $service_id
    function get_all_service_element_records($service_id,$include_meta_type=false,$include_type_title=false){
      $t=array();
      if ($res=$this->d->query("SELECT * FROM service_elements WHERE service_id=$service_id ORDER BY element_nr")){
        while ($r=$res->fetch_assoc()){
          if (($include_meta_type)||($include_type_title)){
            if ($q=$this->get_service_element_type_record($r["element_type"])){
              $include_meta_type ? $r["meta_type"]=$q["meta_type"] : null;
              $include_type_title ? $r["title"]=$q["title"] : null;
            }
          }
          $t[]=$r;
        }      
      }
      return $t;
    }
    
    //Get an array of ids in chronological order
    function get_service_element_ids($service_id){
    	$t=array();
    	$query="SELECT id FROM service_elements WHERE service_id=$service_id ORDER BY element_nr";
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			$t[]=$r['id'];
    		}
    	}
    	return $t;
    }
    
    
    function get_no_of_elements_in_service($service_id){
      if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id;")){
        return $res->num_rows;
      }
      return false;
    }

    //Add element at the end of service plan
    function add_service_element($service_id,$element_type,$duration=0,$segment="",$label="",$note="",$person_id=0,$other_person="",$confirmed=true){
      //The service referenced must be extant
      if ($this->service_exists($service_id)){
        //Try to get info about element type
        if ($type_record=$this->get_service_element_type_record($element_type)){
          if ($duration==0){
            //No duration given, take from type record
            $duration=$type_record["default_duration"];
            //Still no duration? Resort to basic default
            empty($duration) ? $duration=CW_SERVICE_PLANNING_DEFAULT_ELEMENT_DURATION : null; 
          }        
          if (empty($label)){
            //Default to the type title if no label was provided
            $label=$type_record["title"];
          }
        }                 
        $e=array();
        $e["service_id"]=$service_id;
        $e["element_type"]=$element_type;
        $e["duration"]=$duration;
        $e["segment"]=$segment;
        $e["label"]=$label;
        $e["note"]=$note;
        $e["person_id"]=$person_id;
        $e["other_person"]=$other_person;
        $e["confirmed"]=$confirmed;
        $e["element_nr"]=$this->get_element_count($service_id)+1;
        if ($new_id=$this->d->insert_and_get_id($e,"service_elements")){
          $this->mark_service_update($service_id);
          return $new_id;
        }                                 
      }                                      
      return false;
    }
    
    //Create default record and position to $pos.
    //If collapse_generic: collapse new element with the one in previous position if they are both of the generic meta-type and the first one has no note, and same person
    function add_service_element_at_position($pos,$service_id,$element_type,$person_in_charge,$collapse_generic=true){
      if ($id=$this->add_service_element($service_id,$element_type,0,'','','',$person_in_charge)){
        if ($this->reposition_service_element($id,$pos)){
          if ($collapse_generic){
            //Check if we can collapse the new element with the previous one
            //Get previous element
            if ($prev=$this->get_service_element_record($this->get_service_element_by_service_id_and_position($service_id,$pos-1))){
              //$prev has previous element. - Get current
              if ($prev["duration"]>0){
                //Make sure this is not a divider
                if ($curr=$this->get_service_element_record($id)){
                  //Compare meta_types and see if both are same and generic (empty meta_type field)
                  $prev_meta_type=$this->get_meta_type_for_element_type($prev["element_type"]);
                  $curr_meta_type=$this->get_meta_type_for_element_type($curr["element_type"]);
                  if (($prev_meta_type==$curr_meta_type) && ($curr_meta_type=="") && ($prev["person_id"]==$curr["person_id"]) && ($prev["other_person"]==$curr["other_person"]) && ($prev["notes"]=="") && ($curr["label"]!="(other)")){
                    /*
                      We can collapse $curr and $prev into one:
                      Label becomes: prev.label / curr.label
                      Time adds together
                    */
                    $curr["label"]=$prev["label"]."/".$curr["label"];
                    $curr["duration"]=$prev["duration"]+$curr["duration"];
                    //Save this in current element
                    $this->update_service_element($curr["id"],$curr);
                    //Delete previous
                    $this->delete_service_element($prev["id"]);
                  }
                }              
              }
            } else {
              //This is probably the first element - can't collapse
            }
          }
          return $id;
        }
      }
      return false;
    }
    
    function update_service_element($element_id,$e){
      return $this->d->update_record("service_elements","id",$element_id,$e);
    }

    function overwrite_service_element_duration($element_id,$duration){
      $e=array();
      $e["duration"]=$duration;
      return $this->update_service_element($element_id,$e);
    }

    //Return the record of either the previous or the next element in the service
    function get_neighboring_service_element($service_element_id,$prev,$service_id=0){
      if (($service_id>0) || ($service_id=$this->get_service_id_for_element($service_element_id))){
        //Got service id
        if ($element=$this->get_service_element_record($service_element_id)){
          //Got the reference element
          $prev ? $n=-1 : $n=1;
          if ($e_id=$this->get_element_id_at_position($service_id,$element["element_nr"]+$n)){
            //Got ID of element in question
            if ($res=$this->get_service_element_record($e_id)){
              //Got requested element
              return $res;
            }
          }                  
        }         
      }
      return false;
    }

    //Does the element have two neighbors (both sides) that belong to the same group?
    function element_sits_between_group_members($service_element_id,$service_id){
      if (($prev=$this->get_neighboring_service_element($service_element_id,true,$service_id)) && ($next=$this->get_neighboring_service_element($service_element_id,false,$service_id))){
        //Got both neighbors
        if (($this->is_group_segment_string($prev["segment"])) && ($prev["segment"]==$next["segment"])){
          //Both neighbors are members of a group - and the same one. return the group segment_id.
          return $prev["segment"];
        }
        return false;                    
      }
    }

    //Assign a position on the service plan to the element. 1 is first item, 0 or otherwise out of range is last item    
    function reposition_service_element($service_element_id,$new_pos,$group_considerations=true){    
      //Get service id
      if ($service_id=$this->get_service_id_for_element($service_element_id)){
        //If pre-service divider exists and $new_pos==1,adjust $new_pos to 2 (to avoid dropping elements above the pre-service divider)
        if (($this->service_has_dividers($service_id)>0) && ($new_pos==1)){
          $new_pos=2;
        }
        //Ensure validity of desired position
        if ($cnt=$this->get_element_count($service_id)){
          if (($new_pos<1) || ($new_pos>$cnt)){
            //$new_pos is out of range -> use proper last position
            $new_pos=$cnt;
          }
          
          //Make sure user is not trying to place a group header within a group
          //Have to check whether the element that is NOW at the destination position has a group element directly above it
          
          //Move elements in following positions down by 1
          $this->move_following_elements_down($service_element_id);      
          //Temporarily assign negative position (to get the element out of the way)
          $this->overwrite_element_pos($service_element_id,-1);
          //Get service_element_id of the element that occupies the target position
          $x=$this->get_element_id_at_position($service_id,$new_pos);
          //Move the element that sits at the target position and its followers up by one (create gap at desired position)
          $this->move_this_and_following_elements_up($x);
          //Assign desired position
          $this->overwrite_element_pos($service_element_id,$new_pos);
          
          /*
            Group-considerations:
            - if element was formerly a member of a group and now has no member (or header) of its group directly above, then take it out of group
            - if element was formerly not a group member and has now two members of the same group (could be header) both above and below, then put it in the group
          */
          if ($group_considerations){
            if ($group_segment=$this->service_element_is_group_member($service_element_id)){
              //Element is group member - see if element above is, too (of same group)
              if ($prev=$this->get_neighboring_service_element($service_element_id,true,$service_id)){
                if (!($prev["segment"]==$group_segment)){
                  //Element above is either not in a group or part of a different group. Therefore take this element out of group.
                  $this->remove_element_from_group($service_element_id);
                }                
              } else {
                //There was no neighbor above - top position. Take out as well.
                $this->remove_element_from_group($service_element_id);
              }
            } else {
              //Element is not a group member - see if both elements above and below are, and if they are of the same group, add this element, too
              if ($group_segment=$this->element_sits_between_group_members($service_element_id,$service_id)){
                //Both neighbors are members of a group - and the same one. So this element joins, too.
                $this->add_element_to_group($group_segment,$service_element_id);              
              }
            }
          }
          //Mark the service as updated
          $this->mark_service_update($service_id);
          return true;      
        } //cnt not available      
      } //service-id not found
      return false;
    }
    
    //Remove divider elements from service (element_type=0)
    function delete_dividers($service_id){
      $query="DELETE FROM service_elements WHERE service_id=$service_id AND element_type=0;";
      return ($this->d->q($query));
    }
    
    function create_pre_main_post_dividers($service_id){
      $this->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_PRE,CW_SERVICE_PLANNING_SEGMENT_PRE);
      $this->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_MAIN,CW_SERVICE_PLANNING_SEGMENT_MAIN);
      $this->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_POST,CW_SERVICE_PLANNING_SEGMENT_POST);    
    }
        
    //Remove a service element
    function delete_service_element($service_element_id){
      //Need to make sure that associated records get deleted first!
      //Get service id
      $service_id=$this->get_service_id_for_element($service_element_id);
      //Mark update on service 
      $this->mark_service_update($service_id);
      //Make sure to close the gap
      $this->move_following_elements_down($service_element_id);
      return $this->delete_service_element_record($service_element_id);
    }

    //Put this element to the end of the service plan
    function element_to_end_of_service($service_element_id){
      $this->reposition_service_element($service_element_id,0);                
    }

    //Put this element to the beginning of the service plan
    function element_to_beginning_of_service($service_element_id){
      $this->reposition_service_element($service_element_id,1);                
    }

    //Get positon of the element in its service plan (return $element_nr)
    function get_element_pos($service_element_id){
      if ($e=$this->get_service_element_record($service_element_id)){
        return $e["element_nr"];
      }
      return false;          
    }
    
    //How many elements exist in this service plan?
    function get_element_count($service_id){
      if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id;")){
        return $res->num_rows;
      }
      return false;    
    }
    
    //What service plan does this element belong to?
    function get_service_id_for_element($service_element_id){
      if ($e=$this->d->get_record("service_elements","id",$service_element_id)){
        return $e["service_id"];
      }
      return false;                
    }
    
    /************** Private utility functions for moving elements around ***************/
    
        //Directly overwrite element_nr
        private function overwrite_element_pos($service_element_id,$new_pos){
          if ($e=$this->get_service_element_record($service_element_id)){
            $e["element_nr"]=$new_pos;
            $this->d->update_record("service_elements","id",$service_element_id,$e);
          }          
          return false;
        }
            
        //Directly overwrite element position with element_position++
        private function move_element_up($service_element_id,$steps=1){
          return $this->overwrite_element_pos($service_element_id,$this->get_element_pos($service_element_id)+$steps);      
        }
        
        //Directly overwrite element position with element_position--
        private function move_element_down($service_element_id,$steps=1){
          return $this->overwrite_element_pos($service_element_id,$this->get_element_pos($service_element_id)-$steps);      
        }
        
        private function move_this_and_following_elements_up($service_element_id,$steps=1){
          /*	
          $t=$this->get_following_elements($service_element_id,true);
          foreach ($t as $v){
            $this->move_element_up($v,$steps);      
          }
          */

        	
         	//Move all with one query - way faster!
        	if ($e=$this->get_service_element_record($service_element_id)){
        		$current_element_pos=$e["element_nr"];
        		$service_id=$e["service_id"];
        		$q="UPDATE service_elements SET element_nr=element_nr+$steps WHERE service_id=$service_id AND element_nr>=$current_element_pos";
        		$this->d->q($q);
        	}
        	 
        }
    
        private function move_following_elements_down($service_element_id,$steps=1){

		  /*
		  //Old and slow algorithm
		  $t=$this->get_following_elements($service_element_id);
          foreach ($t as $v){
            $this->move_element_down($v,$steps);      
          }
		  */
          
        	
          //Move all with one query - way faster!
          if ($e=$this->get_service_element_record($service_element_id)){
          	$current_element_pos=$e["element_nr"];
          	$service_id=$e["service_id"];
          	$q="UPDATE service_elements SET element_nr=element_nr-$steps WHERE service_id=$service_id AND element_nr>$current_element_pos";
          	$this->d->q($q); 
          }
          
          
          
        }
        
        //Return array of all service_element_ids the element_nr of which is higher than that of $service_element_id
        private function get_following_elements($service_element_id,$include_this=false){
          $t=array();
          //Find out what service the element belongs to
          $e=$this->get_service_element_record($service_element_id);      
          $include_this ? $c="=" : $c="";
          if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=".$e["service_id"]." AND element_nr>$c".$e["element_nr"].";")){
            while ($r=$res->fetch_assoc()){
              $t[]=$r["id"];
            }      
          }
          return $t;
        }

        function get_element_id_at_position($service_id,$pos){
          if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id AND element_nr=$pos;")){
            if ($r=$res->fetch_assoc()){
              return $r["id"];
            }
          }
          return false;
        }
        
        //Delete physical record
        private function delete_service_element_record($service_element_id){
          return $this->d->delete($service_element_id,"service_elements","id");
        }
        
    /************** end private utility functions *****************************************/
    

    //$lib_id refers to media_library. Use $mb object to determine media type
    function assign_background_to_service_element($id,$lib_id,cw_Mediabase $mb,$background_modified_by){
       	if ($r=$this->get_service_element_record($id)){
   			$r["background"]=$lib_id;
   			$r["background_modified_by"]=$background_modified_by;
       		return ($this->update_service_element($id, $r));
        }
        return false;
    }
        
    //A value of 0 means auto (element may inherit background from service or song)
    function set_background_for_service_element_to_auto($id,$person_id){
        if ($r=$this->get_service_element_record($id)){
        	$r["background"]=0;
    		$r["background_modified_by"]=$person_id;
        	return ($this->update_service_element($id, $r));
        }
        return false;
    }

    //A value of -1 means explicitly NO background 
    function unassign_background_from_service_element($id,$person_id){
    	if ($r=$this->get_service_element_record($id)){
    		$r["background"]=-1;
    		$r["background_modified_by"]=$person_id;
    		return ($this->update_service_element($id, $r));
    	}
    	return false;
    }
    
    /*
     * This is actually a higher level function:
    * 	Return file_id:
    * 	(a) from church_services.background_image, if .background_priority==1
    * 	(b) from .background_image, or - if extant - from music_pieces.background_image/video, or arrangements.background_image/video
    *
    *  In case of (b), the priority order (top down) is: arrangement, music_piece, church service
    *  Also in case of (b), depending on whether church_services.use_motion_backgrounds is set, the referenced file from music_piece
    *   or arrangement could be a video file.
    *
    *
    *
    *
    *
    * */
    function get_service_element_background($service_element_id,$a){
    	$service_id=$this->get_service_id_for_element($service_element_id);
    	if (($service_id>0) && ($r=$this->get_service_record($service_id)) && ($e=$this->get_service_element_record($service_element_id))){
    		($r["use_motion_backgrounds"]) ? $type="video" : $type="image";
    		//Is bg directly assigned to service element?
    		if ($e["background"]>0){
	    		return $e["background"]; //This can be image or video, whatever has been directly selected - highest priority
    		} elseif ($e["background"]==0) {
    			//No bg assigned to service element directly: Auto-detect actual background
	    		if ($r["background_priority"]==1){
	    			//Service background supersedes song/arrangement backgrounds is checked. Return service background.
    				return $r["background_image"]; //Service background is image always
	    		} elseif ($r["background_priority"]==0){
	    			//Find song/arr background
	    			if (($r["use_mdb_backgrounds"]) && ($atse=$this->get_atse_for_service_element($service_element_id,true))){
	    				if ($mdb=new cw_Music_db($a)){
	    					if ($arr=$mdb->get_arrangement_record($atse["arrangement"])){
	    						if ($arr["background_$type"]>0){
	    							//Found either image or video with arrangement
	    							return $arr["background_$type"];
	    						} else {
	    							//Required media was not with arrangement, check music_piece
	    							if ($m=$mdb->get_music_piece_record($arr["music_piece"])){
	    								if ($m["background_$type"]>0){
	    									//Found either image or video with music_piece
	    									return $m["background_$type"];
	    								} else {
	    									//Required media not found with either arr or music_piece. In this case fallback to arr.image (even if that's also 0)
	    									return $arr["background_image"];
	    								}
	    							}
	    						}
	    					}
	    				}
	    			} else {
	    				//No music piece assigned to that element, or use_mdb_backgrounds=false, use direct assignment with service
	    				return $r["background_image"];
	    			}
	    		}
    		} else {
    			//Background for service element is marked -1; meaning BLACK (none/0)
    			return 0;
    		}
    	}
    	return false;
    }
    
        
    /* service_element_types */
    
    function add_service_element_type($title,$meta_type="",$default_duration=CW_SERVICE_PLANNING_DEFAULT_ELEMENT_DURATION){
      if (!$this->service_element_type_exists($title)){
        $e=array();
        $e["title"]=$title;
        $e["meta_type"]=$meta_type;
        $e["default_duration"]=$default_duration;
        return $this->d->insert($e,"service_element_types");
      }
    }   
    
    function delete_service_element_type($id){
      return $this->d->delete($id,"service_element_types","id");    
    }
    
    function is_music_element_label($label){
      //this should at some point check against the database (service_element_types titles)
      $labels=explode(",","vorspiel,nachspiel,vortragsst�ck,vortragslied,solo,musikbeitrag,recessional,processional,instrumental,instrumentalst�ck,liedvortrag,musikst�ck,song,lied,gesangsbeitrag,chor,choir,presented song,gruppenlied,m�nnerchor,gemischter chor,gruppe,vocal ensemble,group,congregation");
      return in_array(strtolower($label),$labels);
    }
    
    function service_element_type_exists($title){
      return ($r=$this->get_service_element_type_record($this->get_service_element_type_id($title)));
    }
    
    function get_service_element_type_id($title){
      if ($e=$this->d->get_record("service_element_types","title",$title)){
        return $e["id"];
      }
      return false;            
    }
    
    function get_service_element_type_record($id){
      if ($e=$this->d->get_record("service_element_types","id",$id)){
        return $e;
      }
      return false;      
    }
    
    function get_meta_type_for_element_type($id){
      if ($r=$this->get_service_element_type_record($id)){
        return $r["meta_type"];
      }
      return false;
    }
    
    function get_service_element_type_title($id){
      if ($e=$this->get_service_element_type_record($id)){
        return $e["title"];
      }
      return false;
    }
    
    function get_all_service_element_types(){
      return ($this->d->get_table("service_element_types","meta_type,title"));  
    }
    
    //For the element types that can be dragged into the service plan
    function get_service_element_types_for_ul(){
      if ($types=$this->get_all_service_element_types()){
        $t="";
        $t.="<li class=\"ui-state-default etype_divider\"></li>";            
        $t.="<li id=\"egroup_header\" class=\"ui-state-default element_type\">(group header)</li>";
        $t.="<li class=\"ui-state-default etype_divider\"></li>";            
        $this_meta_type="";
        $color_no=1;
        $color=get_bg_color($color_no);//utilities_misc
        foreach ($types as $v){
          if ($v["meta_type"]!=$this_meta_type){
            $t.="<li class=\"ui-state-default etype_divider\"></li>";
            $color_no++;
            $color=get_bg_color($color_no);//utilities_misc            
          }
          $t.="<li id=\"e".$v["id"]."\" style='background:$color;' class=\"ui-state-default element_type\">".$v["title"]."</li>";
          $this_meta_type=$v["meta_type"];        
        }
        return $t;
      }
      return false;
    }

    //Does this service have divider elements? How many?
    function service_has_dividers($service_id){
      if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id AND element_type=0 AND duration=0;")){
        return ($res->num_rows);      
      }
      return false;
    }
    
    /* Groups of service elements */
    
    
    //Group headers are identified by element_type -1. How many are there in the service?    
    function get_no_of_groups_in_service($service_id){
      if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id AND element_type=-1;")){
        return $res->num_rows;
      }
      return false;
    }

    //Does a group with this segment_id exist in the service?    
    function group_segment_id_exists($service_id,$group_segment){
      if ($res=$this->d->query("SELECT id FROM service_elements WHERE service_id=$service_id AND segment='$group_segment';")){
        return ($res->num_rows>0);
      }
    }
    
    //Get a group_segment_id that doesn't exist yet in the service
    private function get_group_segment_id($service_id){
      do {
        $s="g_".create_sessionid(3);//utilities_misc      
      } while ($this->group_segment_id_exists($service_id,$s));
      return $s;
    }
    
    function add_group_header($service_id,$pos=0,$label=""){ 
      //Only do this if the target position is not within a group
      if ($this->element_at_position_is_not_a_group_member($service_id,$pos)){
        $element_type_id=-1; //Group headers, by convention, have this element_type_id
        $segment=$this->get_group_segment_id($service_id); //Obtain an unused segment id
        if (empty($label)){
          $label=CW_SERVICE_PLANNING_DEFAULT_GROUP_LABEL;
        }    
        if ($id=$this->add_service_element($service_id,$element_type_id,0,$segment,$label,'',0)){
          //$id has a service_element_id
          if ($pos==0){
            //No positioning required
            return $id;        
          } else {
            //Move to requested position
            if ($this->reposition_group($service_id,$segment,$pos)){
              return $id; //on success return the service element id of the group header
            }
          }
        }      
      } else {
        //Tried to add a group header within a group - reject!
      }      
      return false;
    }
    
    //Return ids of elements that are in the group (with or without header)
    function get_group_elements($service_id,$group_segment,$include_header=false){
      $t=array();
      $include_header ? $cond="" : $cond=" AND element_type>0";
      $query="SELECT id FROM service_elements WHERE service_id=$service_id AND segment='$group_segment' $cond ORDER BY element_nr";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r["id"];
        }      
        return $t;        
      }
      return false;
    }
    
    function get_group_header($service_id,$group_segment){
      $query="SELECT * FROM service_elements WHERE service_id=$service_id AND segment='$group_segment' AND element_type=-1;";
      if ($res=$this->d->query($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }        
      }
      return false;    
    }
    
    //Mark element with $id to be part of group $group_segment
    function add_element_to_group($group_segment,$service_element_id){
      if ($e=$this->get_service_element_record($service_element_id)){
        $e["segment"]=$group_segment;
        return $this->update_service_element($service_element_id,$e);            
      }
      return false;
    }

    //Mark element with $id to be part of no group
    function remove_element_from_group($service_element_id){
      if ($e=$this->get_service_element_record($service_element_id)){
        $e["segment"]="";
        return $this->update_service_element($service_element_id,$e);            
      }
      return false;
    }
    
    //Nark possible members as part of no group and delete the header
    function delete_group_header($service_id,$group_segment){
      $r=$this->get_group_elements($service_id,$group_segment);
      if ($r!==false){
        //Delete member markings
        foreach ($r as $v){
          //$v has service element records
          $this->remove_element_from_group($v["id"]);
        }
        //Delete group header
        return $this->d->query("DELETE FROM service_elements WHERE service_id=$service_id AND segment='$group_segment';");
      }
      return false;      
    }
    
    //Returns true if the element at $pos is a group header (element_type=-1) or not part of a group at all
    function element_at_position_is_not_a_group_member($service_id,$pos){
      $e=$this->get_service_element_by_service_id_and_position($service_id,$pos,true);
      if (is_array($e)){
        if (($e["element_type"]==-1) || (!$this->is_group_segment_string($e["segment"]))){
          //Element is indeed either a header or not part of a group at all
          return true; 
        }
        //Element is a group member    
        return false;     
      }
      //this happens if $pos is out of range
      return true;
    }
    
    //Reposition a group header with all its members. $pos is the new position of the header.
    function reposition_group($service_id,$group_segment,$pos){
      //Have to make same test as with adding group headers: the group that's being moved must not end up within another group
      //i.e. the element that now sits at $pos must be either not a group member, OR it must be the header of a group
      if (($this->service_has_dividers($service_id)>0) && ($pos==1)){
        //Dividers exist and an attempt has been made to put the group to the very top. Adjust.
        $pos=2;
      }    
      if ($members=$this->get_group_elements($service_id,$group_segment,true)){
        //Got ALL elements (including header) - $members has array of service_element ids.
        $no_of_members=sizeof($members);
        if ($no_of_members>0){
          //members[0] is the header         
          if ($header=$this->get_service_element_record($members[0])){
            $error=false;
            //see if group is supposed to move up or down
            if ($pos<$header["element_nr"]){
              //Group supposed to move up
              //Ensure that target pos is not occupied by an element that is within another group
              if ($this->element_at_position_is_not_a_group_member($service_id,$pos)){
                $n=0;
                foreach ($members as $v){
                  $this->reposition_service_element($v,$pos+$n,false) ? null : $error=true;
                  $n++; //Header will end up at posision $pos, but each following element will be a position down
                }        
              }
            } elseif ($pos>=$header["element_nr"]+sizeof($members)){ //All positions within the group are invalid
              $pos++;//Important!
              //Group supposed to move down
              //Ensure that target pos is not occupied by an element that is within another group
              if ($this->element_at_position_is_not_a_group_member($service_id,$pos)){              
                /* 
                  - take group out (assign neg. element_nrs)
                  - close gap
                  - make gap at destination
                  - put group in
                */
                //CLOSE GAP: the (former) bottom members' lower neighbor now occupies the (former) position of the header element
                //$members[$no_of_members-1] is the id of the lowest sitting group element
                $bottom_element_id=$members[$no_of_members-1];
                $this->move_following_elements_down($bottom_element_id,$no_of_members); //They all move number of members steps 
                //TAKE GROUP OUT
                for ($i=-$no_of_members;$i<0;$i++){
                  $this->overwrite_element_pos($members[$i+$no_of_members],$i); //Assign neg. positions up to -1
                }
                //MAKE GAP AT DESTINATION
                //Destination has moved up ('down') by $no_of_members steps
                $pos_to_move=$pos-$no_of_members;
                $this->move_this_and_following_elements_up($this->get_element_id_at_position($service_id,$pos_to_move),$no_of_members);
                //PUT GROUP IN
                //The ids for the group are now $pos - $pos+$no_of_members              
                for ($i=0;$i<$no_of_members;$i++){
                  $this->overwrite_element_pos($members[$i],$pos-$no_of_members+$i); 
                }
              }
            } else {
              return true; //New position was same as old, or invalid request (i.e. group header has been moved into the group)
            }
            return (!$error);
          }
        }
      }
      return false;    
    }
    
    //Is the string a valid and existing group_Segment_id?
    function is_group_segment($service_id,$group_segment){
      if ($this->is_group_segment_string($group_segment)){
        return $this->group_segment_id_exists($service_id,$group_segment);      
      }
      return false;
    }
    
    //Is the string syntactically correct as a group_segment id? 
    function is_group_segment_string($t){
      return (substr($t,0,2)=="g_");
    }
    
    //Is this element part of a group? If so, return group_segment id
    function service_element_is_group_member($service_element_id){
      if ($e=$this->get_service_element_record($service_element_id)){
        if ($this->is_group_segment_string($e["segment"])){
          return $e["segment"];
        }
      }
      return false;
    }
    
    /* arrangements_to_service_elements */
    
    //Assign an arrangement to a service element (overwrite possibly existing previous association)
    //Expects list of lyrics fragment ids in $lyrics_sequence
    //Should be called only from event_handling, not directly
    function assign_arrangement_to_service_element($arrangement,$service_element,$lyrics_sequence){
      if ($this->unassign_arrangement_from_service_element($service_element)){
        $e=array();
        $e["arrangement"]=$arrangement;
        $e["service_element"]=$service_element;
        $e["lyrics"]=$lyrics_sequence;
        return $this->d->insert_and_get_id($e,"arrangements_to_service_elements");
      }      
    }
    
    //If an arrangement has previously been assigned to the service element, remove it
    function unassign_arrangement_from_service_element($service_element){
      return ($this->d->q("DELETE FROM arrangements_to_service_elements WHERE service_element=$service_element"));
    }
    
    function get_arrangements_to_service_elements_record($atse_id){
      return ($this->d->get_record("arrangements_to_service_elements","id",$atse_id));    
    }
    
    function update_lyrics_sequence_on_service_element($atse_id,$lyrics_sequence){
      $e=array();
      $e["lyrics"]=$lyrics_sequence;
      return $this->d->update_record("arrangements_to_service_elements","id",$atse_id,$e);
    }
    
    function arrangement_is_assigned_to_service_element($arrangement,$service_element){
      if ($res=$this->d->q("SELECT id FROM arrangements_to_service_elements WHERE arrangement=$arrangement AND service_element=$service_element;")){
        if ($r=$res->fetch_assoc()){                                                                        
          return $r["id"];
        }      
      }
      return false;
    }
    
    //Check if service element has an arrangement assigned. Return atse_id, if so, or whole atse_record if so desired
    function get_atse_for_service_element($service_element,$get_whole_atse_record=false){
      if ($res=$this->d->q("SELECT * FROM arrangements_to_service_elements WHERE service_element=$service_element")){
        if ($r=$res->fetch_assoc()){
          if ($get_whole_atse_record){
            return $r;
          } else {
            return $r["id"];
          }
        }      
      }
      return false;    
    }
    
    function get_service_id_by_arrangements_to_services_record($atse_id){
      $query="
        SELECT
          church_services.id
        FROM
          church_services,service_elements,arrangements_to_service_elements
        WHERE
          arrangements_to_service_elements.id=$atse_id
        AND
          arrangements_to_service_elements.service_element=service_elements.id
        AND
          service_elements.service_id=church_services.id;
      ";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    //Return the id for the first element of meta-type music that does not have an arrangement assigned
    function get_first_empty_music_element($service_id,$presentation_elements=false){
      //Get all elements in the service, include field "meta_type"
      $r=$this->get_all_service_element_records($service_id,true,true);
      if (is_array($r)){
        foreach($r as $v){
          if ($v["meta_type"]=="music"){
            //Make sure to find appropriate slot: Either it is not a "Song" and $presentation_elements is set, or it is a "Song" and $presentation_elements is not set
            if ( ($presentation_elements) && ($v["title"]!="Song") || (!$presentation_elements) && ($v["title"]=="Song")){
              //Found matching element, check if it has an arr assigned
              if (!$this->get_atse_for_service_element($v["id"])){
                //No, it doesnt. Return id.
                return $v["id"];
              }
            }
          }
        }
      }
      return false;    
    }
    
    /*  group_templates */
        
    function add_group_template($label){
      if (!$this->group_template_exists($label)){
        //Add new
        $e=array();      
        $e["label"]=$label;
        return $this->d->insert_and_get_id($e,"group_templates");                
      }
      return false;
    }
    
    function update_group_template($id,$e){
      if (is_array($e)){
        return $this->d->update_record("group_templates","id",$id,$e);                
      }      
    }
        
    function delete_group_template($id){
      return $this->d->delete($id,"group_templates","id");
    }

    function group_template_exists($label){
      return $this->get_group_template_id($label);
    }
    
    function get_group_template_record($id){
      return ($this->d->get_record("group_templates","id",$id));        
    }

    function get_all_group_templates(){
      return $this->d->get_table("group_templates");
    }

    function get_group_template_id($label){
      $r=$this->d->get_record("group_templates","LOWER(label)",strtolower($label));
      if (is_array($r)){
        return $r["id"];      
      }        
    }
    
    function get_all_group_template_records(){
      return $this->d->get_table("group_templates");
    }
    
    /*  church_service_templates */

    function add_church_service_template($service_name){
      if (!$this->church_service_template_exists($service_name)){
        //Add new
        $e=array();      
        $e["service_name"]=$service_name;
        //$e["iteration_rules"]="0.1.".CW_DEFAULT_SERVICE_START_TIME; //Default to weekly on Sunday
        $e["default_duration"]=CW_DEFAULT_SERVICE_DURATION;
        $e["default_use_mdb_backgrounds"]=CW_SERVICE_PLANNING_USE_MDB_BACKGROUNDS_BY_DEFAULT;
        $e["default_use_motion_backgrounds"]=CW_SERVICE_PLANNING_USE_MOTION_BACKGROUNDS_BY_DEFAULT;
        $e["default_background_priority"]=CW_SERVICE_PLANNING_SERVICE_BACKGROUND_PRIORITY_DEFAULT;        
        return $this->d->insert_and_get_id($e,"church_service_templates");                
      }
      return false;
    }
    
    function delete_church_service_template($id){
      return $this->d->delete($id,"church_service_templates","id");
    }
    
    function update_church_service_template($id,$e){
      if (is_array($e)){
        return $this->d->update_record("church_service_templates","id",$id,$e);                
      }      
    }

    function church_service_template_exists($service_name){
      return $this->get_church_service_template_id($service_name);
    }
    
    function get_church_service_template_record($id){
      return ($this->d->get_record("church_service_templates","id",$id));        
    }

    function get_church_service_template_id($service_name){
      $r=$this->d->get_record("church_service_templates","LOWER(service_name)",strtolower($service_name));
      if (is_array($r)){
        return $r["id"];      
      }        
    }
    
    function get_all_church_service_templates(){
      return $this->d->get_table("church_service_templates");
    }

    function template_instance_is_scheduled($template_id,$timestamp){
      $beginning=getBeginningOfDay($timestamp);
      $end=getEndOfDay($timestamp);
      $query="
        SELECT
          events.*
        FROM
          events,church_services
        WHERE
          events.church_service=church_services.id
        AND
          church_services.template_id=$template_id
        AND
          events.timestamp>$beginning
        AND
          events.timestamp<$end;
      ";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }
      return false;
    }
        

      /*    
         //Every week on $daf[0]
        $hm=explode(':',$template["default_start_time"]);
        //hm[0] has hours, hm[1] has minutes
        $add=$hm[0]*HOUR+$hm[1]*MINUTE;
        for ($i=0;$i<$n;$i++){
          $timestamp=strtotime("next ".int_to_dow($daf[0]))+WEEK*$i+$add;
          if (!$this->template_instance_is_scheduled($template_id,$timestamp)){
            $t[]=$timestamp;
          } else {
            $i--; //Decrease control variable because we want 10 valid results
          }
        }                

      */


    //Take the iteration_rule string from church service template $template_id and return timestamps
    //for $n suggested service instances (that might well be more timestamps for services with multiple times)
    //Also only suggest those days/times where that service is not yet scheduled      
    //Return an array with service_instance_no (running nr 1,2,...$n) and csl of timestamps belonging to that instance
    function get_suggested_timestamps_for_service_template($template_id,$n){
      $template=$this->get_church_service_template_record($template_id);
      if (is_array($template)){
        if (!empty($template["iteration_rules"])){
          $t=array();          
          //Separate rule into parts by dot (.)
          $rule=explode('.',$template["iteration_rules"]);
          //Rule now should have 3 elements. Either day_of_week.frequency[m].start_times_csl, OR date.mm-dd.start_times_csl
          //Third element is start-time csl in any case
          $start_times=explode(',',$rule[2]);
          if ($rule[0]=="date"){
            //expect specific date mm-dd in $rule[1], separate by dash (-)
            $date=explode('-',$rule[1]);
            $month=$date[0];
            $day=$date[1];
            foreach ($start_times as $st){
              $st=explode(':',$st);
              $hour=$st[0];
              $minute=$st[1];
              $timestamp=mktime($hour,$minute,0,$month,$day);
              while ($this->template_instance_is_scheduled($template_id,$timestamp)){
                $timestamp=strtotime("next year",$timestamp);
              } 
              $t[0].=",".$timestamp;
            } 
            if (substr($t[0],0,1)==","){
              $t[0]=substr($t[0],1); //cut off first comma
            }                     
          } else {
            //$rule[0] is expected to indicate day of week (0-6)
            //Run as many times as service instances are required ($n)
            $m=0; //This counter is increased when an instance fails because its booked already (to ensure we get $n results)
            for ($i=1;$i<=$n+$m;$i++){
              foreach ($start_times as $st){
                $st=explode(':',$st);
                $hour=$st[0];
                $minute=$st[1];
                $add=$hour*HOUR+$minute*MINUTE;
                if ($st[2]){
                  //If a time was given like 19:00:1 then it is 7pm the following day (add Day). 18:00:-1 is 6pm the previous day.
                  $add+=$st[2]*DAY;
                  //This is where DST change is not covered, if a service is on a saturday and a sunday and dst in between
                }                
                if (substr($rule[1],-1)=="m"){
                  $timestamp=getBeginningOfMonth(time());
                  for ($q=1;$q<=$i;$q++){
                    $timestamp=getBeginningOfMonth(strtotime("next month",$timestamp));
                  }
                  //Now we are at the beginning of the desired month - but go one second back so that "next monday" will not actually be the second monday
                  $actual_beginning_of_month=$timestamp;
                  $timestamp-=1;
                  for ($q=1;$q<=min(substr($rule[1],0,-1),5);$q++){
                    $timestamp=getBeginningOfDay(strtotime("next ".int_to_dow($rule[0]),$timestamp));
                  }
                  if (!isSameMonth($actual_beginning_of_month,$timestamp)){
                    //If we left the month already then it did not have $rule[1] Sundays (or mondays or whatever)                                            
                  } else {
                    //Should be the correct day now
                    $timestamp+=$add;
                    if (!$this->template_instance_is_scheduled($template_id,$timestamp)){
                      $t[$i].=",".$timestamp;
                    } else {
                      $m++;
                    }
                  }
                } else {
                  //every $rule[1] week
                  $timestamp=strtotime("next ".int_to_dow($rule[0]))+$rule[1]*WEEK*$i+$add-WEEK;
                  $timestamp=adjust_timestamp_for_dst($timestamp); //utilities_dates                
                  if (!$this->template_instance_is_scheduled($template_id,$timestamp)){
                    $t[$i].=",".$timestamp;
                  } else {
                    $m++;
                  }                  
                }
                if (substr($t[$i],0,1)==","){
                  $t[$i]=substr($t[$i],1); //cut off first comma
                }
              }            
            }
          }   

          return $t;    
        }
      }  
      return false;  
    }
    
    /* conversations_to_church_services__planning */
    
    //$conversations must be object of type cw_Conversations
    function assign_planning_conversation_to_service_plan($service_id,$conversations){
      $existing_conv_id=$this->get_planning_conversation_id_for_service_plan($service_id);
      if (!($existing_conv_id>0)){
        if ($conv_id=$conversations->add_conversation(-1,"Planning conversation for service-plan #$service_id")){
          $e=array();
          $e["conversation_id"]=$conv_id;
          $e["service_id"]=$service_id;
          if ($this->d->insert($e,"conversations_to_church_services__planning")){
            return $conv_id; //return the ID of the conversation that has been assigned to the service plan
          }        
        }
      }  
      return false;
    }
    
    function get_planning_conversation_id_for_service_plan($service_id){
      $query="SELECT conversation_id FROM conversations_to_church_services__planning WHERE service_id=$service_id;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["conversation_id"];
        }
      }
      return false;
    }
    
    function unassign_planning_conversation_from_service_plan($service_id){
      $query="DELETE FROM conversations_to_church_services__planning WHERE service_id=$service_id;";
      if ($res=$this->d->q($query)){
        return true;
      }
      return false;      
    }
    
    
    /* files_to_service_elements */
    
    //$file_id is a reference to cw_Files
    private function assign_file_to_service_element($id,$file_id){
    	if (($id>0) && ($file_id>0)){
    		$e=array();
    		$e["service_element_id"]=$id;
    		$e["file_id"]=$file_id;
    		return $this->d->insert_and_get_id($e, "files_to_service_elements");
    	}
 		return false;    		
    }
    
    private function unassign_file_from_service_element($id,$file_id){
    	if (($id>0) && ($file_id>0)){
    		$query="DELETE FROM files_to_service_elements WHERE service_element_id=$id AND file_id=$file_id;";
    		return ($res=$this->d->q($query));    			
    	}
    	return false;
    }
    
    function unassign_all_files_from_service_element($id){
    	if ($id>0){
    		return ($res=$this->d->q("DELETE FROM files_to_service_elements WHERE service_element_id=$id"));
    	}
    }
    
    //Return array of cw_files records
    function get_files_assigned_to_service_element($id){
    	$files=array();
    	$query="
    		SELECT files.* FROM files_to_service_elements LEFT JOIN files on files.id=files_to_service_elements.file_id
    		WHERE files_to_service_elements.service_element_id=$id;
    	";
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			$files[]=$r;
    		}
    	}
    	return $files;
    }
    
    function get_service_element_for_file($file_id){
    	$query="SELECT service_element_id FROM files_to_service_elements WHERE file_id=$file_id;";
    	if ($res=$this->d->q($query)){
    		if ($r=$res->fetch_assoc()){
    			return ($r["service_element_id"]);
    		}
    	}
    	return false;
    }
    
    /* Take temporary file (=uploaded file, with $tmp_file coming from $_FILES["file"]["tmp_name"]
     * and process with cw_Files, as well as add to files_to_service_elements table.
	 */
    function add_file_to_service_element($tmp_file,$file_name,$element_id,$added_by){
    	$pathinfo=pathinfo($file_name);
    	$files=new cw_Files($this->d);
    	$file_id=$files->add_uploaded_file($tmp_file, $pathinfo["filename"], $pathinfo["extension"],$added_by);
    	if ($file_id>0){
    		//Successfully added physical file to cw_Files
    		//Add to files_to_service_elements table:
    		return $this->assign_file_to_service_element($element_id, $file_id);
    	} else {
    		//Error with the uploaded file
    	}
    	return false;
    }
    
    /* Remove file physically and from service element */
    function remove_file_from_service_element($file_id,$service_element_id){
    	//Safety: check if the service element and the file belong together
    	if (($file_id>0) && ($service_element_id>0) && ($this->get_service_element_for_file($_GET["file_id"])==$service_element_id)){
    		//OK.
    		$files=new cw_Files($this->d);
    		return (($files->remove_file($file_id)) && ($this->unassign_file_from_service_element($service_element_id, $file_id)));    		
    	}
    }
    
    
    
}

?>
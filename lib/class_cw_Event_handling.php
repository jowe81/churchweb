<?php

class cw_Event_handling {
  
    public $auth; //$auth (passed in)
    public $d; //Database access
    public $events,$event_positions,$church_services,$room_bookings,$rooms,$auto_confirm,$scripture_handling,$sh,$mdb;
    
    function __construct($a){
      $this->auth = $a;
      $this->d = $a->d;
      $this->events = new cw_Events($this->d);
      $this->event_positions = new cw_Event_positions($this->d);
      $this->church_services = new cw_Church_services($this->d);
      $this->room_bookings = new cw_Room_bookings($this->d);
      $this->rooms = new cw_Rooms($this->d);
      $this->auto_confirm = new cw_Auto_confirm($this->d);
      $this->scripture_handling = new cw_Scripture_handling($this->d);
      $this->sh=$this->scripture_handling; //Alias
      $this->mdb=new cw_Music_db($a);
    }
        
    //Init tables for all instantiated classes
    function recreate_tables($default_records=true){
      return (($this->events->recreate_tables($default_records)) && ($this->event_positions->recreate_tables($default_records)) && ($this->church_services->recreate_tables($default_records)));
    }
 
    //Delete entire church service with all connected events, rehearsals, room-bookings, positions, service elements...
    function delete_church_service($service_id){
      $cs=new cw_Church_service($this,$service_id);
      //Clear out the service first: delete service elements (also clears associated records)
      foreach ($cs->elements as $v){
        $this->delete_service_element($v["id"]);
      }
      //Dividers need to go also
      $this->church_services->delete_dividers($service_id);
      //Now clear rehearsals (also clears rehearsal room bookings, notifies people as needed)
      foreach ($cs->rehearsals as $v){
        $this->delete_rehearsal($v["id"]);        
      }
      //Now delete positions (notifies people as needed)
      foreach ($cs->positions as $v){
        $this->delete_position($v["id"]);        
      }
      //Now delete service instances/events (deletes event records and associated room bookings also)
      foreach ($cs->event_records as $v){
        $this->delete_show($v["id"]);
      }
      //Delete planning conversation for this service
      $conversation_id=$this->church_services->get_planning_conversation_id_for_service_plan($service_id);
      $this->church_services->unassign_planning_conversation_from_service_plan($service_id);
      $cv=new cw_Conversations($this->auth);
      $cv->drop_conversation($conversation_id);
      //Finally, delete church_services record
      return $this->church_services->delete_church_service_record($service_id);      
    }
  

    //$bookings_string is from service_template["default_room_bookings"] and indicates which rooms to book with what offsets for the event
    function perform_room_bookings_for_event($event_id,$event_timestamp,$event_duration,$bookings_string){
      //Note: $event_timestamp and $event_duration could be read by obtaining the event_record, but passing it in is faster
      $booking_results=$this->room_bookings->add_bookings($event_id,$event_timestamp,$event_duration,$bookings_string,$this->auth->cuid,"auto-booked by service planning");
      //Check for each booking if it succeeded
      if (is_array($booking_results)){
        foreach ($booking_results as $w){
          if (!$w){
            //Booking failed,
            $failed_bookings++;
          }
        }
        //
        if ($failed_bookings>0){
          $s="$failed_bookings room booking(s) failed - please check the booking system manually";
        } else {
          $s="performed ".sizeof($booking_results)." room booking(s)";
        }
      } else {
        //Something was wrong with the string in $template["default_room_bookings"]
        $s="Error: template record seems invalid";
      }
      return $s;    
    }

    //Delete an instance of a church service, and cancel associated room bookings
    function delete_show($service_event_id){
      return (($this->events->delete_event($service_event_id)) && ($this->room_bookings->delete_bookings_for_event($service_event_id)));      
    }

    //$event_id must point to a service instance (i.e. event record has valid pointer to church_service), $timestamp is the new time
    function move_show($event_id,$timestamp){
      /*
        Pseudo:
          -get data (need to get the template with the default_room_bookings via event record, church services record)
          -delete current bookings
          -attempt to book for the new time
          -adjust timestamp in event record (even if booking failed)
      */    
      $erec=$this->events->get_event_record($event_id);
      $service_record=$this->church_services->get_service_record($erec["church_service"]);
      $template=$this->church_services->get_church_service_template_record($service_record["template_id"]);
      if ((is_array($erec)) && (is_array($service_record)) && (is_array($template))){
        $this->room_bookings->delete_bookings_for_event($event_id);
        $s=$this->perform_room_bookings_for_event($event_id,$timestamp,$erec["duration"],$template["default_room_bookings"]);
        $erec["timestamp"]=$timestamp;
        if ($this->events->update_event($erec["id"],$erec)){
          $s="reschuled show, $s";
        } else {
          $s="error: could not reschedule show, $s";
        }        
      } else {
        $s="Error: could not retrieve all the data needed to move the show";
      }      
      return $s;
    }

    //Add a show to a service. $default_room_bookings is from service_template record
    function add_show($service_id,$event_timestamp,$event_duration=0,$event_title="",$default_room_bookings=""){
      if (isFuture($event_timestamp)){
        //If template info was not provided, get service record and template
        if (($event_duration==0) || ($event_title=="") || ($default_room_bookings=="")){
          $service_record=$this->church_services->get_service_record($service_id);
          $template=$this->church_services->get_church_service_template_record($service_record["template_id"]);
          if ((is_array($service_record)) && (is_array($template))){
            //Got template info, fill in missing info but don't overwrite
            empty($event_duration) ? $event_duration=$template["default_duration"] : null;
            empty($event_title) ? $event_title=$template["service_name"] : null;
            empty($default_room_bookings) ? $default_room_bookings=$template["default_room_bookings"] : null;          
          } else {
            return "Error: could not retrieve service record and template info";
          }
        }             
        $failed_bookings=0;
        //Add an event record
        $e=array();
        $e["created_by"]=$this->auth->cuid;
        $e["timestamp"]=$event_timestamp;
        $e["duration"]=$event_duration;
        $e["is_church_event"]=true;
        $e["is_rehearsal"]=false;
        $e["church_service"]=$service_id;
        $e["title"]=$event_title; //Save the service name also as event title
        $new_event_id=$this->events->add_event($e);
        if ($new_event_id>0){
          //Process room bookings
          $s="added show, ".$this->perform_room_bookings_for_event($new_event_id,$event_timestamp,$event_duration,$default_room_bookings);
        } else {
          //Adding event record failed
          $s="Error: could not generate event record";
        }
      } else {
        //Timestamp was in the past
        $s="Error: provided date is invalid or in the past";
      }
      return $s;
    }
    
    //Schedules a service from $service_template_id at the times indicated by the $timestamps array
    function schedule_services($service_template_id,$timestamps=array(),$title="",$suppress_needed_positions=false){
      if((is_array($timestamps)) && (sizeof($timestamps)>0)){
        $template=$this->church_services->get_church_service_template_record($service_template_id);
        if (is_array($template)){
          //For the church_services record we need template_id (for the service name) and, optionally, title
          $service_id=$this->church_services->add_church_service_record($service_template_id,$title,$this->auth->cuid,$suppress_needed_positions);
          if ($service_id>0){
            //Create default elements
            $this->create_default_elements_for_service($service_id,$template["default_elements"]);
            //Now process each iteration of the service (each timestamp)
            foreach ($timestamps as $v){
              $s.="\n".date("G:i",$v)." ".$this->add_show($service_id,$v,$template["default_duration"],$template["service_name"],$template["default_room_bookings"]);
            }  
            //Create planning conversation
            if ($cv=new cw_Conversations($this->auth)){
              $this->church_services->assign_planning_conversation_to_service_plan($service_id,$cv); 
            }
          } else {
            //Adding church service record failed
            $s="Error: could not generate service record";
          }      
        } else {
          //Retrieving template record failed
          $s="Error: could not retrieve service template";
        }
      } else {
        $s="Error: invalid parameters for schedule_services() in cw_Event_handling";
      }
      return $s;    
    }
        
    //Return array of service ids. if $person_id is given, then return only those services that have at least one matching positions_to_services_record
    function get_upcoming_services($past=false,$person_id=0){
      if ($past){
        $operator="<";
        $orderby="events.timestamp DESC";
      } else {
        $operator=">";      
        $orderby="events.timestamp";
      }
      //Looking only for those services that involve $person_id?
      if ($person_id!=0){
        $cond="WHERE positions_to_services.person_id=$person_id AND events.timestamp+events.duration+".HOUR.$operator.time()." ";
      } else {
        $cond="WHERE events.timestamp+events.duration+".HOUR.$operator.time()." ";
      }
      $query="SELECT DISTINCT
                church_services.id 
              FROM 
                church_services
                LEFT JOIN events ON (church_services.id=events.church_service AND events.is_rehearsal!=1)
                LEFT JOIN positions_to_services ON church_services.id=positions_to_services.service_id
              $cond
              ORDER BY
                $orderby;  
                ";
      $t=array();
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r["id"];          
        }
      }
      return $t;    
    }
    
    //Return array of service ids. $timestamp indicates the starting week 
    function get_services($timestamp=0){
      if ($timestamp==0){
        //If no timestamp is given, show the current week, and 5 weeks down
        $timestamp=time();
        $beginning=getBeginningOfWeek($timestamp);
        $end=getEndOfWeek($beginning+5*WEEK);      
      } else {
        //If timestamp is given, show the week that's indicated and  5weeks down
        $beginning=getBeginningOfWeek($timestamp);
        $end=getEndOfWeek($timestamp+5*WEEK);      
      }
            
      $query="SELECT DISTINCT
                church_services.id,church_services.template_id 
              FROM 
                church_services
                LEFT JOIN events ON (church_services.id=events.church_service AND events.is_rehearsal!=1)
              WHERE
                events.timestamp>$beginning AND events.timestamp<$end
              ORDER BY
                events.timestamp;  
                ";
      $t=array();
      if ($res=$this->d->query($query)){
        $upref=new cw_User_preferences($this->d,$this->auth->cuid);
        while ($r=$res->fetch_assoc()){
          if ($upref->read_pref($this->auth->csid,"SERVICES_FILTER_HIDE_".$r["template_id"])==0){
            //Only add this service id if there's no setting that hides services with that template_id
            $t[]=$r["id"];                    
          }
        }
      }
      return $t;    
    }
    
    
    function get_event_records_for_service($church_service_id,$return_first_match_only=false){
      $q="SELECT * FROM events WHERE church_service=$church_service_id AND is_rehearsal<1 ORDER BY timestamp";
      if ($res=$this->d->q($q)){
        if ($return_first_match_only){
          //Return the first match only
          if ($r=$res->fetch_assoc()){
            return $r;
          }
        } else {
          //Return all matches 
          $t=array();
          while($r=$res->fetch_assoc()){
            $t[]=$r;
          }
          return $t;
        }
      }
      return false;
    }
    
    //Return a string of <option>-tags with the names of all people who can fill the desired position
    function get_all_persons_for_position_for_select($position_id,$exclude_dormant_positions=true){
      $t="";
      if ($q=$this->event_positions->get_all_persons_for_position($position_id,$exclude_dormant_positions)){
        foreach ($q as $v){
          $t.="<option value='".$v["person_id"]."'>".$this->auth->personal_records->get_name_first_last($v["person_id"])."</option>";
        }        
      }
      return $t;    
    }
    
   
    //Delete a position and, if necessariy, notify the person
    function delete_position($pos_to_services_id,$deleted_by=0){
      //Prepare to notify the person if one was scheduled, had received a notification, and had not disconfirmed participation
      if ($r=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
        //Remove position
        if ($this->event_positions->remove_position_from_service($pos_to_services_id)){
          //A person was scheduled
          if ($r["person_id"]!=0){
            //The person had not disconfirmed
            if ($r["confirmed"]>=0){
              //The person had been informed about the scheduling event
              if ($r["last_notification"]!=-1){
                if ($deleted_by!=0){
                  $name=$this->auth->personal_records->get_name_first_last($deleted_by);                
                } else {
                  $name="a schedule administrator";
                }
                //Get service info
                $cs=new cw_Church_service($this,$r["service_id"]);
                //Send message
                $this->auth->send_system_email(
                  $r["person_id"],
                  CW_SERVICE_PLANNING_EMAIL_SUBJECT.": POSITION CANCELLED",
                  "This email is to notify you that $name has cancelled your position of ".$this->event_positions->get_position_title($r["pos_id"]).
                  " from the ".$cs->get_info_string().".".CW_SERVICE_PLANNING_EMAIL_FOOTER
                ); 
              }            
            }
            //The rest of the service elements need to be checked and if this person is in charge of any of them, that link must be dropped
            $this->remove_person_from_service_elements($r["service_id"],$r["person_id"]);
          }
          return true;      
        }
      }
      return false;    
    }
    
    //Loop over all elements in the service, and where $person_id appears to be in charge, remove reference
    function remove_person_from_service_elements($service_id,$person_id){
      $cs=new cw_Church_service($this,$service_id);
      foreach ($cs->elements as $k=>$v){
        if ($v["person_id"]==$person_id){
          $cs->elements[$k]["person_id"]=0;
          $this->church_services->update_service_element($v["id"],$cs->elements[$k]);
        }        
      }    
    }
    
    /* Rehearsals */
    
    //For rehearsal booking
    function get_active_rooms_for_select($orderby="name",$selected_room_id=0){
      if ($r=$this->rooms->get_all_rooms($orderby)){
        $t="";
        foreach ($r as $v){
          if ($v["active"]){
            $selected="";
            if ($selected_room_id==$v["id"]){
              $selected="selected='SELECTED'";
            }
            $t.="<option $selected value='".$v["id"]."'>".$this->rooms->get_name_and_number($v["id"])."</option>";
          }
        }
        $selected="";
        if ($selected_room_id<0){
          //Guest room (other site)
          $selected="selected='SELECTED'";
        }
        $t.="<option $selected value='offsite' style='color:#F66;'>-- other / off-site --</option>";
        return $t;
      }
      return false;
    }
    
    //Schedule a rehearsal for the service $service_id
    function schedule_rehearsal($service_id,$timestamp,$duration=CW_DEFAULT_PRACTICE_DURATION){
      //Get event_id for service
      $event_id=$this->church_services->get_event_id_for_service($service_id);
      //Add the rehearsal event
      if ($id=$this->events->add_event(array("is_rehearsal"=>true,"church_service"=>$service_id,"timestamp"=>$timestamp,"duration"=>$duration,"created_by"=>$this->auth->cuid))){   
        return $id;
      }
      return false;
    }
    
    //Change time and duration of rehearsal & send applicable notifications
    function update_rehearsal($rehearsal_id,$timestamp,$duration){
      $rehearsal_record=$this->get_rehearsals_for_service(0,$rehearsal_id,true);
      return ($this->events->update_event($rehearsal_id,array("timestamp"=>$timestamp,"duration"=>$duration,"updated_by"=>$this->auth->cuid)));
    }
    
    //Called after successful rescheduling of rehearsal
    function send_applicable_rehearsal_update_notificatons($old_rehearsal_record,$new_rehearsal_record){
      //Notifications are due only if really the date, duration or location of the rehearsal changed
      $a=$old_rehearsal_record["bookings"][0]; //use $a and $b for convenience
      $b=$new_rehearsal_record["bookings"][0];      
      $change=false; //init
      if (!(($a["timestamp"]==$b["timestamp"]) && ($a["duration"]==$b["duration"]) && ($a["room_id"]==$b["room_id"]))){
        $change=true;
      }
      if (($change)){
        //date, duration, or location changed - notify participants
        foreach ($new_rehearsal_record["participants"] as $v){
          //Send notification if person has been notified about the position and not rejected it, regardless of their reaction to the rehearsal appointment itself
          if ($r=$this->event_positions->get_positions_to_services_record_by_id($v["pos_id"])){
            if (($r["last_notification"]!=-1) && ($r["confirmed"]>=0)){
              //Notification is due
              $this->auth->send_system_email(
                $v["person_id"],
                CW_SERVICE_PLANNING_EMAIL_SUBJECT.": REHEARSAL RESCHEDULED",
                "This email is to notify you that the rehearsal on ".date("l F j Y, g:ia",$old_rehearsal_record["timestamp"])." has been moved to another time or place or that its duration has changed. Please review the current rehearsal information below."
                ."\n\nDate/time: ".date("l F j Y, g:ia",$new_rehearsal_record["timestamp"])." - ".date("g:ia",$new_rehearsal_record["timestamp"]+$new_rehearsal_record["duration"])
                ."\nLocation: ".$this->rooms->get_roomname($new_rehearsal_record["bookings"][0]["room_id"])              
                .CW_SERVICE_PLANNING_EMAIL_FOOTER
              );            
            }
          } 
        }          
      }
    }
    
    function delete_rehearsal($rehearsal_event_id){
      //Get service event id
      $service_id=$this->church_services->get_service_id_for_event($rehearsal_event_id);
      //Make service object to get at rehearsals and participants
      $cs = new cw_Church_service($this,$service_id);
      $rehearsal_event=$cs->rehearsals[$rehearsal_event_id];
      //Grab the participants to notify them (people records)
      $r=$this->event_positions->get_rehearsal_participants_for_rehearsal_deletion($rehearsal_event_id);
      //Delete
      if (($this->events->delete_event($rehearsal_event_id)) && ($this->event_positions->remove_all_rehearsal_participants($rehearsal_event_id))){
        //Delete associated room booking(s)
        $this->room_bookings->delete_bookings_for_event($rehearsal_event_id);
        //Notify participants that need to be notified
        foreach ($r as $v){
          $this->auth->send_system_email(
            $v["id"],
            CW_SERVICE_PLANNING_EMAIL_SUBJECT.": REHEARSAL CANCELLED",
            "This email is to notify you that the rehearsal on ".date("l F j Y, g:ia",$rehearsal_event["timestamp"])." has been CANCELLED.".CW_SERVICE_PLANNING_EMAIL_FOOTER
          );
        }       
        return true;
      }                      
      return false;
      
    }
    
    function delete_rehearsal_participant($pos_to_services_id,$rehearsal_id){
      //Get record in question
      if ($r=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($pos_to_services_id,$rehearsal_id)){
        //Notify if applicable
        if (($r["last_notification"]!=-1) && ($r["confirmed"]>=0)){
          //Get person_id from pos_to_services_record
          if ($posrec=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
            //Person was notified of the scheduled practice AND has not DISconfirmed -> so notify of cancellation
            //Need rehearsal record
            if ($rehearsal_event=$this->events->get_event_record($rehearsal_id)){
              //Need position title
              if ($pos_title=$this->event_positions->get_position_title($posrec["pos_id"])){
                $this->auth->send_system_email(
                  $posrec["person_id"],
                  CW_SERVICE_PLANNING_EMAIL_SUBJECT.": REHEARSAL CANCELLED",
                  "This email is to notify you that, in your position as $pos_title, you have been taken off the rehearsal on ".date("l F j Y, g:ia",$rehearsal_event["timestamp"]).".".CW_SERVICE_PLANNING_EMAIL_FOOTER
                );                    
              }
            }
          }
        }
        //Remove rehearsal_participant_record
        return $this->event_positions->remove_rehearsal_participant($pos_to_services_id,$rehearsal_id);                                                                                           
      }
      return false;
    }
    
    //If rehearsal_event_id is given, return only the data for that rehearsal
    function get_rehearsals_for_service($service_id,$rehearsal_event_id=0,$include_pos_rec_id_with_participants=false){
      if ($rehearsal_event_id==0){
        //Get all rehearsals for the service
        $service_event_id=$this->church_services->get_event_id_for_service($service_id);
        $r=$this->events->get_events(array("is_rehearsal"=>true,"church_service"=>$service_id)); //event records      
      } else {
        $r=array();
        $r[]=$this->events->get_event_record($rehearsal_event_id);
      }
      $t=array();
      //Add room booking information to each record
      foreach ($r as $v){
        $v["bookings"]=array();
        if ($bookings=$this->room_bookings->get_bookings_for_event($v["id"])){
          //One or several booking records for this rehearsal event are now in $bookings
          $v["bookings"]=$bookings;
        }
        $v["participants"]=array();
        if ($participants=$this->event_positions->get_rehearsal_participants($v["id"],$include_pos_rec_id_with_participants)){
          //All participants of the rehearsal are now in $participants, with person_id and position_type id
          $v["participants"]=$participants;
        }
        $t[$v["id"]]=$v;
      } 
      /*
        Now $t should have all the records from $r, but each record should have added
         - a value 'bookings', containing a bookings array
         - a value 'participants', containing an array of rehearsal_participant records
      */
      if ($rehearsal_event_id!=0){
        //If a rehearsal was specified, just return record, not an array of records
        return array_shift($t);
      }
      return $t;
    }

    function get_rehearsal_participant_person_id($rehearsal_participants_id){
      $query="
        SELECT
          positions_to_services.person_id 
        FROM
          rehearsal_participants,positions_to_services
        WHERE
          rehearsal_participants.id=$rehearsal_participants_id
        AND
          positions_to_services.id=rehearsal_participants.positions_to_services_id;
      ";
      if ($res=$this->d->query($query)){
        if ($r=$res->fetch_assoc()){
          return $r["person_id"];
        }
      }
      return false;
    }
          
    function get_service_id_from_rehearsal_event_id($rehearsal_event_id){
      /*Get rehearsal event record and return field church_service */
      if ($r=$this->events->get_event_record($rehearsal_event_id)){
        return $r["church_service"];
      }
      return false;
    }
    
    //Return a comma-separated string of names, or just the name of the position type if ALL are invited
    function who_of_position_type_is_invited_to_rehearsal($rehearsal_event_id){
      /*
        Get all rehearsal participants for this rehearsal
        Get all positions_to_services_records for the associated service
        For each position_type, check for which positions_to_services_record exists a rehearsal_participant_record
        If all are covered, return position_type name, else list of names 
      */
      if ($service_id=$this->get_service_id_from_rehearsal_event_id($rehearsal_event_id)){
        if ($participants=$this->event_positions->get_rehearsal_participants($rehearsal_event_id)){
          $t="";
          if ($r=$this->event_positions->get_position_types()){
            foreach ($r as $v){
              //$v is pos_type_record
              if ($r2=$this->event_positions->get_people_in_service($service_id,$v["id"])){
                $not_in_practice=0;
                $name_str='';
                foreach ($r2 as $v2){
                  //$v2 is pos_to_services_record
                  //Question here: does a pos_to_services record exist (in $participants) that has person_id=$v2["person_id"] and pos_type_id=$v["id"]
                  if (in_array(array("person_id"=>$v2["person_id"],"position_type_id"=>$v["id"]),$participants)){                  
                    //This person/position is part of the practice
                    $name_str.=", ".$this->auth->personal_records->get_name_first_last_initial($v2["person_id"]);                    
                  } else {
                    //Count those who should be in but aren't
                    $not_in_practice++;
                  }
                }
                if ($not_in_practice>0){
                  //If some of this pos_type are not in the practice, list those that are
                  $t.=$name_str;                  
                } else {
                  //If all of this pos_Type are in the practice, list the pos_type name
                  $t.=", ".$this->event_positions->get_position_type_title($v["id"]);
                }                                                             
              }
            }
          }
          if (substr($t,0,2)==", "){
            $t=substr($t,2); //Cut off first comma
          }
          return $t;
        }
      }
      return false;
    }
    
    //To find out wheather checkbox must be preselected in rehearsal edit dialogue
    function position_has_rehearsal_participant_record($pos_to_services_id,$rehearsal_id){
      if ($res=$this->d->query("SELECT * FROM rehearsal_participants WHERE positions_to_services_id=$pos_to_services_id AND rehearsal=$rehearsal_id;")){
        return ($res->num_rows>0);      
      };
      return false;
    }
    
    //Return an array with email notifications about scheduling and practices. Key = person_id
    function email_notifications($service_id,$send=false){
    /*
      Has everybody received a note about practices they are involved in?
        -get all people that are scheduled
        -foreach (person_id)
          -get all pos_to_services_records for person
          -foreach (pos_to_services rec)
            -if no last_notification, add the scheduling info to note
            -get all rehearsal_participants records FOR THIS POS_TO_SERVICES_RECORD
            -foreach (reh_part_rec)
              -if no last_notification, add the rehearsal info to note
          
    */
      $t=array();
      //Get full service info
      $cs=new cw_Church_service($this,$service_id);
      $people=$this->event_positions->get_people_in_service($service_id);
      foreach ($people as $v){
        $mark_sent=false;
        $has_unsent_notifications=false; //If this gets set to true anywhere in the loop below, an email will be sent
        //Do we have an email address?
        if (($send) && ($recipient=$this->auth->get_cw_comm_email_for_user($v["person_id"]))){
          $mark_sent=true; //Yes. So mark all pending notifications for the user as sent.        
        }        
        $note_for_this_person="Please consider the following scheduling information regarding the ".$cs->get_info_string().".";
        if ($positions=$this->event_positions->get_positions_to_services_records_for_person_and_service($service_id,$v["person_id"])){
          //$positions has one or more records of positions the person has been scheduled for in this service
          $all_positions="";
          foreach ($positions as $v2){
            //$v2 is a pos_to_services record
            if ($v2["last_notification"]==-1){
              //Scheduling note needs to be added
              $all_positions.=", ".$this->event_positions->get_position_title($v2["pos_id"]);
              if (($send) && ($recipient!="")){
                //Mark positions_to_services record last notification
                $this->event_positions->mark_position_notification_as_sent($v2["id"]);
              }
              $has_unsent_notifications=true;
            }
            if ($rehearsal_participant_records=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($v2["id"])){
              //$rehearsal_participant_records has records for no, one, or several practices
              $all_rehearsals="";
              foreach ($rehearsal_participant_records as $v3){
                //$v3 is rehearsal_participants record
                if ($v3["last_notification"]==-1){
                  //Rehearsal scheduling note needs to be added
                  $all_rehearsals.="\n-".date("l F j Y, g:ia",$cs->rehearsals[$v3["rehearsal"]]["timestamp"])." - ".date("g:ia",$cs->rehearsals[$v3["rehearsal"]]["timestamp"]+$cs->rehearsals[$v3["rehearsal"]]["duration"]).", ".$this->rooms->get_roomname($cs->rehearsals[$v3["rehearsal"]]["bookings"][0]["room_id"]);                    
                  if (($send) && ($recipient!="")){
                    $this->event_positions->mark_rehearsal_notification_as_sent($v3["id"]);
                  }
                  $has_unsent_notifications=true;                
                }
              }
            } else {
            
            }
          }
          if ($all_positions!=""){
            $all_positions=substr($all_positions,2);
          }
          if (substr_count($all_positions,",")>0){
            $all_positions=substr_replace($all_positions," and",strrpos($all_positions,","),1);
          }
          $acl="";
          //There are positions
          if (strlen($all_positions)>0){          
            $note_for_this_person.="\n\nYou have been scheduled as $all_positions. ";
            $auto_confirm_link=$this->auto_confirm->get_auto_confirm_link_all($cs->id,$v["person_id"]);
            $acl="\n\nTo accept, visit (click) this URL:\n".$auto_confirm_link.cw_Auto_confirm::get_value_string();
            $acl.="\nTo decline, visit (click) this URL:\n".$auto_confirm_link.cw_Auto_confirm::get_value_string(false);          
          }
          $rehearsal_notice="";
          if (strlen($all_rehearsals)>0){
            //There is rehearsal info
            $note_for_this_person.="\n\nRehearsal info: ".$all_rehearsals;
            if (strlen($all_positions)==0){
              //There is rehearsal info, but no new positions - i.e., person has accepted their positions and should be ok with the rehearsal info -- no confirm link
              $rehearsal_notice="\n\nWe're hoping that you can honor the rehearsal(s) as part of your commitment to serve in the ".$cs->service_name
                                    .". If that is not the case, please let us know which rehearsals you can or cannot make. Thank you so much!";
            }
          } else {
            //There are no rehearsals, just positions
            $note_for_this_person.="\n\nAt this time we don't have rehearsal information. We will send you another email if rehearsals get scheduled.";
          }
          $note_for_this_person.=$rehearsal_notice.$acl;
          $note_for_this_person.=CW_SERVICE_PLANNING_EMAIL_FOOTER;
        }
        $t[$v["person_id"]]["message"]=$note_for_this_person;
        //Send note
        if ($has_unsent_notifications){
          if (($send) && ($recipient!="")){
            if ($this->auth->send_system_email($v["person_id"],CW_SERVICE_PLANNING_EMAIL_SUBJECT.": ".$cs->get_info_string(),$note_for_this_person)){
              //Success.
              $t[$v["person_id"]]["status"]="sent";
            } else {
              //Couldn't send email though we have address
              $t[$v["person_id"]]["status"]="system_email_fail";
            }
          } else {
            if ($recipient==""){
              //No address
              $t[$v["person_id"]]["status"]="no_address";            
            }
          }
        } else {
          //Has been sent before
          $t[$v["person_id"]]["status"]="sent before";        
        }
      }
      return $t;      
    
    }
    
    //Are any email notifications for this service and associated rehearsals pending?
    function email_notifications_pending($service_id){
      $result=false;
      
      $q1="
        SELECT
          positions_to_services.id
        FROM
          positions_to_services 
        WHERE
          positions_to_services.service_id=$service_id
        AND
          positions_to_services.last_notification=-1;
        ";
      if ($res=$this->d->query($q1)){
        if ($res->num_rows>0){
          //Found pos_to_services record with no notification - break
          return true;          
        }
      }

      $q2="        
        SELECT
          rehearsal_participants.id
        FROM
          positions_to_services,rehearsal_participants 
        WHERE
          positions_to_services.service_id=$service_id
        AND
          rehearsal_participants.positions_to_services_id=positions_to_services.id
        AND
          rehearsal_participants.last_notification=-1;
      ";
      if ($res=$this->d->query($q2)){
        if ($res->num_rows>0){
          //Found rehearsal_participants record with no notification - break
          return true;          
        }
      }
      
      return false;    
    }
    
    /* (Auto) confirm methods */

    //Mark a position in the service (and assoc. rehearsals) as (dis)confirmed
    function manual_confirm_position($pos_to_services_id,$value=1){
      //$value=1: confirm
      //$value=0: n/a
      //$value=-1: disconfirm
      if ($value>0){
        $b=true;
      } elseif ($value<0){
        $b=false;
      }
      if ($v=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
        if (isset($b)){
          //Either confirmed or disconfirm
          $this->event_positions->mark_positions_to_services_record_confirmed($v["id"],$b,true);        
        } else {
          //Set to n/a
          $this->event_positions->mark_positions_to_services_record_na($v["id"]);
        }
        if ($t2=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($v["id"])){
          //$t2 has rehearsal_participant records
          foreach ($t2 as $v2){
            //$v2 is a reh_part record. Mark it.
            if (isset($b)){
              $this->event_positions->mark_rehearsal_participant_record_confirmed($v2["id"],$b,true);            
            } else {
              $this->event_positions->mark_rehearsal_participant_record_na($v2["id"]);            
            }
          }
        }
        return true;
      }
      return false;
    }
    
    //Although one particular pos_to_services_id is given, retrieve them all for the associated person and mark $value
    function manual_confirm_all_positions_for_person($pos_to_services_id,$value=1){
      //Get this pos_to_services record to find out person and service
      if ($v=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
        //Now get all pos_to_services records for this person and service
        if ($positions=$this->event_positions->get_positions_to_services_records_for_person_and_service($v["service_id"],$v["person_id"])){
          foreach ($positions as $w){
            $this->manual_confirm_position($w["id"],$value);
          }
          return true;
        }        
      }      
      return false;      
    }
        
    //Mark all pending confirmations (positions and rehearsals) as (dis)confirmed
    //Give either $auto_confirm_record, or the three other params
    function confirm_all($auto_confirm_record=null,$service_id=0,$person_id=0,$val=0){
      if ($auto_confirm_record!=null){
        $ac=$auto_confirm_record;      
      } else {
        $ac=array();
        $ac["service_id"]=$service_id;
        $ac["person_id"]=$person_id;
        $ac["val"]=$val;
        $ac["id"]=null;
      }
      //Get all positions for the person in the service
      if ($t=$this->event_positions->get_positions_to_services_records_for_person_and_service($ac["service_id"],$ac["person_id"])){
        if (sizeof($t)>0){
          //$t has pos_records (at least one)
          foreach ($t as $v){
            //$v is a pos record. Mark it and all associated reharsals with the value $ac["val"]
            $this->event_positions->mark_positions_to_services_record_confirmed($v["id"],$ac["val"],true);
            if ($t2=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($v["id"])){
              //$t2 has rehearsal_participant records
              foreach ($t2 as $v2){
                //$v2 is a reh_part record. Mark it.
                $this->event_positions->mark_rehearsal_participant_record_confirmed($v2["id"],$ac["val"],true);
              }
            }
          } 
        } else {
          //The person has 0 positions in the service
          return false;
        }
      } else {
        //The person has no positions in the service
        return false;
      }
      //Remove auto_confirm record
      return $this->auto_confirm->remove_record($ac["id"]);
    }
    
    //Confirm the person's attendance for a rehearsal. Includes setting the confirmed field for several rehearsal_participant records, if the person is scheduled for multiple positions
    function confirm_rehearsal_for_person($service_id,$rehearsal_event_id,$person_id,$val){
      //Get all positions for the person in the service
      if ($t=$this->event_positions->get_positions_to_services_records_for_person_and_service($service_id,$person_id)){
        //$t has pos_records
        foreach ($t as $v){
          if ($t2=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($v["id"])){
            //$t2 has rehearsal_participant records
            foreach ($t2 as $v2){
              //$v2 is a reh_part record. Mark it if it concernse this rehearsal
              if ($v2["rehearsal"]==$rehearsal_event_id){
                $this->event_positions->mark_rehearsal_participant_record_confirmed($v2["id"],$val,true);
              }
            }
          }
        } 
      }      
    }
    
    //Confirm a position in a service
    function confirm_position($pos_to_services_id,$value=true){
      return false;
    }

    //Confirm a position in a service
    function confirm_rehearsal($rehearsal_participant_id,$value=true){
      return false;
    }

    //Does at least one rehearsal_participants record exist for this rehearsal/person that is confirmed?
    function rehearsal_is_confirmed_for_person($service_id,$rehearsal_event_id,$person_id){
      //Get all positions for the person in the service
      if ($t=$this->event_positions->get_positions_to_services_records_for_person_and_service($service_id,$person_id)){
        //$t has pos_records
        foreach ($t as $v){
          if ($t2=$this->event_positions->get_rehearsal_participant_records_by_pos_to_services($v["id"])){
            //$t2 has rehearsal_participant records
            foreach ($t2 as $v2){
              //$v2 is a reh_part record - if it concerns this rehearsal and is confirmed, return true
              if ($v2["rehearsal"]==$rehearsal_event_id){
                if ($v2["confirmed"]>0){
                  return true;
                }
              }
            }
          }
        } 
      }
      return false;                
    }
        
    //Is at least one confirmed positions_to_services record extant for this person and service?  
    function at_least_one_position_confirmed($service_id,$person_id){
      //Get all positions for the person in the service
      if ($t=$this->event_positions->get_positions_to_services_records_for_person_and_service($service_id,$person_id)){
        //$t has pos_records
        foreach ($t as $v){
          if ($v["confirmed"]>0){
            return true;
          }
        } 
      }          
      return false;
    }  
    
    //Are all extant positions_to_services records for this person and service disconfirmed?
    function all_positions_disconfirmed($service_id,$person_id){
      //Get all positions for the person in the service
      if ($t=$this->event_positions->get_positions_to_services_records_for_person_and_service($service_id,$person_id)){
        if (sizeof($t)==0){
          //This person is not scheduled for this service.
        }
        //$t has pos_records
        foreach ($t as $v){
          if ($v["confirmed"]>=0){
            return false;
          }
        } 
      }          
      return true;    
    }
      
    /* Service planning proper */
    function get_service_elements_for_ul($service_id,$operator_view=false){
      $event_records=$this->get_event_records_for_service($service_id);
          
      $service_event_id=$this->church_services->get_event_id_for_service($service_id);
      $service_record=$this->church_services->get_service_record($service_id);

      if (sizeof($event_records)>0){
        //Got at least 1 instance of the service
        $service_event_record=$event_records[0]; //For the purposes here just use that first event record (most of the time it will be the only one, at least at KR)
        if (sizeof($event_records)>1){
          //If more than one iteration, drop the actual time of the day in the timstamp
          $service_event_record["timestamp"]=getBeginningOfDay($service_event_record["timestamp"]);
        }      
        if ($elements=$this->church_services->get_all_service_element_records($service_id,true)){
          //If pre-service divider exists, calculate real start time
          $pre_length=0;
          $pre_exists=false;
          foreach ($elements as $v){
            if (($v["duration"]==0) && ($v["segment"]==CW_SERVICE_PLANNING_SEGMENT_PRE)){
              //Found pre-service divider
              $pre_exists=true;
            }
            $pre_length=$pre_length+$v["duration"];
            if (($v["duration"]==0) && ($v["segment"]==CW_SERVICE_PLANNING_SEGMENT_MAIN)){
              //Found main-service divider - finish calculation
              $pre_exists=true;
              break;
            }                 
          }
          //If $pre_exists, the pre-service length is in $pre_length; 
          $time=$service_event_record["timestamp"]+$service_record["offset"];
          if ($pre_exists){
            $time=$time-$pre_length;            
          }        
          $t="";
          foreach ($elements as $v){
            //Get name of person in charge
            ($v["person_id"]!=0) ? $name=$this->auth->personal_records->get_name_first_last($v["person_id"]) : $name=$v["other_person"];          
            if (($v["duration"]==0) && ($v["segment"]!="")){
              //Divider has no duration BUT a segment label
              if (!$this->church_services->is_group_segment_string($v["segment"])){
                //This is NOT a group divider/header
                $t.="<li id=\"f".$v["id"]."\" class=\"ui-state-default divider_li\">
                      <div class='service_element divider'>
                        ".$v["segment"]."
                      </div>
                    </li>";              
              } else {
                //This is group header
                $t.="<li id=\"f".$v["id"]."\" class=\"ui-state-default actual_element\">
                      <div class='service_element group_header'>
                        <div class='left'>
                          ".date("H:i",$time)."<br/><span class='gray'></span>
                        </div>
                        <div class='center'>
                          ".$v["label"]."
                        </div>
                        <div class='right' style='font-weight:normal'>
                          $name
                        </div>                        
                      </div>
                    </li>";    
                //Temporarily store the name of the person in charge of this group, so that those elements of the group that have the same person in charge don't need to display it
                $name_of_person_in_charge_of_current_group=$name;          
              }
            } else {
              //Actual element
              //Get type record
              $element_type=$this->church_services->get_service_element_type_record($v["element_type"]);
              empty($v["label"]) ? $label=$element_type["title"] : $label=$v["label"];
              $plain_label=$label;
              //Within a group, a label is not bold
              $this->church_services->is_group_segment_string($v["segment"]) ? $label="<span style='font-weight:normal'>$label</span>" : null;
              $note=$v["note"]; //Note defaults to actual note in element record
              if ($element_type["meta_type"]=="word"){
                $sref=$this->sh->scripture_refs->get_scripture_ref_string_for_service_element($v["id"],true);
                $textnote="";
                if (!empty($sref)){
                  if (substr($sref,0,1)!="<"){
                    $sref_link="<a class='scripture_link' style='color:black;' href=\"http://www.biblegateway.com/bible?language=NIV&passage=".urlencode($sref)."\" target=\"_blank\">".$sref."<a/>";                  
                  } else {
                    $sref_link=$sref;
                  }
                  $textnote="<span class='scripture_ref'>Text: $sref_link</span>";                
                }
                if ($element_type["title"]=="Sermon"){
                  $sermon=$this->sh->sermons->get_sermon_record_for_service_element($v["id"]);
                  if (!empty($sermon["abstract"])){
                    $textnote.="<span class='scripture_ref'> (abstract present)</span>";
                  } else {
                  }
                  if (!empty($sermon["title"])){
                    $this->church_services->is_group_segment_string($v["segment"]) ? $sermon_title_style="style='font-weight:normal;'" : $sermon_title_style="";                                      
                    $label.=":<span class='sermon_title' $sermon_title_style> &quot;".$sermon["title"]."&quot;</span>";                                    
                  }
                } 
                if (!empty($textnote)){
                  $textnote="<div>$textnote</div>";                                  
                }
                $note=$textnote.$note;
              } elseif ($element_type["meta_type"]=="music"){
                $sequence_ext="";
                $db_warning="";
                //Add name of music piece behind label
                $atse=$this->church_services->get_atse_for_service_element($v["id"],true);
                $arr_id=$atse["arrangement"];
                if ($arr_id>0){
                  $arr_rec=$this->mdb->get_arrangement_record($arr_id);
                  //See if source has index, and abbreviation is given, and identification is required
                  $source_notice="";
                  if ($source_rec=$this->mdb->get_source_record($arr_rec["source_id"])){
                    if (($arr_rec["source_index"]>0) && (!empty($source_rec["abbreviation"]) && ($source_rec["identify_in_lyrics_presentation"]>0))){
                      $source_notice=" (".$source_rec["abbreviation"]."#".$arr_rec["source_index"].")";
                    } else {
                      ($arr_rec["source_index"]>0) ? $sequence_ext=" #".$arr_rec["source_index"] : null;                                            
                    }
                    $sequence_ext.=" (".$this->mdb->int_to_musical_key($arr_rec["musical_key"],true).")";
                    $sequence_ext=" | ".$source_rec["title"].$sequence_ext;
                  }
                  $piece=$this->mdb->get_music_piece_record($arr_rec["music_piece"]);
                  if (is_array($piece)){
                    $label.="$source_notice: &quot;".$piece["title"]."&quot;";
                  } else {
                    $label.=": could not get assigned music piece. (Arr-id: $arr_id)";
                  }
                } else {
                  //No piece/arr has been assigned, but a title may have manually been entered
                  //Give warning if label is different from element_type title AND label doesn't occur in service template defaulte elements
                  if ($plain_label!=$element_type["title"]){
                    $template_record=$this->church_services->get_church_service_template_record($service_record["template_id"]);
                    if (strpos($template_record["default_elements"],$plain_label)===false){
                      $db_warning="<span style='color:orange;'>*not&nbsp;assigned&nbsp;to&nbsp;database</span>";
                    }                                    
                  }                   
                }
                //Music items non-bold and italic
                $label="<span style='font-weight:normal;font-style:italic;'>$label</span>";
                //Note gets lyrics_sequence, if present
                if (!empty($atse["lyrics"])){
                  $seq="";
                  $lyrics_ids=explode(',',$atse["lyrics"]);
                  foreach ($lyrics_ids as $g){
                    if ($g>0){
                      //Not a blank slide
                      $lr=$this->mdb->get_lyrics_record($g);
                      //Get fragment types:
                      $types=$this->mdb->get_fragment_types_records();
                      (($types[$lr["fragment_type"]-1]["title"]=="verse") || ($lr["fragment_no"]>1)) ? $fno=$lr["fragment_no"] : $fno="";     
                      ($lr["language"]!=$this->mdb->get_default_language_for_music_piece($arr_rec["music_piece"])) ? $language=$this->mdb->int_to_language($lr["language"]) : $language="";
                      (!empty($language)) ? $language=" (".$language.")" : null;
                      $title=$types[$lr["fragment_type"]-1]["short_title"].$fno.$language;
                      //Got the sequence
                      $seq.=", ".$title;
                    } else {
                      //blank slide
                      $seq.=", blank slide";
                    }
                  }
                  (!empty($seq)) ? $seq="<span style='color:gray'>".substr($seq.$sequence_ext,2)."</span>" : null; //cut first comma
                  if (empty($note)){
                    //No other note, just show sequence
                    $note=$seq;
                  } else {
                    //Note exists. On non-empty sequence append note after <br>
                    (!empty($seq)) ? $note="$seq<br/>$note" : null;
                  }
                } else {
                  //No lyrics found
                  if (!empty($db_warning)){
                    $note=$note." <span style='color:orange;'>$db_warning</span>";                                      
                  }
                }
              }
              $dur="";
              if ($v["duration"]>0){
                $dur=intval(date("i",$v["duration"]));              
                if (intval(date("s",$v["duration"]))>0){
                  $dur.=":".date("s",$v["duration"]);              
                }
                $dur.="m";
              }
              //Is this a group-member? Insert space-div (indent)
              $space="";
              if (substr($v["segment"],0,2)=="g_"){
                $space="<div class='group_spacer'>&nbsp;</div>";
                //Since this is a group_member we do NOT need to display $name if it's the same as $name_of_person_in_charge_of_current_group
                if ($name_of_person_in_charge_of_current_group==$name){
                  $name=""; //Simply don't display name for this element
                } 
              }
              $t.="<li id=\"f".$v["id"]."\" class=\"ui-state-default actual_element\">
                    <div class='service_element'>
                      <div class='left'>
                        ".date("H:i",$time)."<br/><span class='gray'>".$dur."</span>
                      </div>
                      $space
                      <div class='center'>
                        <span class='element_label'>$label</span>
                        <div class='small_note service_element_note'>
                          ".nl2br($note)."
                        </div>
                      </div>
                      <div class='right'>
                        $name
                      </div>
                    </div>
                  </li>";        
              $time=$time+$v["duration"];
            }
          }
          $script="";
          if (!$operator_view){
          	$script="
	            <script type='text/javascript'>
	              $('.scripture_link').click(function(e){
	                e.stopPropagation();
	              }).simpletip({ content:'click to show text in biblegateway.com', offset:[-170,-24]});
	            </script>
          	";
          }
          return $t.$script;
        }    
      }
      return false;
    }

    //Call this instead of church_services->add_service_element_at_position if $person_in_charge needs to be automatically determined    
    function add_service_element_at_position($pos,$service_id,$element_type,$person_in_charge,$collapse_generic=true,$group_template_id=0){
      if ($person_in_charge=="auto"){
        $person_in_charge=$this->identify_person_in_charge($service_id,$element_type,$group_template_id);  
      }
      $new_element_id=$this->church_services->add_service_element_at_position($pos,$service_id,$element_type,$person_in_charge,$collapse_generic);
      if ($new_element_id>0){
        //Success - see if extra steps are needed for this element (e.g. sermon needs sermon record)
        $element_type_rec=$this->church_services->get_service_element_type_record($element_type);
        if ($element_type_rec["title"]=="Sermon"){
          //Generate sermon record and associate with element
          $sermon_id=$this->sh->sermons->add_sermon();
          $this->sh->sermons->assign_sermon_to_service_element($sermon_id,$new_element_id); //Generate sermon record      
        }
      }  
      return $new_element_id;        
    }
    
    
    //Delete service element w/ associated records
    function delete_service_element($element_id){
      //Get element record and type record
      if ($element=$this->church_services->get_service_element_record($element_id)){
        //Got the element in question
        if ($element_type=$this->church_services->get_service_element_type_record($element["element_type"])){
          //Is regular element (type>0)
          if ($element_type["meta_type"]=="word"){
            //Remove scripture refs
            $this->sh->scripture_refs->delete_scripture_ref_records_for_service_element($element_id);
            if ($element_type["title"]=="Sermon"){
              $sermon=$this->sh->sermons->get_sermon_record_for_service_element($element_id);
              //Delete sermons_to_service_elements_record
              $this->sh->sermons->unassign_sermon_from_service_element($sermon["id"],$element_id);
              //Delete sermon record
              $this->sh->sermons->delete_sermon($sermon["id"]);
            }
          } elseif ($element_type["meta_type"]=="music"){
            //Delete arrangements_to_services_record
            $this->church_services->unassign_arrangement_from_service_element($element_id);
          }            
          //Finally remove element itself
          return $this->church_services->delete_service_element($element_id);        
        } else {
          //Type couldn't be determined. If -1: group header
          if ($element["element_type"]==-1){
            //Group header: delete header and members
            //Get members, if extant.
            if ($members=$this->church_services->get_group_elements($element["service_id"],$element["segment"])){
              //Delete members ($members is array of IDs)
              foreach ($members as $v){
                $this->delete_service_element($v); //yes, call THIS function            
              }
            }
            //Delete element in question
            return $this->church_services->delete_service_element($element_id);            
          }
        }                                                                   
      }
      return false;
    }
    
    //Attempts to attach an element to the group directly above it
    function attach_element_to_group_above($element_id,$service_id,$element_position){
      if ($service_id>0){
        //Got service_id
        if ($element_position>0){
          //Got element position
          //Attach requested - Get previous element
          if ($prev_id=$this->church_services->get_service_element_by_service_id_and_position($service_id,$element_position-1)){
            //Got element id in $prev_id - get whole element now
            if ($prev=$this->church_services->get_service_element_record($prev_id)){
              //Got element record in $prev
              if ($this->church_services->is_group_segment($service_id,$prev["segment"])){
                //Previous element is either a group header or a group element -> attach
                if ($this->church_services->add_element_to_group($prev["segment"],$element_id)){
                  $t="OK";                  
                  return true;
                } else {
                  $t="Could not attach element to group";
                }                                
              } else {
                $t="Previous element is not a group!";
              }
            } else {
              $t="Could not obtain previous element record";
            }
          } else {
            $t="Could not get id of previous element";
          }                            
        } else {
          $t="Could not determine element position";
        }
      } else {
        $t="Could not determine service ID";
      }
      return false;
    }
    
    //Apply a group template to a group header element 
    function apply_group_template($service_element_id,$template_id=0){
      ($template_id>0) ? null : $template_id=1; //Use default when template not specified
      //Make sure service_element is group header
      $r=$this->church_services->get_service_element_record($service_element_id);
      if (is_array($r)){
        if ($r["element_type"]==-1){
          //Yes, is group header
          //See if there's elements
          $service_id=$this->church_services->get_service_id_for_element($service_element_id);
          $x=$this->church_services->get_group_elements($service_id,$r["segment"],false);
          if (is_array($x) && (sizeof($x)==0)){
            //Retrieved elements of this group successfully, but there are none - meaning we can apply the template.
            //Retrieve template
            $q=$this->church_services->get_group_template_record($template_id);
            if (is_array($q)){                                      
              $y=array_reverse(explode(',',$q["default_elements"]));//Reverse array so that inserting at static position results in correct order of new elements
              //elements in y contain this: service_element_type_id[.label[.duration]]
              foreach ($y as $v){
                $this_el=explode('.',$v);
                //$this_el has one, two, or three elements (element type id, label, duration)
                $new_element_id=$this->add_service_element_at_position($r["element_nr"]+1,$service_id,$this_el[0],"auto",$template_id);
                if ($new_element_id>0){
                  //New element created, but now update to suit
                  $new_element=$this->church_services->get_service_element_record($new_element_id);
                  $new_element["segment"]=$r["segment"];
                  isset($this_el[1]) ? $new_element["label"]=$this_el[1] : null; //Got label given
                  isset($this_el[2]) ? $new_element["duration"]=$this_el[2] : null; //Got duration given
                  if (!$this->church_services->update_service_element($new_element_id,$new_element)){
                    return false;                    
                  }
                } else {
                  //Adding element failed
                  return false;                
                }
              }
              //Need to apply label still to the group_header ($service_element_id)
              $r["label"]=$q["label"];
              //Also apply person in charge, if $q["default_position_in_charge"] is given
              $r["person_id"]=$this->event_positions->get_person_in_service_position($service_id,$q["default_position_in_charge"]);
              //Save a link to the group_template_id in the service element, so that identify_person_in_charge_for_existing_element can determine the default position/person to be in charge of the group as a whole
              $r["group_template_id"]=$template_id;
              return $this->church_services->update_service_element($service_element_id,$r);            
            }
          }
        }
      }
      return false;
    }
    
    //$default_elements is string from service_templates["default_elements"]
    function create_default_elements_for_service($service_id,$default_elements){
      if (!empty($default_elements)){
        //Elements are comma-separated
        $elements=explode(',',$default_elements);
        foreach ($elements as $v){
          //A dot seperates element type id and label
          $x=explode('.',$v);
          $element_type=$x[0];
          $param=$x[1];
          $duration=$x[2];
          if ($element_type==0){
            //Divider. $param holds segment-label
            if ($param=="pre"){
              $this->church_services->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_PRE,CW_SERVICE_PLANNING_SEGMENT_PRE);          
            } elseif ($param=="main"){
              $this->church_services->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_MAIN,CW_SERVICE_PLANNING_SEGMENT_MAIN);    
            } elseif ($param=="post"){
              $this->church_services->add_service_element($service_id,0,0,CW_SERVICE_PLANNING_SEGMENT_POST,CW_SERVICE_PLANNING_SEGMENT_POST);              
            } else {
              //Non-standard divider
              $this->church_services->add_service_element($service_id,0,0,$param,$param);    
            }
          } elseif ($element_type==-1) {
            //Group header - $param holds group_template_id        
              $group_header_id=$this->church_services->add_group_header($service_id);
              if (!empty($group_header_id)){
                $this->apply_group_template($group_header_id,$param);
              }
          } elseif ($element_type>0) {
            //Regular element. $param may hold label. if not, use default.
            empty($param) ? $label="" : $label=$param ;
            $new_element_id=$this->church_services->add_service_element($service_id,$element_type,$duration,"",$label);
            if ($new_element_id>0){
              //Success - see if extra steps are needed for this element (e.g. sermon needs sermon record)
              $element_type_rec=$this->church_services->get_service_element_type_record($element_type);
              if ($element_type_rec["title"]=="Sermon"){
                //Generate sermon record and associate with element
                $sermon_id=$this->sh->sermons->add_sermon();
                $this->sh->sermons->assign_sermon_to_service_element($sermon_id,$new_element_id); //Generate sermon record      
              }            
            }                
          }
        }    
      }
    }
    
    //********************************* Service elements and music pieces / arrangements
    
    function assign_arrangement_to_service_element($arrangement,$service_element,$service_id=0){
      //Only proceed if the requested association doesn't already exist (because otherwise the lyrics_sequence saved with the association would be overwritten/reset)
      $existing_record_id=$this->church_services->arrangement_is_assigned_to_service_element($arrangement,$service_element);
      if (!($existing_record_id>0)){
        //Get lyrics sequence
        $arr_rec=$this->mdb->get_arrangement_record($arrangement);
        if (is_array($arr_rec)){
          $lyrics_sequence=$arr_rec["lyrics"];
          //If there are no lyrics, check if there are vocalists on the worship team, and take them off
          if (($lyrics_sequence=="") && ($service_id>0)){
            if ($r=$this->event_positions->get_people_in_service($service_id,$this->event_positions->get_position_type_id(CW_POSITION_TYPE_WORSHIP_TEAM))){
              //Got worship team members in $r
              foreach ($r as $v){
                //$v has pos_to_services record
                if ($this->event_positions->position_is_vocalist($this->event_positions->get_position_title($v["pos_id"]))){
                  //Found a vocalist - take off this service element
                  $this->event_positions->unassign_position_from_service_element($v["id"],$service_element);
                }
              }
            }            
          }
          //The duration of the arrangement should overwrite the duration of the service_element
          $this->church_services->overwrite_service_element_duration($service_element,$arr_rec["duration"]+CW_SERVICE_PLANNING_SONG_DURATION_EXTRA);
          return $this->church_services->assign_arrangement_to_service_element($arrangement,$service_element,$lyrics_sequence);
        }
        return false;
      } else {
        return $existing_record_id; //We're technically successful if association pre-exists
      }
    }
    
    //******* 
    
    //Used at creation of elements ($this->add_service_element_at_position). Who's in charge of the new element? Return person_id
    function identify_person_in_charge($service_id,$element_type_id,$group_template_id=0){
      $person_in_charge=false;
      //Need meta_type, so get type_record
      if ($element_type_id>0){
        //Regular element
        $element_type_record=$this->church_services->get_service_element_type_record($element_type_id);
        if (is_array($element_type_record)){
          $meta_type=$element_type_record["meta_type"];
          /*
            If meta_type for this element is music, pre-assign the first worship leader
                                              word: preacher
                                           generic: service host etc
          */                                    
          //Get people in service_leadership:
          $service_leadership=$this->event_positions->get_people_in_service($service_id,1); //1 is the id for the service leadership position type
          if ($meta_type=="music"){
            //Find worship leader
            foreach ($service_leadership as $v){
              if ($this->event_positions->get_position_title($v["pos_id"])==CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER){
                $person_in_charge=$v["person_id"];
              }          
            }      
          } elseif ($meta_type=="word"){
            //Find preacher
            foreach ($service_leadership as $v){
              if ($this->event_positions->get_position_title($v["pos_id"])==CW_SERVICE_PLANNING_DESCRIPTOR_PREACHER){
                $person_in_charge=$v["person_id"];
              }          
            }  
          } elseif ($meta_type=="drama"){
            //Find drama director
            foreach ($service_leadership as $v){
              if ($this->event_positions->get_position_title($v["pos_id"])==CW_SERVICE_PLANNING_DESCRIPTOR_DRAMA_DIRECTOR){
                $person_in_charge=$v["person_id"];
              }          
            }            
          } elseif ($meta_type=="media"){
            //Find technical director
            foreach ($service_leadership as $v){
              if ($this->event_positions->get_position_title($v["pos_id"])==CW_SERVICE_PLANNING_DESCRIPTOR_TECHNICAL_DIRECTOR){
                $person_in_charge=$v["person_id"];
              }          
            }            
          } elseif ($meta_type==""){
            //Find service host
            foreach ($service_leadership as $v){
              if ($this->event_positions->get_position_title($v["pos_id"])==CW_SERVICE_PLANNING_DESCRIPTOR_HOST){
                $person_in_charge=$v["person_id"];
              }          
            }            
          } 
        }                         
      } elseif ($element_type_id==-1) {
        //Group header
        //In this case we should have $group_template_id - get that record
        $template=$this->church_services->get_group_template_record($group_template_id);
        if (is_array($template)){
          //Got the template - field "default_position_in_charge" has a position_id that we'll try to match with a position in the service
          $person_in_charge=$this->event_positions->get_person_in_service_position($service_id,$template["default_position_in_charge"]);
        }         
      }
      return $person_in_charge;
    }       
    
    //Try to find out who should be in charge of the element, return person_id
    function identify_person_in_charge_for_existing_element($service_element_id){
      //need service_id, element_type_id, group_template_id (if applicable) - all to be read from service_element record
      $erec=$this->church_services->get_service_element_record($service_element_id);
      if (is_array($erec)){
        return $this->identify_person_in_charge($erec["service_id"],$erec["element_type"],$erec["group_template_id"]);
      }
      return false;
    }
    
    //Loop through all elements and for those who have no person_id (or other_person) assigned, try to find out and assign the person in charge
    function update_people_in_charge($service_id){
      $cs=new cw_Church_service($this,$service_id);
      foreach ($cs->elements as $k=>$v){
        $person_in_charge=$this->identify_person_in_charge_for_existing_element($v["id"]);
        if (empty($cs->elements[$k]["person_id"]) && empty($cs->elements[$k]["other_person"])){
          $cs->elements[$k]["person_id"]=$person_in_charge;
          $this->church_services->update_service_element($v["id"],$cs->elements[$k]);
        }
      }
    }
    
    //$service_plan_header is an optional header/title; $elements_to_mark may contain ids of service elements to mark up
    function get_service_plan_pdf($service_id,$service_plan_header="",$elements_to_mark=array()){
    
      /* fpdf settings */
      $font_size_label=12;
      $font_size_time=9;
      $font_size_divider=10;
      $font_size_name=11;
      $font_size_note=9;
      $font_size_scripture_ref=10;
      $element_height_divider=5;
      $element_height_time=4;
      $element_height_label=5;
      $element_height_name=10;
      $element_height_note=4;
      $element_height_scripture_ref=5;
      $column_width_time=24;
      $column_width_label=130;
      $group_indent_width=6;
      $note_indent_width=5;
      $time_vertical_offset_group_header=.3;
      $time_vertical_offset_regular=0;
      $divider_bottom_padding=1;
      $name_vertical_offset=-2;
      $space_below_note_or_sref=2;
      $gray_level_font=100;
      $usable_page_height=253; //found out by experimentation
    
      /* pseudo: Generate pdf-file, add to database, return file_id*/  
      
      $service=new cw_Church_service($this,$service_id);
         
      $filename="serviceplan_$service_id";
      //if file exists, append random string
      $addendum="";
      while (file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename.$addendum.".pdf")){
        $addendum="_".create_sessionid(3);     
      }
      $local_name="Service plan ".$service->get_info_string_for_filename().".pdf"; //name of downloaded file
      $filename=$filename.$addendum.".pdf";      
      if ($pdf=new cw_pdf('P','mm','Letter')){
        $pdf->set_auth($this->auth); //necessary so cw_PFD can print the name of current user
        //$pdf->set_page_title($service->get_info_string());  
        $pdf->AliasNbPages(); //nessesary for page numbers
        $pdf->AddPage();
        
        if (!empty($service_plan_header)){
          $pdf->set_page_title($service_plan_header);
          $pdf->header();
        }
        
        $pdf->sety(7);
        $pdf->print_church_logo(30,4);
        empty($service->title) ? $title_string=$service->service_name : $title_string=$service->service_name.": ".$service->title;
        $pdf->print_header(utf8_decode($title_string),40,-12,'R');
        $pdf->print_header($service->get_service_times_string(),40,-7,'R',10);
        $pdf->line(7,22,207,22);
        $pdf->sety(24);
  
        $event_records=$this->get_event_records_for_service($service_id);
            
        $service_event_id=$this->church_services->get_event_id_for_service($service_id);
        $service_record=$this->church_services->get_service_record($service_id);
  
        if (sizeof($event_records)>0){
          //Got at least 1 instance of the service
          $service_event_record=$event_records[0]; //For the purposes here just use that first event record (most of the time it will be the only one, at least at KR)
          if (sizeof($event_records)>1){
            //If more than one iteration, drop the actual time of the day in the timstamp
            $service_event_record["timestamp"]=getBeginningOfDay($service_event_record["timestamp"]);
          }      
          if ($elements=$this->church_services->get_all_service_element_records($service_id,true)){
            //If pre-service divider exists, calculate real start time
            $pre_length=0;
            $pre_exists=false;
            foreach ($elements as $v){
              if (($v["duration"]==0) && ($v["segment"]==CW_SERVICE_PLANNING_SEGMENT_PRE)){
                //Found pre-service divider
                $pre_exists=true;
              }
              $pre_length=$pre_length+$v["duration"];
              if (($v["duration"]==0) && ($v["segment"]==CW_SERVICE_PLANNING_SEGMENT_MAIN)){
                //Found main-service divider - finish calculation
                $pre_exists=true;
                break;
              }                 
            }
            //If $pre_exists, the pre-service length is in $pre_length; 
            $time=$service_event_record["timestamp"]+$service_record["offset"];
            if ($pre_exists){
              $time=$time-$pre_length;            
            }        
            $t="";
            foreach ($elements as $v){
              $remaining_page_height=$usable_page_height-$pdf->getY();
              if ($remaining_page_height<8){
                //Insert page break and reset y-pos
                $pdf->AddPage();
                $bottom_left_y=$pdf->getY();
              }
              //Get name of person in charge
              ($v["person_id"]!=0) ? $name=$this->auth->personal_records->get_name_first_last($v["person_id"]) : $name=$v["other_person"];          
              if (($v["duration"]==0) && ($v["segment"]!="")){
                //Divider has no duration BUT a segment label
                if (!$this->church_services->is_group_segment_string($v["segment"])){
                  //This is a service element divider, NOT a group divider/header
                  //output element divider $v["segment"];
                  $pdf->SetFillColor(180); //gray
                  $pdf->SetTextColor(255,255,255); //White
                  $pdf->SetFont('Arial','',$font_size_divider);
                  $pdf->cell(0,$element_height_divider,utf8_decode($v["segment"]),0,1,'',1);
                  $pdf->SetTextColor(0); //back to black
                  $pdf->sety($pdf->gety()+$divider_bottom_padding);
                } else {
                  //This is group header
                  //output group header: date("H:i",$time) ; $v["label"] ; $name
                 
                  //Time
                  $cy=$pdf->GetY(); //Save top position of current line
                  $pdf->SetY($cy+$time_vertical_offset_group_header);
                  $pdf->SetFont('Arial','',$font_size_time);
                  $pdf->multicell($column_width_time,$element_height_time,date("H:i",$time));
                  $bottom_left_y=$pdf->GetY(); //Save y-position at the bottom of cell just written
                  
                  //Label
                  $pdf->SetY($cy);//Go back to top
                  $pdf->Setx($column_width_time+1);
                  $pdf->SetFont('Arial','B',$font_size_label); //Group header has bold label
                  $pdf->multicell($column_width_label,$element_height_label,utf8_decode($v["label"]));
                  $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far

                  //Name
                  $pdf->SetY($cy+$name_vertical_offset);//Go back to top
                  $pdf->Setx($column_width_time+1+$column_width_label+1);
                  $pdf->SetFont('Arial','',$font_size_name); //Name is not bold
                  $pdf->multicell(0,$element_height_name,$name,0,'R');
                  $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far
                                    
                  //Advance
                  $pdf->SetY($bottom_left_y); //Go below the lowest-reaching cell so far
                  
                  
                    
                  //Temporarily store the name of the person in charge of this group, so that those elements of the group that have the same person in charge don't need to display it
                  $name_of_person_in_charge_of_current_group=$name;          
                }
              } else {
                //Actual element
                $seq="";
                $sref="";
                $sequence_ext="";
                $db_warning="";
                //Get type record
                $element_type=$this->church_services->get_service_element_type_record($v["element_type"]);
                empty($v["label"]) ? $label=$element_type["title"] : $label=$v["label"];
                if ($v["meta_type"]=="music"){
                }
                //Within a group, a label is not bold
                $this->church_services->is_group_segment_string($v["segment"]) ? $label_style="" : $label_style="B";
                $note=$v["note"]; //Note defaults to actual note in element record
                if ($element_type["meta_type"]=="word"){
                  $sref=$this->sh->scripture_refs->get_scripture_ref_string_for_service_element($v["id"],false);
                  if (!empty($sref)){
                    $sref="Text: $sref";                
                  }
                  if ($element_type["title"]=="Sermon"){
                    $sermon=$this->sh->sermons->get_sermon_record_for_service_element($v["id"]);
                    if (!empty($sermon["title"])){
                      $label.=": \"".$sermon["title"]."\"";                                    
                    }
                  } 
                } elseif ($element_type["meta_type"]=="music"){
                  //Add name of music piece behind label
                  $atse=$this->church_services->get_atse_for_service_element($v["id"],true);
                  $arr_id=$atse["arrangement"];
                  if ($arr_id>0){
                    $arr_rec=$this->mdb->get_arrangement_record($arr_id);
                    //See if source has index, and abbreviation is given, and identification is required
                    $source_notice="";
                    if ($source_rec=$this->mdb->get_source_record($arr_rec["source_id"])){
                      if (($arr_rec["source_index"]>0) && (!empty($source_rec["abbreviation"]) && ($source_rec["identify_in_lyrics_presentation"]>0))){
                        $source_notice=" (".$source_rec["abbreviation"]."#".$arr_rec["source_index"].")";
                      } else {
                        ($arr_rec["source_index"]>0) ? $sequence_ext=" #".$arr_rec["source_index"] : null;                                            
                      }
                      $sequence_ext.=" (".$this->mdb->int_to_musical_key($arr_rec["musical_key"],true).")";
                      $sequence_ext=" | ".$source_rec["title"].$sequence_ext;
                    }
                    $piece=$this->mdb->get_music_piece_record($arr_rec["music_piece"]);
                    if (is_array($piece)){
                      $label.="$source_notice: \"".$piece["title"]."\"";
                    } else {
                      $label.=": could not get assigned music piece. (Arr-id: $arr_id)";
                    }
                  } else {
                    //Likely no piece/arr has been assigned, but title may have manually been given
                    if (strpos($plain_label,":")!==false){
                      $db_warning="*not assigend to database";
                    }                  
                  }
                  //Music items non-bold and italic
                  $label_style="I";
                  //Get lyrics_sequence, if present
                  if (!empty($atse["lyrics"])){
                    $lyrics_ids=explode(',',$atse["lyrics"]);
                    foreach ($lyrics_ids as $g){
                      if ($g>0){
                        //Not a blank slide
                        $lr=$this->mdb->get_lyrics_record($g);
                        //Get fragment types:
                        $types=$this->mdb->get_fragment_types_records();
                        (($types[$lr["fragment_type"]-1]["title"]=="verse") || ($lr["fragment_no"]>1)) ? $fno=$lr["fragment_no"] : $fno="";     
                        ($lr["language"]!=$this->mdb->get_default_language_for_music_piece($arr_rec["music_piece"])) ? $language=$this->mdb->int_to_language($lr["language"]) : $language="";
                        (!empty($language)) ? $language=" (".$language.")" : null;
                        $title=$types[$lr["fragment_type"]-1]["short_title"].$fno.$language;
                        //Got the sequence
                        $seq.=", ".$title;
                      } else {
                        //blank slide
                        $seq.=", blank slide";
                      }
                    }
                    (!empty($seq)) ? $seq=substr($seq,2) : null; //cut first comma
                  } else {
                    //No lyrics found
                  }
                }
                $dur="";
                if ($v["duration"]>0){
                  $dur=intval(date("i",$v["duration"]));              
                  if (intval(date("s",$v["duration"]))>0){
                    $dur.=":".date("s",$v["duration"]);              
                  }
                  $dur.="m";
                }
                //Is this a group-member? Insert space-div (indent)
                $space=0;
                if (substr($v["segment"],0,2)=="g_"){
                  $space=$group_indent_width;
                  //Since this is a group_member we do NOT need to display $name if it's the same as $name_of_person_in_charge_of_current_group
                  if ($name_of_person_in_charge_of_current_group==$name){
                    $name=""; //Simply don't display name for this element
                  } 
                }
                //output regular element
                //Time
                $cy=$pdf->GetY(); //Save top position of current line
                $pdf->SetY($cy+$time_vertical_offset_regular);
                $pdf->SetFont('Arial','',$font_size_time);
                $pdf->multicell($column_width_time,$element_height_time,date("H:i",$time));

                //Duration
                $pdf->SetTextColor($gray_level_font); //gray
                $pdf->multicell($column_width_time,$element_height_time,$dur);
                $pdf->SetTextColor(0); //reset color
                $bottom_left_y=$pdf->GetY(); //Save y-position at the bottom of cell just written
                
                //Label
                $pdf->SetY($cy);//Go back to top
                $pdf->Setx($column_width_time+1+$space);
                $pdf->SetFont('Arial',$label_style,$font_size_label);
                
                //Is this element supposed to be marked up?
                $do_fill=false;
                if (in_array($v["id"],$elements_to_mark)){ 
                  $pdf->SetFillColor(255,255,0); //yellow
                  $do_fill=true;
                }
                
                $pdf->multicell($column_width_label,$element_height_label,utf8_decode($label),0,"",$do_fill);
                $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far

                if (!empty($sref)){
                  //Scripture ref
                  $pdf->Setx($column_width_time+1+$space+$note_indent_width);
                  $pdf->SetFont('Arial','',$font_size_scripture_ref);
                  $pdf->multicell($column_width_label,$element_height_scripture_ref,utf8_decode($sref));
                  $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far
                }

                if ((!empty($seq)) XOR (!empty($db_warning))){
                  //Sequence of lyrics
                  $pdf->Setx($column_width_time+1+$space+$note_indent_width);
                  $pdf->SetFont('Arial','',$font_size_note);
                  $pdf->SetTextColor($gray_level_font); //gray
                  $pdf->multicell($column_width_label,$element_height_note,$seq.$sequence_ext.$db_warning);
                  $pdf->SetTextColor(0); //reset color
                  $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far
                }
                
                if (!empty($note)){
                  //Note
                  $pdf->Setx($column_width_time+1+$space+$note_indent_width);
                  $pdf->SetFont('Arial','',$font_size_note);
                  $pdf->multicell($column_width_label,$element_height_note,utf8_decode($note));
                  $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far
                }
                
                if ((!empty($sref)) || (!empty($note))){
                  $bottom_left_y+=$space_below_note_or_sref;
                }

                //Name
                $pdf->SetY($cy+$name_vertical_offset);//Go back to top
                $pdf->Setx($column_width_time+1+$column_width_label+1);
                $pdf->SetFont('Arial','',$font_size_name); //Name is not bold
                $pdf->multicell(0,$element_height_name,$name,0,'R');
                $bottom_left_y=max($bottom_left_y,$pdf->GetY()); //Save the lowest bottom-pos so far
                                  
                //Advance
                $pdf->SetY($bottom_left_y); //Go below the lowest-reaching cell so far

                $time=$time+$v["duration"];                
              }
            }
          }    
        }
        
        /*
          For positions list and rehearsal times, we need to first write everything in a dummy document to find out the height of this segment.
          Then, if it does fit the rest of page 1 , put it there - not, add pagebreak before
        */
        
        //Make dummy
        $dummy=new cw_pdf('P','mm','Letter');
        $dummy->set_auth($this->auth); //necessary so cw_PFD can print the name of current user
        $dummy->AliasNbPages(); //nessesary for page numbers
        $dummy->AddPage();
        
        //Replace real pdf with dummy for test round
        $real_pdf=$pdf;
        $pdf=$dummy;
        
        for($q=0;$q<2;$q++){
          if ($q==1){
            //Second round: save height and do it to the real pdf
            $height_of_segment=$pdf->getY()-$start_y;
            $col=0;             
            unset($y_shortest_col);           
            $pdf=$real_pdf;
            $remaining_height=253-$pdf->getY();
            if ($height_of_segment>$remaining_height){
              $pdf->AddPage();
            }
          }
          $start_y=$pdf->getY();
          //Add list of people involved
          $y=$pdf->getY()+2; //Top of the list of people (get back to here for each column);
          if ((sizeof($service->positions)>0) || (sizeof($service->rehearsals)>0)){
            //Only draw this line if there are either positions or rehearsals scheduled
            $pdf->line(7,$y,207,$y);
          }
          $y=$y+2;
          $t="";
          $xspace=50; //column spacing
          //Get an array of position_type records
          if ($r=$this->event_positions->get_position_types()){
            foreach ($r as $pos_type){
              //Get array of position ids that are of type $pos_type["id"] AND are used in service $_GET['$service_id']
              if ($r1=$this->event_positions->get_positions_in_service($service_id,$pos_type["id"])){
                $t="";
                $c="";
                if (sizeof($r1)>0){
                  $c=$pos_type["title"].":";      
                }
                foreach ($r1 as $pos_id){
                  //Get records from positions_to_services that match this service and position id ($w)
                  if ($r2=$this->event_positions->get_positions_to_services_records($service_id,$pos_id)){
                    $t.=$this->event_positions->get_position_title($pos_id);
                    //Need plural s?
                    if (sizeof($r2)>1){
                      $t=plural($t);
                    }
                    $t.=": ";
                    $names="";
                    foreach($r2 as $x){
                      if ($x["last_notification"]==-1){
                        //$div_class="not_notified";
                      } else {
                        if ($x["confirmed"]>0){
                          //$div_class="confirmed";
                        } elseif ($x["confirmed"]<0){
                          //$div_class="disconfirmed";
                        } else {
                          //$div_class="pending";
                        }
                      }
                      $name=$this->auth->personal_records->get_name_first_last($x["person_id"]);
                      if ($x["person_id"]>0){
                        $guest_label="";
                      } else {
                        //$guest_label="(guest) ";                
                      }
                      $names.=", $guest_label$name$div_class";
                    }
                    if (!empty($names)){
                      $names=substr($names,2); // cut first comma
                      $t.=$names."\n";
                    }            
                  } 
                }
                $x=($col*$xspace)+10.2;
                $pdf->setXY($x,$y);
                if (!empty($t)){
                  $pdf->SetFont('Arial','B',8);
                  $pdf->multicell($xspace-3,4,$c,0,'');
                  $pdf->setX($x);                      
                  $pdf->SetFont('Arial','',7);
                  $pdf->multicell($xspace-3,3,$t,0,'');                      
                }                                                                                            
                $y_lowest_col=max($y_lowest_col,$pdf->getY());
                //Remember the shortest col's number and bottom y-pos
                if (!isset($y_shortest_col)){
                  $y_shortest_col=array($col,$pdf->getY());              
                } elseif ($pdf->getY()<=$y_shortest_col[1]){
                  $y_shortest_col=array($col,$pdf->getY());              
                }
                $col++;
              }      
            }    
          }
          
          //Rehearsal times
          $t="";
          if (sizeof($service->rehearsals)>0){
            foreach ($service->rehearsals as $v){
              $t.=date("l M j, g:ia",$v["timestamp"])."-".date("g:ia",$v["timestamp"]+$v["duration"])."\n";
              foreach ($v["bookings"] as $v2){
                $t.="  location: ".$this->rooms->get_roomname($v2["room_id"])."\n";
              }          
              
            }
            if ($col>3){
              //If there's more than three columns already go below shortest column
              $col=$y_shortest_col[0];
              $y=$y_shortest_col[1]+2;
            }
            $x=($col*$xspace)+10.2;
            $pdf->setXY($x,$y);
            $pdf->SetFont('Arial','B',8);
            $pdf->multicell($xspace-3,4,"rehearsal information:",0,'');
            $pdf->setX($x);                      
            $pdf->SetFont('Arial','',7);
            $pdf->multicell($xspace-3,3,$t,0,'');                      
            $y_lowest_col=max($y_lowest_col,$pdf->getY());
          } 
  
          if (!empty($y_lowest_col)){
            //Only draw bottom line if there were actually any entries in positions and/or rehearsal info
            $y=$y_lowest_col+2;
            $pdf->line(7,$y,207,$y);        
          }
        
        }
                
        //Write pdf
        $pdf->Output(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename,"F");
        
        if (file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename)){
          //PDF generation succeeded, now add file to db
          $f=new cw_Files($this->d);
          return $f->add_existing_file_to_db(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename,time()+CW_ON_THE_FLY_FILE_TTL,0,$local_name);      
        }
      }      
      return false;
    }
    
    function get_simple_service_plan($service_id,&$headers){
      if (($service_id>0) && ($service=new cw_Church_service($this,$service_id,true))){
        $filename="Service plan ".$service->get_info_string_for_filename().".txt";
        $filename=str_replace(" ","_",$filename);
        $headers=array();
        $headers[]="Content-Type:text/plain; charset=ISO-8859-15";
        $headers[]="Content-Disposition: attachment;Filename=$filename";
        
        foreach ($service->elements as $v){
          if ($v["element_type"]!=0){
            //Not a divider
            $label=$v["label"];
            if ($v["meta_type"]=="music"){
              //If assigned to db, get title of music piece
              $atse=$this->church_services->get_atse_for_service_element($v["id"],true);
              $arr_id=$atse["arrangement"];
              if ($arr_id>0){
                $arr_rec=$this->mdb->get_arrangement_record($arr_id);
                //See if source has index, and abbreviation is given, and identification is required
                $source_notice="";
                if ($source_rec=$this->mdb->get_source_record($arr_rec["source_id"])){
                  if (($arr_rec["source_index"]>0) && (!empty($source_rec["abbreviation"]) && ($source_rec["identify_in_lyrics_presentation"]>0))){
                    $source_notice=" (".$source_rec["abbreviation"]."#".$arr_rec["source_index"].")";
                  } else {
                    ($arr_rec["source_index"]>0) ? $sequence_ext=" #".$arr_rec["source_index"] : null;                                            
                  }
                }
                $piece=$this->mdb->get_music_piece_record($arr_rec["music_piece"]);
                if (is_array($piece)){
                  $label.="$source_notice: \"".$piece["title"]."\"";
                } else {
                  $label.=": could not get assigned music piece. (Arr-id: $arr_id)";
                }
              }
            }

            //Get name of person in charge            
            ($v["person_id"]!=0) ? $name=$this->auth->personal_records->get_name_first_last($v["person_id"]) : $name=$v["other_person"];

            //Group?
            if (!empty($v["segment"])){
              if ($v["element_type"]==-1){
                //Group header
                $indent="";
                $current_group=$v["segment"];
                ($v["person_id"]!=0) ? $current_group_person_in_charge=$this->auth->personal_records->get_name_first_last($v["person_id"]) : $current_group_person_in_charge=$v["other_person"];                
              } else {
                //Group element
                $indent="\t";
                //If name is same as group header in current group, drop
                if ($name==$current_group_person_in_charge){
                  $name="";
                }                
              }                        
            } else {
              //Definitely out of group
              $indent="";
              $current_group="";
              $current_group_person_in_charge="";
            }
            if (!empty($name)){
              $content.=$indent.$label." ($name)";                                  
            } else {
              $content.=$indent.$label;
            }
            $content.="\r\n";
          }
        }        
        return $content;      
      }
      return false;
    }
    
    function get_church_service_templates_for_ul(){
      $t="";
      $templates=$this->church_services->get_all_church_service_templates();
      if (is_array($templates)){
        foreach ($templates as $v){
          $t.="<option value='".$v["id"]."'>".$v["service_name"]."</option>";
        } 
        if (empty($t)){
          $t="<option>Error: no service templates found. You need to create one first.</option>";
        } else {
          $t="<option value=''>(select a service template...)</option>$t";        
        }
      } else {
        $t="<option>Error: could not load service templates</option>";
      }
      return $t;
    }
    
    function get_group_templates_for_ul(){
      $t="";
      $templates=$this->church_services->get_all_group_templates();
      if (is_array($templates)){
        foreach ($templates as $v){
          $t.="<option value='".$v["id"]."'>".$v["label"]."</option>";
        } 
        if (empty($t)){
          $t="<option>Error: no element group templates found. You need to create one first.</option>";
        } else {
          $t="<option value=''>(select an element group template...)</option>$t";        
        }
      } else {
        $t="<option>Error: could not load group templates</option>";
      }
      return $t;
    }
    
    //Return csl
    function get_authoritative_instrument_precedence($pos_id,$pos_to_services_id=0,$service_element_id=0){
      /*
        Order of precedence 
          - with pos_to_services_id and service_element_id given:
            - query music_packages (this gives not instrument, but file-id: prepend 'f')

          - with pos_to_services_id given:
            - query instruments_to_people (return if record is found)

          - query instruments_to_positions            
      */    
      $csl="";
      if (($pos_to_services_id>0) && ($service_element_id>0)){
        $partfile_id=$this->event_positions->get_partfile_for_position_and_service_element($pos_to_services_id,$service_element_id);
        if ($partfile_id>0){
          $csl=csl_append_element($csl,"f".$partfile_id);          
        }
      }
      if ($pos_to_services_id>0){
        if ($pts=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
          $csl=csl_append_elements($csl,$this->mdb->get_instruments_for_person($pts["person_id"],$pts["pos_id"],false,true),true); //instruments_to_people
          if ($itprec=$this->mdb->get_instruments_to_positions_record($pts["pos_id"])){
            $csl=csl_append_elements($csl,$itprec["instruments"],true); //instruments_to_positions
          }          
        }
      } else {
        //only pos_id is given
        $csl=csl_append_elements($csl,$this->mdb->get_instruments_to_positions_record($pos_id),true);        
      }
      return $csl;
    }
    
    //$elements_to_mark will contain array of service_element_ids, the returned array will contain file ids
    function get_file_ids_for_music_package($pos_to_services_id,&$elements_to_mark=array(),$key_result_array_with_service_element_ids=false,$include_off_elements=true){
      if (($pos_to_services_id>0) && ($pts=$this->event_positions->get_positions_to_services_record_by_id($pos_to_services_id))){
        if ($service=new cw_Church_service($this,$pts["service_id"],true)){
          //Go through elements - obtain file_ids where needed, and remember what elements to mark up
          $file_ids=array();
          foreach ($service->elements as $v){
            if ($this->event_positions->pos_to_services_record_is_on_service_element($pos_to_services_id,$v["id"])){
              //person is on for this piece
              if ($v["meta_type"]=="music"){   
                //Found music element
                //determine instrument precedence (part precedence)
                $instruments=explode(',',$this->get_authoritative_instrument_precedence($pts["pos_id"],$pos_to_services_id,$v["id"]));
                //get arrangements_to_service_elements record and see which of the preferred scores we can find
                $atserec=$this->church_services->get_atse_for_service_element($v["id"],true);
                if (is_array($atserec)){
                  //Go through the preferred instruments/parts
                  foreach ($instruments as $v2){
                    //$v2 has instrument id, or partfile_id if f is prepended
                    if (substr($v2,0,1)=="f"){
                      //direct link to file comes from music_packages and takes precedence in any case: use this file
                      if ($key_result_array_with_service_element_ids){
                        $file_ids[$v["id"]]=substr($v2,1);
                      } else {
                        $file_ids[]=substr($v2,1);
                      }                                               
                      $elements_to_mark[]=$v["id"];
                      break;
                    } else {
                      $r=$this->mdb->get_files_to_instruments_and_arrangements_record_for_arrangement_and_instrument($atserec["arrangement"],$v2);
                      if (is_array($r)){
                        //Got the link to the file
                        if ($key_result_array_with_service_element_ids){
                          $file_ids[$v["id"]]=$r["file"];
                        } else {
                          $file_ids[]=$r["file"];
                        }                                               
                        $elements_to_mark[]=$v["id"];
                        break;
                      }                  
                    } 
                  }
                }
              }
            } else {
              //person is off the element
              if ($key_result_array_with_service_element_ids && $include_off_elements){
                //Add element for this service_element with a zero file-id
                $file_ids[$v["id"]]=0;
              }
            }          
          }                
          return $file_ids;      
        }
      }
      return false;          
    }

    //Generate combined pdf file for the position in the service
    //If the last to params are not given, combined file will be generated for position (which may have several people)
    function get_combined_music_pdf($service_id,$pos_id,$person_id=0,$pos_to_services_id=0){

      $last_two_params_given=(($person_id!=0) && ($pos_to_services_id>0));
    
      /* pseudo: Generate pdf-file, add to database, return file_id*/  
      $service=new cw_Church_service($this,$service_id,true);

      //Get position title and names
      $r=$this->event_positions->get_positions_to_services_records($service_id,$pos_id);
      if (is_array($r)){
        $names="";
        foreach ($r as $v){
          if (($person_id==0) || ($v["person_id"]==$person_id)){
            //If no $person_id param was given, use all names
            $names.=", ".$this->auth->personal_records->get_name_first_last($v["person_id"]);      
          }
        }      
        $names=substr($names,2); //cut off first comma      
      } else {
        $names="could not load names";
      }
      $pos_title=$this->event_positions->get_position_title($pos_id);
      //Find out if position is a vocal position
      $pos_is_vocalist=$this->event_positions->position_is_vocalist($pos_title);
      if (!empty($pos_title)){
        //Need plural s?
        if ((sizeof($r)>1) && ($person_id==0)){
          //put plural s only when last character of position name is alpha (i.e. exclude something like guitarist (acoustic)))
          if (preg_match("/^[a-z]$/",substr($pos_title,-1))){
            $pos_title.="s"; //plural s                
          }
        }
        $service_plan_header=$names." - $pos_title";
      } else {
        $service_plan_header="";
      }                                                                      
      
      //Go through elements - obtain file_ids where needed, and remember what elements to mark up
      $elements_to_mark=array(); //Put ids of elements that we have music for so they can be marked up in the service plan         
      $file_ids=$this->get_file_ids_for_music_package($pos_to_services_id,$elements_to_mark);
      $file_csl=implode(",",$file_ids);
      
      //Generate target file here - list of files is in $file_csl
                  
      //Get service plan pdf as front page, and label with position title/name(s)
      $service_plan_file_id=$this->get_service_plan_pdf($service_id,$service_plan_header,$elements_to_mark);
      if ($service_plan_file_id>0){
        $file=new cw_Files($this->d);
  
        $target_pdf_filename_for_download=$service->get_info_string_for_filename()." ".$this->event_positions->get_position_title($pos_id).".pdf";
        $target_pdf_filename="music_for_service_".$service_id."_pos_".$pos_id.".pdf";
        
        $full_csl_of_files=$service_plan_file_id.",".$file_csl;
        $combined_pdf_file_id=$file->make_combined_pdf_file(explode(',',$full_csl_of_files),$target_pdf_filename,$target_pdf_filename_for_download,true,true);
              
        return $combined_pdf_file_id;         
      }
      return false;
    }
    
    //Generate powerpoint file and return file-id
    function get_lyrics_ppt($service_id){
      $cs=new cw_Church_service($this,$service_id);

      $target_ppt_filename_for_download=$cs->get_info_string_for_filename()." lyrics.pptx";
      $target_ppt_filename="lyrics_for_service_".$service_id.".pptx";

      $file=new cw_Files($this->d);
      
      $service_title="";
      if (!empty($cs->title)){
        $service_title="\"".$cs->title."\"";
      }
      
      return $file->make_lyrics_ppt($cs->get_slides(true),$cs->get_info_string()."\n$service_title",$target_ppt_filename,$target_ppt_filename_for_download);      
    }
    
    /////////////Synchronization of service planning editing////////////////////////
    /*
        Idea is to save a timestamp every time a session owner reloads (part of) the service plan
        Upon the request of an edit operation, the last_updated timestamp of the service can then be compared against the latest reload
    */

    private function get_sync_mark_pref_name($service_id,$data_type){
      return $service_id.",".$_SESSION["session_id"].",".$data_type;    
    }
    
    //Called after any sort of reload/refresh operation in the service_planning module    
    function set_sync_mark($service_id,$data_type="service_order"){
      $upref=new cw_User_preferences($this->d,$this->auth->cuid);
      $upref->write_pref($this->auth->csid,$this->get_sync_mark_pref_name($service_id,$data_type),time(),false,"SYNC_MARK",$expires=0);
    }
    
    //Has the latest sync mark (set at reload) a later timestamp than the latest update? Return distance in seconds (positive means sync mark is later)
    //If $strict, do not ignore changes made by the same session-id (for CWP) 
    function check_sync_mark($service_id,$data_type="service_order",$strict=false){
      $upref=new cw_User_preferences($this->d,$this->auth->cuid);
      if ($r=$this->church_services->get_service_record($service_id)){
        $last_service_update=$r["updated_at"]; //Timestamp of latest church service plan update
        $last_service_update_session_id=$r["updated_by_session_id"]; //The session ID by which the update was performed
        if ($last_reload=$upref->read_pref($this->auth->csid,$this->get_sync_mark_pref_name($service_id,$data_type))){
          if (($last_service_update_session_id==$_SESSION["session_id"]) && (!$strict)){
            //The last modification has been done by the same session, so send OK
            return CW_SERVICE_PLANNING_SYNC_DISTANCE+1; //the added integer is just a safety 
          } else {
            //Got the two timestamps, return difference
            return $last_reload-$last_service_update;                  
          }        
        }      
      }      
    }
    
    //////// Music package duplication ///////////////
    
    //Returns array of person_ids that the packages has been applied to
    function apply_music_package_to_others($pos_to_services_id,$same_position_only=false){
      /*
        pseudo:
          obtain list of file ids
          execute apply_music_package_to_all_in_position in $this->event_positions 
      */
      $elements_to_mark=array();      
      $file_ids=$this->get_file_ids_for_music_package($pos_to_services_id,$elements_to_mark,true);
      if (is_array($file_ids)){
        $applied_to=array();
        if (sizeof($file_ids)>0){
          //There's at least one file in the package 
          $applied_to=$this->event_positions->apply_music_package_to_others($pos_to_services_id,$file_ids,$same_position_only);        
        }
        return $applied_to;
      }                
      return false;      
    }
    
    
}

?>
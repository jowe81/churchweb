<?php

class cw_Room_bookings {

    private $d; //Database access
    
    function __construct($d){
      $this->d = $d;
    }
    
    function check_for_table(){
      return $this->d->table_exists("room_bookings");
    }
    
    function create_table(){
      return $this->d->q("CREATE TABLE room_bookings (
                          id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
                          event_id int,
                          room_id int,
                          timestamp int,
                          duration int,
                          owner int,
                          other_owner varchar(50),
                          created_by int,
                          created_at int,
                          modified_by int,
                          modified_at int,
                          note char(200),
                          confirmed TINYINT,
                          INDEX (timestamp,duration)
                        )");
    }

    //Delete table (if extant) and re-create. 
    function recreate_tables($default_records=true){
      if ($this->check_for_table()){
        $this->d->drop_table("room_bookings");
      }
      return $this->create_table();
    }

    /* Multiple bookings */
    
    //Takes the contents of e.g. the field "default_room_bookings" from church_service_templates and returns array for add_bookings function
    function csl_to_booking_requests($t){
      $res=array();
      if (!empty($t)){
        //$t looks like "room_id.start_offset.end_offset,..."
        $booking_strings=explode(',',$t);
        foreach($booking_strings as $v){
          $elements=explode('.',$v);
          $res[]=array("room_id"=>$elements[0],"start_offset"=>$elements[1],"end_offset"=>$elements[2]);
        }      
      }
      return $res;    
    }

    //Execute multiple booking requests for the same event.
    //Requests are extracted from $booking_requests_csl; Returns array with true/false for each request
    function add_bookings($event_id,$timestamp,$duration,$booking_requests_csl,$created_by=0,$note="",$confirmed=true,$owner=0){
      $booking_requests=$this->csl_to_booking_requests($booking_requests_csl); 
      if (is_array($booking_requests)){
        $t=array();
        foreach ($booking_requests as $v){
          $t[]=$this->add_booking($event_id,$v["room_id"],$timestamp-$v["start_offset"],$duration+$v["start_offset"]+$v["end_offset"],$created_by,$note,$confirmed,$owner);          
        }
        return $t;
      }
      return false;
    }

    /* Basic room booking functions: add, update, delete, identify */

    function add_booking($event_id,$room_id,$timestamp,$duration,$created_by,$note="",$confirmed=true,$owner=0){
      //Check if requested room is available at requested time
      if ($this->room_is_available($room_id,$timestamp,$duration)){
        //Yes. Build record array
        $e=array();
        $e["event_id"]=$event_id;
        $e["room_id"]=$room_id;
        $e["timestamp"]=$timestamp;
        $e["duration"]=$duration;
        $e["created_by"]=$created_by;
        $e["created_at"]=time();
        $e["note"]=$note;
        $e["confirmed"]=$confirmed;
        if ($owner==0){
          //If no owner id is given, then ascribe the booking to the person creating it
          if (!empty($owner)){
            //$owner contains a string, probably a name - save in other_owner field
            $e["other_owner"]=$owner;
          } else {
            $owner=$created_by;          
          }
        }
        $e["owner"]=$owner;
        //Execute booking
        if ($this->d->insert($e,"room_bookings")){
         return true;
        }      
      }
      return false;    
    }
    
    //Update a booking. If $timestamp/$duration/$modified_by/$owner/$room_id are left 0, those fields won't be updated -> see $this->confirm_booking
    function update_booking($booking_id,$room_id=0,$timestamp,$duration,$modified_by,$note=-1,$confirmed=-1,$owner=0){
      //Get booking record
      if ($e=$this->d->get_record("room_bookings","id",$booking_id)){
        //Update data
        if ($timestamp>0) { $e["timestamp"]=$timestamp; }
        if ($duration>0) { $e["duration"]=$duration; }
        if ($modified_by>0) { $e["modified_by"]=$modified_by; }           
        if ($owner>0){
          $e["owner"]=$owner;
          $e["other_owner"]="";
        } else {
          if (!empty($owner)){
            //$owner contains a string, probably a name - save in other_owner field
            $e["other_owner"]=$owner;
            $e["owner"]=0;
          }        
        }           
        if ($room_id!=0) { $e["room_id"]=$room_id; }           
        $e["modified_at"]=time();
        if ($note!=-1){ $e["note"]=$note; }
        if ($confirmed!=-1){ $e["confirmed"]=$confirmed; }
        //Check availability again, there might have been a time change
        if ($this->room_is_available($e["room_id"],$timestamp,$duration,$booking_id)){
          //Execute update
          return $this->d->update_record("room_bookings","id",$booking_id,$e);
        }
      }
      return false;      
    }

    //Set confirm flag on a booking
    function confirm_booking($id){
      //Get booking record
      if ($e=$this->d->get_record("room_bookings","id",$id)){
        //Update - leave other parameters alone    
        return $this->update_booking($id,0,0,0,$e["note"],true,0);
      }
      return false;      
    }

    function delete_booking($id){
      return $this->d->delete($id,"room_bookings","id");
    }
    
    //Delete those bookings who are linked to the event
    function delete_bookings_for_event($event_id){
      return $this->d->delete($event_id,"room_bookings","event_id");    
    }
    
    //Obtain bookings that pertain to room $room_id and touch the time window between the timestamps    
    function get_bookings_for_room($room_id,$timestamp1,$timestamp2){
      if ($res=$this->d->query("SELECT * FROM room_bookings WHERE room_id=$room_id AND timestamp+duration>=$timestamp1 AND timestamp<$timestamp2 ORDER BY timestamp")){
        $t=array();
        while ($e=$res->fetch_assoc()){
          $t[]=$e;
        }
        return $t;      
      }
      return false;    
    }    
    
    //Obtain bookings that end after timestamp1 and begin before timestamp2 
    function get_bookings_for_timespan($timestamp1,$timestamp2){
      if ($res=$this->d->query("SELECT * FROM room_bookings WHERE timestamp+duration>=$timestamp1 AND timestamp$x<$timestamp2 ORDER BY timestamp,duration")){
        $t=array();
        while ($e=$res->fetch_assoc()){
          $t[]=$e;
        }
        return $t;      
      }
      return false;        
    }
    
    function get_bookings_in_progress(){
      return $this->get_bookings_for_timespan(time(),time());
    }

    //Obtain bookings that end after timestamp1 and end before timestamp2
    function get_completed_bookings_for_timespan($timestamp1,$timestamp2){
      if ($res=$this->d->query("SELECT * FROM room_bookings WHERE timestamp+duration>=$timestamp1 AND timestamp+duration$x<$timestamp2 ORDER BY timestamp+duration DESC")){
        $t=array();
        while ($e=$res->fetch_assoc()){
          $t[]=$e;
        }
        return $t;      
      }
      return false;        
    }
    
    //Obtain bookings that start after timestamp1 and start before timestamp2
    function get_upcoming_bookings_for_timespan($timestamp1,$timestamp2){
      if ($res=$this->d->query("SELECT * FROM room_bookings WHERE timestamp>=$timestamp1 AND timestamp<$timestamp2 ORDER BY timestamp,duration")){
        $t=array();
        while ($e=$res->fetch_assoc()){
          $t[]=$e;
        }
        return $t;      
      }
      return false;        
    }
    
    function get_todays_latest_ending_booking($offset=0){
      //For performance reasons Limit the search to bookings that no earlier than began a week ago (means we would miss longer bookings) 
      $query="SELECT * FROM room_bookings WHERE timestamp>".(time()-WEEK)." AND timestamp+duration>".(getBeginningOfDay(time())+$offset)." AND timestamp+duration<=".(getEndOfDay(time())+$offset)." ORDER BY (timestamp+duration) DESC LIMIT 2;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          if ($res->num_rows>1){
            //Look at 2nd record and see if timestamp is the same - then we have multiple bookings ending together
            if ($q=$res->fetch_assoc()){
              if (($q["timestamp"]+$q["duration"])==($r["timestamp"]+$r["duration"])){
                $r["room_id"]=0; //means: multiple bookings end at the same time              
              }            
            }
          }
          return $r;
        }
      }
      return false;
    }
        
    function get_tomorrows_earliest_beginning_booking($offset=0){
      $query="SELECT * FROM room_bookings WHERE timestamp>".(getEndOfDay(time())+$offset)." AND timestamp<".(getEndOfDay(time()+DAY)+$offset)." ORDER BY timestamp LIMIT 2;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          if ($res->num_rows>1){
            //Look at 2nd record and see if timestamp is the same - then we have multiple bookings beginning together
            if ($q=$res->fetch_assoc()){
              if (($q["timestamp"])==($r["timestamp"])){
                $r["room_id"]=0; //means: multiple bookings begin at the same time              
              }            
            }
          }
          return $r;
        }
      }
      return false;
    }

    //Get array of names of rooms that are no longer used today, but have been used earlier today (i.e. bookings completed today)
    function get_no_longer_used_today($offset=0){
      $query="
        SELECT DISTINCT 
          rooms.id,rooms.name
        FROM
          room_bookings LEFT JOIN rooms ON rooms.id=room_bookings.room_id
        WHERE 
          room_bookings.timestamp+room_bookings.duration>".(getBeginningOfDay(time())+$offset)." 
            AND 
          room_bookings.timestamp+room_bookings.duration<".(time())."
        ORDER BY
          rooms.name
      ;";
      if ($res=$this->d->q($query)){
        //We now have room ids of rooms that have at least 1 booking completed today
        //Need to check for each of them whether they have another booking come up today, in which case they have to be taken off the list of no longer used rooms
        $t=array();
        while ($r=$res->fetch_assoc()){
          if (!$this->get_bookings_for_room($r["id"],time(),getEndOfDay(time())+$offset)){
            $t[]=$r["name"];
          }                                                                        
        }
        return $t;
      }
      return false;
    }
    
    //Get array of names of rooms that are currently in use
    function get_currently_used(){
      $query="
        SELECT DISTINCT 
          rooms.name
        FROM
          room_bookings LEFT JOIN rooms ON rooms.id=room_bookings.room_id
        WHERE 
          room_bookings.timestamp<".(time())." 
            AND 
          room_bookings.timestamp+room_bookings.duration>".(time())."
        ORDER BY
          rooms.name
      ;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){                                                                        
          $t[]=$r["name"];
        }
        return $t;
      }
      return false;
    }
    
    //Get array of names of rooms will be used later, i.e. beginning of booking is in the future, but will begin today
    function get_used_later_today($offset=0){
      $query="
        SELECT DISTINCT 
          rooms.name
        FROM
          room_bookings LEFT JOIN rooms ON rooms.id=room_bookings.room_id
        WHERE 
          room_bookings.timestamp>".(time())." 
            AND 
          room_bookings.timestamp<".(getEndOfDay(time())+$offset)."
        ORDER BY
          rooms.name
      ;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){                                                                        
          $t[]=$r["name"];
        }
        return $t;
      }
      return false;
    }
    
    //Return array of booking records associated with an event    
    function get_bookings_for_event($event_id){
      if ($res=$this->d->query("SELECT * FROM room_bookings WHERE event_id=$event_id")){
        $t=array();
        while ($e=$res->fetch_assoc()){
          $t[]=$e;
        }
        return $t;      
      }
      return false;
    }
    
    //Return a single booking record by id 
    function get_booking($id){
      return $this->d->get_record("room_bookings","id",$id);
    }
        
    //Is the room available at the timespan in question?
    //An 'excepted booking id' may be passed. If that id causes a conflict, it will be ignored (this is for booking updates with timechanges)
    function room_is_available($room_id,$timestamp,$duration,$excepted_booking_id=0){
      if ($room_id>0){
        /*
          $r1=requested start time (timestamp)
          $r2=requested end time (timestamp+duration)
          $e1=requested extant booking start time (timestamp)
          $e2=requested extant booking end time (timestamp+duration)
          
          An extant booking does not conflict with the request
          if ((e2<r1) || (r2<e1))
          
          If the above expression is true for all extant bookings, then the room is available
          at the requested time
          
          $r1=$timestamp
          $r2=$timestamp+$duration
          $e1=timestamp (in db)
          $e2=timestamp+duration (in db)
          
          What bookings do we have to investigate?
          
          SELECT * FROM room_bookings WHERE 
            !((timestamp+duration<$timestamp) OR ($timestamp+$duration<timestamp))
            
          If there are no results on this query, the booking can be made 
        */
        if ($excepted_booking_id==0){
          $query="SELECT * FROM room_bookings WHERE room_id=$room_id AND (!((timestamp+duration<=$timestamp) OR ($timestamp+$duration<=timestamp)))";      
        } else {
          //If excepted_booking_id is given, exclude it from the test (i.e. for each booking there's either no conflict or it's id is the excepted one)
          $query="SELECT * FROM room_bookings WHERE room_id=$room_id AND ((!((timestamp+duration<=$timestamp) OR ($timestamp+$duration<=timestamp))) AND (!(id=$excepted_booking_id)))";            
        }
        if ($res=$this->d->query($query)){
          if (mysqli_num_rows($res)>0){
            //Conflict with existing booking
            return false;          
          } else {
            return true;
          }
        }      
      } else {
        //Always return true for guest rooms (negative room_id)
        return true;
      }
      return false;
    }

    /* Display is handled by cw_Display_room_bookings*/
    


}

?>
<?php

  class cw_Display_room_bookings {
  
    public $a,$room_bokings,$eh;
    private $d; //Database access
    
    function __construct($d,$a){
      $this->d=$d; //Database access
      $this->a=$a; //cw_Auth
      $this->room_bookings=new cw_Room_bookings($d);
      $this->eh=new cw_Event_handling($a);
    }
  
    function output_js(){
      $t="
        <script type='text/javascript'>
          //This will be called by the links from the hour blocks and edit links
          function edit_booking(id,room_id,timestamp){
              $('#main_content').fadeTo(500,0.3);
              $('#modal').load('".CW_AJAX."ajax_room_booking.php?action=display_edit_dialogue&id=' +id+ '&room_id=' +room_id+ '&timestamp=' +timestamp);
              $('#modal').fadeIn(200);                        
          }
        </script>      
      ";
      return $t;    
    }
  
    function display_hour_block($i,$room_id,$timestamp){ //Need to know $room_id  and $timestamp for create - links
      if (($i<12) && ($i>0)){
        $h=$i."am";
      } elseif ($i==12){
        $h="12pm";
      } elseif (($i==24) || ($i==0)){
        $h="12am";
      } else {
        $h=($i-12)."pm";
      }
      //Assign night-class to differently shaded hour blocks      
      $night_class="";
      if (($i<8) || ($i>=22)){
        $night_class="hour_block_night";
      }
      //The js for creating a booking is only applicable if user has editing rights for the booking system
      if ($this->a->csps>=CW_E){
        if (time()<getBeginningOfDay($timestamp)+($i+1)*HOUR){
          //Allow up to an hour in the past/after the fact booking        
          $t="<div onClick='javascript:edit_booking(0,$room_id,".(getBeginningOfDay($timestamp)+$i*HOUR).");' class='hour_block $night_class'>$h<br/>:30</div>";        
        } else {
          $t="<div onClick=\"javascript:alert('Cannot create room booking in the past.');\" class='hour_block $night_class'>$h<br/>:30</div>";        
        }
      } else {
        $t="<div onClick=\"javascript:alert('Cannot create room booking: you need at least editor rights to the booking system to create a room-booking.');\" class='hour_block $night_class'>$h<br/>:30</div>";              
      }
      return $t;
    }
    
    function display_day_column($timestamp,$room_id,$start_hour=0,$end_hour=24,$room_descriptor){
      //Create the time grid
      $t="<div id='day_column_header'>$room_descriptor</div>";
      for ($i=$start_hour;$i<$end_hour;$i++){
        $t.=$this->display_hour_block($i,$room_id,$timestamp);
      }
      // d = vertical offset (because of header with roomname) 
      // b = height of an hour
      $d=22;
      $b=26; //From room_booking.css - height of hour block plus border
      //Calculate timestamps of beginning and end of display area
      $start_timestamp=getBeginningOfDay($timestamp)+HOUR*$start_hour;
      $end_timestamp=getBeginningOfDay($timestamp)+HOUR*$end_hour;
      //If the current time is in view, add arrow
      if ((time()>$start_timestamp) && (time()<$end_timestamp)){
        $c=(time()-$start_timestamp)/HOUR; //Distance of present moment from $start_hour (in hours)
        //Vertical position of arrow is offset + height of hour * #of hours that present moment is away from start_hour ($c)
        $arr_top=$d+($b*$c)-5; //Take off 5px because the arrow is 9px high
        $t.="<div class='arrow' style=\"background-image:url('".CW_ROOT_WEB."img/arrow_l.gif');top:".$arr_top."px;\"></div>"; 
      }
      //Display booking(s)
      /* Pseudo-Code:
        Get all relevant room bookings
        For each booking {
          If currently ongoing, mark it up
          Create positioned/sized div
          Write booking info into that div
        }
      */
      $bookings=$this->room_bookings->get_bookings_for_room($room_id,$start_timestamp,$end_timestamp);
      $z=999; //For output z-index 
      foreach ($bookings as $v){
        $z--; //Output divs with descending z-index
        //Calculate vertical position:
        // c = distance of the beginning of the booking from the start_hour (in hours)
        // top = v+floor(b*c) (drawing position)
        // height = b* duration in hours - 2 (height of div)
        $c=(($v["timestamp"]-$start_timestamp)/HOUR);
        $top=$d+floor($b*$c);
        $height=floor($b*($v["duration"]/HOUR))-2; //-2 because of border
        //Height needs to be cut down if it would overhand below the end_hr
        //That is the case when the top of the booking div + its height is greater than the bottom end of the last hour displayed
        //We need to know: top of booking ($top), bottom of display ($disp_bottom), and the height of the booking ($height)
        $disp_bottom=$d+$b*($end_hour-$start_hour);
        if ($top+$height>$disp_bottom){
          $height=$height-($top+$height-$disp_bottom+2);        
        }
        //If booking starts BEFORE the first hour displayed, gray out the div and fit
        //This is the case when $top is lower than the vertical offset
        if ($top<$d){
          $t.="<div class='booking booking_overhang'
                style='top:".$d."px;
                  height:".($height-($d-$top))."px;
                  overflow:auto;'
                onmouseover=\"javascript: {
                                this.style.height=this.scrollHeight+'px';
                              }\"
                onmouseout=\"javascript: {
                                this.style.height='".($height-($d-$top))."px';
                              }\"
                >
                ".date('m/d g:ia',$v["timestamp"])." - ".date('m/d g:ia',$v["timestamp"]+$v["duration"])."                
                </div>";        
        } else {
          /*Determine applicability of edit/delete links
            You can edit/delete if:
              you have admin rights to the booking system OR
              you have editing rights to the booking system AND own the booking in question
          */
          $links="";          
          if ( ($this->a->csps>=CW_A) || (($this->a->csps==CW_E) && ($this->a->cuid==$v["owner"]))) {
            $links="[<a class='a_delete_booking' id='del".$v["id"]."' href='#'>x</a>][<a id='edit".$v["id"]."' href='#'>e</a>] ";
            $links.="<script type='text/javascript'>
                      $('#del".$v["id"]."').click(function(evt){
                        $.post('".CW_AJAX_DB."',{ query:'DELETE FROM room_bookings WHERE id=".$v["id"]."' },function (){
                          init_dest_element();                                                  
                        });
                      });
                      
                      $('#edit".$v["id"]."').click(function(evt){
                        edit_booking(".$v["id"].");
                      });
                      
                    </script>";                      
          }
          //If event is currently going on, mark it up
          $border="";
          if (($v["timestamp"]<time()) && (($v["timestamp"]+$v["duration"])>time())){
            $border="border:1px solid red;";
          }
          $t.="<div class='booking'
                style='top:".$top."px;
                  height:".$height."px;
                  overflow:auto;
                  $border
                  z-index:$z;'
                onmouseover=\"javascript: {
                                this.style.height=this.scrollHeight+'px';
                                this.style.background='white'; 
                              }\"
                onmouseout=\"javascript: {
                                this.style.height='".$height."px';
                                this.style.background='#FDF'; 
                              }\"
                
                >";
          $t.=$links.date('g:ia',$v["timestamp"])." - ".date('g:ia',$v["timestamp"]+$v["duration"]);
          
          //Got event-id?        
          if ($v["event_id"]>0){
            //Is event a service?
            $event=$this->eh->events->get_event_record($v["event_id"]);
            if ($event["church_service"]>0){
              $service=new cw_Church_service($this->eh,$event["church_service"]);
              $t.="<br/><span style='font-weight:bold;'>".$service->service_name."</span>";
            }
          }
          if ($v["note"]!=""){
            $t.="<br/>".$v["note"];
          }
          $t.="<span class='gray'>";
          //Show owner only if different from booking agent
          if (($v["owner"]>0) && ($v["owner"]!=$v["created_by"])){
            $t.="<br/>Owner: ".$this->a->personal_records->get_name_first_last($v["owner"]);
          }
          if (($v["owner"]==0) && (!empty($v["other_owner"]))){
            $t.="<br/>Owner: ".$v["other_owner"];          
          }
          if ($v["created_by"]>0){
            $t.="<br/>Created by: ".$this->a->personal_records->get_name_first_last($v["created_by"]);
          }
          if ($v["created_at"]>0){
            $t.="<br/>Created at: ".date('m/d/Y g:ia',$v["created_at"]);
          }

          if (($v["modified_at"]!=0) && ($v["modified_at"]!=$v["created_at"])){
            //Has been modified/updated
            if ($v["modified_by"]!=$v["created_by"]){
              $t.="<br/>Modified by: ".$this->a->personal_records->get_name_first_last($v["modified_by"]);
            }
            $t.="<br/>Modified at: ".date('m/d/Y g:ia',$v["modified_at"]);            
          }          
          $t.="</span>";
          /*foreach($v as $k=>$v2){
            $t.="<br/>$k : $v2";
          }*/
          $t.="</div>"; 
        }
      }
      
      
      $t="<div class='day_column'>$t</div>";
      return $t;
    }

    /* Live display */

    //Takes a record from the room_bookings table
    function format_booking_for_live_display($booking,$eh,$omit_progress_info=false){
      //Owner info
      if ($booking["owner"]>0){
        $owner=$this->a->personal_records->get_name_first_last($booking["owner"]);
      } else {
        if (!empty($booking["other_owner"])){
          $owner=$booking["other_owner"];            
        } else {
          $owner="n/a";
        }
      }
      $owner="
        <div class='bk_field'>
          <div class='bk_field_header'>In charge:</div>
          <div class='bk_field_data'>$owner</div>
        </div>
      ";
      //Got event-id?
      $event_type="";        
      if ($booking["event_id"]>0){
        //Is event a service?
        $event=$eh->events->get_event_record($booking["event_id"]);
        if ($event["church_service"]>0){
          $info_add="";
          if ($event["is_rehearsal"]>0){
            $info_add="Rehearsal for ";
          }
          $service=new cw_Church_service($eh,$event["church_service"]);
          $event_type.="
            <div class='bk_field'>
              <div class='bk_field_header'>Event:</div>
              <div class='bk_field_data'>".$info_add.$service->service_name."</div>
            </div>
            ";
        }
      }
      //Note?
      $note="";
      if (!empty($booking["note"])){
        $note="
            <div class='bk_field'>
              <div class='bk_field_header'>Info:</div>
              <div class='bk_field_data'>".$booking["note"]."</div>
            </div>
        ";
      }
      //Multi day booking?
      $multi="";
      if (!isSameDay($booking["timestamp"],$booking["timestamp"]+$booking["duration"])){
        //if it starts on a different day
        if (!isSameDay($booking["timestamp"],time())){
          $multi=", started ".date("D M j",$booking["timestamp"]);
        }
        //if it ends on a different day
        if (!isSameDay($booking["timestamp"]+$booking["duration"],time())) {
          $multi.=", ends ".date("D M j",$booking["timestamp"]+$booking["duration"]);        
        }
        $multi=substr($multi,2);//cut first comma
        $multi="
          <div class='bk_multi'>
            $multi
          </div>
        ";
      }             
      //In Progress?
      $in_progress="";
      if (!$omit_progress_info){
        if (($booking["timestamp"]<time()) && ($booking["timestamp"]+$booking["duration"]>time())){
          //%=elapsed time / total time *100
          $x=round(((time()-$booking["timestamp"]) / $booking["duration"])*100);
          $in_progress="
              <div class='bk_field' style='background:lime;border-bottom:1px solid black;'>            
                <div class='bk_progress_bar' style='width:$x%;'>
                </div>
                <div style='clear:both;'>
                </div>
              </div>
              <div class='bk_field'>
                <div class='bk_field_header'>Progress:</div>
                <div class='bk_field_data'>started ".getHumanReadableLengthOfTime(time()-$booking["timestamp"])." ago, ends in ".getHumanReadableLengthOfTime($booking["timestamp"]+$booking["duration"]-time())."</div>
                <div style='clear:both;'>
                </div>
              </div>            
          ";
        }
      }
      $x="<div class='booking'>
            <div class='bk_roomname'>
              ".$eh->rooms->get_roomname($booking["room_id"])."
            </div>
            <div class='bk_time'>
              ".date('h:i a',$booking["timestamp"])." - ".date('h:i a',$booking["timestamp"]+$booking["duration"])."
            </div>
            $multi
            $in_progress
            $event_type
            $owner
            $note
            <div style='clear:both;'></div>
          </div>";
      return $x;
    }          

  }

?>
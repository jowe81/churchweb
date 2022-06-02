<?php

class cw_Date_picker {

  public
    $timestamp, //Reference position for date picker
    $dest_element_id, //Identifier for destination element - the element that after picking of a date will be updated, e.g. #calendar
    $ajax_script_date_picker, //Path to the ajax script that produces the html to update the date picker with, including static parameters
    $ajax_script_dest_element, //Path to the ajax script that produces the html to update the destination element with, including static parameters
    $calling_service; //This is needed for pass-through (so that the ajax scripts can find the required user preferences)
    
  function __construct($timestamp=0,$calling_service=0){
    if ($timestamp==0){
      $timestamp=time();
    }
    $this->timestamp=$timestamp;
    //Set ajax scripts to defaults:
    $this->ajax_script_date_picker=CW_AJAX."ajax_date_picker.php";
    $this->ajax_script_dest_element=CW_AJAX."ajax_room_booking.php";
    //Set destination element id to default
    $this->dest_element_id="dest_element";
    //Calling service
    $this->calling_service=$calling_service;
  }
  
  function display(&$html,&$jquery){
    $t=''; //Html return
    $j=''; //JQuery return

    //Note that JQuery functions for reloading, reload_date_picker(timestamp) and reload_dest_element(timestamp)
    //must be provided before caling this function (i.e., by display_first()) and are assumed accessible to javascript
        
    //Produce relative navigation
    $t.="<div class='datepicker_relative_nav'>
          <p style='font-size:60%;margin:0px;padding:0px;'>
            <span id='rn_b_d'>&lt;&lt; day</span>
            <span id='rn_b_w'>week</span>
            <span id='rn_b_m'>month</span>
            <span id='rn_b_y'>year</span>
          </p>
          <p style='padding:0px;margin:0px;'>
            <span id='rn_today'>Today</span>
            <span id='rn_tomorrow'>Tomorrow</span>
          </p>
          <p style='font-size:60%;margin:0px;padding:0px;'>
            <span id='rn_f_d'>&gt;&gt; day</span>
            <span id='rn_f_w'>week</span>
            <span id='rn_f_m'>month</span>
            <span id='rn_f_y'>year</span>
          </p>
        </div>";
    //Jquery for relative nav
    $rn_b_d=$this->timestamp-DAY; //Timestamp for 1 day back (_Relative _Navigation _Back _Day)
    $rn_b_w=$this->timestamp-WEEK;
    $rn_b_m=getBeginningOfPreviousMonth($this->timestamp);
    $rn_b_y=getBeginningOfYear($this->timestamp-1);
    $rn_f_d=$this->timestamp+DAY;
    $rn_f_w=$this->timestamp+WEEK;
    $rn_f_m=getBeginningOfNextMonth($this->timestamp);
    $rn_f_y=getBeginningOfNextYear($this->timestamp);
    $j.="
      //Past
      $('#rn_b_d').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_b_d + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_b_w').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_b_w + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_b_m').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_b_m + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_b_y').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_b_y + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      //Today
      $('#rn_today').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + ".time()." + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      //Tomorrow
      $('#rn_tomorrow').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + ".getBeginningOfDay(time()+DAY)." + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      //Future
      $('#rn_f_d').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_f_d + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_f_w').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_f_w + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_f_m').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_f_m + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      $('#rn_f_y').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + $rn_f_y + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');         
          }); 
      });
      
      
      ";
    //Produce divider
    $t.="<div class='datepicker_divider'>&nbsp;</div>";
    //Produce the buttons for the next 30 days
    for ($i=-1;$i<=29;$i++){
      $this_day=$this->timestamp+($i*DAY); //The day currently processed
      $class="datepicker_day datepicker_other_day"; //CSS
      if ((date("w",$this_day))==0){
        //Sunday
        $class="datepicker_day datepicker_sunday";
      }
      if ((date("w",$this_day))==6){
        //Saturday
        $class="datepicker_day datepicker_saturday";
      }
      if (isToday($this_day)){
        //Mark up current day
        $class.=" datepicker_today";
      }
      if (isBeforeToday($this_day)){
        //Mark up past days
        $class.=" datepicker_past";
      }
      if (isAfterToday($this_day)){
        //Mark up future days
        $class.=" datepicker_future";      
      }
      
      $t.="<div class='$class' id='day$i' style='float:left;padding:2px;text-align:center;'>
                <p style='font-size:60%;margin:0px;padding:0px;'>".date("M",$this_day)."</p>
                <p style='padding:0px;margin:0px;'>".date("d",$this_day)."</p>
                <p style='font-size:60%;margin:0px;padding:0px;'>".date("D",$this_day)."</p>
          </div>";
      //JQuery code for click event on this button (div)
      $j.="
        $('#day$i').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + ".$this_day." + '&calling_service=$this->calling_service', function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');                   
          });
        });";
    }
    //Produce divider
    $t.="<div class='datepicker_divider'>&nbsp;</div>";    
    //Produce the buttons for the next 6 months
    for ($i=1;$i<7;$i++){
      $this_day=getBeginningOfNextMonth($this->timestamp,$i); //The month currently processed
      $class="datepicker_month datepicker_other_day";
      if ((date("w",$this_day))==0){
        //Sunday
        $class="datepicker_month datepicker_sunday";
      }
      if ((date("w",$this_day))==6){
        //Saturday
        $class="datepicker_month datepicker_saturday";
      }
      if (isToday($this_day)){
        //Mark up current day
        $class.=" datepicker_today";
      }
      if (isBeforeToday($this_day)){
        //Mark up past days
        $class.=" datepicker_past";
      }
      if (isAfterToday($this_day)){
        //Mark up future days
        $class.=" datepicker_future";      
      }
      
      $t.="<div class='$class' id='month$i' style='float:left;padding:2px 1px;text-align:center;'>
                <p style='font-size:60%;margin:0px;padding:0px;'>".date("Y",$this_day)."</p>
                <p style='margin:0px;padding:0px;'>".date("M",$this_day)."</p>
                <p style='font-size:60%;padding:0px;margin:0px;'>&nbsp;</p>
          </div>";
      //JQuery code for click event on this button (div)
      $j.="
        $('#month$i').click(function(){
          $('#date_picker_wrap').load('$this->ajax_script_date_picker?current_timestamp=' + ".$this_day." + '&calling_service=$this->calling_service',function(){
            $('#$this->dest_element_id').load('$this->ajax_script_dest_element?calling_service=$this->calling_service');                   
          });
        });";
    }
        
    //Produce headline with selected day
    if (isToday($this->timestamp)){
      $note=" (today) ".date("- h:ia",time());
    } elseif (isTomorrow($this->timestamp)){
      $note="(tomorrow)";    
    } elseif (isYesterday($this->timestamp)){
      $note="(yesterday)";    
    } elseif (isFuture($this->timestamp)){
      if (($this->timestamp-time())<(WEEK*2)){
        //For the first 14 days out ignore hours
        $note="(in ".(getDaysOut($this->timestamp)+1)."d)";
      } else {
        $note="(in ".getHumanReadableLengthOfTime($this->timestamp-time()+DAY).")";
      }
    } else {
      if ((time()-$this->timestamp)<(WEEK*2)){
        //For the first 14 days out ignore hours
        $note="(".((floor(abs($this->timestamp-time())/DAY)))."d ago)";
      } else {
        $note="(".getHumanReadableLengthOfTime(time()-$this->timestamp)." ago)";
      }    
    }
    $t.="<div id=\"date_picker_selected_day\">".date("l, F j, Y",$this->timestamp)." $note<div>";
    
    //Return    
    $html=$t;   //Return value for $html
    $jquery=$j; //Return value for $jquery  
  }
  

}

?>
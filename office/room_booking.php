<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/date_picker.css");
  $p->stylesheet(CW_ROOT_WEB."css/room_booking.css");

  $room_bookings = new cw_Room_bookings($d);
  
  //Save current time as display time to start with
  $upref = new cw_User_preferences($d,$_SESSION["person_id"]);
  $time=$upref->write_pref($a->csid,"current_timestamp",time());

  //JQuery functions to init and reload the date picker and dest_element (the room bookings)
  //--(these must be in the global js scope b/c scripts sent from ajax_room_booking.php must be able to access them [really we should place all independent functions in $p->js, not in $p->jquery])
  $p->js("
      function init_date_picker(){
        $('#date_picker_wrap').load('".CW_AJAX."ajax_date_picker.php?current_timestamp=".time()."&calling_service=$a->csid');              
      }
          
      function refresh_date_picker(){
        $('#date_picker_wrap').load('".CW_AJAX."ajax_date_picker.php?calling_service=$a->csid');              
      }  
  
      function init_dest_element(){
        $('#dest_element').load('".CW_AJAX."ajax_room_booking.php?calling_service=$a->csid');                    
      }
  ");

  
  //Placeholder <div> for date picker
  $p->p("<div id=\"date_picker_wrap\">Loading...</div>");
  
  //JQuery functions for setting preferences and adjusting enabled/disabled checkboxes for time-range
  $p->js("
      function set_disabled_checkboxes(){
         //Special case of time-ranges: the middle can't stand alone.
         //If 1 is checked but not 2, 3 must be disabled
         if ($('#display_timespan_1').attr('checked')=='checked' && $('#display_timespan_2').attr('checked')!='checked'){
          $('#display_timespan_3').attr('disabled',true);         
         } else {
          $('#display_timespan_3').removeAttr('disabled');                  
         }      
         //If 3 is checked but not 2, 1 must be disabled
         if ($('#display_timespan_3').attr('checked')=='checked' && $('#display_timespan_2').attr('checked')!='checked'){
          $('#display_timespan_1').attr('disabled',true);         
         } else {
          $('#display_timespan_1').removeAttr('disabled');                  
         }      
         //If 1 and 3 are checked, 2 must be disabled
         if ($('#display_timespan_1').attr('checked')=='checked' && $('#display_timespan_3').attr('checked')=='checked'){
          $('#display_timespan_2').attr('disabled',true);         
         } else {
          $('#display_timespan_2').removeAttr('disabled');                  
         }      
      }
  
      //Generic function to set preference through ajax (for all checkboxes)  
      function set_bool_pref(dom_id,no_reload){
         var c=$('#'+dom_id).attr('checked');
         if (c=='checked'){
          c='1';
         } else {
          c='0';
         } 
         $.get('".CW_AJAX_USER_PREFERENCES."?service=".$a->csid."&pref_name='+dom_id+'&pref_value='+c,function(){
          //After changing pref, reload dest_element
          if (!no_reload){
            init_dest_element();
          }          
         });                      
         set_disabled_checkboxes();         
      }  
  ");  

  
  $t=""; //HTML
  $s=""; //Javascript/JQuery  

  //Selection divs (rooms to display, and time ranges of the day to display)

  //Produce room selection div
  $rooms=new cw_Rooms($d);
  $room_info=$rooms->get_all_rooms("active DESC,name"); //Get full record for all rooms
  $upref=new cw_User_preferences($d,$_SESSION["person_id"]);
  //First, 2 buttons: deselect all, and select most popular
  $t.="
      <input type='button' id='deselect_all' value='deselect all'/>
      <input type='button' id='select_popular' disabled=true value='select popular'/>
      ";
  $s.="
      //Deselect all selected rooms from the list of rooms to display.
      $('#deselect_all').click(function(){
        $('.display_room_checkbox').each(function(){
          $(this).removeAttr('checked');
          set_bool_pref($(this).attr('id'),true); //Change the preferences but don't reload the dest_element every time        
        });
        //After unsetting all the prefs, do one reload
        init_dest_element(".$a->csid.");                        
      })
      ";
  //Checkbox for each room
  foreach ($room_info as $k=>$v){
    //Retrieve previously saved setting
    $checked="";
    if ($upref->read_pref($a->csid,"display_room_".$v["id"])>0){
      $checked="CHECKED";
    } 
    //Gray out rooms that are not available for booking
    $style="";
    if ($v["active"]==0){
      $style="color:gray;";
    }   
    $t.=("<input class='display_room_checkbox' id='display_room_".$v["id"]."'type='checkbox' $checked /><span style='$style'>".$v["descriptor"]."</span><br/>");
    //Output javascript to link click event to the ajax script that saves user preference
    $s.=("
              $('#display_room_".$v["id"]."').click(function(){
                set_bool_pref('display_room_".$v["id"]."');
              });    
    ");
  }
  //Wrap HTML with div
  $t=("<div id=\"room_selection\"><h4>Display these rooms:</h4>$t</div>");

  //Produce time selection div
  $labels=array();
  $labels[1]="night (12am-8am)";
  $labels[2]="daytime (8am-10pm)";
  $labels[3]="late evening (10pm-12am)";
  for($i=1;$i<=3;$i++){
    //Retrieve previously saved setting
    $checked="";
    if ($upref->read_pref($a->csid,"display_timespan_$i")>0){
      $checked="CHECKED";
    }    
    $u.="<input id='display_timespan_$i' type='checkbox' $checked /> ".$labels[$i]."<br/>";
    $s.="
          $('#display_timespan_$i').click(function(){
            set_bool_pref('display_timespan_$i');
          });    
    ";
  }  
  //Wrap HTML with div  
  $u=("<div id=\"time_selection\"><h4>Display these times:</h4>$u</div>");

  //JQuery for room and time selections (ajax to set user preference)
  $p->jquery($s);
    
  //Wrapper for selection divs
  $p->p("<div id='selection_divs'>$u $t</div>");

  //Wrapper for actual booking calendar
  $p->p("<div id=\"dest_element\">Init...</div>");
  
  //Trigger the initial ajax-load
  $p->jquery("init_date_picker();");
  $p->jquery("init_dest_element();");
  $p->jquery("set_disabled_checkboxes();");

  //Timer for periodic refresh 
  $p->jquery("var interval = setInterval(function(){
                refresh_date_picker();
                init_dest_element(); 
              },60000);");
?>
<?php

  require_once "../lib/framework.php";

  //This script displays the room bookings on a day grid, or edit/create dialogue for bookings, or executes bookings/updates
  
  if ($_GET["action"]==""){
    //We have in $_GET["calling_service"] we have the service ID for the main room booking site (room_booking.php)  
    //The time is to be read from user_prefs -> calling_service,"current timestamp"
    $upref = new cw_User_preferences($d,$_SESSION["person_id"]);
    $current_timestamp=$upref->read_pref($_GET["calling_service"],"current_timestamp");
    //Find out what range of day hours is requested
    $t1=$upref->read_pref($_GET["calling_service"],"display_timespan_1");
    $t2=$upref->read_pref($_GET["calling_service"],"display_timespan_2");
    $t3=$upref->read_pref($_GET["calling_service"],"display_timespan_3");
    $start_hr=0;
    if ($t1>0) {
      $start_hr=0;
    } elseif ($t2>0) {
      $start_hr=8;
    } elseif ($t3>0) {
      $start_hr=22;
    }
    $end_hr=0;
    if ($t3>0) {
      $end_hr=24;
    } elseif ($t2>0) {
      $end_hr=22;
    } elseif ($t1>0) {
      $end_hr=8;
    }
    //Create room object to obtain names
    $rooms = new cw_Rooms($d);
    //Need access to preferences to find out which rooms to display
    $upref = new cw_User_preferences($d,$_SESSION["person_id"]);  
    $rooms_with_display_pref=$upref->read_multiple_prefs($_GET["calling_service"],"display_room_%");
    $rooms_to_display=array();
    foreach($rooms_with_display_pref as $k=>$v){
      //$k has the roomnames as in the pref name (e.g. 'display_room_4'), but only those that have $v==1 are to be displayed
      if ($v==1){
        //cut off the beginning of the string so as to be left only with the room id
        $room_id=substr($k,13);
        //Add to array if room (still) exists
        if ($rooms->room_id_exists($room_id)){
          $rooms_to_display[]=$room_id;            
        }
      }
    }   
    //Actual booking display is handled by class cw_Display_room_bookings b/c it needs access to a bunch of other data
    $display = new cw_Display_room_bookings($d,$a);
    //Output the js function that calls the modal window for editing or creating bookings
    echo $display->output_js(); 
    //Show a day column for each selected room  
    foreach($rooms_to_display as $v){
      echo $display->display_day_column($current_timestamp,$v,$start_hr,$end_hr,$rooms->get_roomdescriptor($v));
    }
  
  } elseif ($_GET["action"]=="display_edit_dialogue") {
  
    //Obtain booking details
    $room_bookings=new cw_Room_bookings($d);
    $e=$room_bookings->get_booking($_GET["id"]);
    if (!is_array($e)){
      //Booking didn't exist (so we are in create mode)
      $e=array();
      $e["room_id"]=$_GET["room_id"];
      $e["timestamp"]=$_GET["timestamp"];
      $e["duration"]=HOUR; //Replace this with appropriate user preference
      $header="Create new room booking";
    } else {
      $header="Edit room booking";    
    }
    //Make room object to get roomname
    $rooms=new cw_Rooms($d);
    $room=$rooms->get_room_record($e["room_id"]);    
    //Preselections for am/pm - das ist echt zum Kotzen!
    $f_am_selected="";
    $f_pm_selected="";
    if (date("a",$e["timestamp"])=="am"){
      $f_am_selected="SELECTED";
    } else {    
      $f_pm_selected="SELECTED";
    }
    $u_am_selected="";
    $u_pm_selected="";
    if (date("a",$e["timestamp"]+$e["duration"])=="am"){
      $u_am_selected="SELECTED";
    } else {    
      $u_pm_selected="SELECTED";
    }
    $slider_init_val=($e["duration"]/HOUR*4);
    //Owner?
    if ($e["owner"]>0){
      $owner=$a->personal_records->get_name_first_last($e["owner"])." (#".$e["owner"].")";
    } else {
      $owner=$e["other_owner"];
    }
    
    $t="<div class='modal_head'>$header</div>";
    $t.="<div class='modal_body'>
          <table style='width:100%'>
            <tr><td colspan='2'>Room: <select id='rooms' style='width:300px;'></select></td></tr>
            <tr>
              <td>
                From:<br/>
                Date (dd&#47;mm&#47;yyyy):
                <input type='text' id='fd' style='width:30px;' value='".date("j",$e["timestamp"])."' />&#47;
                <input type='text' id='fm' style='width:30px;' value='".date("n",$e["timestamp"])."' />&#47;
                <input type='text' id='fy' style='width:50px;' value='".date("Y",$e["timestamp"])."' />
              </td>
              <td>
                <br/>
                Time (hh:mm):
                <input type='text' id='fh' style='width:30px;' value='".date("h",$e["timestamp"])."' />:
                <input type='text' id='fmn' style='width:30px;' value='".date("i",$e["timestamp"])."' />
                <select id='fap' style='padding:0px;border:none;background:white;'><option value='am' $f_am_selected>am</option><option value='pm' $f_pm_selected>pm</option></select>
              </td>
            </tr>
            <tr>
              <td>
                Until:<br/>
                Date (dd&#47;mm&#47;yyyy):
                <input type='text' id='ud' style='width:30px;' value='".date("j",$e["timestamp"]+$e["duration"])."' />&#47;
                <input type='text' id='um' style='width:30px;' value='".date("n",$e["timestamp"]+$e["duration"])."' />&#47;
                <input type='text' id='uy' style='width:50px;' value='".date("Y",$e["timestamp"]+$e["duration"])."' />
              </td>
              <td>
                <br/>
                Time (hh:mm):
                <input type='text' id='uh' style='width:30px;' value='".date("h",$e["timestamp"]+$e["duration"])."' />:
                <input type='text' id='umn' style='width:30px;' value='".date("i",$e["timestamp"]+$e["duration"])."' />
                <select id='uap' style='padding:0px;border:none;background:white;'><option value='am' $u_am_selected>am</option><option value='pm' $u_pm_selected>pm</option></select>
              </td>
            </tr>
            <tr>
              <td colspan='2'>
                <div id='slider' style='width:600px;margin-left:20px;margin-top:20px;'></div>
                <script type='text/javascript'>
                  function zerofill(s,length){
                    var n='';
                    n+=s;
                    while (n.length<length){
                      n='0'+n;
                    }
                    return n;
                  }
                
                  $('#slider').slider();
                  $('#slider').slider('option','max','40'); //Set the slider to 40 increments (a 15 min)
                  $('#slider').slider('value',$slider_init_val); //Set the slider to initial position according to existing booking
                  $('#slider').slider({
                    slide: function(event,ui){
                      var fhr=$('#fh').val();
                      var fap=$('#fap').val(); //am or pm?
                      //12:xx am is 00:xx in 24hr system
                      if ((fap=='am') && (fhr==12)){
                        fhr=0;
                      }
                      //01:xx pm - 11:59pm is 13:xx - 23:xx                      
                      if ((fap=='pm') && (fhr<12)){
                        fhr=parseInt(fhr,10)+12; //The 2nd param for parseInt is critical for '08' and '09'
                      }
                      
                      from = new Date ($('#fy').val(),$('#fm').val()-1,$('#fd').val(),fhr,$('#fmn').val(),0,0); //The beginning of the booking
                      until = new Date(from.getTime()+(ui.value/4)*(1000*60*60)); //Add to the from value slider-pos/4*1hr
                                            
                      $('#uy').val(until.getFullYear());
                      $('#um').val(zerofill(until.getMonth()+1,1));
                      $('#ud').val(zerofill(until.getDate(),1));
                      if (until.getHours()>11){
                        var add=0;
                        if (until.getHours()>12){
                          add=-12;                        
                        }
                        $('#uh').val(zerofill(until.getHours()+add,2));
                        $('#uap').val('pm'); 
                      } else {
                        var add=0;
                        if (until.getHours()==0){
                          add=12;                        
                        }                        
                        $('#uh').val(zerofill(until.getHours()+add,2));
                        $('#uap').val('am');                         
                      }
                      $('#umn').val(zerofill(until.getMinutes(),2));
                                        
                    }
                  });
                  
                  //Populate the room selector
                  $.post('".CW_AJAX_DB."', { query:'SELECT descriptor,id,room_no FROM rooms' },function(rtn){
                    var json = eval('(' + rtn + ')'); //Create JSON object from returned string   
                    var sel_html='';
                    $.each(json,function(id,r){
                      var selected='';
                      if (r.id=='".$e["room_id"]."'){ //Mark the selected item
                        selected='SELECTED';                        
                      }
                      sel_html+='<option value=\"' + r.id + '\" ' + selected + '>' + r.descriptor +'</option>';                      
                              
                    });
                     $('#rooms').html(sel_html);      
                  });
                  
                  
                </script>
                <p>(Slide to adjust the length of the booking period)</p>                           
              </td>
            </tr>
            <tr>
              <td colspan='2'>
                Person in charge (if booking on someone else's behalf)
                <input type='text' id='owner' value='$owner' />
              </td>
            </tr>
            <tr>
              <td colspan='2'>
                <p>Notes/comments</p>
                <textarea id='note'>".$e["note"]."</textarea>
              </td>
            </tr>
            <tr>
              <td>
                <input class='button' type='button' id='save' value='save and exit'/>
                <input class='button' type='button' id='cancel' value='cancel'/>
                <script type='text/javascript'>
                  //Cancel on esc
                  $(document).keyup(function(e){
                    if (e.keyCode==27){
                      $('#cancel').click();
                    }
                  });                  
                
                  $('#save').click(function(){
                  
                    $.post('".CW_AJAX."ajax_room_booking.php?action=save_booking', {
                                    fy:$('#fy').val(),
                                    fm:$('#fm').val(),
                                    fd:$('#fd').val(),
                                    fh:$('#fh').val(),
                                    fmn:$('#fmn').val(),
                                    fap:$('#fap').val(),
                                    uy:$('#uy').val(),
                                    um:$('#um').val(),
                                    ud:$('#ud').val(),
                                    uh:$('#uh').val(),
                                    umn:$('#umn').val(),
                                    uap:$('#uap').val(),
                                    note:$('#note').val(),
                                    owner:$('#owner').val(),
                                    room_id:$('#rooms option:selected').val(),
                                    booking_id:$('#booking_id').val()
                                    
                            },function(rtn){
                      if (rtn=='OK'){                      
                        //Success. Leave modal. Refresh dest element.
                        init_dest_element();                        
                        $('#main_content').fadeTo(500,1);
                        $('#modal').hide(200);
                      } else {
                        //Error during update. Show message returned by ajax
                        alert (rtn);
                      }                      
                    });
                  });
                  $('#cancel').click(function(){
                    //Leave modal
                    $('#main_content').fadeTo(500,1);
                    $('#modal').hide(200);
                  });

              		function split( val ) {
              			return val.split( /,\s*/ );
              		}
              		function extractLast( term ) {
              			return split( term ).pop();
              		}

                  //Booking owner autocomplete
                  $('#owner')
              			// don't navigate away from the field on tab when selecting an item
              			.bind( 'keydown', function( event ) {
              				if ( event.keyCode === $.ui.keyCode.TAB &&
              						$( this ).data( 'autocomplete' ).menu.active ) {
              					event.preventDefault();
              				}
              			})
              			.autocomplete({
              				source: function( request, response ) {
              					$.getJSON( '".CW_AJAX."ajax_room_booking.php?action=acomp_names', {
              						term: extractLast( request.term )
              					}, response );
              				},
              				search: function() {
              					// custom minLength
              					var term = extractLast( this.value );
              					if ( term.length < 2 ) {
              						return false;
              					}
              				},
                      autoFocus:true
              			});



                </script>
              </td>
              <td>
                <input type='hidden' id='booking_id' value='".$e["id"]."'/>
              </td>
            </tr>
          </table>
    
    
        </div>";
    
    echo $t;  
  
  
  
  } elseif ($_GET["action"]=="save_booking"){
      //Prepare timestamps&duration
      $fh=$_POST["fh"];
      if ($_POST["fap"]=="am"){
        if ($fh==12){
          $fh=0; //12am = 0hr
        } 
      } else {
        if($fh==12){
          //12pm is 12 - no change  
        } else {
          $fh+=12; //If pm but not 12, add 12          
        }
      }
      $timestamp=mktime($fh,$_POST["fmn"],0,$_POST["fm"],$_POST["fd"],$_POST["fy"]);
      //Prepare timestamps&duration
      $uh=$_POST["uh"];
      if ($_POST["uap"]=="am"){
        if ($uh==12){
          $uh=0; //12am = 0hr
        } 
      } else {
        if($uh==12){
          //12pm is 12 - no change  
        } else {
          $uh+=12; //If pm but not 12, add 12          
        }
      }
      $timestamp2=mktime($uh,$_POST["umn"],0,$_POST["um"],$_POST["ud"],$_POST["uy"]);
      $duration=$timestamp2-$timestamp;
      
      //Make object for room bookings      
      $room_bookings=new cw_Room_bookings($d);

      //Find out owner ($_POST["owner"] has a string of the form Johannes Weber (#1))
      $owner_has_id=(!(strpos($_POST["owner"],'#')===false)); //If the # doesn't exist, we don't have a person_id
      if ($owner_has_id){
        $x=substr($_POST["owner"],strpos($_POST["owner"],'#')+1); //Copy from after the hash until the closing bracket
        $owner_id=substr($x,0,strpos($x,')'));
      } else {
        $owner_id=0;
      }
      if (($owner_id==0) && (strlen($_POST["owner"])>0)){
        //Could not identify owner in db, but there still is a string. So pass original string to booking method, it'll save it in field "other_owner"
        $owner_id=$_POST["owner"];
      }

      //If $_POST["booking_id"] is 0, then create a new booking. Otherwise update.      
      if($_POST["booking_id"]==0){
        //New booking             
        /*
          Booking may be created if $a->csps>=CW_E 
        */

        if ($a->csps>=CW_E){
          //Must ensure that room in question is available for booking
          $rooms = new cw_Rooms($d);
          if ($rooms->is_active($_POST["room_id"])){
            $result=$room_bookings->add_booking(0,$_POST["room_id"],$timestamp,$duration,$a->cuid,$_POST["note"],1,$owner_id);
            if ($result){
              echo "OK"; //confirmation for calling script
            } else {
              echo "The booking could not be created. There might be a conflict with an existing booking."; //Js will alert this
            }         
          } else {
            echo "Sorry, this room is not currently available for booking. Cannot complete request.";
          }
        } else {
          echo "Insufficient privileges";
        }          
      } else {
        //Update existing booking
        /*
          Booking may be edited if
            booking["owner"]==$_GET["owner"]==$a->cuid AND $a->csps>=CW_E        
        */
        //To verify authorization to update the booking we must obtain the existing booking record to compare the owner
        $rb=$room_bookings->get_booking($_POST["booking_id"]);
        if ((((($rb["owner"]==$_POST["owner"]) || ($_POST["owner"]=="")) && ($rb["owner"]==$a->cuid)) && ($a->csps>=CW_E)) || ($a->csps>=CW_A)){
          //Must ensure that room in question is available for booking (or no change to room_id is made: we do allow editing of extant bookings)
          $rooms = new cw_Rooms($d);
          if ($rooms->is_active($_POST["room_id"]) || ($rb["room_id"]==$_POST["room_id"])){
            $result=$room_bookings->update_booking($_POST["booking_id"],$_POST["room_id"],$timestamp,$duration,$a->cuid,$_POST["note"],1,$owner_id);
            if ($result){
              echo "OK"; //confirmation for calling script
            } else {
              echo "The booking could not be updated. There might be a conflict with another booking."; //Js will alert this
            }
          } else {
            echo "Cannot switch the booking to the selected room because it is not currently available for booking. Cannot complete request.";
          }
        } else {
          echo "Insufficient privileges: need administrative rights to assign the booking to someone else.";
        }        
      }  
  } elseif ($_GET["action"]=="acomp_names"){
    echo $a->personal_records->get_name_autocomplete_suggestions($_GET["term"]);
  } else {
    echo "INVALID REQUEST";
  }
  
  $p->nodisplay=true;
?>
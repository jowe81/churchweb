<?php

  class cw_Display_service_planning {
  
    public $eh; //passed in (event_handling object)
    private $d; //Database access
    private $auth;
    
    function __construct(cw_Db $d,cw_Event_handling $eh,cw_Auth $auth){
      $this->d=$d; //Database access
      $this->eh=$eh;
      $this->auth=$auth;
    }
  
  
    function display_service_list_filter_interface($timestamp1=0,$timestamp2=CW_BIGGEST_TIMESTAMP){
      $query="
        SELECT DISTINCT
          church_service_templates.id,church_service_templates.service_name
        FROM
          church_service_templates
          LEFT JOIN
          church_services ON church_service_templates.id=church_services.template_id
          LEFT JOIN
          events ON events.church_service=church_services.id
        WHERE
          events.timestamp>$timestamp1
        AND
          events.timestamp<$timestamp2
        ORDER BY church_service_templates.service_name;
      ";
      if ($res=$this->d->q($query)){
        $t="";
        $cnt=0;
        $upref=new cw_User_preferences($this->d,$this->auth->cuid);
        $template_ids="";      
        while ($r=$res->fetch_assoc()){
          $cnt++;
          ($upref->read_pref($this->auth->csid,"SERVICES_FILTER_HIDE_".$r["id"])==1) ? $checked="" : $checked="CHECKED";
          $t.="
            <input type='checkbox' class='cb_service_type' id='cb".$r["id"]."' $checked/> ".$r["service_name"]."<br/>
          ";
          $template_ids.=$r["id"].",";
        }
        if ($cnt>1){
          $t="
            <a href='' id='clear_filter'>show all</a> <span class='small_note'>|</span> <a href='' id='filter_all'>hide all</a> <br/>$t          
          ";
        }
        if ($t!=""){
          $t="
            <div id='service_list_filter'>
              <span style='color:gray;font-weight:bold;'>Show these services:</span><br/>
              $t
            </div>
            <script type='text/javascript'>
            
              $('#clear_filter').click(function(e){
                e.preventDefault();
                var template_id=$(this).attr('id').substr(2);
                $.post('".CW_AJAX."ajax_service_planning.php?action=set_service_filter&do=clear_filter&template_ids=$template_ids',{},function(rtn){
                  if (rtn!='OK'){
                   alert(rtn);
                  }
                  reload_services_list($timestamp1);
                });        
              });
              
              $('#filter_all').click(function(e){
                e.preventDefault();
                var template_id=$(this).attr('id').substr(2);
                $.post('".CW_AJAX."ajax_service_planning.php?action=set_service_filter&do=filter_all&template_ids=$template_ids',{},function(rtn){
                  if (rtn!='OK'){
                   alert(rtn);
                  }
                  reload_services_list($timestamp1);
                });        
              });

              $('.cb_service_type').click(function(){
                var template_id=$(this).attr('id').substr(2);
                $.post('".CW_AJAX."ajax_service_planning.php?action=set_service_filter&do=toggle_template&template_id='+template_id,{},function(rtn){
                  if (rtn!='OK'){
                   alert(rtn);
                  }
                  reload_services_list($timestamp1);
                });        
              });
            </script>
          ";
        }
        return $t;
      }
      return false;
    }
  
    //If !$rehearsal_id, schedule new practice else edit practice
    function display_edit_rehearsal_dialogue($service_id,$rehearsal_id=0){
      //Get church service object
      $cs=new cw_Church_service($this->eh,$service_id);
      if ($rehearsal_id>0){
        $rehearsal_record=$this->eh->get_rehearsals_for_service($service_id,$rehearsal_id);
        $title="Edit rehearsal";
        //Get the select options for the rooms to select from - since this is a preextant rehearsal, preselected earlier selected location
        $room_options=$this->eh->get_active_rooms_for_select("name",$rehearsal_record["bookings"][0]["room_id"]);
      } else {
        $rehearsal_record=array();
        $rehearsal_record["id"]=0; //Important to avoid js syntax error on send
        $title="Schedule a rehearsal";
        //Get the select options for the rooms to select from - with new rehearsal preselect default choice
        $room_options=$this->eh->get_active_rooms_for_select("name",$this->eh->rooms->get_id(CW_SERVICE_PLANNING_DEFAULT_PRACTICE_ROOM_NAME));
      }
      //Get the select options with the timestamps of the favorite rehearsal times
      $fav_practices=cw_Event_positions::get_favorite_practice_times_for_select($cs->event_records[$cs->no_service_times-1]["timestamp"],$rehearsal_record["timestamp"]);
      //Get the people who can be part of the practice (those that have positions in the service)      
      $people_sel="";
      //Get an array of position_type records
      if ($r=$this->eh->event_positions->get_position_types()){
        foreach ($r as $pos_type){
          //Get array of ids of people who are occupying at least one position within the current $pos_type
          if ($r1=$this->eh->event_positions->get_people_in_service($_GET["service_id"],$pos_type["id"])){
            if (sizeof($r1)>0){
              $people_sel.="<div id='ptcont_p".$pos_type["id"]."' class='rehearsal_sched_pos_type'><p class='postype_link' id='p".$pos_type["id"]."'>".$pos_type["title"]."</p>";      
              foreach ($r1 as $pos_rec){
                //Markup checkbox for people we have no email for
                $cb="";
                $highlight="";
                if ($this->auth->get_cw_comm_email_for_user($pos_rec["person_id"])==""){
                  //$cb="disabled='disabled'";
                  $highlight="color:red";
                }
                //Pre-check box if person has rehearsal_participant record for this position already
                $checked="";
                if ($this->eh->position_has_rehearsal_participant_record($pos_rec["id"],$rehearsal_record["id"])){
                  $checked="checked='CHECKED'";
                }
                //Get position name to write behind person's name
                $pos_name=$this->eh->event_positions->get_position_title($pos_rec["pos_id"]);
                $people_sel.="<div><input $checked id='".$pos_rec["id"]."' type='checkbox' $cb/> <span class='data' style='$highlight'>".$this->auth->personal_records->get_name_first_last($pos_rec["person_id"])."</span> <span class='gray'>- $pos_name</span></div>";
              }
              $people_sel.="</div>";                                                                                            
            }
          }      
        }    
      }
      
      
      $t="
        <div class='modal_head'>$title</div>
        
        <div class='modal_body' style='height:550px;'>
          <div>
            <div style='height:160px;width:940px;border-bottom:1px solid #DDD;'>
              <div style='float:left;'>
                <div>
                  When would you like to practice?
                </div>
                <select style='width:300px;' id='fav_practice_times'><option value=''>(select a favorite date and time...)</option>".$fav_practices."</select>
                <div style='padding-top:25px;'>
                  <div class='gray'>
                    Date:
                    <span id='selected_date' class='data' style='font-weight:bold;'>
                    </span>
                  </div>
                  <div class='gray'>
                    Time:
                    <span id='selected_time' class='data' style='font-weight:bold;'>
                    </span>
                  </div>
                  <div class='gray' style='padding-top:5px;'>
                    Duration:
                    <span id='selected_duration' class='gray' style='font-weight:bold;'>
                    </span>
                  </div>
                  <input type='hidden' id='day'/>
                  <input type='hidden' id='month'/>
                  <input type='hidden' id='year'/>
                  <input type='hidden' id='hr'/>
                  <input type='hidden' id='min'/>
                </div>
              </div>
              <div style='float:left;margin-left:10px;'>
                <div>
                  Slide to adjust date:
                </div>
                <div id='slider_d' style='width:580px;margin-left:20px;margin-top:10px;margin-bottom:10px;'></div>
                <div>
                  Slide to adjust time:
                </div>
                <div id='slider_t' style='width:580px;margin-left:20px;margin-top:10px;margin-bottom:10px;'></div>
                <div>
                  Slide to adjust duration:
                </div>
                <div id='slider_l' style='width:580px;margin-left:20px;margin-top:10px;margin-bottom:10px;'></div>
              </div>
            </div>
  
            
            <div style='padding-top:5px;padding-bottom:5px;height:60px;width:940px;border-bottom:1px solid #DDD;'>
              <div style='width:315px;height:60px;float:left;'>
                <div>
                  Where would you like to practice?
                </div>
                <select id='room_selector' style='width:300px;'>
                  <option value=''>(select a room...)</option>
                  $room_options
                </select>
              </div>
              <div id='roomname_div' style='width:400px;height:60px;float:left;'></div>
            <div>
            
            <div style='background:white;height:260px;width:940px;border-bottom:1px solid #DDD;margin-top:15px;display:inline-block;'>
              Who is invited to the practice?  <span class='small_note gray'>(Note: names in red need manual invitation!)</span>
              <div id='rehearsal_sched_pos_type_container'>
                $people_sel
              </div>
            </div>
            
            <div id='datetime' style='width:930px;height:42px;padding:5px;'>
              <div style='float:right;'>
                <input class='button' type='button' id='schedule_practice' value='schedule rehearsal'>
              </div>
            </div>
            
            
            <script type='text/javascript'>
              
              //Send request
              $('#schedule_practice').click(function(){
                //Get timestamp
                var d=new Date($('#year').val(),($('#month').val()-1),$('#day').val(),$('#hr').val(),$('#min').val());
                var timestamp=Math.round(d.getTime()/1000);
                //Get duration
                var duration=(($('#slider_l').slider('value')+1)*15*60);
                //Get room id
                var room_id=$('#room_selector').val();
                //Get room name (just in case offsite room has been given)
                var room_name=$('#room_name').val();
                //Get list of people
                //Loop over selected checkboxes
                var jsonobj={};
                $('#rehearsal_sched_pos_type_container div input:checked').each(function(){
                  jsonobj[ 'posrec_' + $(this).attr('id') ]=1;                              
                });
                //If rehearsal_id is given, we are updating
                var rehearsal_id=".$rehearsal_record["id"].";
                //
                $.post('".CW_AJAX."ajax_service_planning.php?action=schedule_practice&service_id=".$_GET["service_id"]."&timestamp='+timestamp+'&room_id='+room_id+'&room_name='+room_name+'&duration='+duration+'&rehearsal_id='+rehearsal_id, jsonobj, function(rtn){
                  if (rtn!='OK'){
                    alert(rtn);
                    //close_modal();
                  } else {
                    close_modal();
                  }
                });
              });
  
              $('#room_selector').change(function(){
                if ($(this).val()=='offsite'){
                  $('#roomname_div').load('".CW_AJAX."ajax_service_planning.php?action=get_roomname_div');                  
                } else {
                  $('#roomname_div').html('');
                }
              });
  
              //On click on category caption check all checkboxes below it          
              $('.postype_link').click(function(){
                $('#ptcont_'+$(this).attr('id')+' input').each(function(){
                  if (!$(this).attr('disabled')){
                    $(this).attr('checked','checked');
                  }
                });
              });
            
              $('#fav_practice_times').change(function(){
                if ($(this).val()!=0){
                  d=new Date($(this).val()*1000);
                  set_date(d);
                  set_time(d);
                  set_sliders(d);              
                }
              });
                          
              //Set date for display
              function set_date(d){
                var months=['January','February','March','April','May','June','July','August','September','October','November','December'];
                var days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                $('#selected_date').html(days[d.getDay()]+', '+months[d.getMonth()]+' '+d.getDate());
                //Hidden fields
                $('#day').val(d.getDate());
                $('#month').val(d.getMonth()+1);
                $('#year').val(d.getFullYear());
              }
              
              //Set time for display
              function set_time(d){
                var hr=d.getHours();
                var ap='am';
                if (hr>12){
                  hr=hr-12;
                  ap='pm';
                }
                $('#selected_time').html(zerofill(hr,2)+':'+zerofill(d.getMinutes(),2)+ap);
                //Hidden fields
                $('#hr').val(d.getHours());
                $('#min').val(d.getMinutes());
              }
            
              //Set sliders to preselected date
              function set_sliders(d){
                //Need to know number of days d is away from service start
                var service_ts = new Date (".($cs->event_records[$cs->no_service_times-1]["timestamp"]*1000).");
                var days_out= Math.round( (service_ts.getTime()-d.getTime()) /1000/60/60/24 );
                $('#slider_d').slider('value',days_out);
                
                var hour_pos=( (d.getHours()*4) + ( Math.round(d.getMinutes()/15) ));
                $('#slider_t').slider('value',hour_pos);                                           
              }
              
              //Position the selectbox to the dummy entry
              function set_fav_times_to_nil(){
                $('#fav_practice_times option').first().attr('selected','selected');            
              }
            
              //Interprets a slider position to a duration
              function set_duration_display(slider_value){
                var real=slider_value+1;
                var hr=Math.floor(real/4);
                var min=(real-(hr*4))*15;
                min = zerofill(min,2);
                if (min==0){
                  if (hr==1){
                    $('#selected_duration').html('an hour');                
                  } else {
                    $('#selected_duration').html(hr+' hours');                                  
                  }
                } else {
                  if (hr==1){
                    $('#selected_duration').html('an hour and '+min+' minutes');                
                  }
                  if (hr>1) {
                    $('#selected_duration').html(hr+' hours and '+min+' minutes');                
                  }
                  if (hr==0){
                    $('#selected_duration').html(min+' minutes');                                  
                  }
                }            
              }
            
              $('#slider_d').slider();
              //How many days is the service away?
              var service_ts = new Date (".($cs->event_records[$cs->no_service_times-1]["timestamp"]*1000).");
              var now = new Date();
              var days_out= Math.round( (service_ts.getTime()-now.getTime()) /1000/60/60/24 );
              $('#slider_d').slider('option','max',days_out); //Set the slider to as many increments as the service is away
              $('#slider_d').slider('value',0); //Set the slider to initial position 
              $('#slider_d').slider({
                slide: function(event,ui){
                  var service_ts = new Date (".($cs->event_records[$cs->no_service_times-1]["timestamp"]*1000).");
                  var adjusted_ts = new Date(service_ts.getTime()-(ui.value)*(1000*60*60*24)); //Add to the from value slider-pos/4*1hr
                  set_date(adjusted_ts);
                  set_fav_times_to_nil();
                }
              });
  
              $('#slider_t').slider();
              $('#slider_t').slider('option','max','95'); //Set the slider to 96 increments (quarter hours  of the day)
              $('#slider_t').slider('value',0); //Set the slider to initial position 
              $('#slider_t').slider({
                slide: function(event,ui){
                  time_ts = new Date();
                  time_ts.setHours(0,0,0,0);
                  ts2=new Date(time_ts.getTime()+(ui.value*1000*60*15));
                  set_time(ts2);                  
                  set_fav_times_to_nil();
                }
              });
  
              $('#slider_l').slider();
              $('#slider_l').slider('option','max','46'); //Set the slider to 96 increments (quarter hours for 12h)
              $('#slider_l').slider('value',".(floor(CW_DEFAULT_PRACTICE_DURATION/60/15)-1)."); //Set the slider to initial position 
              $('#slider_l').slider({
                slide: function(event,ui){
                  set_duration_display(ui.value);
                }
              });
              
              //Init duration display
              set_duration_display(".(floor(CW_DEFAULT_PRACTICE_DURATION/60/15)-1).");
              
              $('#fav_practice_times').focus();
              
            </script>
  
          </div>
        </div>
        
      ";  
      //Js commands for preset (edit mode)
      if ($rehearsal_id!=0){
        $t.="
          <script type='text/javascript'>
            //Set date sliders
            d=new Date(".$rehearsal_record["timestamp"]."*1000);
            set_date(d);
            set_time(d);
            set_sliders(d);
            //Duration              
            set_duration_display(".(floor($rehearsal_record["duration"]/60/15)-1).");
            $('#slider_l').slider('value',".(floor($rehearsal_record["duration"]/60/15)-1).");
            //Room if offsite
            if ($('#room_selector').val()=='offsite'){
                  $('#roomname_div').load('".CW_AJAX."ajax_service_planning.php?action=get_roomname_div&value=' +escape('".$this->eh->rooms->get_roomname($rehearsal_record["bookings"][0]["room_id"])."'));                              
            }
          </script>
        ";
      }
      return $t;
    
          
    }

    function display_service_order($service_id,$show_element_list){
      $element_types=$this->eh->church_services->get_service_element_types_for_ul();
      $elements=$this->eh->get_service_elements_for_ul($service_id);
      if (empty($elements)){
        $elements="<li id='placeholder' class=\"ui-state-default\">Drag and drop service elements here</li>";
      }
      if ($show_element_list=='auto'){
        //Auto: more or less than 10 elements?
        if (substr_count($elements,'<li')>10){
          $show_element_list=-1;
        } else {
          $show_element_list=1;
        }
                
      }
      if ($show_element_list>0){
        $el_sel_style='';
        $showhide_title='hide';
      } else {
        $el_sel_style="style='display:none;'";
        $showhide_title='show';      
      }
      
      $t.="
          <div style='position:relative;left:550px;top:-10px;width:100px;text-align:right;' class='small_note'>
            <a id='showhide_elements' href=''>$showhide_title element list</a>
             <span class='gray'>|</span> <img style='height:10px;width:10px;margin:0px;padding:0px;' id='get_help' src='".CW_ROOT_WEB."img/help.gif'/>
          </div>

          <div id='service_order'>
          	
            <ul id='element_selector' $el_sel_style>
              $element_types
            </ul>
            <ul id=\"sortable\">
              $elements
            </ul>
            <p style='height:50px;'></p>
          </div> 
          
          <script type='text/javascript'>
            $('#get_help').click(function(){
              get_help(1);
            });
          
            $('#showhide_elements').click(function(e){
              e.preventDefault();
              if ($('#element_selector').is(':visible')){
                $('#element_selector').hide();
                $(this).text('show element list');
              } else {
                $('#element_selector').show();
                $(this).text('hide element list');              
              }
            })
          
          	$(function() {
          		$( '#sortable' ).sortable({
          			revert: true,
                receive: function(event,ui){
                  $('#placeholder').remove(); //If there was a placeholder, remove it
                  var element_type_id = $(ui.item).attr('id');
                  var element_position = $(this).data().sortable.currentItem.index()+1; //index of new element
                  $(this).data().sortable.currentItem.data('nostop',true); //Set flag to prevent the call of repos_ when stop event is fired right after this                  
                  generate_service_element(element_type_id,element_position,event.ctrlKey,event.altKey); //ctrlKey: edit after generate; altKey: reposition/attach to grp after generate
                },
                stop: function(event,ui){
                  var element_id = $(ui.item).attr('id');
                  var new_element_position=$(this).data().sortable.currentItem.index()+1;
                  //See if this was called after repositioning or after receiving
                  if (!$(ui.item).data('nostop')){
                    repos_service_element(element_id,new_element_position,event.altKey,event.ctrlKey); //When alt key pressed try to attach element to group, when alt+ctrl detach                  
                  } else {
                    $(ui.item).data('nostop',false);                  
                  }
                },
                start: function(event,ui){
                  $(ui.helper).css('background','#FAEAEA');                    
                  $(ui.item).data('noclick',true); //Avoid that the drag is being interpreted as click
                },
                cancel: \".divider_li\"
          		});
          		$( '.element_type' ).draggable({
          			connectToSortable: '#sortable',
          			helper: 'clone',
          			revert: 'invalid',
                start: function(event,ui){
                  
                }
          		}); 
              
          		$( 'ul, li' ).disableSelection();

              //Simple click on an element ->edit it. With shift: delete
              $('.actual_element').click(function(e){
                if (!$(this).data('noclick')){
                  if (!e.shiftKey){
                    edit_service_element($(this).index()+1);                                  
                  } else {
                    delete_service_element($(this).index()+1);                                  
                  }               
                } else {
                  $(this).data('noclick',false);
                }
              });
              
              //On hover, save which element is hovered above
              $('.actual_element').hover(
                function(){                     
                  if (!$('#element_selector').data('dragging')){
                    $('#sortable').data('hover',$(this).attr('id'));                
                  }
                },
                function(){
                  $('#sortable').css('hover','');
                  $(this).css('background',''); //Unmark on leave
                  $('#sortable').data('hover',''); //Mark the element as not hovering anymore                        
                }            
              );
                                        
                            
          	});
          	</script>
               
      ";
    
      return $t;
    }

        
    function display_service_element_edit_dialogue($element_id,$service_id){
      //Get service element record
      $element=$this->eh->church_services->get_service_element_record($element_id);
      //Get element type record
      $element_type=$this->eh->church_services->get_service_element_type_record($element["element_type"]);      
      //Use label if there is one
      empty($element["label"]) ? $label=$element_type["title"] : $label=$element["label"];

      //Get the service leadership people to choose from 
      $cb_options="";
      $service_leadership=$this->eh->event_positions->get_people_in_service($service_id,1); //1 is the id for the service leadership position type

      //$service_leadership has pos_to_services_records
      foreach ($service_leadership as $v){
        if ($v["person_id"]==$element["person_id"]){
          $checked="checked='CHECKED'";
        } else {
          $checked="";
        }
        $cb_options.="<br/><input type='radio' name='person_id' value='".$v["person_id"]."' $checked/> ".$this->auth->personal_records->get_name_first_last($v["person_id"]);      
      }      
      //Is 'other' to be selected?
      $checked="";
      if ($element["person_id"]==0){
        $checked="checked='CHECKED'";      
      }
      $cb_options.="<br/><input type='radio' id='person_id' name='person_id' value='other' $checked/> <span class='gray'>other:</span> <input id='other_person' value='".$element["other_person"]."' type='text' style='width:130px;'/>";
      
      //If this is music item or word item, present appropriate interface
      /*
        if word
          get assoc scripture ref record(s), convert them to strings, and show input
          if sermon
            get assoc sermon record from scripture record(s), and show inputs for title, theme
        if music
          get assoc arrangements record - via arr_to_service_elements
          get song info via arr record
          show inputs
      */
      //
      
      $specific_interface='';
      if ($element_type["meta_type"]=="word"){
        $scripture=$this->eh->sh->scripture_refs->get_scripture_ref_string_for_service_element($element_id);
        if ($element_type["title"]=="Sermon"){
          $sermon=$this->eh->sh->sermons->get_sermon_record_for_service_element($element_id);
          $sermon_details="
            <tr>
              <td>
                Sermon title:  
              </td>
              <td>
                <input type='text' id='sermon_title' style='width:500px;' value=\"".$sermon["title"]."\"/>
              </td>
            </tr>
            <tr>
              <td>
                Sermon abstract: 
              </td>
              <td>
                <textarea id='sermon_abstract' style='width:500px;'>".$sermon["abstract"]."</textarea> 
              </td>
            </tr>
          ";
        }
        $specific_interface.="
            <table>
              <tr>
                <td style='width:205px;'>
                  Scripture reference(s):  
                </td>
                <td>
                  <input type='text' id='scripture_refs' style='width:500px;' value=\"$scripture\"/>
                </td>
              </tr>
              $sermon_details
            </table>          
        ";
        $focus="$('#scripture_refs').focus().select();";
      } elseif ($element_type["meta_type"]=="music"){
        $init_snippet="";
        $atse_id=$this->eh->church_services->get_atse_for_service_element($element_id);
        if ($atse_id>0){
          $init_snippet="
            //Init - possibly need to recall saved music-piece, arrangement and sequence info
            
            function init_recall(){
              //load the piece title to write into autocomplete
              $.post('".CW_AJAX."ajax_service_planning.php?action=get_music_piece_title_via_atse',{ atse_id:$atse_id },function(rtn){
                $('#music_piece').val(rtn);
              });                       
                            
              //load the arrangements, and preselect the saved one - this will trigger loading people and lyrics sequence 
              $('#arrangements').load('".CW_AJAX."ajax_service_planning.php?action=get_arrangements_options_for_music_piece&service_id=$service_id&atse_id=".$atse_id."',function(rtn){
                //preselect first arrangement
                $(this).trigger('change');
              });              
              
            }
            
            init_recall();
          "; 
        } else {
          //When no preselected piece, set focus to #music_piece
          $focus="$('#music_piece').focus();";       
        }
        
        $specific_interface.="
          <div style='width:490px;height:300px;float:left;'>
            1. Select a song or music piece
            <div style='padding-left:2px;' class='small_note'>Type a title or scripture reference <img id='get_help2' src='".CW_ROOT_WEB."img/help.gif'/></div>
            <input type='text' id='music_piece' style='width:468px;'/><br>
            2. Select an arrangement <img id='get_help4' src='".CW_ROOT_WEB."img/help.gif'/><br/>
            <select id='arrangements' style='width:470px;height:55px;font-size:60%;' size='10'></select><br>
            3. Select people<br/>
            <div style='padding-left:2px;' class='small_note'>People you deselect will <u>not</u> get music for this piece</div>
            <div id='select_people_in_service_element' style='width:470px;margin-left:2px;height:117px;overflow-y:scroll;'></div>
          </div>
          <div style='width:686px;height:300px;float:right;'>
            4. Adjust actual lyrics sequence <img id='get_help3' src='".CW_ROOT_WEB."img/help.gif'/>
            <div id='lyrics_sequence_container'>
            </div>
          </div>
          
          <script type='text/javascript'>
            $('#get_help2').click(function(){
              get_help(4);
            });
          
            $('#get_help3').click(function(){
              get_help(5);
            });
          
            $('#get_help4').click(function(){
              get_help(6);
            });

            $('#music_piece').autocomplete({
              source:'".CW_AJAX."ajax_service_planning.php?action=acomp_songs',
              minLength:2,
              select: function(event,ui){
                //Music piece was selected, no load the arrangements we have for that piece in the selectbox
                $('#arrangements').load('".CW_AJAX."ajax_service_planning.php?action=get_arrangements_options_for_music_piece&service_id=$service_id&music_piece=' + ui.item.id,function(rtn){
                  if (rtn=='ERR'){
                    //sync error
                    alert('".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5."');                  
                  } else {
                    //preselect first arrangement
                    $(this).trigger('change');
                    $(this).show();
                  }
                });
              },
              autoFocus:true
            });
            
            $('#arrangements').change(function(){
              //This also creates, if newly chosen, an atse record, and further takes off vocalists in case this is an instrumental arr. Then loads the people involved.
              var arr_id=$(this).val();
              $('#lyrics_sequence_container').load('".CW_AJAX."ajax_service_planning.php?action=get_lyrics_sequence_div&service_id=$service_id&service_element=".$element_id."&arrangement=' + $(this).val(),function(){
                $('#select_people_in_service_element').load('".CW_AJAX."ajax_service_planning.php?action=get_people_on_service_element_checkboxes&service_id=$service_id&service_element_id=$element_id');        
              });
            });
            
            $(document).on('keypress','#arrangements',function(){
              //$('#arrangements').trigger('change'); //Reload arrangement sequence even when selected with arrow keys
            });                                    
            
            $init_snippet;                                 
                                     
          </script>
        ";
      
      }

      
      $duration_and_note="";
      $person_selector="
        <div style='width:220px;float:left;height:200px;padding:3px;'>
          Person in charge:
          <div class='data'>
            $cb_options
          </div>
        </div>        
      ";
	  $attached_files="";
	  $background_media="";     
	      
      //Only display duration and note, background and attached files, when element is not a group header     
      if ($element["element_type"]>0) {
	    $attached_files="
	      <div style='width:220px;float:left;height:200px;padding:3px;'>
	        Attached files:
	        <div class='small_note'>
	        	<a href='' id='add_file'>upload a file</a> | <a href='' id='remove_selected_file'>remove selected file</a>
	    		<div style='display:none;'>
 					<form id='upload_form' target='invisible_iframe' action=\"".CW_AJAX."ajax_service_planning.php?action=upload_file_and_link_to_service_element&element_id=$element_id\" method=\"POST\" enctype=\"multipart/form-data\">
						<input id='filesel' name='filesel' type='file' />
					</form>
					<iframe name='invisible_iframe' id='invisible_iframe' style='display:none;'></iframe>
	        	</div>
	        </div>
	        <div class='data'>
	          <select id='service_element_files' size=\"10\"></select>
	        </div>
	      </div>              		  

	      <script>
	      
			function reload_file_select(){
				$('#service_element_files').load('".CW_AJAX."ajax_service_planning.php?action=get_files_for_service_element_for_select&element_id=$element_id',function(res){
      			});
			}	      
	      
			reload_file_select();
			
	      	$('#add_file').click(function(e){
	      		e.preventDefault();
	      		$('#filesel').click();
      		});
      		
      		
	 		$('#filesel').change(function(){
	 			$('#upload_form').submit();
	 			show_please_wait('Please wait while this file uploads...');
			});
	 			
	 		$('#invisible_iframe').load(function(e){
	 			//Have to check if the event was triggered because of an upload, or initially after page generation:
	 			if (e.target.contentDocument.URL.lastIndexOf('ajax_service_planning.php?action=upload_file_and_link_to_service_element&element_id=$element_id')>0){
	 				//Yes, file upload took place
	 				hide_please_wait();
	 				reload_file_select();
	 				$('#filesel').val('');
				}
			});
      		
	      	$('#remove_selected_file').click(function(e){
	      		e.preventDefault();
	      		var file_id=$('#service_element_files').val();
	      		$.get('".CW_AJAX."ajax_service_planning.php?action=delete_file_from_service_element&element_id=$element_id&file_id='+file_id,function(res){
					if (res!=''){
						alert(res);
					}	      		
					reload_file_select();	
      			});
      		});
			
      		$('#service_element_files').dblclick(function(){
	      		var file_id=$(this).val();
	      		var url='".CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b='+file_id;
	      		window.location.href=url;
      		});
      		
	      </script>
	    ";
      	if ($this->auth->have_mediabase_permission()){
	      	$bg_file_id=$this->eh->church_services->get_service_element_background($element_id, $this->auth);
	      	$last_modified_name=$this->auth->personal_records->get_name_first_last($element["background_modified_by"]);
	      	$last_modified="";
	      	if ($bg_file_id>0){
	      	    if (!empty($last_modified_name)){
	      			$last_modified="<div class='overwrite'>last modified by ".$last_modified_name."</div>";
	      		}
	      		$bg_preview_src=CW_AJAX."ajax_mediabase.php?action=get_downscaled_projector_preview&lib_id=".$bg_file_id."&max_width=190&max_height=150";
	      		$bg_preview_img="<img id='bg_preview_img' src=\"$bg_preview_src\" alt=\"loading background preview...\">$last_modified";      			 
	      	} else {
	      	    if (!empty($last_modified_name)){
	      			$last_modified="(last modified by ".$last_modified_name.")</div>";
	      		}
	      		$bg_preview_img="<span class='small_note gray'>(no background has been associated)<br>$last_modified</span>";
	      	}
	    } else {
	      	$bg_preview_img="<span class='small_note'>Sorry, you do not have sufficient privileges for the mediabase. If you think this is wrong, contact your administrator.</span>";
	    }
	      
	    $background_media="
	        <div style='width:220px;float:left;height:200px;padding:3px;'>
	          Background preview:
	          <div class='small_note' style='margin-bottom:3px;'><a href='mediabase.php?action=select_bg_media_for_service_element&element_id=$element_id'>select from mediabase</a> | 
	          	<a href='' id='bg_auto'>auto</a> |
	          	<a href='' id='bg_none'>none</a></div>
	      	  <div id='bg_preview_container' style='position:relative;font-size:80%;'>$bg_preview_img</div>      	  	
	        </div>        
	    ";
	    $bg_script="
	        	$('#bg_auto').click(function(e){
	      			e.preventDefault();
	        		$.get('".CW_AJAX."ajax_service_planning.php?action=set_service_element_background_to_auto&service_element_id=$element_id',function(res){
	        			if (res=='ERR'){
	        				alert('An error occurred trying to auto-detect the background for this element');
	        			} else {
	        				//Have new file_id in res        				
	        				reload_background_preview(res);
	        			}
	    			});
	    		});
	
	        	$('#bg_none').click(function(e){
	      			e.preventDefault();
	        		$.get('".CW_AJAX."ajax_service_planning.php?action=set_service_element_background_to_none&service_element_id=$element_id',function(res){
	        			if (res=='ERR'){
	        				alert('An error occurred trying to remove the background for this element');
	        			} else {
	        				reload_background_preview(0);
	        			}
	    			});
	    		});
	    		    		
	    		function reload_background_preview(bg_file_id){
	    			$('#bg_preview_img').remove();
	    			if (bg_file_id>0){    			
		    			var src='".CW_AJAX."ajax_service_planning.php?action=get_downscaled_projector_preview&lib_id='+bg_file_id+'&max_width=190&max_height=150';
		    			$('#bg_preview_container').html('<img id=\"bg_preview_img\" src=\"'+src+'\" alt=\"loading background preview...\">');
		    		} else {
		    			$('#bg_preview_container').html('<span class=\"small_note gray\">(no background has been associated)</span>');
		    		}
	    		}
	    ";
        $duration_and_note="
              <tr> 
                <td>
                  Duration:
                </td>
                <td>
                  <div id='duration_display'>00:00</div>
                  <div id='slider_duration' style='margin-top:10px;margin-bottom:10px;margin-left:2px;margin-right:2px;'></div>
                </td>
              </tr>
              <tr>
                <td>
                  Note:
                </td>
                <td>
                  <textarea id='element_note'>".$element["note"]."</textarea>
                </td>
              </tr>
        ";
        $modal_title="Service element: ".$element_type["title"];
        if (empty($focus)){
          //Focus might have been set already above, for sermon or scripture ref or music
          $focus="
            if (($('#label').val()=='') || ($('#label').val()=='(other)') ){
              $('#label').focus().select();
            } else {
              $('#element_note').focus();
            }        
          ";
        }
      } else {
        //Is group header
        $modal_title="Group header";
        $focus="$('#label').focus().select();";
        $sel_options="";
        $options=$this->eh->church_services->get_all_group_template_records();
        if (is_array($options)){
          foreach ($options as $v){
            $sel_options.="<option value='".$v["id"]."'>".$v["label"]."</option>";
          }
        }
        empty($sel_options) ? $sel_options="Error: could not load templates" : $sel_options="<option value='dummy'>(select...)</option>".$sel_options;
        $specific_interface="
          Apply a group template
          <select id='group_templates'>$sel_options</select>
          
          <script type='text/javascript'>            
            //When template gets selected close modal and attempt to apply
            $('#group_templates').change(function(){
              if ($(this).val()!='dummy'){
                close_modal();
                apply_group_template(".$element["element_nr"].",$(this).val());                
              }
            });
          </script>
        ";
      }
      
      $t.="
        <div class='modal_head'>$modal_title</div>
        <div style='width:100%'>
          <div style='width:500px;float:left;height:200px;padding:3px;'>
            <table>
              <tr> 
                <td style='width:65px;'>
                  Label:
                </td>
                <td style='width:400px;'>
                  <input type='text' style='width:400px;' id='label' value=\"".htmlspecialchars($label)."\"/>
                </td>
              </tr>
              $duration_and_note
            </table>
          </div>
          $person_selector
          $attached_files
          $background_media
        </div>
        
        <div id='element_type_specific_interface' style='width:100%;float:left;'>
          <div style='padding:3px;'>
          $specific_interface
          </div>
        </div>
        
        <div style='width:100%;float:left;padding:3px;'>
          <input type='button' class='button' id='save' value='done'/>
          <input type='button' class='button' id='delete' value='delete element'/>
        <div>
      
        <script type='text/javascript'>

          //Auto-select radio button 'other person' when input gets focus
          $('#other_person').focus(function(){
            $(':radio[value=other]').attr('checked','CHECKED');
            //alert('a');
          });
        
          //Save changes
          $('#save').click(function(){
            //Generic info
            var duration=slider_val_to_duration($('#slider_duration').slider('value'));
            var person_id=$('input[name=person_id]:checked').val();
            $.post(
              '".CW_AJAX."ajax_service_planning.php?action=save_element&service_id=$service_id&element_id=$element_id&duration='+duration+'&person_id='+person_id,
              {
                note:$('#element_note').val(),
                other_person:$('#other_person').val(),
                label:$('#label').val(),
                sermon_title:$('#sermon_title').val(),
                sermon_abstract:$('#sermon_abstract').val(),
                scripture_refs:$('#scripture_refs').val()
              },
              function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_service_order(get_element_visibility('#element_selector'));
                close_modal(true);
              }
            );
          });
          
          //Delete
          $('#delete').click(function(){
            delete_service_element($('#modal').data('element_position'));
            close_modal(true);            
          });
          
        
          $('#slider_duration').slider();
          $('#slider_duration').slider('option','max',90); //Set the slider to 15second increments
          
          //Init slider (first 10 minutes are 15 sec increments, then 60s)
          function init_slider(duration){
            var quarter_minutes=Math.floor(duration/15);
            if (quarter_minutes<=40){
              //Within the first 10 minutes simply put slider to appropriate 15s increment 
              $('#slider_duration').slider('value',quarter_minutes); //Set the slider to initial position            
            } else {
              //After ten minutes
              var sliderpos=40+((quarter_minutes/4)-10);
              $('#slider_duration').slider('value',sliderpos); //Set the slider to initial position                      
            }          
          }
          
          init_slider(".$element["duration"].");
          
          $('#slider_duration').slider({
            slide: function(event,ui){
              set_duration_display(slider_val_to_duration(ui.value));
            }
          });
          
          set_duration_display(".$element["duration"].");
          
          setTimeout(function(){ $focus },300);
                    
          $('#person_id').change(function(){
            if ($(this).val()=='other'){
              $('#other_person').focus();
            }
          });
          
          function set_duration_display(total_seconds){
            var minutes=Math.floor(total_seconds/60);
            var seconds=total_seconds-(minutes*60);
            $('#duration_display').html(zerofill(minutes,2)+':'+zerofill(seconds,2));          
          }
          
          function slider_val_to_duration(value){
            var res;
            if (value<=40){
              //The first 40 increments are 15 second increments
              res=(value*15);              
            } else {
              //From the 41st increment they are 60 second increments
              res=(600+(value-40)*60);                            
            }            
            return res;
          }
          
          $bg_script
          
        </script>
      ";
      
      return $t;
    }
    
    
    function display_edit_music_package_dialogue($service_id,$pos_to_services_id,$scrolltop){
      $t="";
      if (($service_id>0) && ($pos_to_services_id>0)){
        if ($service=new cw_Church_service($this->eh,$service_id,true)){
          $actual_selection="";
          if($pts=$this->eh->event_positions->get_positions_to_services_record_by_id($pos_to_services_id)){
            foreach ($service->elements as $v){
              if ($v["meta_type"]=="music"){
                $y="";
                $atse_rec=$this->eh->church_services->get_atse_for_service_element($v["id"],true);
                if ($atse_rec===false){
                  //Not assigned
                  $this_person_off_this_piece=false;                                        
                } else {
                  $this_person_off_this_piece=true;                                        
                  if ($arr_rec=$this->eh->mdb->get_arrangement_record($atse_rec["arrangement"])){
                    if ($music_piece_rec=$this->eh->mdb->get_music_piece_record($arr_rec["music_piece"])){
                      $y=$music_piece_rec["title"];
                      if ($this->eh->event_positions->pos_to_services_record_is_on_service_element($pos_to_services_id,$v["id"])){
                        $parts=$this->eh->mdb->get_parts_for_arrangement($atse_rec["arrangement"]);
                        $z="";
                        $precedence_csl=$this->eh->get_authoritative_instrument_precedence($pts["pos_id"],$pos_to_services_id,$v["id"]);
                        $precedence_array=explode(",",$precedence_csl);
                        $checked_file_id="";
                        foreach ($precedence_array as $j){
                          //$j could either be a file_id if it is prefixed with 'f', or an instrument id
                          if (substr($j,0,1)=="f"){
                            //got file id (from music_packages table)
                            $file_id=substr($j,1);                      
                          } else {
                            //got instrument id only, get corresponding file_id 
                            $ftia_rec=$this->eh->mdb->get_files_to_instruments_and_arrangements_record_for_arrangement_and_instrument($atse_rec["arrangement"],$j);
                            $file_id=$ftia_rec["file"];                                              
                          }
                          //Now we have a file id. See which part has that file-id.
                          foreach ($parts as $part){
                            if ($part["file_id"]==$file_id){
                              $checked_file_id=$file_id;
                              break 2;
                            }
                          }
                        }
                        
                        foreach ($parts as $part){
                          $checked="";                      
                          if ($checked_file_id==$part["file_id"]){
                            $checked="checked='CHECKED'";
                            $actual_selection.="
                              <div class='actual_selection_item'>
                                <div class='as_left'>&quot;".$music_piece_rec["title"]."&quot;</div>
                                <div class='as_right'>".$part["instruments"]."</div>
                              </div>";
                          }
                          //Download link
                          $l=CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b=".$part["file_id"];                    
                          $z.="<input type='checkbox' $checked id='element_".$v["id"]."_partfile_".$part["file_id"]."' class='element_".$v["id"]."'/> <a href=\"$l\">".$part["instruments"]."</a><br/>";
                          $z.="
                              <script type='text/javascript'>
                                $('#element_".$v["id"]."_partfile_".$part["file_id"]."').click(function(){
                                  var service_element_id=$(this).attr('class').substr(8);
                                  var i=$(this).attr('id').lastIndexOf('_');
                                  var file_id=$(this).attr('id').substr(i+1);
                                  var pos_to_services_id=$pos_to_services_id;
                                  var select=$(this).prop('checked');
                                  $('.element_".$v["id"]."').removeAttr('CHECKED');
                                  var this_cb=$(this);
                                  $.post('".CW_AJAX."ajax_service_planning.php?action=music_package_select_partfile&service_id=$service_id',{ file_id:file_id,service_element_id:service_element_id,pos_to_services_id:pos_to_services_id,select:select },function(rtn){
                                    if (rtn=='OK'){
                                      reload_edit_music_package_dialogue();                                                                  
                                    } else {
                                      alert(rtn);
                                    }                              
                                  });
                                });
                              </script>                      
                          ";
                        }
                        $this_person_off_this_piece=false;
                      }
                    }
                  }                
                }
                if (!$this_person_off_this_piece){
                  if (!empty($y)){
                    $divs.="
                          <div class='music_package_music_piece'>
                            <div class='music_package_music_piece_title'>
                              &quot;<span class='link' id='element_".$v["id"]."'>$y</span>&quot;
                            </div>
                            <div class='music_package_music_piece_parts'>
                              $z
                            </div>
                          </div>
                    ";
                  } else {
                    $divs.="
                          <div class='music_package_music_piece'>
                            <div class='music_package_music_piece_title'>
                              <span class='red'>
                              &quot;".$v["label"]."&quot; not assigned to database!
                              </span>
                            </div>
                          </div>                  
                    ";                                    
                  }
                } else {
                  $divs.="
                        <div class='music_package_music_piece'>
                          <div class='music_package_music_piece_title'>
                            <span class='gray'>&quot;<span class='link' id='element_".$v["id"]."'>$y</span>&quot;</span>
                          </div>
                        </div>                  
                  ";
                }
              }            
            }
            if ($pts["person_id"]==$this->auth->cuid){
              $firstname="your";
              $fullname="<span class='yellow'>".$this->auth->personal_records->get_name_first_last($pts["person_id"])."</span> (you)";
            } else {
              $firstname=$this->auth->personal_records->get_first_name($pts["person_id"])."'";
              if (!(strtolower(substr($firstname,-2,1))=='s')){
                $firstname.="s";
              }
              $fullname="<span class='yellow'>".$this->auth->personal_records->get_name_first_last($pts["person_id"])."</span>";
            }
            $pos_title=$this->eh->event_positions->get_position_title($pts["pos_id"]);
            $t.="
              <div class='modal_head'>
                Edit music package for $fullname, $pos_title
              </div>
              
              <div class='modal_body'>
                <div id='music_package_music_pieces_outer_container'>
                  Select parts from the pieces in the service:
                  <div id='music_package_music_pieces_container'>
                    $divs
                  </div>
                </div>
                <div id='actual_selection_outer_container'>
                  Content of $firstname music package:
                  <div id='actual_selection_container'>
                    $actual_selection
                  </div>
                </div>
                <div class='expl' style='float:left;width:406px;margin-left:6px;'>
                  In the box on the left, select for each music piece the part you want to include in $firstname package. 
                  Clicking on the label of any part (beside the checkbox) will download the part for you so you can preview it.
                  Clicking on the title of a piece will put this person on or off that piece. The box on the right displays the actual contents of the music package.
                </div>
                <div style='float:left;clear:left;margin:5px;'>
                  <input style='width:140px;' id='closemodal' type='button' value='Done'/>
                  <input id='apply_to_all_in_position' type='button' value='Apply to all ".plural($pos_title)."'/>
                  <input id='apply_to_everyone' type='button' value='Apply to everyone'/>
                </div>
              </div>          
              
              <script type='text/javascript'>
                
                $('#closemodal').click(function(){
                  close_modal();
                });
                
                $('#apply_to_all_in_position').click(function(){
                  var pos_to_services_id=$pos_to_services_id;
                  $.post('".CW_AJAX."ajax_service_planning.php?action=music_package_apply_to_others&service_id=$service_id&same_position_only=true',{ pos_to_services_id:pos_to_services_id },function(rtn){
                    if (rtn!='ERR'){
                      alert(rtn);
                    } else {
                      alert('An error occurred while trying to apply the music package to other participants');
                    }                              
                  });                  
                });
                
                $('#apply_to_everyone').click(function(){
                  var pos_to_services_id=$pos_to_services_id;
                  $.post('".CW_AJAX."ajax_service_planning.php?action=music_package_apply_to_others&service_id=$service_id&same_position_only=false',{ pos_to_services_id:pos_to_services_id },function(rtn){
                    if (rtn!='ERR'){
                      alert(rtn);
                    } else {
                      alert('An error occurred while trying to apply the music package to other participants');
                    }                              
                  });                  
                });
                  
                function reload_edit_music_package_dialogue(){
                  var scrolltop=$('#music_package_music_pieces_container').scrollTop();                
                  $('#modal').load('".CW_AJAX."ajax_service_planning.php?action=display_edit_music_package_dialogue&service_id=$service_id&pos_to_services_id=$pos_to_services_id&scrolltop='+scrolltop);  
                }
                
                $('#music_package_music_pieces_container').scrollTop($scrolltop);
                
                $('.link').click(function(){
                  //Click on greyed out element (which is on pos_to_services__to_service_elements)
                  var service_element_id=$(this).attr('id').substr(8);
                  var pos_to_services_id=$pos_to_services_id;
                  $.post('".CW_AJAX."ajax_service_planning.php?action=music_package_toggle_person_on_service_element&service_id=$service_id',{ service_element_id:service_element_id,pos_to_services_id:pos_to_services_id },function(rtn){
                    if (rtn=='OK'){
                      reload_edit_music_package_dialogue();                                                                  
                    } else {
                      alert(rtn);
                    }                              
                  });
                });
                
              </script>  
            ";           
          }        
        }
      } 
      return $t;    
    }
    
    
    function display_coffee_order_dialogue($service_id){
      if ($service=new cw_Church_service($this->eh,$service_id)){
        $co=new cw_Coffee_orders($this->auth,$this->eh);
        $t="";
        
        /*
          Pseudo:
            - get all pos_to_services grouped by position type
        */
        
        $pos_types=$this->d->get_table("position_types");
        $addon_recs=$co->get_drink_records(2);
        $add_ons_labels="";
        foreach ($addon_recs as $addon_rec){
          $add_ons_labels.="<td class='addon_label' >".$addon_rec["label"]."</td>";                                              
        }
        for ($i=1;$i<=sizeof($pos_types);$i++){
          $pts_recs=$this->eh->event_positions->get_positions_for_service($service_id,$i);
          if (sizeof($pts_recs)>0){
            $u.="<tr><td colspan=\"3\"><span style='font-weight:bold;'>".$this->eh->event_positions->get_position_type_title($i)."</span> <span class='small_note'><a class='select_pos_type_group' id='ptg_$i' href=''>select all</a> | <a class='unselect_pos_type_group' id='ptg_$i' href=''>deselect all</a></span></td>$add_ons_labels</tr>";
          }
          foreach ($pts_recs as $pts_rec){
            $add_ons_selects="";
            foreach ($addon_recs as $addon_rec){
              $add_ons_selects.="<td class='addon_select'><select class='select_add_on_".$addon_rec["id"]."'>".$co->get_addon_select_options($pts_rec["person_id"],$addon_rec["id"])."</select></td>";                                              
            }
            //Is person on the order already?
            ($co->coffee_order_exists($service_id,$pts_rec["person_id"])) ? $checked="checked=\"CHECKED\"" : $checked="";
            $u.="
              <tr class='person_".$pts_rec["person_id"]."'>
                <td style='width:200px;'><input type='checkbox' class='pos_type_$i'$checked /> ".$this->auth->personal_records->get_name_first_last($pts_rec["person_id"])."</td>
                <td><select class='select_size'>".$co->get_drink_size_select_options($pts_rec["person_id"])."</select></td>
                <td><select class='select_drink'>
                      ".$co->get_drink_choice_select_options($pts_rec["person_id"])."
                      <option style='color:red;' id='other_drink' value='other_drink'>enter other drink</option>
                    </select></td>
                $add_ons_selects
              </tr>
            ";
          }
        }
        
        $t="
          <div class='modal_head'>
            Coffee order for ".$service->service_name." on ".$service->get_service_times_string()."
          </div>
          
          <div class='modal_body'>
            <div id='coffee_order'>
              <table>$u</table>
            </div> 
            <div>
              <input type='button' id='close_dialogue' value='Close'/>
              <input type='button' id='download_order' value='Download Order'/>
              <img style='height:10px;width:10px;margin:0px;padding:0px;' class='get_help' id='ticket_10' src='".CW_ROOT_WEB."img/help.gif'/>
            </div>         
          </div>      
          
          <script type='text/javascript'>

            $('.get_help').click(function(){
              get_help($(this).attr('id').substr(7));
            });                      
          
            //Any of the parameters: drink,size,add-ons
            $('#modal select').click(function(){
              var table_row=$(this).parent().parent();
              var cb=$(table_row).children().first().children().first();
              var person_id=$(table_row).attr('class').substr(7);            
              if (!$(cb).is(':checked')){
                //Add this person
                $.post('".CW_AJAX."ajax_service_planning.php?action=coffee_order&service_id=$service_id',{ person_id:person_id,checked:true },function(rtn){
                  if (rtn!='OK'){
                    alert(rtn);
                  } else {
                    cb.prop('checked', !cb.prop('checked'));
                  }                              
                });            
              }
              if ($(this).val()=='other_drink'){
                var drink_name=prompt('Enter name of drink:');
                if (drink_name!=''){
                  //Got name of new drink
                  $.post('".CW_AJAX."ajax_service_planning.php?action=coffee_order_add_new_drink&service_id=$service_id',{ person_id:person_id,drink_name:drink_name },function(rtn){
                    if (rtn!='OK'){
                      alert(rtn);
                    }                              
                    show_coffee_order_dialogue();
                  });                                
                } else {
                  alert('No label provided - will do nothing.');
                  show_coffee_order_dialogue();
                }              
              } else {                
                var size_id=$(table_row).find('td:eq(1)').children().first().val();
                var drink_id=$(table_row).find('td:eq(2)').children().first().val();
                var add_ons='';
                var i=0;
                $(table_row).children().each(function(){
                  i=i+1;
                  if (i>3){
                    //Build add_on csl: add_on_id:value
                    var qty=$(this).children().first().val();
                    if (qty>0){
                      //Don't register empty add-ons
                      add_ons=add_ons+','+$(this).children().first().attr('class').substr(14)+'.'+$(this).children().first().val();                                                                                         
                    }
                  }
                });
                add_ons=add_ons.substr(1); //cut first comma
                
                $.post('".CW_AJAX."ajax_service_planning.php?action=coffee_order_select_drink&service_id=$service_id',{ person_id:person_id,size_id:size_id,drink_id:drink_id,add_ons:add_ons },function(rtn){
                  if (rtn!='OK'){
                    alert(rtn);
                  }                              
                });              
              }            
            });
            
            //checkbox to place/cancel the order
            $('#modal input').click(function(e){
              e.preventDefault();
              var person_id=$(this).parent().parent().attr('class').substr(7);
              var checked=$(this).is(':checked');
              var thiscb=$(this);
              $.post('".CW_AJAX."ajax_service_planning.php?action=coffee_order&service_id=$service_id',{ person_id:person_id,checked:checked },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                } else {
                  thiscb.prop('checked', !thiscb.prop('checked'));
                }                              
              });            
            });
                                    
            function coffee_order_pos_type_group(pos_type_id,checked){
              $.post('".CW_AJAX."ajax_service_planning.php?action=coffee_order_pos_type_group&service_id=$service_id',{ pos_type_id:pos_type_id,checked:checked },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                } else {
                  $('.pos_type_'+pos_type_id).each(function(){
                    $(this).attr('checked',checked);                
                  });            
                }                              
              });                                    
            }

            $('.select_pos_type_group').click(function(e){
              e.preventDefault();
              var pos_type_id=$(this).attr('id').substr(4);
              coffee_order_pos_type_group(pos_type_id,true);
            });

            $('.unselect_pos_type_group').click(function(e){
              e.preventDefault();
              var pos_type_id=$(this).attr('id').substr(4);
              coffee_order_pos_type_group(pos_type_id,false);
            });
            
            $('#close_dialogue').click(function(){
              close_modal();
            });
            
            $('#download_order').click(function(){
              $.post('".CW_AJAX."ajax_service_planning.php?action=get_coffee_order_pdf&service_id=$service_id',{},function(rtn){
                if (rtn=='ERR'){
                  alert('Error: could not generate PDF file (most likely your order is empty!)');
                } else {
                  //rtn has file-id for pdf file
                  window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->auth->csid."') + '&b=' + rtn;
                }
              });          
            })
            
          </script>
        ";      
        return $t;      
      }
      return false;
    }
    
    
    function display_edit_projection_settings_dialogue($service_id){
    	$t="";
    	$s="";
    	if ($service=new cw_Church_service($this->eh,$service_id)){
    		$bg_img="";
    		$unassign_link="";
    		if (($service->service_record["background_image"]>0) && ($mb=new cw_Mediabase($this->auth))){
    			//Find out if we have mediabase permission
   				if ($this->auth->have_mediabase_permission()){			
   					$bg_preview_src=CW_AJAX."ajax_mediabase.php?action=get_downscaled_projector_preview&lib_id=".$service->service_record["background_image"]."&max_width=495&max_height=490";
    				$bg_img="<img src='$bg_preview_src'><span class='overwrite'> assigned by ".$this->auth->personal_records->get_name_first_last($service->service_record["background_image_assigned_by"])." </span>";
    				$unassign_link="| <a href='' id='ps_remove_bg_image'>remove</a>";
    				$s.="
    					$('#ps_remove_bg_image').click(function(e){
   							e.preventDefault();
   							$.get('".CW_AJAX."ajax_service_planning.php?action=unassign_background_media_from_service&service_id=$service_id',function(res){
   								if (res==''){
    								$('#ps_bg_preview').html('<span class=\"red\">Background image removed</span>');
    							} else {
    								alert(res);
    							}
    						});
   						});
   					";
   				} else {
    				//No rights to mediabase
    				$bg_img="<div class='red'>Cannot display background preview because of insufficient privileges</div>";
    			}
    		} else {
    			//No bg preview because none has been assigned or error
    			$bg_img="<span class='gray'>(no background has been associated with this service)</span>";
    		}
    		($service->service_record["use_mdb_backgrounds"]>0) ? $cb_use_mdb_backgrounds_checked="CHECKED" : $cb_use_mdb_backgrounds_checked="";   		 
    		($service->service_record["use_motion_backgrounds"]>0) ? $cb_use_motion_backgrounds_checked="CHECKED" : $cb_use_motion_backgrounds_checked="";   		 
    		($service->service_record["background_priority"]>0) ? $cb_background_priority_checked="CHECKED" : $cb_background_priority_checked="";   		 
    		$t.="
	    			<div class='modal_head'>Projection settings</div>
	    			<div class='modal_body' style='padding:0px;'>
	    				<div>
	    					<div class='ps_container' style='border-right:1px solid gray;'>
	    						<h4>Lyrics font settings</h4>
	    						<div style='padding-top:10px;'>
	    							<input type='checkbox' id='cb_overwrite_defaults'> overwrite default settings
	    						</div>
	    						<div id='ps_overwrite_defaults'>
	    							<table>
	    								<tr><td>Font face:</td><td><input type='text'></td><td class='small_note'>E.g. <span class='green'>Arial, sans-serif</span></td></tr>
	    								<tr><td>Font size:</td><td><input type='text'></td><td class='small_note'>Relative to 1024*768</td></tr>
	    								<tr><td>Font color:</td><td><input type='text'></td><td class='small_note'>Hex RGB, e.g. <span class='green'>#FFFFFF</span> for white</td></tr>
	    								<tr><td>Stroke color:</td><td><input type='text'></td><td class='small_note'>Hex RGB, e.g. <span class='green'>#000000</span> for black</td></tr>
	    								<tr><td>Vertical align:</td><td><select><option>top</option><option>center</option><option>bottom</option></select></td><td class='small_note'></td></tr>
	    								<tr><td>Horizontal align:</td><td><select><option>left</option><option>center</option><option>right</option></select></td><td class='small_note'>Only <span class='green'>left</span> can preserve indenting</td></tr>
										<tr><td>Indented lines:</td><td><input type='checkbox'> always ignore indenting</td><td class='small_note'></td></tr>    								
	    							</table>	
	    						</div>
	    					</div>
	    					<div class='ps_container'>
	    		    			<h4>Service background</h4>
	    						<div style='padding-top:10px;'>
	    							<a href='mediabase.php?action=select_bg_media_for_church_service&service_id=$service_id'>choose image from mediabase</a> $unassign_link
	    						</div>
	    						<div id='ps_bg_preview'>
	    							$bg_img
	    						</div>
	    						<div style='padding-top:10px;'>
	    							<input type='checkbox' id='cb_use_mdb_backgrounds' $cb_use_mdb_backgrounds_checked> use song/arrangement backgrounds when present<br>
	    							<input type='checkbox' id='cb_use_motion_backgrounds' $cb_use_motion_backgrounds_checked> use motion backgrounds when available<br>
	    							<input type='checkbox' id='cb_background_priority' $cb_background_priority_checked> service background supersedes song/arrangement backgrounds<br>
	    						</div>
	    					</div>
	    					<div style='border-top:1px solid gray;width:100%;clear:both;'>
			                	<div style='float:left;clear:left;margin:5px;'>
			                		<input style='width:140px;' id='closemodal' type='button' value='Done'/>
			                	</div>
	    					</div>
	    				</div>
	    			</div>
	    			
	    	";
	    	
	    	$s.="
	    				$('#cb_overwrite_defaults').click(function(){
	    					if ($(this).prop('checked')){
	    						$('#ps_overwrite_defaults').show();
	    					} else {
	    						$('#ps_overwrite_defaults').hide();
	    					}
	    				});
	    			
	    				$('#closemodal').click(function(){
	    					close_modal();
    					});
	    			
	    				function set_checkbox(field_name,checked){
	    					$.get('".CW_AJAX."ajax_service_planning.php?action=set_projection_settings_checkbox&service_id=$service_id&field='+field_name+'&checked='+checked,function(res){
	    						if (res!=''){
	    							alert(res);
	    						}
    						});
	    				}
	    			
	    				$('#cb_use_mdb_backgrounds').click(function(){
	    					set_checkbox('use_mdb_backgrounds',$(this).prop('checked'));
    					});

	    				$('#cb_use_motion_backgrounds').click(function(){
	    					set_checkbox('use_motion_backgrounds',$(this).prop('checked'));
    					});
	    			
	    				$('#cb_background_priority').click(function(){
	    					set_checkbox('background_priority',$(this).prop('checked'));
    					});
	    			
	    	";
    	}
    	echo $t."<script>$s</script>";
    }

  }

?>
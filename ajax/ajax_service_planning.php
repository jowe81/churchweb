<?php

  require_once "../lib/framework.php";

  $eh=new cw_Event_handling($a);  
  
  $mark_service_update=false; //If this gets set to true during the script, the church_services record will be updated with updated_at and updated_by

  if ($_GET["action"]=="set_service_filter"){
    if ($upref = new cw_User_preferences($d,$a->cuid)){
      if (($_GET["do"]=="toggle_template") && ($_GET["template_id"]>0)){
        //Toggle whether or not service template with template_id is to be shown
        $x=$upref->read_pref($a->csid,"SERVICES_FILTER_HIDE_".$_GET["template_id"]);
        if ($x==0){
          $upref->write_pref($a->csid,"SERVICES_FILTER_HIDE_".$_GET["template_id"],1);                
        } else {
          $upref->delete_pref($a->csid,"SERVICES_FILTER_HIDE_".$_GET["template_id"]);                
        }
      }
      if ($_GET["do"]=="clear_filter"){
        $template_ids=explode(",",$_GET["template_ids"]);
        foreach ($template_ids as $v){
          if ($v>0){
            $upref->delete_pref($a->csid,"SERVICES_FILTER_HIDE_$v");                            
          }
        }
      }          
      if ($_GET["do"]=="filter_all"){
        $template_ids=explode(",",$_GET["template_ids"]);
        foreach ($template_ids as $v){
          if ($v>0){
            $upref->write_pref($a->csid,"SERVICES_FILTER_HIDE_$v",1);                
          }
        }
      }          
    }
    echo "OK";
  } elseif ($_GET["action"]=="get_services_list"){
    //Get current week if no timestamp specified
    ($_GET["timestamp"]>0) ? $timestamp=$_GET["timestamp"] : $timestamp=0; //$timestamp==0 is interpreted as "get current week"
    $sr=$eh->get_services($timestamp);

    ($timestamp==0) ? $timestamp=time() : null;        
    $previous_month=getBeginningOfPreviousMonth($timestamp);
    $this_month=getBeginningOfMonth($timestamp);    
    $next_month=getBeginningOfNextMonth($timestamp);
    $next_month2=getBeginningOfNextMonth($next_month);
    $next_month3=getBeginningOfNextMonth($next_month2);
    $next_month4=getBeginningOfNextMonth($next_month3);
    $prev_wk=getBeginningOfWeek($timestamp-WEEK);
    $next_wk=getBeginningOfWeek($timestamp+WEEK);
    $navigation="
      <div class='services_navigation'>
        <a class='lcurr'>To current week</a> ||
         <a class='lwkback'>&lt;</a> <a class='lwkforward'>&gt;</a>
        || <a class='lprev'>".date("F Y",$previous_month)."</a> | <a class='lthis'>".date("F Y",$this_month)."</a> | <a class='lnext'>".date("F Y",$next_month)."</a> | <a class='lnext2'>".date("F Y",$next_month2)."</a> | <a class='lnext3'>".date("F Y",$next_month3)."</a> | <a class='lnext4'>".date("F Y",$next_month4)."</a>
        || <span style='font-size:80%'>jump to date: <input style='width:80px;' class='fjumpto' type='text' value='MM/YYYY'/></span>
      </div>    
    ";
    $navigation_script="      
      <script type='text/javascript'>
        $('.lprev').click(function(){
          reload_services_list($previous_month);
        });
        $('.lthis').click(function(){
          reload_services_list($this_month);
        });
        $('.lcurr').click(function(){
          reload_services_list();
        });
        $('.lnext').click(function(){
          reload_services_list($next_month);
        });
        $('.lnext2').click(function(){
          reload_services_list($next_month2);
        });
        $('.lnext3').click(function(){
          reload_services_list($next_month3);
        });
        $('.lnext4').click(function(){
          reload_services_list($next_month4);
        });
        $('.lwkback').click(function(){
          reload_services_list($prev_wk);
        });
        $('.lwkforward').click(function(){
          reload_services_list($next_wk);
        });
        
        
        $('.fjumpto').focus(function(){
          $(this).val('');
        }).keypress(function(e){
          if (e.keyCode==13){
            var n=$(this).val().split('/');
            var date = new Date(n[1],n[0]-1);
            var timestamp=(date.getTime()/1000);
            if (timestamp>0){
              reload_services_list(timestamp-1);
            } else {
              $(this).val('');
            }
          }
        });
      </script>
    ";
    
    
    
    if (sizeof($sr)>0){
      $current_week=0;
      $t=""; //HTML
      $s=""; //JQuery      
      $spref=new cw_System_preferences($a);        
      $other_positions_to_display=$spref->read_pref("SERVICE_PLANNING_SERVICE_LIST_OTHER_POSITIONS");
      foreach ($sr as $v){
        //$v is a church_service_id (from church_services)   
        $service=new cw_Church_service($eh,$v);
        
        //Get info about the sermon
        $textinfo=$service->get_first_sermon_scripture_string();
        if ($textinfo==$service->title){
          //If the title is identical with the text info, discard the textinfo string
          $textinfo="";
        } else {
          if ($textinfo!=""){
            $textinfo=" - $textinfo";
          }
        }
  
        //Get leadership positions, and all those of the current user
        $leadership=$eh->event_positions->get_positions_for_service($v,1,$a->cuid);
        $no_leadership_pos=sizeof($leadership); //mark up only this many positions
        //Get other positions that are configured to be displayed in the service list
        if (!empty($other_positions_to_display)){
          $other_pos_ids=explode(",",$other_positions_to_display);
          foreach ($other_pos_ids as $v2){
            if ($v2>0){
              $r=$eh->event_positions->get_positions_to_services_records($v,$v2,$a->cuid);
              if (is_array($r)){
                $leadership=array_merge($leadership,$r);
              }
            }
          }
        }        
        $l_info_left="";
        $l_info_right="";
        $service_includes_me_class="";
        if (sizeof($leadership)>0){
          $cnt=0;
          foreach ($leadership as $pts_rec){
            $cnt++;
            $ptitle=$eh->event_positions->get_position_title($pts_rec["pos_id"]);
            $pname=$a->personal_records->get_name_first_last($pts_rec["person_id"]);
            if ($pts_rec["person_id"]==$a->cuid){
              $ptitle="<span style='color:#DD3333;'>$ptitle</span>";
              $pname="<span style='color:#DD3333;font-weight:normal;'>$pname</span>";
              $service_includes_me_class="service_includes_me";
            }
            if ($cnt>$no_leadership_pos){
              //Mark support positions different
              $ptitle="<span style='color:#888;'>$ptitle</span>";
              $pname="<span style='color:#888;'>$pname</span>";
            }
            if ($ptitle==CW_SERVICE_PLANNING_DESCRIPTOR_PREACHER){
              
              $l_info_left.=$ptitle.":<br/>";
              $l_info_right.=$pname." <span style=''>".shorten($textinfo,25)."</span><br/>";                    
            } else {
              $l_info_left.=$ptitle.":<br/>";
              $l_info_right.=$pname."<br/>";          
            }
          }
        }     
        
        
        $dayscol="";
        $timescol="";
        $startsincol="";
        foreach ($service->event_records as $erec){
          $dayscol.=date("l F j Y",$erec["timestamp"])."<br/>";                
          $timescol.=date("h:i a",$erec["timestamp"])." on ".date("l (F j)",$erec["timestamp"])."<br/>";
          if (($erec["timestamp"]-time()<0) && ($erec["timestamp"]+$erec["duration"]-time()>0)){
            $startsincol.="<span style='color:green;'>in progress</span><br/>";                
          } elseif (($erec["timestamp"]-time()<(15*MINUTE)) && ($erec["timestamp"]-time()>0)) {
            $startsincol.="<span style='color:red;'>starts in ".getHumanReadableLengthOfTime($erec["timestamp"]-time()+CW_CHURCH_TIME_OFFSET)."</span><br/>";                                          
          } elseif ($erec["timestamp"]+$erec["duration"]-time()<0) {
            $startsincol.="<span style='color:gray;'>completed</span><br/>";                
          } else {
            $startsincol.="<span style='color:gray;'>in ".getHumanReadableLengthOfTime($erec["timestamp"]-time()+CW_CHURCH_TIME_OFFSET)."</span><br/>";                                
          }
        }
        //Update info
        $updated_by=$pr->get_name_first_last($service->service_record["updated_by"]);
        (!empty($updated_by)) ? $updated_by="by $updated_by" : null;
        $uinfo="<div class='d_update_info'>updated ".getHumanReadableLengthOfTime(time()-$service->service_record["updated_at"])." ago $updated_by</div>";
        if (time()<$service->event_records[0]["timestamp"]){
          //Needed/open positions - only for future services
          $poslist="";
          $needed_positions=$service->get_array_of_needed_positions();
          if (!empty($needed_positions)){  
            //$needed_positions is array with key=position_id, value=number of needed people for that position
            foreach ($needed_positions as $pos_id=>$no_needed){                    
              $pclass="";
              //Can I volunteer for this positition?
              $can_volunteer=$eh->event_positions->can_person_fill_position($pos_id,$a->cuid);
              if ($can_volunteer){
                $pclass.=" can_volunteer";          
              }
              //Have I volunteered for this position?
              if ($eh->event_positions->person_has_volunteered($a->cuid,$service->id,$pos_id)){
                $pclass.=" have_volunteered";
              }          
              //Are there enough volunteers to fill the position (with the amount of people needed)?
              if (sizeof($eh->event_positions->who_has_volunteered($service->id,$pos_id))>=$no_needed){
                //Yes volunteers are sufficient
                $pclass.=" volunteer";
              } else {
                //Not enough volunteers
                $pclass.=" no_volunteer";          
              }          
    
              //Only actually display this position if I'm either a service plan editor or admin, or I can volunteer for it
              if (($a->csps>=CW_E) || ($can_volunteer)){
                $poslist.=", <span class='service_$v $pclass' id='pos_$pos_id'>".$eh->event_positions->get_position_title($pos_id)."</span>";          
              }
              
            }
            if (!empty($poslist)){
              //Got something in the position list
              if ($a->csps>=CW_E){
                //For Editors/Admins label the position list different than for viewers
                $list_label="Open positions";
              } else {
                $list_label="Declare your availability as";
              }
              $poslist="
                    <div class='d_open_positions'>
                      $list_label: <span class='open_pos_list'>".substr($poslist,2)."</span>
                    </div>
              ";              
            }
          }
        }
     
        
        if (!(isSameWeek($service->event_records[0]["timestamp"],$current_week))){
          //New week: close previous week_div (unless this is the first service to be displayed), open new one
          if (!($sr[0]==$v)){
            //This is NOT the very first service to be displayed, i.e. open week-div exists and must be closed
            $t.="\n</div><!--week_container-->";          
          }
          $current_week=$service->event_records[0]["timestamp"];
          $weekinfo=date("F j",getBeginningOfWeek($current_week))." to ".date("F j",getEndOfWeek($current_week));
          $border="";
          if (isSameWeek($current_week,time())){
            $weekinfo="This week: ".$weekinfo; 
            $border="border:1px solid red;";
          } elseif (isSameWeek($current_week,time()+WEEK)){
            $weekinfo="Next week: ".$weekinfo;                                                    
          } elseif (isSameWeek($current_week,time()-WEEK)){
            $weekinfo="Last week: ".$weekinfo;                                                    
          } else {
            $weekinfo="Week from ".$weekinfo;
          }
          $t.="<div class='week_container' style='$border'>
            <div class='week_info'>$weekinfo</div>
          ";
        }      
  
        $abstract_text=$service->get_first_sermon_abstract();
        $abstract="";
        if (!empty($abstract_text)){
          $abstract="
              <div class='d_abstract' id='y$v'>
                <span style='text-decoration:underline;'>sermon abstract</span><span style='display:none;' id='yy$v'>: <div class='d_abstract_text'>".htmlspecialchars($abstract_text)."</div></span>
              </div>
          ";      
        }

        $this_week_class="";
        if (isSameWeek($current_week,time())){
          $this_week_class="this_week";                                           
        }
        
        $past_class="";
        if ($service->is_past) {
          $past_class="past";
          $this_week_class="";          
        }
        
        $in_progress_class="";
        if ($service->in_progress()){
          $in_progress_class="in_progress";
          $this_week_class="";
          $past_class="";
          $service_includes_me_class="";
        }
        
        $t.="
          <div class='church_service $this_week_class $past_class $service_includes_me_class $in_progress_class' id='s$v'>
            <div class='d_service_name'>
              <div>".$service->service_name."</div>
              $uinfo
              $abstract
            </div>
            <div class='d_service_info'>
              <div class='d_service_title'>".$service->title."</div>
              <div class='d_details'>
                <div class='d_leadership_leftcol'>
                  $l_info_left
                </div>
                <div class='d_leadership_rightcol'>
                  $l_info_right
                </div>              
              </div>
              <div class='d_times'>
                <div class='d_timescol'>
                  $timescol
                </div>
                <div class='d_startsincol'>
                  $startsincol
                </div>
              </div>
            </div>
            $poslist
          </div>
          
          <script type='text/javascript'>
  
            $('#y$v').hover(function(){
              $('#yy$v').show();
            },function(){
              $('#yy$v').hide();
            });        
  
            $('.service_$v.can_volunteer').click(function(e){
              e.stopPropagation();
              var position_id=$(this).attr('id').substr(4);
              $.post('".CW_AJAX."ajax_service_planning.php?action=toggle_volunteer&service_id=$v',{ position_id:position_id,person_id:".$a->cuid." },function(rtn){
                if (rtn!='OK'){
                 alert(rtn);
                }
                reload_services_list();
              });        
            });                                    
  
          </script>
        ";
        
        /*
        $t.="<tr class='$class church_service' id='s$v'>
              <td>
                <table>
                  <tr>
                    <td class='sl1'><span style='font-weight:bold;'>".$service->service_name."</span>".$uinfo."</td>
                    <td class='sl2'><div>".$service->title."</div>".$tinfo."</td>
                    <td class='sl3'>$dayscol</td>
                    <td class='sl4'>$timescol</td>
                    <td class='sl5'>$startsincol</td>
                  </tr>
                  $poslist
                </table>
              </td>
            </tr>
            ";
        */      
      }
      //close last week div
      $t.="\n</div><!--week_container-->";
      //Jquery to link table row
      $s.="
        $('.church_service').click(function(e){ 
          var id=$(this).attr('id');
          id=id.substr(1); //cut 's'
          if (e.shiftKey){
            if (confirm('Are you sure you want to delete this entire service?')){
              show_please_wait('Deleting service...please wait');
              $.post('".CW_AJAX."ajax_service_planning.php?action=delete_service&service_id=' + id ,{},function(ret){
                hide_please_wait();
                if (ret=='OK'){
                  $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list&type=".$_GET["type"]."');
                } else {
                  alert(ret);
                }
              });
            }
          } else {
            if (e.altKey) {
              if ('".$_GET["type"]."'!='past'){
                var url='".CW_AJAX."ajax_service_planning.php?action=get_edit_service_times_interface&service_id=' + id;
                show_modal(url,50,50,830);
              } else {
                alert('Cannot edit shows of services in the past');
              }
            } else {
              window.location='".CW_ROOT_WEB.$a->services->get_non_ajax_service_url($a->csid)."?action=plan_service&service_id='+id;          
            }
          }  
        });
         
        $('.can_volunteer').each(function(){
          if ($(this).hasClass('have_volunteered')){
            $(this).simpletip({ content:'You are available for this position (click to undo)', offset:[tip_offset,-24]}).show();              
          } else {
            $(this).simpletip({ content:'Click to declare your availability for this position', offset:[tip_offset,-24]}).show();              
          }
        });
        
        $('.church_service').hover(function(e){
          if (e.shiftKey){
            $(this).removeClass('greenbg').addClass('redbg'); 
          }
          if (e.altKey){
            $(this).removeClass('redbg').addClass('greenbg');          
          }
        },function(e){
          $(this).removeClass('greenbg').removeClass('redbg');
        });
        
        $('.church_service').disableSelection();
      ";
    }
    echo $navigation;
    if (empty($t)){
      echo "<p>No services found.</p>
            <script type='text/javascript'>
              reload_services_list_filter_interface($timestamp);
            </script>          
      ";
    } else {
      echo "
          $t
        $navigation
        <div style='height:100px;'>&nbsp;</div>  
        <script type='text/javascript'>
          $s
          reload_services_list_filter_interface($timestamp);
        </script>    
      ";
    }
    echo $navigation_script;
  } elseif ($_GET["action"]=="toggle_volunteer"){
    //Got $_POST: position_id,person_id (which should be $a->cuid anyway)
    if ($eh->event_positions->toggle_volunteer($_POST["person_id"],$_GET["service_id"],$_POST["position_id"])){
      echo "OK";
    } else {
      echo "Error: could not mark availability for this position";
    }    
    $mark_service_update=true;
  } elseif ($_GET["action"]=="get_admin_task_links"){
    $tasks="";
    if ($_GET["auth_level"]>=CW_A){
      $tasks="
          <h4>Administrative tasks</h4>
          <ul id='administrative_links'>
            <li><a href='' id='schedule_services_link'>Schedule services</a></li>
            <li><a href='' id='manage_service_templates_link'>Manage service templates</a></li>
            <li><a href='' id='manage_element_group_templates_link'>Manage service element group templates</a></li>      
          </ul>
          
          <script type='text/javascript'>
            $('#schedule_services_link').click(function(e){
              e.preventDefault();
              var url='".CW_AJAX."ajax_service_planning.php?action=display_schedule_services_dialogue';
              show_modal(url,50,20,870,'schedule_services');
            });            
            $('#manage_service_templates_link').click(function(e){
              e.preventDefault();
              var url='".CW_AJAX."ajax_service_planning.php?action=display_manage_service_templates_dialogue';
              show_modal(url,50,20,870,'manage_service_templates');
            });            
            $('#manage_element_group_templates_link').click(function(e){
              e.preventDefault();
              var url='".CW_AJAX."ajax_service_planning.php?action=display_manage_element_group_templates_dialogue';
              show_modal(url,50,20,870,'manage_element_group_templates');
            });            
          </script>      
      ";
    }
    echo $tasks;
  } elseif ($_GET["action"]=="services_list_filter_interface"){
    $dsp = new cw_Display_service_planning($d,$eh,$a);
    echo $dsp->display_service_list_filter_interface($_GET["timestamp"],$_GET["timestamp"]+5*WEEK);  
  } elseif ($_GET["action"]=="get_edit_service_times_interface"){
    //Editing shows requires admin rights
    if ($a->csps>=CW_A){
      $cs=new cw_Church_service($eh,$_GET["service_id"]);
      $t="
        <div class='modal_head'>Edit service times: ".$cs->get_info_string(true)."</div>
        <div class='modal_body'>
          <div id='dialogue'>
            <p>What would you like to do?</p>
            <p>
              <input type='button' class='button' id='change_showtime' value='Move a show'/>
              <input type='button' class='button' id='add_show' value='Add a show'/>
              <input type='button' class='button' id='delete_show' value='Delete a show'/>
            </p>
          </div>
        </div>
        
        <script type='text/javascript'>
          $('#change_showtime').click(function(){
            $('#dialogue').load('".CW_AJAX."ajax_service_planning.php?action=get_select_show_to_edit_interface&service_id=".$_GET["service_id"]."&action_type=move');
          });
          $('#add_show').click(function(){
            $('#dialogue').load('".CW_AJAX."ajax_service_planning.php?action=get_select_showtime_interface&service_id=".$_GET["service_id"]."&action_type=add');
          });
          $('#delete_show').click(function(){
            $('#dialogue').load('".CW_AJAX."ajax_service_planning.php?action=get_select_show_to_edit_interface&service_id=".$_GET["service_id"]."&action_type=delete');
          });
          
        </script>    
      ";
    } else {
      $t=CW_ERR_INSUFFICIENT_PRIVILEGES;
    }
    echo $t;
  } elseif ($_GET["action"]=="get_select_show_to_edit_interface"){
    $cs=new cw_Church_service($eh,$_GET["service_id"]);
    $y="<option value=''>(select the show you wish to ".$_GET["action_type"]."...)</option>";
    foreach ($cs->event_records as $v){
      $y.="<option value=\"".$v["id"]."\">".date("l F j Y g:ia",$v["timestamp"])."</option>";
    }
    $t="
      <p>".$cs->get_info_string()."</p>
      <select id='shows'>$y</select>
      <script type='text/javascript'>
        $('#shows').change(function(){
          var event_id=$(this).val();
          if ('".$_GET["action_type"]."'=='delete'){
            if (".sizeof($cs->event_records)."==1){
              if (confirm('Are you sure you want to delete the only show for this service? This will delete the entire service.')){
                //Delete entire service
                $.post('".CW_AJAX."ajax_service_planning.php?action=delete_service&service_id=".$_GET["service_id"]."',{},function(ret){
                  if (ret=='OK'){
                    $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list');
                    close_modal(true);
                  } else {
                    alert(ret);
                  }                        
                });
              }
            } else {
              //Delete single show
              $.post('".CW_AJAX."ajax_service_planning.php?action=delete_show&event_id='+event_id,{},function(ret){
                if (ret=='OK'){
                  $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list');
                  close_modal(true);
                } else {
                  alert(ret);
                }                        
              });
            }
          } else {
            //Move / reschedule show
            $('#dialogue').load('".CW_AJAX."ajax_service_planning.php?action=get_select_showtime_interface&service_id=".$_GET["service_id"]."&action_type=move&event_id='+event_id);
          }
        });
      </script>
    ";
    echo $t;
  } elseif ($_GET["action"]=="get_select_showtime_interface"){
    if ($_GET["action_type"]=="move"){
      //Reschedule/move existing show
      $erec=$eh->events->get_event_record($_GET["event_id"]);
      $header="<p>Select new date and time for the ".date("l F j Y g:ia",$erec["timestamp"])." show</p>";
      $date=date("m/d/Y",$erec["timestamp"]);
      $hour=date("G",$erec["timestamp"]);
      $minute=date("i",$erec["timestamp"]);
      $submit="Reschedule this show";
    } else {
      //Schedule new show
      $header="<p>Select new date and time for the new show</p>";
      $submit="Add show";
      $date="(click to select date)";
      $hour=CW_DEFAULT_SERVICE_START_TIME;
    }    
    $t="  $header  
          <p>
            <div style='float:left;'>
              <input id='specific_date' type='text' value='".$date."' style='width:170px;'/> at 
              <select id='hour'>".hours_for_select($hour)."</select> : 
              <select id='minute'>".minutes_for_select($minute)."</select> (hh:mm)
            </div>
            <div style='float:right;padding-right:5px;'>
              <input type='button' class='button' id='save_show' value='$submit'/>
            </div>
          </p>    
          <script type='text/javascript'>
            $('#specific_date').datepicker();
            $('#save_show').click(function(){
              $.post('".CW_AJAX."ajax_service_planning.php?action=move_or_add_show&action_type=".$_GET["action_type"]."&event_id=".$_GET["event_id"]."&service_id=".$_GET["service_id"]."',{ date:$('#specific_date').val(),hour:$('#hour').val(),minute:$('#minute').val() },function(ret){
                $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list');
                alert(ret);
                close_modal(true);                
              });
            });
          </script>
    ";
    echo $t;
  } elseif ($_GET["action"]=="move_or_add_show"){
    if ($a->csps>=CW_A){  
      //$_GET["action_type"] has 'move' or 'add', and $_POST[] has "date" (mm/dd/yyyy), "hour", "minute"
      $date=explode('/',$_POST["date"]);
      if (($date[0]>0) && ($date[1]>0) && ($date[2]>0)){
        $timestamp=mktime($_POST["hour"],$_POST["minute"],0,$date[0],$date[1],$date[2]);
        if ($_GET["action_type"]=="move"){
          $res=$eh->move_show($_GET["event_id"],$timestamp);
        } else {
          $res=$eh->add_show($_GET["service_id"],$timestamp);    
        } 
        echo "Scheduling system response:                                          \n\n".$res;  
      } else {
        echo "Error: could not add show - you did not select a valid date";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }    
  } elseif ($_GET["action"]=="delete_show"){
    if ($a->csps>=CW_A){  
      //Admin rights required to delete show
      if ($eh->delete_show($_GET["event_id"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to delete the show";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }    
  } elseif ($_GET["action"]=="display_manage_element_group_templates_dialogue"){
    //Get templates
    $templates_options=$eh->get_group_templates_for_ul();
    echo "
      <div class='modal_head'>Manage element group templates</div>
      <div class='modal_body'>
        <select id='element_group_templates'>$templates_options</select>
        or <a id='add_template_link' href=''>add a new element group template</a>
        <div id='template_record'>
        </div>
      </div>
      
      <script type='text/javascript'>
        $('#element_group_templates').change(function(){
          $('#template_record').load('".CW_AJAX."ajax_service_planning.php?action=get_edit_element_group_template_interface&element_group_template=' + $(this).val());  
        });
        $('#add_template_link').click(function(e){
          e.preventDefault();
          $.post('".CW_AJAX."ajax_service_planning.php?action=add_element_group_template',{},function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            } else {
              alert('Added new template');
              $('#manage_element_group_templates_link').click(); //Reload manage_templates interface
            }
          });                    
        });
      </script>
    ";
  } elseif ($_GET["action"]=="get_edit_element_group_template_interface"){
    $template=$eh->church_services->get_group_template_record($_GET["element_group_template"]);
    $t="";
    if (is_array($template)){
      foreach ($template as $k=>$v){
        if ($k!="id"){
          $t.="<tr><td>$k:</td><td><input style='width:350px;' class='datafield' id='input_$k' type='text' value=\"$v\"/></td></tr>";
        }
      }
      $t="
        <table>$t</table>
        <input type='button' class='button' id='done' value='Done'/>
        <input type='button' class='button' id='delete_template' value='Delete this template'/>
        
        <script type='text/javascript'>
          $('.datafield').blur(function(){
            var fieldname=$(this).attr('id').substr(6);
            var fieldvalue=$(this).val();
            $.post('".CW_AJAX."ajax_service_planning.php?action=update_element_group_template&element_group_template=".$_GET["element_group_template"]."',{ field:fieldname,value:fieldvalue },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });
          });
          $('#delete_template').click(function(){
            $.post('".CW_AJAX."ajax_service_planning.php?action=delete_element_group_template&element_group_template=".$_GET["element_group_template"]."',{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                $('#manage_element_group_templates_link').click(); //Reload manage_templates interface
              }
            });
          });
          $('#done').click(function(){
            close_modal();
          });
        </script>      
      ";
    }
    $t="
      <div class='expl' style='margin-right:5px;'>
        <p>Warning: experts only! Please do not make changes here unless you have understood the syntax for these fields.</p>
        <p>Invalid values will cause malfunction of the service planning module!</p>
        <p>
          Display tables:
          <ul>
            <li><a href='?action=showtable&table=positions' target='_blank'>positions</a></li>
            <li><a href='?action=showtable&table=service_element_types' target='_blank'>service_element_types</a></li>
          </ul>
        </p>        
      </div>
      $t
    ";
    echo $t;
  } elseif ($_GET["action"]=="add_element_group_template"){
    if ($a->csps>=CW_A){
      if ($eh->church_services->add_group_template('[new element group template]')){
        echo "OK";
      } else {
        echo "Error: could not add element group template";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="update_element_group_template"){
    if ($a->csps>=CW_A){    
      $e=array();
      $e[$_POST["field"]]=$_POST["value"];
      if ($eh->church_services->update_group_template($_GET["element_group_template"],$e)){
        echo "OK";
      } else {
        echo "Error: could not update service template #".$_GET["element_group_template"];    
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="delete_element_group_template"){
    if ($a->csps>=CW_A){    
      if ($eh->church_services->delete_group_template($_GET["element_group_template"])){
        echo "OK";    
      } else {
        echo "Error: could not delete element group template #".$_GET["element_group_template"];
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="display_manage_service_templates_dialogue"){
    //Get templates
    $templates_options=$eh->get_church_service_templates_for_ul();
    echo "
      <div class='modal_head'>Manage service templates</div>
      <div class='modal_body'>
        <select id='service_templates'>$templates_options</select>
        or <a id='add_template_link' href=''>add a new service template</a>
        <div id='iteration_selector'>
        </div>
      </div>
      
      <script type='text/javascript'>
        $('#service_templates').change(function(){
          $('#iteration_selector').load('".CW_AJAX."ajax_service_planning.php?action=get_edit_template_interface&service_template=' + $(this).val());  
        });
        $('#add_template_link').click(function(e){
          e.preventDefault();
          $.post('".CW_AJAX."ajax_service_planning.php?action=add_service_template',{},function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            } else {
              alert('Added new template');
              $('#manage_service_templates_link').click(); //Reload manage_templates interface
            }
          });                    
        });
      </script>
    ";
  } elseif ($_GET["action"]=="get_edit_template_interface"){
    $template=$eh->church_services->get_church_service_template_record($_GET["service_template"]);
    $t="";
    if (is_array($template)){
      foreach ($template as $k=>$v){
        if ($k!="id"){
          $t.="<tr><td>$k:</td><td><input style='width:350px;' class='datafield' id='input_$k' type='text' value=\"$v\"/></td></tr>";
        }
      }
      $t="
        <table>$t</table>
        <input type='button' class='button' id='done' value='Done'/>
        <input type='button' class='button' id='delete_template' value='Delete this template'/>
        
        <script type='text/javascript'>
          $('.datafield').blur(function(){
            var fieldname=$(this).attr('id').substr(6);
            var fieldvalue=$(this).val();
            $.post('".CW_AJAX."ajax_service_planning.php?action=update_service_template&service_template=".$_GET["service_template"]."',{ field:fieldname,value:fieldvalue },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });
          });
          $('#delete_template').click(function(){
            $.post('".CW_AJAX."ajax_service_planning.php?action=delete_service_template&service_template=".$_GET["service_template"]."',{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                $('#manage_service_templates_link').click(); //Reload manage_templates interface
              }
            });
          });
          $('#done').click(function(){
            close_modal();
          });
        </script>      
      ";
    }
    $t="
      <div class='expl' style='margin-right:5px;'>
        <p>Warning: experts only! Please do not make changes here unless you have understood the syntax for these fields.</p>
        <p>Invalid values will cause malfunction of the service planning module!</p>
        <p>
          Display tables:
          <ul>
            <li><a href='?action=showtable&table=positions' target='_blank'>positions</a></li>
            <li><a href='?action=showtable&table=service_element_types' target='_blank'>service_element_types</a></li>
            <li><a href='?action=showtable&table=group_templates' target='_blank'>group_templates</a></li>
            <li><a href='?action=showtable&table=rooms' target='_blank'>rooms</a></li>
          </ul>
        </p>        
      </div>
      $t
    ";
    echo $t;
  } elseif ($_GET["action"]=="add_service_template"){
    if ($a->csps>=CW_A){    
      if ($eh->church_services->add_church_service_template('[new service template]')){
        echo "OK";
      } else {
        echo "Error: could not add service template";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="update_service_template"){
    if ($a->csps>=CW_A){    
      $e=array();
      $e[$_POST["field"]]=$_POST["value"];
      if ($eh->church_services->update_church_service_template($_GET["service_template"],$e)){
        echo "OK";
      } else {
        echo "Error: could not update service template #".$_GET["service_template"];    
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="delete_service_template"){
    if ($a->csps>=CW_A){    
      if ($eh->church_services->delete_church_service_template($_GET["service_template"])){
        echo "OK";    
      } else {
        echo "Error: could not delete service template #".$_GET["service_template"];
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }  
  } elseif ($_GET["action"]=="display_schedule_services_dialogue"){
    //Get templates
    $templates_options=$eh->get_church_service_templates_for_ul();
    echo "
      <div class='modal_head'>Schedule services</div>
      <div class='modal_body'>
        <select id='service_templates'>$templates_options</select>
        <div id='iteration_selector'>
        </div>
        <div>
          <input type='checkbox' id='suppress_needed_positions' checked='checked'/> suppress 'needed positions' field from template
        </div>
      </div>
      
      <script type='text/javascript'>
        $('#service_templates').change(function(){
          $('#iteration_selector').load('".CW_AJAX."ajax_service_planning.php?action=get_iteration_selector_interface&service_template=' + $(this).val());  
        });
      </script>
    ";
  } elseif ($_GET["action"]=="get_iteration_selector_interface") {
    $template=$eh->church_services->get_church_service_template_record($_GET["service_template"]);
    if (is_array($template)){
      
      //Analyze iteration rules
      $suggested_timestamps=$eh->church_services->get_suggested_timestamps_for_service_template($_GET["service_template"],10);
      if (!is_array($suggested_timestamps)){
        $rule_text="no iteration rules set";
        
        $interface="
          <p>This service template has no iteration rules for semi-automatic scheduling. Please pick a date and a time manually.</p>
          <div style='background:#EEE;width:848px;padding:5px;text-align:center;'>
            Schedule a single-show instance for the ".$template["service_name"]." on  
            <input id='specific_date' type='text' value='(click to pick a date)' style='width:170px;'/> at 
            <select id='hour'>".hours_for_select(11)."</select> : 
            <select id='minute'>".minutes_for_select()."</select> (hh:mm)
          </div>
            
          <div style='height:35px;padding-top:10px;padding-right:7px;'>
            <input type='button' class='button' id='execute_scheduling_request' value='execute scheduling request' style='float:right'/>
          </div>           
          
          <script type='text/javascript'>
            $('#specific_date').datepicker();
            $('#execute_scheduling_request').click(function(){
              var suppress_needed_positions=0;
              if ($('#suppress_needed_positions').is(':checked')){
                suppress_needed_positions=1;
              }
              show_please_wait('Generating service(s)...please wait');
              $.post('".CW_AJAX."ajax_service_planning.php?action=execute_service_scheduling_request&type=single_instance&service_template=".$_GET["service_template"]."',{ date:$('#specific_date').val(),hour:$('#hour').val(),minute:$('#minute').val(),suppress_needed_positions:suppress_needed_positions },function(rtn){
                hide_please_wait();
                alert(rtn);     
                $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list');
                close_modal(true);  
              });
            });
          </script>
        ";
        
      } else {
        //Got valid iteration rule(s)
        $interface="
          Scheduling suggestions for the ".$template["service_name"]." (check/uncheck as needed):  
          <div id='dates_checkboxes' style='width:400px;padding:5px 30px;margin:5px auto;background:#eee;'>
            <p class='small_note'><a href='' id='sel_all'>select all</a> | <a href='' id='desel_all'>deselect all</a></p>
            ".get_checkboxes_for_timestamps($suggested_timestamps,true,"timestamp_cb")."              
          </div>

          <div style='height:35px;padding-top:10px;padding-right:7px;'>
            <input type='button' class='button' id='execute_scheduling_request' value='execute scheduling request' style='float:right'/>
          </div>           
          
          <script type='text/javascript'>
            $('#sel_all').click(function(e){
              e.preventDefault();
              $('.timestamp_cb').attr('checked','checked');
            });
            $('#desel_all').click(function(e){
              e.preventDefault();
              $('.timestamp_cb').removeAttr('checked');
            });

            $('#execute_scheduling_request').click(function(){
              //Submit csl of the values of the checked checkboxes
              var checked_timestamps='';
              $('.timestamp_cb').each(function(){
                if ($(this).attr('checked')){
                  checked_timestamps=checked_timestamps + ',' + $(this).attr('id').substr(3);                
                }
              });
              if (checked_timestamps!=''){
                checked_timestamps=checked_timestamps.substr(1); //cut off first comma                
              }
              var suppress_needed_positions=0;
              if ($('#suppress_needed_positions').is(':checked')){
                suppress_needed_positions=1;
              }
              show_please_wait('Generating service(s)...please wait');
              $.post('".CW_AJAX."ajax_service_planning.php?action=execute_service_scheduling_request&service_template=".$_GET["service_template"]."',{ checked_timestamps:checked_timestamps,suppress_needed_positions:suppress_needed_positions },function(rtn){
                hide_please_wait();
                alert(rtn);  
                $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list');
                close_modal(true);  
              });
            });
          </script>
          
        ";
      }
      echo "
        $interface
        
      ";
    }
  } elseif ($_GET["action"]=="execute_service_scheduling_request") {
    if ($a->csps>=CW_A){    
      if ($_GET["type"]!="single_instance"){
        if (!empty($_POST["checked_timestamps"])){
          //Multiple services/instances as per a template iteration rule
          //Got a template id in $_GET["service_template"] and a CSL of instance_ids.timestamps in $_POST["checked_timestamps"]
          $all_timestamps=explode(',',$_POST["checked_timestamps"]);
          //Go through each of the timestamps, and regroup them into service instances
          $instances=array();
          foreach ($all_timestamps as $v){
            $segments=explode('.',$v);
            //$segments[0] has service instance, $segment[1] the timestamp
            if (!empty($instances[$segments[0]])){
              $instances[$segments[0]].=",";
            }    
            $instances[$segments[0]].=$segments[1];
          }
          //Now instances is array of timestamp-csls
          $responses=array();
          foreach ($instances as $v){
            $timestamps=explode(',',$v);
            $responses[$timestamps[0]]=$eh->schedule_services($_GET["service_template"],$timestamps,"",($_POST["suppress_needed_positions"]>0));
          }
          $t="";
          foreach ($responses as $k=>$v){
            $t.="\nScheduled ".date("F j",$k)." service:".$v;
          }
        } else {
          $t="No services were scheduled because you did not select any dates and times";
        } 
      } else {
        //Single instance with directly specified date
        $t=$eh->schedule_services($_GET["service_template"],array(strtotime($_POST["date"]." ".$_POST["hour"].":".$_POST["minute"])),"");    
      }
      echo "Scheduling system response:".str_repeat(" ",50)."\n".$t;
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_service"){
    if ($a->csps>=CW_A){    
      if ($eh->delete_church_service($_GET["service_id"])){
        echo "OK";
      } else {
        echo "An error occured while trying to delete this service (service-id#".$_GET["service_id"].")";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_service_title"){
    $cs=new cw_Church_service($eh,$_GET["service_id"]);
    $edit_title_link="";
    if ($a->csps>=CW_E){
      $edit_title_link="<a class='small_note' style='font-weight:normal;cursor:pointer;' id='edit_title_link'>edit&nbsp;service&nbsp;title/name</a>";        
    }
    echo "
      ".$cs->service_name.": ".$cs->title." $edit_title_link
      <script type='text/javascript'>
        $('#edit_title_link').click(function(e){
          e.preventDefault();
          var url='".CW_AJAX."ajax_service_planning.php?action=get_edit_service_title_interface&service_id=".$_GET["service_id"]."';
          show_modal(url,50,350,600,'edit_service_title');
        });
      </script>      
    ";
  } elseif ($_GET["action"]=="get_edit_service_title_interface"){
    if ($a->csps>=CW_E){    
      $cs=new cw_Church_service($eh,$_GET["service_id"]);  
      $template=$eh->church_services->get_church_service_template_record($cs->service_record["template_id"]);
      $t="
        <div class='modal_head'>Edit service title and name</div>      
        <div class='modal_body'>
          <div class='expl' style='margin:10px;'>
            <p>The service title defaults to the sermon title, unless you change it here.</p>
            <p class='red'>Caution: the service name should not be changed unless you are doing a single-instance event based on a generic service template. If this service happens more than once, you should create a service template for it.</p>
          </div>
          <div>
            Service title:<br/>
            <input type='text' style='width:300px;' id='service_title_input' value=\"".$cs->title."\"/>
          </div>
          <div>
            Service name: <span class='small_note'><a id='restore_service_name' href=''>restore default</a></span><br/>      
            <input type='text' style='width:300px;' id='service_name_input' value=\"".$cs->service_name."\"/>
          </div>
          <div style='margin-top:15px;'><input type='button' class='button' id='save_service_title' value='save'/></div>
        </div>      
        <script type='text/javascript'>

          $('input').focus(function(){
            $(this).select();
          }).keypress(function(e){
            if ((e.keyCode==13)){
              $('#save_service_title').click();
            }          
          });

          $('#service_title_input').focus();

          $('#save_service_title').click(function(){
            $.post('".CW_AJAX."ajax_service_planning.php?action=save_service_title&service_id=".$_GET["service_id"]."',{ title:$('#service_title_input').val(),name:$('#service_name_input').val() },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                close_modal();
              }
            });
          });
          
          $('#restore_service_name').click(function(e){
            e.preventDefault();
            $('#service_name_input').val('".$template["service_name"]."');
          });
        </script>
      ";
      echo $t;
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_service_title") {
    if ($a->csps>=CW_E){    
      $e=array();
      $e["title"]=$_POST["title"];
      $e["service_name"]=$_POST["name"];
      if ($eh->church_services->update_church_service_record($_GET["service_id"],$e)){
        echo "OK";
        $mark_service_update=true;
      } else {
        echo "Error: could not update service record";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="display_people_dialogue") {
    if ($a->csps>=CW_E){    
      if ($eh->check_sync_mark($_GET["service_id"],"position_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        $a_default="(begin to type a position title)";
        $t="
          <div class='modal_head'>Schedule a person</div>
          
          <div class='modal_body'>
            <div>
              Type a position
              <input id='positions_autocomplete' type='text' style='width:330px;' value='$a_default'/>
            </div>
            <div id='positions_div'>
              or select from list
              <select style='width:330px;' id='positions'><option value=''>(select a position from the list...)</option>".$eh->event_positions->get_position_options_for_select()."</select>
            </div>
            <p id='person_selector'>
            </p>
          </div>
          
          <script type='text/javascript'>
            //When user selects a position, find available people for it
            $('#positions').change(function(){ 
              $('#person_selector').load('".CW_AJAX."ajax_service_planning.php?action=get_available_persons_for_position&pos_id=' + $(this).val() +'&service_id=".$_GET["service_id"]."');
            });          
                                       
            $(function(){
              var availableTags = [".$eh->event_positions->get_all_position_titles_for_js_array()."];
              $('#positions_autocomplete').autocomplete({ source: availableTags,autoFocus:true });          
            });
            
            $('#positions_autocomplete').keydown(function(){
              if ($(this).val()=='$a_default'){                  
                $(this).val('');
              }
                 
              if (($('#positions_autocomplete').val()!='$a_default') && ($('#positions_autocomplete').val()!='')){
                $('#positions option').each(function(){
                  if ($(this).html()== $('#positions_autocomplete').val()){
                    $(this).attr('selected','selected');
                    $('#positions').change();
                    $('#positions_div').remove();
                    //alert('Match found');
                  }
                });                    
              }
            });
            
            $('#positions_autocomplete').focus();
          </script>
          ";
        echo $t;
      } else {
        echo "Schedule a person: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_available_persons_for_position"){
    echo "
      <div id='person_div'>
        and select a person
        <select id='available_persons' style='width:330px;'>
          <option value=''>(select a person...)</option>".$eh->get_all_persons_for_position_for_select($_GET["pos_id"])."
          <option value='guest' style='color:#F66;'>-- use guest --</option>
        </select>
        <p id='guest_name_p'>
        </p>
      </div>
      ";
    //Js to act after selection
    echo " 
      <script type='text/javascript'>
        $('#available_persons').change(function(){
          if ($(this).val()=='guest'){
            $('#guest_name_p').load('".CW_AJAX."ajax_service_planning.php?action=get_guest_name_input&service_id=".$_GET["service_id"]."&pos_id=".$_GET["pos_id"]."');  
          } else {
            $.post('".CW_AJAX."ajax_service_planning.php?action=schedule_person&pos_id=".$_GET["pos_id"]."&person_id=' + $(this).val() +'&service_id=".$_GET["service_id"]."', {}, function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              close_modal();
            });
          }
        });
        
      </script>";
  } elseif ($_GET["action"]=="get_guest_name_input"){
    echo "
      or type a guest name (enter to submit)
      <input type='text' style='width:330px;' value='' id='guest_name'/>
      <p id='guest_email_p'>
      <script type='text/javascript'>
        $('#guest_name').focus();
        $('#guest_name').keydown(function(e){
          if ((e.keyCode==13) && ($(this).val()!='')){
            $('#guest_email_p').load('".CW_AJAX."ajax_service_planning.php?action=get_guest_email_input&guest_name=' + encodeURIComponent($(this).val()) +'&service_id=".$_GET["service_id"]."&pos_id=".$_GET["pos_id"]."');  
          }          
        });
      </script>
    ";
  } elseif ($_GET["action"]=="get_guest_email_input"){
    echo "
      guest's email for auto-confirm (enter to submit)
      <input type='text' style='width:330px;' value='' id='guest_email'/>
      <script type='text/javascript'>
        $('#guest_email').focus();
        $('#guest_email').keydown(function(e){
          if (e.keyCode==13){
            $.post('".CW_AJAX."ajax_service_planning.php?action=schedule_person&pos_id=".$_GET["pos_id"]."&person_id=guest&guest_name=".$_GET["guest_name"]."&guest_email=' + $(this).val() +'&service_id=".$_GET["service_id"]."', {}, function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              close_modal();
            });
          }          
        });
      </script>
    ";
  } elseif ($_GET["action"]=="schedule_person"){
    if ($a->csps>=CW_E){    
      if ($eh->check_sync_mark($_GET["service_id"],"position_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        //Execute a scheduling request    
        if ($_GET["person_id"]=="guest"){
          //Guest person used, name will be in $_GET["guest_name"]
          //Create guest person and get id
          $guest_id=$a->personal_records->add_guest($_GET["guest_name"],$_GET["guest_email"]);
          $person_id_for_pos_to_services_record=($guest_id*(-1)); //Use negative reference to identify guests
        } else {
          $person_id_for_pos_to_services_record=$_GET["person_id"]; //If not a guest, just take the person_id that has been provided
        }
        //Schedule person from database (default, hopefully)
        if ($eh->event_positions->add_position_to_service($_GET["pos_id"],$_GET["service_id"],$person_id_for_pos_to_services_record,time(),$a->cuid)){
          //Does an available volunteers record happen to exist with pos_id, service_id and person_id? If so, delete it
          $eh->event_positions->remove_volunteer($_GET["person_id"],$_GET["service_id"],$_GET["pos_id"]);
          //If this position was marked open, close one instance of it at any rate (whether we just scheduled a volunteer or not)
          $eh->church_services->remove_open_position($_GET["service_id"],$_GET["pos_id"]);
          //See if this person should be assigned as in charge of one or more elements
          $eh->update_people_in_charge($_GET["service_id"]);
          echo "OK";
        } else {
          echo "Error: could not complete scheduling request.";
        }
        $mark_service_update=true;
      } else {
        echo "Schedule a person: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }
  } elseif ($_GET["action"]=="get_open_positions"){
    $service=new cw_Church_service($eh,$_GET["service_id"]);
    $no_pos=$service->get_number_of_needed_positions();
    if ($no_pos==1){
      $v="One position is marked open";
    } else {
      $v="$no_pos positions are marked open";    
    }
    if (($no_pos>0)){
      echo "<div id='open_positions_summary'>$v: <a href='' id='close_all_open_positions'>Close all</a> | <a href='' id='edit_open_positions'>Edit</a></div>
        <script type='text/javascript'>
          $('#close_all_open_positions').click(function(e){
            e.preventDefault();
            if (confirm('Are you sure you want to close all $no_pos open positions for this service?')){
              $.get('".CW_AJAX."ajax_service_planning.php?action=close_all_open_positions&service_id=".$_GET["service_id"]."',function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_position_list();
              });
            }
          });          
        </script>
      ";    
    } else {
      echo "<div id='open_positions_summary' style='background:#AFA;color:green;'>No open positions: <a class='inverse' href='' id='edit_open_positions'>Edit</a></div>";        
    }
    echo "
      <script type='text/javascript'>
        $('#edit_open_positions').click(function(e){
          e.preventDefault();
          show_modal('".CW_AJAX."ajax_service_planning.php?action=display_open_positions_dialogue&service_id=".$_GET["service_id"]."',50,150,950);      
        });        
      </script>
    ";
  } elseif ($_GET["action"]=="display_open_positions_dialogue"){

    $pos=$eh->event_positions->get_all_positions(0,1); //Get all active positions
    $all_position_labels="";
    $last_pos_type=null;
    foreach ($pos as $v){
      $x="";
      $current_pos_type=$eh->event_positions->get_position_type($v["id"]);
      if ($last_pos_type!=$current_pos_type){
        //New position type, insert divider
        if ($last_pos_type==null){
          $enddiv="";
        } else {
          $enddiv="</div>";
        }
        $x.="$enddiv<div style='font-size:80%'>".$eh->event_positions->get_position_type_title($current_pos_type)." positions </div><div class='positions_to_add'>";
        $cutcomma=true;                  
      }        
      if ($cutcomma){
        $comma="";
        $cutcomma=false;
      } else {
        $comma=", ";
      } 
      $x.="$comma<span class='apos' id='apos_".$v["id"]."'>".$v["title"]."</span>";
      $all_position_labels.=$x;
      $last_pos_type=$eh->event_positions->get_position_type($v["id"]);
    }    
    $all_position_labels.="</div>";

    
    $t="
      <div class='modal_head'>Edit open positions</div>
      <div class='modal_body'>
        <div>
          Click any position label on the left to add it to the list of open positions on the right.
          <div>
            <div id='all_available_positions'>
              <h4 style='text-align:center;'>Available positions</h4>
              $all_position_labels
            </div>
            <div id='positions_to_open'>
            </div>
            <div style='clear:both;'></div>
          </div>
          <div style='padding:3px;margin-top:10px;'>
            <div style='float:right;margin:8px;margin-top:70px;'><input type='button' class='button' id='done' value='Done'/></div>
            <div class='expl' style='float:right;width:810px;margin-right:20px;'>
              <p>To mark a position as open means that volunteers can see that you are looking for a person to fill the position. When people make themselves available you will see that in the service plan and then you can decide whether or not to actually schedule them.</p>
              <p>To mark a position open for multiple people, simply click it again on the left side.</p>
              <p>A click on a position on the right side will close the position. When (enough) people have made themselves available for a position, it will be marked green on the right.</p>
            </div>            
          </div>
        </div>
      </div>
      
      <script type='text/javascript'>
        function reload_positions_to_open(){
          $('#positions_to_open').load('".CW_AJAX."ajax_service_planning.php?action=get_open_positions_for_dialogue&service_id=".$_GET["service_id"]."');
        }  
        
        reload_positions_to_open();
        
        $('.apos').click(function(e){
          var apos_id=$(this).attr('id').substr(5);
          $.get('".CW_AJAX."ajax_service_planning.php?action=add_open_position&service_id=".$_GET["service_id"]."&pos_id='+apos_id,function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_positions_to_open();
          });
        });
        
        $('#done').click(function(){
          close_modal();
        });
        
      </script>    
    
    ";
    echo $t;
  } elseif ($_GET["action"]=="get_open_positions_for_dialogue"){
    $service=new cw_Church_service($eh,$_GET["service_id"]);
    $needed_positions=$service->get_array_of_needed_positions();
    $pos=$eh->event_positions->get_all_positions(0,1); //Get all active positions
    $all_position_labels="";
    $last_pos_type=null;
    foreach ($pos as $v){
      $x="";
      $current_pos_type=$eh->event_positions->get_position_type($v["id"]);
      if ($last_pos_type!=$current_pos_type){
        //New position type, insert divider
        if ($last_pos_type==null){
          $enddiv="";
        } else {
          $enddiv="</div>";
        }
        $x.="$enddiv<div style='font-size:80%'>".$eh->event_positions->get_position_type_title($current_pos_type)." positions </div><div class='positions_to_open'>";
        $cutcomma=true;                  
      }        
      $no_open_instances=$eh->church_services->get_number_of_open_instances_for_position($_GET["service_id"],$v["id"]);
      if ($no_open_instances>0){
        if ($no_open_instances>1){
          $n_addition=" (<span style='font-weight:bold;'>".$no_open_instances."x</span>)";
        } else {
          $n_addition="";
        }
        if ($cutcomma){
          $comma="";
          $cutcomma=false;
        } else {
          $comma=", ";
        } 
        //number of volunteers on this position?
        $no_vols=sizeof($eh->event_positions->who_has_volunteered($_GET["service_id"],$v["id"]));
        if ($no_vols>=$needed_positions[$v["id"]]){
          $pclass="volunteer";
        } else {
          $pclass="no_volunteer";
        }
        $x.="$comma<span class='opos $pclass' id='opos_".$v["id"]."'>".$v["title"]."$n_addition</span>";
      } else {
        //$x.="$comma<span id='".$v["id"]."'>".$v["title"]." (0)</span>";        
      }
      $all_position_labels.=$x;
      $last_pos_type=$eh->event_positions->get_position_type($v["id"]);
    }    
    $all_position_labels.="</div>";

    
    $t="
      <h4 style='text-align:center;'>Open positions for this service</h4>
      $all_position_labels      
      <script type='text/javascript'>

        $('.opos').click(function(e){
          var opos_id=$(this).attr('id').substr(5);
          $.get('".CW_AJAX."ajax_service_planning.php?action=remove_open_position&service_id=".$_GET["service_id"]."&pos_id='+opos_id,function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_positions_to_open();
          });
        });
        
      </script>    
    
    ";
    echo $t;
  } elseif ($_GET["action"]=="add_open_position"){
    if ($a->csps>=CW_E){
      if ($eh->church_services->add_open_position($_GET["service_id"],$_GET["pos_id"])){
        echo "OK";
      } else {
        echo "Error: Could not add this position to the list of open positions";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }
    $mark_service_update=true;
  } elseif ($_GET["action"]=="remove_open_position"){
    if ($a->csps>=CW_E){
      if ($eh->church_services->remove_open_position($_GET["service_id"],$_GET["pos_id"])){
        echo "OK";
      } else {
        echo "Error: Could not remove this position from the list of open positions";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;    
    }
    $mark_service_update=true;
  } elseif ($_GET["action"]=="close_all_open_positions"){
    if ($a->csps>=CW_E){
      if ($eh->church_services->close_all_open_positions($_GET["service_id"])){
        echo "OK";
      } else {
        echo "Error: could not close open positions on this service";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;        
    }                
    $mark_service_update=true;
  } elseif ($_GET["action"]=="get_available_volunteers"){                      
    $available_volunteers=$eh->event_positions->get_number_of_volunteers($_GET["service_id"]);
    if ($available_volunteers==1){
      $v="One volunteer is available";
    } else {
      $v="$available_volunteers volunteers are available";
    }
    if ($available_volunteers>0){
      echo "
        <div id='available_volunteers_summary'>
          $v:
          <a href='' id='schedule_volunteers_link'>Schedule volunteers</a>
        </div>
        <script type='text/javascript'>
          $('#schedule_volunteers_link').click(function(e){
            e.preventDefault();
            show_modal('".CW_AJAX."ajax_service_planning.php?action=display_schedule_volunteers_dialogue&service_id=".$_GET["service_id"]."',50,150,950);      
          });
        </script>
      ";
    }
  } elseif ($_GET["action"]=="display_schedule_volunteers_dialogue"){
    $t="
      <div class='modal_head'>Schedule available volunteers</div>
      <div class='modal_body'>
        These people have declared their availability to serve in this service.
        <div id='list_of_available_volunteers' style='width:900px;padding:5px;'>
        </div>
        <input id='done' type='button' class='button' value='Close'/>
      </div>
      
      <script type='text/javascript'>
        reload_list_of_available_volunteers();
        
        function reload_list_of_available_volunteers(){
          $('#list_of_available_volunteers').load('".CW_AJAX."ajax_service_planning.php?action=get_available_volunteers_list&service_id=".$_GET["service_id"]."');
        }
        
        $('#done').click(function(){
          close_modal();
        });
      </script>    
    
    ";
    echo $t;
  } elseif ($_GET["action"]=="get_available_volunteers_list"){
    //Get all available_volunteer records for this service
    $r=$eh->event_positions->get_volunteers_for_service($_GET["service_id"]);
    $table="";
    $s="";
    if (is_array($r)){
      if (sizeof($r)>0){
        foreach ($r as $v){
          $list.="<li id='avr_".$v["id"]."'>
                    click to <span class='underline'>schedule <span style='color:navy;'>".$a->personal_records->get_name_first_last($v["person_id"])."</span> as <span style='color:navy;'>".$eh->event_positions->get_position_title($v["position_id"])."</span></span>
                    <span class='gray small_note' style='vertical-align:middle;'>(declared availability ".getHumanReadableLengthOfTime(time()-$v["volunteered_at"])." ago)</span>
                  </li>";    
          $s.="
            $('#avr_".$v["id"]."').click(function(e){
              var person_id=".$v["person_id"].";
              var service_id=".$_GET["service_id"].";
              var position_id=".$v["position_id"].";
              $.get('".CW_AJAX."ajax_service_planning.php?action=schedule_person&person_id='+person_id+'&service_id='+service_id+'&pos_id='+position_id,function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_list_of_available_volunteers();
                reload_position_list();
              });
            });
          "; 
        }
        $list="<ul>$list</ul>";
        echo "
          $list
          <script type='text/javascript'>$s</script>
        ";      
      } else {
        //No volunteer left in the list - send command to close modal
        echo "
          <script type='text/javascript'>
            close_modal();
          </script>
        ";
      }
    } else {
      echo "Could not load list of volunteers";
    }  
  } elseif ($_GET["action"]=="get_position_list"){    
    //Get an array of position_type records
    if ($r=$eh->event_positions->get_position_types()){
      foreach ($r as $pos_type){
        //Get array of position ids that are of type $pos_type["id"] AND are used in service $_GET['$service_id']
        if ($r1=$eh->event_positions->get_positions_in_service($_GET["service_id"],$pos_type["id"])){
          if (sizeof($r1)>0){
            $t.="<div class='caption'>".$pos_type["title"]."</div>";      
          }
          foreach ($r1 as $pos_id){
            $r2=$eh->event_positions->get_positions_to_services_records($_GET["service_id"],$pos_id);
            $pos_title=$eh->event_positions->get_position_title($pos_id);
            //Need plural s?
            if (sizeof($r2)>1){
              $pos_title=plural($pos_title);
            }            
            $download_music_link="";
            if (($pos_type["title"]==CW_POSITION_TYPE_WORSHIP_TEAM) || ($pos_title==CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER)){
              //$download_music_link="<a href='' class='download_music_link' style='text-decoration:none;color:#444;' id='pos$pos_id'>download music</a>";
            }
            $t.="<div class='caption_b'>".$pos_title."<div style='float:right;'>$download_music_link</div></div>";
            //Get records from positions_to_services that match this service and position id ($w)
            if (!empty($r2)){
              foreach($r2 as $x){
                if ($x["last_notification"]==-1){
                  $div_class="not_notified";
                  //Not notified, but see if maybe we have (manual) (dis)confirmation anyways
                  if ($x["confirmed"]>0){
                    $div_class="confirmed";
                  } elseif ($x["confirmed"]<0){
                    $div_class="disconfirmed";
                  }                  
                } else {
                  if ($x["confirmed"]>0){
                    $div_class="confirmed";
                  } elseif ($x["confirmed"]<0){
                    $div_class="disconfirmed";
                  } else {
                    $div_class="pending";
                  }
                }
                $name=$a->personal_records->get_name_first_last($x["person_id"]); //supplies guest name if person_id<0
                if ($x["person_id"]>0){
                  //No guest
                  $guest_label="";
                } else {
                  $guest_label="<span class='gray'>(guest)</span> ";                
                }
                //See if we have a default email for this user (or guest)
                if ($a->get_cw_comm_email_for_user($x["person_id"])==""){
                  //No email
                  //In case we already have a confirmation or disconfirmation, just show that anyway
                  if ($x["confirmed"]>0){
                    $div_class="confirmed";                  
                  } elseif ($x["confirmed"]<0){
                    $div_class="disconfirmed";                  
                  } else {
                    //No email, and no (dis)confirmation: manual
                    $div_class="manual";                                     
                  }
                }
                //This a musician or worship leader? Then show music download option
                $download_music_for_person_link="<div id='pts".$x["id"]."' class='icon_space_holder'></div>";;
                if (($pos_type["title"]==CW_POSITION_TYPE_WORSHIP_TEAM) || ($pos_title==CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER)){
                  $download_music_for_person_link="<div id='pts".$x["id"]."' class='download_music_for_person'></div>";
                }
                
                //Only show option to remove person if this is an editor/admin AND the service is not in the past
                $link="";
                $service_is_past=$eh->church_services->service_is_past($_GET["service_id"]);                
                if (($a->csps>=CW_E) && (!$service_is_past)){    
                  $link="
                    <span class='gray'>|</span>
                    <div id='r".$x["id"]."' class='remove_position'></div>                  
                  ";
                }
                
                $t.="
                  <div class='border_bottom'>
                    <span class='data'>$guest_label$name</span> 
                    
                    $download_music_for_person_link
                    <span class='gray'>|</span>
                    <div id='c".$x["id"]."' class='$div_class'></div>
                    $link
                  </div>
                ";
              }            
            } 
          }                                                                                            
        }      
      }    
    }
    $s="
      <script type='text/javascript'>
        function cycle_confirmation_status(pos_to_services_id,e){
          if (e.altKey){
            $.post('".CW_AJAX."ajax_service_planning.php?action=cycle_confirmation_status&service_id=".$_GET["service_id"]."&pos_to_services_id=' + pos_to_services_id,{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                reload_position_list();
                reload_rehearsal_list();
                reload_notification_note();
                reload_service_order(get_element_visibility('#element_selector'));
              }
            });          
          } else {
            alert('To manually change a confirmation status, hold down the alt-key while clicking the icon');
          }
        }
        
      
        $('.pending').simpletip({ content:'Confirmation is pending', offset:[tip_offset,-24]}).click(function(e){ cycle_confirmation_status($(this).attr('id').substr(1),e) });
        $('.confirmed').simpletip({ content:'Position is confirmed', offset:[tip_offset,-24]}).click(function(e){ cycle_confirmation_status($(this).attr('id').substr(1),e) });
        $('.disconfirmed').simpletip({ content:'This person cannot fill this role', offset:[tip_offset,-24]}).click(function(e){ cycle_confirmation_status($(this).attr('id').substr(1),e) });
        $('.manual').simpletip({ content:'We have no email address for this person, please confirm manually.', offset:[tip_offset,-24]}).click(function(e){ cycle_confirmation_status($(this).attr('id').substr(1),e) });
        $('.not_notified').simpletip({ content:'You have not yet sent a notification email to the person.', offset:[tip_offset,-24]}).click(function(e){ cycle_confirmation_status($(this).attr('id').substr(1),e) });

        $('.download_music_for_person').simpletip({ content:'Click to download a pdf music package for this person. Alt-click to edit package.', offset:[tip_offset,-24]});

        $('.remove_position').simpletip({ content:'Remove this person from this position', offset:[tip_offset,-24]});
        
        $('.remove_position').click(function(){
          if (confirm('Are you sure you want to remove this person/position from the service?')){
            $.post('".CW_AJAX."ajax_service_planning.php?action=remove_position_from_service&service_id=".$_GET["service_id"]."&pos_to_services_id=' + $(this).attr('id'),{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                reload_position_list();
                reload_rehearsal_list();
                reload_notification_note();
                reload_service_order(get_element_visibility('#element_selector'));
              }
            });
          }
        });
        
        $('.download_music_link').click(function(e){
          e.preventDefault();
          show_please_wait('Please wait while we generate your music package...');
          //$(this).attr('id') is position_id
          var pos_id=$(this).attr('id');
          $.post('".CW_AJAX."ajax_service_planning.php?action=get_music_pdf&service_id=".$_GET["service_id"]."&pos_id='+pos_id,{},function(rtn){
            hide_please_wait();
            if (rtn=='ERR'){
              alert('Error: could not generate PDF file');
            } else {
              //rtn has file-id for pdf file
              window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$a->csid."') + '&b=' + rtn;
            }
          });          
        });
        
        $('.download_music_for_person').click(function(e){
          e.preventDefault();
          if (!e.altKey){
            show_please_wait('Please wait while we generate your music package...');
            //$(this).attr('id') is positions_to_services.id (with 3-char-prefix)
            var pts_id=$(this).attr('id');
            $.post('".CW_AJAX."ajax_service_planning.php?action=get_music_pdf_for_person&service_id=".$_GET["service_id"]."&pts_id='+pts_id,{},function(rtn){
              hide_please_wait();
              if (rtn=='ERR'){
                alert('Error: could not generate PDF file');
              } else {
                //rtn has file-id for pdf file
                window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$a->csid."') + '&b=' + rtn;
              }
            });          
          } else {
            show_modal('".CW_AJAX."ajax_service_planning.php?action=display_edit_music_package_dialogue&service_id=".$_GET["service_id"]."&pos_to_services_id=' + $(this).attr('id').substr(3),80,270,930);
          }
        });        
        
      </script>
    ";  
    if ($t!=""){
      echo $t.$s;
    } else {
      echo "Nobody has been scheduled for this service";
    }
    $eh->set_sync_mark($_GET["service_id"],"position_list");
  } elseif ($_GET["action"]=='remove_position_from_service'){
    if ($a->csps>=CW_E){    
      if ($eh->check_sync_mark($_GET["service_id"],"position_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        if ($eh->delete_position(substr($_GET["pos_to_services_id"],1))){
          echo "OK";
        } else {
          echo "Could not remove this position";
        }
        $mark_service_update=true;
      } else {
        echo "Remove a person/position: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='cycle_confirmation_status'){
    if ($a->csps>=CW_E){
      $service_is_past=$eh->church_services->service_is_past($_GET["service_id"]);
      if (!$service_is_past){
        //have $_GET["pos_to_services_id"]. If confirmed, cycle to disconfirmed - n/a - confirmed...etc
        if ($v=$eh->event_positions->get_positions_to_services_record_by_id($_GET["pos_to_services_id"])){
          //Got current pos_to_services_record. Check "confirmed" field (positive = confirmed, negative = disc., 0= n/a)
          if ($v["confirmed"]>0){
            //Was confirmed, cycle to disconfirmed
            $val=-1;
          } elseif ($v["confirmed"]<0){
            //Was disconfirmed, cycle to n/a
            $val=0;
          } else {
            //Was n/a, cycle to confirmed
            $val=1;
          }
          if ($eh->manual_confirm_all_positions_for_person($_GET["pos_to_services_id"],$val)){
            echo "OK";
          } else {
            echo "Error: could not change confirmation status";
          }    
        } else {
          echo "Error: could not change confirmation status (could not obtain current status)";    
        }      
      } else { 
        echo "Error: cannot change confirmation status on service in the past";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='get_edit_projection_settings_interface'){
  	if ($a->csps>=CW_E){
  		if (!$service_is_past){
  			$dsp=new cw_Display_service_planning($d,$eh,$a);
  			echo $dsp->display_edit_projection_settings_dialogue($_GET["service_id"]);
  		} else {
  			echo "Error: cannot change settings for past service";
  		}	
  	} else {
  		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}
  } elseif ($_GET["action"]=='set_projection_settings_checkbox'){
  	if ($a->csps>=CW_E){
  		if (!$service_is_past){
  			if ($r=$eh->church_services->get_service_record($_GET["service_id"])){
  				$r[$_GET["field"]]=(strtolower($_GET["checked"])=="true");
  				if (($_GET["field"]=="use_mdb_backgrounds") || ($_GET["field"]=="use_motion_backgrounds") || ($_GET["field"]=="background_priority")){
  					if (!$eh->church_services->update_church_service_record($_GET["service_id"],$r,$a->cuid)){
  						//Couldn't execute update
  						echo "ERR";
  					}  						
  				} else {
  					//Invalid fieldname
  					echo "ERR";
  				}
  			}
  		} else {
  			echo "Error: cannot change projection settings for past service";  				
  		}
  	} else {
  		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}
  } elseif ($_GET["action"]=='assign_background_media_to_service'){
  	//Got $_GET["lib_id"], reference to media_library
  	if ($a->csps>=CW_E){
  		if (!$service_is_past){
  			if ($s=new cw_Church_services($d)){
  				if (!$s->assign_background_image_to_service($_GET["service_id"], $_GET["lib_id"],$a->cuid)){
  					echo "Error: could not assign background image with media id ".$_GET["lib_id"]." to this service";
  				}
  			}
  		} else {
  			echo "Error: cannot change settings for past service";
  		}
  	} else {
		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}
  } elseif ($_GET["action"]=='unassign_background_media_from_service'){
  	if ($a->csps>=CW_E){
  		if (!$service_is_past){
  			if ($s=new cw_Church_services($d)){
  				if (!$s->unassign_background_image_from_service($_GET["service_id"])){
  					echo "An error occurred while trying to remove the background image from the service";
  				}
  			}
  		} else {
  			echo "Error: cannot change settings for past service";
  		}
  	} else {
  		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}  		 
  } elseif ($_GET["action"]=='get_roomname_div'){
    echo "
      <div>
        Enter name of location:
      </div>
      <input type='text' id='room_name' style='width:300px;' value=\"".$_GET["value"]."\"/>
      <script type='text/javascript'>
        $('#room_name').focus();
      </script>
    ";
  } elseif ($_GET["action"]=='get_rehearsal_list'){
    //Get list of rehearsals associated with this service (event record with 'main_event' field and cat1='rehearsal' is considered rehearsal )
    $r=$eh->get_rehearsals_for_service($_GET["service_id"]);
    $service_is_past=$eh->church_services->service_is_past($_GET["service_id"]);
    //$t=sizeof($r)." rehearsals returned for service #".$_GET["service_id"];
    foreach ($r as $v){
      //Show links only to editors/admins - if service is not past
      $links="";
      if (($a->csps>=CW_E) && (!$service_is_past)){
        $links="<div id='erh".$v["id"]."' class='edit_rehearsal'></div> <div id='rrh".$v["id"]."' class='remove_rehearsal'></div>";
      }
      $t.="<div class='rehearsal_caption'>".date("D M j, g:ia",$v["timestamp"])." - ".date("g:ia",$v["timestamp"]+$v["duration"])."
              <div style='float:right;text-align:right;' class='rehearsal_location'>
                ".$eh->rooms->get_roomname($v["bookings"][0]["room_id"])."
                 $links 
              </div>
          </div>";
      $participants=$eh->who_of_position_type_is_invited_to_rehearsal($v["id"]); 
      if ($participants!=""){
        $t.="<div class='rehearsal_data'>participants: ".$participants."</div>";    
      } else {      
        $t.="<div class='rehearsal_data'>Nobody has been invited yet</div>";    
      }        
    }
    $s="
      <script type='text/javascript'>
        $('.edit_rehearsal').simpletip({ content:\"Edit this rehearsal\", offset:[-170,-24]});

        $('.edit_rehearsal').click(function(){
          var rehearsal_id=$(this).attr('id');
          show_modal('".CW_AJAX."ajax_service_planning.php?action=display_rehearsal_dialogue&service_id=".$_GET["service_id"]."&rehearsal_id='+rehearsal_id,50,150,950);      
        });

        $('.remove_rehearsal').simpletip({ content:'Remove this rehearsal', offset:[-170,-24]});

        $('.remove_rehearsal').click(function(){
          if (confirm('Are you sure you want to remove this rehearsal?')){
            $.post('".CW_AJAX."ajax_service_planning.php?action=remove_rehearsal&service_id=".$_GET["service_id"]."&rehearsal_id=' + $(this).attr('id'),{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                reload_rehearsal_list();
                reload_notification_note();
              }
            });
          }
        });
      </script>    
    ";
    if (!empty($t)){
      echo $t.$s;
    } else {
      echo "No rehearsals have been scheduled";    
    }
    $eh->set_sync_mark($_GET["service_id"],"rehearsal_list");
  } elseif ($_GET["action"]=='display_rehearsal_dialogue'){
    if ($a->csps>=CW_E){      
      if ($eh->check_sync_mark($_GET["service_id"],"rehearsal_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        $dsp=new cw_Display_service_planning($d,$eh,$a);
        if ($_GET["rehearsal_id"]){
          $_GET["rehearsal_id"]=substr($_GET["rehearsal_id"],3); //cuf off 'erh'
        }
        echo $dsp->display_edit_rehearsal_dialogue($_GET["service_id"],$_GET["rehearsal_id"]); 
      } else {
        echo "Schedule/edit rehearsal: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="schedule_practice"){
    if ($a->csps>=CW_E){    
      if ($eh->check_sync_mark($_GET["service_id"],"rehearsal_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        //Execute rehearsal scheduling request
        //Ensure that room and date/time have been selected
        if ((($_GET["room_id"]!=0) || (($_GET["room_id"]=="offsite") && ($_GET["room_name"]!=""))) && ($_GET["timestamp"]>0)){
          //Is this an Update request?
          if ($_GET["rehearsal_id"]>0){
            $rehearsal_event_id=$_GET["rehearsal_id"];
            $old_rehearsal_record=$eh->get_rehearsals_for_service($_GET["service_id"],$rehearsal_event_id,true);
            $duration=CW_DEFAULT_PRACTICE_DURATION;
            if ($_GET["duration"]!=0){
              $duration=$_GET["duration"];
            }
            //Before we try to update data, check if the new booking would go through
            if ($eh->room_bookings->room_is_available($_GET["room_id"],$_GET["timestamp"],$duration,$old_rehearsal_record["bookings"][0]["id"])){
              if ($eh->update_rehearsal($rehearsal_event_id,$_GET["timestamp"],$duration)){
                //In case of offsite location, create "guest room" and replace $_GET["room_id"] with (negative) guest room id
                if ($_GET["room_id"]=="offsite"){
                  if ($_GET["room_name"]==$eh->rooms->get_guest_roomname($old_rehearsal_record["bookings"][0]["room_id"])){
                    //Same guest room - nothing to change       
                    $_GET["room_id"]=$old_rehearsal_record["bookings"][0]["room_id"];   
                  } else {
                    //Location changed to a different guest room
                    if ($id=$eh->rooms->add_guest_room($_GET["room_name"])){
                      $_GET["room_id"]=($id*(-1)); 
                    }            
                  }
                }
                //Now attempt to update room booking
                if ($eh->room_bookings->update_booking($old_rehearsal_record["bookings"][0]["id"],$_GET["room_id"],$_GET["timestamp"],$duration,$a->cuid)){
                  //Loop over old participants and see if someone got taken out
                  foreach ($old_rehearsal_record["participants"] as $v){
                    if (array_key_exists("posrec_".$v["pos_id"],$_POST)){
                      //Person is still in practice - do nothing
                      //echo "-Still in: (".$v["pos_id"].") ";
                    } else {
                      //echo "-Now out: (".$v["pos_id"].") ";
                      //Person got taken out, notify if applicable and delete rehearsal_participant record
                      $eh->delete_rehearsal_participant($v["pos_id"],$rehearsal_event_id);
                    }            
                  }
                  //Now write rehearsal participant records for those who have been ADDED
                  foreach ($_POST as $k=>$v){
                    $posrec_id=substr($k,7); 
                    //This method will not rewrite those records that exist already
                    $eh->event_positions->add_rehearsal_participant_by_position_type($posrec_id,$rehearsal_event_id);
                  }
                  //Now send notifications where applicable
                  $new_rehearsal_record=$eh->get_rehearsals_for_service($_GET["service_id"],$rehearsal_event_id,true);
                  $eh->send_applicable_rehearsal_update_notificatons($old_rehearsal_record,$new_rehearsal_record);
                  echo "OK";
                } else {
                  //Booking failed - THIS SHOULD NEVER HAPPEN AS WE JUST CHECKED THE AVAILABILITY OF THE VENUE!
                  $eh->delete_rehearsal($rehearsal_event_id);
                  echo "Booking update failed - rehearsal deleted. Please notify your administrator of this error, as it should never occur.";
                }                
              } else {
                echo "Rehearsal update failed";
              }        
            } else {
              echo "The booking request failed - changes to date, time, duration or venue could not be applied.";
            }
          } else {
            /*
              Schedule NEW rehearsal (not update)
              First, schedule the rehearsal temporarily to get an event_id
            */
            $duration=CW_DEFAULT_PRACTICE_DURATION;
            if ($_GET["duration"]!=0){
              $duration=$_GET["duration"];
            }
            if ($rehearsal_event_id=$eh->schedule_rehearsal($_GET["service_id"],$_GET["timestamp"],$duration)){
              //In case of offsite location, create "guest room" and replace $_GET["room_id"] with (negative) guest room id
              if ($_GET["room_id"]=="offsite"){
                if ($id=$eh->rooms->add_guest_room($_GET["room_name"])){
                  $_GET["room_id"]=($id*(-1)); 
                }  
              } 
              //Now attempt room booking
              if ($eh->room_bookings->add_booking($rehearsal_event_id,$_GET["room_id"],$_GET["timestamp"],$duration,$a->cuid,"Auto-booked through service planning")){
                //Now write rehearsal participant records
                foreach ($_POST as $k=>$v){
                  $posrec_id=substr($k,7); 
                  $eh->event_positions->add_rehearsal_participant_by_position_type($posrec_id,$rehearsal_event_id);
                }
                echo "OK";
              } else {
                //Booking failed - delete rehearsal event record as well
                $eh->delete_rehearsal($rehearsal_event_id);
                echo "Booking failed";
              }                
            } else {
              echo "Scheduling process failed. Service:".$_GET["service_id"].", timestamp:".$_GET["timestamp"].", duration:$duration";
            }    
          }    
        } else {
          //Data missing
          echo "Please make sure that you have selected date and time as well as a location for the rehearsal.";
        }
        $mark_service_update=true;
      } else {
        echo "Schedule rehearsal: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="remove_rehearsal"){
    if ($a->csps>=CW_E){    
      if ($eh->check_sync_mark($_GET["service_id"],"rehearsal_list")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        //Rehearsal event ID is 'rrh#id'
        $rehearsal_event_id=substr($_GET["rehearsal_id"],3);
        if ($rehearsal_event_id>0){
          if ($eh->delete_rehearsal($rehearsal_event_id)){
            echo "OK";
          } else {
            echo "A problem occurred while deleting the rehearsal";
          }      
        } else {
          echo "There is a problem with the rehearsal event-id.";
        }
        $mark_service_update=true;
      } else {
        echo "Remove rehearsal: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_notification_note"){
    if (!$eh->church_services->service_is_past($_GET["service_id"])){
      //Service is in the future
      if (!$eh->email_notifications_pending($_GET["service_id"])){
        if ($pos=$eh->event_positions->get_positions_in_service($_GET["service_id"])){
          echo "Everybody has received notification emails.";      
        } else {
          echo "Nobody has been scheduled yet.";
        }      
      } else {
        echo "There are people who have not received a scheduling/rehearsal notification. <br/><a href='' id='send_notifications'>Click to send unsent notifications</a>.
          <script type='text/javascript'>
            $('#send_notifications').click(function(e){
              e.preventDefault();
              $.post('".CW_AJAX."ajax_service_planning.php?action=send_notifications&service_id=".$_GET["service_id"]."',{},function(rtn){
                reload_notification_note();
                reload_rehearsal_list();
                reload_position_list();
                alert(rtn);
              });
            });
          </script>
        ";
      }
    } else {
      //Service is in the past
      echo "<span class='red' style='font-weight:bold;font-size:120%;'>This service is in the past</span>";
    }
  } elseif ($_GET["action"]=="send_notifications"){
    if ($a->csps>=CW_E){    
      $notes=$eh->email_notifications($_GET["service_id"],true); //Get the notifications send them out
      $fails=array();
      $sent=array();
      foreach ($notes as $k=>$v){
        if (substr($v["status"],0,4)!="sent"){   //Could have been sent before
          $fails[]=$k;
        }
        if ($v["status"]=="sent"){
          //Those sent now
          $sent[]=$k;
        }
      }
      if (sizeof($fails)>0){
        $names.="";
        foreach ($fails as $v){
          $names.=", ".$a->personal_records->get_name_first_last($v);
        }
        if ($names!=""){
          $names=substr($names,2); //cut first comma
        }
        echo sizeof($sent)." email notifications have been sent out. We could not email the following person(s): ".$names;    
      } else {
        echo "All email notifications have been sent out.";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_service_order"){
    $dsp=new cw_Display_service_planning($d,$eh,$a);
    echo $dsp->display_service_order($_GET["service_id"],$_GET["show_element_list"]);
    $eh->set_sync_mark($_GET["service_id"],"service_order");
  } elseif ($_GET["action"]=="generate_service_element"){ 
    if ($a->csps>=CW_E){
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          $element_nr=$_GET["element_position"];
          if ($_GET["element_type_id"]!="egroup_header"){
            $element_type_id=substr($_GET["element_type_id"],1);
            if ($new_id=$eh->add_service_element_at_position($element_nr,$_GET["service_id"],$element_type_id,"auto")){
              echo "OK";
            } else {
              echo "ERR";
            }    
          } else {
            //Group header
            $id=$eh->church_services->add_group_header($_GET["service_id"],$_GET["element_position"]);
            if ($id>0){
              echo $id; //return service_el id of the new header to the script
            } else {
              echo "ERR";
            }
          }
          $mark_service_update=true;
        } else {
          echo "Generate service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
        }      
      } else {
        echo "Error: Cannot generate service element for past service";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="apply_group_template_to_service_element"){
    if (($a->csps>=CW_E) && (!$eh->church_services->service_is_past($_GET["service_id"]))){    
      //A 'nosync' flag can be set from the calling script, if this operation immediately follows the element creation
      if (($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE) || ($_GET["nosync"]=="true")){
        //Get the element id -- this must, of course, be a group header for this to work (service_element_type id=-1)
        $element_id=$eh->church_services->get_element_id_at_position($_GET["service_id"],$_GET["element_position"]);
        if ($element_id>0){
          if ($eh->apply_group_template($element_id,$_GET["template_id"])){
            echo "OK";
          } else {
            echo "Could not apply the template. Perhaps you have members in this group already.";
          }
        } else {
          echo "An error occurred while trying to retrieve the group header service element";
        }
        $mark_service_update=true;
      } else {
        echo "Apply group template: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="repos_service_element"){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          //Obtain element record
          $element_id=substr($_GET["element_id"],1);
          if ($element_id==0){
            //Element ID was not given, try to get it through service_id and element_position
            $element_id=$eh->church_services->get_service_element_by_service_id_and_position($_GET["service_id"],$_GET["element_position"]);
          }
          $element=$eh->church_services->get_service_element_record($element_id);
          //What kind of element is this (group header or no)?
          if ($element["element_type"]>0){
            //Regular element
            if ($eh->church_services->reposition_service_element($element_id,$_GET["element_position"])){
              //Check if element has been placed under group_header or other group element, and is supposed to go in the group (if ctrl pressed)
              if (!empty($_GET["group"])){
                if ($_GET["group"]=="attach"){
                  //Attach requested
                  if ($eh->attach_element_to_group_above($element["id"],$_GET["service_id"],$_GET["element_position"])){
                    echo "OK";
                  } else {
                    echo "Could not attach element to a group. Make sure that previous element is member of a group.";
                  }
                } else {
                  //Detach - but only if element is not surrounded by two group elements
                  if (!$eh->church_services->element_sits_between_group_members($element_id,$_GET["service_id"])){
                    $eh->church_services->remove_element_from_group($element["id"]);
                    echo "OK";
                  } else {
                    echo "Cannot detach element from group because it sits between group members. Move element out of the group to detach.";
                  }
                }
              } else {
                echo "OK";            
              }
            } else {
              echo "Could not reposition service element";
            }    
          } elseif ($element["element_type"]==-1){
            //Group header
            if ($eh->church_services->reposition_group($_GET["service_id"],$element["segment"],$_GET["element_position"])){
              echo "OK";            
            } else {
              echo "Could not reposition group";
            }        
          }
          $mark_service_update=true;
        } else {
          echo "Reposition service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }
  } elseif ($_GET["action"]=="duplicate_service_element"){
    //Have $_GET["element_id"] and $_GET["service_id"]
    $_GET["element_id"]=substr($_GET["element_id"],1); //Cut prefix
    if ($a->csps>=CW_E){
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){

          //Get element record for the element we want to duplicate
          $element=$eh->church_services->get_service_element_record($_GET["element_id"]);
          //Position at which to insert the double
          $element_nr=$element["element_nr"];
          //
          $element_type_id=$element["element_type"];
          if ($element_type_id>0){
            //Not a group header (0 are dividers, -1 are group headers)
            if ($new_id=$eh->add_service_element_at_position($element_nr,$_GET["service_id"],$element_type_id,"auto")){
              //Added new element - now copy duration, label, note etc
              $e=array();
              $e["note"]=$element["note"];
              $e["other_person"]=$element["other_person"];
              $e["duration"]=$element["duration"];
              $e["label"]=$element["label"];
              $e["segment"]=$element["segment"];
              $e["person_id"]=$element["person_id"];
              $e["group_template_id"]=$element["group_template_id"];
              if ($eh->church_services->update_service_element($new_id,$e)){
                echo "OK";
              } else {
                echo "Could not update service element";
              } 
            } else {
              echo "ERR";
            }    
          } else {
            //Group header
            $id=$eh->church_services->add_group_header($_GET["service_id"],$element_nr);
            if ($id>0){
              echo "OK";
            } else {
              echo "ERR";
            }
          }
          $mark_service_update=true;
        } else {
          echo "Generate service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
        }      
      } else {
        echo "Error: Cannot generate service element for past service";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    } 
  } elseif ($_GET["action"]=="set_service_element_background_to_auto"){
  	if ($element=$eh->church_services->set_background_for_service_element_to_auto($_GET["service_element_id"],$a->cuid)){  		
  		//Now get actual bg, if present
  		$bg_file_id=$eh->church_services->get_service_element_background($_GET["service_element_id"], $a);
  		echo $bg_file_id;
  	} else {
  		echo "ERR";
  	}                
  } elseif ($_GET["action"]=="set_service_element_background_to_none"){  	
  	if ($element=$eh->church_services->unassign_background_from_service_element($_GET["service_element_id"],$a->cuid)){
  		echo 0;  		
  	} else {
  		echo "ERR";
  	}                
  } elseif ($_GET["action"]=="get_downscaled_projector_preview"){
	//$_GET["max_height"] and width, $_GET["lib_id"]
	$mb=new cw_Mediabase($a);
	$preview=$mb->get_downscaled_projector_image($_GET["lib_id"], $_GET["max_width"], $_GET["max_height"]);
	if ($preview!=null){
		header('Content-Type: image/jpeg');
		imagejpeg($preview);
	}
  } elseif ($_GET["action"]=="edit_service_element"){
    if ($a->csps>=CW_E){ //<-- this is probably obsolete - the call won't be made unless user has >=CW_E (javascript funtion isn't delivered for CW_V)   
      if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
        $dsp=new cw_Display_service_planning($d,$eh,$a);
        //With $_GET["service_id"] and $_GET["pos"] we can identify the element
        echo $dsp->display_service_element_edit_dialogue($eh->church_services->get_service_element_by_service_id_and_position($_GET["service_id"],$_GET["pos"]),$_GET["service_id"]);
      } else {
        echo "Edit service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_element"){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          //We have in $_GET: duration,element_id,person_id
          //We have in $_POST: note,other_person (use if person_id==0), label, scripture_refs, sermon_abstract, sermon_title
          //Get element record
          $element=$eh->church_services->get_service_element_record($_GET["element_id"]);
          //set $_GET["service_id"] for compatibility reasons (service update at end of script)
          $_GET["service_id"]=$element["service_id"];
          //Get element_type record
          $element_type=$eh->church_services->get_service_element_type_record($element["element_type"]);
          if ($element_type["meta_type"]=="word"){
            if ($sermon=$eh->sh->sermons->get_sermon_record_for_service_element($_GET["element_id"])){
              //Save sermon info
              $sermon["title"]=$_POST["sermon_title"];
              $sermon["abstract"]=$_POST["sermon_abstract"];
              $eh->sh->sermons->update_sermon($sermon,$sermon["id"]);
            }
            //Delete and replace scripture ref records
            $eh->sh->scripture_refs->delete_scripture_ref_records_for_service_element($_GET["element_id"]);
            if ($_POST["scripture_refs"]!=""){
              //Save scripture ref
              if ($refs=$eh->sh->scripture_refs->identify_multi_range($_POST["scripture_refs"])){
                $eh->sh->assign_scripture_refs_to_service_element($refs,$_GET["element_id"]);
              } else {
                //Couldn't identify range/references - Store references as string
                $eh->sh->scripture_refs->assign_scripture_ref_to_service_element($eh->sh->scripture_refs->add_scripture_ref(0,0,$_POST["scripture_refs"]),$_GET["element_id"]);
              }
            }
          }
          $e=array();
          $e["note"]=$_POST["note"];
          $e["other_person"]=$_POST["other_person"];
          $e["duration"]=$_GET["duration"];
          $e["person_id"]=$_GET["person_id"];
          empty($_POST["label"]) ? $e["label"]="" : $e["label"]=$_POST["label"];
          if ($eh->church_services->update_service_element($_GET["element_id"],$e)){
            echo "OK";
          } else {
            echo "Could not update service element";
          } 
          $mark_service_update=true;
        } else {
          echo "Save service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_service_element"){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          $element_id=$eh->church_services->get_service_element_by_service_id_and_position($_GET["service_id"],$_GET["element_position"]);
          if ($eh->delete_service_element($element_id)){
            echo "OK";
          } else {
            echo "Could not delete service element";
          }
          $mark_service_update=true;
        } else {
          echo "Delete service element: ".CW_SERVICE_PLANNING_SYNC_NOTICE;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;        
      }           
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="acomp_songs"){
    $mdb=new cw_Music_db($a);
    echo $mdb->get_songsearch_autocomplete_suggestions($_GET["term"],0);  
  } elseif ($_GET["action"]=="get_arrangements_options_for_music_piece"){
    /* 
      This can be called with either $_GET["music_piece"] when user selects something from the autocomplete, 
      or with $_GET["atse_id"] when edit_service_element window inits and a previous association exists.
      In the latter case preselect the arrangement stored in the atse record, otherwise simply preselect the top one.      
    */    
    if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
      $mdb=new cw_Music_db($a);
      
      if (isset($_GET["atse_id"])){
        //atse_id was given
        $r=$eh->church_services->get_arrangements_to_service_elements_record($_GET["atse_id"]);
        if (is_array($r)){
          $arr_id=$r["arrangement"];
          $arr_rec=$mdb->get_arrangement_record($arr_id);
          if (is_array($arr_rec)){
            $music_piece=$arr_rec["music_piece"];
          }
        }
      }
      if (!isset($music_piece)){
        //$music_piece has not been set above, so likely we got $_GET["music_piece"] instead of $_GET["atse_id"]
        if (isset($_GET["music_piece"])){
          $music_piece=$_GET["music_piece"];
          $option_to_select=1; //Since no arrangement choice has been stored yet, simply preselect the top one
        } else {
          //Did not get any proper parameter. Error.
          echo "<option value=''>Error!</option>";
        }
      }
      
          
      $r=$mdb->get_arrangement_records_for_music_piece($music_piece,true);//Get active arrangements only
      if ($r!==false){
        $cnt=0;
        foreach ($r as $v){
          $cnt++;
          //(!empty($v["title"])) ? $label=$v["title"] : $label='arr#'.$v["id"];
          $label="";
          if (!empty($v["source_id"])){
            $label=$mdb->get_source_title($v["source_id"]);
          }
          if (empty($label)){
            //If no source information available, use arranger name(s) if present
            $label=$mdb->get_writers_for_arrangement_as_string($v["id"]);            
          }
          //Key information
          ($v["musical_key"]!=0) ? $mkey=$mdb->int_to_musical_key($v["musical_key"],true) : $mkey="";
          //Keychanges
          $keychanges=$mdb->get_keychanges_for_arrangement($v["id"]);
          if (is_array($keychanges)){
            foreach ($keychanges as $kc){
              $mkey.=",".$mdb->int_to_musical_key($kc["musical_key"],true);
            }
          }
          $mkey=" [$mkey]";        
          //Instruments information
          $instruments="";
          $instr=$mdb->get_instruments_for_arrangement($v["id"]);
          //$instr has array of instrument ids
          foreach ($instr as $v2){
            $instruments.=",".$mdb->get_instruments_title($v2);        
          }
          (!empty($instruments)) ? $instruments=" [".substr($instruments,1)."]" : $instruments=" [no parts!]"; //cut first comma
          //Style info
          $st=$mdb->get_style_tags_for_arrangement($v["id"]);
          $style_tags="";
          if (is_array($st)){
            foreach ($st as $v3){
              $style_tags.=",".$v3["title"];
            }
            if (!empty($style_tags)){
              $style_tags=" [".substr($style_tags,1)."]";
            } else {
            }
          }
          //Duration
          $duration=" [".date("i:s",$v["duration"])."]";  
          //select if either $cnt matches $option_to_select, or if the id of this arrangement matches $arr_id (set only if we have recalled via atse) 
          $sel="";
          if (($cnt==$option_to_select) || ($v["id"]==$arr_id)){
            $sel=" selected='SELECTED'";
          }
          $t.="<option value='".$v["id"]."' $sel>$label$mkey$instruments$style_tags$duration</option>";
        }
      }      
      echo $t; 
    } else {
      echo "ERR"; //Sync error - js gives out notice
    }            
  } elseif ($_GET["action"]=="get_people_on_service_element_checkboxes"){
    //got $_GET["service_id"] and $_GET["service_element_id"]
    //People (musicians,singers) on this piece
    // This needs no sync as it is immediately called after get_arrangement_options_for_music_piece
    $t="";
    if ($r1=$eh->event_positions->get_people_in_service($_GET["service_id"],$eh->event_positions->get_position_type_id(CW_POSITION_TYPE_WORSHIP_TEAM))){
      //Got at least one worship team member. $r1 has pos_to_services records.
      foreach ($r1 as $v){
        $checked="";
        if ($eh->event_positions->pos_to_services_record_is_on_service_element($v["id"],$_GET["service_element_id"])){
          //This pos_to_services record ($v["id"]) is in fact assigned to the service_element in positions_to_services__to_service_elements
          $checked="checked='CHECKED'";
        }
        $t.="<div><input type='checkbox' class='people_in_service_element' id='pts_".$v["id"]."' $checked/>".$a->personal_records->get_name_first_last($v["person_id"])." - ".$eh->event_positions->get_position_title($v["pos_id"])."</div>";
      }
    }    
    $t.="
      <script type='text/javascript'>
        $('.people_in_service_element').click(function(){
          $.post('".CW_AJAX."ajax_service_planning.php?action=assign_position_to_service_element',{pos_to_services_id:$(this).attr('id'),service_element_id:".$_GET["service_element_id"].",checked:$(this).prop('checked')},function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
          });
        });      
      </script>    
    ";
    echo $t;  
  } elseif ($_GET["action"]=="get_lyrics_sequence_div"){
    $mdb=new cw_Music_db($a);
    //the div with the lyrics sequence in the service-element edit window
    //User has selected an arrangement. This must now be assigned to the service element in question (sync not needed)
    $atse_id=$eh->assign_arrangement_to_service_element($_GET["arrangement"],$_GET["service_element"],$_GET["service_id"]); //arrangements_to_service_elements id
    if ($atse_id>0){
      //Get arrangement record for music_piece id
      $arr_rec=$mdb->get_arrangement_record($_GET["arrangement"]);
      //Get lyrics fragment records
      if ($frag_recs=$mdb->get_lyrics_records_for_music_piece($arr_rec["music_piece"])){
        //Get all lyrics fragment types
        $types=$mdb->get_fragment_types_records();
        //Prepend the blank-slide option
        $fragments.="<li id='lyr_0' class='blankslide_li data fragment_type' >blank slide</li>";      
        foreach ($frag_recs as $v){
          //Lyrics records
          (($types[$v["fragment_type"]-1]["title"]=="verse")|| ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";
          ($v["language"]!=$mdb->get_default_language_for_music_piece($arr_rec["music_piece"])) ? $language=" (".$mdb->int_to_language($v["language"]).")" : $language="";               
          $fragments.="<li id='lyr_".$v["id"]."' class='data fragment_type'>".$types[$v["fragment_type"]-1]["title"].$fno."$language</li>"; //utilities_misc
        }      
        $t="
          <ul id='fragment_selector'>
            $fragments
          </ul>
          <ul id=\"lsortable\">
          </ul>      
          
          <script type='text/javascript'>
          
            ////////////////// Lyrics Sequence
            function reload_lyrics_sequence(){
              $('#lsortable').load('".CW_AJAX."ajax_service_planning.php?action=get_lyrics_sequence_for_sortable&atse_id=$atse_id&music_piece=".$arr_rec["music_piece"]."');
            }
            
            reload_lyrics_sequence(); //init
            
            function generate_fragment_instance(fragment_id,new_fragment_position){
              $.post('".CW_AJAX."ajax_service_planning.php?action=add_fragment_to_sequence&service_id=".$_GET["service_id"]."',{ atse_id:$atse_id,lyrics:fragment_id,sequence_no:new_fragment_position },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_lyrics_sequence();
              });                       
            }
            
            function repos_fragment_instance(old_position,new_position){
              $.post('".CW_AJAX."ajax_service_planning.php?action=repos_fragment_in_sequence&service_id=".$_GET["service_id"]."',{ atse_id:$atse_id, old_position:old_position, new_position:new_position },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_lyrics_sequence();
              });                                 
            }
            
            function delete_lyrics_from_sequence(position){
              $.post('".CW_AJAX."ajax_service_planning.php?action=delete_fragment_from_sequence&service_id=".$_GET["service_id"]."&atse_id=$atse_id&position=' + position,{},function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_lyrics_sequence();
              });                       
            }
            
          	$(function() {
          		$( '#lsortable' ).sortable({
          			revert: true,
                receive: function(event,ui){
                  $('#placeholder').remove(); //If there was a placeholder, remove it
                  var lyrics_id = $(ui.item).attr('id');
                  var fragment_position = $(this).data().sortable.currentItem.index()+1; //index of new element
                  $(this).data().sortable.currentItem.data('nostop',true); //Set flag to prevent the call of repos_ when stop event is fired right after this                  
                  //generate_fragment_instance here
                  generate_fragment_instance(lyrics_id,fragment_position);
                  
                },
                stop: function(event,ui){
                  var new_position=$(this).data().sortable.currentItem.index()+1;
                  var old_position=$(ui.item).data('old_position'); //recall old position
                  //See if this was called after repositioning or after receiving
                  if (!$(ui.item).data('nostop')){
                    repos_fragment_instance(old_position,new_position);                  
                  } else {
                    $(ui.item).data('nostop',false);                  
                  }
                },
                start: function(event,ui){
                  $(ui.helper).css('background','#FAEAEA');                    
                  $(ui.item).data('noclick',true); //Avoid that the drag is being interpreted as click
                  $(ui.item).data('old_position',ui.item.index()+1); //save old position
                }
          		});
              
          		$( '.fragment_type' ).draggable({
          			connectToSortable: '#lsortable',
          			helper: 'clone',
          			revert: 'invalid',
                start: function(event,ui){
                  
                }
          		}); 
              
          		$( 'ul, li' ).disableSelection();                                      
  
            });          
          
          </script>
        ";
        echo $t;
      } else {
        echo "<span class='gray'>This piece does not have lyrics</span>";
      }
    } else {
      if ($_GET["arrangement"]==0){
        //No arrangement id was sent, most likely because no arrangement exists for the song/piece
        //discard previous association and output notice
        $eh->church_services->unassign_arrangement_from_service_element($_GET["service_element"]);                
        echo "<div class='expl'>There are no arrangements in the database for the piece you selected. It is best to add one to the database, but if you do not need music or lyrics you can choose to use a piece that's not in the database by simply editing the label of the service element (above).</div>";
      } else {
        //Some type of error
        echo "Error: could not assign arrangement to service element";         
      }
    }
  } elseif ($_GET["action"]=='get_lyrics_sequence_for_sortable'){
    //Get the <li>s for the actual lyrics sequence sortable
    $mdb=new cw_Music_db($a);
    $sequence_elements="";
    //Get fragment types:
    $types=$mdb->get_fragment_types_records();
    //Get atse record
    $r=$eh->church_services->get_arrangements_to_service_elements_record($_GET["atse_id"]);
    //Get lyrics records
    if (is_array($r)){
      $seq_recs=$mdb->get_lyrics_records_from_csl($r["lyrics"]); //$r["lyrics"] is a Comma Seperated List (string))
      if (sizeof($seq_recs)>0){
        $cnt=0;
        foreach ($seq_recs as $v){
          $cnt++; //This counter just for li-ids, no functionality here other than js-highlighting on shift and hover
          if ($v["fragment_type"]>0){
            //Not a blank slide
            (($types[$v["fragment_type"]-1]["title"]=="verse") || ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";     
            ($v["language"]!=$mdb->get_default_language_for_music_piece($_GET["music_piece"])) ? $language=$mdb->int_to_language($v["language"]) : $language="";
            (!empty($language)) ? $language=" <span class='gray'>".($language)."</span>" : null;
            $title=$types[$v["fragment_type"]-1]["title"].$fno.$language;
            $content=replace_linebreak(replace_double_linebreak($v["content"]," <span class='gray'>|</span> ")," <span class='gray'>/</span> ");//extract_full_words($v["content"])."...";
            $background="";                         
          } else {
            $title="<span style='color:gray;'>blank slide</span>";
            $content="";   
            $background="background:#EAEAEA";                      
          }
          $sequence_elements.="
            <li id='x$cnt' class='lactual_element'>
              <div style='padding:0px;margin:0px;border:0px solid gray;$background'>
                <div style='width:60px;display:inline-block;vertical-align:middle;'>
                  $title
                </div>
                <div class='data lyrics_fragment_div_content' style='width:455px;display:inline-block;vertical-align:middle;'>
                  $content
                </div>
              </div>
            </li>";
        }
        $sequence_elements.="
          <script type='text/javascript'>
            //Shift click on an element ->delete
            $('.lactual_element').click(function(e){
              if (!$(this).data('noclick')){
                if (e.shiftKey){
                  var position=$(this).index()+1;
                  delete_lyrics_from_sequence(position);                                  
                }               
              } else {
                $(this).data('noclick',false);
              }
            });
            
            //On hover, save which element is hovered above
            $('.lactual_element').hover(
              function(){                     
                if (!$('#fragment_selector').data('dragging')){
                  $('#lsortable').data('hover',$(this).attr('id'));                
                }
              },
              function(){
                $('#lsortable').css('hover','');
                $(this).css('background',''); //Unmark on leave
                $('#lsortable').data('hover',''); //Mark the element as not hovering anymore               
              }            
            );


            //Shiftkey held while hover - mark element
            $(document).keydown(function(e){
              if ($('#modal').is(':visible')){
                var el=$('#lsortable').data('hover');
                if (el){
                  if (e.keyCode==16){
                    $('#'+el).css('background','#FAA');            
                  }
                }
              }
            });
            
            $(document).keyup(function(e){
              //If modal is visible, close on esc
              if ($('#modal').is(':visible')){
                //Shift key let go - unmark element
                var el=$('#lsortable').data('hover');
                if (el){
                  if (e.keyCode==16){
                    $('#'+el).css('background','');            
                  }
                }        
              }
            });
            
            
                      
          </script>
        ";      
      } else {
        $sequence_elements="<li id='placeholder'><div>drop lyrics fragments here</div></li>"; 
      }
      echo $sequence_elements;     
    } else {
      echo "Error: could not obtain arrangements_to_services_record ".$_GET["atse_id"];
    }
  } elseif ($_GET["action"]=='add_fragment_to_sequence'){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          //User has dragged and dropped a lyrics segment in the lyrics sequence for the service element (may be different from the chosen arrangement)
          //$_POST has atse_id (arrangements_to_services_id), lyrics, sequence_no
          $lyrics_id=substr($_POST["lyrics"],4);
          //Establish the lyrics sequence to be stored.
          //Get saved sequence from atse record
          $r=$eh->church_services->get_arrangements_to_service_elements_record($_POST["atse_id"]);
          if (is_array($r)){
            $lyrics_sequence=csl_add_element_at_position($r["lyrics"],$lyrics_id,$_POST["sequence_no"]); //utilities_misc
            if ($eh->church_services->update_lyrics_sequence_on_service_element($_POST["atse_id"],$lyrics_sequence)){
              echo "OK";
            } else {
              echo "An error occurred while trying to add a fragment into the lyrics sequence";
            }                   
          } else {
            echo "Error: could not get arrangements_to_service_elements record ".$_POST["atse_id"];
          }
          $mark_service_update=true;
        } else {
          echo "Add lyrics fragment: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }          
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='repos_fragment_in_sequence'){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          //User has repositoined a lyrics fragment in the actual service element
          //$_POST has atse_id and old_position and new_position 
          //Establish the lyrics sequence to be stored.
          //Get saved sequence from atse record
          $r=$eh->church_services->get_arrangements_to_service_elements_record($_POST["atse_id"]);
          if (is_array($r)){
            $lyrics_sequence=csl_move_element($r["lyrics"],$_POST["old_position"],$_POST["new_position"]); //utilities_misc
            if ($eh->church_services->update_lyrics_sequence_on_service_element($_POST["atse_id"],$lyrics_sequence)){
              echo "OK";
            } else {
              echo "An error occurred while trying to reposition a fragment in the lyrics sequence";
            }                   
          } else {
            echo "Error: could not get arrangements_to_service_elements record ".$_POST["atse_id"];
          }
          $mark_service_update=true;
        } else {
          echo "Reposition lyrics fragment: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='get_music_piece_title_via_atse'){
    $mdb=new cw_Music_db($a);
    $r=$eh->church_services->get_arrangements_to_service_elements_record($_POST["atse_id"]);
    if ($r>0){
      //Get arr_rec for music_piece id
      $arr_rec=$mdb->get_arrangement_record($r["arrangement"]);
      $e=$mdb->get_music_piece_record($arr_rec["music_piece"]);
      if (is_array($e)){
        echo $e["title"];
      } else {
        echo "(error while loading title: music-piece or arrangement record failed)";    
      }
    } else {
      echo "(error while loading title: atse failed)";    
    }     
  } elseif ($_GET["action"]=='delete_fragment_from_sequence'){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
          //Use $_GET["atse_id"] and $_GET["position"] to delete a fragment from the actual lyrics sequence of the service element
          $r=$eh->church_services->get_arrangements_to_service_elements_record($_GET["atse_id"]);
          if ($r>0){
            $lyrics_sequence=csl_delete_element_at_position($r["lyrics"],$_GET["position"]);//utilities_misc
            if ($eh->church_services->update_lyrics_sequence_on_service_element($_GET["atse_id"],$lyrics_sequence)){
              echo "OK";
            } else {
              echo "An error occurred while trying to delete a fragment in the lyrics sequence";
            }                         
          }   
          $mark_service_update=true;
        } else {
          echo "Remove lyrics fragment: ".CW_SERVICE_PLANNING_SYNC_NOTICE_WITH_F5;
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='assign_position_to_service_element'){
    if ($a->csps>=CW_E){    
      if (!$eh->church_services->service_is_past($_GET["service_id"])){
        //$_POST has pos_to_services_id (with a 4digit prefix, and service_element_id, and checked (bool))
        $pos_to_services_id=substr($_POST["pos_to_services_id"],4);
        $service_element_id=$_POST["service_element_id"];
        if ($_POST["checked"]=="true"){
          if ($eh->event_positions->assign_position_to_service_element($pos_to_services_id,$service_element_id)){
            echo "OK";
          } else {
            echo "Database problem: Could select this person";
          }
        } else {
          if ($eh->event_positions->unassign_position_from_service_element($pos_to_services_id,$service_element_id)){
            echo "OK";
          } else {
            echo "Database problem: Could deselect this person";
          }    
        }
      } else {
        echo CW_ERR_PAST_SERVICE;
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='get_upcoming_services_to_send_arrangement_to'){
    if ($a->csps>=CW_E){    
      //This is called from cw_Display_music_db - option to send an arrangement directly to a service plan
      //Use $_GET["arrangement_id"] to see if arrangement is a presentation piece
      $mdb=new cw_Music_db($a);
      $arr_id=substr($_GET["arrangement_id"],4); //cut 'edit'
      $arr_rec=$mdb->get_arrangement_record($arr_id);
      if ((is_array($arr_rec)) && ($arr_rec["active"])){      
        $sr=$eh->get_upcoming_services();
        foreach ($sr as $v){
          //$v is service_id
          $empty_music_element=$eh->church_services->get_first_empty_music_element($v,$arr_rec["is_presentation_piece"]);
          //Only consider services that have placeholders (empty music elements)
          if ($empty_music_element>0){
            $service=new cw_Church_service($eh,$v);
            $options.="<option value='$v'>".$service->get_info_string()."</option>";
          }
        }
        if (!empty($options)){
          echo "
            <select id='service_selector' style='width:320px;'><option>(select an upcoming service...)</option>$options</select>
    
            <script type='text/javascript'>
              $('#service_selector').change(function(){  
                $.post('".CW_AJAX."ajax_service_planning.php?action=accept_arrangement_sent_from_music_db',{ arrangement_id:".substr($_GET["arrangement_id"],4).", service_id:$(this).val() },function(rtn){
                  var service_plan_url='".CW_ROOT_WEB.$a->services->get_non_ajax_service_url($a->csid)."?action=plan_service&service_id='+$('#service_selector').val();  
                  if (rtn=='OK'){
                    $('#service_selector').parent().append('Arrangement has been scheduled - <a style=\"color:white;text-decoration:underline;\" href=\"' + service_plan_url + '\">go to service plan</a>');            
                  } else {
                    if (rtn=='NO_EMPTY_MUSIC_ELEMENTS'){
                      //No placeholders left in service plan
                      $('#service_selector').parent().append('The service plan you selected does not have empty music elements. You need to create such placeholders before you can send arrangements from the music database interface.<br/><a style=\"color:white;text-decoration:underline;\" href=\"' + service_plan_url + '\">Go to service plan</a>');                             
                    } else {
                      $('#service_selector').parent().append(rtn);                
                    }
                  }
                  $('#service_selector').remove();              
                });
              });            
            </script>
          ";
        } else {
          echo "no upcoming services with suitable empty music elements found";
        }    
      } else {
        echo "Error: invalid or retired arrangement (#$arr_id)";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=='accept_arrangement_sent_from_music_db'){
    if ($a->csps>=CW_E){    
      //$_POST has arrangement_id and service_id
      $mdb=new cw_Music_db($a);
      $arr_id=$_POST["arrangement_id"];
      $arr_rec=$mdb->get_arrangement_record($arr_id);
      //If service plan has at least one empty element of meta-type music, assign arrangement to that
      $service_element=$eh->church_services->get_first_empty_music_element($_POST["service_id"],$arr_rec["is_presentation_piece"]);
      $atse_id=$eh->assign_arrangement_to_service_element($arr_id,$service_element);
      if ($atse_id>0){
        echo "OK";
      } else {
        echo "NO_EMPTY_MUSIC_ELEMENTS";
      }
      //Need $_GET["service_id"] to update service at end
      $_GET["service_id"]=$_POST["service_id"];
      $mark_service_update=true;
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_service_plan_pdf") {
    $file_id=$eh->get_service_plan_pdf($_GET["service_id"]);
    if ($file_id>0){
      echo $file_id;
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_music_pdf") {
    $file_id=$eh->get_combined_music_pdf($_GET["service_id"],substr($_GET["pos_id"],3));
    if ($file_id>0){
      echo $file_id;    
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_music_pdf_for_person") {
    //We have positons_to_services.id in $_GET["pts_id"]: retrieve positions_to_services record to get both person_id and pos_id
    $pts_id=substr($_GET["pts_id"],3); 
    if ($pts_record=$eh->event_positions->get_positions_to_services_record_by_id($pts_id)){
      $file_id=$eh->get_combined_music_pdf($_GET["service_id"],$pts_record["pos_id"],$pts_record["person_id"],$pts_record["id"]);
      if ($file_id>0){
        echo $file_id;    
      } else {
        //Could not generate file
        echo "ERR";
      }    
    } else {
      //Could not retrieve pos_to_services record
      echo "ERR";    
    }
  } elseif ($_GET["action"]=="get_lyrics_ppt") {
    $file_id=$eh->get_lyrics_ppt($_GET["service_id"]);
    if ($file_id>0){
      echo $file_id;    
    } else {
      echo "ERR";
    }   
  } elseif ($_GET["action"]=="check_for_service_plan_update"){
    if ($eh->check_sync_mark($_GET["service_id"],"service_order")>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
      //no response indicates no reload necessary    
    } else {
      echo "RELOAD";    
    } 
  } elseif ($_GET["action"]=="display_edit_music_package_dialogue"){
    if ($a->csps>=CW_E){
      //Got $_GET["service_id"] and $_GET["pos_to_services_id"], also $_GET["scrolltop"]
      $dsp = new cw_Display_service_planning($d,$eh,$a);
      echo $dsp->display_edit_music_package_dialogue($_GET["service_id"],$_GET["pos_to_services_id"],$_GET["scrolltop"]);
    } else {
      echo "<span class='red'>Insufficient privileges to edit music package. Hit esc to continue.</span>";
    } 
  } elseif ($_GET["action"]=="music_package_select_partfile"){  
    if ($a->csps>=CW_E){
      //Got $_POST["file_id"],"service_element_id" and "pos_to_services_id"
      //$_POST["select"]?
      if (!(strtolower($_POST["select"])=="false")){
        if ($eh->event_positions->assign_partfile_to_position_and_service_element($_POST["pos_to_services_id"],$_POST["service_element_id"],$_POST["file_id"])){
          echo "OK";    
        } else {
          echo "An error occurred: could not assign this file to this music package";
        }    
      } else {
        //On unselect, both clear music_packages record and unsassign person from service element (pos_to_serv__to_serv_elements)
        if ($eh->event_positions->clear_partfile_for_position_and_service_element($_POST["pos_to_services_id"],$_POST["service_element_id"])){
          if ($eh->event_positions->unassign_position_from_service_element($_POST["pos_to_services_id"],$_POST["service_element_id"])){
            echo "OK";            
          } else {
            echo "An error occurred: could not un-assign this person from this service element";
          }
        } else {
          echo "An error occurred: could not un-assign this file from this music package";
        }    
      }
    }
  } elseif ($_GET["action"]=="music_package_toggle_person_on_service_element"){
    if (($_POST["service_element_id"]>0) && ($_POST["pos_to_services_id"]>0)){
      if ($eh->event_positions->toggle_position_on_service_element($_POST["pos_to_services_id"],$_POST["service_element_id"])){
        echo "OK";              
      }
    } else {
      echo "An error occurred: could not (un-)assign this service element to the person";
    }
  } elseif ($_GET["action"]=="music_package_apply_to_others"){
    if ($a->csps>=CW_E){
      if ($_POST["pos_to_services_id"]>0){
        $applied_to=$eh->apply_music_package_to_others($_POST["pos_to_services_id"],($_GET["same_position_only"]=="true"));
        if (is_array($applied_to)){
          if (sizeof($applied_to)>0){
            $people="";
            foreach ($applied_to as $person_id){
              $people.=", ".$a->personal_records->get_name_first_last($person_id);
            }
            $people=substr($people,2);
            echo "Music package has been applied to: $people";
          } else {
            if ($_GET["same_position_only"]=="true"){
              echo "There is no other person in this position who the music package could be applied to.";                      
            } else {
              echo "There is no other musician or singer in the service who the music package could be applied to.";                                  
            }
          }        
        } else {
          echo "ERR";
        }              
      } else {
        echo "ERR";
      }
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_coffee_order_interface"){
    $dsp = new cw_Display_service_planning($d,$eh,$a);
    echo $dsp->display_coffee_order_dialogue($_GET["service_id"]); 
  } elseif ($_GET["action"]=="coffee_order"){
    //$_POST: "person_id", "checked" ('true' or 'false')
    $co=new cw_Coffee_orders($a,$eh);
    if (strtolower($_POST["checked"])!="false"){
      //Place order
      if ($co->add_coffee_order($_GET["service_id"],$_POST["person_id"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to add this person to this order";
      }      
    } else {
      //Delete order
      $co->delete_coffee_order($_GET["service_id"],$_POST["person_id"]);
      echo "OK";      
    }
  } elseif ($_GET["action"]=="coffee_order_select_drink") {
    //$_POST: "person_id", "drink_id", "size_id", "add_ons"
    $co=new cw_Coffee_orders($a,$eh);
    if ($co->save_drinks_to_people_record($_POST["person_id"],$_POST["drink_id"],$_POST["size_id"],$_POST["add_ons"])){
      echo "OK";
    } else {
      echo "An error occurred while trying to save this drink choice";
    }
  } elseif ($_GET["action"]=="coffee_order_add_new_drink") {
    //$_POST: "person_id", "drink_name"
    //Create new drink, and assign it to person (without changing the rest of the dtp record)
    $co=new cw_Coffee_orders($a,$eh);
    if (!empty($_POST["drink_name"])){
      $new_drink_id=$co->add_drink($_POST["drink_name"]);
      if ($new_drink_id>0){
        if ($co->select_drink($_POST["person_id"],$new_drink_id)){
          echo "OK";
        } else {
          echo "Error: could not assign new drink to this person.";
        }                          
      } else {
        echo "Error: could not generate new drink";
      }
    } else {
     echo "Error: you did not provide a valid drink name";
    }          
  } elseif ($_GET["action"]=="coffee_order_pos_type_group"){
    //$_POST: "pos_type_id", "checked" -- apply order or order cancellation to everyone in this pos_type group (leadership, worship team etc)
    $co=new cw_Coffee_orders($a,$eh);
    $pts_recs=$eh->event_positions->get_positions_for_service($_GET["service_id"],$_POST["pos_type_id"]);
    if (is_array($pts_recs)){
      $error=false;
      foreach ($pts_recs as $pts_rec){
        if (strtolower($_POST["checked"])=="true"){
          if (!$co->add_coffee_order($_GET["service_id"],$pts_rec["person_id"])){
            $error=true;
            break;        
          }                  
        } else {
          if (!$co->delete_coffee_order($_GET["service_id"],$pts_rec["person_id"])){
            $error=true;
            break;        
          }                          
        }
      }
      if ($error){
        echo "An error occurred while processing the group (this really shouldn't happen!)";
      } else {
        echo "OK";
      }    
    } else {
      echo "An error occurred trying to apply this order to this group";
    }    
  } elseif ($_GET["action"]=="get_coffee_order_pdf"){
    $co=new cw_Coffee_orders($a,$eh);
    $file_id=$co->get_coffee_order_pdf($_GET["service_id"]);
    if ($file_id>0){
      echo $file_id;
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_simple_service_plan"){
    if ($a->csps>=CW_V){
      if ($simple_service_plan_content=$eh->get_simple_service_plan($_GET["service_id"],$headers)){
        foreach ($headers as $header){
          header($header);
        }              
        echo $simple_service_plan_content;
      } else {
        echo "Error: could not retrieve service plan data";
      }
    }  
  } elseif ($_GET["action"]=="get_files_for_service_element_for_select"){
    if ($a->csps>=CW_V){
	  	$files=$eh->church_services->get_files_assigned_to_service_element($_GET["element_id"]);
	  	$files_options="";
	  	foreach ($files as $f){
	  		$files_options.="<option value=\"".$f["id"]."\">".$f["name"].".".$f["ext"]."</option>";
	  	}
	  	echo $files_options;
    }
  } elseif ($_GET["action"]=="upload_file_and_link_to_service_element"){  	
  	if ($_GET["element_id"]>0){
  		if ($eh->church_services->add_file_to_service_element($_FILES["filesel"]["tmp_name"], $_FILES["filesel"]["name"], $_GET["element_id"],$a->cuid)){		
  			//Successfully uploaded/stored
  		} else {
  			$rs="An error occurred while trying to add this resource to the mediabase";
  		}
  	} else {
  		$rs="Could not store this resource: unsupported file type";
  	}
  	if (!empty($rs)){
  		//Alert error
  		echo "<script>alert('$rs');</script>";
  	} else {
  		//Success: simply return lib_id of new resource
  	}
  } elseif ($_GET["action"]=="delete_file_from_service_element"){
  	if ($a->csps>=CW_E){
  		if ($_GET["file_id"]==0){
  			echo "Please select the file you want to delete first.";
  		} else {
  			//Got file id. Check if user has uploaded the file. If so, they can delete as editor. If not, admin rights are required.
  			$files=new cw_Files($d);
  			if ($f=$files->get_files_record($_GET["file_id"])){
				if (($f["added_by"]==$a->cuid) || ($a->csps>=CW_A)){
					if ($eh->church_services->remove_file_from_service_element($_GET["file_id"], $_GET["element_id"])){
						
					} else {
						echo "An error occurred while trying to remove the file";
					}
				} else {
					echo "This file does not belong to you. To delete it anyway you need administrative privileges.";
				}  				
  			}
  		}  		
  	} else {
  		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}
  } else {   
    echo "INVALID REQUEST";
  }
        
      
  $p->nodisplay=true;
  
  //Perform an update marking on the service if changes have been made
  if ($mark_service_update){
    //echo "\nservice-id:".$_GET["service_id"];
    $eh->church_services->mark_service_update($_GET["service_id"],$a->cuid);      
  }
?>
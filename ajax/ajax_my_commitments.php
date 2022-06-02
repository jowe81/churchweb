<?php

  require_once "../lib/framework.php";

  $eh=new cw_Event_handling($a);  
  
  if ($_GET["action"]=="get_services_list"){
    //Get IDs of the services that this user is scheduled for
    $sr=$eh->get_upcoming_services($_GET["type"]=="past",$a->cuid);
    $t=""; //HTML
    $s=""; //JQuery
    $cnt=0;
    foreach ($sr as $v){
      //$v is a church_service_id (from church_services)
      $service=new cw_Church_service($eh,$v);
      $dayscol="";
      $timescol="";
      $startsincol="";
      foreach ($service->event_records as $erec){
        $dayscol.=date("l F j Y",$erec["timestamp"])."<br/>";                
        $timescol.=date("h:i a",$erec["timestamp"])."<br/>";
        if (($erec["timestamp"]-time()<0) && ($erec["timestamp"]+$erec["duration"]-time()>0)){
          $startsincol.="<span style='color:green;'>in progress</span><br/>";                
        } elseif (($erec["timestamp"]-time()<(15*MINUTE)) && ($erec["timestamp"]-time()>0)) {
          $startsincol.="<span style='color:red;'>starts in ".getHumanReadableLengthOfTime($erec["timestamp"]-time()+CW_CHURCH_TIME_OFFSET)."</span><br/>";                                          
        } elseif ($erec["timestamp"]+$erec["duration"]-time()<0) {
          $startsincol.="completed<br/>";                
        } else {
          $startsincol.="starts in ".getHumanReadableLengthOfTime($erec["timestamp"]-time()+CW_CHURCH_TIME_OFFSET)."<br/>";                                
        }
      }
      //Update info
      $updated_by=$pr->get_name_first_last($service->service_record["updated_by"]);
      (!empty($updated_by)) ? $updated_by="by $updated_by" : null;
      $uinfo="<div style='font-size:70%;color:gray;padding-left:5px;'><a style='text-decoration:none;' href='".CW_ROOT_WEB."worship_dpt/service_planning.php?action=plan_service&service_id=$v'>service plan</a> updated ".getHumanReadableLengthOfTime(time()-$service->service_record["updated_at"])." ago</div>";
      //Position info
      $position_info="";
      if ($positions=$eh->event_positions->get_positions_to_services_records_for_person_and_service($v,$a->cuid)){
        foreach ($positions as $w){
          $position_info.=", ".$eh->event_positions->get_position_title($w["pos_id"]);          
        }
        if (!empty($position_info)){
          $position_info="You have been scheduled as ".substr($position_info,2);
        }
      }            
      if (substr_count($position_info,",")>0){
        $position_info=substr_replace($position_info," and",strrpos($position_info,","),1);
      }
      if (empty($position_info)){
        $position_info="Warning: position information could not be loaded";
      }
      //Rehearsal info
      $rehearsal_info_dates="";
      $rehearsal_info_times="";
      $rehearsal_info_locations="";
      foreach ($service->rehearsals as $w){
        //Display this rehearsal if a rehearsal_participants record exists for at least one of the pos_to_services records
        $display_rehearsal=false;
        foreach ($positions as $y){
          if ($eh->position_has_rehearsal_participant_record($y["id"],$w["id"])){
            $display_rehearsal=true;
            if ($rehearsal_part_record=$eh->event_positions->get_rehearsal_participant_records_by_pos_to_services($y["id"],$w["id"])){
              if ($rehearsal_part_record["confirmed"]>0){
                $rclass="confirmed";
              } elseif ($rehearsal_part_record["confirmed"]<0) {
                $rclass="disconfirmed";
              } else {
                $rclass="pending";
              }  
            }
          }        
        }
        if ($display_rehearsal){
          //$rehearsal_info_dates.="<div class='$rclass rehearsal_status_service_$v status_div' id='rh_".$w["id"]."' ></div> | ";
          $rehearsal_info_dates.=date("l F j",$w["timestamp"])."<br/>";
          $rehearsal_info_times.=date("h:i a",$w["timestamp"])."<br/>";
          foreach ($w["bookings"] as $x){
            $rehearsal_info_locations.=$eh->rooms->get_roomname($x["room_id"])."<br/>";        
          }
        }
      }
      if (empty($rehearsal_info_dates)){
        $rehearsal_info_dates="<span style='font-weight:normal;color:gray;'>not available</span>";
      }    
      //Commitment info - which boxes are checked? If at least one position is confirmed, check 'yes'
      $response_div_header="";
      $response_div_style="";
      if ($eh->at_least_one_position_confirmed($v,$a->cuid)){
        $y_checked="checked=\"CHECKED\"";
        $n_checked="";        
        $y_span="color:green";
        $n_span="";
      } else {
        //See if all positions are disconfirmed - if not, we have no decision yet
        if ($eh->all_positions_disconfirmed($v,$a->cuid)){
          $y_checked="";
          $n_checked="checked=\"CHECKED\"";              
          $y_span="";
          $n_span="color:red";        
        } else {
          //No decision yet
          $y_checked="";
          $n_checked="";              
          $y_span="";
          $n_span="";  
          $response_div_header="<span id='res_h_$v'>Please respond by clicking one of the checkboxes<br/></span>";
          $response_div_style="background:yellow;text-align:center;";                
        }
      }      
      //Row markup
      $cnt++;
      if (($cnt%2)==0){
        $class="even";
      } else{
        $class="odd";
      }
      $t.="<tr class='$class church_service' id='s$v'>
            <td>
              <table style='border:0px solid blue;margin:10px;'>
                <tr style='font-weight:bold;'>
                  <td style='width:200px;font-size:80%'>".$service->service_name.$uinfo."</td>
                  <td style='width:330px;vertical-align:middle;font-weight:normal;'>".$service->title."</td>
                  <td style='width:210px;font-size:80%;vertical-align:middle;'>$dayscol</td>
                  <td style='width:80px;font-size:80%;vertical-align:middle;'>$timescol</td>
                  <td style='width:140px;font-size:80%;vertical-align:middle;font-weight:normal;' class='gray'>$startsincol</td>
                </tr>
                <tr style='font-weight:bold;'>
                  <td colspan='2' style='border-top:1px solid black; font-size:80%; font-weight:normal;'>
                    $position_info<br/>
                    <div style='$response_div_style padding:3px;' class='noclick' id='response_$v'>
                      $response_div_header
                      <input id='i_y_$v' type='checkbox' $y_checked/> <span id='s_y_$v' style='$y_span'>Yes, I am participating</span>
                      <input id='i_n_$v' type='checkbox' $n_checked/> <span id='s_n_$v' style='$n_span'>No, I cannot participate</span>
                    </div>
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;'>
                    <span style='font-weight:normal;font-style:italic;'>Rehearsal information:</span><br/>
                    $rehearsal_info_dates
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;'>
                    <br/>
                    $rehearsal_info_times
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;font-weight:normal;'>
                    <br/>
                    $rehearsal_info_locations
                  </td>
                </tr>            
              </table>
            </td>
          </tr>
          ";
      $s.="
        $('#i_y_$v').click(function(e){
          e.preventDefault();
          if ($(this).attr('checked')){
            send_commitment_change($v,1);
          }
        });
        $('#i_n_$v').click(function(e){
          e.preventDefault();
          if ($(this).attr('checked')){
            send_commitment_change($v,0,$('#i_y_$v').attr('checked'));
          }
        });
        $('.rehearsal_status_service_$v').click(function(e){
          e.stopPropagation();
          var status_div_id=$(this).attr('id');
          var rehearsal_event_id=$(this).attr('id').substr(3);
          var was_confirmed=$(this).hasClass('confirmed');
          if ((!was_confirmed) || (was_confirmed && confirm('Are you sure you want to disconfirm your participation for this rehearsal?'))){
            $.post('".CW_ROOT_WEB."/ajax/ajax_my_commitments.php?action=send_rehearsal_commitment_change',{ service_id:$v, rehearsal_event_id:rehearsal_event_id },function(rtn){
              if (rtn=='OK'){
                toggle_rehearsal_status(status_div_id);
              }
            });                  
          }
        });
      ";            
    }
    $s.="
      function toggle_rehearsal_status(status_div_id){
        if ($('#'+status_div_id).hasClass('confirmed')){
          $('#'+status_div_id).removeClass('confirmed').addClass('disconfirmed');
        } else {
          $('#'+status_div_id).removeClass('disconfirmed pending').addClass('confirmed');                
        }
      }                
    
      function send_commitment_change(service_id,b,change_of_mind){
        if ( (!(change_of_mind)) || (confirm('Are you sure you want to renounce your earlier decision to make this commitment to serve?')) ){
          $.post('".CW_ROOT_WEB."/ajax/ajax_my_commitments.php?action=send_commitment_change',{ service_id:service_id, value:b },function(rtn){
            if (rtn=='OK'){
              $('#response_'+service_id).css('background','none');
              $('#response_'+service_id).css('text-align','left');
              $('#res_h_'+service_id).remove();
              if (b==1){
                //Confirmation
                $('#i_y_'+service_id).attr('checked','true');            
                $('#i_n_'+service_id).removeAttr('checked');            
                $('#s_y_'+service_id).css('color','green');            
                $('#s_n_'+service_id).css('color','black'); 
                //Rehearsal confirmation display
                $('#s'+service_id+' .status_div').removeClass('disconfirmed pending').addClass('confirmed');           
              } else {
                //Disconfirmation
                $('#i_y_'+service_id).removeAttr('checked');            
                $('#i_n_'+service_id).attr('checked','true');                        
                $('#s_y_'+service_id).css('color','black');            
                $('#s_n_'+service_id).css('color','red');
                //Rehearsal disconfirmation display            
                $('#s'+service_id+' .status_div').removeClass('confirmed pending').addClass('disconfirmed');           
              }          
            }
          });
        }
      }      
    ";
    //Jquery to link table rows    
    $s.="
      $('.church_service').click(function(e){ 
        var id=$(this).attr('id');
        id=id.substr(1); //cut 's'
        if (e.shiftKey){
          if (confirm('Are you sure you want to delete this entire service?')){
            $.post('".CW_AJAX."ajax_service_planning.php?action=delete_service&service_id=' + id ,{},function(ret){
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
            window.location='".CW_ROOT_WEB."worship_dpt/service_planning.php?action=plan_service&service_id='+id;          
          }
        }  
      });
      
      //Make sure that click on checkbox doesn't follow link to service planning 
      $('.noclick').click(function(e){
        e.stopPropagation();
      });
      
    ";
    if (empty($t)){
      echo "<p>At this time there are no upcoming services that you have been scheduled for.</p>";
    } else {
      echo "
        <div id='services_list_inner_container'>
          <table style=''>$t</table>
        </div>
        <div style='height:100px;'>&nbsp;</div>  
        <script type='text/javascript'>$s</script>    
      ";
    }  
  } elseif ($_GET["action"]=="send_commitment_change"){
    $eh->confirm_all(null,$_POST["service_id"],$a->cuid,$_POST["value"]);
    echo "OK";
  } elseif ($_GET["action"]=="send_rehearsal_commitment_change"){
    if ($eh->rehearsal_is_confirmed_for_person($_POST["service_id"],$_POST["rehearsal_event_id"],$a->cuid)){
      $eh->confirm_rehearsal_for_person($_POST["service_id"],$_POST["rehearsal_event_id"],$a->cuid,false);    
    } else {
      $eh->confirm_rehearsal_for_person($_POST["service_id"],$_POST["rehearsal_event_id"],$a->cuid,true);        
    }
    echo "OK";
  } elseif ($_GET["action"]=="get_history"){
    //Get IDs of the services that this user is scheduled for
    $sr=$eh->get_upcoming_services(true,$a->cuid);
    $t=""; //HTML
    $s=""; //JQuery
    $cnt=0;
    foreach ($sr as $v){
      //$v is a church_service_id (from church_services)
      $service=new cw_Church_service($eh,$v);
      $dayscol="";
      $timescol="";
      $startsincol="";
      foreach ($service->event_records as $erec){
        $dayscol.=date("l F j Y",$erec["timestamp"])."<br/>";                
        $timescol.=date("h:i a",$erec["timestamp"])."<br/>";
        $startsincol.="completed<br/>";                
      }
      //Update info
      $uinfo="<div style='font-size:70%;color:gray;padding-left:5px;'>look at the <a style='text-decoration:none;' href='".CW_ROOT_WEB."worship_dpt/service_planning.php?action=plan_service&service_id=$v'>service plan</a></div>";
      //Position info
      $position_info="";
      if ($positions=$eh->event_positions->get_positions_to_services_records_for_person_and_service($v,$a->cuid)){
        foreach ($positions as $w){
          $position_info.=", ".$eh->event_positions->get_position_title($w["pos_id"]);          
        }
        if (!empty($position_info)){
          $position_info="You were scheduled as ".substr($position_info,2);
        }
      }            
      if (substr_count($position_info,",")>0){
        $position_info=substr_replace($position_info," and",strrpos($position_info,","),1);
      }
      //Row markup
      $cnt++;
      if (($cnt%2)==0){
        $class="even";
      } else{
        $class="odd";
      }
      $t.="<tr class='$class church_service' id='s$v'>
            <td>
              <table style='border:0px solid blue;margin:10px;'>
                <tr style='font-weight:bold;'>
                  <td style='width:200px;font-size:80%'>".$service->service_name.$uinfo."</td>
                  <td style='width:330px;vertical-align:middle;font-weight:normal;'>".$service->title."</td>
                  <td style='width:210px;font-size:80%;vertical-align:middle;'>$dayscol</td>
                  <td style='width:80px;font-size:80%;vertical-align:middle;'>$timescol</td>
                  <td style='width:140px;font-size:80%;vertical-align:middle;font-weight:normal;' class='gray'>$startsincol</td>
                </tr>
                <tr style='font-weight:bold;'>
                  <td colspan='2' style='border-top:1px solid black; font-size:80%; font-weight:normal;'>
                    $position_info<br/>
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;'>
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;'>
                  </td>
                  <td style='border-top:1px solid black; font-size:80%;font-weight:normal;'>
                  </td>
                </tr>            
              </table>
            </td>
          </tr>
          ";
      $s.="
        $('#i_y_$v').click(function(e){
          e.preventDefault();
          if ($(this).attr('checked')){
            send_commitment_change($v,1);
          }
        });
        $('#i_n_$v').click(function(e){
          e.preventDefault();
          if ($(this).attr('checked')){
            send_commitment_change($v,0,$('#i_y_$v').attr('checked'));
          }
        });
      ";
    }
    $s.="
      function send_commitment_change(service_id,b,change_of_mind){
        if ( (!(change_of_mind)) || (confirm('Are you sure you want to renounce your earlier decision to make this commitment to serve?')) ){
          $.post('".CW_ROOT_WEB."/ajax/ajax_my_commitments.php?action=send_commitment_change',{ service_id:service_id, value:b },function(rtn){
            if (rtn=='OK'){
              $('#response_'+service_id).css('background','none');
              $('#response_'+service_id).css('text-align','left');
              $('#res_h_'+service_id).remove();
              if (b==1){
                $('#i_y_'+service_id).attr('checked','true');            
                $('#i_n_'+service_id).removeAttr('checked');            
                $('#s_y_'+service_id).css('color','green');            
                $('#s_n_'+service_id).css('color','black');            
              } else {
                $('#i_y_'+service_id).removeAttr('checked');            
                $('#i_n_'+service_id).attr('checked','true');                        
                $('#s_y_'+service_id).css('color','black');            
                $('#s_n_'+service_id).css('color','red');            
              }          
            }
          });
        }
      }
    ";
    //Jquery to link table rows    
    $s.="
      $('.church_service').click(function(e){ 
        var id=$(this).attr('id');
        id=id.substr(1); //cut 's'
        if (e.shiftKey){
          if (confirm('Are you sure you want to delete this entire service?')){
            $.post('".CW_AJAX."ajax_service_planning.php?action=delete_service&service_id=' + id ,{},function(ret){
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
            window.location='".CW_ROOT_WEB."worship_dpt/service_planning.php?action=plan_service&service_id='+id;          
          }
        }  
      });
      
      //Make sure that click on checkbox doesn't follow link to service planning 
      $('.noclick').click(function(e){
        e.stopPropagation();
      });
      
    ";
    if (empty($t)){
      echo "<p>There is not yet a history of services that you have been involved in.</p>";
    } else {
      echo "
        <div id='services_list_inner_container'>
          <table style=''>$t</table>
        </div>
        <div style='height:100px;'>&nbsp;</div>  
        <script type='text/javascript'>$s</script>    
      ";
    }  
  
  } else {
    echo "INVALID REQUEST";
  }
        
      
  $p->nodisplay=true;
  
?>
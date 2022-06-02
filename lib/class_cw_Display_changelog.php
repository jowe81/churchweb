<?php

  class cw_Display_changelog {
  
    private $d,$auth,$cl; //Database access
    
    function __construct($cw_Changelog){
      $this->cl=$cw_Changelog;
      $this->d=$cw_Changelog->auth->d;
      $this->auth=$cw_Changelog->auth;
    }

    function display_tickets_for_version($version_id,$orderby="",$all_my_tickets=false){
      if ($all_my_tickets){
        $i=$this->cl->get_my_tickets($orderby);  
        $backlink="get_my_tickets";    
      } else {
        $i=$this->cl->get_tickets_for_version($version_id,$orderby);      
        $backlink="get_tickets_for_version";    
      }
      if (is_array($i)){
        if (sizeof($i)>1){
          //order direction for next link
          $opposite_direction="";
          if (strpos($orderby,"DESC")===false){
            $opposite_direction="DESC";
          }
          $t="
            <span style='font-size:80%;'>
              Found ".sizeof($i)." report(s) |
              Group by
                <a id='o_service_id'>service id</a> | 
                <a id='o_date'>date submitted</a> | 
                <a id='o_author'>author id</a> | 
                <a id='o_status'>status</a> | 
                <a id='o_priority'>priority</a>
            </span>
            <script type='text/javascript'>
              var url='".CW_AJAX."ajax_changelog.php?action=$backlink&version_id=$version_id&direction=$opposite_direction&orderby=';
              $('#o_service_id').click(function(){
                $('#tickets').load(url+'service');
              });
              $('#o_date').click(function(){
                $('#tickets').load(url+'noted_at');
              });
              $('#o_author').click(function(){
                $('#tickets').load(url+'noted_by');
              });
              $('#o_status').click(function(){
                $('#tickets').load(url+'status');
              });
              $('#o_priority').click(function(){
                $('#tickets').load(url+'accepted_priority');
              });
            </script>
          ";        
        } else {
          $t="<span style='font-size:80%;'>Found ".sizeof($i)." report(s)</span>";
        }        
        foreach ($i as $ticket){
          $t.=$this->display_ticket_record($ticket);        
        }
        //Jquery for them all
        $t.="
          <script type='text/javascript'>
            $('.b_process').click(function(){
              var id=$(this).attr('id').substring(1);
              $.post('".CW_AJAX."ajax_changelog.php?action=process_ticket&ticket_id='+id,{ status:$('#f_status_'+id).val(),priority:$('#f_priority_'+id).val() },function(rtn){
                if (rtn=='OK'){
                  //Reload tickets
                  $('#tickets').load('".CW_AJAX."ajax_changelog.php?action=get_tickets_for_version&version_id=$version_id');
                } else {
                  alert(rtn);
                }
              });                          
            });
            $('.b_deploy').click(function(){
              var deployment_comment;
              var id=$(this).attr('id').substring(1);
              if ((deployment_comment = prompt('Deployment comment',$('#titlespan_'+id).text() ))){
                $.post('".CW_AJAX."ajax_changelog.php?action=process_ticket&ticket_id='+id,{ status:0,deployment_comment:deployment_comment },function(rtn){
                  if (rtn=='OK'){
                    //Reload tickets
                    $('#tickets').load('".CW_AJAX."ajax_changelog.php?action=get_tickets_for_version&version_id=$version_id');
                  } else {
                    alert(rtn);
                  }
                });                          
              }              
            }); 
            $('.b_support').click(function(){
              var id=$(this).attr('id').substring(1);            
              $.post('".CW_AJAX."ajax_changelog.php?action=support_ticket&ticket_id='+id,{},function(rtn){
                if (rtn!='ERR'){
                  //Reload tickets
                  $('#tickets').load('".CW_AJAX."ajax_changelog.php?action=get_tickets_for_version&version_id=$version_id');
                } else {
                  alert('An error occurred');
                }
              });                          
            });
            $('.b_edit').click(function(){
              var id=$(this).attr('id').substring(1);
              window.location='?action=edit_ticket&ticket_id='+id;
            });         
          </script>
        ";
        return $t;      
      } else {
        return "An error occurred wihle retrieving reports";      
      }      
    }

    function display_ticket_record($r){
      $status=$this->cl->ticket_status_to_str($r["status"],true);
      $type=$this->cl->ticket_type_to_str($r["type"]);
      $service_name=$this->auth->services->get_service_title($r["service"]);
      (empty($service_name)) ? $service_name="N/A" : null;
      if ($r["accepted_priority"]==0){
        $priority="pending";
      } else {
        $priority=$this->cl->priority_to_str($r["accepted_priority"]);      
      }
      $updated_at="N/A";
      if ($r["updated_at"]>0){
        $updated_at=date("Y/m/d H:i:s",$r["updated_at"]);
      }
      $updated_by="N/A";
      if ($r["updated_by"]>0){
        $updated_by=$this->auth->personal_records->get_name_first_last($r["updated_by"]);
      }
      $noted_by=$this->auth->personal_records->get_name_first_last($r["noted_by"])." <span class='gray'>/".$r["noted_by"]."</span>";
      if (!$this->cl->supported($r["id"])){
        //User has not supported ticket yet
        $support_text="support this report";      
      } else {
        //User has supported ticket
        $support_text="unsupport";      
      }
      if (($r["status"]>0) && ($r["noted_by"]!=$this->auth->cuid)){
        //Support option only for open tickets and those that aren't my own
        $support="<a style='color:black;font-weight:bold;background:yellow;' class='b_support' id='x".$r["id"]."'>$support_text</a>";      
      }
      $header_bg="";
      if ($r["status"]>0){
        $header_bg="background:#FF6666;";
        if ($r["type"]>1){
          $header_bg="background:#AAF;";        
        }
      }      
      $supporter_ids=explode(',',$r["supporters"]);
      $supporters="";
      foreach ($supporter_ids as $s){
        $supporters.=", ".$this->cl->auth->personal_records->get_name_first_last($s);
      }
      $supporters=substr($supporters,2);//cut comma
      $admin_links="";
      if (($this->auth->csps>=CW_A) && ($r["status"]!=0)){
        $priority_options="";
        for($x=1;$x<=5;$x++){
          ($x==$r["suggested_priority"]) ? $selected="SELECTED" : $selected=""; //default to suggested priority
          $priority_options.="<option $selected value=\"$x\">".$this->cl->priority_to_str($x)."</option>";
        }
        $status_options="";
        for($x=-1;$x<=3;$x++){
          ($x==1) ? $x++: null; //skip "open"
          ($x==2) ? $selected="SELECTED" : $selected=""; //default to "in process"
          $status_options.="<option $selected value=\"$x\">".$this->cl->ticket_status_to_str($x)."</option>";
        }
        ($r["status"]!=1) ? $deploybutton="<input class='b_deploy' id='r".$r["id"]."' type='submit' value='deploy immediately'/>" : $deploybutton="";        
        $admin_links="
          <div style='border:1px solid gray;border-top:none;padding:1px;margin-left:3px;margin-right:3px;float:left;width:1150px;background:#EED;'>
            Select priority:
            <select id='f_priority_".$r["id"]."'>
              $priority_options
            </select>          
            Select status:
            <select id='f_status_".$r["id"]."'>
              $status_options
            </select>
            <input class='b_edit' type='button' id='t".$r["id"]."' value='edit report'/>
            $deploybutton          
            <input class='b_process' id='s".$r["id"]."' type='submit' value='process report'/>
          </div>          
        ";
      } 
      $title=$r["title"];
      if ($r["status"]==0){
        //For deployed tickets, display deployment comment instead of title (should be identical if there was no comment)
        $title=$r["deployment_comment"];
      }
      $t="
        <div style='font-size:80%;border:1px solid gray;padding:1px;margin:3px;margin-bottom:0px;float:left;width:1150px;background:#EED;'>
          <div style='$header_bg' class='ticket_header'><span id='titlespan_".$r["id"]."' style='font-weight:bold;'>".$title."</span><div style='float:right;'>$support</div></div>
          <div style='width:500px;float:left;'>
            <table class=\"ticket_display\" style=''>
              <tr>
                <td class=\"caption\">Report ID:</td>
                <td class=\"table_data\">".$r["id"]."</td>
                <td class=\"caption\">Noted by:</td>
                <td class=\"table_data\">".$noted_by."</td>
              </tr>            
              <tr>
                <td class=\"caption\">Service:</td>
                <td class=\"table_data\">".$service_name." <span class='gray'>/".$r["service"]."</span></td>
                <td class=\"caption\">Noted at:</td>
                <td class=\"table_data\">".date("Y/m/d H:i:s",$r["noted_at"])."</td>
              </tr>
              <tr>
                <td class=\"caption\">Type:</td>
                <td class=\"table_data\">".$type."</td>
                <td class=\"caption\">Noted on version:</td>
                <td class=\"table_data\">".$this->cl->get_version_name($r["noted_on_version"])."</td>
              </tr>
              <tr>
                <td class=\"caption\">Status:</td>
                <td class=\"table_data\">".$status."</td>
                <td class=\"caption\">Last processed by:</td>
                <td class=\"table_data\">".$updated_by."</td>
              </tr>
              <tr>
                <td class=\"caption\">Priority:</td>
                <td class=\"table_data\">".$priority."</td>
                <td class=\"caption\">Last processed at:</td>
                <td class=\"table_data\">$updated_at</td>
              </tr>
              <tr>
                <td class=\"caption\">Supported by:</td>
                <td colspan=\"3\" style=''><span>$supporters</span></td>
              </tr>
            </table>
          </div>
          <div style='float:right;width:543px;padding:2px;'>
            <span class='gray'>Description:</span> ".$r["description"]."
          </div>                   
        </div>
        $admin_links
      ";
      return $t;
    } 

    function get_new_ticket_interface($form_action="?action=go_to_version&version_id=&submit=yes"){
      $report_types_select=$this->get_select_with_report_types();
      $concerned_services_select=$this->get_select_with_my_permitted_services();
      $priorities_select=$this->get_select_with_priorities();
      $t="
        <div style='height:255px;border:1px solid gray;margin:5px;'>
          <form action=\"$form_action\" method=\"POST\">
          <div style='padding:5px; width:300px;float:left;'>
            Report type:
            $report_types_select
            Service concerned:
            $concerned_services_select
            Suggested priority:
            $priorities_select
          </div>
          <div style='padding:5px; '>
            <div class='expl'>Use this form to submit anything that concerns the development process of ChurchWeb. If something malfunctions, select report type=bug report, and describe the error. If you have an idea about how to improve ChurchWeb or how to make the interface better, file a feature request. In either case be sure to be as clear as possible and to provide detail. Thank you for your help!</div>
            Report title: <span class='small_note'>(summarize issue/request in one sentence)</span><br/>
            <input id='f_ticket_title' name='f_ticket_title' type='text' style='width:500px;'/><br/>
            Detailed report description: <span class='small_note'>(for bug reports: give detail on how to reproduce the error)</span><br/>
            <textarea name='f_ticket_description' style='width:500px;'></textarea>
          </div>
          <div style='border-top:1px solid gray;padding:5px;'>
            <input id='submit_form' type='submit' value='Submit report'/>
            <input type='reset' value='Reset form'/>
          </div>
          </form>
        </div>
        <script type='text/javascript'>
          $('#submit_form').click(function(e){
            if ($('#f_ticket_type').val()==''){
              e.preventDefault();
              alert('Error: You must specify a report type');            
            }
            if ($('#f_service').val()==''){
              e.preventDefault();
              alert('Error: You must select the service that your report is about');            
            }
            if ($('#f_suggested_priority').val()==''){
              e.preventDefault();
              alert('Error: You must assign a priority to your report');            
            }
            if ($('#f_ticket_title').val()==''){
              e.preventDefault();
              alert('Error: You must provide a report title');            
            }
          });          
        </script>      
      ";
      return $t;    
    }

    function get_edit_ticket_interface($ticket_id=0,$form_action="?action=go_to_version&version_id=&submit=yes"){
      $ticket=$this->cl->get_ticket_record($ticket_id);//load ticket record if id given
      $report_types_select=$this->get_select_with_report_types($ticket["type"]);
      $concerned_services_select=$this->get_select_with_my_permitted_services($ticket["service"]);
      $suggested_priorities_select=$this->get_select_with_priorities($ticket["suggested_priority"],"f_suggested_priority");
      $accepted_priorities_select=$this->get_select_with_priorities($ticket["accepted_priority"],"f_accepted_priority");
      $t="
        <div style='height:295px;border:1px solid gray;margin:5px;background:#FFE;'>
          <form action=\"$form_action\" method=\"POST\">
          <div style='padding:5px; width:300px;float:left;'>
            Report type:
            $report_types_select
            Service concerned:
            $concerned_services_select
            Suggested priority:
            $suggested_priorities_select
            Accepted priority:
            $accepted_priorities_select
          </div>
          <div style='float:left; padding:5px;'>
            Report title: <span class='small_note'>(summarize issue/request in one sentence)</span><br/>
            <input id='f_ticket_title' name='f_ticket_title' type='text' style='width:500px;' value=\"".htmlspecialchars($ticket["title"])."\" /><br/>
            Detailed report description: <span class='small_note'>(for bug reports: give detail on how to reproduce the error)</span><br/>
            <textarea name='f_ticket_description' style='width:500px;height:152px;'>".$ticket["description"]."</textarea>
          </div>
          <div style='float:left;width:100%;border-top:1px solid gray;padding:5px;'>
            <input id='submit_form' type='submit' value='Save report'/>
            <input type='reset' value='Reset form'/>
            <input type='hidden' name='ticket_id' value='".$ticket_id."'/>
          </div>
          </form>
        </div>
        <script type='text/javascript'>
          $('#submit_form').click(function(e){
            if ($('#f_ticket_type').val()==''){
              e.preventDefault();
              alert('Error: You must specify a report type');            
            }
            if ($('#f_service').val()==''){
              e.preventDefault();
              alert('Error: You must select the service that your report is about');            
            }
            if ($('#f_suggested_priority').val()==''){
              e.preventDefault();
              alert('Error: You must assign a priority to your report');            
            }
            if ($('#f_ticket_title').val()==''){
              e.preventDefault();
              alert('Error: You must provide a report title');            
            }
          });          
        </script>      
      ";
      return $t;    
    }

    function get_select_with_priorities($preselected_priority=0,$select_name="f_suggested_priority"){
      $p1="";
      $p2="";
      $p3="";
      $p4="";
      $p5="";
      switch ($preselected_priority){
        case 1:$p1="SELECTED";break;
        case 2:$p2="SELECTED";break;
        case 3:$p3="SELECTED";break;
        case 4:$p4="SELECTED";break;
        case 5:$p5="SELECTED";break;
      }
      $t="
            <select id='$select_name' name='$select_name' style='width:250px;'>
              <option value=\"\">(select priority...)</option>
              <option $p1 value=\"1\">very low</option>
              <option $p2 value=\"2\">low</option>
              <option $p3 value=\"3\">medium</option>
              <option $p4 value=\"4\">high</option>
              <option $p5 value=\"5\">critical</option>
            </select>
      ";
      return $t;
    }

    function get_select_with_report_types($preselected_type=0){
      $rt1="";
      $rt2="";
      switch ($preselected_type){
        case 1:$rt1="SELECTED";break;
        case 2:$rt2="SELECTED";break;
      }
      $t="
            <select id='f_ticket_type' name='f_ticket_type' style='width:250px;'>
              <option value=\"\">(select report type...)</option>
              <option $rt1 value=\"1\">bug report</option>
              <option $rt2 value=\"2\">feature request</option>
            </select>
      ";
      return $t;
    }
    
    function get_select_with_my_permitted_services($preselected_service=0,$select_name='f_service'){
      /*
        Pseudo-code:
          get service hierarchy @root level
          foreach service
            if service permission>-1 or if service has child with permission>-1
              if service is script
                display active
              else
                display inactive
      */
      $full_service_hierarchy=array();
      $this->auth->services->get_service_hierarchy($full_service_hierarchy);
      foreach ($full_service_hierarchy as $s){
        if ($this->auth->my_permitted_services[$s["id"]]>-1){
          if ($s["type"]=="script"){
            //Leaf
            $color="";
            //$leaf="L ";
            $disabled="";
          } else {
            //$color="color:blue;";
            $leaf="";
            $disabled="DISABLED";
          }
          $sel="";
          if ($s["id"]==$preselected_service){
            $sel="SELECTED";
          }
          $t.="<option $sel value=\"".$s["id"]."\" $disabled style=\"$color\">$leaf".str_repeat("&nbsp;&nbsp;",($s["lvl"]-1))." ".$this->auth->services->get_service_title($s["id"])."</option>";
        } else {
        
        }
      }
      return "<select id='$select_name' name='$select_name' style='width:250px;'><option value=''>(select service...)</option>$t</select>";
    }

  }

?>
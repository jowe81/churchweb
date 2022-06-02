<?php

  require_once "../lib/framework.php";

  $p->stylesheet(CW_ROOT_WEB."css/service_planning.css");
  
  if ($_GET["action"]=="showtable"){
    //Display one of the allowed database tables
    $allowed_tables=array("positions","service_element_types","group_templates","rooms");
    if (in_array($_GET["table"],$allowed_tables)){
      if($a->csps>=CW_A){
        if ($rows=$d->get_table($_GET["table"])){
          $t="<p>Table ".$_GET["table"]." (".sizeof($rows)." rows)</p>";
          $t.="<table>";
          if (sizeof($rows)>0){
            $t.="<tr>";
            foreach ($rows[0] as $cell_name=>$c){
              $t.="<th style='border:1px solid black;'>$cell_name</th>";
            }
            $t.="</tr>";
          }
          foreach ($rows as $row){
            $t.="<tr>";
            foreach ($row as $cell){
              $t.="<td style='border:1px solid black;'>$cell</td>";          
            }
            $t.="</tr>";
          }
          $t.="</table>";
        } else {
          $t="Error: could not retrieve table";
        }
        $p->p($t);
        $p->no_page_framework=true;
      }    
    } else {
      $p->error("Invalid operation");
    }   
  } else {
    //reload_services_list must be globally available
    $p->js("
      var reload_timeout;
      
      function reload_services_list(timestamp){
        if (!timestamp){
          var timestamp=0;
        }
        $('#services_list').load('".CW_AJAX."ajax_service_planning.php?action=get_services_list&timestamp='+timestamp);
        clearTimeout(reload_timeout);
        reload_timeout=setTimeout(reload_services_list,".CW_SERVICE_PLANNING_SERVICE_LIST_RELOAD_INTERVAL."*1000);      
      }
    ");
    if ($_GET["action"]==""){
      //No specific action - show list of upcoming services
      $p->p("            
        <div id='services_list_container'>
          <div style='padding:5px;'>
            <div id='services_list'>
            </div>
          </div>
        </div>
        <div id='services_list_filter_container'>
        </div>      
        <div id='tasks'>
        </div>
      ");
      $p->js("
        function reload_services_list_filter_interface(timestamp){
          $('#services_list_filter_container').load('".CW_AJAX."ajax_service_planning.php?action=services_list_filter_interface&timestamp='+timestamp);
        }      
      ");
      //Ajax in the list of upcoming services
      $p->jquery("
        reload_services_list();              
      ");
      //Ajax in the task links
      $p->jquery("        
        $('#tasks').load('".CW_AJAX."ajax_service_planning.php?action=get_admin_task_links&auth_level=".$a->csps."');
      ");
      //Help section on the right
      $p->p("<img style='height:10px;width:10px;margin:0px;padding:0px;' id='get_help' src='".CW_ROOT_WEB."img/help.gif'/>");
      if ($a->csps>=CW_A){
        $help_ticket=7;
      } elseif ($a->csps>=CW_E){
        $help_ticket=8;
      } else {
        $help_ticket=9;
      }
      $p->jquery("
        $('#get_help').click(function(){
          get_help($help_ticket);
        });        
      ");
      
      
      
    } elseif ($_GET["action"]=="plan_service"){
    
      $eh=new cw_Event_handling($a);
      $cs=new cw_Church_service($eh,$_GET["service_id"]);
      $service_title=$cs->service_name.": ".$cs->title;
    
      //Get planning conversation
      $cv=new cw_Conversations($a);
      $dcv=new cw_Display_conversations($cv);
      $conversation=$dcv->display_conversation($eh->church_services->get_planning_conversation_id_for_service_plan($_GET["service_id"]));
      
      //Distinguish viewer and editor/admin
      $notifications="";
      $rehearsal_actions="";
      $people_actions="";
      $rehearsal_header_style="style='margin-top:0px;'"; //This only for viewers (when top section is rehearsals)
      $service_plan_files="
            <a href='' id='link_to_service_plan_pdf'>Service plan PDF</a> 
      ";
      $production_settings="";
      if ($a->csps>=CW_E){
        //Editor/admin sees everything,...
        if (!$eh->church_services->service_is_past($_GET["service_id"])){
          //...if its not a past service
          $rehearsal_actions="
              <div id='schedule_rehearsal_link'><a href='' id='new_rehearsal'>Schedule a rehearsal</a></div>        
          ";
          $people_actions="
              <div id='open_positions'>loading...</div>
              <div id='available_volunteers'>loading...</div>
              <div id='schedule_person_link'><a href='' id='new_person'>Schedule a person</a></div>        
          ";
          $rehearsal_header_style="";        
        }
        //Even for past services, editors/admins may get ppt
        $service_plan_files="
            <div class='service_plan_links' style='text-align:right;'>
              <a href='' id='link_to_service_plan_pdf'>Full (pdf)</a> | 
              <a href='".CW_AJAX."ajax_service_planning.php?action=get_simple_service_plan&service_id=".$_GET["service_id"]."' id='link_to_simple_service_plan'>Simple (txt)</a> | 
              <a href='' id='link_to_lyrics_ppt'>Lyrics (ppt)</a> | 
              <a href='' id='link_to_coffee_order_pdf'>Coffee order</a>
            </div>        
        ";
        $notifications="
            <h4 style='margin-top:0px;'>Notifications</h4>
            <div id='notification_note' class='notification_note'>
            </div>
        ";
        $production_settings="
        	<h4>Live Production</h4>
        	<div class='service_plan_links' style='text-align:right;'>
        		<a href='' id='link_to_projection_settings'>Projection Settings</a>
        	</div>
        		
        		
        ";
      }
      
      //This a callback from the mediabase?
      $load_service_element_dialogue="";
      if ($_GET["show_edit_service_element_dialogue"]=="true"){
      	if ($element=$eh->church_services->get_service_element_record($_GET["service_element_id"])){
      		//Attach selected media to service element
      		$mb=new cw_Mediabase($a);
      		$eh->church_services->assign_background_to_service_element($_GET["service_element_id"], $_GET["lib_id"], $mb,$a->cuid);
      		//Auto load service element edit dialogue
      		$load_service_element_dialogue="
      				edit_service_element(".$element["element_nr"].");
      	";      		
      	}      	
      }
      
      $p->p("            
        <div id='left_container'>
          <div style='padding:5px;'>
            $notifications
            <h4 $rehearsal_header_style>Rehearsals</h4>
            $rehearsal_actions
            <div id='rehearsal_list'>
            </div>        
            <h4>People</h4>
            $people_actions
            <div id='position_list'>
            </div>
            <h4>Service plan files</h4>
            $service_plan_files
      		$production_settings
          </div>
        </div>
        
        <div id='center_container'>
          <div style='padding:5px;'>
            <h4 id='church_service_title' style='color:black;'>$service_title</h4>
            <h5>".$cs->get_service_times_string()."</h5>
            <div id='service_order'>
            </div>
          </div>        
        </div>
                          
        <div id='right_container'>
          <div style='padding:5px;'>
            <h4>Planning conversation</h4>
            <div id='planning_conversation'>
              $conversation
            </div>
          </div>
        </div>      
        
      ");
    }
    
    //Links
    $p->jquery("
      //Esc closes modal
      $(document).keyup(function(e){
        //If modal is visible, close on esc
        if ($('#modal').is(':visible')){
          if (e.keyCode==27) {
            if ($('#modal').data('dialog_name')=='edit_service_element'){
              //This was service element dialog - so trigger 'save' (will close modal, too)
              $('#save').click();            
            } else {
              close_modal();
            }
          }
        } else {
          //Shift key let go - unmark element
          var el=$('#sortable').data('hover');
          if (el){
            if (e.keyCode==16){
              $('#'+el).css('background','');            
            }
          }        
        }
      });
      
      //Shiftkey held while hover - mark element
      $(document).keydown(function(e){
        if (!$('#modal').is(':visible')){
          var el=$('#sortable').data('hover');
          if (el){
            if (e.keyCode==16){
              $('#'+el).css('background','#FAA');            
            }
          }
        }
      });
      
        
      $('#new_person').click(function(e){
        e.preventDefault();
        show_modal('".CW_AJAX."ajax_service_planning.php?action=display_people_dialogue&service_id=".$_GET["service_id"]."',150,150,350);
      });
      
      $('#new_rehearsal').click(function(e){
        e.preventDefault();
        show_modal('".CW_AJAX."ajax_service_planning.php?action=display_rehearsal_dialogue&service_id=".$_GET["service_id"]."',50,150,950);
      });
      
      $('#link_to_service_plan_pdf').click(function(e){
        e.preventDefault();
        $.post('".CW_AJAX."ajax_service_planning.php?action=get_service_plan_pdf&service_id=".$_GET["service_id"]."',{},function(rtn){
          if (rtn!='ERR'){
            //rtn has file-id for pdf file
            window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$a->csid."') + '&b=' + rtn;
          } else {
            alert('An error occurred while trying to generate the PDF-file for download');
          }
        });                          
      });
      
      $('#link_to_lyrics_ppt').click(function(e){
        e.preventDefault();
        show_please_wait('Please wait while we generate your PowerPoint file...');
        $.post('".CW_AJAX."ajax_service_planning.php?action=get_lyrics_ppt&service_id=".$_GET["service_id"]."',{},function(rtn){
          hide_please_wait();
          if (rtn!='ERR'){
            //rtn has file-id for pdf file
            window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$a->csid."') + '&b=' + rtn;
          } else {
            alert('An error occurred while trying to generate the PowerPoint-file for download');
          }
        });                              
      });
            
      $('#link_to_coffee_order_pdf').click(function(e){
        e.preventDefault();
        show_coffee_order_dialogue();
      });

      $('#link_to_projection_settings').click(function(e){
        e.preventDefault();
        show_projection_settings_dialogue();    		
  	  });
  
      //init
      
      reload_all();
      init_service_plan_sync();
      
      //Return call from mediabase?
      if ('".$_GET["show_projection_settings_dialogue"]."'=='true'){
      		//Store/assign selected media
      	   	$.get('".CW_AJAX."ajax_service_planning.php?action=assign_background_media_to_service&service_id=".$_GET["service_id"]."&lib_id=".$_GET["lib_id"]."',function(res){
      	   		if (res!=''){
      	   			//Error
      	   			alert(res);
      	   		}
           		show_projection_settings_dialogue();
      		});
      }
      
      //Reload the parts of the service plan if the ajax request sends the RELOAD signal      
      function init_service_plan_sync(){
        $.get('".CW_AJAX."ajax_service_planning.php?action=check_for_service_plan_update&service_id=".$_GET["service_id"]."',function(rtn){
          if (rtn=='RELOAD'){
            reload_all();
          }
        });
        setTimeout(function(){init_service_plan_sync();},".CW_SERVICE_PLANNING_SYNC_INTERVAL."*1000);              
      }
      
      function reload_all(){
        reload_service_title();
        reload_service_order('auto');
        reload_position_list(); 
        reload_rehearsal_list();
        reload_notification_note();       
      }
      $load_service_element_dialogue
    ");
    
    //Deliver the edit service element function according to privileges 
    if ($a->csps>=CW_E){
      $edit_service_element="
        var url='".CW_AJAX."ajax_service_planning.php?action=edit_service_element&service_id=".$_GET["service_id"]."&pos=' +element_position;
        show_modal(url,75,15,1200,'edit_service_element');
        $('#modal').data('element_position',element_position); //Store position of element with modal element, in case user is going to delete the element from within the dialogue                                                        
      ";
    } else {
      $edit_service_element="
        alert('".CW_ERR_INSUFFICIENT_PRIVILEGES."');
      ";  
    }
    
    $p->js("
    
      //Load, position and show modal window
      function show_modal(url,top,left,width,dialog_name){
        $('#main_content').fadeTo(500,0.3);
        if (top){
          $('#modal').css('top',top + 'px');
        }
        if (left){
          $('#modal').css('left',left + 'px');
        }
        if (width){
          $('#modal').css('width',width + 'px');
        }
        
        if (dialog_name){
          $('#modal').data('dialog_name',dialog_name);
        } else {
          //If no dialog_name was given, delete it
          $('#modal').data('dialog_name','');        
        }
        $('#modal').load(url,function(){
        	$('#modal').fadeIn(200);                        
  		});
      }
    
      function close_modal(no_reload){
            $('#main_content').fadeTo(500,1);
            $('#modal').hide(200);
            //Reload all
            if (!no_reload){
              reload_service_title();
              reload_position_list();  
              reload_rehearsal_list();
              reload_notification_note();
              reload_service_order(get_element_visibility('#element_selector'));
            }
      }    
      
      function reload_service_title(){
        $('#church_service_title').load('".CW_AJAX."ajax_service_planning.php?action=get_service_title&service_id=".$_GET["service_id"]."');
      }      

      //Position list reload includes the open_positions and available_volunteers divs
      function reload_position_list(){
        $('#position_list').load('".CW_AJAX."ajax_service_planning.php?action=get_position_list&service_id=".$_GET["service_id"]."');
        $('#available_volunteers').load('".CW_AJAX."ajax_service_planning.php?action=get_available_volunteers&service_id=".$_GET["service_id"]."');
        $('#open_positions').load('".CW_AJAX."ajax_service_planning.php?action=get_open_positions&service_id=".$_GET["service_id"]."');
      }
      
      function reload_rehearsal_list(){
        $('#rehearsal_list').load('".CW_AJAX."ajax_service_planning.php?action=get_rehearsal_list&service_id=".$_GET["service_id"]."');      
      }
  
      function reload_notification_note(){
        $('#notification_note').load('".CW_AJAX."ajax_service_planning.php?action=get_notification_note&service_id=".$_GET["service_id"]."');      
      }
  
      function reload_service_order(show_element_list){
        $('#service_order').load('".CW_AJAX."ajax_service_planning.php?action=get_service_order&service_id=".$_GET["service_id"]."&show_element_list='+show_element_list);
      }
  
      function show_coffee_order_dialogue(){
        show_modal('".CW_AJAX."ajax_service_planning.php?action=get_coffee_order_interface&service_id=".$_GET["service_id"]."',85,130,950,'coffee_order');      
      }
    		
      function show_projection_settings_dialogue(){
        var url='".CW_AJAX."ajax_service_planning.php?action=get_edit_projection_settings_interface&service_id=".$_GET["service_id"]."';
        show_modal(url,75,80,1050,'edit_projection_settings');
      }

      function get_element_visibility(element){
        var res=-1;
        if ($(element).is(':visible')){
          res=1;                    
        }
        return res;      
      }
      
      function zerofill(s,length){
        var n='';
        n+=s;
        while (n.length<length){
          n='0'+n;
        }
        return n;
      }
  
  
      function apply_group_template(element_position,template_id,nosync){
        $.get('".CW_AJAX."ajax_service_planning.php?action=apply_group_template_to_service_element&nosync='+nosync+'&element_position='+element_position+'&service_id=".$_GET["service_id"]."&template_id='+template_id,function(rtn){
          if (rtn!='OK'){
            alert(rtn);                  
          }
          reload_service_order(get_element_visibility('#element_selector'));
        });                      
      }
  
      function generate_service_element(element_type_id,element_position,edit_after_generate,attach_to_group_after_generate){
        $.get('".CW_AJAX."ajax_service_planning.php?action=generate_service_element&element_type_id='+element_type_id+'&element_position='+element_position+'&service_id=".$_GET["service_id"]."',function(rtn){
          if ((rtn=='OK') || (rtn>0)){
            if (edit_after_generate){
              edit_service_element(element_position);
            } else {
              //No edit - but see if it's a group header, in which case apply the default template
              if (element_type_id=='egroup_header'){
                //Apply default group_template - and because this is right after element creation, omit sync               
                apply_group_template(element_position,0,true);
              } 
            }
            if (attach_to_group_after_generate){
              repos_service_element(0,element_position,true); //Do pseudo-repos to attach element
            }                                                
          } else {
            if (rtn=='ERR'){
              alert('Error: could not create service element');
            } else {
              alert(rtn);
            }                                    
          }
          reload_service_order(get_element_visibility('#element_selector'));
        });
      }
      
      //Identify element via position in the service
      function edit_service_element(element_position){
        $edit_service_element
      }
      
      function delete_service_element(element_position){
        $.get('".CW_AJAX."ajax_service_planning.php?action=delete_service_element&element_position='+element_position+'&service_id=".$_GET["service_id"]."',function(rtn){
          if (rtn!='OK'){
            alert(rtn);                  
          }
          reload_service_order(get_element_visibility('#element_selector'));
        });
      }
  
  
      function repos_service_element(element_id,element_position,altKey,ctrlKey){
        var group;
        if (altKey){
          //On Alt try to attach to group
          group='&group=attach';
          if (ctrlKey){
            //On Alt+Ctrl detach
            group='&group=detach';
          }
        } else {
          group='';
        }
        $.get('".CW_AJAX."ajax_service_planning.php?action=repos_service_element&element_id='+element_id+'&element_position='+element_position+'&service_id=".$_GET["service_id"]."'+group,function(rtn){
          if (rtn!='OK'){
            //Repos failed
            alert(rtn);                  
            reload_service_order(get_element_visibility('#element_selector'));
          } else {
            //Repositioning succeeded, duplication required? (if !altKey && ctrlKey)
            if (!altKey && ctrlKey){
              $.get('".CW_AJAX."ajax_service_planning.php?action=duplicate_service_element&element_id='+element_id+'&service_id=".$_GET["service_id"]."',function(rtn){
                if (rtn!='OK'){
                  alert(rtn);                  
                } else {
                  //Duplication succeeded
                }
                //Reload after duplication attempt regardless of outcome
                reload_service_order(get_element_visibility('#element_selector'));
              });              
            } else {
              //Repos but no duplication - reload service order
              reload_service_order(get_element_visibility('#element_selector'));            
            }
          }
        });
      }
            
      function show_please_wait(message){
        $('body').append('<div id=\"please_wait\">' + message + '</div>');
      }
      
      function hide_please_wait(){
        $('#please_wait').remove();    
      }
  
    
    ");  
  }

  
?>
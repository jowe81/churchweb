<?php

require_once "../lib/framework.php";

$eh=new cw_Event_handling($a);

if ($_GET["action"]=="get_services_list"){
	$sr=$eh->get_services($timestamp);
	if (sizeof($sr)>0){
		foreach ($sr as $service_id){
			$service=new cw_Church_service($eh,$service_id);
			$service_title="";
			if ($service->title!=""){
				$service_title="<div class='service_title'>".$service->title."</div>";
			}
			$times="";
			foreach ($service->event_records as $e){
				$times.="<div>".date("l F j Y, h:i a",$e["timestamp"])."</div>";
			}
			$t.="<div id=\"s_".$service_id."\"class=\"service_record\">
					<div class='service_name'>".$service->service_name."</div>
					$service_title
					<div class='service_times'>$times</div>
				</div>
			";
		}
	} else {
		$t="There are no upcoming services scheduled";
	}
	$t.="
			<script type=\"text/javascript\">
				$('.service_record').click(function(){
					window.location=('?service_id='+$(this).attr('id').substr(2)+'&action=operate');
				});
			</script>
	";
	echo $t;	
} elseif ($_GET["action"]=="get_operator_name"){
	if ($_GET["person_id"]>0){
		$t=$a->personal_records->get_name_first_last($_GET["person_id"]);
	} else {
		$t="<span style='color:red;'>N/A - feed is orphaned</span>";
	}
	echo $t;
} elseif ($_GET["action"]=="get_autoconnect_button"){
	$t="<div class='feed_desc' id='f_auto'>
			<div class='feed_title'>Auto-mode</div>
			<div class='feed_operator'>Standby and connect automatically to first available feed</div>
		</div>
		<script>
			$('#f_auto').click(function(){
				window.location='?action=display&feed=auto';
			});
		</script>
	";
	echo $t;
} elseif ($_GET["action"]=="get_service_order"){
	$service_id=$_GET["service_id"];
	$elements=$eh->get_service_elements_for_ul($service_id,true);
	if (empty($elements)){
		$elements="<li id='placeholder' class=\"ui-state-default\">Service plan is empty!</li>";
	}
	$element_ids=csl_create_from_array($eh->church_services->get_service_element_ids($service_id));
	$t="
		<div id='service_order'>
			<ul id=\"sortable\">
				$elements
			</ul>
		</div>
	
		<script type='text/javascript'>
		
		service_elements=[$element_ids];
		
		$( 'ul, li' ).disableSelection();
		$('.actual_element').click(function(e){
			if (ui_ready){
				var service_element_id=$(this).attr('id').substr(1);
				show_slide_thumbs(service_element_id,true);
			}
		});
		</script>
	";
	echo $t;
	$eh->set_sync_mark($_GET["service_id"]);
} elseif ($_GET["action"]=="check_for_service_plan_update"){
	$elapsed=$eh->check_sync_mark($_GET["service_id"],"service_order",true);
	if ($elapsed>=CW_SERVICE_PLANNING_SYNC_DISTANCE){
      //no response indicates no reload necessary    
      echo $elapsed;
 	} else {
 		echo "RELOAD";    
 	} 
} elseif ($_GET["action"]=="load_slides"){
	$service=new cw_Church_service($eh, $_GET["service_id"]);
	$slides=$service->get_slides_assoc($_GET["service_element_id"],false,true,true);
	//var_dump($slides);
	$t="";
    foreach ($slides as $slide){
    	if (sizeof($slide)==0){
    		$t.="<div class='slidethumb'>
    				<div class='slide_fragment'>(blank slide)</div>
    				<br style='clear:both;'>
    			 </div>";
    	} else {
    		$t.="<div class='slidethumb'>";
    		foreach ($slide as $o){
    		if ($o['type']=='credits'){
    				//$t.="(C)";
    			} elseif ($o['type']=='source_info'){
    				//$t.="(S)";
    			} elseif ($o['type']=='lyrics'){
    				$o['content']=replace_linebreak($o['content']," <span class='gray'>/</span> ");
    				$fragment_div="";
    				if ($o['fragment']!=""){
    					$fragment_div="<div class='slide_fragment'>".$o['fragment']."</div>";
    				}
    				$t.="$fragment_div".$o['content']."<br style='clear:both;'>";
    			}
    		
    		}
    		$t.="</div>";
    	}
	}
	$t.="<script>
			current_service_element=".$_GET["service_element_id"].";
			
			slides['$'+current_service_element] = ".json_encode($slides).";
					
			console.log(slides);

			if (pgm_service_element==current_service_element){
					nav_to_slide(current_slide,true);
			}
			
			$('.slidethumb').click(function(){
					nav_to_slide($(this).index());
					
			});
		</script>";
	echo $t;	
} elseif ($_GET["action"]=="load_all_slide_data"){
	//Return entire slides object
	// slides['$service_element_id'].slides[] has slide data
	// slides['$service_element_id'].files[] has attached file info
	$t="";
	$service=new cw_Church_service($eh, $_GET["service_id"]);
	foreach ($service->elements as $v){
		if ($service->element_is_actual_element($v["element_nr"])){
			$slides=$service->get_slides_assoc($v["id"],false,true,true);
			$files=$service->get_files($v["id"]);
			$t.="	slides['$".$v["id"]."']={};
				slides['$".$v["id"]."'].files=".json_encode($files).";
				slides['$".$v["id"]."'].slides=".json_encode($slides).";";
		} else {
			//$t.="console.log('non-actual element');";
		}
	}
	echo $t;
} elseif ($_GET["action"]=="get_next_service_element_with_lyrics"){	
	$service=new cw_Church_service($eh, $_GET["service_id"]);
	if ($r=$service->get_next_service_element_with_lyrics($_GET["service_element_id"])){
		echo $r;
	}
} elseif ($_GET["action"]=="get_nav_panel"){
	$service=new cw_Church_service($eh, $_GET["service_id"]);
	$service_name=$service->get_info_string();
	$service_title=$service->title;
	$t="";
	//$t.="<div class='nav_panel_item' id='np_service_bg'>Service background</div>";
	$t.="<div class='nav_panel_item' id='np_blackout'><span class='to_underline'>B</span>lackout</div>";
	$t.="<div class='nav_panel_item' id='np_welcome'><span class='to_underline'>W</span>elcome slide<br><div>$service_name, $service_title</div></div>";
	$t.="<div class='nav_panel_item' id='np_im'><span class='to_underline'>I</span>nstant message</div>";
	$t.="<script>
			
			$('.nav_panel_item').disableSelection();
			
			$('.nav_panel_item').hover(function(e){
				$(this).addClass('mark_navitem_preactive');			
			},
			function(){
				$(this).removeClass('mark_navitem_preactive');
			});
			
			
			slides.$0={};
			slides.$0.slides=
					[
						[
							{'type':'lyrics','content':''},
							{'type':'background_image','src':'".CW_ROOT_WEB."img/black.gif'}
						],
						[
							{'type':'cover_text','service_name':".json_encode($service_name).",'service_title':".json_encode($service_title)."},
							{'type':'lyrics','content':''},
							{'type':'background_image','src':'".CW_ROOT_WEB."img/church_logo/full_church_logo_43.gif'}
						]
																		
					];
			
			slides.$0.files={};
									
			$('#np_blackout').click(function(e){
				nav_panel_click(e.shiftKey,0);
			});
									
									
			$('#np_welcome').click(function(e){
				$.get('".CW_ROOT_WEB."ajax/ajax_projector_feeds.php?action=get_countdown_duration&service_id=".$_GET["service_id"]."',function(res){
					var slide=slides.$0.slides[0];
					if (res>0){
						slide.push({'type':'countdown','duration':res});
					}
					nav_panel_click(e.shiftKey,1);
				});
			});
									

			$('#np_im').click(function(e){
				var msg;
				if  (msg=prompt('Please type the message you wish to display',last_instant_message)){				
					var slide=
						[
							{'type':'instant_message','content':msg},
							{'type':'background_image','src':'".CW_ROOT_WEB."img/black.gif'}
						];
					slides.$0.slides[2]=slide;
					nav_panel_click(e.shiftKey,2);				
					last_instant_message=msg;
				}						
			});
								
		 </script>";
	echo $t;
} elseif ($_GET["action"]=="get_countdown_duration"){
	$service=new cw_Church_service($eh, $_GET["service_id"]);
	$countdown=0;
	foreach($service->event_records as $e){
		if ($e["timestamp"]>time()){
			$countdown=$e["timestamp"]-time();
			break;
		}
	}
	if ($countdown>3600){
		$countdown=0; //only show countdown the last hour before the service
	}
	echo $countdown;
} elseif ($_GET["action"]=="get_projector_image"){
	//$_GET["lib_id"]
	$mb=new cw_Mediabase($a);
	$img=$mb->get_projector_image($_GET["lib_id"]);
	if ($img!=null){
		header('Content-Type: image/jpeg');
		imagejpeg($img);
	}
} else {
	echo "INVALID REQUEST";
}

$p->nodisplay=true;

?>
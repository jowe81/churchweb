<?php

	require_once "../lib/framework.php";

 	$p->stylesheet(CW_ROOT_WEB."css/mediabase.css");
  
	$cwidth="560px";
	$add_interface="";
 	if ($a->csps>=CW_E){
 		$add_interface="
 			<div class=\"subcontainer\" style=\"width:300px;border-left:1px solid gray;padding-left:10px;\">
 				<div>
 					Add a new resource
					<div class='small_note'>Click 'browse' to select a file to upload</div>
 					<form id='upload_form' target='invisible_iframe' action=\"".CW_AJAX."ajax_mediabase.php?action=upload_resource\" method=\"POST\" enctype=\"multipart/form-data\">
						<input id='filesel' name='filesel' type='file' />
					</form>
					<iframe name='invisible_iframe' id='invisible_iframe' style='display:none;'></iframe>
 				</div>
 			</div>
 		";
 		$cwidth="890px";
 	}

 	$upref = new cw_User_preferences($d,$a->cuid);
 	(strtolower($upref->read_pref($a->csid, "filter_images"))=="true") ? $filter_images_checked="CHECKED" : $filter_images_checked="";
 	(strtolower($upref->read_pref($a->csid, "filter_videos")=="true")) ? $filter_videos_checked="CHECKED" : $filter_videos_checked="";
 	(strtolower($upref->read_pref($a->csid, "filter_recent")=="true")) ? $filter_recent_checked="CHECKED" : $filter_recent_checked="";

 	$target_type="";
 	$target_id="";
 	if (($_GET["action"]=="select_bg_media_for_church_service") && ($_GET["service_id"]>0)){
 		$target_type="service";
 		$target_id=$_GET["service_id"];
 	} elseif (($_GET["action"]=="select_bg_media_for_service_element") && ($_GET["element_id"]>0)){
 		$target_type="service_element";
 		$target_id=$_GET["element_id"]; 			
 	} elseif (($_GET["action"]=="select_bg_media_for_music_piece") && ($_GET["music_piece"]>0)){
 		$target_type="music_piece";
 		$target_id=$_GET["music_piece"]; 			
 	}
 	$t="
 		<div id=\"mb_top_container\">
 			<div style='width:$cwidth;margin-left:auto;margin-right:auto;'>
				<div class=\"subcontainer\" style=\"width:550px\">
					Select one of <span id='media_library_size'></span> resources
	 				<div style='width:530px;'>
	 					<div style='float:left;'>
							<div class='small_note'>Type a tagname or title to narrow results</div>
							<input id=\"search\" type=\"text\">
	 					</div>
	 					<div style='float:left;padding-left:20px;'>
							<div class='small_note'>Additional filters:</div>
							<input class='filter' id=\"filter_images\" type=\"checkbox\" $filter_images_checked> images</input>
			 				<input class='filter' id=\"filter_videos\" type=\"checkbox\" $filter_videos_checked> videos</input>
			 				<input class='filter' id=\"filter_recent\" type=\"checkbox\" $filter_recent_checked> new since ".date("M j",time()-CW_MEDIABASE_RECENTLY)."</input>
			 			</div>
	 				</div>
	 			</div>
	 			$add_interface
	 		</div>
 		</div>
		<div id=\"mediathumbs\">
		</div>
 	";

 	$s="
 			
 		$('#filesel').change(function(){
 			$('#upload_form').submit();
 			show_please_wait('Please wait while this resource uploads...');
		});
 			
 		$('#invisible_iframe').load(function(e){
 			//Have to check if the event was triggered because of an upload, or initially after page generation:
 			if (e.target.contentDocument.URL.lastIndexOf('ajax_mediabase.php?action=upload_resource')>0){
 				//Yes, file upload took place
 				hide_please_wait();
 				refresh_thumbs();
 				refresh_mediabase_size();
 				var lib_id=$(this).contents().find('#lib_id').html();
 				if (lib_id>0){
 					init_modal('".CW_AJAX."ajax_mediabase.php?action=get_edit_resource_interface&lib_id='+lib_id);
 				}
 				$('#filesel').val('');
			}
		});
 			 			
 							
          $('#search').autocomplete({
            source:'".CW_AJAX."ajax_mediabase.php?action=acomp_media_tags',
            minLength:1,
            select: function(event,ui){
			},
            autoFocus:true
          }).keypress(function(e){
            if (e.keyCode==13){
            	refresh_thumbs(); 
            	$(this).select();    
            }
            if (e.keyCode==27){
            	$(this).val('');
            }
          });
 							
            		
        function adjust_dependencies(filter_name){
            if ($('#'+filter_name).prop('checked')){            	
	            if (filter_name=='filter_videos'){
            		$('#filter_images').prop('checked',false);
	            }
	            if (filter_name=='filter_images'){
            		$('#filter_videos').prop('checked',false);
	            }            		
            }
        }

		function save_filters(){
            $.post('".CW_AJAX."ajax_mediabase.php?action=save_filter_presets',{ 
            		filter_images:$('#filter_images').prop('checked'),
            		filter_videos:$('#filter_videos').prop('checked'),
            		filter_recent:$('#filter_recent').prop('checked'),
			});
		}            		
            		
        $('.filter').click(function(){
            adjust_dependencies($(this).attr('id'));
            refresh_thumbs();
            save_filters();
		});
            		
 		refresh_thumbs(); 	
        refresh_mediabase_size();				
 	";
 	
 	//Global
 	$js="
 			
 			var last_keyword='';
 			
	 		function refresh_thumbs(){
	 			$('#mediathumbs').load('".CW_AJAX."ajax_mediabase.php?action=get_thumbs&target_type=$target_type&target_id=$target_id&searchterm='+encodeURIComponent($('#search').val())+'&recent='+$('#filter_recent').attr('checked')+'&images='+$('#filter_images').attr('checked')+'&videos='+$('#filter_videos').attr('checked'));
			}	 
	 					
	 		function refresh_mediabase_size(){
	 			$('#media_library_size').load('".CW_AJAX."ajax_mediabase.php?action=get_media_library_size'); 					
	 		}

	    	function show_please_wait(message){
	      		$('body').append('<div id=\"please_wait\">' + message + '</div>');
	    	}
	      
	    	function hide_please_wait(){
	      		$('#please_wait').remove();    
	    	}

		    //Esc clears filter or leaves modal
		    $(document).keyup(function(e){
		      //Only work with the search focus if the modal window is not visible
		      if (!($('#modal').is(':visible'))){
		        if (e.keyCode==27) {
		          if (!$('#search').is(':focus')){
		            $('#search').focus().select();
		          }
		        }
		      } else {
		        if (e.keyCode==27) {
		          //Esc was hit with modal visible - leave modal
		          e.preventDefault();
		          close_modal();
		        }
		      }
		    });
		                
		    //Load and open the modal window
		    function init_modal(ajax_url,element_to_focus,smallsize){
		      $('#main_content').fadeTo(500,0.3);
		      $('#modal').load(ajax_url,function(){
		        $('#'+element_to_focus).focus();
		      });
		      if (smallsize){
		        $('#modal').css('width','300px');
		        $('#modal').css('height','200px');
		        $('#modal').css('left','520px');
		        $('#modal').css('top','165px');
		      } else {
		        $('#modal').css('width','1200px');
		        $('#modal').css('height','585px');
		        $('#modal').css('left','20px');
		        $('#modal').css('top','45px');
		      }
		      $('#modal').fadeIn(200);                        
		    }    
		    
		    function close_modal(){
		      $('#search').focus().select(); //Put focus to an element outside the modal to make sure blur gets triggered for the field that has focus in the modal
		      $('#main_content').fadeTo(500,1);
		      $('#modal').hide(200);
	 		  refresh_thumbs();
		    }    
	 					
 					
 	";
 	$p->p($t);
 	$p->jquery($s);
 	$p->js($js);
 	
	$mb=new cw_Mediabase($a);
	//$mb->rebuild_all_image_versions();
	//var_dump($mb->clear_media_library());
	//$mb->recreate_tables();
	
	$mdb=new cw_Music_db($a);
	$cs=new cw_Church_services($d);
	//var_dump($cs->assign_background_to_service_element(100, 4, $mb));
	//$cs->unassign_background_from_service_element(100);
	
	//var_dump($mdb->assign_background_to_music_piece(100, 4, $mb));
	//var_dump($mdb->unassign_background_from_music_piece(100));
	//var_dump($mb->resource_in_use(4));
	
?>
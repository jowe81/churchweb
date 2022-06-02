<?php

class cw_Display_mediabase {
  
    private $mb,$d,$auth,$ajax_scriptname; //Passed in
    
    function __construct(cw_Mediabase $mb,$ajax_scriptname="ajax_mediabase.php"){
		$this->mb=$mb;
		$this->auth=$mb->a;
		$this->d=$mb->d;
		$this->ajax_scriptname=$ajax_scriptname; //Where to send ajax requests?
    }
    
    
    
    
    //$r is array with media_library records
    //$target_type can be service, song, arrangement, service_element
	function display_thumbs($records,$target_type="",$target_id=0){
		$t="";
		$s="";		
		$w=CW_MEDIABASE_THUMB_WIDTH;
		$h=floor($w/(CW_PROJECTOR_X/CW_PROJECTOR_Y));
		$return_url="";
		if ($target_type=="service"){
			$return_url="service_planning.php?action=plan_service&service_id=$target_id&show_projection_settings_dialogue=true";
			$eh=new cw_Event_handling($this->auth);
			$service=new cw_Church_service($eh,$target_id);
			$thumbinfo="<div class='media_select_note'>Click to select this as background for:<br>".$service->service_name.", ".$service->get_service_times_string(true)."</div>";
		} elseif ($target_type=="service_element"){
			$eh=new cw_Event_handling($this->auth);
			$service_id=$eh->church_services->get_service_id_for_element($target_id);
			$service=new cw_Church_service($eh,$service_id);
			$element=$eh->church_services->get_service_element_record($target_id);				
			$return_url="service_planning.php?action=plan_service&service_id=$service_id&show_edit_service_element_dialogue=true&service_element_id=$target_id";
			$thumbinfo="<div class='media_select_note'>Click to select this as background for the ".$element["label"]." in the ".$service->service_name.", ".$service->get_service_times_string(true)."</div>";				
		} elseif ($target_type=="music_piece"){
			$mdb=new cw_Music_db($this->auth);
			$music_piece_title=$mdb->get_music_piece_title_info($target_id);
			$return_url="music_db.php?action=assign_bg_media&music_piece=$target_id";
			$thumbinfo="<div class='media_select_note'>Click to select this as background for $music_piece_title</div>";				
		}
		foreach($records as $r){
			$src="";
			if ($r["thumb_file_id"]>0){
				$src=CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b=".$r["thumb_file_id"];			
			}
			if (!empty($src)){
				if (($target_type=="service") || ($target_type=="service_element") || ($target_type=="music_piece") || ($target_type=="arrangement")){
					
				} else {
					$thumbinfo="
							<div class='media_type'>".$r["type"]." (".$r["ext"]."), ID:".$r["id"]."</div>
							<div class='media_title'> ".$r["title"]."</div>
							<div class='media_info'>
								Added ".date("M j, Y",$r["added_at"])." by<br>".$this->mb->a->personal_records->get_name_first_last($r["added_by"])."
							</div>							
					";					
				}
				$t.="<div class='mb_thumb' id='_lib_id_".$r["id"]."'  style='width:{$w}px;height:{$h}px;'>
						<div><img src=\"$src\"></div>
						<div class='info'>
							$thumbinfo
						</div>					
					</div>";			
			} else {
				$t.="<div class='mb_thumb' id='_lib_id_".$r["id"]."' style='width:{$w}px;height:{$h}px;'>
					<span class='small_note'>An error occurred while trying to process this resource - you should <a href='' id='_".$r["id"]."' class='erroneous'>delete it</a></span>
					</div>";
			}
		}
		$s="
			<script>
				function delete_resource(lib_id){
					$.get('".CW_AJAX.$this->ajax_scriptname."?action=delete_resource&lib_id='+lib_id,function(res){
						if (res!=''){
							alert(res);
						}
						refresh_thumbs();
						refresh_mediabase_size();
					});								
				}
							
				$('.erroneous').click(function(e){
					e.preventDefault();
					e.stopPropagation();
					delete_resource($(this).attr('id').substr(1));
				});
				
				$('.mb_thumb').click(function(e){
					var lib_id=$(this).attr('id').substr(8);
					var return_url='$return_url';
					if (e.shiftKey){
						if (".$this->auth->csps.">=".CW_E."){
							if (confirm('Do you really want to delete this resource?')){
								delete_resource(lib_id);
							}																
						} else {
							alert('You have insufficient privileges to delete this image');
						}						
					} else {
						if (return_url==''){
							init_modal('".CW_AJAX."ajax_mediabase.php?action=get_edit_resource_interface&lib_id='+lib_id);
						} else {
							//return to the service that wanted us to choose a media
							return_url=return_url+'&lib_id='+lib_id;
							//alert(return_url);
							window.location=return_url;
						}								
					}
				});
			</script>
		";				
		return "<div id='mediathumbs'>".$t.$s."</div><script>$('#mediathumbs').disableSelection();</script>";
	}
	
	
	
	function get_edit_resource_interface($lib_id){
		if ($r=$this->mb->get_media_library_record($lib_id)){
			$restype=$r["type"];
			$resource_title=$r["title"];
			$projector_preview_src="";
			$orig_preview_src="";
			$procrop_src="";
			$orig_src="";
			$copyright_holders=$this->mb->get_media_copyright_holder_string_for_resource($lib_id);
			$authors=$this->mb->get_media_author_string_for_resource($lib_id);
			$is_cropped=($r["aspect_x"]/$r["aspect_y"])!=(CW_PROJECTOR_X/CW_PROJECTOR_Y);
			if ($is_cropped){
				$crop_notice="<div class='aspect_warning'>Warning: projector version was cropped from source to fit screen aspect</div>";
			} else {
				$crop_notice="<div class='image_ok'>Notice: source aspect is compatible with screen aspect</div>";				
			}
			$is_upscaled=(($r["width"]<CW_PROJECTOR_X) || ($r["height"]<CW_PROJECTOR_Y));
			if ($is_upscaled){
				$upscale_notice="<div class='dimensions_warning'>Warning: projector version was upscaled because of insufficient source resolution</div>";				
			} else {
				$upscale_notice="<div class='image_ok'>Notice: source resolution is sufficient for quality presentation</div>";
			}
			if ($r["projector_file_id"]>0){
				//$preview_src=CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b=".$r["projector_file_id"];
				$projector_preview_src=CW_AJAX."ajax_mediabase.php?action=get_downscaled_projector_preview&lib_id=$lib_id&max_width=495&max_height=490";
				$orig_preview_src=CW_AJAX."ajax_mediabase.php?action=get_downscaled_original_preview&lib_id=$lib_id&max_height=300&max_width=300";
				$procrop_src=CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b=".$r["projector_file_id"];
				$orig_src=CW_DOWNLOAD_HANDLER."?a=".sha1($this->auth->csid)."&b=".$r["file_id"];
			}
			$info="<table>
						<tr><td>Media type:</td><td>".$r["type"]." (".$r["ext"].")</td></tr>
						<tr><td>Dimensions:</td><td>".$r["width"]."*".$r["height"]."</td></tr>
						<tr><td>Aspect:</td><td>".$r["aspect_x"].":".$r["aspect_y"]."</td></tr>
						<tr><td>Name:</td><td>".$r["orig_name"]."</td></tr>								
						<tr><td>Added at:</td><td>".date("M j, Y",$r["added_at"])."</td></tr>								
						<tr><td>Added by:</td><td>".$this->mb->a->personal_records->get_name_first_last($r["added_by"])."</td></tr>								
						<tr><td>Download:</td><td><a href=\"$orig_src\">original</a> | <a href=\"$procrop_src\">projector crop</a></td></tr>								
					</table>";
			$t="
				<div class='modal_head'>Edit $restype metadata (#".$lib_id.")</div>
				<div class='modal_body' style='padding:0px;'>

	            <div style='width:100%;height:515px;border-top:1px solid #DDD;border-bottom:1px solid #DDD;padding:0px;'>
	              <div style='width:330px;height:505px;border-right:1px solid #DDD;float:left;'>
	                <div id='source_container'>                    
	                  <h4>Identification</h4>        
	                  <div style='padding-top:5px;height:20px;'>Title of $restype:</div>
	                  <div><input id='resource_title' type='text' value=\"$resource_title\" /></div>
	                </div>          
	                <div id='media_tags_container'>                    
	                  <h4>Tags</h4>        
	                  <div style='padding-top:5px;height:20px;'>Type/select new media tag:</div>
	                  <div><input id='new_media_tag' type='text' /></div>
	                  <div style='padding-top:5px;height:20px;'>Tags on this $restype:</div>
	                  <div><select id='media_tags' size='5'></select></div>
	                  <div>
	                    <input id='delete_selected_media_tag' type='button' class='button' style='width:185px;float:left' value='delete selected tag'/>
	                    <input id='clear_all_media_tags' type='button' class='button' style='width:110px;float:right;' value='clear all tags'/>
	                  </div>
	                </div>          
	                <div id='credits_container' style='padding-top:40px;'>                    
	                  <h4>Credits</h4>
			          <div>Author: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
					  <div><input id='author' type='text' style='' value=\"$authors\"/></div>
			          <div>Copyright holder: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
          			  <div><input id='copyright' type='text' value=\"$copyright_holders\"/></div>					  
	                </div>          
	               </div>
	              <div style='width:320px;height:505px;border-right:1px solid #DDD;float:left;'>
	                <div id='info_container'>                    
	                  <h4>Original File Details</h4>
	                  <div>
	                  	$info
	                  </div>
	                </div>          
	                <div id='orig_preview_container'>                    
	                  <h4>Original preview</h4>
	                  <div>
	                  	<img src=\"$orig_preview_src\" alt='loading original preview...'>
	                  </div>
	                </div>          
	                </div>
              	  <div style='width:515px;height:300px;float:left;'>
	                <div id='projector_preview_container'>                    
	                  <h4>Projector preview</h4>
	                  <div>
	                  	$crop_notice
	                  	$upscale_notice	                  
	                  	<img src=\"$projector_preview_src\" alt='loading projector preview...' style='margin-top:10px;'>
	                  </div>
	                </div>          
              	  </div>
              	  
				</div>
   	              <div style='width:1185px;height:40px;'>
		            <input id='done_edit_resource' type='button' class='button' style='width:110px;float:right;' value='done'/>            
		          </div> 
				
				
				
				
			";
			$s="
				<script>
					
				  //init
		          reload_media_tags();
				  $('#resource_title').focus().select();

				  $('#done_edit_resource').click(function(){
					 close_modal();
				  });
					

				  $('#resource_title').blur(function(){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=save_resource_title&lib_id=$lib_id', {'title':$(this).val()},function(res){
		            	if (res!='OK'){
		            		alert(res);
		            	}
					});
				  });

				  
		          /////////////////////////// media tags /////////////////////
		          
		          function reload_media_tags(){
		            $('#media_tags').load('".CW_AJAX.$this->ajax_scriptname."?action=get_media_tags&lib_id=$lib_id'); 
		          }
		          
		          
		          $('#delete_selected_media_tag').click(function(){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=delete_media_tag',{ media_tag_id:$('#media_tags').val(),lib_id:$lib_id },function(rtn){
		              if (rtn!='OK'){
		                alert(rtn);
		              }
		              reload_media_tags();                           
		            });                   
		          });          
		  
		          $('#clear_all_media_tags').click(function(){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=delete_all_media_tags',{ lib_id:$lib_id },function(rtn){
		              if (rtn!='OK'){
		                alert(rtn);
		              }
		              reload_media_tags();                           
		            });                                              
		          });          
		  
		          function assign_existing_media_tag(sel_id){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=assign_existing_media_tag',{ lib_id:$lib_id,media_tag_id:sel_id },function(rtn){
		              if (rtn!='OK'){
		                alert(rtn);
		              }
		              reload_media_tags();                           
		              $('#new_media_tag').val('');
		            });
		          }
		  
		          $('#new_media_tag').autocomplete({
		            source:'".CW_AJAX.$this->ajax_scriptname."?action=acomp_media_tags',
		            minLength:1,
		            select: function(event,ui){
		              $('#new_media_tag').data('auto',true); //Set flag that an selection has been made through the autocomp (don't interpret enter as search command)
		              assign_existing_media_tag(ui.item.id);
		            },
		            autoFocus:true            
		          }).keypress(function(e){
		            if (e.keyCode==13){
		              if ($('#new_media_tag').data('auto')!=true){
		                //If auto complete fails
		                if ($(this).val()!=''){
		                  $.post('".CW_AJAX.$this->ajax_scriptname."?action=add_new_media_tag',{ lib_id:$lib_id,val:$(this).val() },function(rtn){
		                    if (rtn!='OK'){
		                      alert(rtn);
		                    } else {
		                      $('#new_media_tag').val('');
		                    }
		                    reload_media_tags();                           
		                  });
		                }
		              }
		            } else {
		              $('#new_media_tag').data('auto',false);   
		            }
		          });
          
		          ////////////////////// Authors /////////////////
		          
	      		  function split( val ) {
	      			return val.split( /,\s*/ );
	      		  }
	      		  function extractLast( term ) {
	      			return split( term ).pop();
	      		  }

		          function save_media_authors_to_media_library_record(val){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=save_media_authors_to_media_library_record',{ lib_id:$lib_id, value:val },function(rtn){
		              if (rtn!='OK'){
		                alert(rtn);
		              }
		            });                        
		          }	      		  

		          $('#author')
        			// don't navigate away from the field on tab when selecting an item
        			.bind( 'keydown', function( event ) {
        				if ( event.keyCode === $.ui.keyCode.TAB &&
        						$( this ).data( 'autocomplete' ).menu.active ) {
        					event.preventDefault();
        				}
        			})
        			.autocomplete({
        				source: function( request, response ) {
        					$.getJSON( '".CW_AJAX.$this->ajax_scriptname."?action=acomp_author', {
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
        				focus: function() {
        					// prevent value inserted on focus
        					return false;
        				},
        				select: function( event, ui ) {
        					var terms = split( this.value );
        					// remove the current input
        					terms.pop();
        					// add the selected item
        					terms.push( ui.item.value );
        					// add placeholder to get the comma-and-space at the end
        					terms.push( '' );
        					this.value = terms.join( ', ' );
        					return false;
        				},
                	autoFocus:true
        		  })
	              .blur(function(){
	                //value has the string from the multi-autocomplete
	                save_media_authors_to_media_library_record($(this).val());   
	              });
	      		  
	      		  
	      		  
		          function save_copyright_holders(val){
		            $.post('".CW_AJAX.$this->ajax_scriptname."?action=save_copyright_holders',{ lib_id:$lib_id, value:val },function(rtn){
		              if (rtn!='OK'){
		                alert(rtn);
		              }
		            });                                  
		          }      
		          
		          //Copyright holders autocomplete
		          $('#copyright')
		      			// don't navigate away from the field on tab when selecting an item
		      			.bind( 'keydown', function( event ) {
		      				if ( event.keyCode === $.ui.keyCode.TAB &&
		      						$( this ).data( 'autocomplete' ).menu.active ) {
		      					event.preventDefault();
		      				}
		      			})
		      			.autocomplete({
		      				source: function( request, response ) {
		      					$.getJSON( '".CW_AJAX.$this->ajax_scriptname."?action=acomp_copyright_holders', {
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
		      				focus: function() {
		      					// prevent value inserted on focus
		      					return false;
		      				},
		      				select: function( event, ui ) {
		      					var terms = split( this.value );
		      					// remove the current input
		      					terms.pop();
		      					// add the selected item
		      					terms.push( ui.item.value );
		      					// add placeholder to get the comma-and-space at the end
		      					terms.push( '' );
		      					this.value = terms.join( ', ' );
		      					return false;
		      				},
		              autoFocus:true
		      			})
		            .blur(function(){
		              save_copyright_holders($(this).val());
		            });
				  
		         </script>       					
			";
				
		}
		
		return $t.$s;
	}
    
	function get_media_tags_for_select($lib_id){
		$r=$this->mb->get_media_tags_for_resource($lib_id);
		if ($r!==false){
			$t="";
			foreach ($r as $v){
				$t.="<option value='".$v["id"]."'>".$v["title"]."</option>";
			}
			return $t;
		}
		return false;
	}
	
}

?>
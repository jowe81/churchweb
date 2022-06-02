<?php

  class cw_Display_music_db {
  
    public $ajax;
    public $mdb;
    private $d; //Database access
    
    function __construct(cw_Music_db $mdb,$ajax='ajax_music_db.php'){
      $this->d=$mdb->d; //Database access
      $this->mdb=$mdb;
      $this->ajax=CW_AJAX.$ajax;
    }

    function display_edit_music_piece_interface($id){
      //Left column (music_piece details)
    
      $piece=$this->mdb->get_music_piece_record($id);
      $composers=$this->mdb->get_writer_string_for_music_piece($id,CW_MUSICDB_WRITER_CAPACITY_COMPOSER);
      $lyricists=$this->mdb->get_writer_string_for_music_piece($id,CW_MUSICDB_WRITER_CAPACITY_LYRICIST);
      $translators=$this->mdb->get_writer_string_for_music_piece($id,CW_MUSICDB_WRITER_CAPACITY_TRANSLATOR);
      $copyright_holders=$this->mdb->get_copyright_holder_string_for_music_piece($id);
      $t="
        <div id='title_header'></div>
        <div id='music_piece_details_container'>
          <h4>Details</h4>
          <div style='padding-top:5px;'>Title:</div>
          <div><input id='title' class='music_piece_record' type='text' value=\"".$piece["title"]."\"/></div>
          <div>Alternative title:</div>
          <div><input id='alt_title' class='music_piece_record' type='text' value=\"".$piece["alt_title"]."\"/></div>
          <div>Original title:</div>
          <div><input id='orig_title' class='music_piece_record' type='text' value=\"".$piece["orig_title"]."\"/></div>
          <div style='width:150px;float:left;'>Year of release:</div><div>Default language:</div>
          <div style='width:150px;float:left;'><input style='width:120px;' id='year_of_release' class='music_piece_record' type='text' value=\"".$piece["year_of_release"]."\"/></div>
          <div><select style='width:135px;height:27px;float:right;margin-right:8px;padding:0px;' id='default_language' class='music_piece_record'>".$this->mdb->get_languages_for_select($piece["default_language"],false)."</select></div>
          <div>Composer: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
          <div><input id='".CW_MUSICDB_WRITER_CAPACITY_COMPOSER."' class='writers' type='text' value=\"$composers\"/></div>
          <div>Lyricist: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
          <div><input id='".CW_MUSICDB_WRITER_CAPACITY_LYRICIST."' class='writers' type='text' value=\"$lyricists\"/></div>
          <div>Translator: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
          <div><input id='".CW_MUSICDB_WRITER_CAPACITY_TRANSLATOR."' class='writers' type='text' value=\"$translators\"/></div>
          <div>Copyright holder: <div style='float:right;padding:4px 9px 0px 0px;'class='small_note gray'>(comma-separate multiple)</div></div>
          <div><input id='copyright' type='text' value=\"$copyright_holders\"/></div>
        </div>
                
        ";
        
      $disable_form_fields="";
      if ($this->mdb->auth->csps<=CW_E){
        $disable_form_fields="
          $('#music_piece_details_container input').attr('disabled','disabled');
        ";
      }
      
      $s="
          $disable_form_fields
            
          load_title_header();
          
          function load_title_header(){
            $('#title_header').load('".$this->ajax."?action=get_title_header&music_piece_id=$id');
          }
                          
          function save_music_piece_record(fld,val){
            $.post('".$this->ajax."?action=save_music_piece_record',{ id:$id, field:fld, value:val },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              load_title_header();
            });            
          }

          //On blur save each part of the music_piece record
          $('.music_piece_record').blur(function(){
            save_music_piece_record($(this).attr('id'),$(this).val());
          });
              
              
          function save_writers_to_music_pieces_record(writer_cap,val){
            $.post('".$this->ajax."?action=save_writers_to_music_pieces_record',{ music_pieces_id:$id, writer_capacity:writer_cap, value:val },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              load_title_header();
            });                        
          }
                
          function save_copyright_holders(val){
            $.post('".$this->ajax."?action=save_copyright_holders',{ music_pieces_id:$id, value:val },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });                                  
          }      
              
          
          //The autocomplete fields for writers
      		function split( val ) {
      			return val.split( /,\s*/ );
      		}
      		function extractLast( term ) {
      			return split( term ).pop();
      		}
      
      		$( '.writers' ).each(function(){
            $(this)
        			// don't navigate away from the field on tab when selecting an item
        			.bind( 'keydown', function( event ) {
        				if ( event.keyCode === $.ui.keyCode.TAB &&
        						$( this ).data( 'autocomplete' ).menu.active ) {
        					event.preventDefault();
        				}
        			})
        			.autocomplete({
        				source: function( request, response ) {
        					$.getJSON( '".CW_AJAX."ajax_music_db.php?action=acomp_writers', {
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
                //ID has writer_capacity (composer, lyricist, translator), and value has the string from the multi-autocomplete
                save_writers_to_music_pieces_record($(this).attr('id'),$(this).val());   
              });
        	});

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
      					$.getJSON( '".CW_AJAX."ajax_music_db.php?action=acomp_copyright_holders', {
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
      ";
      
      //Second column (scripture references, themes)
      if ($this->mdb->auth->csps>=CW_E){
        $t.="
          <div id='scripture_refs_and_themes_container'>
            <div id='scripture_refs_container'>
              <h4>Scripture references</h4>        
              <div style='padding-top:5px;'>Type a reference to add:</div>
              <div><input id='new_scripture_ref' type='text' /></div>
              <div style='padding-top:5px;'>References in this piece:</div>
              <div><select id='scripture_refs' size='15'></select></div>
              <div><input id='delete_selected_ref' type='button' class='button' style='width:125px;float:left' value='delete selected'/></div>
              <div><input id='clear_all_refs' type='button' class='button' style='width:55px;float:right;' value='clear'/></div>
            </div>
            <div id='themes_container'>                    
              <h4>Themes</h4>        
              <div style='padding-top:5px;'>Type/select a theme to add:</div>
              <div><input id='new_theme' type='text' /></div>
              <div style='padding-top:5px;'>Themes in this piece:</div>
              <div><select id='themes' size='15'></select></div>
              <div><input id='delete_selected_theme' type='button' class='button' style='width:125px;float:left' value='delete selected'/></div>
              <div><input id='clear_all_themes' type='button' class='button' style='width:55px;float:right;' value='clear'/></div>
            </div>          
          </div>      
        ";      
      } else {
        //Viewer mode
        $t.="
          <div id='scripture_refs_and_themes_container'>
            <div id='scripture_refs_container'>
              <h4>Scripture references</h4>        
              <div style='padding-top:5px;'>References in this piece:</div>
              <div><select id='scripture_refs' size='15' style='height:170px;'></select></div>
            </div>
            <div id='themes_container'>                    
              <h4>Themes</h4>        
              <div style='padding-top:5px;'>Themes in this piece:</div>
              <div><select id='themes' size='15' style='height:191px;'></select></div>
            </div>          
          </div>      
        ";        
      }
      
      $s.="
        function reload_scripture_refs(){
          $('#scripture_refs').load('".$this->ajax."?action=get_scripture_refs&music_piece_id=$id'); 
        }
        
        reload_scripture_refs(); //init
        
        $('#delete_selected_ref').click(function(){
          $.post('".$this->ajax."?action=delete_scripture_ref',{ scripture_ref_id:$('#scripture_refs').val() },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_scripture_refs();                           
          });                   
        });          

        $('#clear_all_refs').click(function(){
          $.post('".$this->ajax."?action=delete_all_scripture_refs',{ music_piece_id:$id },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_scripture_refs();                           
          });                                              
        });          

        $('#new_scripture_ref').keypress(function(e){
          if (e.keyCode==13){
            $.post('".$this->ajax."?action=add_scripture_ref',{ music_piece_id:$id,val:$(this).val() },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              } else {
                $('#new_scripture_ref').val('');
              }
              reload_scripture_refs();                           
            });                                                        
          }
        });          
        
        //////////////////////////////////////
        
        function reload_themes(){
          $('#themes').load('".$this->ajax."?action=get_themes&music_piece_id=$id'); 
        }
        
        reload_themes(); //init
        
        $('#delete_selected_theme').click(function(){
          $.post('".$this->ajax."?action=delete_theme',{ theme_id:$('#themes').val(),music_piece_id:$id },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_themes();                           
          });                   
        });          

        $('#clear_all_themes').click(function(){
          $.post('".$this->ajax."?action=delete_all_themes',{ music_piece_id:$id },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_themes();                           
          });                                              
        });          

        function assign_existing_theme(sel_id){
          $.post('".$this->ajax."?action=assign_existing_theme',{ music_piece_id:$id,theme_id:sel_id },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            }
            reload_themes();                           
            $('#new_theme').val('');
          });
        }

        $('#new_theme').autocomplete({
          source:'".CW_AJAX."ajax_music_db.php?action=acomp_themes',
          minLength:1,
          select: function(event,ui){
            $('#new_theme').data('auto',true); //Set flag that an selection has been made through the autocomp (don't interpret enter as search command)
            assign_existing_theme(ui.item.id);
          },
          autoFocus:true
        }).keypress(function(e){
          if (e.keyCode==13){
            if ($('#new_theme').data('auto')!=true){
              //If auto complete fails
              if ($(this).val()!=''){
                $.post('".$this->ajax."?action=add_new_theme',{ music_piece_id:$id,val:$(this).val() },function(rtn){
                  if (rtn!='OK'){
                    alert(rtn);
                  } else {
                    $('#new_theme').val('');
                  }
                  reload_themes();                           
                });
              }
            }
          } else {
            $('#new_theme').data('auto',false);   
          }
        });


      ";
      
      //Third column (lyrics fragments)
      if ($this->mdb->auth->csps>=CW_A){      
        $t.="
          <div id='lyrics_fragments_container'>
            <h4>Lyrics</h4>
            <div id='lyrics_fragments'>
              loading...
            </div>
            <h4 style='padding-top:5px;'>Add a lyrics fragment</h4>
            <div id='new_lyrics_fragment'>
              <textarea id='lyrics_fragment_textarea'></textarea>
            </div>
            <div>
              <select id='new_lyrics_fragment_type' style='width:110px;'><option>loading...</option></select>
              <select id='new_lyrics_language' style='width:110px;'>".$this->mdb->get_languages_for_select()."</select>
              <input type='button' id='add_fragment' class='button' style='float:right;margin-right:0px;' value='Add'/>
            </div>
          </div>              
        ";
      } else {
        //Viewer mode
        $t.="
          <div id='lyrics_fragments_container'>
            <h4>Lyrics</h4>
            <div id='lyrics_fragments' style='height:437px;'>
              loading...
            </div>
          </div>              
        ";      
      }
      
      $s.="
        function reload_lyrics_fragments(){
          $('#lyrics_fragments').load('".$this->ajax."?action=get_lyrics_fragments&music_piece=$id'); 
        }
      
        //Load lyrics fragments into display
        reload_lyrics_fragments();
        //Load the select box for the fragment types
        $('#new_lyrics_fragment_type').load('".$this->ajax."?action=get_lyrics_fragment_types_for_select');
        
        $('#add_fragment').click(function(){
          $.post('".$this->ajax."?action=add_lyrics_fragment&music_piece=$id',{ fragment_type_id:$('#new_lyrics_fragment_type').val(),content:$('#lyrics_fragment_textarea').val(),language:$('#new_lyrics_language').val() },function(rtn){
            if (rtn!='OK'){
              alert(rtn);
            } else {
              reload_lyrics_fragments();
              $('#lyrics_fragment_textarea').val('').focus();
            }
          });
        });
      ";
      
      //Fourth column (arrangements)
      
      if ($this->mdb->auth->csps>=CW_E){      
      	$edit_bg_image_links="";
      	if ($this->mdb->auth->have_mediabase_permission()){
			$edit_bg_image_links="<a href='mediabase.php?action=select_bg_media_for_music_piece&music_piece=$id'>select</a> | <a href='' id='remove_bg_image'>remove</a>";      		
      	}
      	
        $t.="
          <div id='arrangement_list_container'>
			<div id='add_arrangement_link_container'><a href='' id='add_arrangement'>add arrangement</a></div>        		
            <h4>Arrangements</h4>
            <div id='arrangements'>
              loading...
            </div>
        	<h4>Background visuals</h4>	
        	<div id='background'>
        		<div>        			
        			<span class='small_note'>
        				Still image: $edit_bg_image_links
        			</span>
        			<div id='bg_image_container'>
        				<img id='bg_image_preview'>
        			</div>
        		</div>
        		<div style='padding-left:2px;border-left:1px dotted gray;'>        			
        			<span class='small_note'>Video: select | remove</span>
        		</div>
        	</div>
          </div>      
        ";
      } else {
        //Viewer mode
        $t.="
          <div id='arrangement_list_container'>
            <h4>Arrangements</h4>
            <div id='arrangements' style='height:437px;'>
              loading...
            </div>
          </div>      
        ";      
      }
      
      $s.="
      		
    	function reload_bg_image(){
   			$.get('".CW_AJAX."ajax_music_db.php?action=get_bg_image_id&music_piece=$id',function(res){
   				if (res!='ERR'){
   					var bg_file_id=res;
		    		$('#bg_image_preview').remove();
		   			if (bg_file_id>0){    			
			   			var src='".CW_AJAX."ajax_music_db.php?action=get_downscaled_projector_preview&lib_id='+bg_file_id+'&max_width=180&max_height=96';
			   			$('#bg_image_container').html('<img id=\"bg_image_preview\" src=\"'+src+'\" alt=\"loading...\">');
			    	} else {
			    		$('#bg_image_container').html('<span class=\"small_note gray\">(none has been associated)</span>');
			   		}
			   	} else {
					$('#bg_image_container').html('<span class=\"small_note red\">an error occurred</span>');			   					
			   	}
		   	});
    	}      		
      		
        function reload_arrangements(show_retired){
          var show_retired_str;
          if (show_retired){
            show_retired_str='1';
          } else {
            show_retired_str='0';          
          }
          $('#arrangements').load('".$this->ajax."?action=get_arrangements&music_piece=$id&show_retired='+show_retired_str); 
        }
      
        function load_edit_arrangement_interface(arr_id,element_to_focus){
          init_modal('".$this->ajax."?action=get_edit_arrangement_interface&arrangement_id='+arr_id,element_to_focus);
        }
      
        //Init
        reload_arrangements(); 
		reload_bg_image();
          		
        
        $('#add_arrangement').click(function(e){
          //Create arr and get id
          e.preventDefault();
          $.post('".$this->ajax."?action=add_arrangement&music_piece_id=$id',{},function(rtn){
            if (rtn>0){
              //rtn has id of new arrangement record
              load_edit_arrangement_interface(rtn,'arr_title');              
            } else {
              alert(rtn);
            }
            reload_arrangements();                
          });          
        });

    	$('#remove_bg_image').click(function(e){
   			e.preventDefault();
   			$.get('".CW_AJAX."ajax_music_db.php?action=unassign_background_image_from_music_piece&music_piece=$id',function(res){
   				if (res==''){
    				$('#bg_image_container').html('<span class=\"red\">Background image removed</span>');
   				} else {
    				alert(res);
    			}
    		});
   		});
        
        
      ";
      
      $s="<script type=text/javascript>$s</script>";
      return $t.$s;    
    }
    
    //Get the <divs> to display the lyrics fragments in the music_piece view
    function get_lyrics_fragments($music_piece){                       
      $r=$this->mdb->get_lyrics_records_for_music_piece($music_piece);
      if ($r!==false){
        //Got lyrics
        $t="";
        //Get fragment type records for labels
        $types=$this->mdb->get_fragment_types_records();
        foreach ($r as $v){
          (($types[$v["fragment_type"]-1]["title"]=="verse") || ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";
          ($v["language"]!=$this->mdb->get_default_language_for_music_piece($music_piece)) ? $language=" (".$this->mdb->int_to_language($v["language"]).")" : $language="";
          $edit_links="";
          if ($this->mdb->auth->csps>=CW_A){
            $edit_links="
                  <a href='' class='edit_lyrics_link' id='del_".$v["id"]."'>edit</a> | 
                  <a href='' class='delete_lyrics_link' id='del_".$v["id"]."'>delete</a>
            ";
          }               
          $t.="
            <div class='lyrics_fragment_div'>
              <div class='lyrics_fragment_div_head'>
                <span style='font-style:italic'>".$types[$v["fragment_type"]-1]["title"]." ".$fno."</span>$language
                <div style='float:right'>
                  $edit_links
                </div>
              </div>
              <div class='lyrics_fragment_div_content'>
                <span class='data'>".double_br_to_hr(space_after_linebreak_to_nsbp(nl2br(htmlspecialchars($v["content"]))))."</span>
              </div>
            </div>"; 
        }
        if (empty($t)){
          $t.="
            <div class='lyrics_fragment_div expl' style='width:255px;border:none;'>
              <p>There are no lyrics saved with this music piece.</p>
              <p>
                To add lyrics, type in the box below, choose a fragment type and click &quot;add fragment&quot;.
              </p>
              <p>
                Note that you have to give the lyrics in order for each fragment type (i.e., you must enter verse 1 before verse 2, and chorus 1 before chorus 2 etc).
              </p>
              <p>
                Note also that the way you do or do not enter line-breaks affects the way the lyrics will be formatted for projection.
              </p>
              <p>
                If you want a single lyrics fragment (e.g. a long verse) to split in two slides, simply insert an empty line where you want the split.
              </p>
            </div>
          ";        
        }
        $s="
          <script type='text/javascript'>
            $('.delete_lyrics_link').click(function(e){
              e.preventDefault();              
              $.get('".$this->ajax."?action=delete_lyrics_fragment&lyrics_id='+$(this).attr('id'),function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                } else {
                  reload_lyrics_fragments();
                }
              });
            });
            
            $('.edit_lyrics_link').click(function(e){
              e.preventDefault();           
              init_modal('".$this->ajax."?action=get_edit_lyrics_fragment_interface&music_piece=$music_piece&lyrics_id='+$(this).attr('id'),'fragment',true);  
            });
            
          </script>
        ";               
        return $t.$s;
      }      
      return false;
    }

    //Get the <divs> to display the arrangement info in the music_piece view
    function get_arrangements($music_piece,$show_retired=false){
      $r=$this->mdb->get_arrangement_records_for_music_piece($music_piece);
      if ($r!==false){
        $t="";
        $ret_arrs=array(); //for retired arrangements 
        foreach ($r as $v){
          if (($v["active"]) || ($show_retired)){
            ($v["musical_key"]!=0) ? $mkey=$this->mdb->int_to_musical_key($v["musical_key"]) : $mkey="";
            //Keychanges
            $keychanges=$this->mdb->get_keychanges_for_arrangement($v["id"]);
            if (is_array($keychanges)){
              foreach ($keychanges as $kc){
                $mkey.=", ".$this->mdb->int_to_musical_key($kc["musical_key"]);
              }
            }
            $gtr_friendly=$this->mdb->int_to_guitar_friendliness($v["guitar_friendly"]);
            if (!empty($gtr_friendly)){
              $mkey.=" <span class='gray'>($gtr_friendly)</span>";
            }       
            //Writers (Arrangers)     
            $writers=$this->mdb->get_writers_for_arrangement_as_string($v["id"]);

            //Arrangement source            
            $title="";
            /*            
            !! TITLE field no longer used, but kept variable $title here, filled with source_title and source_index (see below) !!
             
            if (!empty($v["title"])){
              //Title exists
              $title="<span style='font-style:italic'>".$v["title"]."</span>&nbsp;";            
            }            
            */
            
            if ($v["source_id"]>0){
              $source_index="";
              if ($v["source_index"]!=""){
                $source_index="(#".$v["source_index"].")";
              }
              $title="<span style='font-style:italic'>".$this->mdb->get_source_title($v["source_id"])." $source_index</span>&nbsp;";            
            }            
            
            ////////////STYLE TAGS
            $st=$this->mdb->get_style_tags_for_arrangement($v["id"]);
            $style_tags="";
            if (is_array($st)){
              foreach ($st as $v3){
                $style_tags.=", ".$v3["title"];
              }
              if (!empty($style_tags)){
                $style_tags=substr($style_tags,2);
              } else {
                $style_tags="<span class='gray'>none</span>";
              }
            }
            //////////PARTS
            $parts_recs=$this->mdb->get_parts_for_arrangement($v["id"]);
            $parts_cnt=sizeof($parts_recs);
            //$p_recs now contains arrays with file_id and instruments
            $parts="";
            foreach ($parts_recs as $v4){
              //Download link
              $l=CW_DOWNLOAD_HANDLER."?a=".sha1($this->mdb->auth->csid)."&b=".$v4["file_id"];
              $parts.=", <a href=\"$l\">".$v4["instruments"]."</a>";
            }
            $parts_link="";
            if (!empty($parts)){
              $parts=substr($parts,2);
              ($parts_cnt==1) ? $w="part" : $w="parts";
              $parts_link="<a href='' id='arr_".$v["id"]."' class='arrlistlink_download_parts'>".$parts_cnt." $w</a>";
            } else {
              $parts="<span class='gray'>none</span>";
            }          
            ///////////OTHER FILES
            $other_files_recs=$this->mdb->get_other_files_for_arrangement($v["id"]);
            $no_other_files=sizeof($other_files_recs);
            if ($no_other_files==1){
              $r=array_pop($other_files_recs);
              empty($r["ext"]) ? $ext="" : $ext=".".$r["ext"];
              //Download link for single other file
              $l=CW_DOWNLOAD_HANDLER."?a=".sha1($this->mdb->auth->csid)."&b=".$r["id"];
              $other_files="<a href=\"".$l."\">".$ext."-file</a>";
            } elseif ($no_other_files>1){
              $other_files="<a href='' id='arr_".$v["id"]."' class='arrlistlink_download_other_files'>$no_other_files other files</a>";            
            } else {
              $other_files="";
            }
            ////////////LYRICS SEQUENCE
            $types=$this->mdb->get_fragment_types_records();
            $seq_recs=$this->mdb->get_lyrics_records_from_csl($v["lyrics"]); //$v["lyrics"] is a Comma Seperated List (string))
            $sequence="";
            foreach ($seq_recs as $v5){
              if ($v5["fragment_type"]>0){
                //Not a blank slide
                (($types[$v5["fragment_type"]-1]["title"]=="verse") || ($v5["fragment_no"]>1)) ? $fno=" ".$v5["fragment_no"] : $fno="";                 
                ($v5["language"]!=$this->mdb->get_default_language_for_music_piece($music_piece)) ? $language=" (".$this->mdb->int_to_language($v5["language"]).")" : $language="";               
                $sequence.=", ".str_replace(" ","&nbsp;",$types[$v5["fragment_type"]-1]["title"].$fno.$language);
              } else {
                $sequence.=", blank&nbsp;slide";
              }                                
            }
            if (empty($sequence)){
              $sequence="<span class='gray'>none</span>";
            } else {
              $sequence=substr($sequence,2);
            }          
            if (sizeof($seq_recs)>0){
              $seq_link="<a href='' id='arr_".$v["id"]."' class='arrlistlink_download_lyrics'>lyrics</a>";
            }
            //////////EVERYTHING
            $download_options=0;
            ($parts_cnt>0) ? $download_options++ : null;
            ($no_other_files>0) ? $download_options++ : null;
            (!empty($seq_link)) ? $download_options++ : null;  
            ($download_options>1) ? $everything="<a href='' id='arr_".$v["id"]."' class='arrlistlink_download_everything'>everything</a>" : $everything="";
            $downloads="";
            empty($parts_link) ? null : $downloads.=", ".$parts_link;
            empty($other_files) ? null : $downloads.=", ".$other_files;
            empty($seq_link) ? null : $downloads.=", ".$seq_link;
            empty($everything) ? null: $downloads.=", ".$everything;
            empty($downloads) ? $downloads="<span class='gray'>none</span>" : $downloads=substr($downloads,2);
            

            if (($v["is_presentation_piece"]) && empty($title)){
              $title="<span class='' style='font-style:italic;'>for presentation</span>&nbsp;";
            }
            
            $v["is_presentation_piece"] ? $bgc="background:#FFDDDD" : $bgc="";
            
            $links="";
            if ($v["active"]){
              if ($this->mdb->auth->csps>=CW_A){
                //CW_A required for retiring or deleting
                if ($this->mdb->arrangement_is_in_use($v["id"])){
                  $links.="
                    <a href='' class='retire_arrangement_link' id='ret_".$v["id"]."'>retire</a> |               
                  ";                
                } else {
                  $links.="
                    <a href='' class='delete_arrangement_link' id='del_".$v["id"]."'>delete</a> |               
                  ";                                
                }
              }
              if ($this->mdb->auth->csps>=CW_E){
                //CW_E only needed for editing
                $links.="
                  <a href='' class='edit_arrangement_link' id='edit".$v["id"]."'>edit</a> | 
                ";
              }
              $links.="
                <a href='' class='send_to_service_plan_link' id='edit".$v["id"]."'>send to service</a>              
              ";
            } else {
              $bgc="background:#D66";
              $title="<span style='color:white;'>RETIRED ARRANGEMENT</span>";
              $writers="";
              $links="
                    <a href='' class='retire_arrangement_link' id='ret_".$v["id"]."'>revive</a>                
              ";
            }
            
            $t.="
              <div class='arrangement_div'>
                <div class='arrangement_div_head' style='$bgc'>
                  <span class='data'>$title$writers&nbsp;</span>
                  <div style='float:right'>
                    $links
                  </div>
                </div>
                <div class='arrangement_div_content'>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Key(s):
                    </div>
                    <div class='r'>
                      $mkey
                    </div>
                  </div>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Duration:
                    </div>
                    <div class='r'>
                      ".date("i:s",$v["duration"])."
                    </div>
                  </div>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Style tags:
                    </div>
                    <div class='r'>
                      $style_tags
                    </div>
                  </div>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Parts:
                    </div>
                    <div class='r'>
                      $parts
                    </div>
                  </div>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Downloads:
                    </div>
                    <div class='r'>
                      $downloads
                    </div>
                  </div>
                  <div class='arr_div_line'>
                    <div class='l'>
                      Lyrics:
                    </div>
                    <div class='r'>
                      $sequence
                    </div>
                  </div>
                  <span class='data'>".(nl2br(htmlspecialchars($v["comment"])))."</span>
                </div>
              </div>
            ";
          } else {
            //arrangement is retired AND retired arrangements are hidden
            $ret_arrs[]=$v;
          }//end !$v["active"]
        }
        //Retired arrangements
        $ret_cnt=sizeof($ret_arrs);
        if ($ret_cnt>0){
          ($ret_cnt==1) ? $ret_cnt_note="Show one retired arrangement" : $ret_cnt_note="Show $ret_cnt retired arrangements";
          $t="
            <div class='arrangement_div retired_arr'>
              <a href='' id='show_retired_arrangements'>$ret_cnt_note</a>
            </div>
          ".$t;
        }
        //Script output just once for the whole list 
        $s="
          <script type='text/javascript'>

            $('#show_retired_arrangements').click(function(e){
              e.preventDefault();              
              reload_arrangements(true);
            });
            
            $('.edit_arrangement_link').click(function(e){
              e.preventDefault();              
              load_edit_arrangement_interface($(this).attr('id')); //php needs to chop off the first 4 chars ('edit') in this case
            });

            $('.retire_arrangement_link').click(function(e){
              e.preventDefault();              
              $.post('".$this->ajax."?action=toggle_retire_arrangement',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_arrangements();
              });                          
            });
            
            $('.delete_arrangement_link').click(function(e){
              e.preventDefault();              
              $.post('".$this->ajax."?action=delete_arrangement',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
                reload_arrangements();
              });                          
            });
            

            //Show-hide the modally loaded inserted list of services that the arr can be sent to
            $('.send_to_service_plan_link').click(function(e){
              e.preventDefault();  
              //Save status of this modal2
              var this_modal2_exists=$(this).data('modal2_exists');
              //remove all potentially open modal2-s and reset their statuses
              $('.modal2').remove(); 
              $('.send_to_service_plan_link').data('modal2_exists','');
              if (!this_modal2_exists){
                //if this one did not previously exist, append it
                $(this).parent().parent().append('<div class=\"modal2\" style=\"text-align:center;background:gray;width:100%;color:white;\"></div>');
                $('.modal2').load('".CW_AJAX."ajax_service_planning.php?action=get_upcoming_services_to_send_arrangement_to&arrangement_id='+$(this).attr('id'));
                $(this).data('modal2_exists',true);
              }
            });            
            
            $('.arrlistlink_download_other_files').click(function(e){
              e.preventDefault();
              $.post('".$this->ajax."?action=get_zip_for_arrangement&zip_type=other_files',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='ERR'){
                  //rtn has file-id for zipfile
                  window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
                } else {
                  alert('An error occurred while trying to generate the zip-file for download');
                }
              });                          
            });

            $('.arrlistlink_download_parts').click(function(e){
              e.preventDefault();
              $.post('".$this->ajax."?action=get_zip_for_arrangement&zip_type=parts',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='ERR'){
                  //rtn has file-id for zipfile
                  window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
                } else {
                  alert('An error occurred while trying to generate the zip-file for download');
                }
              });                          
            });
            
            $('.arrlistlink_download_lyrics').click(function(e){
              e.preventDefault();
              $.post('".$this->ajax."?action=get_lyrics_pdf_for_arrangement',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='ERR'){
                  //rtn has file-id for pdf file
                  window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
                } else {
                  alert('An error occurred while trying to generate the PDF-file for download');
                }
              });                          
            });

            $('.arrlistlink_download_everything').click(function(e){
              e.preventDefault();
              $.post('".$this->ajax."?action=get_zip_for_arrangement&zip_type=everything',{ arrangement_id:$(this).attr('id') },function(rtn){
                if (rtn!='ERR'){
                  //rtn has file-id for zipfile
                  window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
                } else {
                  alert('An error occurred while trying to generate the zip-file for download');
                }
              });                          
            });


            
          </script>
        ";               
        return $t.$s;
      }
      return false;
    }
        
    function get_lyrics_fragment_types_for_select(){
      $r=$this->mdb->get_fragment_types_records();
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["id"]."'>".$v["title"]."</option>";
        }
        return $t;
      }
      return false;
    }
    
    function get_scripture_refs_for_select($music_piece){
      $r=$this->mdb->scripture_handling->scripture_refs->get_scripture_ref_records_for_music_piece($music_piece);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          //Convert reference or range to string
          $d=$this->mdb->scripture_handling->scripture_refs->range_to_scripture_string($v["start"],$v["end"]);
          $t.="<option value='".$v["id"]."'>$d</option>";          
        }
        return $t;
      }
      return false;
    }

    function get_themes_for_select($music_piece){
      $r=$this->mdb->get_themes_for_music_piece($music_piece);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["id"]."'>".$v["title"]."</option>";          
        }
        return $t;
      }
      return false;
    }

    function get_style_tags_for_select($arrangement){
      $r=$this->mdb->get_style_tags_for_arrangement($arrangement);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["id"]."'>".$v["title"]."</option>";          
        }
        return $t;
      }
      return false;
    }
    
    function get_parts_for_select($arrangement){
      $r=$this->mdb->get_parts_for_arrangement($arrangement);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["file_id"]."'>".$v["instruments"]."</option>";          
        }
        return $t;
      }
      return false;
    }
    
    function get_other_files_for_select($arrangement){
      $r=$this->mdb->get_other_files_for_arrangement($arrangement);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["id"]."'>".$v["label"]."</option>";          
        }
        return $t;
      }
      return false;
    }

    function get_keychanges_for_select($arrangement){
      $r=$this->mdb->get_keychanges_for_arrangement($arrangement);
      if ($r!==false){
        $t="";
        foreach ($r as $v){
          $t.="<option value='".$v["id"]."'>".$this->mdb->int_to_musical_key($v["musical_key"])."</option>";          
        }
        return $t;
      }
      return false;
    }

    
    function display_edit_arrangement_interface($arrangement){
      $arr_rec=$this->mdb->get_arrangement_record($arrangement);
      $source_title=$this->mdb->get_source_title($arr_rec["source_id"]);
      $piece_rec=$this->mdb->get_music_piece_record($arr_rec["music_piece"]);
      $arrangers=$this->mdb->get_writer_string_for_arrangement($arrangement,CW_MUSICDB_WRITER_CAPACITY_ARRANGER);
      $musical_keys_options=$this->mdb->get_musical_keys_for_select($arr_rec["musical_key"]);
      $musical_mode_options=$this->mdb->get_musical_modes_for_select($arr_rec["musical_key"]);
      $keychange_musical_keys_options=$this->mdb->get_musical_keys_for_select();
      $keychange_musical_mode_options=$this->mdb->get_musical_modes_for_select();
      $arr_rec["is_presentation_piece"] ? $is_presentation_piece_checked="checked='CHECKED'" : $is_presentation_piece_checked="";
      //Prepend the blank-slide option with the lyrics fragments
      $fragments.="<li id='lyr_0' class='blankslide_li data fragment_type' >blank slide</li>";      
      //Get lyrics fragment records
      if ($frag_recs=$this->mdb->get_lyrics_records_for_music_piece($piece_rec["id"])){
        //Get all lyrics fragment types
        $types=$this->mdb->get_fragment_types_records();
        foreach ($frag_recs as $v){
          //Lyrics records
          (($types[$v["fragment_type"]-1]["title"]=="verse")|| ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";
          ($v["language"]!=$this->mdb->get_default_language_for_music_piece($arr_rec["music_piece"])) ? $language=" (".$this->mdb->int_to_language($v["language"]).")" : $language="";               
          $fragments.="<li id='lyr_".$v["id"]."' class='data fragment_type'>".$types[$v["fragment_type"]-1]["title"].$fno."$language</li>"; //utilities_misc
        }
      }
      //

      $t="<div class='modal_head'>Edit arrangement of ".$piece_rec["title"]."</div>
          <div class='modal_body' style='padding:0px;'>
            <div style='width:100%;height:40px;padding-left:12px;'>
              Key: <select id='musical_key'>$musical_keys_options</select>
              Mode: <select id='musical_mode'>$musical_mode_options</select>
              Duration: <div class='data' style='width:40px;padding:0px;display:inline-block;' id='duration_display'>00:00</div><div id='duration_slider' style='width:330px;display:inline-block;margin:10px 25px 0px 20px;padding:0px;'></div>
              Arranger(s): <input id='arranger' type='text' style='width:170px;' value=\"$arrangers\"/>              
              <input id='cb_is_presentation_piece' type='checkbox' $is_presentation_piece_checked/> is presentation piece              
            </div>
            <div style='width:100%;height:465px;border-top:1px solid #DDD;border-bottom:1px solid #DDD;padding:0px;'>
              <div style='width:230px;height:455px;border-right:1px solid #DDD;float:left;'>
                <div id='source_container'>                    
                  <h4>Source</h4>        
                  <div style='padding-top:5px;height:20px;'>Songbook/collection title:</div>
                  <div><input id='source_title' type='text' value=\"$source_title\" /></div>
                  <div style='padding-top:5px;height:20px;'>Index/song # (optional):</div>
                  <div><input id='source_index' type='text' value=\"".$arr_rec["source_index"]."\" /></div>
                </div>          
                <div id='style_tags_container'>                    
                  <h4>Style tags</h4>        
                  <div style='padding-top:5px;height:20px;'>Type/select new style tag:</div>
                  <div><input id='new_style_tag' type='text' /></div>
                  <div style='padding-top:5px;height:20px;'>Tags on this arrangement:</div>
                  <div><select id='style_tags' size='5'></select></div>
                  <div>
                    <input id='delete_selected_style_tag' type='button' class='button' style='width:125px;float:left' value='delete selected'/>
                    <input id='clear_all_style_tags' type='button' class='button' style='width:55px;float:right;' value='clear'/>
                  </div>
                </div>          
              </div>
              <div style='width:300px;height:455px;border-right:1px solid #DDD;float:left;'>
                <div id='parts_container'>                    
                  <h4>Parts</h4>        
                  <div style='padding-top:5px;height:42px;'>To add a part:<br/>1. Select part file to upload:</div>
                  <form id='upload_form' target='invisible_iframe' action=\"".$this->ajax."?action=upload_arr_file\" method=\"POST\" enctype=\"multipart/form-data\">
                    <div style='margin-left:15px;margin-top:5px;margin-bottom:2px;background:#EEE;border:1px solid gray;width:255px;'><input id='filesel' name='filesel' type='file' /></div>
                    <input type='hidden' name='arrangement_id' value='$arrangement'/>
                    <input type='hidden' name='instruments' id='instruments'/>
                  </form>
                  <div style='padding-top:5px;height:18px;'>2. Type/select instruments in part:</div>
                  <div><input id='new_part' type='text' /></div>
                  <div style='padding-top:5px;height:18px;'>Parts in this arrangement:</div>
                  <div><select id='parts' size='10' ></select></div>
                  <div>
                    <input id='delete_selected_part' type='button' class='button' style='width:140px;float:left' value='delete selected'/>
                    <input id='download_all_parts' type='button' class='button' style='width:110px;float:right;' value='download all'/>
                  </div>
                  <iframe name='invisible_iframe' id='invisible_iframe' style='display:none;'></iframe>
                </div>          
              </div>
              <div style='width:300px;height:455px;border-right:1px solid #DDD;float:left;'>
                <div id='other_files_container'>                    
                  <h4>Other files</h4>        
                  <div style='padding-top:5px;height:18px;'>Select a file to upload:</div>
                  <form id='upload_form_other' target='invisible_iframe_other' action=\"".$this->ajax."?action=upload_other_file\" method=\"POST\" enctype=\"multipart/form-data\">
                    <div style='margin-left:15px;margin-top:5px;margin-bottom:2px;background:#EEE;border:1px solid gray;width:255px;'><input id='filesel_other' name='filesel_other' type='file' /></div>
                    <input type='hidden' name='arrangement_id' value='$arrangement'/>
                  </form>
                  <div style='padding-top:5px;height:18px;'>Other files for this arrangement:</div>
                  <div><select id='other_files' size='10'></select></div>
                  <div>
                    <input id='delete_selected_file' type='button' class='button' style='width:140px;float:left' value='delete selected'/>
                    <input id='download_all_other_files' type='button' class='button' style='width:110px;float:right;' value='download all'/>
                  </div>
                  <iframe name='invisible_iframe_other' id='invisible_iframe_other' style='display:none;'></iframe>
                </div>          
                <div id='keychanges_container'>                    
                  <h4>Key changes</h4>
                  <div style='padding-left:12px;'>        
                    <select id='keychange_musical_key'>$keychange_musical_keys_options</select>
                    <select id='keychange_musical_mode'>$keychange_musical_mode_options</select>
                    <input id='add_keychange' type='button' class='button' style='float:right;' value='add'></input>
                  </div>
                  <div style='padding-top:5px;height:18px;'>Key changes in this arrangement:</div>
                  <div>
                    <select id='keychanges' size='10'></select>
                    <input id='clear_keychanges' type='button' class='button' style='width:110px;float:right;' value='clear'/>
                  </div>
                </div>          
              </div>
              <div style='width:320px;height:300px;float:left;'>
                <div id='lyrics_sequence_container'>                    
                  <h4>Lyrics sequence</h4>
                  Drag&drop fragments: <div style='float:right;'><span class='small_note'><a id='apply_default_sequence' href=''>apply default</a> | <a id='clear_sequence' href=''>clear</a></span></div>
                  <ul id='fragment_selector'>
                    $fragments
                  </ul>
                  <ul id=\"sortable\">
                  </ul>
                </div>                
              </div>
            </div>
            <div style='width:1185px;height:40px;'>
              <input id='done_edit_arrangement' type='button' class='button' style='width:110px;float:right;' value='done'/>            
            </div> 
          </div>      
      ";
      
      $s="
        <script type='text/javascript'>

          $('#source_title').autocomplete({
            source:'".CW_AJAX."ajax_music_db.php?action=acomp_source_titles',
            minLength:1,
            autoFocus:true            
          });          
        
          
          $('#duration_slider').slider();
          $('#duration_slider').slider('option','max',60); //Set the slider to no of increments
          $('#duration_slider').slider('value',".floor($arr_rec["duration"]/30)."); //Set the slider to initial position (30 sec intervals)
          $('#duration_slider').slider({
            slide: function(event,ui){
              var val=ui.value;
              if (val==0) { val=1; }
              set_duration_display(val*30);               
            },
            stop: function(event,ui){
              var val=ui.value;
              if (val==0) { val=1; }
              $.post('".$this->ajax."?action=update_arrangements_record&arrangement_id=$arrangement',{ duration:(val*30) },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                }
              });                                    
            }
          }); 

          //Init duration display
          set_duration_display(".$arr_rec["duration"].");

          function set_duration_display(total_seconds){
            var minutes=Math.floor(total_seconds/60);
            var seconds=total_seconds-(minutes*60);
            $('#duration_display').html(zerofill(minutes,2)+':'+zerofill(seconds,2));          
          }

          function zerofill(s,length){
            var n='';
            n+=s;
            while (n.length<length){
              n='0'+n;
            }
            return n;
          }

      		$( '#arranger' )
    			// don't navigate away from the field on tab when selecting an item
    			.bind( 'keydown', function( event ) {
    				if ( event.keyCode === $.ui.keyCode.TAB &&
    						$( this ).data( 'autocomplete' ).menu.active ) {
    					event.preventDefault();
    				}
    			})
    			.autocomplete({
    				source: function( request, response ) {
    					$.getJSON( '".CW_AJAX."ajax_music_db.php?action=acomp_writers', {
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
            save_writers_to_arrangements_record($(this).val());   
          });
          
          function save_writers_to_arrangements_record(val){
            var arranger='".CW_MUSICDB_WRITER_CAPACITY_ARRANGER."';
            $.post('".$this->ajax."?action=save_writers_to_arrangements_record',{ arrangement_id:$arrangement, writer_capacity:arranger, value:val },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });                        
          }
          
          function save_musical_key_and_mode(){
            var key=$('#musical_key').val();
            var mode=$('#musical_mode').val();
            var mkey;
            if (mode=='+'){
              //Major
              mkey=parseInt(key);
            } else {
              //Minor
              mkey=(-1)*parseInt(key);
            }
            $.post('".$this->ajax."?action=update_arrangements_record&arrangement_id=$arrangement',{ musical_key:mkey },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              //Reload keychanges because they get cleared when the original key changes
              load_keychanges();
            });                                    
          }
          
          $('#musical_key').change(function(){
            save_musical_key_and_mode();
          });
              
          $('#musical_mode').change(function(){
            save_musical_key_and_mode();
          });
          
          $('#cb_is_presentation_piece').click(function(){
            var val=0;
            if ($(this).attr('checked')){ 
              val=1;              
            }
            $.post('".$this->ajax."?action=update_arrangements_record&arrangement_id=$arrangement',{ is_presentation_piece:val },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });                                    
          });
          
          $('#source_title').change(function(){
            $.post('".$this->ajax."?action=save_arrangement_source_title',{ arrangement_id:$arrangement, value:$(this).val() },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });                                  
          });
          
          $('#source_index').change(function(){
            $.post('".$this->ajax."?action=save_arrangement_source_index',{ arrangement_id:$arrangement, value:$(this).val() },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
            });                                  
          });
          
          /////////////////////////// Style tags /////////////////////
          
          function reload_style_tags(){
            $('#style_tags').load('".$this->ajax."?action=get_style_tags&arrangement_id=$arrangement'); 
          }
          
          reload_style_tags(); //init
          
          $('#delete_selected_style_tag').click(function(){
            $.post('".$this->ajax."?action=delete_style_tag',{ style_tag_id:$('#style_tags').val(),arrangement_id:$arrangement },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_style_tags();                           
            });                   
          });          
  
          $('#clear_all_style_tags').click(function(){
            $.post('".$this->ajax."?action=delete_all_style_tags',{ arrangement_id:$arrangement },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_style_tags();                           
            });                                              
          });          
  
          function assign_existing_style_tag(sel_id){
            $.post('".$this->ajax."?action=assign_existing_style_tag',{ arrangement_id:$arrangement,style_tag_id:sel_id },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_style_tags();                           
              $('#new_style_tag').val('');
            });
          }
  
          $('#new_style_tag').autocomplete({
            source:'".CW_AJAX."ajax_music_db.php?action=acomp_style_tags',
            minLength:1,
            select: function(event,ui){
              $('#new_style_tag').data('auto',true); //Set flag that an selection has been made through the autocomp (don't interpret enter as search command)
              assign_existing_style_tag(ui.item.id);
            },
            autoFocus:true            
          }).keypress(function(e){
            if (e.keyCode==13){
              if ($('#new_style_tag').data('auto')!=true){
                //If auto complete fails
                if ($(this).val()!=''){
                  $.post('".$this->ajax."?action=add_new_style_tag',{ arrangement_id:$arrangement,val:$(this).val() },function(rtn){
                    if (rtn!='OK'){
                      alert(rtn);
                    } else {
                      $('#new_style_tag').val('');
                    }
                    reload_style_tags();                           
                  });
                }
              }
            } else {
              $('#new_style_tag').data('auto',false);   
            }
          });
          
          /////////////////// Parts //////////

          function reload_parts(){
            $('#parts').load('".$this->ajax."?action=get_parts&arrangement_id=$arrangement'); 
          }
          
          reload_parts(); //init

      		$( '#new_part' )
    			// don't navigate away from the field on tab when selecting an item
    			.bind( 'keydown', function( event ) {
    				if ( event.keyCode === $.ui.keyCode.TAB &&
    						$( this ).data( 'autocomplete' ).menu.active ) {
    					event.preventDefault();
    				}
    			})
    			.autocomplete({
    				source: function( request, response ) {
    					$.getJSON( '".CW_AJAX."ajax_music_db.php?action=acomp_parts', {
    						term: extractLast( request.term )
    					}, response );
    				},
    				search: function() {
    					// custom minLength
    					var term = extractLast( this.value );
    					if ( term.length < 1 ) {
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
              $(this).data('auto',true);
    					return false;
    				},
            autoFocus:true            
    			})
          .keyup(function(e){
            if ((e.keyCode==13) && (!$(this).data('auto'))){
              //save new part
              if ($('#filesel').val()!=''){
                //File has been selected
                if ($(this).val()!=''){
                  //Instrument(s) have been given
                  $('#instruments').val($('#new_part').val());
                  $('#invisible_iframe').data('reload',true);
                  $('#upload_form').submit();
                } else {
                  alert('To add a new part you must specify the instrument(s) that are in the part');
                }                
              } else {
                if ($(this).val()!=''){
                  alert('To add a new part you must select a PDF file first');
                }
              }
            }
            $(this).data('auto',false);
          });

          $('#filesel').change(function(){
            //Focus #new_part after file selection
            $('#new_part').focus();
          });
                    
          $('#invisible_iframe').load(function(){
            //After loading of the iframe has completed, the new part has been successfully saved. Reload parts.
            if ($(this).data('reload')){
              reload_parts();
              //Also empty the #new_part and #filesel inputs
              $('#new_part').val('');
              $('#filesel').val('');
              $(this).data('reload',false);
            }
          });
          
          $('#delete_selected_part').click(function(){
            $.post('".$this->ajax."?action=delete_part',{ file_id:$('#parts').val(),arrangement_id:$arrangement },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_parts();                                       
            });
          });

          $('#parts').dblclick(function(){
            //Download selected part
            //The download handler needs sha1-encoded service id (as a)(for auth check), and file id (as b)
            window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + $(this).val();
          });

          $('#download_all_parts').click(function(){
            $.post('".$this->ajax."?action=get_zip_for_arrangement&zip_type=parts',{ arrangement_id:$arrangement },function(rtn){
              if (rtn!='ERR'){
                //rtn has file-id for zipfile
                window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
              } else {
                alert('An error occurred while trying to generate the zip-file for download');
              }
            });            
          });

          /////////////////// Other files //////////

          function reload_other_files(){
            $('#other_files').load('".$this->ajax."?action=get_other_files&arrangement_id=$arrangement'); 
          }
          
          reload_other_files(); //init
                    
          $('#invisible_iframe_other').load(function(){
            //After loading of the iframe has completed, the new file has been successfully saved. Reload other files.
            if ($(this).data('reload')){
              reload_other_files();
              //Also empty the #filesel_other input
              $('#filesel_other').val('');
              $(this).data('reload',false); //This is probably not neccessary
            }
          });
          
          $('#filesel_other').change(function(){
            //Upload the file
            $('#invisible_iframe_other').data('reload',true);
            $('#upload_form_other').submit();            
          });
          
          $('#delete_selected_file').click(function(){
            $.post('".$this->ajax."?action=delete_other_file',{ file_id:$('#other_files').val(),arrangement_id:$arrangement },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_other_files();                                       
            });
          });
          
          $('#other_files').dblclick(function(){
            //Download
            //The download handler needs sha1-encoded service id (as a)(for auth check), and file id (as b)
            window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + $(this).val();
          });

          $('#download_all_other_files').click(function(){
            $.post('".$this->ajax."?action=get_zip_for_arrangement&zip_type=other_files',{ arrangement_id:$arrangement },function(rtn){
              if (rtn!='ERR'){
                //rtn has file-id for zipfile
                window.location.href = '".CW_DOWNLOAD_HANDLER."?a=' + CryptoJS.SHA1('".$this->mdb->auth->csid."') + '&b=' + rtn;
              } else {
                alert('An error occurred while trying to generate the zip-file for download');
              }
            });            
          });


          ////////////////// Key changes
          
          function load_keychanges(){
            $('#keychanges').load('".$this->ajax."?action=get_keychanges&arrangement_id=$arrangement'); 
          }
          
          load_keychanges(); //init
          
          $('#add_keychange').click(function(){
            var key=$('#keychange_musical_key').val();
            var mode=$('#keychange_musical_mode').val();
            var mkey;
            if (mode=='+'){
              //Major
              mkey=parseInt(key);
            } else {
              //Minor
              mkey=(-1)*parseInt(key);
            }
            $.post('".$this->ajax."?action=add_keychange&arrangement_id=$arrangement',{ musical_key:mkey },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              load_keychanges();
            });                                              
          });
          
          $('#clear_keychanges').click(function(){
            $.post('".$this->ajax."?action=clear_keychanges&arrangement_id=$arrangement',{},function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              load_keychanges();
            });                                                        
          });
          

          ////////////////// Lyrics Sequence
          
          function reload_lyrics_sequence(){
            $('#sortable').load('".$this->ajax."?action=get_lyrics_sequence_for_sortable&arrangement_id=$arrangement&music_piece=".$arr_rec["music_piece"]."');
          }
          
          reload_lyrics_sequence(); //init
          
          $('#apply_default_sequence').click(function(e){
            e.preventDefault();
            var arr_id=$arrangement;
            $.post('".$this->ajax."?action=apply_default_sequence',{ arrangement_id:$arrangement,music_piece:".$arr_rec["music_piece"]." },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_lyrics_sequence();
            });                       
          });
          
          $('#clear_sequence').click(function(e){
            e.preventDefault();
            var arr_id=$arrangement;
            $.post('".$this->ajax."?action=clear_sequence',{ arrangement_id:$arrangement,music_piece:".$arr_rec["music_piece"]." },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_lyrics_sequence();
            });                       
          });
                    
          function generate_fragment_instance(fragment_id,new_fragment_position){
            $.post('".$this->ajax."?action=add_fragment_to_sequence',{ arrangement_id:$arrangement,lyrics:fragment_id,sequence_no:new_fragment_position },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_lyrics_sequence();
            });                       
          }
          
          function repos_fragment_instance(old_pos,new_pos){
            $.post('".$this->ajax."?action=repos_fragment_in_sequence',{ arrangement_id:$arrangement, old_pos:old_pos, new_pos:new_pos },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_lyrics_sequence();
            });                                 
          }
          
          function delete_lyrics_from_sequence(position){
            $.post('".$this->ajax."?action=delete_fragment_from_sequence',{ arrangement_id:$arrangement, sequence_no:position },function(rtn){
              if (rtn!='OK'){
                alert(rtn);
              }
              reload_lyrics_sequence();
            });                       
          }
          
        	$(function() {
        		$( '#sortable' ).sortable({
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
                var old_pos = $(ui.item).attr('id'); //old position is the li-id
                var new_pos = $(this).data().sortable.currentItem.index()+1;
                //See if this was called after repositioning or after receiving
                if (!$(ui.item).data('nostop')){
                  repos_fragment_instance(old_pos,new_pos);                  
                } else {
                  $(ui.item).data('nostop',false);                  
                }
              },
              start: function(event,ui){
                $(ui.helper).css('background','#FAEAEA');                    
                $(ui.item).data('noclick',true); //Avoid that the drag is being interpreted as click
              }
        		});
        		$( '.fragment_type' ).draggable({
        			connectToSortable: '#sortable',
        			helper: 'clone',
        			revert: 'invalid',
              start: function(event,ui){
                
              }
        		}); 
            
        		$( 'ul, li' ).disableSelection();                                      

            //Shiftkey held while hover - mark element
            $(document).keydown(function(e){
              if ($('#modal').is(':visible')){
                var el=$('#sortable').data('hover');
                if (el){
                  if (e.keyCode==16){
                    $('#'+el).css('background','#FAA');            
                  }
                }
              }
            });
                          
        	});

          $('#done_edit_arrangement').click(function(){
            close_modal();
          });

          
        </script>
      ";
      return $t.$s;
    }
    
    function get_edit_lyrics_fragment_interface($lyrics_id,$music_piece=0){
      $t="";
      //Get lyrics
      $r=$this->mdb->get_lyrics_record($lyrics_id);            
      if (is_array($r)){
        //Get title      
        $types=$this->mdb->get_fragment_types_records();
        (($types[$r["fragment_type"]-1]["title"]=="verse") || ($r["fragment_no"]>1)) ? $fno=" ".$r["fragment_no"] : $fno="";
        $language="";
        if ($music_piece>0){
          ($r["language"]!=$this->mdb->get_default_language_for_music_piece($music_piece)) ? $language=$this->mdb->int_to_language($r["language"]) : null;        
        }     
        (!empty($language)) ? $language=" <span>(".$language.")</span>" : null;
        $title=$types[$r["fragment_type"]-1]["title"].$fno.$language;      
        $t.="
          <div style='border:1px dotted gray; padding:0px; margin:0px;width:288px;'>        
            <textarea id='fragment_textarea' style='width:284px;height:120px;font-size:70%;resize:none;background:white;border:none;'>".$r["content"]."</textarea>
          </div>
          <div style='padding:0px;margin:0px auto;width:188px;'>        
          <input style='width:180px;' type='button' id='done' class='button' value='done'/>
          </div>
          
          <script type='text/javascript'>
            $('#fragment_textarea').blur(function(){
              $.post('".$this->ajax."?action=save_edited_lyrics_fragment',{ lyrics_id:$lyrics_id,content:$('#fragment_textarea').val() },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                } else {                    
                  close_modal();                
                }
              });            
            });
            
            $('#done').click(function(){
              $('#fragment_textarea').blur();
            });
          </script>
        ";        
      } else {
        $t.="Could not load lyrics fragment #$lyrics_id";      
      }
      $t="<div class='modal_head'>Edit lyrics: $title</div><div class='modal_body'>$t</div>";
      return $t;
    }
    
    //Output filtered table  
    function display_songlist($term,$field,$page,$items_per_page=CW_SONGLIST_ITEMS_PER_PAGE){
      if ($page==0){
        $page=1;
      }
      $t="";
      //Obtain query string for the search term
      $query=$this->mdb->get_songsearch_autocomplete_suggestions($term,$field,true);
      if (empty($query)){
        $query="SELECT * FROM music_pieces ORDER BY title";        
      }
      //Add limits for requested page
      //$query.=" LIMIT ".((($page-1)*$items_per_page)).",".(($page*$items_per_page));
      //Obtain matching music pieces into array $r;
      $r=array();
      if ($res=$this->d->q($query)){
        while ($e=$res->fetch_assoc()){
          $r[]=$e;
        }
      } 

      $n=sizeof($r);
      $word="pieces";
      if ($n==1){
        $word="piece";
      }
      
      //How many pages are there?
      $page_cnt=floor(($n-1)/$items_per_page)+1; 
      
      if ($n>0){
        //At least one entry found
        $page_info="<a href='' id='prev_page' style='color:white;'>previous</a> | page $page/$page_cnt | <a href='' id='next_page' style='color:white;'>next</a>";
        $page_info.="
          <script type='text/javascript'>
            $('#next_page').click(function(e){
              e.preventDefault();    
              $('#edit_piece').load('".CW_AJAX."ajax_music_db.php?action=get_songlist&term='+ encodeURIComponent('$term') +'&page=".min($page+1,$page_cnt)."');            
            });
            $('#prev_page').click(function(e){
              e.preventDefault();
              $('#edit_piece').load('".CW_AJAX."ajax_music_db.php?action=get_songlist&term='+ encodeURIComponent('$term') +'&page=".max($page-1,1)."');            
            });
          </script>";
        $page_info="$n $word found <div style='float:right;padding-right:6px;'>$page_info</div>";
      } else {
        //Nothing found
        $page_info="No music pieces were found for term '$term'";
      }
      $t.="<div id='title_header' style='text-align:left;'><div style='padding-left:2px;'>$page_info</div></div>";
      $t.="<table id='cd_ind_view_table'>";
      for ($i=($page-1)*$items_per_page;$i<min($page*$items_per_page,sizeof($r));$i++){
        $v=$r[$i];
        
        $title=$this->mdb->get_music_piece_title_info($v["id"]);
        //Markup odd or even
        $x++;
        $class="even";
        if (($x%2)>0){
          $class="odd";        
        }
                        
        $t.="<tr id='r".$v["id"]."' class='$class mdb_songrow'>
              <td>$title</td>
              <td style='text-align:right;'><a class='blink' href='javascript:epr(".$v["id"].");'>select</a>&nbsp;</td>
            </tr>  
          ";
      }
      
      $t.="</table>
          <script type='text/javascript'>
            $('.mdb_songrow').click(function(e){
              if (!e.shiftKey){
                get_music_piece_edit_interface($(this).attr('id').substr(1));
              } else {
                if (confirm('Are you sure you want to delete the entire song with all arrangements?')){
                  $.post('".$this->ajax."?action=delete_music_piece',{ music_piece_id:$(this).attr('id').substr(1) },function(rtn){
                    if (rtn=='OK'){
                      alert('Music piece deleted!');
                      location.reload();                  
                    } else {
                      alert(rtn);
                    }
                  });                  
                }
              }
            }).disableSelection()
            .mouseenter(function(e){
              if (e.shiftKey){
                $(this).addClass('redbg');
              }
            }).
            mouseleave(function(){
              $(this).removeClass('redbg');              
            });
          </script>                      
      ";
      return $t;
    }
    

  }

?>
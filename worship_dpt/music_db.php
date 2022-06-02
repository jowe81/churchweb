<?php

  require_once "../lib/framework.php";

  $p->stylesheet(CW_ROOT_WEB."css/music_db.css");
  
  $add_input="";
  $cwidth="530px";
  if ($a->csps>=CW_A){
    $add_input="
        <div style='float:left;padding:5px;border-left:1px solid gray;'>
          Add a song or music piece
          <div class='small_note'>Type a title and hit enter</div>
          <input id='new_title' style='width:400px;' type='text'/>
        </div>    
    ";
    $cwidth="950px";
  }
  
  $t="
    <div style='background:#DDDDFF;width:100%;height:75px;padding:0px;overflow:hidden;'>
      <div style='margin-left:auto;margin-right:auto;width:$cwidth;'>
        <div style='float:left;padding:5px;'>
          Select <span id='music_pieces_size'></span> songs and music pieces  <img id='get_help' src='".CW_ROOT_WEB."img/help.gif'/>
          <div class='small_note'>Select a field and start typing a search term</div>
          <select id='field' style='padding:1px;width:210px;'>
            <option value='0'>title or scripture reference</option>
            <option value='1'>writer</option>
            <option value='2'>theme</option>
            <option value='3'>lyrics fragment</option>
          </select>
          <input id='search' style='width:270px;' type='text'/>
        </div>
        $add_input
      </div>
    </div>
    
    <div id='edit_piece'>
    </div>
      ";
  $j="
    $('#get_help').click(function(){get_help(2);});
  
    load_music_pieces_size();
  
    //Retrieve via AJAX the title of the piece with the id
    function get_music_piece_title_to_search_input(id){
      $.post('".CW_AJAX."ajax_music_db.php?action=get_music_piece_title_by_id',{ music_piece_id:id },function(rtn){
        $('#search').val(rtn).select();
      });       
    }  
  
    $('#search').autocomplete({
      source:function(request,response){
        $.get('".CW_AJAX."ajax_music_db.php?action=acomp_songs&term='+$('#search').val()+'&field='+$('#field').val(),function(rtn){
          var data=eval(rtn);
          response(data);
        });
      },
      minLength:2,
      autoFocus:true,
      select: function(event,ui){
        $('#search').data('auto',true).select(); //Set flag that an selection has been made through the autocomp (don't interpret enter as search command)
        get_music_piece_edit_interface(ui.item.id);
      }
    }).keypress(function(e){
      if (e.keyCode==13){
        if ($('#search').data('auto')!=true){
          //If auto complete fails
          $('#edit_piece').load('".CW_AJAX."ajax_music_db.php?action=get_songlist&field='+$('#field').val()+'&term='+encodeURIComponent($(this).val())); //encoudeURIComponent to allow spaces as in 'Amazing Grace' or '-instruments=full score'
        }
        $(this).select();
      } else {
        $('#search').data('auto',false);   
      }
    });
    
    $('#search').focus(function(){
      $(this).val('');
    });
    
    $('#search').focus();
    
    
    $('#new_title').keypress(function(e){
      if (e.keyCode==13){
        if ($(this).val()!=''){
          $.post('".CW_AJAX."ajax_music_db.php?action=save_new_piece',{ title:$(this).val() },function(rtn){
            if (rtn>0){
              $('#search').val($('#new_title').val()).select();
              $('#new_title').val('');
              load_music_pieces_size(); //Update this info
              get_music_piece_edit_interface(rtn); //rtn has id of newly created music_pieces record
            } else {
              alert(rtn);
            }
          });
        }
      }
    });
  ";

  $s="
    function get_music_piece_edit_interface(id){
      $('#edit_piece').load('".CW_AJAX."ajax_music_db.php?action=get_edit_music_piece_interface&id='+id);          
    }
    
    //Load the number of records in the db
    function load_music_pieces_size(){
      $('#music_pieces_size').load('".CW_AJAX."ajax_music_db.php?action=get_music_pieces_size',{},function(rtn){
      });    
    }
    
    //Esc clears filter or leaves modal
    $(document).keyup(function(e){
      //Only work with the songsearch focus if the modal window is not visible
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
        //for edit lyrics fragment
        $('#modal').css('width','300px');
        $('#modal').css('height','200px');
        $('#modal').css('left','520px');
        $('#modal').css('top','165px');
      } else {
        //for edit arrangement
        $('#modal').css('width','1200px');
        $('#modal').css('height','585px');
        $('#modal').css('left','20px');
        $('#modal').css('top','45px');
      }
      $('#modal').fadeIn(200);                        
    }    
    
    function close_modal(){
      $('#add_arrangement').focus(); //Put focus to an element outside the modal to make sure blur gets triggered for the field that has focus in the modal
      $('#main_content').fadeTo(500,1);
      $('#modal').hide(200);
      reload_arrangements();
      reload_lyrics_fragments();
    }    

  
  ";
  
  //Return call from mediabase?
  if ($_GET["music_piece"]>0){
  	$mdb=new cw_Music_db($a);
  	$j.="
  		get_music_piece_edit_interface(".$_GET["music_piece"].");
  	";
  	
  	if (($_GET["action"])=="assign_bg_media"){
  		if ($_GET["lib_id"]>0){
  			$mdb->assign_background_to_music_piece($_GET["music_piece"], $_GET["lib_id"], new cw_Mediabase($a));
  		}
  	}
  		 
  }
  
  
  $p->p($t);
  $p->jquery($j);
  $p->js($s);
  
?>
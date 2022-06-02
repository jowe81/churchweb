<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/church_directory.css");

  if ($_GET["action"]==""){  
    //No specific action: display filter and list    
    $def_val_last="(type a last name to filter)";
    $def_val_first="(type a first name to filter)";
    $p->p("
      <div id='cd_filter_controls'>
        <input type='text' id='f_last_name' value='$def_val_last'>
        <input type='text' id='f_first_name' value='$def_val_first'>
      </div>
    ");
    $p->jquery("
                //Esc clears filter
                $(document).keyup(function(e){
                  //Only work with the filter if the modal window is not visible
                  if (!($('#modal').is(':visible'))){
                    if (e.keyCode==27) {
                      $('#f_last_name').val('$def_val_last');
                      $('#f_first_name').val('$def_val_first');
                      $('#f_last_name').focus();                    
                    }
                    init_dest_element();
                  } else {
                    if (e.keyCode==27) {
                      //Esc was hit with modal visible - leave modal
                      close_modal();
                    }
                  }
                });
                
                $('#f_last_name').keydown(function(){
                  if ($(this).val()=='$def_val_last'){                  
                    $(this).val('');
                  }
                });
                
                $('#f_first_name').focus(function(){
                  if ($(this).val()=='$def_val_first'){                  
                    $(this).val('');
                  }
                });

    ");
    //Init_dest_element must be in global js scope
    $p->js("
                function init_dest_element(id){
                  var last_name=$('#f_last_name').val();
                  if (last_name=='$def_val_last') {
                    last_name='';
                  }
                  var first_name=$('#f_first_name').val();
                  if (first_name=='$def_val_first') {
                    first_name='';
                  }
                  $('#cd_list').load('".CW_AJAX."ajax_church_directory.php?last_name=' + last_name +'&first_name=' + first_name);
                }  
                
                function close_modal(){
                      $('#main_content').fadeTo(500,1);
                      $('#modal').hide(200);
                      init_dest_element();                                    
                }    
    ");
  
    //Unfortunately for now we need to do this here (because this code must be inside document.ready)  
    $p->jquery("
                $(window).resize(function(){
                  $('#cd_list').css('height',($(document).height()-100));
                });  
    ");
  
    $p->p("<div id='cd_list'>Loading...</div>");
    
    $p->jquery("
      init_dest_element(".$_GET["id"].");
      $('#f_last_name').focus();
    ");
      
  }



?>
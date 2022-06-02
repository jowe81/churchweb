<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/personal_records.css");

  //No specific action: display filter and list    
  $def_val_name="(type a group name to filter)";
  //If name is passed in GET, use as initial filter value
  $init_name=$def_val_name;
  if ($_GET["name"]!=''){
    $init_name=$_GET["name"];
  }
  $p->p("
    <div id='cd_filter_controls'>
      <input type='text' id='f_name' value='$init_name'>
    </div>
  ");
  $p->jquery("
              //Esc clears filter
              $(document).keyup(function(e){
                //Only work with the filter if the modal window is not visible
                if (!($('#modal').is(':visible'))){
                  if (e.keyCode==27) {
                    $('#f_name').val('$def_val_name');
                    $('#f_name').focus();                    
                  }
                  init_dest_element();
                } else {
                  if (e.keyCode==27) {
                    //Esc was hit with modal visible - leave modal
                    $('#main_content').fadeTo(500,1);
                    $('#modal').hide(200);
                    init_dest_element();                    
                  }
                }
              });
              
              $('#f_name').keydown(function(){
                if ($(this).val()=='$def_val_name'){                  
                  $(this).val('');
                }
              });
              

  ");
  //Init_dest_element must be in global js scope
  //If init_dest_element is passed a group_id, the edit dialogue for that group will be loaded in the modal window
  $p->js("
              function init_dest_element(id){
                var name=$('#f_name').val();
                if (name=='$def_val_name') {
                  name='';
                }
                $('#cd_list').load('".CW_AJAX."ajax_manage_groups.php?name=' + escape(name) +'&goto=' +id);
              }      
  ");

  //Unfortunately for now we need to do this here (because this code must be inside document.ready)  
  $p->jquery("
              $(window).resize(function(){
                $('#cd_list').css('height',($(document).height()-100));
              });  
  ");

  $p->p("<div id='cd_list'>Loading...</div>");
  
  //In case a group name has been passed (probably by add_group.php), find the corresponding id and make sure we'll go to the edit window for this group  
  $id=$a->groups->get_id($_GET["name"]);
   
  $p->jquery("
    init_dest_element($id);
    $('#f_name').focus();
  ");    
    
      

?>
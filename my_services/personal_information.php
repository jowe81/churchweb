<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/personal_records.css");

  if ($_GET["action"]==""){  
    //Init_dest_element must be in global js scope
    $p->js("
                function init_dest_element(){
                  $('#cd_list').load('".CW_AJAX."ajax_personal_information.php?action=display_edit_dialogue&id=".$a->cuid."',function(){
                  });
                }      
    ");
    
    $p->p("<div id='cd_list' style='width:1090px;left:105px;top:70px;overflow:visible;'>Loading...</div>");
    
    $p->jquery("
      init_dest_element();
    ");
      
  }



?>
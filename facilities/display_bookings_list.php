<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/display_bookings_list.css");


  if ($_GET["action"]==""){
    $p->p("            
      <div>
        <div style='padding:5px;'>
          <h4>Daily List</h4>
          <p><a href='?action=get_printer_friendly_list' target='_blank'>Printer-friendly list of today's bookings</a></p>
        </div>
      </div>      
    ");
    
  } elseif ($_GET["action"]=="get_printer_friendly_list"){  
    $p->p("            
      <div>
        <div style='padding:5px;'>
          <div id='bookings_list'> 
            loading...
          </div>
        </div>
      </div>      
    ");
    //Ajax in the list of booking
    $p->jquery("
      reload_bookings_list();
            
      function reload_bookings_list(){
        $('#bookings_list').load('".CW_AJAX."ajax_display_bookings_list.php?action=get_bookings_list');
        setTimeout(reload_bookings_list,".CW_SERVICE_PLANNING_SERVICE_LIST_RELOAD_INTERVAL."*1000);      
      }
    ");       
    $p->no_page_framework=true;
  }



?>
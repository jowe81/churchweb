<?php

  require_once "../lib/framework.php";

  //Load styles
  $p->stylesheet(CW_ROOT_WEB."css/my_commitments.css");

  if ($_GET["action"]==""){  
    $p->p("            
      <div id='services_list_container'>
        <div style='padding:5px;'>
          <h4>Upcoming services that you have been scheduled for</h4>
          <div id='services_list'>
          </div>
        </div>
      </div>      
      <div id='tasks'>
        <div class='expl' style='width:190px;float:left;'>
          <p>
            The list on the left shows you for which services you have been scheduled, and whether or not you accepted the invitation to participate. You can also change your decision (click the checkbox).
          </p>
          <p>
            Click the link below to see information about your past involvement.
          </p>
        </div>
        <div style='width:190px;float:left;padding:10px;font-size:80%;'>
          <a href='?action=display_history'>Display history of involvement</a>
        </div>
      </div>
    ");
    //Ajax in the list of upcoming services
    $p->jquery("
      reload_services_list();
            
      function reload_services_list(){
        $('#services_list').load('".CW_AJAX."ajax_my_commitments.php?action=get_services_list');
        setTimeout(reload_services_list,".CW_SERVICE_PLANNING_SERVICE_LIST_RELOAD_INTERVAL."*1000);      
      }
    ");
  } elseif ($_GET["action"]=="display_history"){
    $p->p("            
      <div id='services_list_container'>
        <div style='padding:5px;'>
          <h4>History of your involvement</h4>
          <div id='services_list'>
          </div>
        </div>
      </div>      
      <div id='tasks'>
        <div style='width:190px;float:left;padding:10px;font-size:80%;'>
          <a href='?action='>Back to upcoming services</a>
        </div>
      </div>
    ");
    //Ajax in the list of upcoming services
    $p->jquery("
      reload_services_list();
            
      function reload_services_list(){
        $('#services_list').load('".CW_AJAX."ajax_my_commitments.php?action=get_history');
        setTimeout(reload_services_list,".CW_SERVICE_PLANNING_SERVICE_LIST_RELOAD_INTERVAL."*1000);      
      }
    ");
  
  }



?>
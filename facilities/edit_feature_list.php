<?php

  require_once "../lib/framework.php";

  $rooms=new cw_Rooms($d);

  //Editing the room feature list requires at least Editor rights  
  if ($a->csps<CW_E){
    $p->error(CW_E,array($a->get_cspl()=>"OK"));  
  } else {
    //Display list and form
    $p->p("    
      <div>
        <table>
          <tr>
            <td>
              <p>List of amenities:</p>
              <p><select id='sel' style='width:200px;' size=20></select></p>
            </td>
            <td>
              <p>New amenity:</p>
              <p><input id='new' type='text' /></p>
              <p><div class='button' style='width:188px' id='add'>&lt;&lt; Add new amenity to list</div></p>
              <p><div class='button' style='width:188px' id='del'>Delete selected amenity</div></p>
              <p><a href='".$a->get_cspl()."'><div class='button button_default' style='width:188px' id='done'>Done</div></a></p>              
            </td>
          </tr>
        </table>
      </div>
    ");
    
    //Disallow quotes in the input field
    $p->jquery(jq_no_quote("#new"));
    
    //Assign ajax queries to the delete and add buttons
    $p->jquery("
      $('#del').click(function(){
         del_id = $('#sel option:selected').attr('id');
         $.post('".CW_AJAX_DB."', { query:'DELETE FROM room_features WHERE id=' +del_id });
         reload();    
      });
      $('#add').click(function(){
         if ($('#new').attr('value')!=''){
          sql_query = 'INSERT INTO room_features VALUES(0,\'' +$('#new').attr('value') +'\')';
          $.post('".CW_AJAX_DB."', { query: sql_query },function(){
            reload();      
            $('#new').attr('value','');
          });
         }
      });
    ");
    
    //Reload function for the select box - execute on load
    $p->jquery("
      function reload(){
        $.post('".CW_AJAX_DB."', { query:'SELECT * FROM room_features' },function(rtn){
          var json = eval('(' + rtn + ')'); //Create JSON object from returned string   
          var sel_html='';
          $.each(json,function(id,r){
            sel_html+='<option id=\"' + r.id + '\">' + r.feature +'</option>';
                    
          });
           $('#sel').html(sel_html);      
        });
      }
      reload(); //Execute reload on page load
    
    ");

    //Give focus to the input field
    $p->jquery("$('#new').focus()");
  }
  

?>
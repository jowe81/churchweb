<?php

class cw_Display_conversations {
  
    private $d,$auth,$conversations,$ajax_scriptname; //Passed in
    
    function __construct($conversations,$ajax_scriptname="ajax_home.php"){
      $this->conversations=$conversations;
      $this->auth = $conversations->auth;
      $this->d = $conversations->d;
      $this->ajax_scriptname=$ajax_scriptname; //Where to send ajax requests?
    }
    
    //Is called with $omit_wrapper by the ajax reload function; initial call must include the wrapper 
    function display_conversation($id,$omit_wrapper=false){
      $t="";
      if ($r=$this->conversations->get_conversations_record($id)){
        //Link to add a note
        $t.="
          <div class=\"conversation_add_note\">
            <a id=\"add_note_link\" href=''>Add a note</a>
            <input style='display:none;width:98%;background:#FFE;' type='text' id=\"new_note_text\"/>
          </div>
          <script type='text/javascript'>
            $('#add_note_link').click(function(e){
              e.preventDefault();
              $('#new_note_text').css('display','inline').focus();
              
            });
            
            $('#new_note_text').keydown(function(e){
              if ((e.keyCode==13) && ($(this).val()!='')){
                $.post('".CW_AJAX.$this->ajax_scriptname."?action=conversation_add_note', { conversation_id:$id,content:$(this).val() }, function(rtn){
                  if (rtn!='OK'){
                    alert(rtn);
                  } else {
                    //Success - clear and hide input field
                    $('#new_note_text').val('').css('display','none');
                    //Reload conversation
                    reload_conversation_$id();                  
                  }                                                        
                });
              }          
            });
          </script>
        ";
        for ($i=$r["number_of_notes"];$i>0;$i--){
          $t.=$this->display_note($id,$i);
        }
        //Save sync mark (i.e. timestamp of latest reload by current session)
        $this->conversations->set_sync_mark($id);   
      } else {
        $t="<span class='gray'>could not load conversation #$id</span>";      
      }
      //Script: display input on mouseover, hide on mouseout (unless user has typed something)
      $s="
        <script type='text/javascript'>
          $('.conversation_note').mouseenter(function(){
            var response_div=$(this).children('.conversation_response');
            $(response_div).css('display','block');    
            $(response_div).children('input').select().focus();    
          }).mouseleave(function(){
            var response_div=$(this).children('.conversation_response');
            var inp=$(response_div).children('input');
            if ((inp.val()=='') || (inp.val()=='".CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT."')){
              $(response_div).css('display','none');                
            }                
          });
          
          $('.response_input').keydown(function(e){
            if ((e.keyCode==13) && ($(this).val()!='".CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT."')){
              var response_div=$(this).parent();
              var response_input=$(this);
              $.post('".CW_AJAX.$this->ajax_scriptname."?action=conversation_add_contribution',{ conversation_id:$id,note_no:$(this).attr('id').substr(3),content:$(this).val() },function(rtn){
                if (rtn!='OK'){
                  alert(rtn);
                } else {
                  //Success - hide and clear input
                  response_div.css('display','none');
                  response_input.val('".CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT."');
                  update_conversation_$id(new Array($(response_input).attr('id').substr(3)));
                }
              });
            }
          });
          
          //Accepts array with note_nos to update
          function update_conversation_$id(notes_to_update){
            if (!((notes_to_update.length==1) && (notes_to_update[0]==''))){
              //Only do anything if there's at least one real (not empty) element
              var note_divs=new Array();
              for (n=0;n<notes_to_update.length;n++) {
                note_divs.push($('#note_'+notes_to_update[n]));
                var latest_loaded_contribution_sql_id=$(note_divs[n]).children().filter('.conversation_contribution').last().attr('id').substr(13);
                $.get('".CW_AJAX.$this->ajax_scriptname."?action=get_new_contributions&latest_loaded_contribution_sql_id='+latest_loaded_contribution_sql_id+'&n='+n,function(rtn){
                  var space_pos=rtn.indexOf(' ');
                  var content=rtn.substr(space_pos);
                  var n=rtn.substr(0,space_pos);
                  $(note_divs[n]).children().last().before(content);
                });
              }                                    
            }
          }
          
          function check_for_update_conversation_$id(){
            var last_contribution_ids='';
            //Get the sql_ids for the last contributions of all notes
            $('.conversation_note').each(function(){
              var note_no=$(this).attr('id').substr(5);
              var latest_loaded_contribution_sql_id=$(this).children().filter('.conversation_contribution').last().attr('id').substr(13);
              last_contribution_ids=last_contribution_ids+','+latest_loaded_contribution_sql_id;              
            });
            last_contribution_ids=last_contribution_ids.substr(1);
            $.get('".CW_AJAX.$this->ajax_scriptname."?action=check_for_conversation_update&conversation_id=$id&latest_loaded_contribution_sql_ids='+last_contribution_ids,function(rtn){
              if (rtn!='REQUEST_RELOAD'){
                var notes_to_update=rtn.split(','); //Comma separated list to array
                update_conversation_$id(notes_to_update);  
              } else {
                reload_conversation_$id();              
              }
            });            
          }
          
          //Is any of the input fields in the conversation in focus and being used?
          function focus_is_on_conversation(){
            if ($(document.activeElement).parents('.conversation_wrapper').length){
              return (($(document.activeElement).val()!='') && ($(document.activeElement).val()!='".CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT."'));
            }
            return false;
          }
          
          function reload_conversation_$id(){           
            if (focus_is_on_conversation()){            
              //User appears to be working on a note - skip reload this time
            } else {
              clearInterval(update_timer_$id);
              $('#conversation_$id').load('".CW_AJAX.$this->ajax_scriptname."?action=conversation_full_reload&conversation_id=$id');            
            }
          }          
          
          $('.delete_contribution_link').click(function(e){
            e.preventDefault();
            var contribution_id=$(this).attr('id').substr(4);
            $.get('".CW_AJAX.$this->ajax_scriptname."?action=conversation_delete_contribution&contribution_id='+contribution_id,function(rtn){
              if (rtn=='OK'){
                reload_conversation_$id() ;
              } else {
                alert(rtn);
              }
            });
          });
          
          var update_timer_$id=setInterval(check_for_update_conversation_$id,6000);                      
          
        </script>
      ";
      if (!$omit_wrapper){
        $result="<div class=\"conversation_wrapper\" id=\"conversation_$id\">".$t.$s."</div>";
      } else {
        $result=$t.$s;
      }
      return $result;
    }
    
    //Display note content in wrapper div
    function display_note($conversation_id,$note_no){
      $t="";
      $contributions=$this->conversations->get_contribution_ids_for_note($conversation_id,$note_no);
      if (is_array($contributions)){
        $i=0;
        foreach ($contributions as $v){
          $t.=$this->display_contribution($v,($i>0)); //if $i>0: responses, indent
          $i++;
        }
        //Produce answer input box
        $t.="<div class='conversation_response'><input class=\"response_input\" id=\"rn_$note_no\" type='text' value='".CW_CONVERSATIONS_DEFAULT_RESPONSE_TEXT."'/></div>";
      } else {
        $t="<span class='gray'>could not load note #$note_no for conversation #$conversation_id</span>";
      }
      return "<div id=\"note_$note_no\" class=\"conversation_note\">$t</div>";      
    }    
    
    //Display contribution content in wrapper div
    function display_contribution($id,$is_response=false){
      if ($r=$this->conversations->get_conversation_contributions_record($id)){
        $is_response ? $header_prefix="" : $header_prefix="note from ";
        if (($this->conversations->auth->csps==CW_A) || ($r["person_id"]==$this->conversations->auth->cuid)){
          //User can delete this
          $delete_link="[<a style='font-size:100%;' href='' class=\"delete_contribution_link\" id=\"ctb_".$r["id"]."\">x</a>]";
        }
        $t="<div class='conversation_contribution_header'>$delete_link $header_prefix".$this->auth->personal_records->get_name_first_last_initial($r["person_id"],true).", ".date("M j Y, H:i",$r["timestamp"])."</div>".$r["content"];
      } else {
        $t="<span class='gray'>could not load contribution #$id</span>";
      }
      $is_response ? $addclass="conversation_contribution_indent" : $addclass="";
      return "<div id=\"contribution_$id\" class=\"conversation_contribution $addclass\">$t</div>";
    }
  
    function display_new_contributions($latest_loaded_contribution_sql_id){
      if ($t=$this->conversations->check_for_new_contributions($latest_loaded_contribution_sql_id)){
        //Got array of conversation_contributions ids in $t
        $x="";
        foreach ($t as $v){
          $x.=$this->display_contribution($v,true);
        }         
        return $x;
      }
      return false;
    }

    
}

?>
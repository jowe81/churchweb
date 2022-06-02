<?php

  require_once "../lib/framework.php";

  //$help=new cw_Help($d);
  //$help->recreate_tables(true);
    
  
  if (!isset($_GET["action"]) || ($_GET["action"]=="")){
    $p->p("System test/development home");
    
    $p->p("
      <ul>
        <li><a href=\"?action=reset_coffee_orders_tables\">Reset coffee_orders tables</a></li>
      </ul>
    ");
    $p->p("
      <ul>
        <li><a href=\"?action=test_music_packages\">Test music_packages</a></li>
        <li><a href=\"?action=test_instruments_to_people\">Test mdb instruments_to_people</a></li>
      </ul>
    ");
    $p->p("
      <ul>
        <li><a href=\"?action=reset_help_system\">Reset help system</a></li>
      </ul>
    ");
    $p->p("
      <ul>
        <li><a href=\"?action=check_lyrics_integrity\">MdB: Check integrity of lyrics fragment numbers</a></li> 
      </ul>
    ");
    $p->p("
      <ul>
        <li><a href=\"?action=convert_arrangement_titles\">MdB: Convert arrangement titles to source titles</a></li> 
        <li><a href=\"?action=convert_lyrics_languages\">MdB: Convert music piece default languages and lyrics languages</a></li> 
        <li><a href=\"?action=init_system_preferences\">System Preferences: recreate tables</a></li> 
        <li><a href=\"?action=toggle_login_blockade\">System Preferences: toggle login blockade</a></li> 
        <li><a href=\"?action=read_blockade_status\">System Preferences: read login blockage status</a></li> 
      </ul>
    ");
  } elseif ($_GET["action"]=="reset_coffee_orders_tables"){
    $eh=new cw_Event_handling($a);
    $co=new cw_Coffee_orders($a,$eh);
    $p->p($co->recreate_tables());
  } elseif ($_GET["action"]=="test_music_packages"){
    $ep=new cw_Event_positions($d);
    /*$ep->assign_partfile_to_position_and_service_element(9999999,9999999,19);
    $p->p($ep->get_partfile_for_position_and_service_element(9999999,9999999));*/
    $p->p("Test retired");
  } elseif ($_GET["action"]=="test_instruments_to_people"){
    if ($_GET["method"]=="assign_instruments_to_person"){
      if ($mdb=new cw_Music_db($a)){
        if ($mdb->assign_instruments_to_person(array("100,101,102"),43,20)){
          $t.="success assign...";        
        } else {
          $t.="failure assign...";
        }
      }
      $t.=" DONE";    
    } elseif ($_GET["method"]=="unassign_instrument_from_person"){
      if ($mdb=new cw_Music_db($a)){
        if ($mdb->unassign_instrument_from_person(103,1,3)){
          $t.="success unassign...";        
        } else {
          $t.="failure unassign...";
        }
        if ($mdb->unassign_instrument_from_person(32,1,0)){
          $t.="success unassign...";        
        } else {
          $t.="failure unassign...";
        }
      }
      $t.=" DONE";    
    } else {
      $t="
        <p>
          <a href='?action=test_instruments_to_people&method=assign_instruments_to_person'>assign_instruments_to_person</a>
        </p>
        <p>
          <a href='?action=test_instruments_to_people&method=unassign_instrument_from_person'>unassign_instrument_from_person</a>
        </p>
      ";
    }
    $p->p($t);
  } elseif ($_GET["action"]=="reset_help_system"){
    $help=new cw_Help($d);
    if ($help->recreate_tables(true)){
      $t="Help system reset successfully";
    } else {
      $t="Help system reset failed";
    }  
    $p->p($t);
  } elseif ($_GET["action"]=="check_lyrics_integrity") {
    $p->p("<ul>");
    $p->li("Checking integrity of lyrics fragment numbers...");
    if ($mdb=new cw_Music_db($a)){
      $p->li("mdb object created");    
      $p->li("Obtaining table of music pieces...");
      $music_pieces=$d->get_table("music_pieces");
      foreach ($music_pieces as $v){
        $lyrics=$mdb->get_lyrics_records_for_music_piece($v["id"],0,0,null,true);
        if (is_array($lyrics)){
          $p->li("Obtained ".sizeof($lyrics)." lyrics fragment(s) for '".$v["title"]."'");
          //$lyrics are order by language, fragment_type, fragment_no
          $curr_ft=false;
          $corrupt=false;
          foreach ($lyrics as $w){
            if ($curr_ft===false){
              //First fragment
              $curr_ft=$w["fragment_type"];
              $curr_ln=$w["language"];            
              $last_fn=0;
            }
            if ($curr_ln==$w["language"]){
              if ($curr_ft==$w["fragment_type"]){
                //Same type, so number must be +1
                if ($last_fn!=$w["fragment_no"]-1){
                  //Corrupt sequence
                  $corrupt=true;
                  break;                
                }            
              } else {
                //Type changed
                $curr_ft=$w["fragment_type"];
                $last_fn=1;              
              }
              $last_fn=$w["fragment_no"];            
            } else {
              //Language changed
              $curr_ln=$w["language"];              
              $curr_ft=$w["fragment_type"];
              $last_fn=1;              
            }            
          }
          $p->p("<ul>");
          if ($corrupt){
            $p->li("<span style='color:red;'>Sequence corrupt!</span>");  
          } else {
            $p->li("<span style='color:green;'>Sequence ok</span>");            
          }
          $p->p("</ul>");
        }
      }
    }
    $p->p("</ul>");  
  } elseif ($_GET["action"]=="convert_arrangement_titles") {
    $p->p("<ul>");
    $p->li("Converting arrangement titles to source titles...");
    if ($mdb=new cw_Music_db($a)){
      $p->li("mdb object created");    
      $p->li("Obtaining table of arrangements...");
      $arrangements=$d->get_table("arrangements");
      foreach ($arrangements as $v){
        if ($v["title"]!=""){
          //Got a title. See if source id exists
          $source_id=$mdb->get_source_id($v["title"]);
          if ($source_id==0){
            //Source did not exist, create...
            $source_id=$mdb->add_source($v["title"]);
            if ($source_id>0){
              $p->li("Created new source with title ".$v["title"]." and ID $source_id");                
            } else {
              $p->li("<span class='red'>FAILED to create new source with title ".$v["title"]."</span>");                            
            }
          } else {
            $p->li("Source id: $source_id");                          
          }
          //Now we should have the source id          
          if ($source_id>0){
            $e=array();
            $e["source_id"]=$source_id;
            $mdb->update_arrangement_record($v["id"],$e);
            $p->li("Assigned arr #".$v["id"]." with title ".$v["title"]." to source id $source_id");          
          } else {
            $p->li("Assignment failed for arr #".$v["id"]);
          }
        }
      }
    } else {
      $p->li("Could not create mdb object - quitting...");
    }
    $p->p("</ul>");
  } elseif ($_GET["action"]=="convert_lyrics_languages") {
    $p->p("<ul>");
    $p->li("Converting music piece default languages and lyrics languages...");
    if ($mdb=new cw_Music_db($a)){
      $p->li("mdb object created"); 
      $p->li("Obtaining source IDs for German collections...");
      $s_titles=array("Gesangbuch","Jesu Name nie verklinget","Gemeindelieder","Neue Gemeindelieder","Ich will dir danken","Neues Singvögelein","Janz-Team: Feldzugschor singt");
      $s_ids=array();
      foreach ($s_titles as $w){
        $source_id=$mdb->get_source_id(substr($w,0,8),false);
        if ($source_id>0){
          $s_ids[]=$source_id;
          $p->li("Source ID for '$w' is $source_id");              
        } else {
          $p->li("Could not get source ID for '$w'");
        }
      }
      $p->li("Obtaining source IDs for English collections...");
      $se_titles=array("CCLI","Worship Together","TWS","The Hymnal","The Celebration Hymnal","Children","Christmas Eve","Faith's Collection");
      $se_ids=array();
      foreach ($se_titles as $w){
        $source_id=$mdb->get_source_id(substr($w,0,8),false);
        if ($source_id>0){
          $se_ids[]=$source_id;
          $p->li("Source ID for '$w' is $source_id");              
        } else {
          $p->li("Could not get source ID for '$w'");
        }
      }
      $p->li("Obtaining table of music_pieces...");
      $music_pieces=$d->get_table("music_pieces");
      $p->li(sizeof($music_pieces)." music pieces found");
      foreach ($music_pieces as $v){
        $p->li("Processing ".$v["title"]);
        $p->p("<ul>");
        $r=$mdb->get_arrangement_records_for_music_piece($v["id"]);
        if (true){
          $p->li($v["title"]." has ".sizeof($r)." arrangement(s)");
          $n=0;
          $m=0;
          foreach ($r as $arr_rec){
            if (in_array($arr_rec["source_id"],$s_ids)){
              $n++;
            }
            if (in_array($arr_rec["source_id"],$se_ids)){
              $m++;
            }
          }
          $p->li("Found $n arrangement(s) from German books, $m from English books");
          if (($n>0) && ($n>$m) && ($v["title"]!="His name is higher") && ($v["title"]!="His name is Wonderful") && ($v["title"]!="He is Lord") || ($v["title"]=="Viel tausend Kerzen") || ($v["title"]=="Wunder der Weihnachtszeit") || ($v["title"]=="Weihnachtspotpourri") || ($v["title"]=="Stille Nacht, heilige Nacht")|| (substr($v["title"],0,12)==substr("Das Licht aus der Höhe",0,12)) || (substr($v["title"],0,8)==substr("Doxology",0,8)) || (substr($v["title"],0,12)==substr("Der Friedensfürst",0,12))){
            $e=array();
            $e["default_language"]=$mdb->language_to_int("German");
            if ($mdb->update_music_piece($v["id"],$e)){
              $p->li("<b>assigning GERMAN (".$e["default_language"].") as default langauge for '".$v["title"]."'</b>");                      
            } else {
              $p->li("<b><span style='color:red;'>ERROR: could not update default langauge for '".$v["title"]."'</span></b>");                                  
            }
          } else {
            $e=array();
            $e["default_language"]=$mdb->language_to_int("English");
            if ($mdb->update_music_piece($v["id"],$e)){
              $p->li("<b><span style='color:green;'>assigning ENGLISH (".$e["default_language"].") as default langauge for '".$v["title"]."'</span></b>");                      
            } else {
              $p->li("<b><span style='color:red;'>ERROR: could not update default langauge for '".$v["title"]."'</span></b>");                                  
            }          
          }          
        }
        //Check if $v has lyrics in $v["default_language"]. If so, leave untouched. If not, convert ALL lyrics to default_language
        $lyrics=$mdb->get_lyrics_records_for_music_piece($v["id"],0,0,null,true);
        if (is_array($lyrics)){
          $p->li("Obtained ".sizeof($lyrics)." lyrics fragment(s) for '".$v["title"]."'");
          $found_default_lang=false;
          foreach ($lyrics as $y){
            if ($y["language"]==$e["default_language"]){
              $found_default_lang=true;
              break;
            }       
          }     
          if (sizeof($lyrics)>0){
            if ($found_default_lang){
              $p->li("Found lyrics fragment(s) in default langauge (".$mdb->int_to_language($e["default_language"]).")");               
            } else {
              $p->li("<span style='color:blue;font-weight:bold;'>No fragments in default language (".$mdb->int_to_language($e["default_language"]).") found. ".sizeof($lyrics)." fragments will be converted.");
              foreach ($lyrics as $y){
                $f=array();
                $f["language"]=$e["default_language"];
                if ($mdb->update_lyrics_record($y["id"],$f)){
                  $p->li("Conversion successful for lyrics #".$y["id"]);
                } else {
                  $p->li("<span style='color:red;'>Conversion failed for lyrics #".$y["id"]."</span>");                
                }
              }                             
            }  
          }
        } else {
          $p->li("<span style='color:red;font-weight:bold;'>ERROR: failed to obtain lyrics fragment(s) for '".$v["title"]."'</span>");        
        }
        $p->p("</ul>");        
      }
    } else {
      $p->li("Could not create mdb object - quitting...");
    }
    $p->p("</ul>");    
  } elseif ($_GET["action"]=="init_system_preferences") {
    $spref=new cw_System_preferences($a);
    if ($spref->recreate_tables()){
      $p->p("cw_System_preferences: recrate_tables: Success");
    } else {
      $p->p("cw_System_preferences: recrate_tables: FAILED");    
    }
  } elseif ($_GET["action"]=="toggle_login_blockade") {
    $spref=new cw_System_preferences($a);
    if ($spref->toggle_login_blockade()){ 
      ($spref->login_blocked()==1) ? $b="blocked" : $b="unblocked";     
      $p->p("OK. Login is now: $b");
    } else {
      ($spref->login_blocked()==1) ? $b="blocked" : $b="unblocked";     
      $p->p("FAILED. Login is: $b");
    }
  } elseif ($_GET["action"]=="read_blockade_status") {
    $spref=new cw_System_preferences($a);
    ($spref->login_blocked()==1) ? $b="blocked" : $b="unblocked";     
    $p->p("Login is: $b");
  }


    
  
?>
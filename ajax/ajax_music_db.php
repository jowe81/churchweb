<?php

  require_once "../lib/framework.php";

  $mdb=new cw_Music_db($a);
  $dmdb=new cw_Display_music_db($mdb);  
  
  if ($_GET["action"]=="acomp_songs"){
    //Get suggestions for autocomplete (via $_GET["term"])
    //format: "[ { \"id\": \"Platalea leucorodia\", \"label\": \"action:".$_GET["action"].", term:".$_GET["term"]."\"} ]";
    echo $mdb->get_songsearch_autocomplete_suggestions($_GET["term"],$_GET["field"]);
  } elseif ($_GET["action"]=="acomp_writers"){
    echo $mdb->get_writersearch_autocomplete_suggestions($_GET["term"]);
  } elseif ($_GET["action"]=="acomp_copyright_holders"){
    echo $mdb->get_copyright_holders_autocomplete_suggestions($_GET["term"]);
  } elseif ($_GET["action"]=="acomp_themes"){
    echo $mdb->get_themes_autocomplete_suggestions($_GET["term"]); 
  } elseif ($_GET["action"]=="acomp_style_tags"){
    echo $mdb->get_style_tags_autocomplete_suggestions($_GET["term"]); 
  } elseif ($_GET["action"]=="acomp_arr_titles"){
    echo $mdb->get_arrangement_titles_autocomplete_suggestions($_GET["term"]); 
  } elseif ($_GET["action"]=="acomp_source_titles"){
    echo $mdb->get_source_titles_autocomplete_suggestions($_GET["term"]); 
  } elseif ($_GET["action"]=="acomp_parts"){
    echo $mdb->get_parts_autocomplete_suggestions($_GET["term"]); 
  } elseif ($_GET["action"]=="get_music_pieces_size"){
    echo "one of ".$mdb->get_size_of_music_pieces();
  } elseif ($_GET["action"]=="save_new_piece"){
    if ($a->csps>=CW_A){      
      //Create new music_piece record and then select the piece for editing
      if ($new_id=$mdb->add_music_piece($_POST["title"],$a->cuid)){
        echo $new_id;
      } else {
        echo "Could not add music piece";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_music_piece_id_by_title"){
    $pieces=$mdb->get_music_piece_records_by_title($_POST["title"]);
    if (sizeof($pieces)>0){
      echo $pieces[0]["id"];
    } else {
      echo "No music piece or song found";
    }
  } elseif ($_GET["action"]=="get_songlist"){
    echo $dmdb->display_songlist($_GET["term"],$_GET["field"],$_GET["page"]);
  } elseif ($_GET["action"]=="get_music_piece_title_by_id"){
    if ($r=$mdb->get_music_piece_record($_POST["music_piece_id"])){
      echo $r["title"]; 
    }
  } elseif ($_GET["action"]=="get_edit_music_piece_interface"){
    if ($a->csps>=CW_V){
      //This dialogue is available to viewers - but not all its functions      
      if ($_GET["id"]>0){
        echo $dmdb->display_edit_music_piece_interface($_GET["id"]);
      } else {
        echo "Cannot load interface: invalid music_piece ID";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_title_header"){
    echo $mdb->get_music_piece_title_info($_GET["music_piece_id"]);
  } elseif ($_GET["action"]=="save_music_piece_record"){
    if ($a->csps>=CW_A){
      //Update a field in the music piece record 
      $e=array();
      $e[$_POST["field"]]=$_POST["value"]; 
      if ($mdb->update_music_piece($_POST["id"],$e,$a->cuid)){
        echo "OK";
      } else {
        echo "Error while updating music piece record: field ('".$_POST["field"]."'), value('".$_POST["value"]."')";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_writers_to_music_pieces_record"){
    if ($a->csps>=CW_E){
      //Save composer(s), lyricist(s) or translator(s)
      //$_POST has music_piece_id,writer_capacity (as string),and value (from autocomplete: [substr(first_name,0,1). last_name (#id),])
      $music_piece_id=$_POST["music_pieces_id"];
      $writer_capacity_id=$mdb->get_writer_capacities_id($_POST["writer_capacity"]);
      $error=false;
      if (($music_piece_id>0) && ($writer_capacity_id>0)){
        //Delete existing associations between the piece and the writer/capacity from db
        $mdb->unassign_all_writers_from_music_piece($music_piece_id,$writer_capacity_id);      
        //Split value by , then find id
        $parts=explode(',',$_POST["value"]);
        foreach ($parts as $v){
          if (trim($v)!=""){
            //Only look at this substring if it's not empty
            $has_id=(!(strpos($v,'#')===false)); //If the # doesn't exist, we don't have a writer ID
            if ($has_id){
              $x=substr($v,strpos($v,'#')+1); //Copy from after the hash until the closing bracket
              $writer_id=substr($x,0,strpos($x,')'));
              if ($writer_id>0){
                //Now we have all data to create writer_to_music_pieces record
                $new_error=($mdb->assign_writer_to_music_piece($music_piece_id,$writer_id,$writer_capacity_id)===false);
                if (!$error){
                  //Only if there was no previous error we'll register this result (avoid overwriting a previous error)
                  $error=$new_error;
                }
              } else {
                $error=true; //ID zero or undefined
              }
            } else {
              //No #, so no ID. But if we have at least two words (i.e. at least a space exists) we will try to add the writer to the database
              $words=explode(' ',$v);
              if (sizeof($words)>=2){
                if (all_elements_alpha($words)){ //in utilities_misc
                  //Last name is the last element
                  $last_name=array_pop($words);
                  //First name are the previous elements together
                  $first_name="";
                  foreach ($words as $v2){
                    $first_name.=" ".$v2;                
                  }
                  //Cut first space
                  $first_name=trim($first_name);
                  //Have name of new writer now (actually if this writer previously existed, we have their ID, too - returned from add_writer)
                  $writer_id=$mdb->add_writer($last_name,$first_name);
                  //Add association in question
                  $error=($mdb->assign_writer_to_music_piece($music_piece_id,$writer_id,$writer_capacity_id)===false);
                }
              } else {
                $error=true; //ID not found, and not sufficient information to create new writer record
              }          
            }
          }
        }
      } else {
        $error=true; //Problem with music_piece or writer cap id
      }
      if ($error){
        echo "An error occurred while trying to save writer information.";
      } else {
        echo "OK";
      }        
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_copyright_holders"){
    if ($a->csps>=CW_E){
      //$_POST has music_piece_id, and value (from autocomplete: [title (#id),])
      $music_piece_id=$_POST["music_pieces_id"];
      $error=false;
      if ($music_piece_id>0){
        //Delete existing associations between the piece and copyright holders from db
        $mdb->unassign_all_copyright_holders_from_music_piece($music_piece_id);      
        //Split value by , then find id
        $parts=explode(',',$_POST["value"]);
        foreach ($parts as $v){
          if (trim($v)!=""){
            //Only look at this substring if it's not empty
            $has_id=(!(strpos($v,'#')===false)); //If the # doesn't exist, we don't have a c-holder ID
            if ($has_id){
              $x=substr($v,strpos($v,'#')+1); //Copy from after the hash until the closing bracket
              $cholder_id=substr($x,0,strpos($x,')'));
              if ($cholder_id>0){
                //Now we have all data to create copyright_holders to music_pieces record
                $new_error=($mdb->assign_copyright_holder_to_music_piece($cholder_id,$music_piece_id)===false);
                if (!$error){
                  //Only if there was no previous error we'll register this result (avoid overwriting a previous error)
                  $error=$new_error;
                }
              } else {
                $error=true; //ID zero or undefined
              }
            } else {
              //No #, so no ID. Try to add the copyright holder to the database, as is
              $cholder_title=trim($v);
              $cholder_id=$mdb->add_copyright_holder($cholder_title);
              //Add association in question
              $error=($mdb->assign_copyright_holder_to_music_piece($cholder_id,$music_piece_id)===false);
            }
          }
        }    
      } else {
        $error=true; //Problem with music_piece id
      }
      if ($error){
        echo "An error occurred while trying to save copyright holder information.";
      } else {
        echo "OK";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }               
  } elseif ($_GET["action"]=="get_lyrics_fragments"){
    $fragments=$dmdb->get_lyrics_fragments($_GET["music_piece"]);
    if ($fragments!==false){
      echo $fragments;
    } else { 
      echo "Could not load lyrics fragments for music piece #".$_GET["music_piece"];
    }      
  } elseif ($_GET["action"]=="get_lyrics_fragment_types_for_select"){
    echo $dmdb->get_lyrics_fragment_types_for_select();
  } elseif ($_GET["action"]=="add_lyrics_fragment"){
    if ($a->csps>=CW_A){
      //music_piece is in GET, fragment_type_id is in POST, also content
      //$_POST["language"] has to be lowered by one, 0 means default, 1 English, 2 German etc.
      if ($_POST["language"]==0){
        //User has selected "default", replace with whatever value the default language for the piece has
        $_POST["language"]=$mdb->get_default_language_for_music_piece($_GET["music_piece"]);
      } else {
        //User has selected a specific language
        $_POST["language"]--;
      }
      if ($mdb->add_lyrics($_POST["content"],$_GET["music_piece"],$_POST["fragment_type_id"],$_POST["language"])){
        echo "OK";
      } else {
        echo "Could not save lyrics";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_lyrics_fragment"){
    if ($a->csps>=CW_A){
      $lyrics_id=substr($_GET["lyrics_id"],4);//cutoff leading del_ 
      if ($mdb->delete_lyrics($lyrics_id)){
        echo "OK";
      } else {
        echo "Could not delete lyrics fragment. It is probably in use by one or more arrangements.";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_edit_lyrics_fragment_interface"){
    if ($a->csps>=CW_A){
      $lyrics_id=substr($_GET["lyrics_id"],4);//cutoff leading edt_
      echo $dmdb->get_edit_lyrics_fragment_interface($lyrics_id,$_GET["music_piece"]); //music piece id passed along because of default language
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_edited_lyrics_fragment"){
    if ($a->csps>=CW_A){
      if ($mdb->update_lyrics_record_content($_POST["lyrics_id"],$_POST["content"])){
        echo "OK";
      } else {
        echo "Error: could not update lyrics fragment";
      }     
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_scripture_refs"){
    //Return the options for the scripture_ref select
    $options=$dmdb->get_scripture_refs_for_select($_GET["music_piece_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load references</option>";
    }    
  } elseif ($_GET["action"]=="delete_scripture_ref"){
    if ($a->csps>=CW_E){
      //Remove one scripture ref from a song
      if ($_POST["scripture_ref_id"]>0){
        if ($mdb->scripture_handling->scripture_refs->unassign_scripture_ref_from_music_piece($_POST["scripture_ref_id"])){
          echo "OK";
        } else {
          echo "Could not delete scripture reference";
        }
      } else {
        echo "To delete a single scripture reference, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_all_scripture_refs"){
    if ($a->csps>=CW_A){
      //Remove all scripture refs from the song
      if ($mdb->scripture_handling->scripture_refs->delete_scripture_ref_records_for_music_piece($_POST["music_piece_id"])){
        echo "OK";
      } else {
        echo "Could not delete scripture references";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="add_scripture_ref"){
    if ($a->csps>=CW_E){
      //Add a new scripture ref to the song
      //Try to identify the scripture reference/range
      $refs=$mdb->scripture_handling->scripture_refs->identify_multi_range($_POST["val"]);
      if (is_array($refs)){
        //Success
        if ($mdb->scripture_handling->assign_scripture_refs_to_music_piece($refs,$_POST["music_piece_id"])){
          echo "OK";
        } else {
          echo "An error occurred while trying to a add the scripture reference";
        }
      } else {
        echo "Could not identify Scripture reference. Make sure to use proper formatting.";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="assign_existing_theme"){
    if ($a->csps>=CW_E){
      //Assign a theme to a song via id
      if ($mdb->assign_theme_to_music_piece($_POST["theme_id"],$_POST["music_piece_id"])){
        echo "OK";
      } else {
        echo "Error while trying to add theme to song";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="add_new_theme"){
    if ($a->csps>=CW_A){
      //Add a new theme
      $new_id=$mdb->add_theme($_POST["val"]);
      if ($new_id>0){
        if ($mdb->assign_theme_to_music_piece($new_id,$_POST["music_piece_id"])){
          echo "OK";
        } else {
          echo "An error occurred while trying to assing the new theme to the song";
        }
      } else {
        echo "An error occurred while trying to add the theme to the database";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_themes"){
    //Return select options with the themes attached to the song
    $options=$dmdb->get_themes_for_select($_GET["music_piece_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load themes</option>";
    }       
  } elseif ($_GET["action"]=="delete_theme"){
    if ($a->csps>=CW_E){
      //Remove one theme from a song
      if ($_POST["theme_id"]>0){
        if ($mdb->unassign_theme_from_music_piece($_POST["theme_id"],$_POST["music_piece_id"])){
          echo "OK";
        } else {
          echo "Could not delete theme";
        }
      } else {
        echo "To delete a single theme, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_all_themes"){
    if ($a->csps>=CW_A){
      //Remove all themes from the song
      if ($mdb->unassign_all_themes_from_music_piece($_POST["music_piece_id"])){
        echo "OK";
      } else {
        echo "Could not delete themes";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_arrangements"){
    $arrs=$dmdb->get_arrangements($_GET["music_piece"],$_GET["show_retired"]);
    if ($arrs!==false){
      echo $arrs;
    } else { 
      echo "Could not load arrangements for music piece #".$_GET["music_piece"];
    }      
  } elseif ($_GET["action"]=="add_arrangement"){
    if ($a->csps>=CW_E){
      $new_id=$mdb->add_arrangement($_GET["music_piece_id"],$a->cuid);
      if ($new_id>0){
        echo $new_id;
      } else {
        echo "Could not create arrangement";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="toggle_retire_arrangement"){
    if ($a->csps>=CW_A){
      //It could be that the actual ID is preceded by the string "ret_"
      (substr($_POST["arrangement_id"],0,4)=="ret_") ? $arr_id=substr($_POST["arrangement_id"],4) : $arr_id=$_POST["arrangement_id"];
      if ($mdb->toggle_retire_arrangement($arr_id)){
        echo "OK";
      } else {
        echo "A problem occurred while trying to retire this arrangement";
      }  
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_arrangement"){
    if ($a->csps>=CW_A){
      //It could be that the actual ID is preceded by the string "del_"
      (substr($_POST["arrangement_id"],0,4)=="del_") ? $arr_id=substr($_POST["arrangement_id"],4) : $arr_id=$_POST["arrangement_id"];
      if ($mdb->delete_arrangement($arr_id)){
        echo "OK";
      } else {
        echo "A problem occurred while trying to delete this arrangement";
      }  
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    } 
  } elseif ($_GET["action"]=="get_bg_image_id"){
  	if (!empty($_GET["music_piece"])){
  		$bg=$mdb->get_music_piece_background($_GET["music_piece"]);
  		if ($bg!==false){
  			echo $bg;
  		} else {
  			echo "ERR";
  		}	
  	} elseif (!empty($_GET["arrangement"])){
  		
  	} else {
  		echo "ERR";
  	}
  } elseif ($_GET["action"]=='unassign_background_image_from_music_piece'){
  	if ($a->csps>=CW_E){
  		if (!$mdb->unassign_background_from_music_piece($_GET["music_piece"])){
  			echo "An error occurred while trying to remove the background image";
  		}
  	} else {
  		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
  	}  		 
  } elseif ($_GET["action"]=="get_downscaled_projector_preview"){
	//$_GET["max_height"] and width, $_GET["lib_id"]
	$mb=new cw_Mediabase($a);
	$preview=$mb->get_downscaled_projector_image($_GET["lib_id"], $_GET["max_width"], $_GET["max_height"]);
	if ($preview!=null){
		header('Content-Type: image/jpeg');
		imagejpeg($preview);
	}
  } elseif ($_GET["action"]=="get_edit_arrangement_interface"){
    if ($a->csps>=CW_E){    
      //It could be that the actual ID is preceded by the string "edit"
      (substr($_GET["arrangement_id"],0,4)=="edit") ? $arr_id=substr($_GET["arrangement_id"],4) : $arr_id=$_GET["arrangement_id"];
      echo $dmdb->display_edit_arrangement_interface($arr_id);
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_writers_to_arrangements_record"){
    if ($a->csps>=CW_E){
      //Save arranger(s)
      //$_POST has arrangement_id and value (from autocomplete: [substr(first_name,0,1). last_name (#id),])
      $arrangement_id=$_POST["arrangement_id"];
      $writer_capacity_id=$mdb->get_writer_capacities_id(CW_MUSICDB_WRITER_CAPACITY_ARRANGER);
      $error=false;
      if (($arrangement_id>0) && ($writer_capacity_id>0)){
        //Delete existing associations between the piece and the writer/capacity from db
        $mdb->unassign_all_writers_from_arrangement($arrangement_id,$writer_capacity_id);      
        //Split value by , then find id
        $parts=explode(',',$_POST["value"]);
        foreach ($parts as $v){
          if (trim($v)!=""){
            //Only look at this substring if it's not empty
            $has_id=(!(strpos($v,'#')===false)); //If the # doesn't exist, we don't have a writer ID
            if ($has_id){
              $x=substr($v,strpos($v,'#')+1); //Copy from after the hash until the closing bracket
              $writer_id=substr($x,0,strpos($x,')'));
              if ($writer_id>0){
                //Now we have all data to create writer_to_music_pieces record
                $new_error=($mdb->assign_writer_to_arrangement($arrangement_id,$writer_id,$writer_capacity_id)===false);
                if (!$error){
                  //Only if there was no previous error we'll register this result (avoid overwriting a previous error)
                  $error=$new_error;
                }
              } else {
                $error=true; //ID zero or undefined
              }
            } else {
              //No #, so no ID. But if we have at least two words (i.e. at least a space exists) we will try to add the writer to the database
              $words=explode(' ',$v);
              if (sizeof($words)>=2){
                if (all_elements_alpha($words)){ //in utilities_misc
                  //Last name is the last element
                  $last_name=array_pop($words);
                  //First name are the previous elements together
                  $first_name="";
                  foreach ($words as $v2){
                    $first_name.=" ".$v2;                
                  }
                  //Cut first space
                  $first_name=trim($first_name);
                  //Have name of new writer now (actually if this writer previously existed, we have their ID, too - returned from add_writer)
                  $writer_id=$mdb->add_writer($last_name,$first_name);
                  //Add association in question
                  $error=($mdb->assign_writer_to_arrangement($arrangement_id,$writer_id,$writer_capacity_id)===false);
                }
              } else {
                $error=true; //ID not found, and not sufficient information to create new writer record
              }          
            }
          }
        }
      } else {
        $error=true; //Problem with music_piece or writer cap id
      }
      if ($error){
        echo "An error occurred while trying to save writer information.";
      } else {
        echo "OK";
      }       
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_arrangement_source_title"){
    if ($a->csps>=CW_E){
      //Got $_POST["arrangement_id"] and $_POST["value"] with the title of the source/collection
      if ($_POST["value"]!=""){
        //Try to get the source_id for the given title. If non extant, create new.
        $source_id=$mdb->get_source_id($_POST["value"]);
        if ($source_id==0){
          //No source_id. Create new source.
          $source_id=$mdb->add_source($_POST["value"]);      
        }
        if ($source_id>0){
          $e=array();
          $e["source_id"]=$source_id;
          if ($mdb->update_arrangement_record($_POST["arrangement_id"],$e)){
            echo "OK";
          } else {
            echo "Could not update source identity";
          }
        } else {
          echo "Could not save new source information";
        }          
      } else {
        //No title given, so clear the field
        $e=array();
        $e["source_id"]=null;
        if ($mdb->update_arrangement_record($_POST["arrangement_id"],$e)){
          echo "OK";
        } else {
          echo "Could not clear source field";
        }
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="save_arrangement_source_index"){
    if ($a->csps>=CW_E){
      //Got $_POST["arrangement_id"] and $_POST["value"] with the index/song number 
      $e=array();
      $e["source_index"]=$_POST["value"];
      if ($mdb->update_arrangement_record($_POST["arrangement_id"],$e)){
        echo "OK";
      } else {
        echo "Could not update index/song number";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="update_arrangements_record"){
    if ($a->csps>=CW_E){
      $e=array();
      foreach ($_POST as $k=>$v){
        $e[$k]=$v;
      } 
      if ($mdb->update_arrangement_record($_GET["arrangement_id"],$e)){
        echo "OK";
      } else {
        echo "Could not update arrangement information";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="assign_existing_style_tag"){
    if ($a->csps>=CW_E){
      //Assign a style_tag to an arrangement via id
      if ($mdb->assign_style_tag_to_arrangement($_POST["style_tag_id"],$_POST["arrangement_id"])){
        echo "OK";
      } else {
        echo "Error while trying to add style tag to arrangement";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="add_new_style_tag"){
    if ($a->csps>=CW_A){
      //Add a new style_tag
      $new_id=$mdb->add_style_tag($_POST["val"]);
      if ($new_id>0){
        if ($mdb->assign_style_tag_to_arrangement($new_id,$_POST["arrangement_id"])){
          echo "OK";
        } else {
          echo "An error occurred while trying to assign the new style tag to the arrangement";
        }
      } else {
        echo "An error occurred while trying to add the style tag to the database";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_style_tags"){
    //Return select options with the style tags attached to the arr
    $options=$dmdb->get_style_tags_for_select($_GET["arrangement_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load style tags</option>";
    }       
  } elseif ($_GET["action"]=="delete_style_tag"){
    if ($a->csps>=CW_E){
      //Remove one style tag from an arr
      if ($_POST["style_tag_id"]>0){
        if ($mdb->unassign_style_tag_from_arrangement($_POST["style_tag_id"],$_POST["arrangement_id"])){
          echo "OK";
        } else {
          echo "Could not delete style tag";
        }
      } else {
        echo "To delete a single style tag, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_all_style_tags"){
    if ($a->csps>=CW_E){
      //Remove all style tags from the arrangement
      if ($mdb->unassign_all_style_tags_from_arrangement($_POST["arrangement_id"])){
        echo "OK";
      } else {
        echo "Could not delete style tags";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="upload_arr_file"){
    if ($a->csps>=CW_E){
      //$_POST has arrangement_id, and instruments (the string from the input, comma-separated instrument titles)
      $result=$mdb->add_part_file($_POST["arrangement_id"],$_POST["instruments"],$_FILES["filesel"]["tmp_name"],$_FILES["filesel"]["name"]);
      if ($result===true){    
        /*echo "
          <script type='text/javascript'>
            alert('Success');
          </script>      
        ";*/
      } else {
        //Failure: send alert through iframe
        echo "
          <script type='text/javascript'>
            alert('Could not store parts file ($result)');
          </script>      
        ";
      }
    } else {
      //Failure: send alert through iframe
      echo "
        <script type='text/javascript'>
          alert('".CW_ERR_INSUFFICIENT_PRIVILEGES."');
        </script>      
      ";
    }            
  } elseif ($_GET["action"]=="get_parts"){
    //Return select options with the parts attached to the arr
    $options=$dmdb->get_parts_for_select($_GET["arrangement_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load parts</option>";
    }       
  } elseif ($_GET["action"]=="delete_part"){
    if ($a->csps>=CW_E){
      //Remove one part from an arr. We get file_id (there is no separate parts table)
      if ($_POST["file_id"]>0){
        if ($mdb->delete_part_file($_POST["file_id"],$_POST["arrangement_id"])){
          echo "OK";
        } else {
          echo "Could not delete part";
        }
      } else {
        echo "To delete a part, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="upload_other_file"){
    if ($a->csps>=CW_E){
      //$_POST has arrangement_id
      $result=$mdb->add_other_file($_POST["arrangement_id"],$_FILES["filesel_other"]["tmp_name"],$_FILES["filesel_other"]["name"]);
      if ($result===true){    
        /*echo "
          <script type='text/javascript'>
            alert('Success');
          </script>      
        ";*/
      } else {
        //Failure: send alert through iframe
        echo "
          <script type='text/javascript'>
            alert('Could not store file ($result)');
          </script>      
        ";
      }
    } else {
      //Failure: send alert through iframe
      echo "
        <script type='text/javascript'>
          alert('".CW_ERR_INSUFFICIENT_PRIVILEGES."');
        </script>      
      ";
    }            
  } elseif ($_GET["action"]=="get_other_files"){
    //Return select options with the other files (not parts) attached to the arr
    $options=$dmdb->get_other_files_for_select($_GET["arrangement_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load files</option>";
    }       
  } elseif ($_GET["action"]=="delete_other_file"){
    if ($a->csps>=CW_E){
      //Remove one file from an arr. We get file_id (there is no separate parts table)
      if ($_POST["file_id"]>0){
        if ($mdb->delete_other_file($_POST["file_id"],$_POST["arrangement_id"])){
          echo "OK";
        } else {
          echo "Could not delete file";
        }
      } else {
        echo "To delete a file, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_lyrics_pdf_for_arrangement"){
    $result=$mdb->get_lyrics_pdf_for_arrangement(substr($_POST["arrangement_id"],4));
    if ($result>0){
      echo $result;
    } else {
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_zip_for_arrangement"){
    //Expects argument in $_GET["zip_type"]
    //Get details for name of zipfile    
    $arr_id=substr($_POST["arrangement_id"],4);
    if ($arr_id>0){
      $arr_rec=$mdb->get_arrangement_record($arr_id);
      $piece_rec=$mdb->get_music_piece_record($arr_rec["music_piece"]);
      empty($arr_rec["title"]) ? $arr_info="(arr#".$arr_rec["id"].")" : $arr_info="(".$arr_rec["title"].")";
      //Put filename together
      if ($_GET["zip_type"]=="parts"){
        $filename="parts for ".$piece_rec["title"]." $arr_info";    
      } elseif ($_GET["zip_type"]=="other_files"){
        $filename="other files for ".$piece_rec["title"]." $arr_info";    
      } elseif ($_GET["zip_type"]=="everything"){
        $filename="complete arrangement of ".$piece_rec["title"]." $arr_info";    
      }
      //Make sure to pick a filename that doesn't exist yet
      $addendum="";
      while(file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename.$addendum.".zip")){
        $addendum=" ".create_sessionid(3);     
      }
      $local_name=$filename.".zip"; //Save the filename without the addendum so that it doesn't get passed back on download
      $filename=$filename.$addendum.".zip"; //phsyical filename      
      //Try to get list of file-ids
      $file_ids=array();
      if (($_GET["zip_type"]=="parts") || ($_GET["zip_type"]=="everything")){
        //Get file-ids for parts
        $parts=$mdb->get_parts_for_arrangement($arr_id);
        if ((is_array($parts)) && (sizeOf($parts>0))){
          foreach ($parts as $v){
            $file_ids[]=$v["file_id"];      
          }
        }
      }
      if (($_GET["zip_type"]=="other_files") || ($_GET["zip_type"]=="everything")){
        //Get file-ids for other files
        $other_files=$mdb->get_other_files_for_arrangement($arr_id);
        if ((is_array($other_files)) && (sizeOf($other_files>0))){
          foreach ($other_files as $v){
            $file_ids[]=$v["id"];      
          }
        }    
      }
      if ($_GET["zip_type"]=="everything"){
        //Make and get file-id for lyrics file
        $lyrics_file_id=$mdb->get_lyrics_pdf_for_arrangement($arr_id);
        if ($lyrics_file_id>0){
          $file_ids[]=$lyrics_file_id;
        }
      }
      //Did we get a list of file-ids?
      if (sizeOf($file_ids)>0){
        $f=new cw_Files($d);
        $result=$f->make_zip_file($file_ids,$filename,$local_name);
        if ($result!==false){
          //Have file_id for archive in $result
          echo $result;
        } else {
          //Making the file failed
          echo "ERR";
        }
      } else {
        //Obtaining the file_ids failed
        echo "ERR";
      }
    } else {
      //no arr-id
      echo "ERR";
    }
  } elseif ($_GET["action"]=="get_lyrics_sequence_for_sortable"){
    $sequence_elements="";
    //Get fragment types:
    $types=$mdb->get_fragment_types_records();
    $lyrics_ids=$mdb->get_lyrics_csl_for_arrangement($_GET["arrangement_id"]);
    $seq_recs=$mdb->get_lyrics_records_from_csl($lyrics_ids);
    if (sizeof($seq_recs)>0){
      $cnt=0;
      foreach ($seq_recs as $v){
        $cnt++; //This counter just for li-ids, no functionality here other than js-highlighting on shift and hover
        if ($v["fragment_type"]>0){
          //Not a blank slide
          (($types[$v["fragment_type"]-1]["title"]=="verse") || ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";     
          ($v["language"]!=$mdb->get_default_language_for_music_piece($_GET["music_piece"])) ? $language=$mdb->int_to_language($v["language"]) : $language="";
          (!empty($language)) ? $language=" <span class='gray'>".($language)."</span>" : null;
          $title=$types[$v["fragment_type"]-1]["title"].$fno.$language;
          $content=replace_linebreak(replace_double_linebreak($v["content"]," <span class='gray'>|</span> ")," <span class='gray'>/</span> ");//extract_full_words($v["content"])."...";
          $background="";                         
        } else {
          $title="<span style='color:gray;'>blank slide</span>";
          $content="";   
          $background="background:#EAEAEA";                      
        }
        $sequence_elements.="
          <li id='x$cnt' class='actual_element'>
            <div style='padding:0px;$background'>
              <div style='width:60px;display:inline-block;vertical-align:middle;'>
                $title
              </div>
              <div class='data' style='width:205px;display:inline-block;vertical-align:middle;'>
                $content
              </div>
            </div>
          </li>";
      }
      $sequence_elements.="
        <script type='text/javascript'>
          //Shift click on an element ->delete
          $('.actual_element').click(function(e){
            if (!$(this).data('noclick')){
              if (e.shiftKey){
                delete_lyrics_from_sequence($(this).attr('id'));                                  
              }               
            } else {
              $(this).data('noclick',false);
            }
          });
          
          //On hover, save which element is hovered above
          $('.actual_element').hover(
            function(){                     
              if (!$('#fragment_selector').data('dragging')){
                $('#sortable').data('hover',$(this).attr('id'));                
              }
            },
            function(){
              $('#sortable').css('hover','');
              $(this).css('background',''); //Unmark on leave
              $('#sortable').data('hover',''); //Mark the element as not hovering anymore      
            }            
          );
          
          $(document).keyup(function(e){
            //If modal is visible, close on esc
            if ($('#modal').is(':visible')){
              //Shift key let go - unmark element
              var el=$('#sortable').data('hover');
              if (el){
                if (e.keyCode==16){
                  $('#'+el).css('background','');            
                }
              }        
            }
          });
                    
        </script>
      ";      
    } else {
      $sequence_elements="<li id='placeholder'><div>drop lyrics fragments here</div></li>"; 
    }
    echo $sequence_elements;        
  } elseif ($_GET["action"]=="apply_default_sequence"){
    if ($a->csps>=CW_E){
      if ($mdb->apply_default_lyrics_sequence_to_arrangement($_POST["arrangement_id"],$_POST["music_piece"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to apply the default lyrics sequence to this arrangement.";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="clear_sequence"){
    if ($a->csps>=CW_E){
      if ($mdb->unassign_all_lyrics_from_arrangement($_POST["arrangement_id"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to clear the lyrics sequence for this arrangement.";
      }    
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="add_fragment_to_sequence"){
    if ($a->csps>=CW_E){
      $lyrics_id=substr($_POST["lyrics"],4);
      if ($mdb->assign_lyrics_to_arrangement($_POST["arrangement_id"],$lyrics_id,$_POST["sequence_no"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to add a fragment into the lyrics sequence";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_fragment_from_sequence"){
    if ($a->csps>=CW_E){
      $lta_id=substr($_POST["lta_id"],4);
      if ($mdb->unassign_lyrics_from_arrangement($_POST["arrangement_id"],substr($_POST["sequence_no"],1))){
        echo "OK";
      } else {
        echo "Could not remove lyrics fragment from arrangement";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="repos_fragment_in_sequence"){
    if ($a->csps>=CW_E){
      if ($mdb->reposition_lyrics_in_arrangement($_POST["arrangement_id"],substr($_POST["old_pos"],1),$_POST["new_pos"])){
        echo "OK";
      } else {
        echo "Could not reposition the lyrics fragment";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="get_keychanges"){
    //Return select options with the keychanges attached to the arr
    $options=$dmdb->get_keychanges_for_select($_GET["arrangement_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load keychanges</option>";
    }           
  } elseif ($_GET["action"]=="add_keychange"){
    if ($a->csps>=CW_E){
      if ($mdb->assign_keychange_to_arrangement($_POST["musical_key"],$_GET["arrangement_id"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to add a keychange";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="clear_keychanges"){
    if ($a->csps>=CW_E){
      if ($mdb->unassign_all_keychanges_from_arrangement($_GET["arrangement_id"])){
        echo "OK";
      } else {
        echo "An error occurred while trying to delete the keychanges";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } elseif ($_GET["action"]=="delete_music_piece"){
    if ($a->csps>=CW_A){    
      if ($mdb->delete_music_piece($_POST["music_piece_id"])){
        echo "OK";
      } else {
        echo "An error occurred while deleting the music piece and/or associated records";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
  } else {
    echo "INVALID REQUEST";
  }
   
      
  $p->nodisplay=true;
?>
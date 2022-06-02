<?php

require_once "../lib/framework.php";

$mb=new cw_Mediabase($a);
$dmb=new cw_Display_mediabase($mb);

if ($_GET["action"]=="upload_resource"){
	$type="";
	$rs="";
	if ((($_FILES["filesel"]["type"] == "image/gif")
			|| ($_FILES["filesel"]["type"] == "image/jpeg")
			|| ($_FILES["filesel"]["type"] == "image/jpg")
			|| ($_FILES["filesel"]["type"] == "image/pjpeg")
			|| ($_FILES["filesel"]["type"] == "image/x-png")
			|| ($_FILES["filesel"]["type"] == "image/png"))){
		$type="image";
	} else {
		//$type="video";
		//var_dump($_FILES);
	}
	if ($type!=""){
		$lib_id=$mb->add_media($_FILES["filesel"]["tmp_name"], $type, $_FILES["filesel"]["name"],$_FILES["filesel"]["name"]);
		if ($lib_id>0){
			//Successfully uploaded/stored
			//$rs="Thank you for uploading this $type";
		} else {
			$rs="An error occurred while trying to add this resource to the mediabase";
		}
	} else {
		$rs="Could not store this resource: unsupported file type";
	}
	if (!empty($rs)){
		//Alert error
		echo "<script>alert('$rs');</script>";		
	} else {
		//Success: simply return lib_id of new resource
		echo "<div id=\"lib_id\">$lib_id</div>";
	}
} elseif ($_GET["action"]=="get_downscaled_projector_preview"){
	//$_GET["max_height"] and width, $_GET["lib_id"]
	$preview=$mb->get_downscaled_projector_image($_GET["lib_id"], $_GET["max_width"], $_GET["max_height"]);
	if ($preview!=null){
		header('Content-Type: image/jpeg');
		imagejpeg($preview);
	}
} elseif ($_GET["action"]=="get_downscaled_original_preview"){
	//$_GET["max_height"] and width, $_GET["lib_id"]
	$preview=$mb->get_downscaled_original_image($_GET["lib_id"], $_GET["max_width"], $_GET["max_height"]);
	if ($preview!=null){
		header('Content-Type: image/jpeg');
		imagejpeg($preview);
	}
} elseif ($_GET["action"]=="get_thumbs"){
	//Obtain array of media_library records
	$r=$mb->get_media_library_records_by_searchterm($_GET["searchterm"],strtolower($_GET["images"])=="checked",strtolower($_GET["videos"])=="checked",strtolower($_GET["recent"])=="checked");
	echo $dmb->display_thumbs($r,$_GET["target_type"],$_GET["target_id"]);
} elseif ($_GET["action"]=="save_filter_presets"){
	$upref=new cw_User_preferences($d,$a->cuid);
	$mediabase_service_id=$a->services->get_service_id_for_non_ajax_file($a->services->get_non_ajax_service_url($a->csid));
	foreach ($_POST as $filter_name=>$value){
		echo "filtername:$filter_name, value:$value";
		$upref->write_pref($mediabase_service_id, $filter_name, $value);		
	}	
} elseif ($_GET["action"]=="delete_resource"){
	if ($a->csps>=CW_E){
		if (!$mb->remove_media($_GET["lib_id"])){
			echo "Internal error: could not complete the process of deleting this resource (ID:".$_GET["lib_id"].")";
		}		
	} else {
		echo CW_ERR_INSUFFICIENT_PRIVILEGES;
	}
} elseif ($_GET["action"]=="get_media_library_size"){
	echo $mb->get_media_library_size();
} elseif ($_GET["action"]=="get_edit_resource_interface"){
	if ($_GET["lib_id"]>0){
		echo $dmb->get_edit_resource_interface($_GET["lib_id"]);
	}
} elseif ($_GET["action"]=="acomp_resource_titles"){
	echo $mb->get_resource_title_autocomplete_suggestions($_GET["term"]);
} elseif ($_GET["action"]=="save_resource_title"){
	if ($mb->update_resource_title($_GET["lib_id"], $_POST["title"])){
		echo "OK";
	} else {
		echo "An error occurred while trying to save the new title for this resource.";
	}
} elseif ($_GET["action"]=="assign_existing_media_tag"){
    if ($a->csps>=CW_E){
      //Assign a media_tag to an lib via id
      if ($mb->assign_media_tag_to_media_library($_POST["media_tag_id"],$_POST["lib_id"])){
        echo "OK";
      } else {
        echo "Error while trying to add media tag to the resource";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
} elseif ($_GET["action"]=="add_new_media_tag"){
    if ($a->csps>=CW_A){
      //Add a new media_tag
      $new_id=$mb->add_media_tag($_POST["val"]);
      if ($new_id>0){
        if ($mb->assign_media_tag_to_media_library($new_id,$_POST["lib_id"])){
          echo "OK";
        } else {
          echo "An error occurred while trying to assign the new media tag to the resource";
        }
      } else {
        echo "An error occurred while trying to add the media tag to the database";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
 } elseif ($_GET["action"]=="get_media_tags"){
    //Return select options with the media tags attached to the arr
    $options=$dmb->get_media_tags_for_select($_GET["lib_id"]);
    if ($options!==false){
      echo $options;
    } else {
      echo "<option>Could not load media tags</option>";
    }       
 } elseif ($_GET["action"]=="delete_media_tag"){
    if ($a->csps>=CW_E){
      //Remove one media tag from an arr
      if ($_POST["media_tag_id"]>0){
        if ($mb->unassign_media_tag_from_media_library($_POST["media_tag_id"],$_POST["lib_id"])){
          echo "OK";
        } else {
          echo "Could not delete media tag";
        }
      } else {
        echo "To delete a single media tag, select it first";                                                      
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
 } elseif ($_GET["action"]=="delete_all_media_tags"){
    if ($a->csps>=CW_E){
      //Remove all media tags from the lib
      if ($mb->unassign_all_media_tags_from_resource($_POST["lib_id"])){
        echo "OK";
      } else {
        echo "Could not delete media tags";
      }
    } else {
      echo CW_ERR_INSUFFICIENT_PRIVILEGES;
    }            
} elseif ($_GET["action"]=="acomp_media_tags"){
	echo $mb->get_media_tags_autocomplete_suggestions($_GET["term"]);
} elseif ($_GET["action"]=="acomp_author"){
    echo $mb->get_media_authors_autocomplete_suggestions($_GET["term"]);
} elseif ($_GET["action"]=="acomp_copyright_holders"){
    echo $mb->get_media_copyright_holders_autocomplete_suggestions($_GET["term"]);
} elseif ($_GET["action"]=="save_copyright_holders"){
    if ($a->csps>=CW_E){
      //$_POST has lib_id, and value (from autocomplete: [title (#id),])
      $lib_id=$_POST["lib_id"];
      $error=false;
      if ($lib_id>0){
        //Delete existing associations between the piece and copyright holders from db
        $mb->unassign_all_media_copyright_holders_from_resource($lib_id);      
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
                $new_error=($mb->assign_media_copyright_holder_to_resource($cholder_id,$lib_id)===false);
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
              $cholder_id=$mb->add_media_copyright_holder($cholder_title);
              //Add association in question
              $error=($mb->assign_media_copyright_holder_to_resource($cholder_id,$lib_id)===false);
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
} elseif ($_GET["action"]=="save_media_authors_to_media_library_record"){
    if ($a->csps>=CW_E){
      //$_POST has lib_id, and value (from autocomplete: [title (#id),])
      $lib_id=$_POST["lib_id"];
      $error=false;
      if ($lib_id>0){
        //Delete existing associations between the piece and copyright holders from db
        $mb->unassign_all_media_authors_from_resource($lib_id);      
        //Split value by , then find id
        $parts=explode(',',$_POST["value"]);
        foreach ($parts as $v){
          if (trim($v)!=""){
            //Only look at this substring if it's not empty
            $has_id=(!(strpos($v,'#')===false)); //If the # doesn't exist, we don't have a c-holder ID
            if ($has_id){
              $x=substr($v,strpos($v,'#')+1); //Copy from after the hash until the closing bracket
              $authorid=substr($x,0,strpos($x,')'));
              if ($authorid>0){
                //Now we have all data to create authors to music_pieces record
                $new_error=($mb->assign_media_author_to_resource($authorid,$lib_id)===false);
                if (!$error){
                  //Only if there was no previous error we'll register this result (avoid overwriting a previous error)
                  $error=$new_error;
                }
              } else {
                $error=true; //ID zero or undefined
              }
            } else {
              //No #, so no ID. Try to add the copyright holder to the database, as is
              $authortitle=trim($v);
              $authorid=$mb->add_media_author($authortitle);
              //Add association in question
              $error=($mb->assign_media_author_to_resource($authorid,$lib_id)===false);
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
} else {
	echo "INVALID REQUEST";
}

$p->nodisplay=true;

?>
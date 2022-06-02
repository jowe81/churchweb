<?php

/* Instances of this class represent single services - not the table of services or table of Church_services*/

class cw_Church_Service {

  public $a,$d,$id,$event_id,$title,$service_name,$no_service_times;//Number of service times
  public $positions,$elements,$event_records,$service_record,$rehearsals,$sermons;
  public $is_past; //is the service in the past, i.e. are all shows over?
  private $event_handling;                                           
  
  function __construct(cw_Event_handling $event_handling,$church_service_id,$include_meta_type_with_elements=false){
    $this->event_handling=$event_handling;
    $this->d=$event_handling->d;//?
    $this->a=$event_handling->auth;
    $this->id=$church_service_id;
    $this->load_from_db($include_meta_type_with_elements);  
  }
  
  function get_sermon_records(){
    $t=array();
    $sermon_element_type_id=$this->event_handling->church_services->get_service_element_type_id("Sermon");
    //Go through elements
    foreach ($this->elements as $element){
      if ($element["element_type"]==$sermon_element_type_id){
        //Got a sermon element
        $t[]=$this->event_handling->scripture_handling->sermons->get_sermon_record_for_service_element($element["id"]);        
      }
    }
    return $t;  
  }
  
  function get_first_sermon_scripture_string(){
    $t=array();
    $sermon_element_type_id=$this->event_handling->church_services->get_service_element_type_id("Sermon");
    //Go through elements
    foreach ($this->elements as $element){
      if ($element["element_type"]==$sermon_element_type_id){
        //Got a sermon element
        return $this->event_handling->scripture_handling->scripture_refs->get_scripture_ref_string_for_service_element($element["id"]);
      }
    }
    return false;  
  }                                       
  
  //Pass auth in to be able to get name for person_id
  function get_first_sermon_preacher_name($auth){ 
    $t=array();
    $sermon_element_type_id=$this->event_handling->church_services->get_service_element_type_id("Sermon");
    //Go through elements
    foreach ($this->elements as $element){
      if ($element["element_type"]==$sermon_element_type_id){
        //Got a sermon element
        ($element["person_id"]!=0) ? $name=$auth->personal_records->get_name_first_last($element["person_id"]) : $name=$element["other_person"];          
        return $name;
      }
    }
    return false;    
  }
  
  function get_first_sermon_abstract(){
    if (isset($this->sermons[0])){
      return $this->sermons[0]["abstract"];    
    }
    return false;
  }
  
  function load_from_db($include_meta_type_with_elements){
    //Load the event records
    $this->event_records=$this->event_handling->get_event_records_for_service($this->id);
    //Check if they are all in the past (if the last one is in the past, they all are)
    $last_event=end($this->event_records);
    $this->is_past=($last_event["timestamp"]<time());
    //
    $this->no_service_times=sizeof($this->event_records);
    //Load the service record
    $this->service_record=$this->event_handling->church_services->get_service_record($this->id);
    //Load the positions array
    $this->positions=$this->event_handling->event_positions->get_positions_to_services_records($this->id);
    //Load the elements array
    $this->elements=$this->event_handling->church_services->get_all_service_element_records($this->id,$include_meta_type_with_elements);
    //Name
    $this->service_name=$this->service_record["service_name"];
    //Title
    $this->title=$this->service_record["title"];
    //Sermons
    $this->sermons=$this->get_sermon_records();
    if (empty($this->title)){
      $title="";
      $sermons=$this->get_sermon_records();
      $sermons=$this->sermons;
      if (sizeof($sermons)>0){
        if ($sermons[0]["title"]!=""){
          //Have title for first sermon
          $title=$sermons[0]["title"];
          if (sizeof($sermons)>1){
            $title.=" (and more)";
          }
        } else {
          //No title for first sermon - maybe scripture?
          $title=$this->get_first_sermon_scripture_string();
        }      
      }
      $this->title=$title;
      if (empty($this->title)){
        //Couldn't find either sermon title or sermon scripture, give up
      }
    }
    //Service name
    $this->service_name=$this->service_record["service_name"];  
    //Rehearsals
    $this->rehearsals=$this->event_handling->get_rehearsals_for_service($this->id);
    
  }
  
  //Service is in progress if one of the shows is in progress
  function in_progress(){
    foreach ($this->event_records as $v){
      if (($v["timestamp"]<time()) && ( time()<$v["timestamp"]+$v["duration"])){
        return true;
      } 
    }
    return false;
  }
  
  function get_info(){
    $t="<table>";
    $t.="<tr><td>Service-ID</td><td>".$this->id."</td></tr>";
    $t.="<tr><td>Title</td><td>".$this->title."</td></tr>";
    $t.="<tr><td>#of positions</td><td>".sizeof($this->positions)."</td></tr>";
    $t.="<tr><td>#of elements</td><td>".sizeof($this->elements)."</td></tr>";
    $t.="<tr><td>Event-ID</td><td>".$this->event_id."</td></tr>";
    $t.="<tr><td>Date/time</td><td>".date("Y/m/d H:i:s",$this->event_record["timestamp"])."</td></tr>";
    $t.="<tr><td>Category 1</td><td>".$this->event_record["cat1"]."</td></tr>";
    $t.="<tr><td>Category 2</td><td>".$this->event_record["cat2"]."</td></tr>";
    $t.="<tr><td>Category 3</td><td>".$this->event_record["cat3"]."</td></tr>";
    $t.="</table>";
    return $t;
  } 
  
  function get_info_string(){
    return $this->service_name." ".$this->get_service_times_string(true); 
  }
  
  function get_info_string_for_filename(){
    return $this->service_name." ".date("M j",$this->event_records[0]["timestamp"]);
  }
    
  function get_service_times_string($short=false){
    $t="";
    $day=0;
    $date_format_string="l F j Y";
    if ((sizeof($this->event_records)>3) || ($short)){
      $date_format_string="F j";
    }
    foreach ($this->event_records as $v){
      if ($day!=getBeginningOfDay($v["timestamp"])){
        $t.=", ".date($date_format_string,$v["timestamp"]);
      }
      $t.=", ".date("g:ia",$v["timestamp"]);    
      $day=getBeginningOfDay($v["timestamp"]);  
    }
    if (substr($t,0,2)==", "){
      $t=substr($t,2);
    }
    return $t;
  }
  
  function get_number_of_needed_positions(){
    if (empty($this->service_record["needed_positions"])){
      return 0;
    }
    return sizeof(explode(',',$this->service_record["needed_positions"]));    
  }
  
  function get_array_of_needed_positions(){
    if (empty($this->service_record["needed_positions"])){
      return array();
    }
    $res=explode(',',$this->service_record["needed_positions"]);
    //Sorting is important so that positions that are needed multiple times can be identified as such
    sort($res);    
    //Now assign a position id to each key, and the number of needed instances for that position to the values    
    $t=array();
    foreach ($res as $v){
      $t[$v]++;
    }    
    return $t;  
  }
  
  //Return array of strings, one element per lyrics slide
  //Copyright info gets attached to each slide after % separator
  //Source info, if requested, is attached to the respective slides after a second % separator
  //If $service_element_id given, return slides for the associated song only 
  function get_slides($include_copyright=true,$include_source_info=true,$service_element_id=0){
  	$slides=array();
  	$slides[]="";
  	foreach ($this->elements as $v){
  		if (($service_element_id==0) || ($v["id"]==$service_element_id)){
  			$atse=$this->event_handling->church_services->get_atse_for_service_element($v["id"],true);
  			if (is_array($atse)){
  				//This element has arrangements_to_service_elements-record, i.e. it's a music piece
  				if (!empty($atse["lyrics"])){
  					//Obtain credits (author, copyright etc)
  					if ($arr=$this->event_handling->mdb->get_arrangement_record($atse["arrangement"])){
  						$music_piece_credits=$this->event_handling->mdb->get_music_piece_credits($arr["music_piece"]);
  						$arrangement_credits=$this->event_handling->mdb->get_arrangement_credits($atse["arrangement"]);
  						$copyright_info=$this->event_handling->mdb->get_copyright_holder_string_for_music_piece($arr["music_piece"],false);
  						$credits="";
  						if ($music_piece_credits!=""){
  							$credits.=$music_piece_credits.", ";
  						}
  						if ($arrangement_credits!=""){
  							$credits.=$arrangement_credits.", ";
  						}
  						if ($copyright_info!=""){
  							$credits.="(C) ".$copyright_info.", ";
  						}
  						if (CW_CCLI_NUMBER!=""){
  							$credits.="CCLI#".CW_CCLI_NUMBER.", ";
  						}
  						$credits=substr($credits,0,-2);
  					}
  					//See if we have source info
  					$source_info="";
  					if ($include_source_info){
  						$source_rec=$this->event_handling->mdb->get_source_record($arr["source_id"]);
  						$source_title=$source_rec["title"];
  						if ((!empty($source_title)) && ($source_rec["identify_in_lyrics_presentation"])){
  							//Got a source - number/index, too?
  							if ($arr["source_index"]>0){
  								//Source info is requested and available, so add it
  								$source_info=$source_title." #".$arr["source_index"];
  							}
  						}
  					}
  					//It has lyrics, too - $atse["lyrics"] is CSL of lyrics_ids. 0=blank slide.
  					$lyrics_for_this_element=explode(',',$atse["lyrics"]);
  					foreach ($lyrics_for_this_element as $v2){
  						if ($v2==0){
  							//Blank slide
  							$slides[]="";
  						} else {
  							$lyrics_record=$this->event_handling->mdb->get_lyrics_record($v2);
  							if (is_array($lyrics_record)){
  								//Got the lyrics record
  								$c=$lyrics_record["content"];
  								$dbl_nl=strpos($c,"\n\n");
  								while ($dbl_nl!==false){
  									$this_slide=substr($c,0,$dbl_nl); //Copy the part before the double newline into new slide
  									if (!empty($source_info)){
  										$this_slide.="@/".$source_info;
  									}
  									$slides[]=$this_slide;
  									$c=substr($c,$dbl_nl+2); //Cut the copied slide and the double nl
  									$dbl_nl=strpos($c,"\n\n");
  								}
  								$this_slide=$c;
  								if (!empty($source_info)){
  									$this_slide.="@/".$source_info;
  								}
  								$slides[]=$this_slide; //Add the rest of the string (or the whole string if there was no doubl-nls)
  							}
  						}
  					}
  					//Add the credits to the lastslide of the piece
  					$last_slide_index=sizeof($slides)-1;
  					$slides[$last_slide_index].="%/".$credits;
  					//If the last slide has less than 2 lines, also put the credits on the second last slide
  					if (strpos($slides[$last_slide_index],"\n")===false){
  						if ($last_slide_index-1>0){
  							//Second last slide is not the first slide of the presentation
  							if ($slides[$last_slide_index-1]!=""){
  								//It is also not empty
  								$slides[$last_slide_index-1].="%/".$credits;
  							}
  						}
  					}
  					//Add blank slide after end of each piece
  					$slides[]="";
  				}
  			}  				
  		}
  	}
  	return $slides;
  }
  
  private function get_projector_source_url($lib_id){
  	return CW_AJAX."ajax_projector_feeds.php?action=get_projector_image&lib_id=$lib_id";
  }

  private function get_black_background_src(){
  	return CW_ROOT_WEB.'img/black.gif';
  }
  
  private function get_background_obj($src){
  	$o=array();
  	$o['type']='background_image';
  	$o['src']=$src;
  	return $o;
  }
  
  private function get_service_background_src(){
  	if ($this->service_record["background_image"]>0){
  		//Service background exists
  		return $this->get_projector_source_url($this->service_record["background_image"]);
  	} else {
  		//Service background does not exist - return black
  		return $this->get_black_background_src();
  	}
  }  
  
  private function get_blank_lyrics_slide($bg_src=""){
  	$this_slide=array();
  	$o=array();
  	$o['type']='lyrics';
  	$o['content']='';
  	$o['fragment']='blank slide';
  	$this_slide[]=$o;
  	if (!empty($bg_src)){
  		//Background source has been passed
  		$this_slide[]=$this->get_background_obj($bg_src);
  	} else {
  		//No background source has been passed - assume service bg
  		$this_slide[]=$this->get_background_obj($this->get_service_background_src());
  	}
  	return $this_slide;
  }
  
  //Get slides as array of assoc arrays - for CWP
  function get_slides_assoc($service_element_id,$service_background_supersedes_mdb_backgrounds=false,$include_copyright=true,$include_source_info=true){
  	$mdb=new cw_Music_db($this->a);
  	$types=$mdb->get_fragment_types_records();
  	$slides=array();
  	$background_id=$this->event_handling->church_services->get_service_element_background($service_element_id, $this->a);
  	if ($background_id>0){
  		$bg_src=$this->get_projector_source_url($background_id);
  	} else {
  		$bg_src=$this->get_black_background_src();
  	}
  	$atse=$this->event_handling->church_services->get_atse_for_service_element($service_element_id,true);
  	if (is_array($atse)){
  		//Begin with blank slide
  		$slides[]=$this->get_blank_lyrics_slide($bg_src);
  		if (!empty($atse["lyrics"])){
  			//Obtain credits (author, copyright etc)
  			if ($arr=$this->event_handling->mdb->get_arrangement_record($atse["arrangement"])){
  				$music_piece_credits=$this->event_handling->mdb->get_music_piece_credits($arr["music_piece"]);
  				$arrangement_credits=$this->event_handling->mdb->get_arrangement_credits($atse["arrangement"]);
  				$copyright_info=$this->event_handling->mdb->get_copyright_holder_string_for_music_piece($arr["music_piece"],false);
  				$credits="";
  				if ($music_piece_credits!=""){
  					$credits.=$music_piece_credits.", ";
  				}
  				if ($arrangement_credits!=""){
  					$credits.=$arrangement_credits.", ";
  				}
  				if ($copyright_info!=""){
  					$credits.="(C) ".$copyright_info.", ";
  				}
  				if (CW_CCLI_NUMBER!=""){
  					$credits.="CCLI#".CW_CCLI_NUMBER.", ";
  				}
  				$credits=substr($credits,0,-2);
  			}
  			//See if we have source info
  			$source_info="";
  			if ($include_source_info){
  				$source_rec=$this->event_handling->mdb->get_source_record($arr["source_id"]);
  				$source_title=$source_rec["title"];
  				if ((!empty($source_title)) && ($source_rec["identify_in_lyrics_presentation"])){
  					//Got a source - number/index, too?
  					if ($arr["source_index"]>0){
  						//Source info is requested and available, so add it
  						$source_info=$source_title." #".$arr["source_index"];
  					}
  				}
  			}
  			//It has lyrics, too - $atse["lyrics"] is CSL of lyrics_ids. 0=blank slide.
  			$lyrics_for_this_element=explode(',',$atse["lyrics"]);
  			foreach ($lyrics_for_this_element as $v2){
  				$this_slide=array();
  				$o=array();
  				if ($v2==0){
  					//Blank slide
 					$slides[]=$this->get_blank_lyrics_slide($bg_src);
				} else {
  					$lyrics_record=$this->event_handling->mdb->get_lyrics_record($v2);
  					if (is_array($lyrics_record)){
  						//Got the lyrics record
  						$this_fragment_slideno=1;
  						//What fragment-type and no?
  						(($types[$lyrics_record["fragment_type"]-1]["title"]=="verse")|| ($lyrics_record["fragment_no"]>1)) ? $fno=" ".$lyrics_record["fragment_no"] : $fno="";
  						($lyrics_record["language"]!=$mdb->get_default_language_for_music_piece($arr["music_piece"])) ? $language=" (".$mdb->int_to_language($lyrics_record["language"]).")" : $language="";
  						$this_fragment=$types[$lyrics_record["fragment_type"]-1]["title"].$fno.$language;
  						$c=$lyrics_record["content"];
  						$dbl_nl=strpos($c,"\n\n");
  						if ($dbl_nl===false) {
  							$dbl_nl=strlen($c); //If there are no dbl\ns the whole content goes in first slide
  						} else {
  							do {
  								$this_slide=array();
  								$o=array();
  								$o['type']="lyrics";
  								$o['content']=substr($c,0,$dbl_nl); //Copy the part before the double newline into new slide
  								$o['fragment']=$this_fragment;
  								if ($this_fragment_slideno>1){
  									$o['fragment']="";//(cont'd)";
  								}
  								$this_slide[]=$o;
 								if (!empty($source_info)){
  									$o=array();
  									$o['type']='source_info';
  									$o['content']=$source_info;
  									$this_slide[]=$o;
  								}
  								$this_slide[]=$this->get_background_obj($bg_src);
  								$slides[]=$this_slide;
  								$c=substr($c,$dbl_nl+2); //Cut the copied slide and the double nl
  								$dbl_nl=strpos($c,"\n\n");
  								$this_fragment_slideno++;
  							} while ($dbl_nl!==false);
  						}
  						//Last slide with remaining content:
  						$this_slide=array();
  						$o=array();
  						$o['type']="lyrics";
  						$o['content']=$c;
  						$o['fragment']=$this_fragment;
  						if ($this_fragment_slideno>1){
  							$o['fragment']="";//(cont'd)";
  						}
  						$this_slide[]=$o;
  						if (!empty($source_info)){
  							$o=array();
  							$o['type']='source_info';
  							$o['content']=$source_info;
  							$this_slide[]=$o;
  						}
  						$this_slide[]=$this->get_background_obj($bg_src);
  						$slides[]=$this_slide;
  					}
  				}
  			}
  			//Add the credits to the lastslide of the piece
  			$o=array();
  			$o['type']='credits';
  			$o['content']=$credits;
  			$last_slide_index=sizeof($slides)-1;
  			$slides[$last_slide_index][]=$o;
  			/*
  					 //If the last slide has less than 2 lines, also put the credits on the second last slide
  					if (strpos($slides[$last_slide_index][0]['content'],"\n")===false){
  					if ($last_slide_index-1>0){
  					//Second last slide is not the first slide of the presentation
  					if ($slides[$last_slide_index-1][0]['content']!=""){
  					//It is also not empty
  					$slides[$last_slide_index-1][]=$o;
  					}
  					}
  					}
  			*/
  			//Add blank slide after end of each piece
  			//$slides[]=$this->get_blank_lyrics_slide();
  		}
  	} else {
  		//Non music object
  		$this_slide=array();
  		$this_slide[]=$this->get_background_obj($this->get_service_background_src());
  		$slides[]=$this_slide;
  	}
  
  	return $slides;
  }
  
  function get_next_service_element_with_lyrics($service_element_id){
  	$current_element_nr=0;
  	foreach ($this->elements as $v){
  		//Determine element_nr of current element
  		if ($v["id"]==$service_element_id){
  			$current_element_nr=$v["element_nr"];
  			break;
  		}
  	}
  	$query="
  			SELECT service_elements.id 
  			FROM service_elements 
  			LEFT JOIN service_element_types 
  				ON service_elements.element_type=service_element_types.id 
  			LEFT JOIN arrangements_to_service_elements 
  				ON arrangements_to_service_elements.service_element=service_elements.id 
  			WHERE 
  				service_elements.service_id=".$this->id." 
  				AND service_element_types.meta_type='music' 
  				AND arrangements_to_service_elements.lyrics<>''
  				AND service_elements.element_nr>$current_element_nr;
  	";
  	if ($res=$this->d->query($query)){
  		if ($r=$res->fetch_assoc()){
  			//Got ID of chronologically next music element with lyrics
  			return $r["id"];
  		}
  	}
  	return false;
  }
  
  function get_files($service_element_id){
  	$result=array();
  	$files=$this->event_handling->church_services->get_files_assigned_to_service_element($service_element_id);
  	if (sizeof($files)>0){
  		foreach ($files as $r){
  			$m=array();
  			$m["name"]=$r["name"].".".$r["ext"];
  			if ($m["name"]=="."){
  				//No name?
  				$m["name"]="file_".$r["id"];
  			}
  			$m["size"]=bytes_to_human_readable_filesize($r["size"]); //utilities misc
  			$m["url"]=CW_DOWNLOAD_HANDLER."?a=".sha1($this->a->csid)."&b=".$r["id"];
  			$m["id"]=$r["id"];
  			$result[]=$m;
  		}
  	}
  	return $result;
  }

  //Return true if element is not a service divider, or group header (depending on $allow_group_header)
  function element_is_actual_element($element_nr,$allow_group_headers=false){
  	$etype=$this->elements[$element_nr-1]["element_type"];
  	if ($allow_group_headers){
  		//Group headers (type==-1) are allowed
  		$result=($etype!=0);
  	} else {
  		//Group headers are not allowed
  		$result=($etype>0);
  	}
  	return $result;
  }
}

?>
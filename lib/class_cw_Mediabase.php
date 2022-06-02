<?php

class cw_Mediabase {

    public $a,$d; //Database access
    
    function __construct(cw_Auth $auth){
      $this->a = $auth;
      $this->d = $auth->d;
    }
    
    function check_for_table($table){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (
              ($this->d->q("CREATE TABLE media_library (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
              			  file_id INT,
              			  thumb_file_id INT,
              			  projector_file_id INT,
                          type VARCHAR(50),
              			  ext VARCHAR(10),
              		      width SMALLINT,
              		      height SMALLINT,
              			  aspect_x SMALLINT,
              			  aspect_y SMALLINT,
              		      length SMALLINT,
              		      title VARCHAR(255),
              			  orig_name VARCHAR(255),
              			  active INT,
              			  added_at INT,
              			  added_by INT,
              		      INDEX(title)
                        )"))                                                
                &&        
              ($this->d->q("CREATE TABLE media_tags_to_media_library (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          media_tag INT,
                          lib_id INT,
              			  INDEX(media_tag)
                        )"))                        
                &&        
              ($this->d->q("CREATE TABLE media_tags (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title VARCHAR(50),
              			  INDEX(title)
                        )"))
	      		&&
    	      ($this->d->q("CREATE TABLE media_copyright_holders (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name VARCHAR(100),
    	      			  INDEX(name)
                        )"))
      			&&
      		  ($this->d->q("CREATE TABLE media_authors (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          name VARCHAR(50),
      		  			  INDEX(name)
                        )"))      		      		      	
                &&        
              ($this->d->q("CREATE TABLE media_copyright_holders_to_media_library (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          media_copyright_holder INT,
                          lib_id INT,
              			  INDEX(lib_id)
                        )"))                        
                &&        
              ($this->d->q("CREATE TABLE media_authors_to_media_library (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          media_author INT,
                          lib_id INT,
              			  INDEX(lib_id)
                        )"))                        
			);
            /*
             * media_library:
             * 	type can be audio, video, image
             * 
             * media_tags_to_media_library:
             *  media_tag refers to media_tags table
             *  lib_id refers to media_library table
            */
    }

    private function check_for_and_drop_tables($tables=array()){
      foreach ($tables as $v){
        if ($this->check_for_table($v)){
          $this->d->drop_table($v);
        }        
      }
    }
    
    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $tables=array("media_library",
                    "media_tags_to_media_library",
                    "media_tags",
      				"media_copyright_holders",
      				"media_authors",
      				"media_copyright_holders_to_media_library",      		
      				"media_authors_to_media_library");
      $this->check_for_and_drop_tables($tables);
      $res=$this->create_tables();
      if ($res && $default_records){
      	//Add default records
      }
      return $res;
    }

	/* media_library */
    
    //Will only add media library record that links an ID from cw_Files
    private function add_media_to_media_library($file_id,$type,$ext,$title="",$file_name="",$width=0,$height=0,$length=0){
    	if (!$this->media_exists($file_id)){
    		$e=array();
    		$e["file_id"]=$file_id;
    		$e["type"]=$type; //audio, image, video
    		$e["ext"]=$ext; //original file extension
    		$e["orig_name"]=$file_name;//original file name
    		$e["width"]=$width;
    		$e["height"]=$height;
    		if (($width>0) && ($height>0)){
    			$gcd=gcd($width,$height); //utilities_misc
    			$e["aspect_x"]=($width/$gcd);
    			$e["aspect_y"]=($height/$gcd);
    		}
			$e["length"]=$length; //in seconds, for video and audio files
			$e["title"]=$title; 
			$e["added_at"]=time();
			$e["added_by"]=$this->a->cuid;  
			$e["active"]=true;	
			return $this->d->insert_and_get_id($e, "media_library");
    	}
    	return false;
    }
        
    //Will only remove media library (and associated) record(s)
    private function remove_media_from_media_library($lib_id){
    	$res=$this->d->q("DELETE FROM media_library WHERE id=$lib_id;")
    	  && $this->unassign_all_media_authors_from_resource($lib_id)
    	  && $this->unassign_all_media_copyright_holders_from_resource($lib_id)
    	  && $this->unassign_all_media_tags_from_resource($lib_id);
    	return $res;
    }
    
    private function update_media_library_record($lib_id,$e){
    	return $this->d->update_record("media_library", "id", $lib_id, $e);
    }
    
    //Does a mediabase record exist for this file?
    function media_exists($file_id){
    	$query="SELECT id FROM media_library WHERE file_id=$file_id";
    	if ($res=$this->d->q($query)){
    		if ($res->num_rows>0){
    			return true;
    		}
    	}	
    	return false;
    }
    
    
    function get_media_library_record($lib_id){
    	if ($res=$this->d->q("SELECT * FROM media_library WHERE id=$lib_id;")){
    		if ($r=$res->fetch_assoc()){
    			return $r;
    		}
    	}
    	return false;
    }
    
    //If $images,$videos, or $recent is set, those are additional conditions
    //Do not return retired media
    function get_media_library_records_by_searchterm($term,$images=false,$videos=false,$recent=false,$limit=100){
    	$r=array();
    	$cond=" AND (active=1) ";
    	if ($images){
    		$cond.=" AND (type=\"image\") ";
    	}
        if ($videos){
    		$cond.=" AND (type=\"video\") ";
        }
    	if ($recent){
    		$cond.=" AND (added_at>".(time()-CW_MEDIABASE_RECENTLY).") ";
    	}    	 
    	if (true){
    		$query="
    		SELECT DISTINCT media_library.* FROM media_library
    		LEFT JOIN
    			media_tags_to_media_library ON media_tags_to_media_library.lib_id=media_library.id
    		LEFT JOIN
    			media_tags ON media_tags.id=media_tags_to_media_library.media_tag
    		WHERE
    			(media_tags.title LIKE \"%$term%\"
    				OR
    			media_library.title LIKE \"%$term%\")
    			$cond
    		LIMIT $limit
    		;
    		";    		
    	} else {
    		$query="SELECT * FROM media_library LIMIT $limit;";
    	}
    	if ($res=$this->d->q($query)){
    		while ($e=$res->fetch_assoc()){
    			$r[]=$e;
    		}
    	}
    	return $r;
    }
    
    function get_media_file_id($lib_id){
    	if ($r=$this->get_media_library_record($lib_id)){
    		return $r["file_id"];
    	}
    }
    
    //(Re)build thumbs and projector versions of this resource (image)
    private function rebuild_image_versions($lib_id){
    	if ($src=$this->get_original_image($lib_id)){
    		//Crop to projector ratio
    		$src=crop_image($src,CW_PROJECTOR_X,CW_PROJECTOR_Y); //utilities_misc
    		//Reduce to projector size
    		$projectorimg=resize_image($src,CW_PROJECTOR_X,CW_PROJECTOR_Y);
    		//Reduce further to thumb size
    		$thumbimg=resize_image($projectorimg,CW_MEDIABASE_THUMB_WIDTH,CW_MEDIABASE_THUMB_WIDTH*(CW_PROJECTOR_Y/CW_PROJECTOR_X),false);    		    		    	
    		//Output thumb to temporary file
    		$tmp_file=CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER."_thumb_".$lib_id;
    		imagejpeg($thumbimg,$tmp_file);
    		imagedestroy($thumbimg);
    		//Process temporary thumbfile with cw_Files
    		$files=new cw_Files($this->d);
    		$thumb_id=$files->add_existing_file_to_db($tmp_file,0,$this->a->cuid,"thumb_".$lib_id.".jpg");
    		if ($thumb_id>0){
    			//Thumb successfully processed
    			if ($r=$this->get_media_library_record($lib_id)){
    				//Remove old thumb file if extant
    				$files->remove_file($r["thumb_file_id"]);
    				$r["thumb_file_id"]=$thumb_id;
   					//Output projector image to temporary file
   					$tmp_file=CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER."_projector_".$lib_id;
    				imagejpeg($projectorimg,$tmp_file);
    				imagedestroy($projectorimg);
    				//Process temporary projector file with cw_Files
    				$proj_id=$files->add_existing_file_to_db($tmp_file,0,$this->a->cuid,"projector_".$lib_id.".jpg");
    				if ($proj_id>0){
    					//Remove old projector file if extant
    					$files->remove_file($r["projector_file_id"]);
    					$r["projector_file_id"]=$proj_id;
    					return $this->update_media_library_record($lib_id, $r);
    				}
    			}
    		}
    	}
    	return false; 
    }
        
    //Go through entire library and recreate thumbs and projector copies of the images according to CW_PROJECTOR_X, CW_PROJECTOR_Y
    function rebuild_all_image_versions(){
    	$error=false;
    	$query="SELECT id FROM media_library;";
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			if (!$this->rebuild_image_versions($r["id"])){
    				$error=true; //Note the error, but don't break
    			}
    		}
    	}
    	return !$error; 
    }
    
    /* Take temporary file (=uploaded file, with $tmp_file coming from $_FILES["file"]["tmp_name"]
     * and process with cw_Files, as well as add to media_library table.
     * If image, also generate a thumbfile and a projector file if requested
     * Projector file is a copy scaled to CW_PROJECTOR_X*CW_PROJECTOR_Y
     * Return media_library id (lib_id)
     * */
    function add_media($tmp_file,$type,$file_name,$title,$length=0,$generate_thumb=true,$generate_projector_file=true){
    	if ($type=="image"){
    		$dimensions=getimagesize($tmp_file);
    		$width=$dimensions[1];
    		$height=$dimensions[0];
    	}
    	$pathinfo=pathinfo($file_name);
    	$files=new cw_Files($this->d);
    	$file_id=$files->add_uploaded_file($tmp_file, $pathinfo["filename"], $pathinfo["extension"],$this->a->cuid);
    	if ($file_id>0){
    		//Successfully added physical file to cw_Files
    		//Add to media_library table:
    		$lib_id=$this->add_media_to_media_library($file_id,$type,strtolower($pathinfo["extension"]),$title,$file_name,$height,$width,$length);
    		if ($lib_id>0){
    			//Thumb&projector versions?
    			if ($generate_thumb){
    				if ($this->rebuild_image_versions($lib_id,$generate_thumb,$generate_projector_file)){
    					return $lib_id;
    				} else {
    					return false;
    				}
    			} else {
    				return $lib_id;
    			}
    		} else {
    			//Error with the media_library table
    		}
    	} else {
    		//Error with the uploaded file
    	}
    	return false;
    }
    
    function retire_media($lib_id){
    	$query="UPDATE media_library SET active=0 WHERE id=$lib_id";
    	return (($lib_id>0) && ($res=$this->d->q($query)));
    }
    
    //Remove linked file from cw_Files, thumb file if extant, and media_library record
    function remove_media($lib_id,$retire_if_in_use=true){
    	if (($this->resource_in_use($lib_id)) && ($retire_if_in_use)){
    		return $this->retire_media($lib_id);	
    	} else {
    		//Resource not in use or retire-flag not set - delete completely
    		if ($r=$this->get_media_library_record($lib_id)){
    			$file_id=$r["file_id"];
    			if ($file_id>0){
    				$files=new cw_Files($this->d);
    				$removed_file=$files->remove_file($file_id);
    				$removed_record=$this->remove_media_from_media_library($lib_id);
    				($r["thumb_file_id"]>0) ? $removed_thumb=$files->remove_file($r["thumb_file_id"]) : $removed_thumb=true;
    				($r["projector_file_id"]>0) ? $removed_proj=$files->remove_file($r["projector_file_id"]) : $removed_proj=true;
    				return ($removed_file && $removed_record && $removed_thumb && $removed_proj);
    			}
    		}
    	}
    	return false;
    }
    
    //Clear entire library
    function clear_media_library(){
    	$error=false;
    	$query="SELECT id FROM media_library;";
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			if (!$this->remove_media($r["id"])){
    				$error=true;
    				break;
    			}
    		}
    	}
    	return !$error;
    }

    //Return URL to obtain the media from
    function get_media_url($lib_id){
    	$file_id=$this->get_media_file_id($lib_id);
    	if ($file_id>0){
    		$files=new cw_Files($this->d);
    		$url=CW_DOWNLOAD_HANDLER."?a=".sha1($this->a->csid)."&b=".$file_id;    		
    		return $url;
    	}
    	return false;
    }

    //Get image object
    function get_original_image($lib_id){
    	if ($r=$this->get_media_library_record($lib_id)){
    		if (($r["type"]=="image") && ($r["aspect_x"]>0)){
    			if ($r["file_id"]>0){
    				$files=new cw_Files($this->d);
    				$imagefile=$files->get_full_physical_path($r["file_id"]);
    				if (file_exists($imagefile)){
    					if ($r["ext"]=="jpg"){
    						return imagecreatefromjpeg($imagefile);    						
    					} elseif ($r["ext"]=="gif"){
    						return imagecreatefromgif($imagefile);
    					} elseif ($r["ext"]=="png"){
    						return imagecreatefrompng($imagefile);
    					}
    				}
    			}
    		}
    	}
    	return null;
    }

    //Get image object
    function get_projector_image($lib_id){
    	if ($r=$this->get_media_library_record($lib_id)){
    		if (($r["type"]=="image") && ($r["aspect_x"]>0)){
    			if ($r["file_id"]>0){
    				$files=new cw_Files($this->d);
    				$imagefile=$files->get_full_physical_path($r["projector_file_id"]);
    				if (file_exists($imagefile)){
    					return imagecreatefromjpeg($imagefile);
    				}
    			}
    		}
    	}
    	return null;
    }
    
    function get_downscaled_image($img,$max_width,$max_height){
    	//Start by calculating the height we'd get if we made the image $max_width wide
    	$height=get_scaled_height(imagesx($img), imagesy($img), $max_width); //utilities_misc
    	if ($height>$max_height){
    		//Resulting image would be to high, so do it the other way around
    		$height=$max_height;
    		$width=get_scaled_width(imagesx($img), imagesy($img), $height); //utilities_misc
    	} else {
    		//Ok - use max_width and calculated height
    		$width=$max_width;
    	}
    	return resize_image($img,$width,$height); //utilities_misc    	   
    }
    
    function get_downscaled_projector_image($lib_id,$max_width,$max_height){
    	if ($img=$this->get_projector_image($lib_id)){
    		//Got image object
    		return $this->get_downscaled_image($img, $max_width, $max_height);
    	}
    	return null;
    }
    
    function get_downscaled_original_image($lib_id,$max_width,$max_height){
    	if ($img=$this->get_original_image($lib_id)){
    		//Got image object
    		return $this->get_downscaled_image($img, $max_width, $max_height);
    	}
    	return null;
    }
        
   	function get_media_library_size($include_retired=false){
    	$include_retired ? $cond='' : $cond='active=true';
    	return $this->d->get_size("media_library",$cond);
    }
    	 
    function update_resource_title($lib_id,$title){
    	if ($r=$this->get_media_library_record($lib_id)){
    		$r["title"]=$title;
    		return $this->d->update_record("media_library", "id", $lib_id, $r);
    	}
    	return false;
    }
    
    
    function resource_in_use_with_church_services($lib_id){
    	$query="SELECT id FROM church_services WHERE background_image=$lib_id;";
    	if (($lib_id>0) && ($res=$this->d->q($query))){
    		return ($res->num_rows>0);
    	}
    	return null;
    }

    function resource_in_use_with_music_pieces($lib_id){
    	$query="SELECT id FROM music_pieces WHERE background_image=$lib_id OR background_video=$lib_id;";
    	if (($lib_id>0) && ($res=$this->d->q($query))){
    		return ($res->num_rows>0);
    	}
    	return null;
    }
    
    function resource_in_use_with_arrangements($lib_id){
    	$query="SELECT id FROM arrangements WHERE background_image=$lib_id OR background_video=$lib_id;";
    	if (($lib_id>0) && ($res=$this->d->q($query))){
    		return ($res->num_rows>0);
    	}
    	return null;
    }
    
    function resource_in_use_with_service_elements($lib_id){
    	$query="SELECT id FROM service_elements WHERE background_image=$lib_id OR background_video=$lib_id;";
    	if (($lib_id>0) && ($res=$this->d->q($query))){
    		return ($res->num_rows>0);
    	}
    	return null;
    }
    
    //Check if resource is used in a service, music_piece, arrangement, service element
    function resource_in_use($lib_id){
		return ($this->resource_in_use_with_church_services($lib_id) || $this->resource_in_use_with_music_pieces($lib_id) || $this->resource_in_use_with_arrangements($lib_id) || $this->resource_in_use_with_service_elements($lib_id));    	
    }
    
    /* media_tags_to_media_library*/
    
    function assign_media_tag_to_media_library($media_tag,$lib_id){
    	if (!$this->media_has_media_tag($lib_id,$media_tag)){
    		//Association does not yet exist
    		$e=array();
    		$e["lib_id"]=$lib_id;
    		$e["media_tag"]=$media_tag;
    		return $this->d->insert_and_get_id($e,"media_tags_to_media_library");
    	}
    	return true; //If association exists then technically we're succesful
    }
    
    function unassign_media_tag_from_media_library($media_tag,$lib_id){
    	return $this->d->q("DELETE FROM media_tags_to_media_library WHERE lib_id=$lib_id AND media_tag=$media_tag;");
    }
    
    function unassign_all_media_tags_from_resource($lib_id){
    	return $this->d->q("DELETE FROM media_tags_to_media_library WHERE lib_id=$lib_id;");
    }
    
    function media_has_media_tag($lib_id,$media_tag){
    	if ($res=$this->d->q("SELECT id FROM media_tags_to_media_library WHERE lib_id=$lib_id AND media_tag=$media_tag;")){
    		return ($res->num_rows>0);
    	}
    }
    
    function get_media_tags_for_media($lib_id){
    	$query="
	    	SELECT DISTINCT
	    		media_tags.*
	    	FROM
	    		media_tags,media_tags_to_media_library
	    	WHERE
	    		media_tags_to_media_library.lib_id=$lib_id
	    	AND
		    	media_tags_to_media_library.media_tag=media_tags.id
	    	ORDER BY
		    	media_tags.title;
    	";
    	$t=array();
    	if ($res=$this->d->q($query)){
	    	while ($r=$res->fetch_assoc()){
	    		$t[]=$r;
	    	}
    	}
    	return $t;
    }

    /* media_tags */
    
    //Add new or just return ID of extant record
    function add_media_tag($title){
    	$title=strtolower($title);
    	$existing_id=$this->get_media_tag_id($title);
    	if ($existing_id==0){
    		$e=array();
    		$e["title"]=$title;
    		$result=$this->d->insert_and_get_id($e,"media_tags");
    		return $result;
    	} else {
    		return $existing_id;
    	}
    }
    
    function get_media_tag_id($title){
    	if ($res=$this->d->q("SELECT id FROM media_tags WHERE title=\"$title\";")){
    		if ($r=$res->fetch_assoc()){
    			return $r["id"];
    		}
    	}
    	return false;
    }
    
    function get_media_tags_record($id){
    	if ($res=$this->d->q("SELECT * FROM media_tags WHERE id=$id;")){
    		if ($r=$res->fetch_assoc()){
    			return $r;
    		}
    	}
    	return false;
    }
    
    function get_media_tag_title($id){
    	if ($r=$this->get_media_tags_record($id)){
    		return $r["title"];
    	}
    	return false;
    }
    
    function delete_media_tags($id_or_title){
    	if ($id_or_title>0){
    		return ($this->db->query("DELETE FROM media_tags WHERE id=$id_or_title;"));
    	} else {
    		return ($this->db->query("DELETE FROM media_tags WHERE title=\"$id_or_title\";"));
    	}
    }
    
    function get_media_tags_for_resource($lib_id){
    	$query="
    	SELECT DISTINCT
    		media_tags.*
    	FROM
    		media_tags,media_tags_to_media_library
    	WHERE
    		media_tags_to_media_library.lib_id=$lib_id
    	AND
    		media_tags_to_media_library.media_tag=media_tags.id
    	ORDER BY
    		media_tags.title;
    	";
    	$t=array();
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			$t[]=$r;
    		}
    	}
    	return $t;
    }
        
    
    //Delete media_tags that are not showing up in media_tags_to_media_library
    function delete_unused_media_tags(){
    	$query="DELETE media_tags FROM media_tags LEFT JOIN media_tags_to_media_library ON media_tags.id=media_tags_to_media_library.media_tag WHERE media_tags_to_media_library.media_tag IS NULL;";
    	return ($this->d->q($query));
    }

    
    /* media_copyright_holders to media_library */
    
    function assign_media_copyright_holder_to_resource($media_copyright_holder,$lib_id){
    	if (!$this->resource_has_media_copyright_holder($lib_id,$media_copyright_holder)){
    		//Association does not yet exist
    		$e=array();
    		$e["lib_id"]=$lib_id;
    		$e["media_copyright_holder"]=$media_copyright_holder;
    		return $this->d->insert_and_get_id($e,"media_copyright_holders_to_media_library");
    	}
    	return true; //If association exists then technically we're succesful
    }
    
    function unassign_media_copyright_holder_from_resource($media_copyright_holder,$lib_id){
    	return $this->d->q("DELETE FROM media_copyright_holders_to_media_library WHERE lib_id=$lib_id AND media_copyright_holder=$media_copyright_holder;");
    }
    
    function unassign_all_media_copyright_holders_from_resource($lib_id){
    	return $this->d->q("DELETE FROM media_copyright_holders_to_media_library WHERE lib_id=$lib_id;");
    }
    
    function resource_has_media_copyright_holder($lib_id,$media_copyright_holder){
    	if ($res=$this->d->q("SELECT id FROM media_copyright_holders_to_media_library WHERE lib_id=$lib_id AND media_copyright_holder=$media_copyright_holder;")){
    		return ($res->num_rows>0);
    	}
    }
    
    function get_media_copyright_holders_for_resource($lib_id){
    	$query="
    	SELECT DISTINCT
    		media_copyright_holders.*
    	FROM
    		media_copyright_holders,media_copyright_holders_to_media_library
    	WHERE
    		media_copyright_holders_to_media_library.lib_id=$lib_id
    	AND
    		media_copyright_holders_to_media_library.media_copyright_holder=media_copyright_holders.id
    	ORDER BY
    		media_copyright_holders.name;
    	";
    	$t=array();
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			$t[]=$r;
    		}
    	}
    	return $t;
    }
    
    /* Media copyright holders */
    
    //Add new or just return ID of extant record
    function add_media_copyright_holder($name){
    	$existing_id=$this->get_media_copyright_holder_id($name);
    	if ($existing_id==0){
    		$e=array();
    		$e["name"]=$name;
    		$result=$this->d->insert_and_get_id($e,"media_copyright_holders");
    		return $result;
    	} else {
    		return $existing_id;
    	}
    }
    
    function get_media_copyright_holder_id($name){
    	if ($res=$this->d->q("SELECT id FROM media_copyright_holders WHERE name=\"$name\";")){
    		if ($r=$res->fetch_assoc()){
    			return $r["id"];
    		}
    	}
    	return false;
    }
    
    function get_media_copyright_holder_record($id){
    	if ($res=$this->d->q("SELECT * FROM media_copyright_holders WHERE id=$id;")){
    		if ($r=$res->fetch_assoc()){
    			return $r;
    		}
    	}
    	return false;
    }
    
    function get_media_copyright_holder_name($id){
    	if ($r=$this->get_media_copyright_holder_record($id)){
    		return $r["name"];
    	}
    	return false;
    }
    
    function delete_media_copyright_holder($id_or_name){
    	if ($id_or_title>0){
    		return ($this->db->query("DELETE FROM media_copyright_holders WHERE id=$id_or_name;"));
    	} else {
    		return ($this->db->query("DELETE FROM media_copyright_holders WHERE name=\"$id_or_name\";"));
    	}
    }
    
    //Delete copyright holders that are not showing up in media_copyright_holders_to_media_library
    function delete_unused_media_copyright_holders(){
    	$query="DELETE media_copyright_holders FROM media_copyright_holders LEFT JOIN media_copyright_holders_to_media_library ON media_copyright_holders.id=media_copyright_holders_to_media_library.media_copyright_holder WHERE media_copyright_holders_to_media_library.media_copyright_holder IS NULL;";
    	return ($this->d->q($query));
    }
    
    //Get a string for the input value (on load of a resource).
    function get_media_copyright_holder_string_for_resource($lib_id,$include_copyright_holder_id=true){
    	$t="";
    	if ($r=$this->get_media_copyright_holders_for_resource($lib_id)){
    		foreach ($r as $v){
    			$t.=", ".$v["name"];
    			if ($include_copyright_holder_id){
    				$t.=" (#".$v["id"].")";
    			}
    		}
    		if ($t!=""){
    			$t=substr($t,2); //cut first comma
    		}
    	}
    	return $t;
    }
    
    
    /* media_authors to media_library */
    
    function assign_media_author_to_resource($media_author,$lib_id){
    	if (!$this->resource_has_media_author($lib_id,$media_author)){
    		//Association does not yet exist
    		$e=array();
    		$e["lib_id"]=$lib_id;
    		$e["media_author"]=$media_author;
    		return $this->d->insert_and_get_id($e,"media_authors_to_media_library");
    	}
    	return true; //If association exists then technically we're succesful
    }
    
    function unassign_media_author_from_resource($media_author,$lib_id){
    	return $this->d->q("DELETE FROM media_authors_to_media_library WHERE lib_id=$lib_id AND media_author=$media_author;");
    }
    
    function unassign_all_media_authors_from_resource($lib_id){
    	return $this->d->q("DELETE FROM media_authors_to_media_library WHERE lib_id=$lib_id;");
    }
    
    function resource_has_media_author($lib_id,$media_author){
    	if ($res=$this->d->q("SELECT id FROM media_authors_to_media_library WHERE lib_id=$lib_id AND media_author=$media_author;")){
    		return ($res->num_rows>0);
    	}
    }
    
    function get_media_authors_for_resource($lib_id){
    	$query="
    		SELECT DISTINCT
    			media_authors.*
    		FROM
    			media_authors,media_authors_to_media_library
    		WHERE
    			media_authors_to_media_library.lib_id=$lib_id
    		AND
    			media_authors_to_media_library.media_author=media_authors.id
    		ORDER BY
    			media_authors.name;
    	";
    	$t=array();
    	if ($res=$this->d->q($query)){
    		while ($r=$res->fetch_assoc()){
    			$t[]=$r;
    		}
    	}
    	return $t;
    }
    
    
    /* Media authors */
    
    //Add new or just return ID of extant record
    function add_media_author($name){
    	$existing_id=$this->get_media_author_id($name);
    	if ($existing_id==0){
    		$e=array();
    		$e["name"]=$name;
    		$result=$this->d->insert_and_get_id($e,"media_authors");
    		return $result;
    	} else {
    		return $existing_id;
    	}
    }
    
    function get_media_author_id($name){
    	if ($res=$this->d->q("SELECT id FROM media_authors WHERE name=\"$name\";")){
    		if ($r=$res->fetch_assoc()){
    			return $r["id"];
    		}
    	}
    	return false;
    }
    
    function get_media_author_record($id){
    	if ($res=$this->d->q("SELECT * FROM media_authors WHERE id=$id;")){
    		if ($r=$res->fetch_assoc()){
    			return $r;
    		}
    	}
    	return false;
    }
    
    function get_media_author_name($id){
    	if ($r=$this->get_media_author_record($id)){
    		return $r["name"];
    	}
    	return false;
    }
    
    function delete_media_author($id_or_name){
    	if ($id_or_title>0){
    		return ($this->db->query("DELETE FROM media_authors WHERE id=$id_or_name;"));
    	} else {
    		return ($this->db->query("DELETE FROM media_authors WHERE name=\"$id_or_name\";"));
    	}
    }
    
    //Delete copyright holders that are not showing up in media_authors_to_media_library
    function delete_unused_media_authors(){
    	$query="DELETE media_authors FROM media_authors LEFT JOIN media_authors_to_media_library ON media_authors.id=media_authors_to_media_library.media_author WHERE media_authors_to_media_library.media_author IS NULL;";
    	return ($this->d->q($query));
    }
    
    //Get a string for the input value (on load of a resource).
    function get_media_author_string_for_resource($lib_id,$include_author_id=true){
    	$t="";
    	if ($r=$this->get_media_authors_for_resource($lib_id)){
    		foreach ($r as $v){
    			$t.=", ".$v["name"];
    			if ($include_author_id){
    				$t.=" (#".$v["id"].")";
    			}
    		}
    		if ($t!=""){
    			$t=substr($t,2); //cut first comma
    		}
    	}
    	return $t;
    }
    
    
    //**** Auto complete
    
    //Return JSON
    function get_media_tags_autocomplete_suggestions($term){
    	$term=mysqli_real_escape_string($this->d->db,$term);
    	$query="
    	SELECT
    		id,title as label
    	FROM
    		media_tags
    	WHERE
    		title LIKE \"%$term%\"
    	ORDER BY
    		title;
    	";
    	return $this->d->select_flat_json($query);
    }
    
    
    //Return JSON.
    function get_resource_title_autocomplete_suggestions($term){
    	$term=mysqli_real_escape_string($this->d->db,$term);
    	$query="
	    	SELECT
		    	id,title
	    	FROM
		    	media_library
	    	WHERE
	    		title LIKE \"%$term%\"
	    	ORDER BY
	    		title;
    	";
    	if ($res=$this->d->q($query)){
	    	$t=array();
	    	while ($r=$res->fetch_assoc()){
	    		$t[]=$r;
	    	}
	    	//Now make JSON string
	    	$z="";
	    	foreach ($t as $v){
	    		$label=$v["title"];
	    		$value=$label;
	    		$z.=",
	    			{
				   		\"id\":\"".$v["id"]."\",
	    				\"label\":\"$label\",
	    				\"value\":\"$value\"
	    			}";
	    	}
        	//Cut first comma
        	if ($z!=""){
            	$z=substr($z,1);
            	$z="[ $z ]";
            	return $z;
    		}
    	}
    	return false;
    }
    
    //Return JSON
    function get_media_copyright_holders_autocomplete_suggestions($term){
    	$term=mysqli_real_escape_string($this->d->db,$term);
    	$query="
    		SELECT
    			*
    		FROM
    			media_copyright_holders
    		WHERE
    			name LIKE \"%$term%\"
	    	ORDER BY
    			name;
    	";
    	if ($res=$this->d->q($query)){
    		$t=array();
    		while ($r=$res->fetch_assoc()){
    			$t[]=$r;
    		}
    		//Now make JSON string
    		$z="";
    		foreach ($t as $v){
    			$label=$v["name"];
    			$value=$v["name"]." (#".$v["id"].")";
    			$z.=",
    				{
    					\"id\":\"".$v["id"]."\",
    					\"label\":\"$label\",
    					\"value\":\"$value\"
    				}";
    		}
    		//Cut first comma
        	if ($z!=""){
    				$z=substr($z,1);
    				$z="[ $z ]";
    				return $z;
    		}
    	}
    	return false;
    }
    
    //Return JSON
    function get_media_authors_autocomplete_suggestions($term){
    	$term=mysqli_real_escape_string($this->d->db,$term);
    	$query="
    		SELECT
    			*
    		FROM
    			media_authors
    		WHERE
    			name LIKE \"%$term%\"
    		ORDER BY
    			name;
    	";
    	if ($res=$this->d->q($query)){
	    	$t=array();
	    	while ($r=$res->fetch_assoc()){
	    		$t[]=$r;
	    	}
	    	//Now make JSON string
    		$z="";
    		foreach ($t as $v){
    			$label=$v["name"];
    			$value=$v["name"]." (#".$v["id"].")";
    			$z.=",
    				{
    					\"id\":\"".$v["id"]."\",
    					\"label\":\"$label\",
    					\"value\":\"$value\"
    			}";
    		}
    		//Cut first comma
    		if ($z!=""){
    			$z=substr($z,1);
    			$z="[ $z ]";
    			return $z;
	    	}
    	}
    	return false;
    }
    
}

?>
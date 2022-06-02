<?php

  /* Create session ID - taken from LifeLog */
	function create_sessionid($length)
	{
		$sid="";
		for ($i=0;$i<$length;$i++)
		{
			$r=mt_rand(48,122);  //45-57, 65-90, 97-122
			while ( (($r>57)&&($r<65)) or (($r>90)&&($r<97)) )
			{ //get one digit
				$r=mt_rand(48,122);				
			}
			$sid=$sid.chr($r);
		}
		return $sid;
	}

  //If $file is an absolute path return the CW relative path
  function get_cw_relative_path($file){
    //We must assume that $file may be an absolute path and calculate the relative path from CW
    $pos=strpos($file,CW_ROOT_UNIX);
    //Is the first part of $file the unix root for CW?
    if (($pos==0) && (!($pos===false))) {
      //Yes -> cut it away
      $file=substr($file,strlen(CW_ROOT_UNIX));
    }  
    return $file;
  }
  
  //Return the filename without path 
  function get_filename($file){
    $n=strpos($file,"/");
    if ($n!==false){
      return substr($file,($n+1));
    }
    return $file;
  }

  //Extract extension from filename
  function get_extension($file){
    $n=strrpos($file,'.');
    if ($n!==false){
      return substr($file,$n+1);
    }
    //No dot found = empty extension
    return "";
  }
  
  function get_filename_without_extension($file){
    $n=strrpos($file,'.');
    if ($n!==false){
      return substr($file,0,$n);
    }
    //No dot found = empty extension, so return whole thing
    return $file;    
  }

  //Return "yes" or "no" from bool,int,string
  function get_yes_no($i){
    if (($i>0) or ($i)) { return "yes"; }
    return "no";
  }
  
  //Return "CHECKED" if $i evaluates to 'yes'
  function get_checked($i){
    if (($i>0) or ($i)) { return "CHECKED"; }
    return "";
  }
  
  //Return a checkbox tag
  function get_checkbox($cb_name,$cb_title,$checked){
    return "<input type='checkbox' name='$cb_name' ".get_checked($checked)."/> ".$cb_title;
  }
  
  //If First string Empty Return 2nd string. (Otherwise return 1st string.)
  function ifer2($s1,$s2){
    if ($s1==""){
      return $s2;
    }
    return $s1;
  }
  
  //Returns an array with the $fields (comma-seperated string) from array $a, if extant
  function copy_from_array($a=array(),$fields){
    $e=array();
    $fields=explode(',',$fields);
    foreach ($fields as $v){
      if (isset($a["$v"])){      
        $e["$v"]=$a["$v"];
      }
    }
    return $e;
  }
  
  //For form processing:
  //Return an array with all set keys in $_POST the names of which begin with $substr
  function copy_from_POST($substr){
    $e=array();
    foreach ($_POST as $k=>$v){
      if (substr($k,0,strlen($substr))==$substr){
        //Found candidate
        $e[]=substr($k,strlen($substr));
      }
    }
    return $e;
  }
  
  //A non-empty string evaluates to 1, empty string to 0
  function str_to_tinyint($s){
    if (strlen($s)>0){
      return 1;
    }
    return 0;
  }
  
  function zerofill($s,$length){
    while (strlen($s)<$length){
     $s="0".$s;  
    }
    return $s;  
  }
  
  //Take one dimensional array and return as JSON
  function one_dim_array_to_json($a){
    $t="{ ";
    foreach ($a as $k=>$v){
       $t.="\"$k\":\"$v\",";
    }
    $t=substr($t,0,-1); //cut last commma
    $t.=" }";                      
    return $t;
  }
  
  //Is this string an alpha word (letters only)?
  function is_alpha($s){
    //Always true for now - use regex later!
    return true;  
  }
  
  //Are all array elements alpha words?
  function all_elements_alpha($t){
    if (is_array($t)){
      foreach ($t as $v){
        if (!is_alpha($v)){
          return false;
        }
      }
      return true;    
    }
    return false;
  }
  
  //Replace <br/><br/> with <hr>
  function double_br_to_hr($t){
    return preg_replace('/^<br *\/>$/m','<hr>',$t);
    return $t;
  }
           
  function space_after_linebreak_to_nsbp($t){
    return str_replace("\n ","\n&nbsp;",$t);      
  }
  
  function double_newline_to_single_newline($t){
    return str_replace("\n\n","\n",$t);
  }
  
  //Is the string an extension for a media file?
  function is_mediafile_extension($t){
    $media_extensions=array("wmv","mp3","mp4","mpg","wav","avi","flv","mpeg","vob","aif","aiff","ogg");
    return in_array(strtolower($t),$media_extensions);  
  }

  //Is the string an extension for a (browser-displayable) image file?
  function is_imagefile_extension($t){
    $image_extensions=array("jpg","jpeg","gif","png");
    return in_array(strtolower($t),$image_extensions);  
  }
  
  //Is the string an extension for a sibelius or finale file?
  function is_notation_extension($t){
    $notation_extensions=array("sib","mus");
    return in_array(strtolower($t),$notation_extensions);  
  }
  
  function bytes_to_human_readable_filesize($n){
    $unit=0;
    while ($n>1024){
      $n=$n/1024;
      $unit++;
    }
    ($unit==0) ? $t="bytes" : null;
    ($unit==1) ? $t="KB" : null;
    ($unit==2) ? $t="MB" : null;
    ($unit==3) ? $t="GB" : null;
    ($t=="bytes") ? $dec=0 : $dec=1;
    return number_format($n,$dec)." ".$t;
  }
  
  function extract_full_words($t,$minchars=70){
    $i=$minchars;
    while((substr($t,$i,1)!=" ") && ($i<strlen($t))){
      $i++;    
    }
    return substr($t,0,$i);
  }

	//Returns the position of the first space in $s after $n characters
	function first_space_after_length($s,$n){
		$tmp=substr($s,$n); 		//This is the substring in which the first space is to be found
		$tmp_pos=strpos($tmp," ");	//Position of the desired space within $tmp
		return ($n+$tmp_pos);
	}
  
  function first_space_before_length($s,$n){
		$tmp=substr($s,0,$n); 		//This is the substring in which the last space is to be found
		return strrpos($tmp," ");	//Position of the desired space within $tmp
  }
  
  function shorten($s,$max_chars){
    if (strlen($s)>$max_chars){
      return substr($s,0,first_space_before_length($s,$max_chars))."...";    
    }
    return $s;
  }
  
  function replace_double_linebreak($t,$replacement=" |<br>"){
    return preg_replace('/\\n\n/m',$replacement,$t);      
  }
  
  function replace_linebreak($t,$replacement=" / "){
    return preg_replace('/\\n/m',$replacement,$t);    
  }
  
  
  //Returns true if $a[x][field] is empty for all x
  function subfield_is_empty($a,$field){
    if (is_array($a)){
      foreach ($a as $v){
        if (!empty($v[$field])){
          //Found a non-empty subfield, abort
          return false;
        }
      }      
      //If we arrived here, there was only empty subfields
      return true;
    }
    return false;
  }
  
  //Return hex for different background colors
  function get_bg_color($n){
    $color="#FFFFFF";
    if ($n==1){ $color="#FEE"; }
    elseif ($n==2){ $color="#EFE"; }
    elseif ($n==3){ $color="#EFF"; }
    elseif ($n==4){ $color="#FEF"; }
    elseif ($n==5){ $color="#FFE"; }
    return $color;  
  }
  
  
  
  //Functions for comma separated list

  function csl_get_element_pos($str,$element){
    $list=explode(',',$str);
    $r=array_search($element,$list);
    if ($r!==false){
      return $r+1;    
    }
    return false;
  }

  function csl_add_element_at_position($str,$new_element,$position){
    $list=explode(',',$str);
    array_splice($list,$position-1,0,$new_element);
    $res=trim(implode(',',$list));
    if (substr($res,-1)==','){
      $res=substr($res,0,-1);
    }
    return $res;    
  } 
  
  function csl_delete_element_at_position($str,$position){
    $list=explode(',',$str);
    array_splice($list,$position-1,1);
    return implode(',',$list);
  }
  
  function csl_move_element($str,$old_pos,$new_pos){
    $list=explode(',',$str);
    $element=$list[$old_pos-1];
    $str=csl_delete_element_at_position($str,$old_pos);
    return csl_add_element_at_position($str,$element,$new_pos);  
  }
  
  function csl_append_element($str,$new_element,$strict=false){
    if (empty($str)){
      return $new_element;
    } else {
      if ((!$strict) || (csl_get_element_pos($str,$new_element)===false)){
        return $str.",".$new_element;      
      } else {
        return $str;
      }
    }
  }  
  
  function csl_append_elements($str,$array,$strict=false){    
    if (!is_array($array)){
      $array=explode(',',$array);
    }
    foreach ($array as $v){
      if (!empty($v)){
        $str=csl_append_element($str,$v,$strict);
      }
    }  
    return $str;
  }
    
  function csl_delete_element($str,$element){
    if ($n=csl_get_element_pos($str,$element)){
      return csl_delete_element_at_position($str,$n);
    }
    return $str;
  }
  
  function csl_create_from_array($a){
  	$r="";
  	foreach ($a as $v){
  	  $r.=",$v";	
  	}
  	return substr($r,1);
  }
  
  function int_to_1st2nd3rd($i){
    switch ($i){
      case 0: return ""; break;
      case 1: return "first"; break;
      case 2: return "second"; break;
      case 3: return "third"; break;
    }
    return $i."th";
  }
  
  function plural($t){
    //put plural s only when last character of position name is alpha (i.e. exclude something like guitarist (acoustic)))
    if (preg_match("/^[a-z]$/",substr($t,-1))){
      $t.="s"; //plural s                
    }  
    return $t;
  }
  
  //Euclid!
  function gcd($num,$den){
  	$a=$num;
  	$b=$den;
  	while($a!=$b){
  		$c=min($a,$b);
  		$d=abs($a-$b);
  		$a=$c;
  		$b=$d;
  	}
  	return $a;
  }

  //Resize with no regard for aspect
  function resize_image($orig,$width=120,$height=80,$destroy_orig=true){
  	$dest=imagecreatetruecolor($width,$height);
  	imagecopyresampled($dest, $orig, 0, 0, 0, 0, $width, $height, imagesx($orig), imagesy($orig));
  	if ($destroy_orig){
  		imagedestroy($orig);
  	}
  	return $dest;
  }
  
  //Crop to given aspect without resizing
  function crop_image($orig,$aspect_x,$aspect_y){
  	$factor=$aspect_x/$aspect_y;
  	$orig_width=imagesx($orig);
  	$orig_height=imagesy($orig);
  	if ($factor<imagesx($orig)/imagesy($orig)){
  		//image too wide: source_x must be >0, and source_width must be source_orig_width-2*source_x
  		$dest_width=floor($orig_height*$factor); //this is width of destination image
  		$dest_height=$orig_height;
  		$source_x=floor(($orig_width-$dest_width)/2);
  		$source_y=0;
  	} elseif ($factor>imagesx($orig)/imagesy($orig)) {
  		//image too high: source_y must be >0, and source_height must be source_orig_height-2*source_y
  		$dest_width=$orig_width;
  		$dest_height=floor($orig_width/$factor); //this is height of destination image
  		$source_x=0;
  		$source_y=floor(($orig_height-$dest_height)/2);
  	} else {
  		//image does not need cropping
  		return $orig;
  	}
  	$dest=imagecreatetruecolor($dest_width,$dest_height);
  	imagecopyresampled($dest, $orig, 0, 0, $source_x, $source_y, $dest_width, $dest_height, $dest_width, $dest_height);
  	imagedestroy($orig);
  	return $dest;
  }
  
  function get_scaled_height($orig_w,$orig_h,$scaled_w){
	$r=$scaled_w/($orig_w/$orig_h);
  	return $r;
  }

  function get_scaled_width($orig_w,$orig_h,$scaled_h){
  	$r=$scaled_h*($orig_w/$orig_h);
  	return $r;
  }
  

  
?>
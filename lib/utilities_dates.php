<?php
	//Date-time related constants
  //DEFINED IN constants.php
  /*
	DEFINE ("WEEK",604800);
	DEFINE ("DAY",86400);
	DEFINE ("HOUR",3600);
	DEFINE ("MINUTE",60);
	*/
  
	//Number of full days in $secs seconds
	function days($secs) {
		return floor($secs/DAY);
	}

	//Number of full hours in $secs seconds
	function hours($secs) {
		return floor($secs/HOUR);
	}
	
	//Number of full minutes in $secs seconds
	function minutes($secs) {
		return floor($secs/MINUTE);
	}

	//Determine whether the two timestamps belong to the same day
	function isSameDay($time1,$time2) {
		return (date("Ymd",$time1)==date("Ymd",$time2));
	}

	//Determine whether the timestamp belongs to today
	function isToday($time) {
		return (isSameDay($time,time()));
	}
	
	function isYesterday($time){
		return (isToday($time+DAY));
	}
	
	//Determine whether the timestamp belongs to today
	function isTomorrow($time) {
		return (isSameDay($time,time()+DAY));
	}

	//Timestamp in the future?
	function isFuture($time) {
		return ($time>time());
	}
	
	//Timestamp in the past?
	function isPast($time) {
		return ($time<time());
	}

	//Timestamp before 00:00:00 today?
	function isBeforeToday($time) {
		return (getBeginningOfDay(time())>$time);
	}

	//Timestamp after 23:59:59 today?
	function isAfterToday($time) {
		return ((getBeginningOfDay(time())+DAY)<=$time);
	}

	
	//Determine whether the timestamp is less than 1 week old
	function isThisWeek($time) {
		return ($time>(time()-(86400*7)));
	}
	
	//Two timestamps within the same week? ($shift is shift from Sunday 00:00:00)
	function isSameWeek($time1,$time2,$shift=DAY){
		/*
    // This fails if the timestamps come from different years
    return (date("W",$time1-$shift)==date("W",$time2-$shift));
    */
    return (getBeginningOfWeek($time1,$shift)==getBeginningOfWeek($time2,$shift));
	}
	
	//Return timestamp of the beginning of the week that $time is in. $shift defaults such that the week begins MON not SUN
	function getBeginningOfWeek($time,$shift=DAY){
		//What day of week (w) is $time? Then: getBeginningOfDay($time)-w*DAY
		$w=date("w",$time)-round($shift/DAY);
		if ($w<0) {$w+=7;}
		return (getBeginningOfDay($time-($w*DAY)));
	}
	
	function getEndOfWeek($time){
		return (getBeginningOfWeek($time)+(WEEK-1));
	}

	//Is this timestamp within the current week?
	function isCurrentWeek($time){
		return (($time>getBeginningOfWeek(time())) && ($time-getBeginningOfWeek(time())<WEEK));
	}
	
	//Return Day of week (monday=0, sunday=6)
	function getDayOfWeek($time,$shift=DAY){
		$w=date("w",$time)-round($shift/DAY);
		if ($w<0) {$w+=7;}
		return $w;
	}
	
	//Get the first timestamp on the day
	function getBeginningOfDay($timestamp) {
		if ($timestamp=="") { $timestamp=0; }
		return mktime(0,0,0,date("n",$timestamp),date("j",$timestamp),date("Y",$timestamp));
	}
	
	//Get the timestamp of noon on this day
	function getNoonOfDay($timestamp) {
		return getBeginningOfDay($timestamp)+(HOUR*12);
	}

	//Get the last timestamp on the day
	function getEndOfDay($timestamp) {
		return getBeginningOfDay($timestamp)+(DAY-1);
	}
	
	//Get the first timestamp on the month
	function getBeginningOfMonth($timestamp) {
		return mktime(0,0,0,date("n",$timestamp),1,date("Y",$timestamp));
	}

	function getBeginningOfNextMonth($timestamp,$number_of_months_ahead=1){
		return mktime(0,0,0,date("n",$timestamp)+$number_of_months_ahead,1,date("Y",$timestamp));
	}
  
  function getEndOfMonth($timestamp){
    return getBeginningOfNextMonth($timestamp)-1;
  }

	function getBeginningOfNextYear($timestamp){
		$year=date("Y",$timestamp);
		$year++;
		return mktime(0,0,0,1,1,$year);
	}
  
  function isSameMonth($time1,$time2){
    return (getBeginningOfMonth($time1)==getBeginningOfMonth($time2));
  }

	//Get first timestamp of year
	function getBeginningOfYear($timestamp){
		return mktime(0,0,0,1,1,date("Y",$timestamp));
	}
  
	function getBeginningOfPreviousMonth($timestamp) {
    $month=date("n",$timestamp);
    $year=date("Y",$timestamp);
		return mktime(0,0,0,$month-1,1,$year);
	}
  
	function getBeginningOfPreviousYear($timestamp) {
    $year=date("Y",$timestamp);
		return mktime(0,0,0,1,1,$year-1);
	}

	//Turn $seconds into something lik 10m, 2h, 5d, 3wks
	//$smallestunit can by  "m" (minute) "d" (day), "w" week etc.
	function getHumanReadableLengthOfTime($seconds,$smallestunit="m") {
		$w=floor($seconds/WEEK);
		$d=floor($seconds/DAY);
		$h=floor($seconds/HOUR);
		$m=floor($seconds/MINUTE);
		//For more than 9 weeks all we want is the number of weeks
		if ($w>=9){
			$result=$w."wks";
		//For between 2 and 9 weeks we want sth like 4w 3d
		} elseif ($w>=2) {
			$result=$w."w"; //Full weeks
			if ($d>$w*7) { //How many days on top? $d-($w*7)
				$result.=" ".($d-$w*7)."d";
			}
			if ($smallestunit=="d"){ return $result; }
		//For anything between 2d and 2wks we want something like 12d 4h
		} elseif ($d>=2) {
			$result=$d."d"; //Full days
			if ($smallestunit=="d"){ return $result; }
			if ($h>$d*24) { //How many ours on top?
				$result.=" ".($h-$d*24)."h";
			}
		//For anything between 10hrs and 2days we just want hours: 49hrs
		} elseif ($h>=10) {
			if ($smallestunit=="d"){ return "1d"; }
			$result=$h."h";
		//For anything between 1h and 1d we want something like 12h 4m
		} elseif ($h>=1) {
			$result=$h."h"; //Full hours
			if ($m>$h*60) { //How many minutes on top?
				$result.=" ".($m-$h*60)."m";
			}
		//For anything between 1m and 1h we want something like 53m
		} elseif ($m>=1) {
			$result=$m."m";
		} elseif ($seconds>0){
			$result="<1m";
		} else {
			$result="";
		}
		return $result;	
	}
	
	//How many full days is the timestamp in the future (seen from now, or $reference)? -1 simply means it is past.
	function getDaysOut($timestamp,$reference=0){
		if ($reference==0) { $reference=time(); }
		if ($timestamp-time()>=0){
			return floor(abs($timestamp-$reference)/DAY);
		} else {
			return -1;
		}
	}
	
	//Convert month abbreviation to month number
	function month_abbr_to_nr($s){
		switch (strtolower($s)){
			case "jan": return 1; break;
			case "feb": return 2; break;
			case "mar": return 3; break;
			case "apr": return 4; break;
			case "may": return 5; break;
			case "jun": return 6; break;
			case "jul": return 7; break;
			case "aug": return 8; break;
			case "sep": return 9; break;
			case "oct": return 10; break;
			case "nov": return 11; break;
			case "dec": return 12; break;
		}
	}
  
  //Takes timestamp for birthday and returns age in years
  function get_age_in_years($birthday,$reference=0){
    /*  
      Pseudo code:
        get year, month, day of birth
        get year, month, day of today
        if this year's bd is coming up still, we have now_year - birth_year -1 for age
    */  
    $by=date("Y",$birthday);
    $bm=date("n",$birthday);
    $bd=date("j",$birthday);
    $now=time();
    //If second argument was passed in...
    if ($reference!=0){
      $now=$reference;    
    }
    $y=date("Y",$now);
    $m=date("n",$now);
    $d=date("j",$now);
    $age=$y-$by; //Age is birth year minus now-year...
    //...unless birthday this year is still ahead: in this case subtract one
    $birthday_this_year=mktime(0,0,0,$bm,$bd,$y);
    if (isFuture($birthday_this_year)){
      $age--;
    }
    return $age;        
  }
  
  function timestamp_to_ddmmyyyy($timestamp){
    $y=date("Y",$timestamp);
    $m=date("m",$timestamp);
    $d=date("d",$timestamp);
    return "$d/$m/$y";
  }
  
  //Int to day of week
  function int_to_dow($i){
		switch ($i){
			case 1: return "Monday"; break;
			case 2: return "Tuesday"; break;
			case 3: return "Wednesday"; break;
			case 4: return "Thursday"; break;
			case 5: return "Friday"; break;
			case 6: return "Saturday"; break;
		}  
    return "Sunday";
  }
  
  function hours_for_select($selected=0){
    $t="";
    for ($i=0;$i<=23;$i++){
      ($i<10) ? $n="0" : $n="";
      ($selected==$i) ? $sel="selected='SELECTED'" : $sel="";
      $t.="<option value='$i' $sel>$n$i</option>";
    }
    return $t;
  }
  
  function minutes_for_select($selected=0){
    $t="";
    for ($i=0;$i<=59;$i++){
      ($i<10) ? $n="0" : $n="";
      ($selected==$i) ? $sel="selected='SELECTED'" : $sel="";
      $t.="<option value='$i' $sel>$n$i</option>";
    }
    return $t;  
  }
  
  //Array of timestamp csl's to a bunch of checkboxes
  function get_checkboxes_for_timestamps($x=array(),$checked=true,$class="timestamp_cb"){
    $t="";
    $i=1;
    $checked ? $checked="checked='CHECKED'" : $checked="";
    foreach ($x as $v){
      $times=explode(",",$v);
      foreach ($times as $v2){
        //the id for the checkbox is ts_ followed by service instnace no, dot, timestamp
        $t.="<input class='$class' type='checkbox' id='ts_$i.$v2' $checked> ".date("l F j Y, h:ia",$v2)."<br/>";      
      }
      if ((sizeof($times)>1) && ($i<sizeof($x))){
        $t.="<div style='height:1px;margin:0px;padding:0px;border-bottom:1px dotted gray;'></div>";      
      }
      $i++;    
    }
    return $t;  
  }
  
  function adjust_timestamp_for_dst($timestamp){
    $dst_ts=date("I",$timestamp);
    $dst_now=date("I",time());
    if ($dst_ts>$dst_now){
      $timestamp-=HOUR;
    } elseif ($dst_ts<$dst_now){
      $timestamp+=HOUR;
    }
    return $timestamp;  
  }
  
	

?>
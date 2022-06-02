<?php

class cw_Scripture_refs {

    private $d; //Database access
    
    
    function check_for_table($table="scripture_refs"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE scripture_refs (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          start INT,
                          end INT,
                          alt_string varchar(100)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE scripture_refs_to_service_elements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          scripture_ref INT,
                          service_element INT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE scripture_refs_to_music_pieces (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          scripture_ref INT,
                          music_piece INT
                        )")));
                        
      /*
        scripture_refs
          - alt_string: use this when user-provided scripture reference cannot be determined from string
      */
    }

    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      if ($this->check_for_table("scripture_refs")){
        $this->d->drop_table("scripture_refs");
      }
      if ($this->check_for_table("scripture_refs_to_service_elements")){
        $this->d->drop_table("scripture_refs_to_service_elements");
      }
      if ($this->check_for_table("scripture_refs_to_music_pieces")){
        $this->d->drop_table("scripture_refs_to_music_pieces");
      }
      if ($this->create_tables()){
        return true;                          
      }
      return false;
    }

    /* Scripture refs: create, delete */
    
    function add_scripture_ref($start,$end=0,$alt_string=""){
      $e=array();
      if ($start>0){
        if ($end==0){
          $end=$start;
        }
        $e["start"]=$start;
        $e["end"]=$end;
      } else {
        $e["alt_string"]=$alt_string;        
      }
      return $this->d->insert_and_get_id($e,"scripture_refs");
    }
    
    function delete_scripture_ref($id){
      return $this->d->delete($id,"scripture_refs");
    }
    
    function get_scripture_refs_record($id){
      return $this->d->get_record("scripture_refs","id",$id);
    }
    
    /* Scripture refs to service elements */
    
    function assign_scripture_ref_to_service_element($scripture_ref_id,$service_element_id){
      if (($scripture_ref_id>0) && ($service_element_id>0)){
        $e=array();
        $e["scripture_ref"]=$scripture_ref_id;
        $e["service_element"]=$service_element_id;
        return $this->d->insert($e,"scripture_refs_to_service_elements");      
      }
      return false;
    }
    
    function unassign_scripture_ref_from_service_element(){
    
    }
    
    function get_scripture_ref_records_for_service_element($service_element_id){
      $t=array();
      $query="
        SELECT
          scripture_refs.*
        FROM
          scripture_refs,scripture_refs_to_service_elements
        WHERE
          scripture_refs.id=scripture_refs_to_service_elements.scripture_ref
        AND
          scripture_refs_to_service_elements.service_element=$service_element_id
        ORDER BY
          scripture_refs.start;
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }
    
    function delete_scripture_ref_records_for_service_element($service_element_id){
      //Get scripture ref records and delete them
      if ($recs=$this->get_scripture_ref_records_for_service_element($service_element_id)){
        foreach ($recs as $v){
          $this->d->query("DELETE FROM scripture_refs WHERE id=".$v["id"]);
        }
      }
      //Unassign from service element
      return ($this->d->query("DELETE FROM scripture_refs_to_service_elements WHERE service_element=$service_element_id;"));
    }
    
    function get_scripture_ref_string_for_service_element($service_element_id,$mark_unrecognized=false){
      $t="";
      if ($r=$this->get_scripture_ref_records_for_service_element($service_element_id)){
        //If first record has field $alt_string - just return that and ignore the potential rest
        if (!empty($r[0]["alt_string"])){
          $t=$r[0]["alt_string"];
          if ($mark_unrecognized){
            $t="<span style='color:red;'>".$t."</span>";
          }
        } else {
          //Go once through to weed out doubles and inconsistencies
          $y=array();
          $prev=array("start"=>1,"end"=>1);
          foreach ($r as $v){
            if ($v["start"]<=$prev["end"]){
              //$v begins within the previous
              if ($v["end"]>$prev["end"]){
                //$v ends after the previous one: drop previous record and lengthen current
                array_pop($y);
                $y[]=array("start"=>$prev["start"],"end"=>$v["end"]);
              } else {
                //$v is completely contained in the previous one - do nothing
              }            
            } else {
              if ($v["start"]-$prev["end"]==1){
                //$v starts with NEXT verse after end of $x: drop previous rec and lengthen current
                array_pop($y);
                $y[]=array("start"=>$prev["start"],"end"=>$v["end"]);              
              } else {
                //Reference doesnt touch previous - simply store
                $y[]=$v;
              }
            }   
            $prev=$v;
          }
          $last_end=0;
          foreach ($y as $v){
            //$v has scripture_ref record
            //Get references for both the beginning of this and the end of the last record
            $a=explode(',',$this->int_to_scripture_ref($last_end));
            $b=explode(',',$this->int_to_scripture_ref($v["start"]));
            /*
              If $b is a reference to the same chapter as the one before, just add -verse
              If $b is a reference to the same book but diff. chapter, add -chapter:verse
              If $b is a reference to a diff. book, add full string
            */
            if ($b[0]!=$a[0]){
              //2nd ref starts with different book - add entire range string
              $t.="; ".$this->range_to_scripture_string($v["start"],$v["end"],false);
            } elseif ($a[1]!=$b[1]){
              //2nd ref starts with different chapter - add range string with first book omitted
              $t.=",".$this->range_to_scripture_string($v["start"],$v["end"],false,true);
            } elseif ($a[2]!=$b[2]){
              //2nd ref begins in same chapter - add range string with first chapter omitted
              $t.=",".$this->range_to_scripture_string($v["start"],$v["end"],false,true,true);
            }
            $last_end=$v["end"]; //Save the ref to the last verse of this record
          }
        }
      }
      if (substr($t,0,2)=="; "){
        $t=substr($t,2);
      } 
      return $t;
    }
    
    /* Scripture refs to music pieces */
    
    function assign_scripture_ref_to_music_piece($scripture_ref_id,$music_piece_id){
      if (($scripture_ref_id>0) && ($music_piece_id>0)){
        $e=array();
        $e["scripture_ref"]=$scripture_ref_id;
        $e["music_piece"]=$music_piece_id;
        return $this->d->insert($e,"scripture_refs_to_music_pieces");      
      }
      return false;
    }
    
    //Unassign and delete reference identified by scripture_refs_id
    function unassign_scripture_ref_from_music_piece($scripture_ref_id){
      $r=$this->get_scripture_refs_record($scripture_ref_id);
      if ($r!==false){
        //Got record in question, delete
        $this->delete_scripture_ref($scripture_ref_id);
        //Delete assignment (don't need music_piece_id because we know that for each reference exists exactly one assignment)
        $this->d->q("DELETE FROM scripture_refs_to_music_pieces WHERE scripture_ref=$scripture_ref_id");        
      } 
      return true; //Scripture ref record never existed - technically successful   
    }
    
    function get_scripture_ref_records_for_music_piece($music_piece_id){
      $t=array();
      $query="
        SELECT
          scripture_refs.*
        FROM
          scripture_refs,scripture_refs_to_music_pieces
        WHERE
          scripture_refs.id=scripture_refs_to_music_pieces.scripture_ref
        AND
          scripture_refs_to_music_pieces.music_piece=$music_piece_id
        ORDER BY
          scripture_refs.start;
      ";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }
    
    //We are assuming that each scripture ref record is used only by one of the assignment tables and can be deleted if the assignment is no longer present     
    function delete_scripture_ref_records_for_music_piece($music_piece_id){
      //Get scripture ref records and delete them
      if ($recs=$this->get_scripture_ref_records_for_music_piece($music_piece_id)){
        foreach ($recs as $v){
          $this->d->query("DELETE FROM scripture_refs WHERE id=".$v["id"]);
        }
      }
      //Unassign from music_piece
      return ($this->d->query("DELETE FROM scripture_refs_to_music_pieces WHERE music_piece=$music_piece_id;"));
    }
    


    
    /* Scripture refs proper */
    
/*
	Functions to store and search scripture references
	
	Johannes Weber, Jan 4/5 2011
*/



	/*This array contains the names of the books, and the second element in each value is the official abbrevation.
	Further elements represent additional valid reference strings */
	private $books=array(	"Genesis,Gen,Gn,Ge,1 Mo,1 Mose,1. Mose,1Mo,1Mose,1.Mose,1.Mo",
					"Exodus,Ex,Exo,Exod,2 Mo,2 Mose,2. Mose,2Mo,2Mose,2.Mose,2.Mo",
					"Leviticus,Lev,Lv,3 Mo,3 Mose,3. Mose,3Mo,3Mose,3.Mose,3.Mo",
					"Numbers,Num,Nm,Nu,4 Mo,4 Mose,4. Mose,4Mo,4Mose,4.Mose,4.Mo",
					"Deuteronomy,Dt,Deu,Deut,De,5 Mo,5 Mose,5. Mose,5Mo,5Mose,5.Mose,5.Mo",
					"Joshua,Josh,Jo,Josua,Jos",
					"Judges,Jgs,Judg,Jdg,Juges,Richter",
					"Ruth,Ru,Rut",
					"1 Samuel,1 Sm,1 Sa,1 Sam,1Samuel,1Sm,1Sa,1Sam,1. Samuel,1.Samuel,1.Sam,1.Sa,1.Sm",
					"2 Samuel,2 Sm,2 Sa,2 Sam,2Samuel,2Sm,2Sa,2Sam,2. Samuel,2.Samuel,2.Sam,2.Sa,2.Sm",
					"1 Kings,1 Ki,1 Kg,1 Kgs,1Kings,1Ki,1Kg,1Kgs,1. Könige,1.Könige,1. Kön,1.Kön,1. Kö,1.Kö,1. Kge,1.Kge",
					"2 Kings,2 Ki,2 Kg,2 Kgs,2Kings,2Ki,2Kg,2Kgs,2. Könige,2.Könige,2. Kön,2.Kön,2. Kö,2.Kö,2. Kge,2.Kge",
					"1 Chronicles,1 Chr,1 Chron,1Chronicles,1Chr,1Chron,1. Chronik,1.Chronik,1 Chronik,1Chronik,1. Chr,1.Chr,1 Chr,1Chr",
					"2 Chronicles,2 Chr,2 Chron,2Chronicles,2Chr,2Chron,2. Chronik,2.Chronik,2 Chronik,2Chronik,2. Chr,2.Chr,2 Chr,2Chr",
					"Ezra,Ezr,Esra,Esr",
					"Nehemiah,Neh,Nehemia",
					"Esther,Est,Ester",
					"Job,Jb,Hiob",
					"Psalms,Ps,Pss,Psalm,Psalmen",
					"Proverbs,Prov,Prv,Sprüche,Spr",
					"Ecclesiastes,Eccl,Eccles,Prediger,Pred",
					"Song of Solomon,Sg,Song of Sol,Song of Songs,Hohelied,Hoheslied,Hoh",
					"Isaiah,Is,Isa,Jesaja,Jes",
					"Jeremiah,Jer,Jeremia",
					"Lamentations,Lam,Lm,Klagelieder,Klag,Kl,Klagelider,Klagelied,Klagelid,Klaglied,Klaglid,Klgl",
					"Ezekiel,Ez,Ezek,Hesekiel,Hes",
					"Daniel,Dan,Dn",
					"Hosea,Hos",
					"Joel,Jl",
					"Amos,Am",
					"Obadiah,Ob,Obad,Obadja",
					"Jonah,Jon,Jona",
					"Micah,Mic,Mi,Micha",
					"Nahum,Nah,Na",
					"Habakkuk,Hab,Hb,Habakuk",
					"Zephaniah,Zeph,Zep,Zefania,Zefanja,Zef,Zephanja",
					"Haggai,Hag,Hg",
					"Zechariah,Zech,Zec,Sacharja,Sach",
					"Malachi,Mal,Maleachi",
					"Matthew,Mt,Matt,Matthäus",
					"Mark,Mk,Markus",
					"Luke,Lk,Lukas",
					"John,Jn,Johannes",
					"Acts,Ac,Apostelgeschichte,Apg",
					"Romans,Rom,Ro,Römer,Röm",
					"1 Corinthians,1 Cor,1 Co,1Corinthians,1Cor,1Co,1. Korinther,1.Korinther,1Korinther,1 Kor,1.Kor,1Kor,1 Ko,1.Ko,1Ko",
					"2 Corinthians,2 Cor,2 Co,2Corinthians,2Cor,2Co,2. Korinther,2.Korinther,2Korinther,2 Kor,2.Kor,2Kor,2 Ko,2.Ko,2Ko",
					"Galatians,Gal,Galater",
					"Ephesians,Eph,Epheser",
					"Phillippians,Phil,Phi,Ph,Philipper,Philiper,Philliper,Phillipper",
					"Colossians,Col,Co,Kolosser,Kol",
					"1 Thessalonians,1 Thess,1 Thes,1 Th,1Thessalonians,1Thess,1Thes,1Th,1. Thessalonicher,1.Thessalonicher,1Thessalonicher,1. Thess,1.Thess,1Thess",
					"2 Thessalonians,2 Thess,2 Thes,2 Th,2Thessalonians,2Thess,2Thes,2Th,2. Thessalonicher,2.Thessalonicher,2Thessalonicher,2. Thess,2.Thess,2Thess",
					"1 Timothy,1 Tim,1 Tm,1 Ti,1Timothy,1Tim,1Tm,1Ti,1. Timotheus,1.Timotheus,1Timotheus,1. Tim,1.Tim",
					"2 Timothy,2 Tim,2 Tm,2 Ti,2Timothy,2Tim,2Tm,2Ti,2. Timotheus,2.Timotheus,2Timotheus,2. Tim,2.Tim",
					"Titus,Ti",
					"Philemon,Phlm,Philem",
					"Hebrews,Heb,He,Hebräer",
					"James,Jas,Ja,Jakobus,Jak",
					"1 Peter,1 Pet,1 Pt,1 Pe,1Peter,1Pet,1Pt,1Pe,1. Petrus,1.Petrus,1.Pet",
					"2 Peter,2 Pet,2 Pt,2 Pe,2Peter,2Pet,2Pt,2Pe,2. Petrus,2.Petrus,2.Pet",
					"1 John,1 Jn,1John,1Jn,1. Johannes,1.Johannes,1Johannes,1. Joh,1.Joh,1Joh,1. Jo,1.Jo,1Jo",
					"2 John,2 Jn,2John,2Jn,2. Johannes,2.Johannes,2Johannes,2. Joh,2.Joh,2Joh,2. Jo,2.Jo,2Jo",
					"3 John,3 Jn,3John,3Jn,3. Johannes,3.Johannes,3Johannes,3. Joh,3.Joh,3Joh,3. Jo,3.Jo,3Jo",
					"Jude,Judas,Jud",
					"Revelation,Rev,Rv,Apoc,Offenbarung,Offenb.,Offenb,Off,Off." );
	private $books_lc=array(); //This will be the same as above, just lowercase values

	/* Chapter and verse numbers below from: http://www.deafmissions.com/tally/bkchptrvrs.html */
	private $chapters=array(
					"31,25,24,26,32,22,24,22,29,32,32,20,18,24,21,16,27,33,38,18,34,24,20,67,34,35,46,22,35,43,55,32,20,31,29,43,36,30,23,23,57,38,34,34,28,34,31,22,33,26",
					"22,25,22,31,23,30,25,32,35,29,10,51,22,31,27,36,16,27,25,26,36,31,33,18,40,37,21,43,46,38,18,35,23,35,35,38,29,31,43,38",
					"17,16,17,35,19,30,38,36,24,20,47,08,59,57,33,34,16,30,37,27,24,33,44,23,55,46,34",
					"54,34,51,49,31,27,89,26,23,36,35,16,33,45,41,50,13,32,22,29,35,41,30,25,18,65,23,31,40,16,54,42,56,29,34,13",
					"46,37,29,49,33,25,26,20,29,22,32,32,18,29,23,22,20,22,21,20,23,30,25,22,19,19,26,68,29,20,30,52,29,12",
					"18,24,17,24,15,27,26,35,27,43,23,24,33,15,63,10,18,28,51,09,45,34,16,33",
					"36,23,31,24,31,40,25,35,57,18,40,15,25,20,20,31,13,31,30,48,25",
					"22,23,18,22",
					"28,36,21,22,12,21,17,22,27,27,15,25,23,52,35,23,58,30,24,42,15,23,29,22,44,25,12,25,11,31,13",
					"27,32,39,12,25,23,29,18,13,19,27,31,39,33,37,23,29,33,43,26,22,51,39,25",
					"53,46,28,34,18,38,51,66,28,29,43,33,34,31,34,34,24,46,21,43,29,53",
					"18,25,27,44,27,33,20,29,37,36,21,21,25,29,38,20,41,37,37,21,26,20,37,20,30",
					"54,55,24,43,26,81,40,40,44,14,47,40,14,17,29,43,27,17,19,08,30,19,32,31,31,32,34,21,30",
					"17,18,17,22,14,42,22,18,31,19,23,16,22,15,19,14,19,34,11,37,20,12,21,27,28,23,09,27,36,27,21,33,25,33,27,23",
					"11,70,13,24,17,22,28,36,15,44",
					"11,20,32,23,19,19,73,18,38,39,36,47,31",
					"22,23,15,17,14,14,10,17,32,03",
					"22,13,26,21,27,30,21,22,35,22,20,25,28,22,35,22,16,21,29,29,34,30,17,25,06,14,23,28,25,31,40,22,33,37,16,33,24,41,30,24,34,17",
					"6,12,8,8,12,10,17,9,20,18,7,8,6,7,5,11,15,50,14,9,13,31,6,10,22,12,14,9,11,12,24,11,22,22,28,12,40,22,13,17,13,11,5,26,17,11,9,14,20,23,19,9,6,7,23,13,11,11,17,12,8,12,11,10,13,20,7,35,36,5,24,20,28,23,10,12,20,72,13,19,16,8,18,12,13,17,7,18,52,17,16,15,5,23,11,13,12,9,9,5,8,28,22,35,45,48,43,13,31,7,10,10,9,8,18,19,2,29,176,7,8,9,4,8,5,6,5,6,8,8,3,18,3,3,21,26,9,8,24,13,10,7,12,15,21,10,20,14,9,6",
					"33,22,35,27,23,35,27,36,18,32,31,28,25,35,33,33,28,24,29,30,31,29,35,34,28,28,27,28,27,33,31",
					"18,26,22,16,20,12,29,17,18,20,10,14",
					"17,17,11,16,16,13,13,14",
					"31,22,26,6,30,13,25,22,21,34,16,6,22,32,9,14,14,7,25,6,17,25,18,23,12,21,13,29,24,33,9,20,24,17,10,22,38,22,8,31,29,25,28,28,25,13,15,22,26,11,23,15,12,17,13,12,21,14,21,22,11,12,19,12,25,24",
					"19,37,25,31,31,30,34,22,26,25,23,17,27,22,21,21,27,23,15,18,14,30,40,10,38,24,22,17,32,24,40,44,26,22,19,32,21,28,18,16,18,22,13,30,05,28,7,47,39,46,64,34",
					"22,22,66,22,22",
					"28,10,27,17,17,14,27,18,11,22,25,28,23,23,8,63,24,32,14,49,32,31,49,27,17,21,36,26,21,26,18,32,33,31,15,38,28,23,29,49,26,20,27,31,25,24,23,35",
					"21,49,30,37,31,28,28,27,27,21,45,13",
					"11,23,05,19,15,11,16,14,17,15,12,14,16,9",
					"20,32,21",
					"15,16,15,13,27,14,17,14,15",
					"21",
					"17,10,10,11",
					"16,13,12,13,15,16,20",
					"15,13,19",
					"17,20,19",
					"18,15,20",
					"15,23",
					"21,13,10,14,11,15,14,23,17,12,17,14,09,21",
					"14,17,18,6",
					
					"25,23,17,25,48,34,29,34,38,42,30,50,58,36,39,28,27,35,30,34,46,46,39,51,46,75,66,20",
					"45,28,35,41,43,56,37,38,50,52,33,44,37,72,47,20",
					"80,52,38,44,39,49,50,56,62,42,54,59,35,35,32,31,37,43,48,47,38,71,56,53",
					"51,25,36,54,47,71,53,59,41,42,57,50,38,31,27,33,26,40,42,31,25",
					"26,47,26,37,42,15,60,40,43,48,30,25,52,28,41,40,34,28,41,38,40,30,35,27,27,32,44,31",
					"32,29,31,25,21,23,25,39,33,21,36,21,14,23,33,27",
					"31,16,23,21,13,20,40,13,27,33,34,31,13,40,58,24",
					"24,17,18,18,21,18,16,24,15,18,33,21,14",
					"24,21,29,31,26,18",
					"23,22,21,32,33,24",
					"30,30,21,23",
					"29,23,25,18",
					"10,20,13,18,28",
					"12,17,18",
					"20,15,16,16,25,21",
					"18,26,17,22",
					"16,15,15",
					"25",
					"14,18,19,16,14,20,28,13,28,39,40,29,25",
					"27,26,18,17,20",
					"25,25,22,19,14",
					"21,22,18",
					"10,29,24,21,21",
					"13",
					"15",
					"25",
					"20,29,22,11,14,17,17,13,21,11,19,17,18,20,08,21,18,24,21,15,27,21"
					);

	//The numbers of the first verse of each book (calculated from the information above)
	private $books_start_ids=array(
					"1","1534","2747","3606","4894","5853","6511","7129","7214","8024","8719","9535","10254","11196",
					"12018","12298","12704","12871","13941","16402","17317","17539","17656","18948","20312","20466",
					"21739","22096","22293","22366","22512","22533","22581","22686","22733","22789","22842","22880",
					"23091","23146","24217","24895","26046","26925","27932","28365","28802","29059","29208","29363",
					"29467","29562","29651","29698","29811","29894","29940","29965","30268","30376","30481","30542",
					"30647","30660","30675","30700"
					);
	
	function __construct($d){
    $this->d = $d;
		//Fill the array that corresponds to the bookname array with lowercase version for internal purposes
		$this->books_lc=$this->array_values_to_lowercase($this->books);
	}
	
	//------HELP-FUNCTIONS--------------------------------------------------------------------------------------------------

	//Return a lowercase-value-version of the array
	private function array_values_to_lowercase($a){
		foreach ($a as $key=>$value){
			$a[$key]=strtolower($value);
		}
		return $a;
	}
	
	//Gets element n from a comma-separated string
	private function get_element($n,$s){
		$x=explode(',',$s);
		return $x[$n];
	}
	
	private function get_first_element($s){
		return $this->get_element(0,$s);
	}

	//Return total number of verses in chapters before $chapternr
	private function get_total_verses_in_previous_chapters($booknr,$chapternr){
		$x=explode(',',$this->chapters[$booknr-1]);
		$r=0;
		for ($i=1;$i<$chapternr;$i++){
			if (isset($x[$i-1])){
				$r+=$x[$i-1];
			}
		}
		return $r;
	}

	//Return total number of verses in book $booknr (1=Ge)
	function get_total_verses_in_book($booknr){
		$r=0;
		if (($booknr>=1) && ($booknr<=66)){
			$x=explode(',',$this->chapters[$booknr-1]);
			foreach ($x as $v){
				$r+=$v;
			}
		}
		return $r;
	}

	//Adds the total of all verses in previous books. I.E. if $booknr=3 you get the total of books 1 (Ge) and 2 (Ex)
	function get_total_verses_in_previous_books($booknr){
		$r=0;
		for ($i=1;$i<$booknr;$i++){
			$r+=$this->get_total_verses_in_book($i);
		}
		return $r;
	}
	
	//Return number of chapters in a given book
	function get_number_of_chapters($booknr){
		if (($booknr>=1) && ($booknr<=66)){
			$x=explode(',',$this->chapters[$booknr-1]);
			return sizeof($x);
		}
		return 0;
	}
	
	//Return number of verses in a given chapter
	function get_number_of_verses($booknr,$chapternr){
		if (($booknr>=1) && ($booknr<=66)){
			//Booknr is ok
			if ($this->get_number_of_chapters($booknr)>=$chapternr){
				//Chapternr is ok
				$x=explode(',',$this->chapters[$booknr-1]);
				return $x[$chapternr-1];
			}
		}
		return 0;
	}
	
	//------STRING-TO-BOOKNR AND VICE VERSA----------------------------------------------------------------------

	//Returns the full or abbreviated name of the book with the number $n
	function number_to_book($n, $abbr=false){
		if (!$abbr){
			//Return full name
			return $this->get_first_element($this->books[$n-1]);
		} else {		
			return $this->get_element(1,$this->books[$n-1]);
		}
	}
	
	//Tries to identify the book referenced by the string
	function string_to_booknr($s){
		//If $s matches any string in $books,$books_abbr2,$books_abbr3 then return number of that element
		$s=strtolower($s);
		foreach ($this->books_lc as $key=>$value){
			$x=explode(',',$value);
			foreach ($x as $value2){
				if ($value2==$s){
					//Found the book.
					return ($key+1); //$key is booknumber-1 (1=ge)
				}
			}
		}
		return false; //Could not assign a book to the string
	}
	
	//Tries to interpret the string as a range of referenced verses and returns an array of two strings of the format booknumber,chapter,verse
	function identify_range($s){
		//Range must have the dash (-)
		if (!(strpos($s,'-')===false)){
			//Split the range in the two constituent references (Range looks sth like 'Gen 1:5-10', or 'Exodus 1:5-2:15')
			$x=explode('-',$s);
			//Trim away whitespace around the dash
			foreach ($x as $k=>$v) { $x[$k]=trim($v); } 
			//Anything before the dash must be a simple reference
			if ($r1=$this->identify_reference($x[0])){
				$y=explode(',',$r1); //Now $y[0] contains booknr, $y[1] chapternr and $y[2] versenr of the FIRST reference
				//Anything after the dash (i.e. the contents of $x[1]) must either be...
				if ($r2=$this->identify_reference($x[1])){
					//...a full reference
					return $this->put_references_in_order($r1,$r2);
				} elseif ((!(strpos($x[1],':')===false)) && ($r2=$this->identify_reference($this->number_to_book($y[0])." ".$x[1]))){
					//or a chapter-verse reference
					return $this->put_references_in_order($r1,$r2);
				} elseif ($r2=$this->identify_reference($this->number_to_book($y[0])." ".$y[1].":".$x[1])){
					//or a simple verse number in which case the whole string has an integer value >0
					return $this->put_references_in_order($r1,$r2);
				}
			}
		} else {
			//It could be that this is a shortcut-range-reference for a chapter, like Psalm 50. In that case, identify_reference returns 'booknr,chapternr,1,numberofversesinchapter'
			$x=explode(',',$this->identify_reference($s));
			if (sizeof($x)==4){
				//4 Elements returned by $this->identify_Reference means full-chapter range
				$r1=$x[0].",".$x[1].",".$x[2];
				$r2=$x[0].",".$x[1].",".$x[3];
				return $this->put_references_in_order($r1,$r2);
			}
		}
		return false;
	}
	
	//Tries to interpret the string as a reference and returns a string with this format: booknumber,chapter,verse (e.g. "19,119,105").
	//If we are dealing with a range, return 'r'
	function identify_reference($s){
		//Name of book: The first number or the first space after the first alpha character delimits the book name
		$s=trim($s); //Cut whitespace
		//In case of Samuel, Kings, Chronicles etc the first character might be a digit and the second a space. Remove the space.
		if ((substr($s,0,1)>0) && (substr($s,1,1)==" ")){
			$s=substr($s,0,1).substr($s,2);
		}
		$x=explode(' ',$s); //$x[0] should be something like "1Peter" now, $x[1] should be sth like "3:22"
		if ($booknr=$this->string_to_booknr($x[0])){
			//Book identified, move on to chapter/verse, now expected as x:y in $x[1]
			$x[1]=trim($x[1]); //Cut whitespace if extant
			//If the book has only one chapter, the 1: may be missing and we might only have the verse number
			if ($this->get_number_of_chapters($booknr)==1){
				if (($x[1]>=1) && (strpos($x[1],":")===false)){
					//Apparently we have a verse number
					if ($this->get_number_of_verses($booknr,1)>=$x[1]){
						//The verse number is valid, too
						$versenr=$x[1];
						//Success
						return $booknr.",1,".$versenr;
					}
				}
			}
			$t=explode(':',$x[1]); //now $t[0] should be chapter, $t[1] should be verse
			if ($this->get_number_of_chapters($booknr)>=$t[0]){
				//Chapter nr is valid
				$chapternr=$t[0];
				//See if the dash exists in $t[2]. If so, we are dealing with a range, not a single reference - fail
				if (strpos($t[1],'-') === false){
					if (isset($t[1])){
						if ($t[1]>0){
							if ($this->get_number_of_verses($booknr,$chapternr)>=$t[1]){
								$versenr=$t[1];
								//Success
								return $booknr.",".$chapternr.",".$versenr;
							}
						}
					} else {
						//$t[1] is not set, so there was no colon. But we have multiple chapters. Therefore we assume a range: the whole chapter
						//and return booknr,chapternr,1,numberofverses_in_chapter
						return $booknr.",".$chapternr.",1,".$this->get_number_of_verses($booknr,$chapternr);
					}
				} else {
					//Is range
					return 'r';
				}
			}
		}
		//Identification of reference failed
		return false;
	}
  
  //Find position of first numeric character in $s
  private function find_first_numeric($s){
    for ($i=0;$i<strlen($s);$i++){
      $c=substr($s,$i,1);
      if (($c=="0") || ($c>0)){
        return $i;
      }
    }
  }
   
  //Try to convert the string into an array of two integers: start,end
  function identify_multi_range($s){
    /*
      example: 2 Samuel 1:3-6,9,2:4-5,4:10-5:2; John 5:1
      
      1.  Strip all whitespace
      2.  Split by ;
      3.  Foreach
    
    */
    //Strip whitespace
    $s=str_replace(' ','',$s);
    //Split by ;
    $p=explode(';',$s);
    //Iterate
    $prev_book=-1;
    $refs=array(); 
    foreach ($p as $v){
      //Check if bookname can be identified: first numeric char after 2nd character in $v
      $end_of_bookname=$this->find_first_numeric(substr($v,1))+1;
      $bookname=substr($v,0,$end_of_bookname);
      if ($booknr=$this->string_to_booknr($bookname)){
        //Book was found at beginning - cut that part off the string under consideration
        $v=substr($v,$end_of_bookname);     
      } else {
        //No book found.
        if ($prev_book==-1){
          //No previous book either: exit and fail
          return false;
        } else {
          //There was a previous book. Use that.
          $booknr=$prev_book;                            
        }
      }
      //Now we have something like 1:3-6,9,2:4-5,4:10-5:2 in $v
      //Split by ,
      $q=explode(',',$v);
      //Iterate
      $prev_ch=-1;
      foreach ($q as $w){        
        //Is there a dash?
        $dash=strpos($w,'-');
        if ($dash===false){
          //No dash. Check for colon
          $colon=strpos($w,':');
          if ($colon===false){
            //No colon - single number. Do we have a previous chapter number?
            if ($prev_ch==-1){
              //No, so this must be either an entire chapter, or one verse in a one-chapter book
              if ($this->get_number_of_chapters($booknr)==1){
                //Do we have anything in $w at all?
                if (!empty($w)){
                  //This is a reference to a single verse in a one-chapter book
                  $chapternr=1;
                  $versenr=$w;
                  $start=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
                  $end=$start;                
                } else {
                  //This is a reference to the entire one-chapter book
                  $chapternr=1;
                  $start=$this->scripture_ref_to_int("$booknr,$chapternr,1");
                  $end=$start+$this->get_number_of_verses($booknr,$chapternr)-1;                 
                }
              } else {
                //It's not a one-chapter-book, so this is a reference to an entire chapter
                $chapternr=$w;
                $start=$this->scripture_ref_to_int("$booknr,$chapternr,1");
                $end=$start+$this->get_number_of_verses($booknr,$chapternr)-1;
              }
            } else {
              //We have a previous chapter number, so this must be a verse
              $chapternr=$prev_ch;
              $versenr=$w;
              $start=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
              $end=$start;
            }                    
          } else {
            //Colon exists: so we assume $chapternr:$versenr, single reference
            $chapternr=substr($w,0,$colon);
            $versenr=substr($w,$colon+1);
            $start=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
            $end=$start;
          }
        } else {
          //Dash exists, so we have a range
          $left=substr($w,0,$dash); //Here is the start ref
          $right=substr($w,$dash+1); //Here is the end ref
          //Both left and right may have a colon and a chapter number or not.
          $colon=strpos($left,':');
          if ($colon===false){
            //No colon
            if ($prev_ch!=-1){
              //Previous chapter exists, so left reference (start) is to $booknr,$prev_ch,$left
              $chapternr=$prev_ch;
              $versenr=$left;
              $start=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
            } else {
              //No previous chapter number - exit and fail
              return false;
            }
          } else {
            //We have colon and assume a chapter:verse reference for the start
            $chapternr=substr($left,0,$colon);
            $versenr=substr($left,$colon+1);
            $start=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
          }
          //Same for the right part (end of range)
          $colon=strpos($right,':');
          if ($colon===false){
            //No colon
            //We must have a $chapternr already. $right must be the ending verse
            $versenr=$right;
            $end=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");            
          } else {
            //We have colon and assume a chapter:verse reference for the end
            $chapternr=substr($right,0,$colon);
            $versenr=substr($right,$colon+1);
            $end=$this->scripture_ref_to_int("$booknr,$chapternr,$versenr");
          }
        } 
        //Check if we found a reference
        if (!(($start===false) || ($end===false))){
          //Store this reference
          $refs[]=array("start"=>$start,"end"=>$end);
          $prev_ch=$chapternr;        
        } else {
          //Couldn't interpret this segment. Exit and fail.
          return false;
        } 
      }
    }
    return $refs;  
  }
	
	
	//Looks at a reference of the format booknr,chapternr,versenr and checks validity
	function verify_reference($ref){
		$x=explode(',',$ref);
		if (($x[0]>0) && ($x[0]<67)){
			//Book is valid
			if (($x[1]>0) && ($x[1]<=$this->get_number_of_chapters($x[0]))){
				//Chapter is valid
				if (($x[2]>0) && ($x[2]<=$this->get_number_of_verses($x[0],$x[1]))){
					//Verse is valid - sucess
					return true;
				}
			}
		}
		return false;
	}
	
	//Takes two reference strings (booknr,chapternr,versenr) and returns an array of the two put in order
	function put_references_in_order($r1,$r2){
		if (($this->verify_reference($r1)) && ($this->verify_reference($r2))){
			//Both references are valid
			$x1=explode(',',$r1);
			$x2=explode(',',$r2);
			//Compare books
			if ($x1[0]>$x2[0]){
				//Book in r1 is later - so r1 is larger
				return (array($r2,$r1));
			} elseif ($x1[0]<$x2[0]){
				//Book in r2 is later - so r2 is larger
				return (array($r1,$r2));
			} else {
				//Books are the same, compare chapters
				if ($x1[1]>$x2[1]){
					//Chapter in r1 is later - so r1 is larger
					return (array($r2,$r1));
				} elseif ($x1[1]<$x2[1]){
					//Chapter in r2 is later - so r2 is larger
					return (array($r1,$r2));
				} else {
					//Chapters are the same also - compare verses
					if ($x1[2]>$x2[2]){
						//Verse in r1 is later - so r1 is larger
						return (array($r2,$r1));
					} elseif ($x1[2]<$x2[2]){
						//Verse in r2 is later - so r2 is larger
						return (array($r1,$r2));
					} else {
						//Verses are the same, too. The two references are identical
						return false;
					}
				}
			}
		}
		return false;
	}
	
	//-------REFERENCE TO INT AND VICE VERSA--------------------------------------------------------------------

	//Takes a string with a reference (eg. Psalm 50:15, or 1Pe 2:2) and returns an integer
	function scripture_string_to_int($s){
		if ($ref=$this->identify_reference($s)){
			//Got the reference in format booknr,chapternr,versenr
			return $this->scripture_ref_to_int($ref);
		}
		//Reference could not be identified
		return false;
	}
	
	//Takes a reference of the format booknr,chapternr,versenr and converts to integer
	function scripture_ref_to_int($ref){
		if ($this->verify_reference($ref)){
			$x=explode(',',$ref);
			$booknr=$x[0];
			$chapternr=$x[1];
			$versenr=$x[2];
			//Total number of verses for each preceding book, plus number of verses of the preceding chapters of this book, plus verse number
			$r=$this->get_total_verses_in_previous_books($booknr);
			$r+=$this->get_total_verses_in_previous_chapters($booknr,$chapternr);
			$r+=$versenr;
			return $r;
		}
		//Reference was not valid
		return false;
	}
	
	//Returns a reference of the form 'booknr,chapternr,versenr'
	function int_to_scripture_ref($n){
		if ($n>0){
			//Identify book first.
			if ($n<$this->books_start_ids[65]){
				//$n is in one of the first 65 books
				$i=1;
				//Look at next book as long as $n is larger than the last verse of the current book $i
				while ($n>=$this->books_start_ids[$i]){
					$i++;
				}
				//We should know now that we are in book $i (ge=1)
			} elseif ($n<($this->books_start_ids[65])+$this->get_total_verses_in_book(66)) {
				//$n is in Revelation
				$i=66;
			}
			//Book identified
			$booknr=$i;
			//It could still be that $n is out of range
			if ($n<($this->books_start_ids[65])+$this->get_total_verses_in_book(66)){
				//Identify chapter and verse
				$verseinbook=($n-$this->books_start_ids[$booknr-1])+1; //The number of the verse in the book
				//As long as $verseinbook is bigger than the total of verses in preceding chapters plus verses in the current chapter, move on to next chapter
				$i=1; //Start with chapter 1
				while ($verseinbook>($this->get_total_verses_in_previous_chapters($booknr,$i)+$this->get_number_of_verses($booknr,$i))){
					$i++;
				}
				//Now $i is the chapter that we are in
				$chapternr=$i;
				//Versenr is also clear now
				$versenr=$verseinbook-$this->get_total_verses_in_previous_chapters($booknr,$i);
				//Return reference	
				return ("$booknr,$chapternr,$versenr");
			}
		}
		//Invalid $n
		return false;
	}
	
	//Returns a reference of the plaintext form, full length (default) or abbreviated
	function int_to_scripture_string($n,$abbr=false,$omit_book=false,$omit_chapter=false){
		if ($ref=$this->int_to_scripture_ref($n)){
			$x=explode(',',$ref);
			$booknr=$x[0];
			$chapternr=$x[1];
			$versenr=$x[2];
      $bookname="";
      if (!$omit_book){
        $bookname=$this->number_to_book($booknr,$abbr)." ";
      }
			//In case we have chapter 1, check if the book has only 1 chapter. In that case, omit chapter reference in output
			if (($chapternr==1) && ($this->get_number_of_chapters($booknr)==1)){
				//Book has the one chapter only
				return ($bookname.$versenr);
			} else {
				//Book has two or more chapters
        if (!$omit_chapter){
				  return ($bookname.$chapternr.":".$versenr);        
        } else {
          return $versenr;
        }
			}
		}
		return false;
	}

	function scripture_ref_to_string($ref){
		return ($this->int_to_scripture_string($this->scripture_ref_to_int($ref)));
	}

  //Does the given range represent an entire chapter?
  function is_one_whole_chapter($start,$end){
    $a=explode(',',$this->int_to_scripture_ref($start));
    $b=explode(',',$this->int_to_scripture_ref($end));
    //See if a whole chapter is referenced
    if (($a[0]==$b[0]) && ($a[1]==$b[1]) && ($a[2]==1) && ($b[2]==$this->get_number_of_verses($b[0],$b[1]))){
      return true;
    }  
    return false;
  }

  function range_to_scripture_string($start,$end,$abbr=false,$omit_first_book=false,$omit_first_chapter=false){
    $res="";
    if (($start!=$end) && ($start>0) && ($start<$end)){
      $a=explode(',',$this->int_to_scripture_ref($start));
      $b=explode(',',$this->int_to_scripture_ref($end));
      //First reference as usual
      $res=$this->int_to_scripture_string($start,$abbr,$omit_first_book,$omit_first_chapter);
      $res.="-";
      //Second reference 
      if ($a[0]!=$b[0]){
        //Different book - add bookname
        $res.=$this->number_to_book($b[0],$abbr)." ";
      }
      if ($a[1]!=$b[1]){
        //Different chapter - add chapter no
        $res.=$b[1].":";
      }
      $res.=$b[2]; //Verse number        
      if ($this->is_one_whole_chapter($start,$end)){
        //This is a reference to ONE whole chapter - take the verses away
        //$res=substr($res,0,strpos($res,':'));
      }
    } elseif (($end==0) || ($end==$start)){
      return $this->int_to_scripture_string($start,$abbr,$omit_first_book,$omit_first_chapter);
    }
    return $res;
  }
  

}

?>
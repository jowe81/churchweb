<?php

class cw_Music_db {
  
    public $d,$auth; //Database access
    public $scripture_handling;
    
    
    function __construct($a){
      $this->auth = $a;
      $this->d = $a->d;
      $this->scripture_handling=new cw_Scripture_handling($this->d);
    }
    
    function check_for_table($table="music_pieces"){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (($this->d->q("CREATE TABLE music_pieces (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(70),
                          alt_title varchar(70),
                          orig_title varchar(70),
                          year_of_release varchar(10),
                          active INT,
                          added_at INT,
                          added_by INT,
                          updated_at INT,
                          updated_by INT,
                          default_language SMALLINT,
                          background_image INT,
                          background_video INT,
                          INDEX (title,alt_title,orig_title,year_of_release),
                          INDEX (default_language,title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE writers (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          last_name varchar(30),
                          first_name varchar(30),
                          person_id INT,
                          INDEX (last_name,first_name)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE writers_to_music_pieces (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          music_piece INT,
                          writer INT,
                          writer_capacity INT,
                          INDEX (music_piece,writer)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE writer_capacities (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(30)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE arrangements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          music_piece INT,
                          title varchar(50),
                          source_id INT,
                          source_index varchar(20),
                          lyrics varchar(255),
                          is_presentation_piece TINYINT,
                          musical_key TINYINT,
                          keychanges TINYINT,
                          guitar_friendly TINYINT,
                          duration INT,
                          comment text,
                          active INT,
                          added_at INT,
                          added_by INT,
                          updated_at INT,
                          updated_by INT,
                          background_image INT,
                          background_video INT,
                          INDEX (music_piece,musical_key)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE sources (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(255),
                          abbreviation varchar(50),
                          identify_in_lyrics_presentation TINYINT
                        )"))
                &&        
             ($this->d->q("CREATE TABLE writers_to_arrangements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          arrangement INT,
                          writer INT,
                          writer_capacity INT,
                          INDEX (arrangement,writer)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE lyrics (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          fragment_type INT,
                          fragment_no INT,
                          music_piece INT,
                          language SMALLINT,
                          content text,
                          active INT,
                          INDEX (music_piece)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE fragment_types (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(30),
                          short_title varchar(20)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE instruments (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(50),
                          INDEX (title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE files_to_instruments_and_arrangements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          file INT,
                          instrument INT,
                          arrangement INT,
                          INDEX (file,arrangement,instrument)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE instruments_to_positions (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          position INT,
                          instruments varchar(255),
                          INDEX (position)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE instruments_to_people (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          position INT,
                          instruments varchar(255),
                          INDEX (person_id)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE copyright_holders (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(150),
                          INDEX (title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE copyright_holders_to_music_pieces (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          music_piece INT,
                          copyright_holder INT,
                          INDEX (music_piece,copyright_holder)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE themes (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(50),
                          INDEX (title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE themes_to_music_pieces (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          music_piece INT,
                          theme INT,
                          INDEX (theme,music_piece)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE style_tags (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          title varchar(50),
                          INDEX (title)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE style_tags_to_arrangements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          style_tag INT,
                          arrangement INT,
                          INDEX (style_tag,arrangement)
                        )"))
                &&        
             ($this->d->q("CREATE TABLE keychanges_to_arrangements (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          arrangement INT,
                          musical_key TINYINT,
                          sequence_no TINYINT,
                          INDEX (arrangement)
                        )")));                                
    }

    /*
      music_pieces
        - year_of_release is NOT timestamp but year in format YYYY
      writers
        - person_id can be used in case the writer is one of us
      writer_capacities
        - title can be composer, arranger, lyricist, translator etc.
      lyrics
        - fragment_no: eg. verse-nr, or chorus 2
      fragment_types
        - title can be chorus, verse, bridge, ending etc.
      arrangements
        - keychanges: number of keychanges
        - guitar_friendly: 0 not guitar friendly, 1 partly guitar friendly, 2 guitar friendlay. Gets updated when key or keychanges are added or cleared on an arr
      instruments_to_positions
        - priority: you can rank the pos->instr connections
          If, for example, violinist can get no violin part (violinist -> violin, priority 1) they will still get a leadsheet (violinist -> leadsheet, priority 2)
          Or an alto singer who can't get an alto part (singer (alto) -> voice (alto), priority 1) will get a 4-part sheet (singer (alto) -> voice (4 parts), pri 2)
            or at least a lead sheet (singer (alto) -> voice (lead), pri 3)
      instruments_to_people
        - same idea as instruments_to_positions, except this one takes priority.
          If an instruments_to_people record exists for a person in a position on a service plan, the priority of instruments given in that record will be observed
          - there may be multiple such records, with preferences for each position (as a worship leader I may have different preferences than as a bassist)
          - an existing generic record (position_id) 0 will be used as fallback if none exists for the position in question
        - sidenote: top priority in parts precendence takes the music_packages table (in event_positions)  
    */

    private function check_for_and_drop_tables($tables=array()){
      foreach ($tables as $v){
        if ($this->check_for_table($v)){
          $this->d->drop_table($v);
        }        
      }
    }
    
    //Delete tables (if extant) and re-create. Add default records.
    function recreate_tables($default_records=true){
      $tables=array("music_pieces",
                    "writers",
                    "writers_to_music_pieces",
                    "writer_capacities",
                    "arrangements",
                    "sources",
                    "writers_to_arrangements",
                    "lyrics",
                    "fragment_types",
                    "instruments",
                    "files_to_instruments_and_arrangements",
                    "instruments_to_positions",
                    "instruments_to_people",
                    "copyright_holders",
                    "copyright_holders_to_music_pieces",
                    "themes",
                    "themes_to_music_pieces",
                    "style_tags",
                    "style_tags_to_arrangements",
                    "keychanges_to_arrangements");
      $this->check_for_and_drop_tables($tables);
      $res=$this->create_tables();
      if ($res && $default_records){
        //Add default records
        //Sources (songbooks/collections)
        $this->add_source("CCLI");
        $this->add_source("Worship Together",true,"WT");
        $this->add_source("Gesangbuch",true,"GB");
        //Different writer capacities
        $this->add_writer_capacity(CW_MUSICDB_WRITER_CAPACITY_COMPOSER);
        $this->add_writer_capacity(CW_MUSICDB_WRITER_CAPACITY_LYRICIST);
        $this->add_writer_capacity(CW_MUSICDB_WRITER_CAPACITY_TRANSLATOR);
        $this->add_writer_capacity(CW_MUSICDB_WRITER_CAPACITY_ARRANGER);
        //Instruments, instruments_to_positions
        $ep = new cw_Event_positions($this->d);
        //Most generic "instruments" are 4part vocals, lead sheet, full score
        $fourparts=$this->add_instrument("4part vocals");
        $leadsheet=$this->add_instrument("lead sheet");
        $chordsheet=$this->add_instrument("chord sheet");
        $full_score=$this->add_instrument("full score");

        //Voices
        $this->assign_instruments_to_position(array($this->add_instrument("soprano voice"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("singer (soprano)"));
        $this->assign_instruments_to_position(array($this->add_instrument("alto voice"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("singer (alto)"));
        $this->assign_instruments_to_position(array($this->add_instrument("tenor voice"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("singer (tenor)"));
        $this->assign_instruments_to_position(array($this->add_instrument("bass voice"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("singer (bass)"));
        $this->assign_instruments_to_position(array($this->add_instrument("voice"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("singer"));


        //Pianist
        $this->assign_instruments_to_position(array($this->add_instrument("piano"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("pianist"));
        
        //Organist
        $this->assign_instruments_to_position(array($this->add_instrument("organ"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("organist"));
        
        //Keyboard
        $this->assign_instruments_to_position(array($this->add_instrument("keyboard"),$chordsheet,$leadsheet,$full_score),$ep->get_position_id("keyboard player"));

        //Bassist
        $this->assign_instruments_to_position(array($this->add_instrument("bass guitar"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("bassist"));
        
        //Drummer
        $this->assign_instruments_to_position(array($this->add_instrument("drums"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("drummer"));
        
        //Percussionist
        $this->assign_instruments_to_position(array($this->add_instrument("percussion"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("percussionist"));

        //Acoustic guitar
        $this->assign_instruments_to_position(array($this->add_instrument("guitar (acoustic)"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("guitarist (acoustic)"));
        
        //Electric guitar
        $this->assign_instruments_to_position(array($this->add_instrument("guitar (electric)"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("guitarist (electric)"));

        //accordion player
        $this->assign_instruments_to_position(array($this->add_instrument("accordion"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("accordion player"));
        

        //Violinists
        $violin=$this->add_instrument("violin");
        $violinII=$this->add_instrument("violin II");
        $violinIII=$this->add_instrument("violin III");
        $this->assign_instruments_to_position(array($violin,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("violinist"));        
        $this->assign_instruments_to_position(array($violinII,$violin,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("violinist [2nd]"));
        $this->assign_instruments_to_position(array($violinIII,$violinII,$violin,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("violinist [3rd]"));
        
        //Violists
        $viola=$this->add_instrument("viola");
        $violaII=$this->add_instrument("viola II");
        $this->assign_instruments_to_position(array($viola,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("violist"));        
        $this->assign_instruments_to_position(array($violaII,$viola,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("violist [2nd]"));
        
        //Cellists
        $cello=$this->add_instrument("cello");
        $celloII=$this->add_instrument("cello II");
        $this->assign_instruments_to_position(array($cello,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("cellist"));
        $this->assign_instruments_to_position(array($celloII,$cello,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("cellist [2nd]"));

        //Double bass player
        $this->assign_instruments_to_position(array($this->add_instrument("double bass"),$leadsheet,$chordsheet,$full_score),$ep->get_position_id("double bass player"));
        
        //Clarinet players
        $clarinet=$this->add_instrument("clarinet");
        $clarinetA=$this->add_instrument("clarinet (A)");
        $clarinetBb=$this->add_instrument("clarinet (Bb)");
        $this->assign_instruments_to_position(array($clarinet,$fourparts,$leadsheet,$chordsheet,$full_score),$ep->get_position_id("clarinet player"));
        $this->assign_instruments_to_position(array($clarinetA),$ep->get_position_id("clarinet player (A)"));
        $this->assign_instruments_to_position(array($clarinetBb),$ep->get_position_id("clarinet player (Bb)"));

        //Recorders
        $this->assign_instruments_to_position(array($this->add_instrument("recorder"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("recorder player"));
        $this->assign_instruments_to_position(array($this->add_instrument("alto recorder"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("alto recorder player"));
        $this->assign_instruments_to_position(array($this->add_instrument("tenor recorder"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("tenor recorder player"));
        $this->assign_instruments_to_position(array($this->add_instrument("bass recorder"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("bass recorder player"));

        //Flute
        $this->assign_instruments_to_position(array($this->add_instrument("flute"),$fourparts,$leadsheet,$full_score),$ep->get_position_id("flute player"));

        //Saxophonists
        $sax=$this->add_instrument("saxophone");
        $saxEb=$this->add_instrument("saxophone (Eb)");
        $this->assign_instruments_to_position(array($sax,$fourparts,$leadsheet,$full_score),$ep->get_position_id("saxophonist"));
        $this->assign_instruments_to_position(array($saxEb),$ep->get_position_id("saxophonist (Eb)"));    

        //Trumpets
        $trumpet=$this->add_instrument("trumpet");
        $trumpetII=$this->add_instrument("trumpet II");
        $trumpetIII=$this->add_instrument("trumpet III");
        $trumpetBb=$this->add_instrument("trumpet (Bb)");
        $trumpetBbII=$this->add_instrument("trumpet (Bb) II");
        $trumpetBbIII=$this->add_instrument("trumpet (Bb) III");
        $this->assign_instruments_to_position(array($trumpet,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trumpet player"));
        $this->assign_instruments_to_position(array($trumpetII,$trumpet,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trumpet player [2nd]"));
        $this->assign_instruments_to_position(array($trumpetIII,$trumpetII,$trumpet,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trumpet player [3rd]"));
        $this->assign_instruments_to_position(array($trumpetBb),$ep->get_position_id("trumpet player (Bb)"));
        $this->assign_instruments_to_position(array($trumpetBbII,$trumpetBb),$ep->get_position_id("trumpet player (Bb) [2nd]"));
        $this->assign_instruments_to_position(array($trumpetBbIII,$trumpetBbII,$trumpetBb),$ep->get_position_id("trumpet player (Bb) [3nd]"));
        
        //Trombones
        $trombone=$this->add_instrument("trombone");
        $tromboneII=$this->add_instrument("trombone II");
        $tromboneIII=$this->add_instrument("trombone III");
        $this->assign_instruments_to_position(array($trombone,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trombone player"));
        $this->assign_instruments_to_position(array($tromboneII,$trombone,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trombone player [2nd]"));
        $this->assign_instruments_to_position(array($tromboneIII,$tromboneII,$trombone,$fourparts,$leadsheet,$full_score),$ep->get_position_id("trombone player [3rd]"));

        //Conductor
        $this->assign_instruments_to_position(array($full_score,$fourparts,$leadsheet),$ep->get_position_id("conductor"));
        
        //Worship leader
        $this->assign_instruments_to_position(array($full_score,$leadsheet,$fourparts,$chordsheet),$ep->get_position_id(CW_SERVICE_PLANNING_DESCRIPTOR_WORSHIP_LEADER));
        
        /*
        
          LATER ADDITIONS BELOW
          
        */
          $front_team=$this->add_instrument("front team");
          $vocal_ens=$this->add_instrument("vocal ensemble");
          $choir=$this->add_instrument("choir");
          $child_vocal=$this->add_instrument("child vocal");

          $this->assign_instruments_to_position(array($front_team),$ep->get_position_id("front team singer"));
          $this->assign_instruments_to_position(array($vocal_ens,$choir,$fourparts),$ep->get_position_id("ensemble singer"));
          $this->assign_instruments_to_position(array($choir,$vocal_ens,$fourparts),$ep->get_position_id("choir singer"));
          $this->assign_instruments_to_position(array($child_vocal),$ep->get_position_id("child singer"));
                          
        /*
        */
        
        //Fragment types
        $this->add_fragment_type("verse","v");
        $this->add_fragment_type("pre-chorus","pre-ch");
        $this->add_fragment_type("chorus","ch");
        $this->add_fragment_type("bridge","bd");
        $this->add_fragment_type("ending","edg");
        $this->add_fragment_type("option");
        $this->add_fragment_type("segment","sgmt");
        
      }
      return $res;
    }

    /* music pieces */
    
    function add_music_piece($title,$person_id=0){
      $e=array();
      $e["title"]=$title;
      $e["added_at"]=time();
      $e["added_by"]=$person_id;
      $e["active"]=true; //Default to use the piece
      $e["default_language"]=CW_DEFAULT_LANGUAGE;
      return $this->d->insert_and_get_id($e,"music_pieces");
    }
    
    function update_music_piece($id,$e=array(),$person_id=0){
      $e["updated_at"]=time();
      $e["updated_by"]=$person_id;
      return $this->d->update_record("music_pieces","id",$id,$e);    
    }
    
    function delete_music_piece_record($id){
      return $this->d->delete($id,"music_pieces");
    }
    
    function get_music_piece_record($id){
      return $this->d->get_record("music_pieces","id",$id);
    }
    
    function get_default_language_for_music_piece($id){
      if ($r=$this->get_music_piece_record($id)){
        return $r["default_language"];
      }
      return false;
    }
    
    //Return all pieces that match title or alt_title
    function get_music_piece_records_by_title($title){
      $t=array();
      $query="SELECT * FROM music_pieces WHERE title LIKE '%$title%' OR alt_title LIKE '%$title%';";
      if ($res=$this->d->query($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }      
      }
      return $t;
    }
    
    //Delete entire music piece with all arrangements and assoc. records
    function delete_music_piece($id){
      /*
        pseudo:
          -get associated arrangements
          -delete them
          -delete records associated to music_piece:
            copyright_holders_to_music_pieces
            scripture_refs_to_music_pieces
            themes_to_music_pieces
            writers_to_music_pieces
            lyrics
          -delete music_pieces record
      */
      $result=false;
      $r=$this->get_arrangement_records_for_music_piece($id);
      if (is_array($r)){
        $result=true;
        foreach ($r as $v){
          $result=(($result) && ($this->delete_arrangement($v["id"])));
        }
      }
      //
      $result=(
        ($this->unassign_all_copyright_holders_from_music_piece($id))
        && ($this->scripture_handling->unassign_all_scripture_refs_from_music_piece($id))
        && ($this->unassign_all_themes_from_music_piece($id))
        && ($this->unassign_all_writers_from_music_piece($id))
        && ($this->delete_lyrics_from_music_piece($id))
        && ($result)
      );
            
      return (($this->delete_music_piece_record($id)) && ($result));
    }
    
    //$lib_id refers to media_library. Use $mb object to determine media type
    function assign_background_to_music_piece($id,$lib_id,cw_Mediabase $mb){
    	if (($m=$mb->get_media_library_record($lib_id)) && ($r=$this->get_music_piece_record($id))){
			if ($m["type"]=="image"){
				$r["background_image"]=$lib_id;
				return ($this->update_music_piece($id,$r,$this->auth->cuid));
			} elseif ($m["type"]=="video"){
				$r["background_video"]=$lib_id;
				return ($this->update_music_piece($id,$r,$this->auth->cuid));
			}    		
    	}
    	return false;
    }
    
    //type can be "image" or "video"
    function unassign_background_from_music_piece($id,$type="image"){
    	if (($r=$this->get_music_piece_record($id)) && (($type=="image") || ($type=="video"))){
    		$r["background_$type"]=0;
    		return ($this->update_music_piece($id,$r,$this->auth->cuid));
    	}
    	return false;
    }
    
    function get_music_piece_background($id,$type="image"){
    	if ($r=$this->get_music_piece_record($id)){
    		return $r["background_$type"];
    	}
    	return false;
    }
    
    /* writers */
    function add_writer($last_name,$first_name){
      //Only add writer if s/he doesn't exist yet
      $existing_id=$this->get_writer_id($last_name,$first_name);
      if ($existing_id===false){
        $e=array();
        $e["last_name"]=$last_name;
        $e["first_name"]=$first_name;
        return $this->d->insert_and_get_id($e,"writers");      
      } else {
        //If writer existed already we're successful, too - return id.
        return $existing_id;
      }
    }
    
    function delete_writer($id){
      return $this->d->delete($id,"writers");    
    }
    
    function get_writer_record($id){
      return $this->d->get_record("writers","id",$id);    
    }
    
    function get_writer_id($last_name,$first_name){
      if ($res=$this->d->q("SELECT id FROM writers WHERE first_name=\"$first_name\" AND last_name=\"$last_name\";")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    function get_writer_name_first_last($id){
      if ($r=$this->get_writer_record($id)){
        return $r["first_name"]." ".$r["last_name"];
      }
      return false;
    }
    
    //Delete writers that are not showing up in either writers_to_music_pieces OR writers_to_arrangements
    function delete_unused_writers(){
      $query="DELETE writers FROM writers LEFT JOIN writers_to_music_pieces ON writers.id=writers_to_music_pieces.writer LEFT JOIN writers_to_arrangements ON writers.id=writers_to_arrangements.writer WHERE (writers_to_music_pieces.writer IS NULL) AND (writers_to_arrangements.writer IS NULL);";
      return ($this->d->q($query));
    }
    
    /* writers_to_music_pieces */
    
    function assign_writer_to_music_piece($music_piece,$writer,$writer_capacity){
      if (($this->get_music_piece_record($music_piece)!==false) && ($this->get_writer_record($writer)!==false) && ($this->get_writer_capacities_record($writer_capacity)!==false)){
        if (!$this->writer_to_music_pieces_association_exists($music_piece,$writer,$writer_capacity)){
          $e=array();
          $e["music_piece"]=$music_piece;
          $e["writer"]=$writer;
          $e["writer_capacity"]=$writer_capacity;
          return $this->d->insert_and_get_id($e,"writers_to_music_pieces");
        } else {
          return true; //If the association exists already, we're technically successful
        }
      }
      return false;
    }
    
    function unassign_writer_from_music_piece($id){
      return $this->d->delete($id,"writers_to_music_pieces");        
    }
    
    function unassign_all_writers_from_music_piece($music_piece,$writer_capacity=0){
      if ($writer_capacity>0){
        $query="DELETE FROM writers_to_music_pieces WHERE music_piece=$music_piece AND writer_capacity=$writer_capacity;";        
      } else {
        $query="DELETE FROM writers_to_music_pieces WHERE music_piece=$music_piece;";
      }
      return ($this->d->q($query));
    }
    
    function writer_to_music_pieces_association_exists($music_piece,$writer,$writer_capacity){
      if ($res=$this->d->q("SELECT * FROM writers_to_music_pieces WHERE music_piece=$music_piece AND writer=$writer AND writer_capacity=$writer_capacity;")){
        return ($res->num_rows>0);
      }
      return false;
    }
        
    //Return an array of writers last_name,first_name,id and writer_capcities.title
    function get_writers_for_music_piece($music_piece,$writer_capacity=""){
      $cond="";
      if (!empty($writer_capacity)){
        $cond="
          AND
            writers_to_music_pieces.writer_capacity=$writer_capacity
        ";
      }
      $t=array();
      $query="               
        SELECT DISTINCT
          writers.*
        FROM
          writers,writer_capacities,writers_to_music_pieces
        WHERE
          writers_to_music_pieces.music_piece=$music_piece
        $cond
        AND
          writers_to_music_pieces.writer=writers.id
        ORDER BY
          writers.last_name,writers.first_name
      ";
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }      
      }
      return $t;      
    } 
    
    function get_music_piece_title_info($music_piece){
      if ($r=$this->get_music_piece_record($music_piece)){
        $writers="";
        $w=$this->get_writers_for_music_piece($music_piece);
        if (is_array($w) && (sizeof($w)>0)){
          foreach ($w as $v){
            $writers.=", ".$v["first_name"]." ".$v["last_name"];
          }
          if (!empty($writers)){
            //Cut first comma
            $writers=substr($writers,2);
          }
          $writers=" by $writers";
        }    
        empty($r["alt_title"]) ? $alt_title="" : $alt_title=" ('".$r["alt_title"]."')";
        return "'".$r["title"]."'".$alt_title.$writers;
      }    
      return "N/A";
    }     
    
    
    //Return one-liner of credits/copyright info for ppt slides etc
    function get_music_piece_credits($music_piece){
      $composers=$this->get_writer_string_for_music_piece($music_piece,CW_MUSICDB_WRITER_CAPACITY_COMPOSER,false);
      $lyricists=$this->get_writer_string_for_music_piece($music_piece,CW_MUSICDB_WRITER_CAPACITY_LYRICIST,false);
      $translators=$this->get_writer_string_for_music_piece($music_piece,CW_MUSICDB_WRITER_CAPACITY_TRANSLATOR,false);
      $copyright_holders=$this->get_copyright_holder_string_for_music_piece($music_piece,false);
      $t="";
      if ($composers==$lyricists){
        $t.="Words and Music: $composers, ";
      } else {
        if ($composers!=""){
          $t.="Music: $composers, ";
        }
        if ($lyricists!=""){
          $t.="Words: $lyricists, ";
        }
      }
      if ($translators!=""){
        $t.="Transl. $translators, ";
      }
      return substr($t,0,-2); //cut last comma                
    }
    
    function get_arrangement_credits($arrangement_id){
      $t=$this->get_writer_string_for_arrangement($arrangement_id,CW_MUSICDB_WRITER_CAPACITY_ARRANGER,false);
      if ($t!=""){
        return "Arr. ".$t;
      }
    }
    
    /* writer_capacities */
    
    function add_writer_capacity($title){
      if (!$this->get_writer_capacities_id($title)){
        $e=array();
        $e["title"]=$title;              
        return $this->d->insert_and_get_id($e,"writer_capacities");            
      }
      return false;
    }
        
    function get_writer_capacities_record($id){
      return $this->d->get_record("writer_capacities","id",$id);    
    }
    
    function get_writer_capacities_title($id){
      if ($r=$this->get_writer_capacities_record($id)){
        return $r["title"];
      }
      return false;
    }
    
    function get_writer_capacities_id($title){
      if ($res=$this->d->q("SELECT id FROM writer_capacities WHERE title='$title'")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    /* arrangements */
    
    function add_arrangement($music_piece,$person_id=0){
      //Music piece must exist
      if ($this->get_music_piece_record($music_piece)){
        $e=array();
        $e["music_piece"]=$music_piece;
        $e["duration"]=CW_NEW_ARRANGEMENT_DEFAULT_DURATION;
        $e["musical_key"]=1; //default to C Major
        ($this->key_is_guitar_friendly($e["musical_key"])) ? $e["guitar_friendly"]=2 : $e["guitar_friendly"]=0;
        $e["keychanges"]=0; //zero keychanges exist at the time of arrangement creation
        $e["added_at"]=time();
        $e["added_by"]=$person_id;
        $e["active"]=true; //default to using the new arrangement
        return $this->d->insert_and_get_id($e,"arrangements");          
      }
      return false;
    }
    
    function get_arrangement_record($id){
      return $this->d->get_record("arrangements","id",$id);        
    }
    
    //Update the column 'guitar_friendly'
    private function update_arrangement_guitar_friendliness($id){
      //Guitar_friendliness depends on the original key and the keychanges in the arrangement
      $r=$this->get_arrangement_record($id);
      $keychanges=$this->get_keychanges_for_arrangement($id);
      if ((is_array($keychanges)) && (is_array($r))){
        $keychanges[]=array("musical_key"=>$r["musical_key"]); //Save orig key in same array as keychanges
        //Loop over all keys
        $no_guitar_friendly_keys=0;
        $total_no_keys=sizeof($keychanges);
        foreach ($keychanges as $kc){
          if ($this->key_is_guitar_friendly($kc["musical_key"])){
            $no_guitar_friendly_keys++;
          }
        }
        $e=array();
        $e["guitar_friendly"]=0;
        if ($no_guitar_friendly_keys>0){
          if ($no_guitar_friendly_keys==$total_no_keys){
            //All keys are guitar friendly
            $e["guitar_friendly"]=2;
          } else {
            //At least one guitar friendly key
            $e["guitar_friendly"]=1;
          }            
        }
        //Update arrangement record
        return $this->update_arrangement_record($id,$e,false);      
      }
      return false;
    }
    
    function update_arrangement_record($id,$e,$auto_update_guitar_friendliness=true){
      //On change of original key clear keychanges. 
      if (isset($e["musical_key"])){
        //Double check if key really changed.
        $r=$this->get_arrangement_record($id);
        if ($r["musical_key"]!=$e["musical_key"]){
          $this->unassign_all_keychanges_from_arrangement($id);        
        }
      }
      $success=$this->d->update_record("arrangements","id",$id,$e);
      if (($success) && ($auto_update_guitar_friendliness)){
        return $this->update_arrangement_guitar_friendliness($id);
      }
      return $success;
    }
    
    function get_arrangement_records_for_music_piece($id,$active_only=false){
      $cond="";
      if ($active_only){
        $cond=" AND active>0";
      }
      if ($res=$this->d->q("SELECT * FROM arrangements WHERE music_piece=$id $cond ORDER BY active DESC,title;")){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        return $t;
      }
      return false;
    }
    
    function rename_arrangement($id,$title){
      if ($e=$this->get_arrangement_record($id)){
        $e["title"]=$title;
        return $this->d->update_record("arrangements","id",$id,$e);           
      }
      return false;
    }
    
    function delete_arrangement_record($id){
      return $this->d->delete($id,"arrangements");            
    }
    
    function toggle_retire_arrangement($id){
      $e=$this->get_arrangement_record($id);
      $e["active"]=!$e["active"];
      return $this->d->update_record("arrangements","id",$id,$e);
    }
    
    function get_lyrics_csl_for_arrangement($id){
      $r=$this->get_arrangement_record($id);
      if (is_array($r)){
        return $r["lyrics"];
      }
      return false;
    }
    
    //"lyrics" field in arrangements is a csl (comma-separated list) of lyrics ids
    //Put in $lyrics_id at $position  
    function assign_lyrics_to_arrangement($arrangement,$lyrics_id,$position){
      $str=$this->get_lyrics_csl_for_arrangement($arrangement);
      if ($str!==false){
        $str=csl_add_element_at_position($str,$lyrics_id,$position);
        $e=array();
        $e["lyrics"]=$str;
        return $this->update_arrangement_record($arrangement,$e);
      }
      return false;
    }
    
    function unassign_lyrics_from_arrangement($arrangement,$position){
      $str=$this->get_lyrics_csl_for_arrangement($arrangement);
      if ($str!==false){
        $str=csl_delete_element_at_position($str,$position);
        $e=array();
        $e["lyrics"]=$str;
        return $this->update_arrangement_record($arrangement,$e);
      }
      return false;    
    }
    
    function unassign_all_lyrics_from_arrangement($arrangement){
      $e=array();
      $e["lyrics"]="";
      return $this->update_arrangement_record($arrangement,$e);
    }
    
    function reposition_lyrics_in_arrangement($arrangement,$old_pos,$new_pos){
      $str=$this->get_lyrics_csl_for_arrangement($arrangement);
      if ($str!==false){
        $str=csl_move_element($str,$old_pos,$new_pos);
        $e=array();
        $e["lyrics"]=$str;
        return $this->update_arrangement_record($arrangement,$e);
      }
      return false;          
    }
    
    function replace_lyrics_csl_for_arrangement($arrangement,$csl){
      $e=array();
      $e["lyrics"]=$csl;
      return $this->update_arrangement_record($arrangement,$e);    
    }
        
    function apply_default_lyrics_sequence_to_arrangement($arrangement,$music_piece){
      /*
        Pseudo:
          -clear lyrics sequence
          -if verse(s) exist:
            if chorus(s) exist:
              if pre-chorus exists:
                if bridge exists:
                  v1,pre-ch,ch,v2,pre-ch,ch,...,vX,pre-ch,ch,bd,ch
                else
                  v1,pre-ch,ch,v2,pre-ch,ch,...,vX,pre-ch,ch
              else
                if bridge exists:
                  v1,ch,v2,ch,...,vX,ch,bd,ch
                else
                  v1,ch,v2,ch,...,vX,ch
            else
              v1,v2,...vX
            -if ending exists: append edg
          
          fragment_types should default to
           1 = verse
           2 = pre-chorus
           3 = chorus
           4 = bridge
           5 = ending
      */
      if ($this->unassign_all_lyrics_from_arrangement($arrangement)){
        $t="";
        $verses=$this->get_lyrics_records_for_music_piece($music_piece,1);
        $prechoruses=$this->get_lyrics_records_for_music_piece($music_piece,2);
        $choruses=$this->get_lyrics_records_for_music_piece($music_piece,3);
        $bridges=$this->get_lyrics_records_for_music_piece($music_piece,4);
        $endings=$this->get_lyrics_records_for_music_piece($music_piece,5);
        if (is_array($verses) && (sizeof($verses)>0)){        
          if (is_array($choruses) && (sizeof($choruses)>0)){        
            if (is_array($prechoruses) && (sizeof($prechoruses)>0)){        
              if (is_array($bridges) && (sizeof($bridges)>0)){
                //Verses, pre-ch, chorus, bridge
                foreach ($verses as $v){
                  $t=csl_append_element($t,$v["id"]);
                  $t=csl_append_element($t,$prechoruses[0]["id"]);
                  $t=csl_append_element($t,$choruses[0]["id"]);
                }                                                            
                $t=csl_append_element($t,$bridges[0]["id"]);  
                $t=csl_append_element($t,$choruses[0]["id"]);                                               
              } else {
                //Verses, pre-ch, chorus
                foreach ($verses as $v){
                  $t=csl_append_element($t,$v["id"]);
                  $t=csl_append_element($t,$prechoruses[0]["id"]);
                  $t=csl_append_element($t,$choruses[0]["id"]);
                }                                                            
              }
            } else {
              if (is_array($bridges) && (sizeof($bridges)>0)){
                //Verses and a chorus and a bridge        
                foreach ($verses as $v){
                  $t=csl_append_element($t,$v["id"]);
                  $t=csl_append_element($t,$choruses[0]["id"]);
                }
                $t=csl_append_element($t,$bridges[0]["id"]);  
                $t=csl_append_element($t,$choruses[0]["id"]);                                 
              } else {
                //Verses and a chorus
                foreach ($verses as $v){
                  $t=csl_append_element($t,$v["id"]);
                  $t=csl_append_element($t,$choruses[0]["id"]);
                }                                    
              }            
            }
          } else {
            //Just verses
            foreach ($verses as $v){
              $t=csl_append_element($t,$v["id"]);
            }          
          }
          if (is_array($endings) && (sizeof($endings)>0)){
            //Append ending
            $t=csl_append_element($t,$endings[0]["id"]);
          }        
        }
        return $this->replace_lyrics_csl_for_arrangement($arrangement,$t);
      }
      return false;
    }
    
    //Is this arrangement in use somewhere? true if it shows up in atse (arrangements_to_service_elements)
    function arrangement_is_in_use($arrangement){
      $query="SELECT * FROM arrangements_to_service_elements WHERE arrangement=$arrangement;";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }  
      return false;
    }
    
    //Delete arrangement with all associated records
    function delete_arrangement($arrangement,$force=false){
      /*
        Associate tables are:
          arrangements_to_service_elements //leave this one
          files_to_instruments_and_arrangements
          keychanges_to_arrangements
          lyrics: in arrangements record - thus irrelevant here (gets delete with arrangements record)
          style_tags_to_arrangements
          writers_to_arrangements
          arrangements
          
        Also: files need to be deleted
      */
      
      $result=false;
      if ($force || (!$this->arrangement_is_in_use($arrangement))){
        $f=new cw_Files($this->d);
        $query="
          SELECT file FROM files_to_instruments_and_arrangements WHERE arrangement=$arrangement;
        ";
        if ($res=$this->d->q($query)){
          $result=true;
          while($r=$res->fetch_assoc()){
            $result=(($result) && ($f->remove_file($r["file"]))); 
          }
        }            
        $result=( ($this->unassign_all_files_and_instruments_from_arrangement($arrangement))
                  && ($this->unassign_all_keychanges_from_arrangement($arrangement))
                  && ($this->unassign_all_style_tags_from_arrangement($arrangement))
                  && ($this->unassign_all_writers_from_arrangement($arrangement))
                  && ($this->delete_arrangement_record($arrangement))
                  && ($result)                
                );
      
      }              
      return $result;      
    }    
    
    //$lib_id refers to media_library. Use $mb object to determine media type
    function assign_background_to_arrangement($id,$lib_id,cw_Mediabase $mb){
    	if (($m=$mb->get_media_library_record($lib_id)) && ($r=$this->get_arrangement_record($id))){
    		if ($m["type"]=="image"){
    			$r["background_image"]=$lib_id;
    			return ($this->update_arrangement_record($id, $r, false));
    		} elseif ($m["type"]=="video"){
    			$r["background_video"]=$lib_id;
    			return ($this->update_arrangement_record($id, $r, false));
       		}
    	}
    	return false;
    }
    
    //type can be "image" or "video"
    function unassign_background_from_arrangement($id,$type="image"){
    	if (($r=$this->get_arrangement_record($id)) && (($type=="image") || ($type=="video"))){
    		$r["background_$type"]=0;
    		return ($this->update_arrangement_record($id,$r,false));
    	}
    	return false;
    }
    
    
    /* sources */
    
    function add_source($title,$identify_in_lyrics_presentation=false,$abbreviation=""){
      if ((!empty($title)) && (!$this->source_exists($title))){
        $e=array();
        $e["title"]=$title;
        $e["abbreviation"]=$abbreviation;
        if ($identify_in_lyrics_presentation){
          $e["identify_in_lyrics_presentation"]=1;
        }
        return $this->d->insert_and_get_id($e,"sources");        
      }
    }
    
    function source_exists($title){
      $query="SELECT * FROM sources WHERE title=\"$title\"";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }
    }
   
    function get_source_record($id){
      return ($this->d->get_record("sources","id",$id));
    }
    
    function get_source_abbreviation($id){
      if ($r=$this->get_source_record($id)){
        return $r["abbreviation"];
      }
      return false;
    }
    
    function get_source_title($source_id){
      if ($source_id>0){
        $query="SELECT title FROM sources WHERE id=$source_id";
        if ($res=$this->d->q($query)){
          if ($r=$res->fetch_assoc()){
            return $r["title"];
          }
        }      
      }
      return false;
    }
    
    function get_source_id($source_title,$strict=true){
      if ($source_title!=""){
        if ($strict){
          $query="SELECT id FROM sources WHERE title LIKE \"$source_title\"";        
        } else {
          $query="SELECT id FROM sources WHERE title LIKE \"%$source_title%\"";                
        }
        if ($res=$this->d->q($query)){
          if ($r=$res->fetch_assoc()){
            return $r["id"];
          }
        }      
      }
      return false;
    }
    
    function delete_unused_sources(){
      $query="DELETE sources FROM sources LEFT JOIN arrangements ON sources.id=arrangements.source_id WHERE arrangements.source_id IS NULL;";
      return ($this->d->q($query));      
    }
    
    /* writers_to_arrangements */
    
    //Typically the capacity is arranger, but could be translator on occasion
    function assign_writer_to_arrangement($arrangement,$writer,$writer_capacity_id){
      if ($writer_capacity_id>0){
        if (($this->get_arrangement_record($arrangement)) && ($this->get_writer_record($writer))){
          $e=array();
          $e["arrangement"]=$arrangement;
          $e["writer"]=$writer;
          $e["writer_capacity"]=$writer_capacity_id;
          return $this->d->insert_and_get_id($e,"writers_to_arrangements");                    
        }      
      }
      return false;
    }
    
    function unassign_writer_from_arrangement($id){
      return $this->d->delete($id,"writers_to_arrangements");                
    }
    
    function unassign_all_writers_from_arrangement($arrangement,$writer_capacity=0){
      if ($writer_capacity==0){
        $query="DELETE FROM writers_to_arrangements WHERE arrangement=$arrangement";
      } else {
        $query="DELETE FROM writers_to_arrangements WHERE arrangement=$arrangement AND writer_capacity=$writer_capacity;";      
      }
      return ($this->d->q($query));
    }
    
    //Return an array of writers last_name,first_name,id and writer_capcities.title
    function get_writers_for_arrangement($arrangement){
      $t=array();
      $query="
        SELECT
          writers.last_name,writers.first_name,writers.id,writer_capacities.title
        FROM
          writers,writer_capacities,writers_to_arrangements
        WHERE
          writers_to_arrangements.arrangement=$arrangement
        AND
          writers_to_arrangements.writer_capacity=writer_capacities.id
        AND
          writers_to_arrangements.writer=writers.id
      ";
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }      
      }
      return $t;      
    }      
    
    function get_writers_for_arrangement_as_string($arrangement){
      $w=$this->get_writers_for_arrangement($arrangement);
      $writers="";
      if (is_array($w)){
        foreach ($w as $v2){
          $writers.=", ".substr($v2["first_name"],0,1).". ".$v2["last_name"];            
        }
        if (!empty($writers)){
          //Cutoff first comma
          $writers=substr($writers,2);
        }
      }
      return $writers;    
    }
    
    /* lyrics */

    function get_lyrics_record($id){
      return $this->d->get_record("lyrics","id",$id);            
    }
    
    //Return a bunch of records as specified in the comma-separated string-list $list
    function get_lyrics_records_from_csl($list,$skip_blank_slides=false){
      $ids=explode(',',$list);
      $t=array();
      foreach ($ids as $v){
        if ($v>0){
          $t[]=$this->get_lyrics_record($v);
        } else {
          if (($v=="0") && (!$skip_blank_slides)){
            $t[]=array("fragment_type"=>0,"content"=>"[blank slide]");
          }
        }
      }
      return $t;    
    }
    
    private function overwrite_lyrics_fragment_no($id,$fragment_no){
      return ($this->d->q("UPDATE lyrics SET fragment_no=$fragment_no WHERE id=$id;"));
    }

    function update_lyrics_record_content($id,$new_content){
      $e=array();
      $e["content"]=$new_content;
      return ($this->d->update_record("lyrics","id",$id,$e));
    }
    
    function update_lyrics_record($id,$e){
      if (is_array($e)){
        return ($this->d->update_record("lyrics","id",$id,$e));          
      }
      return false;
    }
    
    //If fragment_type is omitted, return all
    //If fragment_no is omitted, return all of fragment_type
    function get_lyrics_records_for_music_piece($music_piece,$fragment_type=0,$fragment_no=0,$language=null,$always_return_array=false){
      $cond="";
      ($fragment_type>0) ? $cond.=" AND fragment_type=$fragment_type" : null;
      ($fragment_no>0) ? $cond.=" AND fragment_no=$fragment_no": null;
      (!is_null($language)) ? $cond.=" AND language=$language": null;
      $query="SELECT * FROM lyrics WHERE music_piece=$music_piece $cond ORDER BY language,fragment_type,fragment_no;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        if (($fragment_no>0) && (!$always_return_array)){
          //Fragment_no was given, there can be only one record - so don't give array
          return $t[0];
        }
        return $t;
      }
      return false;  
    }
    
    //Add a lyrics fragment at the end of the sequence (i.e. if a verse gets added it gets the next free fragment_no)
    //MIGHT ADD THE OPTION TO SPECIFY FRAGMENT-NO LATER
    function add_lyrics($content,$music_piece,$fragment_type,$language=0){
      //See if there is content and if the music_piece and fragment_type exist
      if ((!empty($content)) && ($this->get_music_piece_record($music_piece)) && ($this->get_fragment_types_record($fragment_type))){
        //See how many fragments of this type and language exist for this song (to determine fragment_no)
        $t=$this->get_lyrics_records_for_music_piece($music_piece,$fragment_type,0,$language,true);
        if ($t!==false){
          $e=array();
          $e["content"]=$content;
          $e["language"]=$language;//Default to English
          $e["music_piece"]=$music_piece;
          $e["fragment_type"]=$fragment_type;
          $e["fragment_no"]=sizeof($t)+1;
          return $this->d->insert_and_get_id($e,"lyrics");                                        
        }
      }
      return false;
    }
            
    //Delete a lyrics fragment and close the gap in fragment_no sequence
    function delete_lyrics($id){
      //Check whether lyrics fragment is used in an arrangement
      if (!$this->lyrics_in_use($id)){
        //Retrieve record to be deleted
        if ($e=$this->get_lyrics_record($id)){
          //Delete from database
          $success=$this->d->delete($id,"lyrics");          
          //If there are fragments with a higher fragment_no, move them down
          $n=1;
          while ($r=$this->get_lyrics_records_for_music_piece($e["music_piece"],$e["fragment_type"],$e["fragment_no"]+$n,true)){
            $this->overwrite_lyrics_fragment_no($r["id"],$r["fragment_no"]-1);
            $n++;  
          }
          return $success;
        }
      }
      return false;
    }
    
    //Look at all arrangements to see if any of them uses the lyrics
    function lyrics_in_use($id){
      $regexp="((^$id,)|(,".$id."$)|(,$id,)|(^".$id."$))"; //Match item $id in a comma-separated list: could be at the beginning, the end, or between commas, or sole item
      if ($r=$this->d->q("SELECT id FROM arrangements WHERE lyrics REGEXP '$regexp' LIMIT 1;")){
        if ($r->num_rows>0){
          return true;
        }
        return false;
      }      
      return true; //If in doubt (error), we'll say the lyrics are in use
    }
    
    function delete_lyrics_from_music_piece($music_piece_id){
      $query="DELETE FROM lyrics WHERE music_piece=$music_piece_id;";
      return ($this->d->q($query));
    }
    
    /* languages */
    
    private function get_languages_array($preface_with_default=false){
      if ($preface_with_default){
        return explode(',',"default,".CW_LANGUAGES);
      }
      return explode(',',CW_LANGUAGES);
    }
    
    function language_to_int($t){
      $x=$this->get_languages_array();
      return array_search($t,$x); //False if $t not found, numeric key otherwise
    }
    
    function int_to_language($i){
      $x=$this->get_languages_array();
      if ($i<=sizeof($x)){
        //Range is valid
        return $x[$i];
      }      
      return "out of range";
    }
    
    //$selected must be an int value
    function get_languages_for_select($selected=-1,$include_default=true){
      $x=$this->get_languages_array($include_default);
      $t="";
      foreach ($x as $k=>$v){
        ($k==$selected) ? $sel="selected=\"SELECTED\"" : $sel="";
        $t.="<option value='$k' $sel>$v</option>";
      }
      return $t;
    }
        
    /* fragment types */
    
    function add_fragment_type($title,$short_title=""){
      if (!$this->get_fragment_types_id($title)){
        $e=array();
        $e["title"]=$title;   
        empty($short_title) ? $e["short_title"]=$title : $e["short_title"]=$short_title;                    
        return $this->d->insert_and_get_id($e,"fragment_types");            
      }
      return false;
    }
        
    function get_fragment_types_record($id){
      return $this->d->get_record("fragment_types","id",$id);    
    }
    
    function get_fragment_types_title($id){
      if ($r=$this->get_fragment_types_record($id)){
        return $r["title"];
      }
      return false;
    }
    
    function get_fragment_types_id($title){
      if ($res=$this->d->q("SELECT id FROM fragment_types WHERE title='$title'")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    function get_fragment_types_records(){
      return $this->d->get_table("fragment_types");
    }
   
   /* instruments */
   
    function add_instrument($title){
      if (!$this->get_instruments_id($title)){
        $e=array();
        $e["title"]=$title;              
        return $this->d->insert_and_get_id($e,"instruments");            
      }
      return false;
    }
        
    function get_instruments_record($id){
      return $this->d->get_record("instruments","id",$id);    
    }
    
    function get_instruments_title($id){
      if ($r=$this->get_instruments_record($id)){
        return $r["title"];
      }
      return false;
    }
    
    function get_instruments_id($title){
      if ($res=$this->d->q("SELECT id FROM instruments WHERE title='$title'")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }
    
    /* files_to_instruments_and_arrangements */
    
    function assign_file_to_instrument_and_arrangement($file,$instrument,$arrangement){
      if (!$this->file_has_instrument_and_arrangement($file,$arrangement,$instrument)){
        //Association does not yet exist
        $e=array();
        $e["file"]=$file;
        $e["instrument"]=$instrument;
        $e["arrangement"]=$arrangement;
        return $this->d->insert_and_get_id($e,"files_to_instruments_and_arrangements");                    
      }
      return true; //If association exists then technically we're succesful
    }

    function unassign_file_from_instrument_and_arrangement($file,$instrument,$arrangement){
      return $this->d->q("DELETE FROM files_to_instruments_and_arrangements WHERE file=$file AND arrangement=$arrangement AND instrument=$instrument;");
    }
    
    function unassign_all_files_and_instruments_from_arrangement($arrangement){
      return $this->d->q("DELETE FROM files_to_instruments_and_arrangements WHERE arrangement=$arrangement;");    
    }
    
    function file_has_instrument_and_arrangement($file,$arrangement,$instrument){
      if ($res=$this->d->q("SELECT id FROM files_to_instruments_and_arrangements WHERE file=$file AND arrangement=$arrangement AND instrument=$instrument;")){
        return ($res->num_rows>0);
      }
    }
    
    //Get the file info for the arrangement that contains the instrument $instrument
    //if $minimal: find the file that has the least other instruments in it
    //if !$minimal: find the file that has the greatest number of other instruments in it (full score will not be found that way, because it is treated as an instrument) 
    function get_files_to_instruments_and_arrangements_record_for_arrangement_and_instrument($arrangement,$instrument,$minimal=true){
    
      /*
        Pseudo:
          - get all records that have the instrument and arrangement (i.e. all files in which the instrument is in)
          - for each result file, find out how many other instruments are in there
          - if minimal, return the record (of the original result set) that has the file with the least other instruments
          - if !minimal, return the record that has the file with the most other instruments  
      
      */
      $query="SELECT * FROM files_to_instruments_and_arrangements WHERE arrangement=$arrangement AND instrument=$instrument ORDER BY file;";

      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        //All result records are in $t
        if (sizeof($t)>1){
          //Multiple results. Find out how many instruments are in each associated file.
          $tt=array();
          foreach ($t as $v){
            $tt[$v["id"]]=sizeof($this->get_files_to_instruments_and_arrangements_records_for_file($v["file"],$v["arrangement"]));
          }
          asort($tt); //Sort array by value
          if ($minimal){
            //minimal: return first (lowest) element
            reset($tt);
            $files_to_arrangements_record_id_to_return=key($tt);    
          } else {
            //!minimal: return last (highest) element
            end($tt);
            $files_to_arrangements_record_id_to_return=key($tt);    
          }
          foreach ($t as $v){
            if ($v["id"]==$files_to_arrangements_record_id_to_return){
              return $v;
            }
          }          
        } elseif (sizeof($t)==1) {
          //One result - return that one
          return $t[0];          
        } else {
          //No results
        }
      }            
      return false;
      
      /*
      $query="SELECT * FROM files_to_instruments_and_arrangements WHERE arrangement=$arrangement AND instrument=$instrument;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
      */
    }
    
    //Essentially find out which instruments are in the partfile $file    
    function get_files_to_instruments_and_arrangements_records_for_file($file,$arrangement){
      $query="SELECT * FROM files_to_instruments_and_arrangements WHERE file=$file AND arrangement=$arrangement;";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        return $t;
      }    
      return false;
    }
    
    //Used by backup/restore
    function replace_file_id_in_files_to_instruments_and_arrangements_table($old_id,$new_id){
      $query="UPDATE files_to_instruments_and_arrangements SET file=$new_id WHERE file=$old_id;";
      return $this->d->q($query);    
    }

    /* instruments_to_positions */
    
    /* now CSL */
    
    function assign_instrument_to_position($instrument,$position){
      if (!$this->position_has_instrument($instrument,$position)){
        $e=$this->get_instruments_to_positions_record($position);
        if (is_array($e)){
          //Position has other instrument(s) already, append
          $e["instruments"]=csl_append_element($e["instruments"],$instrument);
          return $this->d->update_record("instruments_to_positions","position",$position,$e);
        } else {
          $e=array();
          $e["position"]=$position;
          $e["instruments"]=$instrument;
          return $this->d->insert_and_get_id($e,"instruments_to_positions");                              
        }        
      }
      return true; //Connection exists, so we're successful      
    }
    
    //$instruments is array of instrument ids  
    function assign_instruments_to_position($instruments,$position){
      if (is_array($instruments) && ($position>0)){
        foreach ($instruments as $v){
          $res=$this->assign_instrument_to_position($v,$position);
          if ($res===false){
            return false;
          }          
        }
        return true;
      }
      return false;
    }        

    function get_instruments_to_positions_record($position){
      if ($res=$this->d->q("SELECT * FROM instruments_to_positions WHERE position=$position;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
    }

    function unassign_instrument_from_position($instrument,$position){
      if ($this->position_has_instrument($position,$instrument)){
        $e=$this->get_instruments_to_positions_record($position);
        if (is_array($e)){
          $element_pos=csl_get_element_pos($e["instruments"],$instrument);
          $e["instruments"]=csl_delete_element_at_position($e["instruments"],$element_pos);
          return $this->d->update_record("instruments_to_positions","position",$position,$e);          
        }
        return false;
      }
      return true; //no association existed, success
    }
    
    function position_has_instrument($position,$instrument){
      $regexp="((^$instrument)|(".$instrument."$)|,$instrument,)"; //Match item $instrument in a comma-separated list: could be at the beginning, the end, or between commas
      if ($r=$this->d->q("SELECT id FROM instruments_to_positions WHERE position=$position AND instruments REGEXP '$regexp' LIMIT 1;")){
        if ($r->num_rows>0){
          return true;
        }
      }      
      return false;              
    }
    
    //Return array of IDs of instruments associated with the position, in priority order (top down)
    function get_instruments_for_position($position){
      $r=$this->get_instruments_to_positions_record($position);
      if (is_array($r)){
        return explode(',',$r["instruments"]);      
      }
      return false;
    }
    
    /* instruments_to_people */
        
    //Position may be given, and indicates a particular preference for the person serving in that position
    function assign_instrument_to_person($instrument,$person,$position=0){
      if (!$this->person_has_instrument($person,$position,$instrument)){
        $e=$this->get_instruments_to_people_record($person,$position);
        if (is_array($e)){
          //Person/Position has other instrument(s) already, append
          $e["instruments"]=csl_append_element($e["instruments"],$instrument);
          return $this->d->update_record("instruments_to_people","id",$e["id"],$e);
        } else {
          $e=array();
          $e["person_id"]=$person;
          $e["position"]=$position;
          $e["instruments"]=$instrument;
          return $this->d->insert_and_get_id($e,"instruments_to_people");                              
        }        
      }
      return true; //Connection exists, so we're successful      
    }
    
    //$instruments is array of instrument ids  
    function assign_instruments_to_person($instruments,$person,$position=0){
      if (is_array($instruments) && ($person>0)){
        foreach ($instruments as $v){
          $res=$this->assign_instrument_to_person($v,$person,$position);
          if ($res===false){
            return false;
          }          
        }
        return true;
      }
      return false;
    }        

    function get_instruments_to_people_record($person,$position=0){
      if ($res=$this->d->q("SELECT * FROM instruments_to_people WHERE person_id=$person AND position=$position;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }
      }
      return false;
    }

    function unassign_instrument_from_person($instrument,$person,$position=0){
      if ($this->person_has_instrument($person,$position,$instrument)){
        $e=$this->get_instruments_to_people_record($person,$position);
        if (is_array($e)){
          $element_pos=csl_get_element_pos($e["instruments"],$instrument);
          $e["instruments"]=csl_delete_element_at_position($e["instruments"],$element_pos);
          return $this->d->update_record("instruments_to_people","id",$e["id"],$e);          
        }
        return false;
      }
      return true; //no association existed, success
    }
    
    function person_has_instrument($person,$position=0,$instrument){
      $regexp="((^$instrument)|(".$instrument."$)|,$instrument,)"; //Match item $instrument in a comma-separated list: could be at the beginning, the end, or between commas
      if ($r=$this->d->q("SELECT id FROM instruments_to_people WHERE person_id=$person AND position=$position AND instruments REGEXP '$regexp' LIMIT 1;")){
        if ($r->num_rows>0){
          return true;
        }
      }      
      return false;              
    }
    
    //Return array of IDs of instruments associated with the person (and, optionally, position), in priority order (top down)
    //if $include_zero_position, the table will be queried both with the given $position and $position=0, returns combined results (csl or array)
    function get_instruments_for_person($person,$position=0,$return_csl=false,$include_zero_position=false){
      $r=$this->get_instruments_to_people_record($person,$position);
      if (is_array($r)){
        if ($return_csl){
          if ($include_zero_position){
            return csl_append_elements($r["instruments"],$this->get_instruments_for_person($person,0,$return_csl),true);
          } else {
            return $r["instruments"];
          }
        } else {
          if ($include_zero_position){
            return explode(',',csl_append_elements($r["instruments"],$this->get_instruments_for_person($person,0,$return_csl),true));
          } else {
            return explode(',',$r["instruments"]);              
          }
        }
      }
      return false;
    }

    /* Copyright holders */
        
    //Add new or just return ID of extant record
    function add_copyright_holder($title){
      $existing_id=$this->get_copyright_holder_id($title);
      if ($existing_id==0){
        $e=array();
        $e["title"]=$title;
        $result=$this->d->insert_and_get_id($e,"copyright_holders");
        return $result;        
      } else {
        return $existing_id;
      }      
    }
    
    function get_copyright_holder_id($title){
      if ($res=$this->d->q("SELECT id FROM copyright_holders WHERE title=\"$title\";")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }      
      }
      return false;
    }
    
    function get_copyright_holder_record($id){
      if ($res=$this->d->q("SELECT * FROM copyright_holders WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }      
      }
      return false;      
    }
    
    function get_copyright_holder_title($id){
      if ($r=$this->get_copyright_holder_record($id)){
        return $r["title"];      
      }
      return false;
    }
    
    function delete_copyright_holder($id_or_title){
      if ($id_or_title>0){
        return ($this->db->query("DELETE FROM copyright_holders WHERE id=$id_or_title;"));
      } else {
        return ($this->db->query("DELETE FROM copyright_holders WHERE title=\"$id_or_title\";"));      
      }      
    }

    //Delete copyright holders that are not showing up in copyright_holders_to_music_pieces
    function delete_unused_copyright_holders(){
      $query="DELETE copyright_holders FROM copyright_holders LEFT JOIN copyright_holders_to_music_pieces ON copyright_holders.id=copyright_holders_to_music_pieces.copyright_holder WHERE copyright_holders_to_music_pieces.copyright_holder IS NULL;";
      return ($this->d->q($query));
    }

    /* copyright_holders to music_pieces */
    
    function assign_copyright_holder_to_music_piece($copyright_holder,$music_piece){
      if (!$this->music_piece_has_copyright_holder($music_piece,$copyright_holder)){
        //Association does not yet exist
        $e=array();
        $e["music_piece"]=$music_piece;
        $e["copyright_holder"]=$copyright_holder;
        return $this->d->insert_and_get_id($e,"copyright_holders_to_music_pieces");                    
      }
      return true; //If association exists then technically we're succesful
    }

    function unassign_copyright_holder_from_music_piece($copyright_holder,$music_piece){
      return $this->d->q("DELETE FROM copyright_holders_to_music_pieces WHERE music_piece=$music_piece AND copyright_holder=$copyright_holder;");
    }
    
    function unassign_all_copyright_holders_from_music_piece($music_piece){
      return $this->d->q("DELETE FROM copyright_holders_to_music_pieces WHERE music_piece=$music_piece;");    
    }
    
    function music_piece_has_copyright_holder($music_piece,$copyright_holder){
      if ($res=$this->d->q("SELECT id FROM copyright_holders_to_music_pieces WHERE music_piece=$music_piece AND copyright_holder=$copyright_holder;")){
        return ($res->num_rows>0);
      }
    }
    
    function get_copyright_holders_for_music_piece($music_piece){
      $query="
        SELECT DISTINCT
          copyright_holders.*
        FROM
          copyright_holders,copyright_holders_to_music_pieces
        WHERE
          copyright_holders_to_music_pieces.music_piece=$music_piece
        AND
          copyright_holders_to_music_pieces.copyright_holder=copyright_holders.id
        ORDER BY
          copyright_holders.title;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }

    /* Themes */
        
    //Add new or just return ID of extant record
    function add_theme($title){
      $title=strtolower($title);
      $existing_id=$this->get_theme_id($title);
      if ($existing_id==0){
        $e=array();
        $e["title"]=$title;
        $result=$this->d->insert_and_get_id($e,"themes");
        return $result;        
      } else {
        return $existing_id;
      }      
    }
    
    function get_theme_id($title){
      if ($res=$this->d->q("SELECT id FROM themes WHERE title=\"$title\";")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }      
      }
      return false;
    }
    
    function get_themes_record($id){
      if ($res=$this->d->q("SELECT * FROM themes WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }      
      }
      return false;      
    }
    
    function get_theme_title($id){
      if ($r=$this->get_themes_record($id)){
        return $r["title"];      
      }
      return false;
    }
    
    function delete_theme($id_or_title){
      if ($id_or_title>0){
        return ($this->db->query("DELETE FROM themes WHERE id=$id_or_title;"));
      } else {
        return ($this->db->query("DELETE FROM themes WHERE title=\"$id_or_title\";"));      
      }      
    }

    //Delete themes that are not showing up in themes_to_music_pieces
    function delete_unused_themes(){
      $query="DELETE themes FROM themes LEFT JOIN themes_to_music_pieces ON themes.id=themes_to_music_pieces.theme WHERE themes_to_music_pieces.theme IS NULL;";
      return ($this->d->q($query));
    }

    /* themes to music_pieces */
    
    function assign_theme_to_music_piece($theme,$music_piece){
      if (!$this->music_piece_has_theme($music_piece,$theme)){
        //Association does not yet exist
        $e=array();
        $e["music_piece"]=$music_piece;
        $e["theme"]=$theme;
        return $this->d->insert_and_get_id($e,"themes_to_music_pieces");                    
      }
      return true; //If association exists then technically we're succesful
    }

    function unassign_theme_from_music_piece($theme,$music_piece){
      return $this->d->q("DELETE FROM themes_to_music_pieces WHERE music_piece=$music_piece AND theme=$theme;");
    }
    
    function unassign_all_themes_from_music_piece($music_piece){
      return $this->d->q("DELETE FROM themes_to_music_pieces WHERE music_piece=$music_piece;");    
    }
    
    function music_piece_has_theme($music_piece,$theme){
      if ($res=$this->d->q("SELECT id FROM themes_to_music_pieces WHERE music_piece=$music_piece AND theme=$theme;")){
        return ($res->num_rows>0);
      }
    }
    
    function get_themes_for_music_piece($music_piece){
      $query="
        SELECT DISTINCT
          themes.*
        FROM
          themes,themes_to_music_pieces
        WHERE
          themes_to_music_pieces.music_piece=$music_piece
        AND
          themes_to_music_pieces.theme=themes.id
        ORDER BY
          themes.title;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }

    /* Style tags */
        
    //Add new or just return ID of extant record
    function add_style_tag($title){
      $title=strtolower($title);
      $existing_id=$this->get_style_tag_id($title);
      if ($existing_id==0){
        $e=array();
        $e["title"]=$title;
        $result=$this->d->insert_and_get_id($e,"style_tags");
        return $result;        
      } else {
        return $existing_id;
      }      
    }
    
    function get_style_tag_id($title){
      if ($res=$this->d->q("SELECT id FROM style_tags WHERE title=\"$title\";")){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }      
      }
      return false;
    }
    
    function get_style_tags_record($id){
      if ($res=$this->d->q("SELECT * FROM style_tags WHERE id=$id;")){
        if ($r=$res->fetch_assoc()){
          return $r;
        }      
      }
      return false;      
    }
    
    function get_style_tag_title($id){
      if ($r=$this->get_style_tags_record($id)){
        return $r["title"];      
      }
      return false;
    }
    
    function delete_style_tags($id_or_title){
      if ($id_or_title>0){
        return ($this->db->query("DELETE FROM style_tags WHERE id=$id_or_title;"));
      } else {
        return ($this->db->query("DELETE FROM style_tags WHERE title=\"$id_or_title\";"));      
      }      
    }

    //Delete style_tags that are not showing up in style_tags_to_arrangements
    function delete_unused_style_tags(){
      $query="DELETE style_tags FROM style_tags LEFT JOIN style_tags_to_arrangements ON style_tags.id=style_tags_to_arrangements.style_tag WHERE style_tags_to_arrangements.style_tag IS NULL;";
      return ($this->d->q($query));
    }

    /* Style tags to arragements */
    
    function assign_style_tag_to_arrangement($style_tag,$arrangement){
      if (!$this->arrangement_has_style_tag($arrangement,$style_tag)){
        //Association does not yet exist
        $e=array();
        $e["arrangement"]=$arrangement;
        $e["style_tag"]=$style_tag;
        return $this->d->insert_and_get_id($e,"style_tags_to_arrangements");                    
      }
      return true; //If association exists then technically we're succesful
    }

    function unassign_style_tag_from_arrangement($style_tag,$arrangement){
      return $this->d->q("DELETE FROM style_tags_to_arrangements WHERE arrangement=$arrangement AND style_tag=$style_tag;");
    }
    
    function unassign_all_style_tags_from_arrangement($arrangement){
      return $this->d->q("DELETE FROM style_tags_to_arrangements WHERE arrangement=$arrangement;");    
    }
    
    function arrangement_has_style_tag($arrangement,$style_tag){
      if ($res=$this->d->q("SELECT id FROM style_tags_to_arrangements WHERE arrangement=$arrangement AND style_tag=$style_tag;")){
        return ($res->num_rows>0);
      }
    }
    
    function get_style_tags_for_arrangement($arrangement){
      $query="
        SELECT DISTINCT
          style_tags.*
        FROM
          style_tags,style_tags_to_arrangements
        WHERE
          style_tags_to_arrangements.arrangement=$arrangement
        AND
          style_tags_to_arrangements.style_tag=style_tags.id
        ORDER BY
          style_tags.title;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }

    /* keychanges to arragements */
  
    function assign_keychange_to_arrangement($musical_key,$arrangement){
      if ($this->last_keychange($arrangement)!=$musical_key){
        //Last keychange was not to this key - go ahead
        $e=array();
        $e["arrangement"]=$arrangement;
        $e["musical_key"]=$musical_key;
        $no=$this->get_number_of_keychanges($arrangement);
        if ($no!==false){
          $e["sequence_no"]=$no+1;
          //Update "keychanges" field in arrangement record
          $f=array();
          $f["keychanges"]=$no+1;
          return (
            ($this->d->insert_and_get_id($e,"keychanges_to_arrangements"))
              &&
            ($this->update_arrangement_record($arrangement,$f))
          );                            
        }
      }
      return true; //If last keychange was the same then technically we're succesful
    }
    
    function unassign_all_keychanges_from_arrangement($arrangement){
      //Update "keychanges" field in arrangement record as well
      $f=array();
      $f["keychanges"]=0;
      return (
        ($this->d->q("DELETE FROM keychanges_to_arrangements WHERE arrangement=$arrangement;"))
          &&
        ($this->update_arrangement_record($arrangement,$f))      
      );    
    }
                                
    //Retrieve the last keychange (Highest seq_no) for this arr and return the key
    function last_keychange($arrangement){
      if ($res=$this->d->q("SELECT musical_key FROM keychanges_to_arrangements WHERE arrangement=$arrangement ORDER BY sequence_no DESC")){
        //If no record found, get original key of the arrangement
        if ($res->num_rows==0){
          $arr=$this->get_arrangement_record($arrangement);
          if (is_array($arr)){
            return $arr["musical_key"];
          }
        } else {
          if ($r=$res->fetch_assoc()){
            return $r["musical_key"];
          }
        }
      }
      return false;
    }
    
    function get_number_of_keychanges($arrangement){
      if ($res=$this->d->q("SELECT id FROM keychanges_to_arrangements WHERE arrangement=$arrangement")){
        return $res->num_rows;
      }
      return false;
    }
    
    function get_keychanges_for_arrangement($arrangement){
      $query="
        SELECT
          *
        FROM
          keychanges_to_arrangements
        WHERE
          arrangement=$arrangement
        ORDER BY
          sequence_no;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
      }
      return $t;
    }


    
    /* Musical key to TINYINT and back */
    
    private function get_musical_keys_array(){
      return array(
        'C'=>1,
        'C#'=>2,
        'Db'=>3,
        'D'=>4,
        'D#'=>5,
        'Eb'=>6,
        'E'=>7,
        'F'=>8,
        'F#'=>9,
        'Gb'=>10,
        'G'=>11,
        'G#'=>12,
        'Ab'=>13,
        'A'=>14,
        'A#'=>15,
        'Bb'=>16,
        'B'=>17      
      );    
    }
    
    //Expects a string like C-major, or D#-minor (dash is mandatory)
    function musical_key_to_int($t){
      $keys=cw_Music_db::get_musical_keys_array();
      $x=explode('-',$t);
      $x[0]=strtoupper(substr($x[0],0,1)).substr($x[0],1);
      if (array_key_exists($x[0],$keys)){
        $base_no=$keys[$x[0]]; //Got the abs number
        if (substr(strtolower($x[1]),0,2)=="mi"){
          //Minor mode
          return -$base_no;
        } else {
          //Major mode
          return $base_no;        
        }
      }
      return false; //invalid key          
    }
    
    function int_to_musical_key($i,$short=false){
      $keys=cw_Music_db::get_musical_keys_array();
      ($i<0) ? $mode="-minor" : $mode="-major";
      if ($short){
        //Abbreviated mode
        if ($i<0){
          $mode="m";
        } else {
          $mode="";
        }
      }
      $no=abs($i);
      if ($no<=sizeof($keys)){
        //Range is valid
        $key=array_search($no,$keys);
        return $key.$mode;
      }      
      return "out of range";
    }
    
    //$selected must be an int value
    function get_musical_keys_for_select($selected=0){
      $keys=cw_Music_db::get_musical_keys_array();
      $t="";
      foreach ($keys as $k=>$v){
        ($v==abs($selected)) ? $sel="selected=\"SELECTED\"" : $sel="";
        $t.="<option value='$v' $sel>$k</option>";
      }
      return $t;
    }
    
    //Positive $selected is major, negative is minor
    function get_musical_modes_for_select($selected=0){
      $major_selected="";
      $minor_selected="";
      if ($selected>0){
        $major_selected="selected=\"SELECTED\"";
      } elseif ($selected<0){
        $minor_selected="selected=\"SELECTED\"";      
      }
      $t="
        <option value='+' $major_selected>major</option>
        <option value='-' $minor_selected>minor</option>        
      ";
      return $t;    
    }

    function key_is_guitar_friendly($t){
      $s=explode(',',CW_GUITAR_FRIENDLY_KEYS);
      $r=array();
      foreach ($s as $v){
        $r[]=$this->musical_key_to_int($v);
      }
      return in_array($t,$r);
    }
    
    function int_to_guitar_friendliness($i){
      $res="";
      switch ($i){
        case 1:
          $res="partly guitar-friendly";
          break;
        case 2:
          $res="guitar-friendly";
          break;
      }
      return $res;
    }
    
    //Return JSON. Search over title,alt_title,writers last and firstname, theme, lyrics fragments, scripture references
    //Allow for optional parameters like style=rock,hymn or style!=rock
    //If $query_only is set, the function will return the query-string
    function get_songsearch_autocomplete_suggestions($term,$field=0,$query_only=false){
      $possible_switches=array("-key","-style","-instruments","-instrument");
      //Check for and extract switches
      $switches=array();
      $first_switch_pos=strlen($term); //Init
      foreach ($possible_switches as $v){
        $p=strpos($term,$v);
        if ($p!==false){
          //Found switch $v. Get value.
          ($first_switch_pos>$p) ? $first_switch_pos=$p : null; //Remember the position of the first switch, to cut them all off the remaining term
          $val=substr($term,$p+strlen($v));
          $next_space=strpos($val," -");
          if ($next_space!==false){
            $val=substr($val,0,$next_space);
          }
          $is_negated=(substr($val,0,1)=="!");
          $is_negated ? $val_string=substr($val,2) : $val_string=substr($val,1); //Cut != or =, respectively
          if (!empty($val_string)){
            $key_name=substr($v,1);
            $switches[$key_name]=array();
            $switches[$key_name]["negated"]=$is_negated;
            $switches[$key_name]["string"]=$val_string;
            $switches[$key_name]["arguments"]=explode(',',$val_string);          
          }
        }
      }     
      //Cut switches part off
      $term=trim(substr($term,0,$first_switch_pos));
      //If there is no value in any of the switches, and no search term, then quit prematurely (to avoid showing the entire list)
      if ((subfield_is_empty($switches,"string")) && empty($term)){
        return false;
      }      
      //preserve unescaped term
      $orig_term=$term;
      //mysql escape
      $term=mysqli_real_escape_string($this->d->db,$term);
      
      if ($field==0){
        //Title or scripture reference search
        //See if $term is a valid scripture reference
        $ref=$this->scripture_handling->scripture_refs->identify_multi_range($term);
        //var_dump($ref);
        if (is_array($ref)){
          //Have scripture ref - so search those
          $query="
            SELECT DISTINCT
              music_pieces.*
            FROM
              music_pieces
                LEFT JOIN scripture_refs ON music_pieces.id=scripture_refs.id                
                LEFT JOIN scripture_refs_to_music_pieces ON scripture_refs.id=scripture_refs_to_music_pieces.scripture_ref
            WHERE
            (
              NOT
               (
                  scripture_refs.start>".$ref[0]["end"]." 
                OR
                  scripture_refs.end<".$ref[0]["start"]."
               )
            )
            ORDER BY 
              music_pieces.title
          ";
        } else {
          //Titles
          $query="
            SELECT DISTINCT
             *
            FROM
              music_pieces             
            WHERE
              music_pieces.title LIKE \"%$term%\"
            OR
              music_pieces.alt_title LIKE \"%$term%\"
            OR
              music_pieces.orig_title LIKE \"%$term%\"
            ORDER BY 
              music_pieces.title
          ";
        }      
      } elseif ($field==1){
        //writer
        $query="
          SELECT DISTINCT
            music_pieces.*
          FROM
            music_pieces
              LEFT JOIN writers_to_music_pieces ON music_pieces.id=writers_to_music_pieces.music_piece
              LEFT JOIN writers ON writers_to_music_pieces.writer=writers.id
          WHERE
              writers.last_name LIKE \"%$term%\"
            OR
              writers.first_name LIKE \"%$term%\"
            OR
              CONCAT(writers.first_name,' ',writers.last_name) LIKE \"%$term%\"
            OR
              CONCAT(writers.last_name,' ',writers.first_name) LIKE \"%$term%\"
            ORDER BY 
              music_pieces.title
        ";
      } elseif ($field==2){
        //theme
        $query="
          SELECT DISTINCT
            music_pieces.*
          FROM
            music_pieces
              LEFT JOIN themes_to_music_pieces ON music_pieces.id=themes_to_music_pieces.music_piece
              LEFT JOIN themes ON themes_to_music_pieces.theme=themes.id
          WHERE
            themes.title LIKE \"%$term%\"                  
          ORDER BY 
            music_pieces.title
        ";
      } elseif ($field==3){
        $query="
          SELECT DISTINCT
            music_pieces.*
          FROM
            music_pieces LEFT JOIN lyrics ON music_pieces.id=lyrics.music_piece
          WHERE
            lyrics.content LIKE \"%$term%\"            
          ORDER BY 
            music_pieces.title
        ";
      }   

      if (isset($switches["style"])){      
        if ($switches["style"]["negated"]){
          $not="NOT";
          $concat="AND";
        } else {
          $not="";
          $concat=" OR";
        }
        $query_extension="";
        foreach ($switches["style"]["arguments"] as $v){
          $query_extension.=" $concat $not style_tags.title LIKE \"$v%\"" ;
        }
        $query_extension=substr($query_extension,4); //cut first OR or AND
        $query_extension="( $query_extension )";
        $query="
          SELECT DISTINCT
            a.*
          FROM
            ($query) AS a
            LEFT JOIN arrangements ON a.id=arrangements.music_piece
            LEFT JOIN style_tags_to_arrangements ON arrangements.id=style_tags_to_arrangements.arrangement
            LEFT JOIN style_tags ON style_tags_to_arrangements.style_tag=style_tags.id
          WHERE
            $query_extension;           
        ";   
      } elseif (isset($switches["instruments"])){
        if ($switches["instruments"]["negated"]){
          $not="NOT";
          $concat="AND";
        } else {
          $not="";
          $concat=" OR";
        }
        $query_extension="";
        foreach ($switches["instruments"]["arguments"] as $v){
          $query_extension.=" $concat $not instruments.title LIKE \"$v%\"" ;
        }
        $query_extension=substr($query_extension,4); //cut first OR or AND
        $query_extension="( $query_extension )";
        $query="
          SELECT DISTINCT
            a.*
          FROM
            ($query) AS a
            LEFT JOIN arrangements ON a.id=arrangements.music_piece
            LEFT JOIN files_to_instruments_and_arrangements ON arrangements.id=files_to_instruments_and_arrangements.arrangement
            LEFT JOIN instruments ON files_to_instruments_and_arrangements.instrument=instruments.id
          WHERE
            $query_extension;           
        ";         
      } elseif (isset($switches["instrument"])){
        if ($switches["instrument"]["negated"]){
          $not="NOT";
          $concat="AND";
        } else {
          $not="";
          $concat=" OR";
        }
        $query_extension="";
        foreach ($switches["instrument"]["arguments"] as $v){
          $query_extension.=" $concat $not instruments.title LIKE \"$v%\"" ;
        }
        $query_extension=substr($query_extension,4); //cut first OR or AND
        $query_extension="( $query_extension )";
        $query="
          SELECT DISTINCT
            a.*
          FROM
            ($query) AS a
            LEFT JOIN arrangements ON a.id=arrangements.music_piece
            LEFT JOIN files_to_instruments_and_arrangements ON arrangements.id=files_to_instruments_and_arrangements.arrangement
            LEFT JOIN instruments ON files_to_instruments_and_arrangements.instrument=instruments.id
          WHERE
            $query_extension;           
        ";         
      } elseif (isset($switches["key"])){
        if ($switches["key"]["negated"]){
          $not="NOT";
          $concat="AND";
        } else {
          $not="";
          $concat=" OR";
        }
        $query_extension="";
        foreach ($switches["key"]["arguments"] as $v){
          $mk=$this->musical_key_to_int($v);
          if (!empty($mk)){
            $query_extension.=" $concat $not arrangements.musical_key = \"".$mk."\" ";
          }
        }
        if (!empty($query_extension)){
          $query_extension=substr($query_extension,4); //cut first OR or AND
          $query_extension="( $query_extension )";
        }
        //Guitar-friendly keys wanted?       
        if (strpos($switches["key"]["string"],"guitar")!==false){
          $query_extension2.=" AND arrangements.guitar_friendly>0 ";
        }
        //Keychanges forbidden?
        if (strpos($switches["key"]["string"],"nochange")!==false){
          $query_extension2.=" AND arrangements.keychanges=0 ";
        }            
        if (!empty($query_extension2)){
          $query_extension2=substr($query_extension2,4); //cut first AND
          $query_extension2="( $query_extension2 )";
          if (empty($query_extension)){
            $query_extension=$query_extension2;              
          } else {
            //Have no two extensions to be concatenated with and
            $query_extension=" ".$query_extension." AND ".$query_extension2." ";                          
          }
        }
        $query="
          SELECT DISTINCT
            a.*
          FROM
            ($query) AS a
            LEFT JOIN arrangements ON a.id=arrangements.music_piece
          WHERE
            $query_extension;           
        ";      
      
      }
      
      if ($query_only){
        //Calling function only wanted the query, return (called by the non-autocomplete search in musicdb.php)
        return $query;
      }      
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }        
        //Now we have array $t of music_pieces - but we need to add author info for labeling: conduct another search for each song
        $t2=array();
        //Acquire often needed values
        $writer_capacity_composer=$this->get_writer_capacities_id(CW_MUSICDB_WRITER_CAPACITY_COMPOSER);
        $writer_capacity_lyricist=$this->get_writer_capacities_id(CW_MUSICDB_WRITER_CAPACITY_LYRICIST);
        //Loop over each song
        foreach ($t as $v){
          //$v has a music_piece record
          $writers=$this->get_writers_for_music_piece($v["id"]);
          //$writers potentially has one or more writers records
          $writer_info="";
          //Loop over writers
          foreach ($writers as $v2){
            //$v2 has writer record
            $writer_info.=", ".substr($v2["first_name"],0,1).". ".$v2["last_name"];
          }
          //Cut first comma
          $writer_info=substr($writer_info,2);
          $v["writer_info"]="";
          if (!empty($writer_info)){
            $v["writer_info"]="(".$writer_info.")";          
          }
          $t2[]=$v;          
        }
        //Now $t2 has music_piece records with an additional field "writer_info"
        
        
        //Now make JSON string
        $z="";
        foreach ($t2 as $v){
          $label=$v["title"];
          $value=$v["title"];
          if (!empty($v["alt_title"])){
            $label.=" (".$v["alt_title"].")";
          }
          $z.=",
            {
              \"id\":\"".$v["id"]."\",
              \"label\":\"$label ".$v["writer_info"]."\",
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


    //Return JSON.
    function get_writersearch_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT
          *
        FROM
          writers
        WHERE
          (last_name LIKE \"%$term%\"
        OR
          first_name LIKE \"%$term%\")
        ORDER BY
          last_name;
      ";    
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r;
        }
        //Now make JSON string
        $z="";
        foreach ($t as $v){
          $label=$v["first_name"]." ".$v["last_name"];
          $value=substr($v["first_name"],0,1).". ".$v["last_name"]." (#".$v["id"].")";
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

    //Get a string for the input value (on load of a song). $writer_capacity is string
    function get_writer_string_for_music_piece($music_piece,$writer_capacity,$include_writer_id=true){
      $t="";
      if ($writer_capacity_id=$this->get_writer_capacities_id($writer_capacity)){
        if ($r=$this->get_writers_for_music_piece($music_piece,$writer_capacity_id)){
          foreach ($r as $v){
            $t.=", ".substr($v["first_name"],0,1).". ".$v["last_name"];
            if ($include_writer_id){
              $t.=" (#".$v["id"].")";
            }
          }
          if ($t!=""){
            $t=substr($t,2); //cut first comma
          }          
        }      
      }
      return $t;
    }

    //Get a string for the input value (on load of an arrangement). $writer_capacity is string
    function get_writer_string_for_arrangement($arrangement,$writer_capacity,$include_writer_id=true){
      $t="";
      if ($writer_capacity_id=$this->get_writer_capacities_id($writer_capacity)){
        if ($r=$this->get_writers_for_arrangement($arrangement,$writer_capacity_id)){
          foreach ($r as $v){
            $t.=", ".substr($v["first_name"],0,1).". ".$v["last_name"];
            if ($include_writer_id){
              $t.=" (#".$v["id"].")";
            }
          }
          if ($t!=""){
            $t=substr($t,2); //cut first comma
          }          
        }      
      }
      return $t;
    }
    
    //Get a string for the input value (on load of a song). 
    function get_copyright_holder_string_for_music_piece($music_piece,$include_copyright_holder_id=true){
      $t="";
      if ($r=$this->get_copyright_holders_for_music_piece($music_piece)){
        foreach ($r as $v){
          $t.=", ".$v["title"];
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

    //Return JSON
    function get_copyright_holders_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT
          *
        FROM
          copyright_holders
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
          $value=$v["title"]." (#".$v["id"].")";
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
    function get_themes_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT
          *
        FROM
          themes
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
          $value=$v["title"];
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
    function get_style_tags_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT
          id,title as label
        FROM
          style_tags
        WHERE
          title LIKE \"%$term%\"
        ORDER BY
          title;
      ";    
      return $this->d->select_flat_json($query);      
    }

    //Return JSON
    function get_arrangement_titles_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT DISTINCT
          title as label
        FROM
          arrangements
        WHERE
          title LIKE \"%$term%\"
        ORDER BY
          title;
      ";    
      return $this->d->select_flat_json($query);      
    }

    //Return JSON
    function get_source_titles_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT DISTINCT
          title as label
        FROM
          sources
        WHERE
          title LIKE \"%$term%\"
        ORDER BY
          title;
      ";    
      return $this->d->select_flat_json($query);      
    }

    //Return JSON
    function get_parts_autocomplete_suggestions($term){
      $term=mysqli_real_escape_string($this->d->db,$term);
      $query="
        SELECT DISTINCT
          id,title as label
        FROM
          instruments
        WHERE
          title LIKE \"%$term%\"
        ORDER BY
          title;
      ";    
      return $this->d->select_flat_json($query);      
    }
    
    function get_size_of_music_pieces($include_retired=false){
      $include_retired ? $cond='' : $cond='active=true';
      return $this->d->get_size("music_pieces",$cond);
    }
    
    //**************** part files
    
    //$instruments contains a string with comma-separated instrument names
    function add_part_file($arrangement_id,$instruments,$tmp_file,$upload_name){
      //Before dealing with the file, check arrangement_id and instruments
      if ($arrangement_id>0){
        //Get an array of instrument_ids from the string in $instruments
        $instrument_titles=explode(',',$instruments);
        $instrument_ids=array();
        foreach ($instrument_titles as $v){
          if (!empty($v)){
            $n=$this->get_instruments_id(trim($v));//Trim whitespace
            if ($n>0){
              //Found an instrument
              $instrument_ids[]=$n;
            }
          }
        }
        if (sizeof($instrument_ids)>0){
          //At least one instrument found that's in this part
          //Now see if the file transfer succeeds              
          //$tmp_file should come from $_FILES["filesel"]["tmp_name"]
          //$upload_name has filename with extension
          $ext=strtolower(get_extension($upload_name)); //utils misc
          if ($ext=="pdf"){
            //We'll discard the original name and give a descriptive name
            //$name=get_filename_without_extension($upload_name); //utils_misc
            //Get music piece and arrangement records for title
            $arr_rec=$this->get_arrangement_record($arrangement_id);
            $piece=$this->get_music_piece_record($arr_rec["music_piece"]);
            empty($arr_rec["title"]) ? $arr_info="(arr#".$arrangement_id.")" : $arr_info="(".$arr_rec["title"].")";
            //Take away comma and whitespace at the end of $instruments
            $instruments=trim($instruments); //whitespace
            (substr($instruments,-1)==",") ? $instruments=substr($instruments,0,-1) : null;
            $name=$piece["title"]." ".$arr_info." ".$instruments;            
            $f=new cw_Files($this->d);
            $file_id=$f->add_uploaded_file($tmp_file,$name,$ext,$this->auth->cuid);
            if ($file_id>0){
              //Filetransfer succeeded. Now create associations.
              foreach ($instrument_ids as $v){
                $this->assign_file_to_instrument_and_arrangement($file_id,$v,$arrangement_id);            
              } 
              return true;
            }
            return $file_id;
          }
          return "part must be a pdf-file";
        }      
      }      
    }
    
    //Does two things: delete the files_to_instruments_and_arrangements association, and removes the files record along with the physical file    
    function delete_part_file($file_id,$arrangement_id){
      //First, get the records 
      $r=$this->get_files_to_instruments_and_arrangements_records_for_file($file_id,$arrangement_id);
      if (is_array($r)){
        foreach ($r as $v){
          if (!$error){
            $error=(!$this->unassign_file_from_instrument_and_arrangement($file_id,$v["instrument"],$arrangement_id));
          } 
        }  
        if (!$error){
          //Remove file
          $f=new cw_Files($this->d);
          $error=(!$f->remove_file($file_id));
        }
        return (!$error);      
      }      
      return false;
    }

    //This for the usort call below    
    function cmp($a,$b){
      return ($a["instruments"]>$b["instruments"]);
    }
    
    function get_parts_for_arrangement($arrangement){
    
      //This will get the different part files that belong to the arr, sorted by instrument title
      $query="
        SELECT
          files_to_instruments_and_arrangements.*
        FROM
          files_to_instruments_and_arrangements,instruments
        WHERE
          files_to_instruments_and_arrangements.arrangement=$arrangement
        AND
          files_to_instruments_and_arrangements.instrument>0
        AND
          instruments.id=files_to_instruments_and_arrangements.instrument
        ORDER BY
          files_to_instruments_and_arrangements.file      
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        $prev_file=0;    
        while ($r=$res->fetch_assoc()){
          if (($r["file"]!=$prev_file) && ($prev_file>0)){
            //New file: send old first
            $t[]=array("file_id"=>$prev_file,"instruments"=>substr($instruments_in_this_part,2));
            $instruments_in_this_part="";
          }
          $instruments_in_this_part.=", ".$this->get_instruments_title($r["instrument"]);
          $prev_file=$r["file"];
        }
        if ($prev_file!=0){
          //Must duplicate this line to get the last part, too
          $t[]=array("file_id"=>$prev_file,"instruments"=>substr($instruments_in_this_part,2));
        }
      }
      //$t is array with file_ids and a string with an instrument list
      //Sort by that second subfield (instrument list)
      usort($t,"cw_Music_db::cmp");
      return $t;
    }
    
    //Outputs array of instrument ids
    function get_instruments_for_arrangement($arrangement){
      $query="
        SELECT
          *
        FROM
          files_to_instruments_and_arrangements
        WHERE
          arrangement=$arrangement
        AND
          instrument>0
        ORDER BY
          file;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          $t[]=$r["instrument"];  
        }
      }
      return $t;    
    }

    //*********************** other files **********************
    
    function add_other_file($arrangement_id,$tmp_file,$upload_name){
      if ($arrangement_id>0){
        //See if the file transfer succeeds              
        //$tmp_file should come from $_FILES["filesel_other"]["tmp_name"]
        //$upload_name has filename with extension
        $ext=strtolower(get_extension($upload_name)); //utils misc
        $name=get_filename_without_extension($upload_name); //utils_misc
        $f=new cw_Files($this->d);
        $file_id=$f->add_uploaded_file($tmp_file,$name,$ext,$this->auth->cuid);
        if ($file_id>0){
          //Filetransfer succeeded. Now create association. Other files get saved in same table as parts
          //Convention: instrument=-1 for media files, -2 for images, -3 for notation (sibelius, finale), 0 for other files
          $v=0;
          is_mediafile_extension($ext) ? $v=-1 : null; //utilities_misc
          is_imagefile_extension($ext) ? $v=-2 : null; //utilities_misc
          is_notation_extension($ext) ? $v=-3 : null; //utilities_misc
          $this->assign_file_to_instrument_and_arrangement($file_id,$v,$arrangement_id);            
          return true;
        }
        return $file_id;              
      }      
    }
    
    //Does two things: delete the files_to_instruments_and_arrangements association, and removes the files record along with the physical file    
    function delete_other_file($file_id,$arrangement_id){
      //First, get the record - should only be one in this case (since this is no partsfile that can contain multiple instruments)
      $r=$this->get_files_to_instruments_and_arrangements_records_for_file($file_id,$arrangement_id);
      if (is_array($r)){
        foreach ($r as $v){
          if (!$error){
            $error=(!$this->unassign_file_from_instrument_and_arrangement($file_id,$v["instrument"],$arrangement_id));
          } 
        }  
        if (!$error){
          //Remove file
          $f=new cw_Files($this->d);
          $error=(!$f->remove_file($file_id));
        }
        return (!$error);      
      }      
      return false;
    }

    function get_other_files_for_arrangement($arrangement){
      //This will get the different other files that belong to the arr. Other files have 0 or negative instrument value.
      $query="
        SELECT DISTINCT
          files.*
        FROM
          files,files_to_instruments_and_arrangements
        WHERE
          files_to_instruments_and_arrangements.arrangement=$arrangement
        AND
          files_to_instruments_and_arrangements.instrument<=0
        AND
          files_to_instruments_and_arrangements.file=files.id
        ORDER BY
          files.name;
      ";
      $t=array();
      if ($res=$this->d->q($query)){
        while ($r=$res->fetch_assoc()){
          //We're expecting only one record per file here. Generate label.
          empty($r["ext"]) ? $ext="" : $ext=".".$r["ext"];
          $r["label"]=$r["name"].$ext." (".bytes_to_human_readable_filesize($r["size"]).")"; //utilities misc
          $t[]=$r; 
        }
      }
      return $t;
    }
    
    //Lyrics
    function get_lyrics_pdf_for_arrangement($arrangement){
      /* pseudo: Generate pdf-file, add to database, return file_id*/
      $seq_recs=$this->get_lyrics_records_from_csl($this->get_lyrics_csl_for_arrangement($arrangement),true);
      //Only do anything if this arrangement has lyrics
      if ( (is_array($seq_recs)) && (sizeof($seq_recs)>0) ){
        //Get all the details
        $arr_rec=$this->get_arrangement_record($arrangement);
        $piece_rec=$this->get_music_piece_record($arr_rec["music_piece"]);
        empty($arr_rec["title"]) ? $arr_info="(arr#".$arrangement.")" : $arr_info="(".$arr_rec["title"].")";
        $filename=$piece_rec["title"]." $arr_info lyrics";
        //if file exists, append random string
        $addendum="";
        while (file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename.$addendum.".pdf")){
          $addendum=" ".create_sessionid(3);     
        }
        $local_name=$filename.".pdf"; //Save the filename without the addendum so that it doesn't get passed back on download
        $filename=$filename.$addendum.".pdf";      
        if ($pdf=new cw_pdf('P','mm','Letter')){
          $page_title='Lyrics for '.$this->get_music_piece_title_info($arr_rec["music_piece"]).' '.$arr_info;
          $pdf->set_page_title(utf8_decode($page_title));
          
          $types=$this->get_fragment_types_records();
  
          $pdf->AliasNbPages();
          $pdf->AddPage();
          
          foreach($seq_recs as $v){
            //Determine actual title of fragment (number 1 is only given for verse)
            (($types[$v["fragment_type"]-1]["title"]=="verse") || ($v["fragment_no"]>1)) ? $fno=" ".$v["fragment_no"] : $fno="";                         
            ($v["language"]!=CW_DEFAULT_LANGUAGE) ? $language=" (".$this->int_to_language($v["language"]).")" : $language="";               
            $pdf->SetFont('Times','I',12);
            $pdf->SetX(8);
            $pdf->Write(5,$types[$v["fragment_type"]-1]["title"].$fno.$language."\n");
            $pdf->SetFont('Times','',12);
            $pdf->Write(5,utf8_decode(double_newline_to_single_newline($v["content"]))."\n\n");
          }
          
          //Write pdf
          $pdf->Output(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename,"F");
          
          if (file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename)){
            //PDF generation succeeded, now add file to db
            $f=new cw_Files($this->d);
            return $f->add_existing_file_to_db(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename,time()+CW_ON_THE_FLY_FILE_TTL,0,$local_name);      
          }
        }      
      }
      return false;
    }
    
    //Return array of all file_ids (from files table) that belong to the musicdb, i.e. that are referenced in files_to_instruments_and_arrangements
    //For backup script
    function get_all_musicdb_file_ids(){
      $query="SELECT DISTINCT file FROM files_to_instruments_and_arrangements";
      if ($res=$this->d->q($query)){
        $t=array();
        while ($r=$res->fetch_assoc()){
          $t[]=$r["file"];
        }
        return $t;
      }    
      return false;
    }
    
    
    
}

?>
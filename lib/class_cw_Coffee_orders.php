<?php

class cw_Coffee_orders {

    private $a,$eh,$d; //Database access
    
    function __construct($auth,$event_handling){
      $this->a = $auth;
      $this->d = $auth->d;
      $this->eh = $event_handling;
    }
    
    function check_for_table($table){
      return $this->d->table_exists($table);
    }
    
    function create_tables(){
      return (
              ($this->d->q("CREATE TABLE drinks (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          type TINYINT,
                          label char(50)
                        )"))                                                
                &&        
              ($this->d->q("CREATE TABLE drinks_to_people (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          person_id INT,
                          drink INT,
                          size INT,
                          add_ons varchar(255)
                        )"))                        
                &&        
              ($this->d->q("CREATE TABLE coffee_orders (
                          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          church_service_id INT,
                          person_id INT
                        )"))                                                
            );
            /*
              drinks:
                type can be 0=drink, 1=size, 2=add_on
                label can be the name of a drink (coffee, french vanilla...) 
                  or the label of a size (small, large, grande, venti...)
                  or the label of an add-on (sugar, cream, honey...)
               
              drinks_to_people
                drink, size and add_ons all refer to the 'drinks' table
                drink and size are just ids from 'drinks'
                add_ons is a csl: drinks_id.quantity (e.g. 3.2 could mean double-honey) 
                
              coffee_orders
                church_service_id: church service
                person_id: this is used to look up the person's drink of choice in drinks_to_people
                
                To get everyone who wants a drink for a church service:
                  SELECT person_id FROM coffee_orders WHERE church_service_id=(church service id)
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
      $tables=array("drinks",
                    "drinks_to_people",
                    "coffee_orders");
      $this->check_for_and_drop_tables($tables);
      $res=$this->create_tables();
      if ($res && $default_records){
        //type 0 = drinks
        $this->add_drink("coffee");
        $this->add_drink("french vanilla");
        $this->add_drink("english toffee");
        $this->add_drink("hot chocolate");
        $this->add_drink("peppermint tea");
        $this->add_drink("earl grey tea");
        $this->add_drink("steeped tea");
        $this->add_drink("honey lemon tea");
        //type 1 = sizes
        $this->add_size("small"); 
        $this->add_size("medium");
        $this->add_size("large");
        $this->add_size("extra large");
        $this->add_size("tall");
        $this->add_size("grande");
        $this->add_size("venti");
        //type 2 = add_ons
        $this->add_addon("cream"); 
        $this->add_addon("sugar");
        $this->add_addon("honey");
        $this->add_addon("lemon");
        $this->add_addon("whipped cream");
        $this->add_addon("espresso shot");        
      }
      return $res;
    }


    /* drinks */

    function add_drink($label,$type=0){
      if (!$this->drink_exists($label,$type=0)){
        $e=array();
        $e["label"]=$label;
        $e["type"]=$type;
        return $this->d->insert_and_get_id($e,"drinks");
      }
      return false;
    }
        
    function drink_exists($label,$type=0){
      $query="SELECT id FROM drinks WHERE label='$label' and type=$type;";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }
      return false;
    }
    
    function delete_drink($id){
      return $this->d->delete($id,"drinks","id");
    }
    
    function relabel_drink($id,$label){
      if ($e=$this->get_drink_record($id)){
        $e["label"]=$label;
        return $this->d->update_record("drinks","id",$id,$e);            
      }
      return false;
    }

    function get_drink_record($id){
      return $this->d->get_record("drinks","id",$id);
    }
    
    function get_drink_label($id){
      if ($g=$this->get_drink_record($id)){
        return $g["label"];
      }
      return false;    
    }
    
    //Type 0: drinks, 1: sizes, 2:add-ons
    function get_drink_records($type=0){
      //Sort actual drink by label, don't sort sizes and add_ons
      $orderby="";
      if ($type==0){
        $orderby="ORDER BY label";
      }
      return $this->d->retrieve_all("SELECT * FROM drinks WHERE type=$type $orderby;");      
    }
    
    function add_size($label){
      //type 1=size
      if (!$this->drink_exists($label,1)){
        $e=array();
        $e["label"]=$label;
        $e["type"]=1;
        return $this->d->insert_and_get_id($e,"drinks");
      }
      return false;      
    }
    
    function delete_size($label){
      $query="DELETE * FROM drinks WHERE label='$label' AND type=1";
      return $this->d->q($query);
    }
    
    function add_addon($label){
      //type 2=add-on
      if (!$this->drink_exists($label,2)){
        $e=array();
        $e["label"]=$label;
        $e["type"]=2;
        return $this->d->insert_and_get_id($e,"drinks");
      }
      return false;      
    }

    function delete_addon($label){
      $query="DELETE * FROM drinks WHERE label='$label' AND type=2";
      return $this->d->q($query);
    }

    function get_first_drink_id(){
      $query="SELECT id FROM drinks WHERE type=0 ORDER BY id LIMIT 1;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }    

    function get_first_size_id(){
      $query="SELECT id FROM drinks WHERE type=1 ORDER BY id LIMIT 1;";
      if ($res=$this->d->q($query)){
        if ($r=$res->fetch_assoc()){
          return $r["id"];
        }
      }
      return false;
    }    

    /* drinks_to_people */

    function get_drinks_to_people_record_by_id($id){
      return $this->d->get_record("drinks_to_people","id",$id);    
    }    

    function get_drinks_to_people_record($person_id){
      $query="SELECT * FROM drinks_to_people WHERE person_id=$person_id;";
      if ($res=$this->d->q($query)){
        return $res->fetch_assoc();
      }
      return false;    
    }   
        
    //$add_ons must be csl: drink_id.quantity,...
    function save_drinks_to_people_record($person_id,$drink,$size,$add_ons){
      $e=$this->get_drinks_to_people_record($person_id);
      if (is_array($e)){
        //Update
        $e["drink"]=$drink;
        $e["size"]=$size;
        $e["add_ons"]=$add_ons;
        return $this->d->update_record("drinks_to_people","id",$e["id"],$e);                     
      } else {
        //New
        $e=array();        
        $e["person_id"]=$person_id;
        $e["drink"]=$drink;
        $e["size"]=$size;
        $e["add_ons"]=$add_ons;
        return $this->d->insert_and_get_id($e,"drinks_to_people");
      }    
    }
    
    function delete_drinks_to_people_record($person_id){
      return $this->d->delete($person_id,"drinks_to_people","person_id");      
    } 
    
    //Change only the drink field of the dtp record
    function select_drink($person_id,$drink_id){
      if (($person_id!=0) && ($drink_id>0) && ($r=$this->get_drinks_to_people_record($person_id))){
        return $this->save_drinks_to_people_record($person_id,$drink_id,$r["size"],$r["add_ons"]);
      }
      return false;      
    }
    
    /* coffee_orders */
    
    function add_coffee_order($church_service_id,$person_id){
      if (($church_service_id>0) && ($person_id!=0)){
        if (!$this->coffee_order_exists($church_service_id,$person_id)){
          //If no drinks_to_people record exists for this person, init to first drink/first size/no add-ons
          $dtp=$this->get_drinks_to_people_record($person_id);
          if ((is_array($dtp)) || ($this->save_drinks_to_people_record($person_id,$this->get_first_drink_id(),$this->get_first_size_id(),""))){
            $e=array();
            $e["church_service_id"]=$church_service_id;
            $e["person_id"]=$person_id;
            return $this->d->insert_and_get_id($e,"coffee_orders");                                          
          }
          return false; //dtp init failed
        }
        return true; //order pre-exists: success    
      }
      return false;
    }
    
    function delete_coffee_order($church_service_id,$person_id){
      $query="DELETE FROM coffee_orders WHERE church_service_id=$church_service_id AND person_id=$person_id;";
      return $this->d->q($query);
    }
    
    function coffee_order_exists($church_service_id,$person_id){
      $query="SELECT id FROM coffee_orders WHERE church_service_id=$church_service_id AND person_id=$person_id;";
      if ($res=$this->d->q($query)){
        return ($res->num_rows>0);
      }
      return false;      
    }
    
    //Return drinks_to_people records, order by drink so they can be grouped 
    function get_coffee_order($church_service_id){
      $query="
              SELECT
                drinks_to_people.*
              FROM 
                drinks_to_people
              LEFT JOIN
                coffee_orders ON coffee_orders.person_id=drinks_to_people.person_id                
              WHERE
                coffee_orders.church_service_id=$church_service_id
              ORDER BY
                drinks_to_people.drink,drinks_to_people.size,drinks_to_people.add_ons;
      ";    
      return $this->d->retrieve_all($query);            
    }   
    
    
    private function push_item(&$list,$drink,$size,$add_ons,&$qty,$people){
      $list[]=array("drink"=>$drink,"size"=>$size,"add_ons"=>$add_ons,"qty"=>$qty,"people"=>$people);
      $qty=1;      
    }
    
    //Return file-id.
    function get_coffee_order_pdf($church_service_id){
      /*
        Pseudo
          -get order records (drinks_to_people records)
          -group (same drink, same size, same add_ons)
          
      */    
            
      $t=$this->get_coffee_order($church_service_id);
      if ((is_array($t)) and (sizeof($t)>0)){        
        $total_no_of_drinks=sizeof($t);       
        $list=array();
        $curr_drink=$t[0]["drink"];
        $curr_size=$t[0]["size"];
        $curr_add_ons=$t[0]["add_ons"];
        $curr_people="";
        $curr_qty=0; //quantity counter
        foreach ($t as $v){
          if ($v["drink"]!=$curr_drink){
            //Different drink
            $this->push_item($list,$curr_drink,$curr_size,$curr_add_ons,$curr_qty,$curr_people);
            $curr_drink=$v["drink"];
            $curr_size=$v["size"];
            $curr_add_ons=$v["add_ons"];
            $curr_people=$v["person_id"];
          } else {
            //Same drink
            if ($v["size"]!=$curr_size){
              //Different size
              $this->push_item($list,$curr_drink,$curr_size,$curr_add_ons,$curr_qty,$curr_people);
              $curr_size=$v["size"];
              $curr_add_ons=$v["add_ons"];              
              $curr_people=$v["person_id"];
            } else {
              //Same size
              if ($v["add_ons"]!=$curr_add_ons){
                //Different add_ons
                $this->push_item($list,$curr_drink,$curr_size,$curr_add_ons,$curr_qty,$curr_people);
                $curr_add_ons=$v["add_ons"];                              
                $curr_people=$v["person_id"];
              } else {
                //Same add_ons
                //This is the exact same drink as the last, increase qty              
                $curr_qty++;  
                $curr_people=csl_append_element($curr_people,$v["person_id"]);          
              }            
            }          
          }
        }
        //don't forget the last item
        $this->push_item($list,$curr_drink,$curr_size,$curr_add_ons,$curr_qty,$curr_people);
        /*
          Now in $list we've got an array: drink (id) | size (id) | add_ons (csl) | quantity | people (csl of person ids)
          Translate to drink name | size name | add_ons description | qty
        */
        $fulltext_order=array();
        foreach ($list as $v){
          $drink_name=$this->get_drink_label($v["drink"]);
          $size_name=$this->get_drink_label($v["size"]);
          $add_ons_description="";
          if (!empty($v["add_ons"])){
            $add_ons=explode(",",$v["add_ons"]);
            foreach ($add_ons as $w){
              $x=explode(".",$w); //$x[0] has drink_id for addon, $x[1] qty
              if ($x[1]>0){
                $add_ons_description.=", ".$x[1]." ".$this->get_drink_label($x[0]);              
              }
            }
            //Take out first comma
            if (!empty($add_ons_description)){
              $add_ons_description=substr($add_ons_description,2);
            }
            if (!empty($add_ons_description)){
              $add_ons_description="with ".$add_ons_description;
            }
          } else {
            //$add_ons_description="(black)";          
          }
          $people_names="";
          if (!empty($v["people"])){
            $person_ids=explode(",",$v["people"]);
            $n=sizeof($person_ids);
            $m=1;
            foreach ($person_ids as $w){
              if (($m==$n) && ($n>1)){
                $people_names.=" and ";              
              } else {
                $people_names.=", ";
              }
              $people_names.=$this->a->personal_records->get_first_name($w);
              $m++;
            }
            $people_names=substr($people_names,2);
          }
          $fulltext_order_arr[]=array("drink"=>$drink_name,"size"=>$size_name,"add_ons"=>$add_ons_description,"qty"=>$v["qty"],"names"=>$people_names);
        } 

        $t="";
        foreach ($fulltext_order_arr as $v){
          $t.=$v["qty"]."x ".$v["size"]." ".$v["drink"]." ".$v["add_ons"]." for ".$v["names"]."<br>";
        }       
        /* pseudo: Generate pdf-file, add to database, return file_id*/  
        $service=new cw_Church_service($this->eh,$church_service_id);
        $filename="coffee_order_".$church_service_id;
        //if file exists, append random string
        $addendum="";
        while (file_exists(CW_ROOT_UNIX.CW_FILEBASE.CW_TMP_SUBFOLDER.$filename.$addendum.".pdf")){
          $addendum="_".create_sessionid(3);     
        }
        $local_name="Coffee order ".$service->get_info_string_for_filename().".pdf"; //name of downloaded file
        $filename=$filename.$addendum.".pdf";      
        if ($pdf=new cw_pdf('P','mm','Letter')){
          $pdf->set_auth($this->a); //necessary so cw_PFD can print the name of current user
          $pdf->AliasNbPages(); //nessesary for page numbers
          $pdf->AddPage();
                    
          $pdf->sety(7);
          $pdf->print_church_logo(30,4);
          empty($service->title) ? $title_string=$service->service_name : $title_string=$service->service_name.": ".$service->title;
          $pdf->print_header(utf8_decode("Coffee order"),40,-12,'R');
          $pdf->print_header($service->service_name.": ".$service->get_service_times_string(),40,-7,'R',10);
          $pdf->line(7,22,207,22);
          $pdf->sety(24);
    

          foreach ($fulltext_order_arr as $v){
            $line=$v["qty"]."x ".$v["size"]." ".$v["drink"]." ".$v["add_ons"];
            $pdf->SetFont('Arial','',12);
            $pdf->SetTextColor(0); //reset color
            $pdf->setX(10);
            $pdf->cell(0,20,utf8_decode($line));
            $pdf->setY($pdf->getY()+12);
            //$pdf->SetTextColor(0,0,255);
            $pdf->SetFont('Arial','',9);
            $pdf->SetTextColor(100); //gray
            $pdf->write(4,utf8_decode("for ".$v["names"]));
            //$pdf->setY($pdf->getY()+8);
          }       
                  
          $pdf->setY($pdf->getY()+10);
          $pdf->line(7,$pdf->getY(),207,$pdf->getY());
          $pdf->setY($pdf->getY()+8);
          
          $no_trays=ceil($total_no_of_drinks/4);
          ($total_no_of_drinks>1) ? $t_drink="drinks" : $t_drink="drink"; 
          ($no_trays>1) ? $t_trays="trays" : $t_trays="tray"; 
          
          $this_row=0;
          for ($i=0;$i<$no_trays;$i++){
            $this_row++;
            if ($i+1<$no_trays){
              $q=4;
            } else {
              $q=$total_no_of_drinks-$i*4;
            }
            if ($this_row>7){
              $pdf->setY($pdf->getY()+25);
              $this_row=0;              
            }
            $pdf->print_image("img/tray$q.gif",10+($i%7)*28);            
          }
                            
          $pdf->setY($pdf->getY()+15);
          $pdf->SetFont('Arial','',12);
          $pdf->SetTextColor(0); //reset color
          $pdf->setX(10);
          $pdf->cell(0,20,$total_no_of_drinks." $t_drink / $no_trays $t_trays total");
          
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
    
    //Obtain the persons drinks_to_people record and return select options with their preferred drink pre-selected
    function get_drink_choice_select_options($person_id){
      $t="";
      $dtp_rec=$this->get_drinks_to_people_record($person_id);
      $drink_recs=$this->get_drink_records();
      $selected="";
      foreach ($drink_recs as $drink_rec){
        if (is_array($dtp_rec)){
          //preference exists
          ($drink_rec["id"]==$dtp_rec["drink"]) ? $selected="selected=\"SELECTED\"" : $selected="";                  
        }
        $t.="<option $selected value=\"".$drink_rec["id"]."\">".$drink_rec["label"]."</option>";
      }
      return $t;
    }

    //Obtain the persons drinks_to_people record and return select options with their preferred drink size pre-selected
    function get_drink_size_select_options($person_id){
      $t="";
      $dtp_rec=$this->get_drinks_to_people_record($person_id);
      $size_recs=$this->get_drink_records(1);
      $selected="";
      foreach ($size_recs as $size_rec){
        if (is_array($dtp_rec)){
          //preference exists
          ($size_rec["id"]==$dtp_rec["size"]) ? $selected="selected=\"SELECTED\"" : $selected="";                  
        }
        $t.="<option $selected value=\"".$size_rec["id"]."\"> ".$size_rec["label"]."</option>";
      }
      return $t;
    }
    
    function get_addon_select_options($person_id,$addon_id){
      $t="";
      $dtp_rec=$this->get_drinks_to_people_record($person_id);
      $addon_rec=$this->get_drink_record($addon_id);
      
      //Get preselected qty
      $qty=0;
      if (is_array($dtp_rec)){
        $z=explode(",",$dtp_rec["add_ons"]);
        foreach ($z as $v){
          $y=explode(".",$v);
          if ($y[0]==$addon_id){
            $qty=$y[1];
            break;
          }
        }
      }
      
      $selected="";
      for ($i=0;$i<4;$i++){
        ($i==$qty) ? $selected="selected=\"SELECTED\"" : $selected="";
        ($i==0) ? $l="-" : $l=$i;                  
        $t.="<option $selected value=\"$i\">$l</option>";
      }
      return $t;      
    }


}

?>
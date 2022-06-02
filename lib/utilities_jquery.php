<?php
  /* Functions to generate code for input character restriction */ 

  //Return jQuery code to restrict input by regular expression
  function jq_regexp_filter_for_input($input,$regexp){
    return ("
          $('$input').bind('keypress', function (event) {
              var regex = new RegExp('$regexp');
              var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
              if (!regex.test(key)) {
                 event.preventDefault();
                 return false;
              }
          });    
    ");  
  }


  //Return jQuery code to restrict input $input to numbers only
  function jq_num_only($input){
    //From http://rosslynmarketing.com/2010/05/03/jquery-restrict-input-field-to-numerical-values-only/
    return ("
          $('$input').live('keypress', function(e) {
                return ( e.which!=8 && e.which!=0 && e.which!=46 && (e.which<48 || e.which>57)) ? false : true ;
          });
    ");
  }
  
  //Return jQuery code to disallow double quotes
  function jq_no_dbl_quote($input){
    return jq_regexp_filter_for_input($input,'^[^\"]');
  }

  //Return jQuery code to disallow double and single quotes  
  function jq_no_quote($input){
    return jq_regexp_filter_for_input($input,'^[^\"\\\']'); //The double escaping is really tricky
  }
  
?>
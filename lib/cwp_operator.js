
function get_next_slide(){
	/*
	 * Get element_id and slide_no for logically next slide (the one chronologically after the last service slide that has been on pgm)
	 */
	var result={};
	if ('latest_service_slide' in pgm){
		var last_pgm_element=pgm.latest_service_slide.service_element;
		var last_pgm_slide_no=pgm.latest_service_slide.slide_no;		
		var slides_in_target_pgm_element=slides['$'+last_pgm_element].slides.length;		
		if (last_pgm_slide_no<slides_in_target_pgm_element-1){
			//There is at least one more slide in this element - so just point to next slide of same element
			result.service_element=last_pgm_element;
			result.slide_no=last_pgm_slide_no+1;
		} else {
			//End of this element. Find next element with slides.
			var x=0;
			var found_next_element=false;
			var index=service_elements.indexOf(parseInt(last_pgm_element));
			while (index<service_elements.length){
				x=service_elements[index+1];
				if (('$'+x in slides) && ('slides' in slides['$'+x]) && (slides['$'+x].slides.length>0)){
					found_next_element=true;
					break;
				}
				index++;
			}
			if (found_next_element){
				//There is another element with slides: x
				result.service_element=x;
				result.slide_no=0;
			} else {
				//There is no next slide. Return pointer to current slide
				result.service_element=last_pgm_element;
				result.slide_no=last_pgm_slide_no;
			}
		}						
	} else {
		//No slide from the service has been shown yet. Get first element with slides and point to first slide.
		var x=0;
		var found_next_element=false;
		var index=0;
		while (index<service_elements.length){
			x=service_elements[index+1];
			if (('$'+x in slides) && ('slides' in slides['$'+x]) && (slides['$'+x].slides.length>0)){
				found_next_element=true;
				break;
			}
			index++;
		}
		if (found_next_element){
			//There is another element with slides: x
			result.service_element=x;
			result.slide_no=0;
		} else {
			//There is no next slide. Return pointer to pgm
			result.service_element=pgm.service_element;
			result.slide_no=pgm.slide_no;
		}
	}
	return result;
}

function get_previous_slide(){
	/*
	 * Get element_id and slide_no for logically previous slide (the one chronologically before the latest service slide that has been on pgm)
	 */
	var result={};
	if ('latest_service_slide' in pgm){
		var last_pgm_element=pgm.latest_service_slide.service_element;
		var last_pgm_slide_no=pgm.latest_service_slide.slide_no;		
		var slides_in_target_pgm_element=slides['$'+last_pgm_element].slides.length;		
		if (last_pgm_slide_no>0){
			//There is at least one previous slide in this element - so just point to prev slide of same element
			result.service_element=last_pgm_element;
			result.slide_no=last_pgm_slide_no-1;
		} else {
			//Beginning of this element. Find previous element with slides.
			var x=0;
			var found_prev_element=false;
			var index=service_elements.indexOf(parseInt(last_pgm_element));
			while (index>0){
				x=service_elements[index-1];
				if (('$'+x in slides) && ('slides' in slides['$'+x]) && (slides['$'+x].slides.length>0)){
					found_prev_element=true;
					break;
				}
				index--;
			}
			if (found_prev_element){
				//There is another element with slides: x
				result.service_element=x;				
				result.slide_no=slides['$'+x].slides.length-1;
			} else {
				//There is no previous slide. Return pointer to current slide
				result.service_element=last_pgm_element;
				result.slide_no=last_pgm_slide_no;
			}
		}						
	} else {
		//No slide from the service has been shown yet. Point to pgm.
		result.service_element=pgm.service_element;
		result.slide_no=pgm.slide_no;
	}
	return result;
}

//How many divs are in the container before the first actual slide? #of filethumbs+1 (the spacer)
function get_no_of_filethumb_divs(){
	var no_of_filethumbs=$('#current_service_element .filethumb').length;
	var result=0;
	if (no_of_filethumbs>0){
		result=no_of_filethumbs+1;
	}
	return result; 
}

function clear_markup_pgm(){
  	$('.actual_element').removeClass('mark_element_pgm');
  	$('.slidethumb').removeClass('mark_slidethumb_pgm');
	$('.nav_panel_item').removeClass('mark_navitem_pgm');
}

function markup_pgm(){
	clear_markup_pgm();
	if (pgm.service_element==0){
		$('.mark_navitem_pgm').removeClass('mark_navitem_pgm');
		$('.nav_panel_item:eq('+(pgm.slide_no)+')').addClass('mark_navitem_pgm');
	} else {
		if (current_service_element==pgm.service_element){
			$('#current_service_element .mark_slidethumb_pgm').removeClass('mark_slidethumb_pgm');
			$('#current_service_element .slidethumb:eq('+pgm.slide_no+')').addClass('mark_slidethumb_pgm');
		}
		$('#f'+pgm.service_element).addClass('mark_element_pgm');
	}	
}


function clear_markup_pre(){
  	$('.actual_element').removeClass('mark_element_pre');
  	$('.slidethumb').removeClass('mark_slidethumb_pre');
	$('.nav_panel_item').removeClass('mark_navitem_pre');
}

function markup_pre(noscroll){
	clear_markup_pre();
	if (pre.service_element==0){
		$('.mark_navitem_pre').removeClass('mark_navitem_pre');
		$('.nav_panel_item:eq('+(pre.slide_no)+')').addClass('mark_navitem_pre');
	} else {
		if (current_service_element==pre.service_element){
			$('#current_service_element .mark_slidethumb_pre').removeClass('mark_slidethumb_pre');
			$('#current_service_element .slidethumb:eq('+pre.slide_no+')').addClass('mark_slidethumb_pre');
			//Scroll
			if (typeof noscroll==='undefined'){
				var pos=$('#current_service_element .mark_slidethumb_pre').position();
				var abstop=pos.top+$('#current_service_element').scrollTop(); //top of current element relative to top of container (not scroll-top)
				var height=$('#current_service_element .mark_slidethumb_pre').height();
				var visible_height=$('#current_service_element').height(); //visible height of the service order
				$('#current_service_element').scrollTop(abstop+height+(visible_height*0.5)-visible_height);								
			}
		}
		//Service order (actual_element id starts with 'f', see $eh->get_service_order_for_ul
		$('#f'+pre.service_element).addClass('mark_element_pre');
	}	
}

function markup_current(noscroll){
  	$('.actual_element').removeClass('mark_element_current');
	$('#f'+current_service_element).addClass('mark_element_current');
	//Scroll - keep current element in center
	if ((typeof noscroll==='undefined') && (current_service_element>0)){
		var pos=$('#f'+current_service_element).position();
		var abstop=pos.top+$('#service_order_container').scrollTop(); //top of current element relative to top of service order (not scroll-top)
		var height=$('#f'+current_service_element).height();
		var visible_height=$('#service_order_container').height(); //visible height of the service order
		$('#service_order_container').scrollTop(abstop+height+(visible_height*0.5)-visible_height);			
	}
}

function markup_all(noscroll){
	markup_current(noscroll);
	markup_pre(noscroll);
	markup_pgm();
}

function flush_pgm(){
	send_slide(slides['$'+pgm.service_element].slides[pgm.slide_no]);
	markup_pgm();
	if (pgm.service_element>0){
		//Remember the last slide from the actual show (used by get_next_slide())
		pgm.latest_service_slide={};
		pgm.latest_service_slide.service_element=pgm.service_element;
		pgm.latest_service_slide.slide_no=pgm.slide_no;
	}
}

function slide_to_pre(service_element,slide_no){
	pre.service_element=service_element;
	pre.slide_no=slide_no;
	flush_pre();
	//This condition makes sure that current_service_element does not advance to pre.service_element if there's attached files on pgm
	if ((pre.service_element>0)&&((slides['$'+pgm.service_element].files.length==0) || (pgm.service_element!=current_service_element))){
		show_slide_thumbs(pre.service_element);
	}
}


function slide_to_pgm(service_element,slide_no){
	pgm.service_element=service_element;
	pgm.slide_no=slide_no;
	flush_pgm();
	var next=get_next_slide();
	slide_to_pre(next.service_element,next.slide_no);
}

function flush_pre(){
	send_slide(slides['$'+pre.service_element].slides[pre.slide_no],'preview');
	markup_pre();
}

function broadcast(){
	flush_pgm();
	flush_pre();
}

function immediate(service_element,slide_no){
	pre.service_element=pgm.service_element;
	pre.slide_no=pgm.slide_no;
	pgm.service_element=service_element;
	pgm.slide_no=slide_no;
	broadcast();
}

function nav_panel_click(shift,slide_no){
	if (ui_ready){
		if (shift){						
			immediate(0,slide_no);
		} else {
			slide_to_pre(0,slide_no);
		}			
	}
}

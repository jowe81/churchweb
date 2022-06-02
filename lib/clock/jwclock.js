

var CLOCK={};

CLOCK.init=function($canvas,directory_context){
	var r=$.Deferred();
	this.handler=0;
	this.$canvas=$canvas;
	this.canvas=$canvas[0];
	var max=Math.min($(window).height(),$(window).width())*.9;
	this.$canvas.width(max).height(max).attr('height',max).attr('width',max).css('left',$(window).width()/2-max/2).css('top',$(window).height()/2-max/2);
	this.context=this.canvas.getContext('2d');
	this.pixel_factor=$canvas.width()/1024;
	this.padding=35*this.pixel_factor,
	this.x=this.canvas.width / 22, 	
	this.Hx=this.canvas.width / 10,
	this.space = 20*this.pixel_factor,
	this.r = this.canvas.width / 2 - this.padding,
	this.Hr = this.r + this.space;
	//load images
	if (typeof this.bgimage==='undefined'){
		this.bgimage=new Image();
		this.bgimage.src = directory_context+'clockbg.gif';		
	}
	if (typeof this.clocklogo==='undefined'){
		this.clocklogo=new Image();
		this.clocklogo.src = directory_context+'clocklogo.gif';
	}
	if (typeof this.clockfillbg==='undefined'){	
		this.clockfillbg=new Image();
		this.clockfillbg.src = directory_context+'clockfillbg.gif';
		this.clockfillbg.onload=function(){r.resolve();};
	} else {
		r.resolve();		
	}
	return r;
};

CLOCK.drawhrmark=function(loc){
	var
		handRadius_outer=480*this.pixel_factor,
		handRadius_inner=450*this.pixel_factor,
		angle = (Math.PI * 2) * (loc / 3600) - Math.PI / 2,
		line_endpoint_x=this.canvas.width / 2 + Math.cos(angle) * handRadius_outer,
		line_endpoint_y=this.canvas.height / 2 + Math.sin(angle) * handRadius_outer,
		line_startpoint_x=this.canvas.width / 2 + Math.cos(angle) * handRadius_inner,
		line_startpoint_y=this.canvas.height / 2 + Math.sin(angle) * handRadius_inner;
	
    this.context.beginPath();
    this.context.lineWidth=15*this.pixel_factor;
    this.context.moveTo(line_startpoint_x, line_startpoint_y);
    this.context.lineTo(line_endpoint_x, line_endpoint_y);
    this.context.strokeStyle="#000000";
    this.context.stroke();
};

CLOCK.drawclockface=function(){
	//White filled circle full radius 
	this.context.fillStyle="rgba(255,255,255,0.9)";
	this.context.beginPath();
	this.context.arc(this.canvas.width / 2, this.canvas.height / 2, this.r+20*this.pixel_factor, 0, Math.PI * 2, true);   
	this.context.fill();
	
	this.context.fillStyle="rgba(0,0,0,.4)";
	this.context.beginPath();
	this.context.arc(this.canvas.width / 2, this.canvas.height / 2, this.r, 0, Math.PI * 2, true);   
	this.context.fill();
    
	
    var
    	bg_size=155,
    	bg_aspect=this.bgimage.width/this.bgimage.height,
    	bg_display_width=bg_size*this.pixel_factor*bg_aspect,
    	bg_display_height=bg_size*this.pixel_factor,
    	bgfsize=this.canvas.width,
    	bgfscale=36.5*this.pixel_factor;

    //Clockface background
    this.context.globalAlpha = 0.5;
    this.context.drawImage(this.clockfillbg, bgfscale, bgfscale, bgfsize-2*bgfscale,bgfsize-2*bgfscale );
	//Logo on clockface
    this.context.shadowColor = '#000';
    this.context.shadowBlur = 20*this.pixel_factor;
    this.context.shadowOffsetX = 15*this.pixel_factor;
    this.context.shadowOffsetY = 15*this.pixel_factor;
	
    this.context.globalAlpha = 0.9;
    this.context.drawImage(this.bgimage, 170*this.pixel_factor, 550*this.pixel_factor, bg_display_width, bg_display_height);	
    this.context.globalAlpha = 1;
    this.context.fillStyle="#000000";	
	//Less shadow on the outer features
    this.context.shadowOffsetX = 5*this.pixel_factor;
    this.context.shadowOffsetY = 5*this.pixel_factor;
	//Black circle
    this.context.lineWidth=7*this.pixel_factor;
    this.context.beginPath();
    this.context.arc(this.canvas.width / 2, this.canvas.height / 2, this.r, 0, Math.PI * 2, true);
    this.context.strokeStyle="#000000";
    this.context.stroke();
    //White outer circle
    this.context.lineWidth=1*this.pixel_factor;
    this.context.beginPath();
    this.context.arc(this.canvas.width / 2, this.canvas.height / 2, this.r+5*this.pixel_factor, 0, Math.PI * 2, true);   
    this.context.strokeStyle="rgba(255,255,255,1)";
    //context.stroke();
    //White inner circle
    this.context.beginPath();
    this.context.arc(this.canvas.width / 2, this.canvas.height / 2, this.r-6*this.pixel_factor, 0, Math.PI * 2, true);   
    //context.stroke();
    this.context.strokeStyle="rgba(0,0,0,1)";
    //Black markers
    for (var i=1;i<13;i++){
    	this.drawhrmark(i*300);    	
    }
};

CLOCK.drawcenter=function(){
	this.context.beginPath();
	this.context.arc(this.canvas.width / 2, this.canvas.height / 2, 20*this.pixel_factor, 0, Math.PI * 2, true);
	this.context.fillStyle="rgba(0,0,0,1)";
	this.context.strokeStyle="white";
	this.context.lineWidth=2*this.pixel_factor;
	this.context.fill();
	this.context.stroke();
};

CLOCK.drawhand=function(loc,handRadius,color,width,logo){
    var
    	cl_size=155,
    	cl_aspect=this.clocklogo.width/this.clocklogo.height,
    	cl_display_width=cl_size*this.pixel_factor*cl_aspect,
    	cl_display_height=cl_size*this.pixel_factor;
    
    this.context.shadowColor = '#000';
    this.context.shadowBlur = 20*this.pixel_factor;
    this.context.shadowOffsetX = 15*this.pixel_factor;
    this.context.shadowOffsetY = 15*this.pixel_factor;    
    if (logo){
    	//Seconds
    	this.context.save();
    	this.context.translate(this.canvas.width / 2, this.canvas.height / 2);
    	this.context.rotate((2*Math.PI/3600)*loc-(Math.PI/2));
    	this.context.drawImage(this.clocklogo, cl_display_width/10*7,-cl_display_height/2 , cl_display_width, cl_display_height);	        
    	this.context.restore();        	
    } else {
    	//Minutes and hours
    	this.context.fillStyle=color;
    	this.context.strokeStyle="white";
    	this.context.save();
    	this.context.translate(this.canvas.width/2,this.canvas.height/2);
    	this.context.rotate((2*Math.PI/3600)*(loc-900));
    	this.context.beginPath();
    	this.context.rect(0,-(width*this.pixel_factor/2), handRadius,width*this.pixel_factor);        
    	this.context.fill();
    	this.context.lineWidth=5*this.pixel_factor;
    	this.context.stroke();
    	this.context.restore();    	
    }
    this.context.shadowColor = 'rgba(0,0,0,0)';
};

CLOCK.drawhands=function(date) {
    hour = date.getHours();
    hour = hour > 12 ? hour - 12 : hour;    
    this.drawhand(hour*300+date.getMinutes()*5,this.r-this.x-this.Hx,"rgba(0,0,0,.7)",15,false);
    this.drawhand(date.getMinutes()*60+date.getSeconds(),this.r-this.x,"rgba(0,0,0,.7)",12,false);
    this.drawhand(date.getSeconds()*60, this.r-this.x-60,null,7,true);
};

CLOCK.createclock=function(){
    var date = new Date;
    this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.drawclockface();
    this.drawhands(date);
    this.drawcenter();
};

CLOCK.stopClock=function(){
	if (this.handler>0){
		var _this=this;
		_this.$canvas.animate({ opacity:0 },2000);
		$(window).unbind('resize.clock');
		setTimeout(function(){
			clearInterval(_this.handler);
			_this.handler=0;
			
		},2000);		
	}
};

CLOCK.startClock=function($canvas,directory_context){
	if (!(this.handler>0)){
		//Clock not yet running
		var _this=this;
		$(window).bind('resize.clock',function(){_this.init($canvas,directory_context);});
		this.init($canvas,directory_context).done(function(){
			_this.handler = setInterval(function(){
				CLOCK.createclock();
			}, 1000);			
			_this.$canvas.animate({ opacity:1 },2000);
		});		
	}
};




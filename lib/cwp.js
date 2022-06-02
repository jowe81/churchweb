//Assoc array utility functions
//Return index of the first property in a that has the value val
function ar_indexOf(a,val){
	for (var e in a){
		if (a[e]==val){
			return e;
		}
	}
	return undefined;
}

//Return array of all indices of properties in a that have the value val
function ar_indicesOf(a,val){
	var res=[];
	for (var e in a){
		if (a[e]==val){
			res.push(e);
		}
	}
	return res;	
}

//Return # of properties in a
function ar_length(a){
	var res=0;
	for (var e in a){
		res++;
	}
	return res;
}

function ar_keys_to_csl(a){
	var res="";
	if (ar_length(a)>0){
		for (var k in a){
			res=res+','+k;
		}		
		res=res.substr(1);
	}
	return res;
}


//var net=require("net");
//var server=net.createServer();

var io=require('socket.io').listen(4001);
io.set('log level',1); //reduce logging
var util=require("util");


var sockets={}; //actual socket objects
var socket_count=-1; //Increase with every new connection, for unique socket ids

var feeds={}; //feed-id > feed object
var operators={}; //connected operators: operator socket_index->feed_index
var displays={};  //connected displays: display socket_index->feed_index

var cached_data={};

var term="\n";
var ok="+OK ";
var err="-ERR ";

function get_command(t){
	var exp=/[^\s"']+|"([^"\\]|\\.)*"|'([^']*)'/g; //http://stackoverflow.com/questions/366202/regex-for-splitting-a-string-using-space-when-not-surrounded-by-single-or-double
	var results=t.match(exp);
	var final_results=[];
	if (Object.prototype.toString.call(results)==='[object Array]'){
		var element="",prev_element="";
		var i=0,skip_next=true;
		for (i=0;i<results.length;i++){
			prev_element=element;
			element=results[i];
		    if (!skip_next){
				if ((prev_element.substr(-1,1)=="=") && (element.substr(0,1)=="\"")){
					//Add this and previous element together
					final_results.push(prev_element+element);
					skip_next=true;
				} else {
					//Add previous element only
					final_results.push(prev_element);
				}	    	
		    } else {
		    	skip_next=false;
		    }
		}
		final_results.push(element);
		final_results[0]=final_results[0].toUpperCase(); //Allow any case for the command itself		
	} else {
		//No array given
		final_results=[];
	}
	return final_results;
}

//Expects argument_name=value, returns value (strips quotes if present)
function arg_val(t){
	var v="";
	v=t.substr(t.indexOf('=')+1);
	if (v.match(/^'|^"/)){
		v=v.substring(1, v.length-1);
	}
	return v;
}

function arg_name(t){
	return t.substr(0,t.indexOf('='));
}

function array_to_csl(a){
	var r="";
	if (a.length>0){
		r=a[0];
		for(var i=1;i<a.length;i++){
			r=r+","+a[i];
		}		
	}
	return r;
}

function real_length(a){
	var res=0;
	for (var i=0;i<a.length;i++){
		if (undefined!=a[i]){
			res++;
		}
		//console.log("socket pos "+i+": "+util.inspect(a[i]));
	}
	return res;
}

function get_feeds_without_operator(feeds,operators){
	var res={};
	for (var feed in feeds){
		if (ar_indexOf(operators,feed)==undefined){
			res[feed]=feeds[feed];
		}
	}
	return res;
}

function get_number_of_viewers(feed_id){
	return ar_indicesOf(displays,feed_id).length;
}

function send(socket,data){
	//console.log('sending: '+JSON.stringify(data));
	socket.emit('data',JSON.stringify(data));
}

//Broadcast data to every display except the one with id socket_id 
function broadcast(socket_id,data,subfeed){
	for (socket_index in displays){
		//socket_index is socket id of display, displays[socket_index] is a feed id
		//operators[socket_id] is the feed_id that we are connected to
		if ((operators[socket_id]==displays[socket_index]["feed_id"]) && (subfeed==displays[socket_index]["subfeed"])){
			//Found display
			send(sockets[socket_index],data);
		}
	}	
}


function disconnect_operator_from_feed(socket_id){
	if (socket_id in operators){
		//Operator exists
		if (operators[socket_id] in feeds){
			//Operator is connected to feed
			delete(feeds[operators[socket_id]]["operator"]);		
			delete(feeds[operators[socket_id]]["operator_person_id"]);
			feeds[operators[socket_id]]["feed_status"]="orphaned";
		}
		delete(operators[socket_id]);	
	}	
}

io.sockets.on('connection', function(socket){
	socket_count++;

	//Variables local to the client
	var socket_mode="";  
	var socket_person_id=""; //used for person_id in ChurchWeb
	var socket_id=socket_count.toString();
	var cmd="";
	
	socket["socket_id"]=socket_id;
	sockets[socket_id]=socket;
	console.log("New connection, id: "+socket_id);
	
	socket.on('data',function(data){
		var rsp=""; //Response to be sent after processing
		var rsp2=""; //Optional second response (used by connect_to_feed, to send cached data after confirming the connection
		data=data.toString().trim();
		cmd=get_command(data);

		var rcv={};
		try {
			rcv=JSON.parse(data);
		}
		catch (err) {
			rsp={"code":9999,"error":"invalid command object"};
			console.log("invalid JSON: "+data);
		}
		
		if (rcv){
			//console.log('data: ',util.inspect(cmd));
			if (socket_mode=="operator"){
				if (rcv["cmd"]=="init_feed"){
					if ("id" in rcv){
						if (rcv["id"]in feeds){
							rsp={"code":4100,"error":"feed exists already"};							
						} else {
							if (!("aspect" in rcv)){
								rcv["aspect"]="4:3";
							}
							var new_feed={
									"id":rcv["id"], 
									"title":rcv["title"], 
									"aspect":rcv["aspect"], 
									"owner_person_id":socket_person_id, 
									"feed_status":"orphaned",
									"viewers":0
							};
							
							feeds[rcv["id"]]=new_feed;
							rsp={"code":2100,"feed":JSON.stringify(feeds[rcv["id"]])};							
						}
					} else {
						rsp={"code":4100,"error":"feed id missing or invalid"};						
					}									
				} else if (rcv["cmd"]=="get_feed"){
					//return details of feed with id
					if ("id" in rcv){
						if (rcv["id"]in feeds){
							rsp={"code":2101,"feed":JSON.stringify(feeds[rcv["id"]])};							
						} else {
							rsp={"code":4101,"error":"feed does not exist"};							
						}
					} else {
						rsp={"code":4101,"error":"feed id missing or invalid"};						
					}														
				} else if (rcv["cmd"]=="connect_to_feed"){
					if ("id" in rcv){
						if (rcv["id"]in feeds){
							if (!(ar_indexOf(operators,rcv["id"])==undefined)){
								//Feed has an operator already
								rsp={"code":4120,"error":"feed is busy"};
							} else {
								//Disconnect this operator from a different feed he might be connected to
								if (operators[socket_id]){
									delete(feeds[operators[socket_id]]["operator"]);
									delete(feeds[operators[socket_id]]["operator_person_id"]);
									delete(operators[socket_id]);								
								}
								//Connect to desired feed
								operators[socket_id]=rcv["id"];
								feeds[rcv["id"]]["operator"]=socket_id;
								feeds[rcv["id"]]["operator_person_id"]=socket_person_id;
								feeds[rcv["id"]]["feed_status"]="live";
								rsp={"code":2120,"feed":JSON.stringify(feeds[rcv["id"]])};		
							}							
						} else {
							rsp={"code":4120,"error":"feed does not exist"};							
						}
					} else {
						rsp={"code":4120,"error":"parameter missing: provide feed id"};						
					}
				} else if (rcv["cmd"]=="get_viewer_count"){
					if (operators[socket_id]){
						rsp={"code":2160,"viewer_count":get_number_of_viewers(operators[socket_id])};
					} else {
						rsp={"code":4150,"error":"not connected to a feed"};
					}
				} else if (rcv["cmd"]=="get_orphaned_feeds"){
					rsp={"code":2110,"feeds":JSON.stringify(get_feeds_without_operator(feeds,operators))};
				} else if (rcv["cmd"]=="data"){
					if (operators[socket_id]){
						if ("data" in rcv){
							var subfeed="program";
							if ("subfeed" in rcv["data"]){
								subfeed=rcv["data"]["subfeed"];								
							}
							var bcast={"code":3000,"data":rcv["data"]};
							broadcast(socket_id,bcast,subfeed);
							rsp={"code":2150,"viewer_count":get_number_of_viewers(operators[socket_id])};
							//Check into data, and if it's a display_slide command, cache accordingly
							if (("cmd" in rcv["data"]) && (rcv["data"]["cmd"]=="display_slide")){
								if (!(operators[socket_id] in cached_data)){
									cached_data[operators[socket_id]]={};
								}
								cached_data[operators[socket_id]][subfeed]=rcv["data"]; //Cache this slide for this subfeed
							}							
						} else {
							rsp={"code":4150,"error":"no data" };							
						}
					} else {
						rsp={"code":4150,"error":"not connected to a feed" };
					}
				} else if (rcv["cmd"]=="disconnect_from_feed"){
					disconnect_operator_from_feed(socket_id);
					rsp={"code":2130};
				} else if (rcv["cmd"]=="terminate_feed"){
					console.log('terminating '+socket_id);
					if (socket_id in operators){
						//Notify connected displays of feed termination
						var bcast={"code":3999,"error":"feed terminated"};
						broadcast(socket_id,bcast,"program");
						//remove feed and disconnect from it
						var feed_id=operators[socket_id];
						disconnect_operator_from_feed(socket_id);
						delete feeds[feed_id];
						rsp={"code":2140};
					} else {
						rsp={"code":5140,"error":"not connected to a feed"};
					}
				} else if (rcv["cmd"]=="quit"){
					socket.end();
				} else {
					rsp={"code":4499,"error":"unknown command: "+rcv["cmd"]};
				}					
			} else if (socket_mode=="display") {
				if (rcv["cmd"]=="get_feeds"){
					rsp={"code":2500,"feeds":JSON.stringify(feeds) };
				} else if (rcv["cmd"]=="connect_to_feed"){
					if ("id" in rcv){
						if (rcv["id"] in feeds){
							//Feed exists
							var subfeed;
							if (!("subfeed" in rcv)){
								subfeed="program";
							} else {
								subfeed=rcv["subfeed"];
							}
							displays[socket_id]={ "feed_id":rcv["id"], "subfeed":subfeed };
							feeds[rcv["id"]]["viewers"]++;
							rsp={"code":2510,"feed":feeds[rcv["id"]]};
							if (rcv["id"] in cached_data){
								//console.log('sending cached data');
								if (subfeed=="program"){
									rsp2={"code":3000,"data":cached_data[rcv["id"]][subfeed]};																	
								}
							}
						} else {
							rsp={"code":4510,"error":"feed ["+rcv["id"]+"] does not exist"};
						}						
					} else {
						rsp={"code":4510,"error":"parameter missing: provide feed id"};
					}
				} else if (rcv["cmd"]=="disconnect_from_feed"){
					feeds[displays[socket_id]["feed_id"]]["viewers"]--;
					delete(displays[socket_id]);
					rsp={"code":2520};
				} else if (rcv["cmd"]=="quit"){
					socket.end();
				} else {
					rsp={"code":4999,"error":"unknown command"};
				}					
			} else {
				//Mode not set
				if (rcv["cmd"]=="set_mode"){
					if (rcv["mode"]=="operator"){
						socket_mode="operator";
						rsp={"code":2010,"mode":socket_mode };						
					} else if (rcv["mode"]=="display"){
						socket_mode="display";
						rsp={"code":2010,"mode":socket_mode };						
					} else {
						rsp={"code":4010,"error":"invalid mode or parameter missing"};						
					}
					//Set person id in any case, if provided
					if ("person_id" in rcv){
						socket_person_id=rcv["person_id"];
					}
				} else if (rcv["cmd"]=="get_mode"){
					rsp={"code":4998,"error":"no mode selected - use set_mode"};
				} else if (cmd[0]=="QUIT"){
					socket.end();
				} else {
					rsp={"code":1999,"error":"unknown command (no mode set)"};
				}			
			}			
		} else {
			rsp={"code":9999,"error":"invalid command object"};
		}
		send(socket,rsp);
		if (rsp2!=""){
			send(socket,rsp2);
			rsp2="";
		}
	});
	
	socket.on('disconnect',function(){
		if (socket_mode=="display"){
			if (socket_id in displays){
				console.log('removing dead display: '+socket_id);
				if (displays[socket_id]["feed_id"] in feeds){
					//Feed still exists
					feeds[displays[socket_id]["feed_id"]]["viewers"]--;					
				}
				delete displays[socket_id];				
			}
		} else if (socket_mode=="operator"){
			disconnect_operator_from_feed(socket_id);			
		}
		delete sockets[socket_id];
		console.log("Connection "+socket_id+" closed. "+ar_length(sockets)+" connections remaining");
	});
});



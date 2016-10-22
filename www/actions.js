/*!
* LinnDS-jukebox
*
* Copyright (c) 2011-2015 Henrik Tolbøl, http://tolbøl.dk
*
* Licensed under the MIT license:
* http://www.opensource.org/licenses/mit-license.php
*/

$(function() {

    // These variables contains information regarding the actual navigation
    // through pages.
    var SubMenuData = "";
    var AlbumListData = "";
    var AlbumListFirst = true;
    var AlbumFirst = true;
    var PlayData = "";
    var Queue = new Object();
    Queue.Sequence = [];
    Queue.RevNo = -1;
    Queue.CurLinnId = -1;
    Queue.State = "";
    Queue.ScrollTop = -1;

    var conn = new WebSocket('ws://diskstation.local:9052');

    conn.onopen = function(e) {
	console.log("Connection established!");

	var sendthis = new Object();
	sendthis.Message = 'HTML Body';
	sendthis.Context = new Object();
	sendthis.Context.query = 'HTML Body';

	conn.send(JSON.stringify(sendthis));
    };

    conn.onclose = function(e) {
	console.log("Connection closed!" . e);

	alert("Connection closed! " + e + "\n Please reload the page");
    };

    conn.onerror = function(e) {
	console.log("Connection error!");

	alert("Connection error! " + e + "\n Please reload the page");
    };

    conn.onmessage = function(e) {
	try
	{
	    var data = JSON.parse(e.data);
	    console.log(data);
	    console.log(data.Message);
	    console.log(data.Context);
	    console.log(data.Result);

	    if (data.Message == 'State'){

		var Vol = data.Result.Volume;
		$('span.ShowVolume').each(function() {
		    $(this).html(Vol);
		});
		QueueMarkings(data.Result.RevNo, data.Result.Id);
		    
	    }
	    else if (data.Context.query == 'Query AlphabetPresent') {
		$("#alphabet-title").html(data.Context.title);
		$.each( data.Result, function ( i, val ) {
		    if (val == 1)
			$("#alphabet-"  + i).removeClass("ui-disabled");
		    else
			$("#alphabet-"  + i).addClass("ui-disabled");
		});
		$("body").pagecontainer("change", "#alphabet");
	    }
	    else if (data.Context.query == 'Query AlbumList') {
		$("#albumlist-title").html(data.Context.title);
		var html = "";
		$.each(data.Result, function ( i, val ) {
		    html += AlbumListEntry("albumlist", val.Preset, val);
		});
		$("#albumlist-list").html( html );
		if (! AlbumListFirst) {
		    $("#albumlist-list").listview( "refresh" );
		}
		$("body").pagecontainer("change", "#albumlist");
		AlbumListFirst = false;
	    }
	    else if (data.Context.query == 'Query Album') {
		var html = "";
		var first = true;
		$.each( data.Result, function ( i, val ) {
		    if (first) {
			$("img.album").attr("src", val.AlbumArt);
			$("#album-title").html("Album");
			$("#album-artist").html(val.ArtistAlbumArtist);
			$("#album-album").html(val.Album + " (" + val.Year + ")");

			first = false;
		    }
		    html += AlbumEntry("album", val.Preset, val.TrackSeq, val);
		});
		$("#album-list").html( html );
		if (! AlbumFirst) {
		    $("#album-list").listview( "refresh" );
		}
		$("body").pagecontainer("change", "#album");
		AlbumFirst = false;
	    }
	    else if (data.Context.query == 'Query Search') {
		var html = "";

		$.each(data.Result, function ( i, val ) {
		    if (val.Type == "Album") {
			html += SearchAlbumEntry(data.Context.id, val.Preset, data.Context.filtertext, val);
		    }
		    else
		    {
			html += SearchTrackEntry(data.Context.id, val.Preset, val.TrackSeq, data.Context.filtertext, val);
		    }
                });
                $(data.Context.ul).html( html );
                $(data.Context.ul).listview( "refresh" );
//                data.Context.$ul.trigger( "updatelayout");
	    }
	    else if (data.Context.query == 'Query PlayingNow') {
		var html = "";
		$.each( data.Result, function ( i, val ) {
		    if (i == 0) {
			Queue.State = val;
		    } else {
			html += QueueTrackEntry("queue", val);
		    }
		});
		if (html != "")
		{
		    $("#queue-list").html( html );
		    //$("#queue-list").listview( "refresh" );
		    
		    Queue.CurLinnId = -1; // Force mariking we have new html
		}

		QueueMarkings(Queue.State.RevNo, Queue.State.LinnId);
	    }
	    else if (data.Context.query == 'HTML Body') {
		var bd = $('body');
		bd.html(data.Result);
		bd.pagecontainer("change", "#musik");

		// update html with state information
		var sendthis = new Object();
		sendthis.Message = 'State';
		sendthis.Context = new Object();
		sendthis.Context.action = 'State';

		conn.send(JSON.stringify(sendthis));

	    }
	}
	catch(ee)
	{
	    console.log(ee.data);
		//sendthis = new Object();
		//sendthis.Message = 'Query AlphabetPresent "' + menu + '"';;
		//sendthis.Context = new Object();
		//sendthis.Context.query = 'Query AlphabetPresent';
		//sendthis.Context.menu = menu;
		//sendthis.Context.type = type;
		//sendthis.Context.title = title;
	}
    };

    function QueueMarkings(RevNo, LinnId) {
	var this_li = $("#queue-"+LinnId);

	if (Queue.CurLinnId != LinnId) {
	    var t;
	    t = this_li.prevAll().attr("data-icon", "check").children();
	    t.filter("a.showalbumclick").removeClass("ui-icon-audio").removeClass("ui-icon-carat-r").addClass("ui-icon-check");
	    t.filter("a.queuepopup").addClass("ui-disabled");

	    t = this_li.attr("data-icon", "audio").children();
	    t.filter("a.showalbumclick").removeClass("ui-icon-check").removeClass("ui-icon-carat-r").addClass("ui-icon-audio");
	    t.filter("a.queuepopup").removeClass("ui-disabled");

	    t = this_li.nextAll().attr("data-icon", "carat-r").children();
	    t.filter("a.showalbumclick").removeClass("ui-icon-audio").removeClass("ui-icon-check").addClass("ui-icon-carat-r");
	    t.filter("a.queuepopup").removeClass("ui-disabled");
	}
	Queue.RevNo = RevNo;
	Queue.CurLinnId = LinnId;

	$("#queue-list").listview( "refresh" );

        var pageId = $('body').pagecontainer('getActivePage').prop('id'); 
	if (pageId == "queue") {
	    this_li = $("#queue-"+Queue.CurLinnId);
	    var Pos = this_li.offset();
	    if (Pos !== undefined && Pos.top > 100)
	    {
		$.mobile.silentScroll(Pos.top - 100);
		Queue.ScrollTop = Pos.top;
	    }
	    else if (Queue.ScrollTop != -1)
	    {
		$.mobile.silentScroll(Queue.ScrollTop - 100);
	    }
	}
    }

    // This one is called when clicking to open a playpopup.
    $('body').delegate("a.playpopup", "click", function() {
	var id = $(this).attr('id');
	var preset = $(this).data("musik").preset;
	var track  = $(this).data("musik").track;
	if (track === undefined) track = 0;
	var popupid = $(this).data("musik").popupid;
	PlayData = new Object();
	PlayData.preset = preset;
	PlayData.track = track;
	PlayData.popupid = popupid;
	console.log("a.playpopup: " + id + ", " + preset + ", " + track + ", " + popupid);
	$("#" + popupid).popup('open', {positionTo: "#" + id } );
	return true;
    });

    // This one is called when an entry in the playpopup is clicked
    $('body').delegate("a.playpopupclick", "click", function() {
	var volume = $(this).data("musik").volume;
	var action = $(this).data("musik").action;
	console.log("a.playpopupclick: " + action + " = " + PlayData.preset + ", " + PlayData.track + ", " + volume);
	if (action != "Cancel") {
	    var sendthis = new Object();
	    sendthis.Message = 'Jukebox ' + action + ' "' + PlayData.preset + '" "' + PlayData.track + '"';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Jukebox';
	    sendthis.Context.action = action;
	    sendthis.Context.preset = PlayData.preset;
	    sendthis.Context.track = PlayData.track;

	    conn.send(JSON.stringify(sendthis));
	}
	$("#" + PlayData.popupid).popup('close');
	return true;
    });

    // This one is called when clicking to open a queuepopup.
    $('body').delegate("a.queuepopup", "click", function() {
	var id = $(this).attr('id');
	Queue.popup =  $(this).data("musik");
	console.log("a.queuepopup: " + id + ", " + Queue.popup.preset + ", " + Queue.popup.track + ", " + Queue.popup.popupid);
	$("#" + Queue.popup.popupid).popup('open', {positionTo: "#" + id } );
	return true;
    });

    // This one is called when an entry in the queuepopup is clicked
    $('body').delegate("a.queuepopupclick", "click", function() {
	var volume = $(this).data("musik").volume;
	var action = $(this).data("musik").action;
	console.log("a.queuepopupclick: " + action + " = " + Queue.popup.preset + ", " + Queue.popup.track + ", " + volume);
	if (action != "Cancel") {
	    if (action.indexOf('Queue-') >= 0) {
		var sendthis = new Object();
		sendthis.Message = action;
		sendthis.Context = new Object();
		sendthis.Context.query = 'Queue';
		sendthis.Context.action = action;
		sendthis.Context.preset = Queue.popup.preset;
		sendthis.Context.track = Queue.popup.track;
		sendthis.Context.LinnId = Queue.popup.LinnId;

		conn.send(JSON.stringify(sendthis));
	    }
	}
	$("#" + Queue.popup.popupid).popup('close');
	return true;
    });

    // Click a button in single album list
    $('body').delegate("button.albumclick", "click", function() {
	var action = $(this).data("musik").action;
	var preset = PlayData.preset;
	var track  = 0;
	console.log("button.albumclick: " + action + " = " + preset + ", " + track);
	if (action != "Cancel") {
	    var sendthis = new Object();
	    sendthis.Message = 'Jukebox ' + action + ' "' + preset + '" "' + track + '"';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Jukebox';
	    sendthis.Context.action = action;
	    sendthis.Context.preset = preset;
	    sendthis.Context.track = track;

	    conn.send(JSON.stringify(sendthis));
	    //jQuery.get("Send.php", { action: action, preset: preset, track: track } , function (data) {
		////alert('Load OK' + data);
	    //});
	}
	return true;
    });
    
    // This one is called when an entry in the mainmenu is clicked
    $('body').delegate("a.menuclick", "click", function() {
	var menu = $(this).data("musik").menu;
	var type = $(this).data("musik").type;
	var title = $(this).data("musik").title;
	var html = "";
	SubMenuData = $(this).data("musik");
	console.log("a.menuclick: menu = " + menu + ", type = " + type + ", title = " + title);
	
	if (type == "alphabet") {

	    var sendthis = new Object();
	    sendthis.Message = 'Query AlphabetPresent "' + menu + '"';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Query AlphabetPresent';
	    sendthis.Context.menu = menu;
	    sendthis.Context.type = type;
	    sendthis.Context.title = title;

	    conn.send(JSON.stringify(sendthis));
	}
	else if (type == "newest") {
	    var sendthis = new Object();
	    sendthis.Message = 'Query Newest';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Query AlbumList';
	    sendthis.Context.menu = menu;
	    sendthis.Context.type = type;
	    sendthis.Context.title = title;

	    conn.send(JSON.stringify(sendthis));
	}
	else
	{
	    var sendthis = new Object();
	    sendthis.Message = 'Query AlbumList "' + menu + '" "*"';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Query AlbumList';
	    sendthis.Context.menu = menu;
	    sendthis.Context.type = type;
	    sendthis.Context.title = title;

	    conn.send(JSON.stringify(sendthis));
	}
	return true;
    });

    // This one is called when an entry in the alphabetmenu is clicked
    $('body').delegate("a.alphabetclick", "click", function() {
	var html = "";
	AlbumListData = new Object();
	AlbumListData.ArtistFirst = $(this).html();
	console.log("a.alphabetclick: menu: " + SubMenuData.menu + ", type = " + SubMenuData.type + ", artistfirst = " + AlbumListData.ArtistFirst);
	
	var sendthis = new Object();
	sendthis.Message = 'Query AlbumList "' + SubMenuData.menu + '" "' + AlbumListData.ArtistFirst + '"';
	sendthis.Context = new Object();
	sendthis.Context.query = 'Query AlbumList';
	sendthis.Context.menu = SubMenuData.menu;
	sendthis.Context.type = SubMenuData.type;
	sendthis.Context.title = SubMenuData.title + " - " + AlbumListData.ArtistFirst;

	conn.send(JSON.stringify(sendthis));
	return true;
    });

    // This one is called when an entry in the alphabetmenu is clicked
    $('body').delegate("a.queueclick", "click", function() {
	var html = "";
	console.log("a.queueclick: ");

	return true;
    });

    // This one is called when an entry in the albummenu is clicked
    $('body').delegate("a.showalbumclick", "click", function() {
	PlayData = new Object();
	PlayData.preset = $(this).data("musik").preset;
	var html = "";
	var first = true;
	console.log("a.showalbumclick: menu: " + SubMenuData.menu + ", type = " + SubMenuData.type + ", preset = " + PlayData.preset);

	var sendthis = new Object();
	sendthis.Message = 'Query Album "' + PlayData.preset + '"';
	sendthis.Context = new Object();
	sendthis.Context.query = 'Query Album';
	sendthis.Context.menu = SubMenuData.menu;
	sendthis.Context.type = SubMenuData.type;

	conn.send(JSON.stringify(sendthis));
	return true;
    });

    $("body").on('pagecontainerbeforeshow', function (event, ui) {
        //pageId = $('body').pagecontainer('getActivePage').prop('id'); 
	var pageId = ui.toPage[0].id;
        console.log('beforeshow toPage: '+pageId);
        if (pageId==='queue') {
            console.log('beforeshow Do stuff: ' + pageId);

	    var sendthis = new Object();
	    sendthis.Message = 'Query PlayingNow';
	    sendthis.Context = new Object();
	    sendthis.Context.query = 'Query PlayingNow';

	    conn.send(JSON.stringify(sendthis));

	    console.log('beforeshow Do stuff finished: ' + pageId);
        }
    });

    $("body").on('pagecontainershow', function (event, ui) {
        //pageId = $('body').pagecontainer('getActivePage').prop('id'); 
	var pageId = ui.toPage[0].id;
        console.log('show toPage: '+pageId);
        if (pageId==='queue') {
            console.log('show Do stuff: ' + pageId);
	        var this_li = $("#queue-"+Queue.CurLinnId);
		var Pos = this_li.offset();
		if (Pos !== undefined && Pos.top > 100)
		{
		    $.mobile.silentScroll(Pos.top - 100);
		    Queue.ScrollTop = Pos.top;
		}
		else if (Queue.ScrollTop != -1)
		{
		    $.mobile.silentScroll(Queue.ScrollTop - 100);
		}
	    console.log('show Do stuff finished: ' + pageId);
        }
    });


    // Bind to the navigate event
    //$( window ).on( "navigate", function() {
	    //console.log( "navigated!" );
    //});
    $( window ).on( "navigate", function( event, data ) {
		console.log( "Navigate: " + data.state );
		//console.log( data.state.info );
		//console.log( data.state.direction );
		//console.log( data.state.url );
		//console.log( data.state.hash );
		//if (false && data.state.info !== undefined) {
		    //console.log("Updating ul...");
		//var ul = $("main");
    //            ul.html( data.state.info );
    //            ul.listview( "refresh" );
    //            ul.trigger( "updatelayout");
		//}
	});
   

    // Click a button in left Kontrol panel
    $('body').delegate("button.panelclick", "click", function() {
	var volume = $(this).data("musik").volume;
	var action = $(this).data("musik").action;
	var preset = $(this).data("musik").preset;
	var track  = $(this).data("musik").track;
	if (track === undefined) track = 0;
	console.log("button.panelclick: " + action + " = " + preset + ", " + track + ", " + volume);
	if (action != "Cancel") {
	    if (action == "PlayRandomTracks") {
		var sendthis = new Object();
		sendthis.Message = 'Jukebox ' + action + ' "' + preset + '" "' + track + '"';
		sendthis.Context = new Object();
		sendthis.Context.query = 'Jukebox';
		sendthis.Context.action = action;
		sendthis.Context.preset = preset;
		sendthis.Context.track = track;

		conn.send(JSON.stringify(sendthis));
	    }
	    else if (action.indexOf('Source-') >= 0) {
		var sendthis = new Object();
		sendthis.Message = action;
		sendthis.Context = new Object();
		sendthis.Context.query = 'Source';
		sendthis.Context.action = action;

		conn.send(JSON.stringify(sendthis));
	    }
	    else if (action.indexOf('Control-') >= 0) {
		var sendthis = new Object();
		sendthis.Message = action;
		sendthis.Context = new Object();
		sendthis.Context.query = 'Source';
		sendthis.Context.action = action;

		conn.send(JSON.stringify(sendthis));
	    }
	    else if (action.indexOf('Volume-') >= 0) {
		var sendthis = new Object();
		sendthis.Message = action;
		sendthis.Context = new Object();
		sendthis.Context.query = 'Volume';
		sendthis.Context.action = action;

		conn.send(JSON.stringify(sendthis));
	    }
	}
	return true;
    });

    $( document ).on( "pagecreate", "#musik", function() {
	$( "#autocomplete" ).on( "filterablebeforefilter", function ( e, data ) {
	    var $ul = $( this ),
                $input = $( data.input ),
                filtertext = $input.val(),
                html = "",
		id = "musik";
            $ul.html( "" );
            if ( filtertext && filtertext.length > 2 ) {
                $ul.html( "<li><div class='ui-loader'><span class='ui-icon ui-icon-loading'></span></div></li>" );
                $ul.listview( "refresh" );

		var sendthis = new Object();
		sendthis.Message = 'Query Search "' + filtertext + '"';
		sendthis.Context = new Object();
		sendthis.Context.query = 'Query Search';
		sendthis.Context.id = id;
		sendthis.Context.filtertext = filtertext;
		sendthis.Context.ul = "#autocomplete";

		conn.send(JSON.stringify(sendthis));
            }
	});
    });
    
    // Change Kontrol volume slider
    $(document).on("change", "input#volume", function() {
	var vol = $(this).val();
	console.log("volume = " + vol);
	jQuery.get("Send.php", { action: "SetVolume", volume: vol } , function (data) {
	    //alert('Load OK' + data);
	});
    });

    //$("img.onepreset").lazyload({placeholder : "webapp/tuupola-jquery_lazyload-3f123e9/img/grey.gif"});

    function getStatus() {
	$.getJSON("Send.php", { action: "State", volume: 0, preset: 0 } , function (data) {
	    //$('div#status').html(data.status);
	    //$('div#lastupdate').html(data.lastupdate);
	    var myslider = $('input.volume');
	    //if (myslider.val() != data.Volume)
	    //{
		myslider.val(data.Volume);
		myslider.attr('max', data.MAX_VOLUME);
		myslider.slider('refresh');
	    //}
	});
	setTimeout("getStatus()",10000);
    }

    function AlbumListEntry(id, preset, values) {
	//<li><a id="p0_D-187" class="playpopup" data-rel="popup" href="#" data-musik='{"popupid": "p0_D-popup", "preset": "187"}'>
	//<img class="sprite_187" src="Transparent.gif"/><h3>DAD</h3><p>Call Of The Wild (1986)</p></a>
	//<a href="album_187.html"></a></li>
	var html = "";

        html += '<li>';
	html += '<a id="' + id + '-' + preset + '" class="playpopup" data-rel="popup" href="#" data-musik=' + "'" + '{"popupid": "' + id + '-popup", "preset": "' + preset + '"}' + "'" + '>';
	html += '<img class="sprite_' + preset + '" src="Transparent.gif"/>';

	html += '<h3>';

	if (values.Artist == "Various Artists")
	{
	    html += values.Album;
	    html += '</h3>';
	    html += '<p>' + ' (' + values.Date + ')</p>';  
	    html += '</a>';
	}
	else
	{
	    html += values.Artist;
	    html += '</h3>';
	    html += '<p>' + values.Album + ' (' + values.Date + ')</p>';  
	    html += '</a>';
	}

	html += '<a href="#" class="showalbumclick" data-musik=' + "'" + '{"preset": "' + preset + '"}' + "'" + '></a>';

	html += '</li>';

	return html;
    }

    function AlbumEntry(id, preset, trackseq, values) {
	//<li><a id ="album-194-1" href="#" class="playpopup" data-rel="popup" 
	//       data-musik='{"popupid": "album-popup", "preset": "194", "track": "1"}'>
	//       <h3>1. Revolution</h3><p>3:23</p></a></li>
	var html = "";

        html += '<li>';
	html += '<a id="' + id + '-' + preset + '-' + trackseq + '" class="playpopup" data-rel="popup" href="#" data-musik=' + "'" + '{"popupid": "' + id + '-popup", "preset": "' + preset + '", "track": "' + trackseq + '"}' + "'" + '>';

	html += '<h3>';

	var Performer = "";
	if (values.ArtistAlbumArtist !== values.ArtistPerformer)
	    Performer = " (" + values.ArtistPerformer + ")";
	html += values.TrackNumber + '. ';
	html += values.Title + Performer;
	html += '</h3>';
	html += '<p>' + values.Duration + '</p>';  
	html += '</a>';

	html += '</li>';

	return html;
    }

    function QueueTrackEntry(id, values) {
	var html = "";
	Queue.Sequence[values.Seq] = values.LinnId;
        html += '<li id ="' + id  + '-' + values.LinnId + '"' + '>';
	html += '<a id ="' + id  + '-A-' + values.LinnId + '" href="#" class="queuepopup" data-rel="popup" data-musik=' + "'" + '{"popupid": "' + id + '-popup", "LinnId": "' +values.LinnId + '", "preset": "' + values.Preset + '", "track": "' + values.TrackSeq + '"}' + "'" + '>';
	html += '<img class="sprite_' + values.Preset + '" src="Transparent.gif"/>';
	html += '<h3>' + values.ArtistPerformer + ' - ' + values.Album + '</h3>';
	html += '<p>' + values.TrackNumber + '. ' + values.Title + ' (' + values.Duration + ') ' + '</p> ';
        html += '</a>';
	html += '<a href="#" class="showalbumclick" data-musik=' + "'" + '{"preset": "' + values.Preset + '"}' + "'" + '></a>';
        html += '</li>';

	return html;
    }


    function SearchAlbumEntry(id, preset, filtertext, values) {
	var html = "";

        html += '<li data-filtertext="' + filtertext + '">';
	html += '<a id="' + id + '-' + preset + '" class="playpopup" data-rel="popup" href="#" data-musik=' + "'" + '{"popupid": "' + id + '-popup", "preset": "' + preset + '"}' + "'" + '>';
	html += '<img class="sprite_' + preset + '" src="Transparent.gif"/>';

	html += '<h3>';

	if (values.ArtistPerformer == "Various Artists")
	{
	    html += values.Album;
	    html += '</h3>';
	    html += '<p>' + ' (' + values.Year + ')</p>';  
	    html += '</a>';
	}
	else
	{
	    html += values.ArtistPerformer;
	    html += '</h3>';
	    html += '<p>' + values.Album + ' (' + values.Year + ')</p>';  
	    html += '</a>';
	}

	//html += '<a href="album_' + preset + '.html"></a>';
	html += '<a href="#" class="showalbumclick" data-musik=' + "'" + '{"preset": "' + preset + '"}' + "'" + '></a>';

	html += '</li>';

	return html;
    }

    function SearchTrackEntry(id, preset, trackseq, filtertext, values) {
	var html = "";
        html += '<li data-filtertext="' + filtertext + '">';
	html += '<a id ="' + id  + '-' + preset + '-' + trackseq + '" href="#" class="playpopup" data-rel="popup" data-musik=' + "'" + '{"popupid": "' + id + '-popup", "preset": "' + preset + '", "track": "' + trackseq + '"}' + "'" + '>';
	html += '<img class="sprite_' + preset + '" src="Transparent.gif"/>';
	html += '<h3>' + values.ArtistPerformer + ' - ' + values.Album + '</h3>';
	html += '<h3>' + values.TrackNumber + '. ' + values.Title + '</h3> ';
	html += '<p>' + values.Duration + '</p>';
        html += '</a>';
	//html += '<a href="album_' + preset + '.html"></a>';
	html += '<a href="#" class="showalbumclick" data-musik=' + "'" + '{"preset": "' + preset + '"}' + "'" + '></a>';
        html += '</li>';

	return html;
    }


});

// Query the device pixel ratio. 
//------------------------------- 
function getDevicePixelRatio() { 
   if(window.devicePixelRatio === undefined) 
      return 1; // No pixel ratio available. Assume 1:1. 
   return window.devicePixelRatio; 
};


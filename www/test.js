var conn = new WebSocket('ws://localhost:8080');
conn.onopen = function(e) {
    console.log("Connection established!");
};

conn.onmessage = function(e) {
    try
    {
    var data = JSON.parse(e.data);
    console.log(data);
    console.log(data.Message);
    console.log(data.Context);
    console.log(data.Result);
    }
    catch(ee)
    {
	console.log(e.data);
    }
};



#############

sendthis = new Object();
sendthis.Message = 'Query Search "Cougar"';;
sendthis.Context = new Object();
sendthis.Context.track = 5;
sendthis.Context.popupid = 7;
conn.send(JSON.stringify(sendthis));

conn.send('ECHO:jsdkldsklsad dsajksadjklsda');

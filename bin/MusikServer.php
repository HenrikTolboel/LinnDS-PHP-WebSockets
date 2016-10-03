<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MusikServerApp\ServerState;
use MusikServerApp\LPECClient;
use MusikServerApp\MusicServer;

require_once("src/MusikServerApp/setup.php");


require dirname(__DIR__) . '/vendor/autoload.php';


SetLogFile(dirname($argv[0]) . "/logfile.txt");

LogWrite("############################## Restarted ######################################");

$ServerState = ServerState::getInstance();

$LPECSTREAM = "";
	$LPECSTREAM = stream_socket_client('tcp://192.168.0.12:23', $errno, $errstr);
	echo "socket: $LPECSTREAM, $errno, $errstr\n";

$LPEC = LPECClient::getInstance($LPECSTREAM, $ServerState, 20480);



function lpecread($socket)
{
    global $LPEC;
    //$resp = fread($socket, 30000);
    $resp = stream_get_line($socket, 30000, "\r\n");
    //LogWrite("lpecread: $resp");
    return $LPEC->processMessage($resp);
}

function lpecwrite($socket)
{
    //fwrite($socket, "SUBSCRIBE Ds/Volume\n");
}


$musicServer = new MusicServer($ServerState, $LPEC);
$server = IoServer::factory(
    new HttpServer(
	new WsServer(
	    $musicServer
	)
    ),
    8080
);

$server->loop->addReadStream($LPECSTREAM, lpecread);
//$server->loop->addWriteStream($LPECSTREAM, lpecwrite);
//$server->loop->addReadStream($LPECSTREAM, array('lpecreader',read));
// LPECSTREAM is inserted in main event loop, and when something
// available, the callable function (array(....)) is called.

lpecwrite($LPECSTREAM);
$server->run();


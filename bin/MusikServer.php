<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MusikServerApp\ServerState;
use MusikServerApp\LPECClient;
use MusikServerApp\MusicDB;
use MusikServerApp\MusicServer;

echo "bin/MusikServer.php: __DIR__=" . dirname(__DIR__) . "\n";

require dirname(__DIR__) . '/vendor/autoload.php';

require_once(dirname(__DIR__) . "/src/MusikServerApp/setup.php");

//SetLogFile(dirname($argv[0]) . "/logfile.txt");
SetLogFile(dirname(__DIR__) . "/logfile.txt");

LogWrite("############################## Restarted ######################################");

$DATABASE_FILENAME = dirname(__DIR__) . "/LinnDS-jukebox.db";

$musicDB = MusicDB::create($DATABASE_FILENAME);
$musicDB->close();

$ServerState = ServerState::getInstance();

$LinnAdr = 'tcp://' . $LINN_HOST . ':' . $LINN_PORT;
echo "Linn DS at: " . $LinnAdr . "\n";

$LPECSTREAM = stream_socket_client($LinnAdr, $errno, $errstr);

echo "socket: $LPECSTREAM, $errno, $errstr\n";

$LPEC = LPECClient::getInstance($LPECSTREAM, $ServerState, 20480);



$musicServer = new MusicServer($ServerState, $LPEC);

$server = IoServer::factory(
    new HttpServer(
	new WsServer(
	    $musicServer
	)
    ),
    9052
);

function lpecread($socket)
{
    global $LPEC;
    global $musicServer;

    $resp = stream_get_line($socket, 30000, "\r\n");
    //LogWrite("lpecread: $resp");
    $DataHandled = $LPEC->processMessage($resp);

    if ($DataHandled == 2)
    {
	$musicServer->SendStateToAll();
    }

    return $DataHandled != 0;
}

$server->loop->addReadStream($LPECSTREAM, 'lpecread');
// LPECSTREAM is inserted in main event loop, and when something
// available, the callable function (array(....)) is called.

$server->run();


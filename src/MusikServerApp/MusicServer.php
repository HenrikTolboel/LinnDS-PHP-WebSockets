<?php
namespace MusikServerApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use MusikServerApp\ServerState;
use MusikServerApp\MusicDB;
use MusikServerApp\LPECClient;

require_once("setup.php");
require_once("StringUtils.php");
require_once("html_parts.php");

class MusicServer implements MessageComponentInterface {
    protected $clients;
    protected $serverState;
    protected $LPEC;

    public function __construct(ServerState $ServerState, LPECClient $LPECClient) {
        $this->clients = new \SplObjectStorage;
	$this->serverState = $ServerState;
	$this->LPEC = $LPECClient;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {

	$data = isJSON($msg, true);

	if ($data === false)
	{
	    $message = $msg;
	    $context = false;
	    echo "$message \n";
	}
	else
	{
	    echo "MusicServer::onMessage: $msg \n";
	    echo print_r($data, true) . "\n";
	    $message = $data->{"Message"};
	    $context = $data->{"Context"};
	    echo "$message \n";
	}

	if (strncmp($message, "ECHO:", 5) == 0)
	{
	    $numRecv = count($this->clients) - 1;
	    echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
		, $from->resourceId, $message, $numRecv, $numRecv == 1 ? '' : 's');

	    foreach ($this->clients as $client) {
		if ($from !== $client) {
		    // The sender is not the receiver, send to each client connected
		    $client->send($message);
		}
	    }
	    $from->send("ECHO:I have distributed it!");
	    return;
	}
	elseif (strpos($message, "Query") !== false)
	{
	    $this->processQueryMessage($from, $message, $context);
	    return;
	    
	}
	elseif (strpos($message, "HTML") !== false)
	{
	    $this->processHtmlMessage($from, $message, $context);
	    return;
	    
	}

	$this->processLinnMessage($from, $message);
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

    // END MessageComponentInterface

    // Send to all websocket clients
    public function SendAll($msg)
    {
        foreach ($this->clients as $client) {
	    $client->send($msg);
	}
    }

    private function getState()
    {
	return $this->serverState;
    }

    public function SendStateToAll()
    {
	$Res = array();
	$Res["Message"] = "State";
	$Res["Context"] = "";

	$Res["Result"] = $this->getState()->StateArray();

	$this->SendAll(json_encode($Res));
    }

    // Handle one message to Linn - potentially answering back to $from
    private function processLinnMessage($from, $message)
    {
	LogWrite("MusicServer::processLinnMessage - $message");

	$DataHandled = false;

	if (strpos($message, "Jukebox") !== false)
	{
	    // Here things happens - we execute the actions sent from the
	    // application, by issuing a number of ACTIONs.
	    $D = getParameters($message);

	    if (strpos($message, "Jukebox PlayNow ") !== false) 
	    {
		//Jukebox PlayNow \"(\d+)\" \"(\d+)\"
		$JukeBoxPlay = $D[0];
		$JukeBoxTrack = $D[1];
		LogWrite("JukeBoxPlayNow: " . $JukeBoxPlay . ", " . $JukeBoxTrack);

		if ($this->LPEC->SelectPlaylist() == false)
		    $Continue = false;

		if ($this->LPEC->Stop() == false)
		    $Continue = false;

		$musicDB = new MusicDB();
		if ($this->LPEC->DeleteAll($musicDB) == false)
		$Continue = false;
		if ($this->LPEC->InsertDIDL_list($musicDB, $JukeBoxPlay, $JukeBoxTrack, 0) == false)
		    $Continue = false;
		$musicDB->close();

		//Send("ACTION Ds/Jukebox 3 SetCurrentPreset \"" . $JukeBoxPlay . "\"");

		if ($this->LPEC->Play() == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Playlist 1 IdArray") == false)
		    $Continue = false;
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Jukebox PlayNext ") !== false) 
	    {
		//Jukebox PlayNext \"(\d+)\" \"(\d+)\"
		$JukeBoxPlay = $D[0];
		$JukeBoxTrack = $D[1];
		LogWrite("JukeBoxPlayNext: " . $JukeBoxPlay . ", " . $JukeBoxTrack);

		if ($this->LPEC->SelectPlaylist() == false)
		    $Continue = false;

		$musicDB = new MusicDB();
		if ($this->LPEC->InsertDIDL_list($musicDB, $JukeBoxPlay, $JukeBoxTrack, $this->getState()->getState('Id')) == false)
		    $Continue = false;
		$musicDB->close();

		if ($this->LPEC->Play() == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Playlist 1 IdArray") == false)
		    $Continue = false;

		if ($DEBUG > 0)
		{
		    //LogWrite($message);
		    //print_r($State);
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Jukebox PlayLater ") !== false) 
	    {
		//Jukebox PlayLater \"(\d+)\" \"(\d+)\"
		$JukeBoxPlay = $D[0];
		$JukeBoxTrack = $D[1];
		LogWrite("JukeBoxPlayLater: " . $JukeBoxPlay . ", " . $JukeBoxTrack);

		if ($this->LPEC->SelectPlaylist() == false)
		    $Continue = false;

		$musicDB = new MusicDB();
		if ($this->LPEC->InsertDIDL_list($musicDB, $JukeBoxPlay, $JukeBoxTrack, end($this->getState()->getState('IdArray'))) == false)
		    $Continue = false;
		$musicDB->close();


		if ($this->LPEC->Play() == false)
		    $Continue = false;
		$this->LPEC->Send("ACTION Ds/Playlist 1 IdArray");

		if ($DEBUG > 0)
		{
		    //LogWrite($message);
		    //print_r($State);
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Jukebox PlayRandomTracks ") !== false) 
	    {
		//Jukebox PlayRandomTracks \"(\d+)\" \"(\d+)\"
		$JukeBoxFirstAlbum = $D[0];
		$JukeBoxLastAlbum = $D[1];
		LogWrite("JukeBoxPlayRandomTracks: " . $JukeBoxFirstAlbum . ", " . $JukeBoxLastAlbum);

		if ($this->LPEC->SelectPlaylist() == false)
		    $Continue = false;

		$musicDB = new MusicDB();
		if ($this->getState()->getState('TransportState') == "Stopped")
		{
		    if ($this->LPEC->DeleteAll($musicDB) == false)
			$Continue = false;
		}

		for ($i = 0; $i < 50; $i++) 
		{
		    $RandomPreset = rand($JukeBoxFirstAlbum, $JukeBoxLastAlbum);
		    $RandomTrack = rand(1, $musicDB->NumberOfTracks($RandomPreset));
		    if ($i == 0)
		    {
			if ($this->LPEC->InsertDIDL_list($musicDB, $RandomPreset, $RandomTrack, end($this->getState()->getState('IdArray'))) == false)
			    $Continue = false;
		    }
		    else
		    {
			if ($this->LPEC->InsertDIDL_list($musicDB, $RandomPreset, $RandomTrack, "%NewId%") == false)
			    $Continue = false;
		    }
		}

		$musicDB->close();

		if ($this->LPEC->Play() == false)
		    $Continue = false;
		$this->LPEC->Send("ACTION Ds/Playlist 1 IdArray");

		if ($DEBUG > 0)
		{
		    //LogWrite($message);
		    //print_r($State);
		}
		$DataHandled = true;
	    }
	}
	elseif (strpos($message, "Volume") !== false) 
	{
	    $D = getParameters($message);
	    // Here things happens - we execute the actions sent from the
	    // application, by issuing a number of ACTIONs.

	    if (strpos($message, "Volume-Set ") !== false) 
	    {
		//Volume Set \"(\d+)\"
		$value = $D[0];
		if ($value > $this->getState()->getState('MAX_VOLUME'))
		{
		    $value = $this->getState()->getState('MAX_VOLUME');
		}
		if ($value != $this->getState()->getState('Volume') && $value != "")
		{
		    LogWrite("VolumeSet: " . $value);
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 SetVolume \"" . $value . "\"") == false)
			$Continue = false;
		    $this->getState()->setState('Volume', $value);
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Volume-Incr5") !== false) 
	    {
		//Volume Incr5
		if ($this->getState()->getState('Volume') < $this->getState()->getState('MAX_VOLUME') -5)
		{
		    LogWrite("VolumeIncr5: ");
		    $value = $this->getState()->getState('Volume');
		    $value = $value + 5;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
			$Continue = false;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
			$Continue = false;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
			$Continue = false;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
			$Continue = false;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
			$Continue = false;
		    $this->getState()->setState('Volume', $value);
		}
		else
		{
		    LogWrite("VolumeIncr: IGNORED MAX_VOLUME REACHED");
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Volume-Incr") !== false) 
	    {
		//Volume Incr
		if ($this->getState()->getState('Volume') < $this->getState()->getState('MAX_VOLUME'))
		{
		    LogWrite("VolumeIncr: ");
		    $value = $this->getState()->getState('Volume');
		    $value = $value + 1;
		    if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeInc") == false)
		        $Continue = false;
		    $this->getState()->setState('Volume', $value);
		}
		else
		{
		    LogWrite("VolumeIncr: IGNORED MAX_VOLUME REACHED");
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Volume-Decr5") !== false) 
	    {
		//Volume Decr5
		LogWrite("VolumeDecr: ");
		$value = $this->getState()->getState('Volume');
		$value = $value - 5;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		$this->getState()->setState('Volume', $value);
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Volume-Decr") !== false) 
	    {
		//Volume Decr
		LogWrite("VolumeDecr: ");
		$value = $this->getState()->getState('Volume');
		$value = $value - 1;
		if ($this->LPEC->Send("ACTION Ds/Volume 1 VolumeDec") == false)
		    $Continue = false;
		$this->getState()->setState('Volume', $value);
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Volume-Reset") !== false) 
	    {
		//Volume Reset
		LogWrite("VolumeReset: ");
		$value = 30;
		LogWrite("VolumeSet: " . $value);
		if ($this->LPEC->Send("ACTION Ds/Volume 1 SetVolume \"" . $value . "\"") == false)
		    $Continue = false;
		$this->getState()->setState('Volume', $value);
		$DataHandled = true;
	    }
	}
	elseif (strpos($message, "Control") !== false) 
	{
	    // Here things happens - we execute the actions sent from the
	    // application, by issuing a number of ACTIONs.

	    if (strpos($message, "Control-Play") !== false) 
	    {
		//Control Play
		if ($this->getState()->getState('TransportState') === "Stopped" || $this->getState()->getState('TransportState') === "Paused")
		{
		    LogWrite("ControlPlay: ");
		    if ($this->LPEC->Play() == false)
			$Continue = false;
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Control-Pause") !== false) 
	    {
		//Control Pause
		if ($this->getState()->getState('TransportState') !== "Paused")
		{
		    LogWrite("ControlPause: ");
		    if ($this->LPEC->Send("ACTION Ds/Playlist 1 Pause") == false)
			$Continue = false;
		}
		else
		{
		    LogWrite("ControlPause - restart: ");
		    if ($this->LPEC->Play() == false)
			$Continue = false;
		}

		$DataHandled = true;
	    }
	    elseif (strpos($message, "Control-Stop") !== false) 
	    {
		//Control Stop
		if ($this->getState()->getState('TransportState') !== "Stopped")
		{
		    LogWrite("ControlStop: ");
		    if ($this->LPEC->Stop() == false)
			$Continue = false;
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Control-Next") !== false) 
	    {
		//Control Next
		if ($this->getState()->getState('TransportState') != "Stopped")
		{
		    LogWrite("ControlNext: ");
		    if ($this->LPEC->Send("ACTION Ds/Playlist 1 Next") == false)
			$Continue = false;
		}
		$DataHandled = true;
	    }
	    elseif (strpos($message, "Control-Previous") !== false) 
	    {
		//Control Previous
		if ($this->getState()->getState('TransportState') != "Stopped")
		{
		    LogWrite("ControlPrevious: ");
		    if ($this->LPEC->Send("ACTION Ds/Playlist 1 Previous") == false)
			$Continue = false;
		}
		$DataHandled = true;
	    }
	}
	elseif (strpos($message, "Source") !== false) 
	{
	    // Here things happens - we execute the actions sent from the
	    // application, by issuing a number of ACTIONs.

	    if (strpos($message, "Source-Off") !== false) 
	    {
		//Source Off
		if ($this->getState()->getState('Standby') == "false")
		{
		    if ($this->LPEC->Send('ACTION Ds/Product 1 SetStandby "true"') == false)
			$Continue = false;
		    $this->getState()->setState('Standby', true);
		}
		$DataHandled = true;
	    }
	    else
	    {
		if ($this->getState()->getState('Standby') == "true")
		{
		    if ($this->LPEC->Send('ACTION Ds/Product 1 SetStandby "false"') == false)
			$Continue = false;
		    $this->getState()->setState('Standby', true);
		}

		if (strpos($message, "Source-Playlist") !== false) 
		{
		    //Source Playlist
		    if ($this->getState()->getState('SourceIndex') != $this->getState()->getStateArray('SourceName', 'Playlist'))
		    {
			if ($this->LPEC->Send('ACTION Ds/Product 1 SetSourceIndex "' . $this->getState()->getStateArray('SourceName', 'Playlist') . '"') == false)
			    $Continue = false;
		    }
		    if ($this->LPEC->Play() == false)
			$Continue = false;
		    $DataHandled = true;
		}
		elseif (strpos($message, "Source-TV") !== false) 
		{
		    //Source TV
		    if ($this->getState()->getState('SourceIndex') != $this->getState()->getStateArray('SourceName', 'TV'))
		    {
			if ($this->LPEC->Send('ACTION Ds/Product 1 SetSourceIndex "' . $this->getState()->getStateArray('SourceName', 'TV') . '"') == false)
			    $Continue = false;
		    }
		    $DataHandled = true;
		}
		elseif (strpos($message, "Source-Radio") !== false) 
		{
		    //Source Radio
		    if ($this->getState()->getState('SourceIndex') != $this->getState()->getStateArray('SourceName', 'Radio'))
		    {
			if ($this->LPEC->Send('ACTION Ds/Product 1 SetSourceIndex "' . $this-getState()->getStateArray('SourceName', 'Radio') . '"') == false)
			    $Continue = false;
		    }
		    $DataHandled = true;
		}
		elseif (strpos($message, "Source-NetAux") !== false) 
		{
		    //Source NetAux
		    if ($this->getState()->getState('SourceIndex') != $this->getState()->getStateArray('SourceName', 'Net Aux'))
		    {
			if ($this->LPEC->Send('ACTION Ds/Product 1 SetSourceIndex "' . $this->getState()->getStateArray('SourceName', 'Net Aux') . '"') == false)
			    $Continue = false;
		    }
		    $DataHandled = true;
		}
	    }
	}
	elseif (strpos($message, "State") !== false) 
	{
	    LogWrite("HTState: " . $this->getState()->dump());
	    $seri = $this->getState()->Serialize();
	    LogWrite("Serialized: " . $seri);
	    $from->send($seri); // answer State back to caller ($from)
	    $DataHandled = true;
	}

	return $DataHandled;
    }

    // Handle one query message - answering back to $from
    private function processQueryMessage($from, $message, $context)
    {
	    echo "Query...\n";
	LogWrite("MusicServer::processQueryMessage - $message");

	$DataHandled = false;

	if (strpos($message, "Query") === false)
	{
	    return false;
	}

	$D = getParameters($message);

	$Res = array();
	$Res["Message"] = $message;
	$Res["Context"] = $context;

	if (strpos($message, "Query Album ") !== false) 
	{
	    //Query Album \"(\d+)\"
	    $value = $D[0];
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QueryAlbum($value);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	elseif (strpos($message, "Query AlbumList ") !== false) 
	{
	    //Query AlbumList \"(\d+)\" \"(\d+)\"
	    $v1 = $D[0];
	    $v2 = $D[1];
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QueryAlbumList($v1, $v2);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	elseif (strpos($message, "Query Newest") !== false) 
	{
	    //Query Newest"
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QueryNewest();
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	elseif (strpos($message, "Query AlphabetPresent ") !== false) 
	{
	    //Query AlphabetPresent \"(\d+)\"
	    $value = $D[0];
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QueryAlphabetPresent($value);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	elseif (strpos($message, "Query Search ") !== false) 
	{
	    //Query Search \"(\d+)\"
	    $value = $D[0];
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QuerySearch($value);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	elseif (strpos($message, "Query PlayingNow") !== false) 
	{
	    echo "Query PlayingNow...\n";
	    //Query PlayingNow \"(\d+)\"
	    $value = $D[0];
	    $musicDB = new MusicDB();
	    $Res["Result"] = $musicDB->QueryPlayingNow($value);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}

	return $DataHandled;
    }

    // Handle one Html message - answering back to $from
    private function processHtmlMessage($from, $message, $context)
    {
	    echo "Html...\n";
	LogWrite("MusicServer::processHtmlMessage - $message");

	$DataHandled = false;

	if (strpos($message, "HTML") === false)
	{
	    return false;
	}

	$D = getParameters($message);

	$Res = array();
	$Res["Message"] = $message;
	$Res["Context"] = $context;

	if (strpos($message, "HTML Body") !== false) 
	{
	    //HTML Body
	    $musicDB = new MusicDB();
	    $Res["Result"] = Body($musicDB);
	    $musicDB->close();
	    $from->send(json_encode($Res));
	    $DataHandled = true;
	}
	return $DataHandled;
    }
}

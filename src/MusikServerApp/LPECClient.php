<?php
/*!
* LinnDS-jukebox
*
* Copyright (c) 2015-2016 Henrik Tolbøl, http://tolbøl.dk
*
* Licensed under the MIT license:
* http://www.opensource.org/licenses/mit-license.php
*/

namespace MusikServerApp;

use MusikServerApp\ServerState;
use MusikServerApp\MusicDB;
use MusikServerApp\MusicServer;

require_once("setup.php");
require_once("StringUtils.php");


class LPECClient
{
	protected $socket;
	protected $serverState;
	protected $maxBufferSize;
	
	protected function __construct($Socket, ServerState $ServerState, $bufferLength = 2048) {
		
		$this->socket = $Socket;
		$this->serverState = $ServerState;
		$this->maxBufferSize = $bufferLength;
		
		$this->InitLPECClient();
	}
	
	// Queue is a queue of outstanding commands to be sent to Linn.
	// The currently executing command is still in the queue, removed when the
	// response commes.
	protected $Queue = array();
	
	// AwaitResponse tells whether we miss a response before sending next
	// command.
	protected $AwaitResponse = 0;
	
	protected $lastCommandSent = "";
	
	// SubscribeType tells the mapping between "EVENT <digits> XXX" subscribed
	// to protokol (e.g. "Ds/Playlist")
	// <digits> -> "Ds/Playlist"
	// Used to make fewer regular expressions in the EVENT section
	protected $SubscribeType = array();
	
	// This is a singleton class
	public static function getInstance($Socket, ServerState $ServerState, $BufferLength = 2048) 
	{
		static $inst = null;
		
		if ($inst === null) 
		{
			$inst = new LPECClient($Socket, $ServerState, $BufferLength = 2048);
		}
		
		LogWrite("LPECClientSocket::getInstance");
		return $inst;
	}
	
	protected function InitLPECClient()
	{
		$this->SubscribeType['Ds/Product'] = -1;
		$this->SubscribeType['Ds/Playlist'] = -1;
		$this->SubscribeType['Ds/Jukebox'] = -1;
		$this->SubscribeType['Ds/Volume'] = -1;
		$this->SubscribeType['Ds/Radio'] = -1;
		$this->SubscribeType['Ds/Info'] = -1;
		$this->SubscribeType['Ds/Time'] = -1;
		
		$this->IncrRevNo();
	}
	
	private function getState()
	{
		return $this->serverState;
	}
	
	// Send Message to Linn via socket.
	public function Send($Message) 
	{
		// Add to queue. if not awaiting responses, then send front
		if (strlen($Message) > 0)
		{
			array_push($this->Queue, $Message);
		}
		else
		{
			$this->AwaitResponse = 0;
		}
		$Res = true;
		if ($this->AwaitResponse == 0 && count($this->Queue) > 0)
		{
			$S = array_shift($this->Queue);
			$S = str_replace("%NewId%", strval($this->getState()->getState('NewId')), $S);
			LogWrite("LPECClientSocket::Send: " . $S);
			$sent = fwrite($this->socket, $S . "\n");
			if ($sent === false)
			{
				$Res = false;
				LogWrite("Send: fwrite failed with \"" . $S . "\"");
			}
			$this->lastCommandSent = $S;
			array_unshift($this->Queue, $S); // We leave the sent item in Queue - removed when we get the response
			$this->AwaitResponse = 1;
		}
		//$CountQueue = count($this->Queue)
		return $Res;
	}
	
	public function LastCommandSent() 
	{
		return $this->LastCommandSent;
	}
	
	function PrepareXML($xml)
	{
		$xml = AbsoluteURL($xml); // late binding of http server
		
		$xml = htmlspecialchars(str_replace(array("\n", "\r"), '', $xml));
		$xml = str_replace("&amp;#", "&#", $xml); // e.g. danish "å" is transcoded from "&#E5;" to "&amp;#E5;" so we convert back
		return $xml;
	}
	
	private function CheckSend($Res, $action) {
		if ($this->Send($action) === false)
		{
			return false;
		}
		return $Res;
	}
	
	function InsertDIDL_list($musicDB, $Preset, $TrackSeq, $AfterId)
	{
		$DIDL_URL = $musicDB->PresetURL($Preset);
		$Res = true;
		LogWrite("InsertDIDL_list: " . $DIDL_URL . ", " . $TrackSeq . ", " . $AfterId);
		
		$xml = simplexml_load_file($DIDL_URL);
		
		$xml->registerXPathNamespace('didl', 'urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/');
		$URLs = $xml->xpath('//didl:res');
		
		$DIDLs = $xml->xpath('//didl:DIDL-Lite');
		
		if ($TrackSeq == 0) {
			$DIDLs[0]->addAttribute("albumkey", "ALBUMKEY");
			$DIDLs[0]->addAttribute("preset", $Preset);
			$DIDLs[0]->addAttribute("trackseq", 1);
			
			$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Insert \"" . $AfterId . "\" \"" . $this->PrepareXML($URLs[0][0]) . "\" \"" . $this->PrepareXML($DIDLs[0]->asXML()) . "\"");
			if ($this->Play() === false)
			{			
				$Res = false;
			}	
			for ($i = 1; $i < sizeof($URLs); $i++)
			{
				$DIDLs[$i]->addAttribute("albumkey", "ALBUMKEY");
				$DIDLs[$i]->addAttribute("preset", $Preset);
				$DIDLs[$i]->addAttribute("trackseq", $i+1);
				
				$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Insert \"%NewId%\" \"" . $this->PrepareXML($URLs[$i][0]) . "\" \"" . $this->PrepareXML($DIDLs[$i]->asXML()) . "\"");
			}
		}
		else
		{
			$No = $TrackSeq -1;
			$DIDLs[$No]->addAttribute("albumkey", "ALBUMKEY");
			$DIDLs[$No]->addAttribute("preset", $Preset);
			$DIDLs[$No]->addAttribute("trackseq", $TrackSeq);
			//echo  $this->PrepareXML($DIDLs[$No]->asXML()) . $NL
			
			$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Insert \"" . $AfterId . "\" \"" . $this->PrepareXML($URLs[$No][0]) . "\" \"" . $this->PrepareXML($DIDLs[$No]->asXML()) . "\"");	
			if ($this->Play() === false)
			{
				$Res = false;
			}
		}
		$this->IncrRevNo();
		return $Res;
	}
	
	function CheckPlaylist()
	{
		$Res = true;
		$seq = 0;
		foreach ($this->getState()->getState('IdArray') as $value)
		{
			$seq++;
			if ($this->getState()->getStateArray('PlaylistURLs', $value) === false) {
				$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Read \"" . $value . "\"");
			}
		}
		$this->IncrRevNo();
		return $Res;
	}
	
	
	function DeleteAll($musicDB)
	{
		$Res = true;
		$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 DeleteAll");
		$this->getState()->deleteAll();
		$this->IncrRevNo();
		return $Res;
	}
	
	function IncrRevNo()
	{
		$RevNo = $this->getState()->getState('RevNo');
		if ($RevNo === false) 
		{
			die("IncrRevNo: RevNo is false");
		}	
		$RevNo = $RevNo + 1;
		$this->getState()->setState('RevNo', $RevNo);
		//echo "LPECClient::IncrRevNo: RevNo=$RevNo \n"
		
	}
	
	public function SelectPlaylist()
	{
		$Res = true;
		if ($this->getState()->getState('Standby') == 'true')
		{
			$Res = $this->CheckSend($Res, 'ACTION Ds/Product 1 SetStandby "false"');
			$this->getState()->setState('Standby', false);
			$Res = $this->CheckSend($Res, 'ACTION Ds/Playlist 1 SetShuffle "0"');
			$Res = $this->CheckSend($Res, 'ACTION Ds/Playlist 1 SetRepeat "0"');
			$Res = $this->CheckSend($Res, 'ACTION Ds/Product 1 SetSourceIndex "' . $this->getState()->getStateArray('SourceName', 'Playlist') . '"');
		}
		elseif ($this->getState()->getState('SourceIndex') != $this->getState()->getStateArray('SourceName', 'Playlist'))
		{
			$Res = $this->CheckSend($Res, 'ACTION Ds/Playlist 1 SetShuffle "0"');
			$Res = $this->CheckSend($Res, 'ACTION Ds/Playlist 1 SetRepeat "0"');
			$Res = $this->CheckSend($Res, 'ACTION Ds/Product 1 SetSourceIndex "' . $this->getState()->getStateArray('SourceName', 'Playlist') . '"');
		}
		return $Res;
	}
	
	function Stop()
	{
		$Res = true;
		if ($this->getState()->getState('TransportState') !== "Stopped")
		{
			$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Stop");
			$this->getState()->setState('TransportState', "Stopped");
		}
		return $Res;
	}
	
	function Play()
	{
		$Res = true;
		if ($this->getState()->getState('TransportState') === "Stopped" || $this->getState()->getState('TransportState') === "Paused")
		{
			$Res = $this->CheckSend($Res, "ACTION Ds/Playlist 1 Play");
			$this->getState()->setState('TransportState', "Starting");
		}
		return $Res;
	}
	
	// Handle a line received from Linn LPEC. All lines read from Linn
	// socket should be read here and only here
	//
	// return 0 if data was not handled
	// return 1 if data was handled
	// return 2 if data was handled and state was changed
	public function processMessage($message)
	{
		$DEBUG = 3;
		LogWrite("LPECClientSocket::processMessage - $message");
		
		$DataHandled = 0;
		if (strlen($message) == 0) 
		{
			$DataHandled = 1;
		}
		elseif (strpos($message, "ALIVE Ds") !== false)
		{
			$this->Send("SUBSCRIBE Ds/Product");
			$DataHandled = 1;
		}
		elseif (strpos($message, "ALIVE") !== false)
		{
			LogWrite("ALIVE ignored : " . $message);
			$DataHandled = 1;
		}
		elseif (strpos($message, "ERROR") !== false)
		{
			LogWrite("ERROR ignored : " . $message);
			$DataHandled = 1;
		}
		elseif (strpos($message, "SUBSCRIBE") !== false)
		{
			// SUBSCRIBE are sent by Linn when a SUBSCRIBE finishes, thus
			// we send the possible next command (Send) after removing
			// previous command.
			// We record the Number to Subscribe action in the array to
			// help do less work with the events.
			$front = array_shift($this->Queue);
			if ($DEBUG > 1)
			{
				LogWrite("Command: " . $front . " -> " . $message);
				
			}			$S1 = substr($front, 10);
			$S2 = substr($message, 10);
			$this->SubscribeType[$S1] = $S2;
			$this->Send("");
			$DataHandled = 1;
		}
		elseif (strpos($message, "RESPONSE") !== false)
		{
			// RESPONSE are sent by Linn when an ACTION finishes, thus we
			// send the possible next command (Send) after removing
			// previous command.
			$front = array_shift($this->Queue);
			if ($DEBUG > 1)
			{
				LogWrite("Command: " . $front . " -> " . $message);
			}			
			if (strpos($front, "ACTION Ds/Product 1 Source ") !== false) 
			{
				//ACTION Ds/Product 1 Source \"(\d+)\"
				//RESPONSE \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\"
				$F = getParameters($front);
				$D = getParameters($message);
				
				//$State['Source_SystemName'][$F[0]] = $D[0]
				//$State['Source_Type'][$F[0]] = $D[1]
				//$State['Source_Name'][$F[0]] = $D[2]
				//$State['Source_Visible'][$F[0]] = $D[3]
				
				$this->getState()->setStateArray('SourceName', $D[2], $F[0]);
				
				if ($D[1] == "Playlist")
				{
					// We have the Playlist service. subscribe...
					$this->Send("SUBSCRIBE Ds/Playlist");
					//$this->Send("SUBSCRIBE Ds/Jukebox")
				}
				elseif ($D[1] == "Radio")
				{
					// We have the Radio service. subscribe...
					//$this->Send("SUBSCRIBE Ds/Radio")
				}
			}
			elseif (strpos($front, "ACTION Ds/Playlist 1 Read ") !== false) 
			{
				//ACTION Ds/Playlist 1 Read \"(\d+)\"
				//RESPONSE \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\"
				$F = getParameters($front);
				$D = getParameters($message);
				
				$this->getState()->setStateArray('PlaylistURLs', $F[0], $D[0]);
				$this->getState()->setStateArray('PlaylistXMLs', $F[0], $D[1]);
			}
			elseif (strpos($front, "ACTION Ds/Playlist 1 Insert ") !== false) 
			{
				//ACTION Ds/Playlist 1 Insert \"(\d+)\" \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\"
				//RESPONSE \"([[:ascii:]]+?)\"
				$F = getParameters($front);
				$D = getParameters($message);
				
				$this->getState()->setState('NewId', $D[0]);
				$this->getState()->setStateArray('PlaylistURLs', $D[0], $F[1]);
				$this->getState()->setStateArray('PlaylistXMLs', $D[0], $F[2]);
			}
			elseif (strpos($front, "ACTION Ds/Playlist 1 IdArray") !== false) 
			{
				//ACTION Ds/Playlist 1 IdArray
				//RESPONSE \"([[:ascii:]]+?)\" \"([[:ascii:]]+?)\"
				$F = getParameters($front);
				$D = getParameters($message);
				
				$this->getState()->setState('IdArray_Token', $D[0]);
				$this->getState()->setState('IdArray_base64', $D[1]);
				$this->getState()->setState('IdArray', unpack("N*", base64_decode($D[1])));
				$this->CheckPlaylist();
			}
			
			$this->Send("");
			$DataHandled = 1;
		}
		elseif (strpos($message, "EVENT ") !== false)
		{
			// EVENTs are sent by Your linn - those that were subscribed
			// to. We think the below ones are interesting....
			
			//LogWrite("EVENT - SubscribeType: " . print_r($this->SubscribeType, true))
			$E = getEvent($message);
			if (strpos($message, "EVENT " . $this->SubscribeType['Ds/Product']) !== false)
			{
				if (strpos($message, "SourceIndex ") !== false)
				{
					$this->getState()->setState('SourceIndex', $E['SourceIndex']);
				}
				if (strpos($message, "ProductModel ") !== false)
				{
					$this->getState()->setState('ProductModel', $E['ProductModel']);
				}
				if (strpos($message, "ProductName ") !== false)
				{
					$this->getState()->setState('ProductName', $E['ProductName']);
				}
				if (strpos($message, "ProductRoom ") !== false)
				{
					$this->getState()->setState('ProductRoom', $E['ProductRoom']);
				}
				if (strpos($message, "ProductType ") !== false)
				{
					$this->getState()->setState('ProductType', $E['ProductType']);
				}
				if (strpos($message, "Standby ") !== false)
				{
					$this->getState()->setState('Standby', $E['Standby']);
				}
				if (strpos($message, "ProductUrl ") !== false)
				{
					$this->getState()->setState('ProductUrl', $E['ProductUrl']);
				}
				if (strpos($message, "Attributes ") !== false)
				{
					$this->getState()->setState('Attributes', $E['Attributes']);
					if (strpos($E['Attributes'], "Volume") !== false) // We have a Volume service
					{
						$this->Send("SUBSCRIBE Ds/Volume");
					}
					if (strpos($E['Attributes'], "Info") !== false) // We have a Info service
					{
						//$this->Send("SUBSCRIBE Ds/Info")
					}
					if (strpos($E['Attributes'], "Time") !== false) // We have a Time service
					{
						//$this->Send("SUBSCRIBE Ds/Time")
					}
				}
				if (strpos($message, "SourceCount ") !== false)
				{
					for ($i = 0; $i < $E['SourceCount']; $i++) 
					{
						$this->Send("ACTION Ds/Product 1 Source \"" . $i . "\"");
					}
				}
				$DataHandled = 2;
			}
			elseif (strpos($message, "EVENT " . $this->SubscribeType['Ds/Playlist']) !== false)
			{
				if (strpos($message, "TransportState ") !== false)
				{
					$this->getState()->setState('TransportState', $E['TransportState']);
				}
				if (strpos($message, "Id ") !== false)
				{
					$this->getState()->setState('Id', $E['Id']);
					$this->getState()->setState('LinnId', $E['Id']);
				}
				if (strpos($message, "IdArray ") !== false)
				{
					$this->getState()->setState('IdArray_base64', $E['IdArray']);
					$this->getState()->setState('IdArray', unpack("N*", base64_decode($E['IdArray'])));
					$this->CheckPlaylist();
				}
				if (strpos($message, "Shuffle ") !== false)
				{
					$this->getState()->setState('Shuffle', $E['Shuffle']);
				}
				if (strpos($message, "Repeat ") !== false)
				{
					$this->getState()->setState('Repeat', $E['Repeat']);
				}
				if (strpos($message, "TrackDuration ") !== false)
				{
					$this->getState()->setState('TrackDuration', $E['TrackDuration']);
				}
				if (strpos($message, "TrackCodecName ") !== false)
				{
					$this->getState()->setState('TrackCodecName', $E['TrackCodecName']);
				}
				if (strpos($message, "TrackSampleRate ") !== false)
				{
					$this->getState()->setState('TrackSampleRate', $E['TrackSampleRate']);
				}
				if (strpos($message, "TrackBitRate ") !== false)
				{
					$this->getState()->setState('TrackBitRate', $E['TrackBitRate']);
				}
				if (strpos($message, "TrackLossless ") !== false)
				{
					$this->getState()->setState('TrackLossless', $E['TrackLossless']);
				}
				$DataHandled = 2;
			}
			elseif (strpos($message, "EVENT " . $this->SubscribeType['Ds/Volume']) !== false)
			{
				if (strpos($message, "Volume ") !== false)
				{
					LogWrite("Event Volume");
					$this->getState()->setState('Volume', $E['Volume']);
				}
				if (strpos($message, "Mute ") !== false)
				{
					$this->getState()->setState('Mute', $E['Mute']);
				}
				$DataHandled = 2;
			}
			elseif (strpos($message, "EVENT " . $this->SubscribeType['Ds/Jukebox']) !== false)
			{
				if (strpos($message, "CurrentPreset ") !== false)
				{
					$this->getState()->setState('CurrentPreset', $E['CurrentPreset']);
				}
				if (strpos($message, "CurrentBookmark ") !== false)
				{
					$this->getState()->setState('CurrentBookmark', $E['CurrentBookmark']);
				}
				$DataHandled = 2;
			}
			else
			{
				LogWrite("UNKNOWN : " . $message);
				$DataHandled = 1;
			}
		}
		return $DataHandled;
	}
}


?>


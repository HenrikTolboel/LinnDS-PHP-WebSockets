<?php
/*!
* MusicDB
*
* Copyright (c) 2015-2015 Henrik Tolbøl, http://tolbøl.dk
*
* Licensed under the MIT license:
* http://www.opensource.org/licenses/mit-license.php
*/

namespace MusikServerApp;

require_once("setup.php");

define("SINGLE_TRACKS_ONLY", 0);
define("ALBUMS_ONLY", 1);
define("ALBUM_PRESET_ONLY", 2);

class MusicDB extends \SQLite3
{
    private $insertQueueStmt = 0;
    private $updateQueueStmt = 0;
    private $deleteQueueStmt = 0;

    private $insertStateStmt = 0;
    private $updateStateStmt = 0;

    private $insertSequenceStmt = 0;
    private $deleteSequenceStmt = 0;

    private $numberOfTracksStmt = 0;
    private $presetURLStmt = 0;
    private $CheckURLExistStmt = 0;

    private $insertAlbumStmt = 0;
    private $insertTracksStmt = 0;

    function __construct()
    {
	global $DATABASE_FILENAME;

	$this->open($DATABASE_FILENAME, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	$this->CreateTables();
    }

    private function CreateTables()
    {
	static $TablesChecked = 0;
	if ($TablesChecked != 0)
	    return;
	$TablesChecked++;

	//LogWrite("MusicDB::CreateTables - checking existance of tables and indexes...");

	$this->exec('CREATE TABLE IF NOT EXISTS Tracks (AlbumKey STRING, Preset INTEGER, TrackSeq INTEGER, URL STRING, Duration STRING, Title STRING, Year STRING, AlbumArt STRING, ArtWork STRING, Genre STRING, ArtistPerformer STRING, ArtistComposer STRING, ArtistAlbumArtist STRING, ArtistConductor STRING, Album STRING, TrackNumber STRING, DiscNumber STRING, DiscCount STRING, BitRate INTEGER, SampleFrequency INTEGER, BitsPerSample STRING, Size INTEGER)');

	// Preset is an alias of the rowid field in the Album table.
	$this->exec('CREATE TABLE IF NOT EXISTS Album (Key STRING, Preset INTEGER PRIMARY KEY ASC, NoTracks INTEGER, URI STRING, ArtistFirst STRING, SortArtist STRING, Artist STRING, Album STRING, Date STRING, Genre STRING, MusicTime INTEGER, ImageURI STRING, TopDirectory STRING, RootMenuNo INTEGER)');

	// Tables used in LinnDS-jukebox-daemon.php
	$this->exec('CREATE TABLE IF NOT EXISTS Queue (LinnId INTEGER, AlbumKey STRING, Preset INTEGER, TrackSeq INTEGER, URL STRING, XML STRING)');
	$this->exec('CREATE TABLE IF NOT EXISTS State (Id STRING, Value STRING)');
	$this->exec('CREATE TABLE IF NOT EXISTS Sequence (Seq INTEGER, LinnId INTEGER)');


	// Create indexes
	$this->exec('CREATE INDEX IF NOT EXISTS Album_idx1 ON Album (Key)');
	$this->exec('CREATE INDEX IF NOT EXISTS Album_idx2 ON Album (Preset)');
	$this->exec('CREATE UNIQUE INDEX IF NOT EXISTS Album_idx3 ON Album (URI)');
	$this->exec('CREATE INDEX IF NOT EXISTS Tracks_idx1 ON Tracks (AlbumKey, TrackSeq)');
	$this->exec('CREATE INDEX IF NOT EXISTS Tracks_idx2 ON Tracks (Preset, TrackSeq)');
    }

    function InsertQueueStmt()
    {
	if ($this->insertQueueStmt == 0)
	    $this->insertQueueStmt = $this->prepare('INSERT INTO Queue (LinnId, AlbumKey, Preset, TrackSeq, URL, XML) VALUES (:LinnId, :AlbumKey, :Preset, :TrackSeq, :URL, :XML)');

	return $this->insertQueueStmt;
    }

    function UpdateQueueStmt()
    {
	if ($this->updateQueueStmt == 0)
	    $this->updateQueueStmt = $this->prepare('UPDATE Queue set LinnId = :LinnId where (LinnId == :LinnId) OR (LinnId == -1 and URL == :URL)');

	return $this->updateQueueStmt;
    }

    function DeleteQueueStmt()
    {
	if ($this->deleteQueueStmt == 0)
	    $this->deleteQueueStmt = $this->prepare('DELETE FROM Queue');

	return $this->deleteQueueStmt;
    }


    function InsertStateStmt()
    {
	if ($this->insertStateStmt == 0)
	    $this->insertStateStmt = $this->prepare('INSERT INTO State (Id, Value) VALUES (:Id, :Value)');
	return $this->insertStateStmt;
    }

    function UpdateStateStmt()
    {
	if ($this->updateStateStmt == 0)
	    $this->updateStateStmt = $this->prepare('UPDATE State set Value = :Value WHERE Id = :Id');

	return $this->updateStateStmt;
    }

    function InsertSequenceStmt()
    {
	if ($this->insertSequenceStmt == 0)
	    $this->insertSequenceStmt = $this->prepare('INSERT INTO Sequence (Seq, LinnId) VALUES (:Seq, :LinnId)');

	return $this->insertSequenceStmt;
    }

    function DeleteSequenceStmt()
    {
	if ($this->deleteSequenceStmt == 0)
	    $this->deleteSequenceStmt = $this->prepare('DELETE FROM Sequence');

	return $this->deleteSequenceStmt;
    }

    function NumberOfTracksStmt()
    {
	if ($this->numberOfTracksStmt == 0)
	    $this->numberOfTracksStmt = $this->prepare('SELECT NoTracks FROM Album WHERE Preset == :q1');

	return $this->numberOfTracksStmt;
    }

    function PresetURLStmt()
    {
	if ($this->presetURLStmt == 0)
	    $this->presetURLStmt = $this->prepare('SELECT URI FROM Album WHERE Preset == :q1');

	return $this->presetURLStmt;
    }

    function CheckURLExistStmt()
    {
	if ($this->CheckURLExistStmt == 0)
	    $this->CheckURLExistStmt = $this->prepare('SELECT rowid FROM Album WHERE URI == :q1 LIMIT 1');
	    //$this->CheckURLExistStmt = $this->prepare('SELECT EXISTS(SELECT rowid FROM Album WHERE URI == :q1 LIMIT 1)');

	return $this->CheckURLExistStmt;
    }

    function InsertAlbumStmt()
    {
	if ($this->insertAlbumStmt == 0)
	    $this->insertAlbumStmt = $this->prepare('INSERT INTO Album (Key, Preset, NoTracks, URI, ArtistFirst, SortArtist, Artist, Album, Date, Genre, MusicTime, ImageURI, TopDirectory, RootMenuNo) VALUES  (:Key, :Preset, :NoTracks, :URI, :ArtistFirst, :SortArtist, :Artist, :Album, :Date, :Genre, :MusicTime, :ImageURI, :TopDirectory, :RootMenuNo)');

	return $this->insertAlbumStmt;
    }

    function InsertTracksStmt()
    {
	if ($this->insertTracksStmt == 0)
	    $this->insertTracksStmt = $this->prepare('INSERT INTO Tracks (AlbumKey, Preset, TrackSeq, URL, Duration, Title, Year, AlbumArt, ArtWork, Genre, ArtistPerformer, ArtistComposer, ArtistAlbumArtist, ArtistConductor, Album, TrackNumber, DiscNumber, DiscCount, BitRate, SampleFrequency, BitsPerSample, Size) VALUES  (:AlbumKey, :Preset, :TrackSeq, :URL, :Duration, :Title, :Year, :AlbumArt, :ArtWork, :Genre, :ArtistPerformer, :ArtistComposer, :ArtistAlbumArtist, :ArtistConductor, :Album, :TrackNumber, :DiscNumber, :DiscCount, :BitRate, :SampleFrequency, :BitsPerSample, :Size)');

	return $this->insertTracksStmt;
    }

    public function InsertQueue($LinnId, $AlbumKey, $Preset, $TrackSeq, $URL, $XML)
    {
	$this->InsertQueueStmt()->bindParam(':LinnId', $LinnId);
	$this->InsertQueueStmt()->bindParam(':AlbumKey', $AlbumKey);
	$this->InsertQueueStmt()->bindParam(':Preset', $Preset);
	$this->InsertQueueStmt()->bindParam(':TrackSeq', $TrackSeq);
	$this->InsertQueueStmt()->bindParam(':URL', $URL);
	$this->InsertQueueStmt()->bindParam(':XML', $XML);

	$result = $this->InsertQueueStmt()->execute();

	$r = $this->changes();
	LogWrite("InsertQueue: $LinnId, $AlbumKey, $Preset, $TrackSeq, $URL -> $r");
	$this->InsertQueueStmt()->reset();
    }

    public function UpdateQueue($LinnId, $AlbumKey, $Preset, $TrackSeq, $URL, $XML)
    {
	$this->UpdateQueueStmt()->bindParam(':LinnId', $LinnId);
	$this->UpdateQueueStmt()->bindParam(':URL', $URL);

	$result = $this->UpdateQueueStmt()->execute();

	$r = $this->changes();
	LogWrite("UpdateQueue: $LinnId, $AlbumKey, $Preset, $TrackSeq, $URL -> $r");
	if ($this->changes() < 1)
	{
	    $this->InsertQueue($LinnId, $AlbumKey, $Preset, $TrackSeq, $URL, $XML);
	}

	$this->UpdateQueueStmt()->reset();
    }

    public function DeleteQueue()
    {
	$result = $this->DeleteQueueStmt()->execute();

	$r = $this->changes();
	LogWrite("DeleteQueue: -> $r");

	$this->DeleteQueueStmt()->reset();
    }

    public function SetState($Id, $Value)
    {
	$this->UpdateStateStmt()->bindParam(':Id', $Id);
	$this->UpdateStateStmt()->bindParam(':Value', $Value);

	$result = $this->UpdateStateStmt()->execute();

	$r = $this->changes();
	LogWrite("SetState: $Id, $Value -> $r");
	if ($this->changes() < 1)
	{
	    $this->InsertStateStmt()->bindParam(':Id', $Id);
	    $this->InsertStateStmt()->bindParam(':Value', $Value);

	    $result = $this->InsertStateStmt()->execute();

	    $r = $this->changes();
	    LogWrite("SetState-Insert: $Id, $Value -> $r");
	    $this->InsertStateStmt()->reset();
	}

	$this->UpdateStateStmt()->reset();
    }

    public function InsertSequence($Seq, $LinnId)
    {
	$this->InsertSequenceStmt()->bindParam(':Seq', $Seq);
	$this->InsertSequenceStmt()->bindParam(':LinnId', $LinnId);

	$result = $this->InsertSequenceStmt()->execute();

	$r = $this->changes();
	LogWrite("InsertSequence: $Seq, $LinnId -> $r");
	$this->InsertSequenceStmt()->reset();
    }

    public function DeleteSequence()
    {
	$result = $this->DeleteSequenceStmt()->execute();

	$r = $this->changes();
	LogWrite("DeleteSequence: -> $r");

	$this->DeleteSequenceStmt()->reset();
    }

    public function NumberOfTracks($Preset)
    {
	$this->NumberOfTracksStmt()->bindValue(":q1", $Preset);

	$result = $this->NumberOfTracksStmt()->execute();

	$R = array();
	$i = 0;

	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = $row;
	    $i++;
	}

	$this->NumberOfTracksStmt()->reset();

	return $R[0][NoTracks];
    }

    public function PresetURL($preset)
    {
	$this->PresetURLStmt()->bindValue(":q1", $preset);

	$result = $this->PresetURLStmt()->execute();

	$R = array();
	$i = 0;

	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = $row;
	    $i++;
	}

	$this->PresetURLStmt()->reset();

	return AbsolutePath(ProtectPath($R[0][URI]));
    }

    public function CheckURLExist($uri)
    {
	$this->CheckURLExistStmt()->bindValue(":q1", $uri);

	$result = $this->CheckURLExistStmt()->execute();

	$r = $result->fetchArray(SQLITE3_ASSOC);
	//print_r($r);

	if (!empty($r))
	    $Res = $r[Preset];
	else
	    $Res = false;

	$this->CheckURLExistStmt()->reset();

	return $Res;
    }

    public function InsertAlbum($Key, $Preset, $NoTracks, $URI, $ArtistFirst, $Artist, $SortArtist, 
	$Album, $Date, $Genre, $MusicTime, $ImageURI, $TopDirectory, $RootMenuNo)
    {
	$this->InsertAlbumStmt()->bindParam(':Key', $Key);
	$this->InsertAlbumStmt()->bindParam(':Preset', $Preset);
	$this->InsertAlbumStmt()->bindParam(':NoTracks', $NoTracks);
	$this->InsertAlbumStmt()->bindParam(':URI', $URI);
	$this->InsertAlbumStmt()->bindParam(':ArtistFirst', $ArtistFirst);
	$this->InsertAlbumStmt()->bindParam(':Artist', $Artist);
	$this->InsertAlbumStmt()->bindParam(':SortArtist', $SortArtist);
	$this->InsertAlbumStmt()->bindParam(':Album', $Album);
	$this->InsertAlbumStmt()->bindParam(':Date', $Date);
	$this->InsertAlbumStmt()->bindParam(':Genre', $Genre);
	$this->InsertAlbumStmt()->bindParam(':MusicTime', $MusicTime);
	$this->InsertAlbumStmt()->bindParam(':ImageURI', $ImageURI);
	$this->InsertAlbumStmt()->bindParam(':TopDirectory', $TopDirectory);
	$this->InsertAlbumStmt()->bindParam(':RootMenuNo', $RootMenuNo);

	$result = $this->InsertAlbumStmt()->execute();

	$rowid = $this->lastInsertRowID();

	$this->InsertAlbumStmt()->reset();

	return $rowid;
    }

    public function InsertTracks($AlbumKey, $Preset, $TrackSeq, $URL, $DURATION, $TITLE, $YEAR, 
	$AlbumArt, $ArtWork, $Genre, $Artist_Performer, $Artist_Composer, 
	$Artist_AlbumArtist, $Artist_Conductor, $ALBUM, $TRACK_NUMBER, 
	$DISC_NUMBER, $DISC_COUNT, $BITRATE, $SAMPLE_FREQUENCY, $BITS_PER_SAMPLE, $SIZE)
    {
	$this->InsertTracksStmt()->bindParam(':AlbumKey', $AlbumKey);
	$this->InsertTracksStmt()->bindParam(':Preset', $Preset);
	$this->InsertTracksStmt()->bindParam(':TrackSeq', $TrackSeq);
	$this->InsertTracksStmt()->bindParam(':URL', $URL);
	$this->InsertTracksStmt()->bindParam(':Duration', $DURATION);
	$this->InsertTracksStmt()->bindParam(':Title', $TITLE);
	$this->InsertTracksStmt()->bindParam(':Year', $YEAR);
	$this->InsertTracksStmt()->bindParam(':AlbumArt', $AlbumArt);
	$this->InsertTracksStmt()->bindParam(':ArtWork', $ArtWork);
	$this->InsertTracksStmt()->bindParam(':Genre', $Genre);
	$this->InsertTracksStmt()->bindParam(':ArtistPerformer', $Artist_Performer);
	$this->InsertTracksStmt()->bindParam(':ArtistComposer', $Artist_Composer);
	$this->InsertTracksStmt()->bindParam(':ArtistAlbumArtist', $Artist_AlbumArtist);
	$this->InsertTracksStmt()->bindParam(':ArtistConductor', $Artist_Conductor);
	$this->InsertTracksStmt()->bindParam(':Album', $ALBUM);
	$this->InsertTracksStmt()->bindParam(':TrackNumber', $TRACK_NUMBER);
	$this->InsertTracksStmt()->bindParam(':DiscNumber', $DISC_NUMBER);
	$this->InsertTracksStmt()->bindParam(':DiscCount', $DISC_COUNT);
	$this->InsertTracksStmt()->bindParam(':BitRate', $BITRATE);
	$this->InsertTracksStmt()->bindParam(':SampleFrequency', $SAMPLE_FREQUENCY);
	$this->InsertTracksStmt()->bindParam(':BitsPerSample', $BITS_PER_SAMPLE);
	$this->InsertTracksStmt()->bindParam(':Size', $SIZE);

	$result = $this->InsertTracksStmt()->execute();

	$rowid = $this->lastInsertRowID();

	$this->InsertTracksStmt()->reset();

	return $rowid;
    }

    public function NumberOfAlbumsInMenuNo($MenuNo)
    {
	static $A = array();

	if (empty($A))
	{
	    $Stmt = $this->prepare('select RootMenuNo, count(RootMenuNo) from Album group by RootMenuNo');
	    $result = $Stmt->execute();

	    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		$A[$row[RootMenuNo]] = $row["count(RootMenuNo)"];
	    }

	    //print_r($A);
	}

	return $A[$MenuNo];
    }

    public function MaxPreset() {
	$result = $this->prepare('SELECT MAX(Preset) as max from Album')->execute()->fetchArray();
	//print_r($result);
	return $result['max'];
    }


    // ########## Query's ###################

    public function QueryAlbum($preset)
    {
	$Stmt = $this->prepare("SELECT * FROM Tracks WHERE Preset == :q1 order by TrackSeq");

	$Stmt->bindValue(":q1", $preset);

	$result = $Stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = AbsoluteURL($row);
	    $i++;
	}

	$Stmt->close();

	//print_r($R);
	return $R;
	//return json_encode($R);
    }

    public function QueryAlbumList($menu, $artistfirst)
    {
	LogWrite("QueryAlbum: $menu, $artistfirst");
	if ($artistfirst != "*") {
	    $SelStmt = "SELECT * FROM Album WHERE RootMenuNo == :q1 and ArtistFirst == :q2 order by SortArtist, Date, Album";
	}
	else
	{
	    $SelStmt = "SELECT * FROM Album WHERE RootMenuNo == :q1 order by SortArtist, Date, Album";
	}

	$Stmt = $this->prepare($SelStmt);

	$Stmt->bindValue(":q1", $menu);
	if ($artistfirst != "*") {
	    $Stmt->bindValue(":q2", $artistfirst);
	}

	$result = $Stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = AbsoluteURL($row);
	    $i++;
	}

	$Stmt->close();

	//print_r($R);
	return $R;
	//return json_encode($R);
    }

    public function QueryNewest()
    {
	$SelStmt = "SELECT * FROM Album order by MusicTime DESC Limit 50";

	$Stmt = $this->prepare($SelStmt);

	$result = $Stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = AbsoluteURL($row);
	    $i++;
	}

	$Stmt->close();

	//print_r($R);
	return $R;
	//return json_encode($R);
    }

    public function QueryAlphabetPresent($menu)
    {
	global $ALPHABET_SIZE;
	global $ALPHABET;

        $SelStmt = "SELECT distinct ArtistFirst FROM Album WHERE RootMenuNo == :q1 order by ArtistFirst";

	$Stmt = $this->prepare($SelStmt);

	$Stmt->bindValue(":q1", $menu);

	$result = $Stmt->execute();

	$R = array();

	for ($alpha = 0; $alpha < $ALPHABET_SIZE; $alpha++)
	{
	    $Letter = $ALPHABET[$alpha];
	    echo $Letter;
	    if ($Letter == "#")
		$R["NUM"] = 0;
	    else
		$R[$Letter] = 0;
	}

	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    if ($row[ArtistFirst] == "#")
		$R["NUM"] = 1;
	    else
	    $R[$row[ArtistFirst]] = 1;
	    $i++;
	}

	$Stmt->close();

	//print_r($R);
	return $R;
	//return json_encode($R);
    }

    private function QuearySearch_BuildSelect($words, $mode)
    {
	$first = true;
	if ($mode == SINGLE_TRACKS_ONLY)
	{
	    $SelectStmt = "SELECT 'Track' as Type, * FROM Tracks WHERE ";
	    // Dont take those where artist and album matches
	    $SelectStmt .= " (Preset NOT IN (" . $this->QuearySearch_BuildSelect($words, 2) . "))";
	    $first = false;
	}
	if ($mode == ALBUMS_ONLY)
	{
	    $SelectStmt = "SELECT 'Album' as Type, * FROM Tracks WHERE ";
	    $SelectStmt .= "(TrackSeq = 1)"; // Take first track as representative for the album
	    $first = false;
	}
	if ($mode == ALBUM_PRESET_ONLY)
	{
	    $SelectStmt = "SELECT Preset FROM Tracks WHERE ";
	    $SelectStmt .= "(TrackSeq = 1)"; // Take first track as representative for the album
	    $first = false;
	}

	foreach ($words as $key => $value) {
	    //echo "key: $key, value: $value\n";

	    if ($mode == SINGLE_TRACKS_ONLY)
		$Add = "(Title LIKE :q$key OR Album LIKE :q$key OR ArtistPerformer LIKE :q$key OR ArtistAlbumArtist LIKE :q$key)";
	    else
		$Add = "(Album LIKE :q$key OR ArtistPerformer LIKE :q$key OR ArtistAlbumArtist LIKE :q$key)";

	    if ($first) {
		$SelectStmt .= "$Add";
	    }
	    else
	    {
		$SelectStmt .= " AND " . "$Add";
	    }
	    $first = false;
	}

	//echo "QuearySearch_BuildSelect: $SelectStmt\n";

	return $SelectStmt;
    }

    public function QuerySearch($search)
    {
	//$search = "cougar america";
	//$search = "yes land";
	//$search = "look in";

	// $search is a space seperated list of words... We split in words and search for them individually.

	$words = explode(" ", $search);


	$SelectStmtAlbum = $this->QuearySearch_BuildSelect($words, ALBUMS_ONLY);
	$SelectStmt = $this->QuearySearch_BuildSelect($words, SINGLE_TRACKS_ONLY);
	//echo "$SelectStmt\n";

	$stmtAlbum = $this->prepare($SelectStmtAlbum);
	$stmt = $this->prepare($SelectStmt);

	foreach ($words as $key => $value) {
	    //echo "key: $key, value: $value\n";

	    $stmtAlbum->bindValue(":q$key", "%$value%");
	    $stmt->bindValue(":q$key", "%$value%");
	}

	$resultAlbum = $stmtAlbum->execute();
	$result = $stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $resultAlbum->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = AbsoluteURL($row);
	    $i++;
	}
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = AbsoluteURL($row);
	    $i++;
	}

	$stmtAlbum->close();
	$stmt->close();


	print_r($R);
	return $R;
	//return json_encode($R);
    }

    public function QueryPlayingNow($revno)
    {
	$Queue = array();
	$Queue[0] = array();

	$Stmt = $this->prepare("SELECT MAX(Seq) As MaxSeq, MAX(LinnId) AS MaxLinnId FROM Sequence");
	$result = $Stmt->execute();

	while ($row = $result->fetchArray(SQLITE3_ASSOC)) 
	{
	    $Queue[0][MaxSeq] = $row[MaxSeq];
	    $Queue[0][MaxLinnId] = $row[MaxLinnId];
	}
	$Stmt->close();

	$Stmt = $this->prepare("SELECT * FROM State");
	$result = $Stmt->execute();

	while ($row = $result->fetchArray(SQLITE3_ASSOC)) 
	{
	    $Queue[0][$row[Id]] = $row[Value];
	}
	$Stmt->close();

	if ($revno == -1 || $Queue[0][RevNo] != $revno)
	{
	    $QueueStmt = $this->prepare("SELECT S.Seq-L.Seq AS PlayState, Q.LinnId AS LinnId, S.Seq AS Seq, T.* FROM Queue Q, Sequence S, Tracks T, (SELECT S2.Seq FROM Sequence S2, (SELECT ST.value FROM State ST where ST.Id == 'LinnId') L2 WHERE S2.LinnId == L2.value) L WHERE Q.LinnId == S.LinnId AND Q.Preset == T.Preset AND Q.TrackSeq == T.TrackSeq ORDER BY S.Seq");


	    $result = $QueueStmt->execute();

	    $QueueCount = 1;
	    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
		if ($row[PlayState] < 0)
		    $row[PlayState] = "Played";
		else if ($row[PlayState] > 0)
		    $row[PlayState] = "Pending";
		else
		    $row[PlayState] = "Playing";
		$Queue[$QueueCount] = AbsoluteURL($row);
		$QueueCount++;
	    }

	    $QueueStmt->close();
	}

	print_r($Queue);
	return $Queue;
	//return json_encode($Queue);
    }

}


function test()
{
    global $NL;

    $musicDB = new MusicDB();

    $musicDB->SetState("State1", "Value1");
    $musicDB->SetState("State2", "Value1");
    echo $musicDB->NumberOfAlbumsInMenuNo(0) . $NL;
    echo $musicDB->NumberOfAlbumsInMenuNo(1) . $NL;
    echo $musicDB->NumberOfAlbumsInMenuNo(2) . $NL;
    echo $musicDB->NumberOfAlbumsInMenuNo(3) . $NL;

    $musicDB->close();
}
//test();
//test();
?>

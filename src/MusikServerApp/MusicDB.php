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

use MusikServerApp\ServerState;

define("SINGLE_TRACKS_ONLY", 0);
define("ALBUMS_ONLY", 1);
define("ALBUM_PRESET_ONLY", 2);

class MusicDB extends \SQLite3
{
    protected $serverState;

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

    private static $DataBaseFileName = null;

    function __construct()
    {
    }

    public static function create($DatabaseFileName)
    {
	$instance = new self();

	self::$DataBaseFileName = $DatabaseFileName;

	if (self::$DataBaseFileName === null) 
	    die("MusicDB::create: Database file name not given!");

	$instance->docreate(self::$DataBaseFileName);

	return $instance;
    }

    public static function connect()
    {
	$instance = new self();

	if (self::$DataBaseFileName === null) 
	    die("MusicDB::connect: Database file name not given!");

	$instance->doconnect(self::$DataBaseFileName);

	return $instance;
    }

    protected function docreate($DataBaseFileName)
    {

	$this->open($DataBaseFileName, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	$this->CreateTables();
    }

    protected function doconnect($DataBaseFileName)
    {
	$this->open($DataBaseFileName, SQLITE3_OPEN_READWRITE);
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


	// Create indexes
	$this->exec('CREATE INDEX IF NOT EXISTS Album_idx1 ON Album (Key)');
	$this->exec('CREATE INDEX IF NOT EXISTS Album_idx2 ON Album (Preset)');
	$this->exec('CREATE UNIQUE INDEX IF NOT EXISTS Album_idx3 ON Album (URI)');
	$this->exec('CREATE INDEX IF NOT EXISTS Tracks_idx1 ON Tracks (AlbumKey, TrackSeq)');
	$this->exec('CREATE INDEX IF NOT EXISTS Tracks_idx2 ON Tracks (Preset, TrackSeq)');
    }


    public function CreateNewSpritesTable()
    {
	$this->exec('DROP TABLE IF EXISTS Sprites');
	$this->exec('CREATE TABLE Sprites (Preset INTEGER)');

	$Stmt = $this->prepare('INSERT INTO Sprites SELECT Preset FROM Album ORDER BY RootMenuNo, ArtistFirst, SortArtist, Album');
	$result = $Stmt->execute();

	$Stmt->close();
    }

    public function QuerySprites()
    {
	$SelStmt = "SELECT Album.Preset, Sprites.RowId, Album.ImageURI FROM Album, Sprites WHERE Album.Preset = Sprites.Preset";

	$Stmt = $this->prepare($SelStmt);

	$result = $Stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = $row;
	    $i++;
	}

	$Stmt->close();

	//print_r($R);
	return $R;
	//return json_encode($R);
    }


    function NumberOfTracksStmt()
    {
	if ($this->numberOfTracksStmt === 0)
	    $this->numberOfTracksStmt = $this->prepare('SELECT NoTracks FROM Album WHERE Preset == :q1');

	return $this->numberOfTracksStmt;
    }

    function PresetURLStmt()
    {
	if ($this->presetURLStmt === 0)
	    $this->presetURLStmt = $this->prepare('SELECT URI FROM Album WHERE Preset == :q1');

	return $this->presetURLStmt;
    }

    function CheckURLExistStmt()
    {
	if ($this->CheckURLExistStmt === 0)
	    $this->CheckURLExistStmt = $this->prepare('SELECT preset FROM Album WHERE URI == :q1 LIMIT 1');

	return $this->CheckURLExistStmt;
    }

    function InsertAlbumStmt()
    {
	if ($this->insertAlbumStmt === 0)
	    $this->insertAlbumStmt = $this->prepare('INSERT INTO Album (Key, NoTracks, URI, ArtistFirst, SortArtist, Artist, Album, Date, Genre, MusicTime, ImageURI, TopDirectory, RootMenuNo) VALUES  (:Key, :NoTracks, :URI, :ArtistFirst, :SortArtist, :Artist, :Album, :Date, :Genre, :MusicTime, :ImageURI, :TopDirectory, :RootMenuNo)');

	return $this->insertAlbumStmt;
    }

    function InsertTracksStmt()
    {
	if ($this->insertTracksStmt === 0)
	    $this->insertTracksStmt = $this->prepare('INSERT INTO Tracks (AlbumKey, Preset, TrackSeq, URL, Duration, Title, Year, AlbumArt, ArtWork, Genre, ArtistPerformer, ArtistComposer, ArtistAlbumArtist, ArtistConductor, Album, TrackNumber, DiscNumber, DiscCount, BitRate, SampleFrequency, BitsPerSample, Size) VALUES  (:AlbumKey, :Preset, :TrackSeq, :URL, :Duration, :Title, :Year, :AlbumArt, :ArtWork, :Genre, :ArtistPerformer, :ArtistComposer, :ArtistAlbumArtist, :ArtistConductor, :Album, :TrackNumber, :DiscNumber, :DiscCount, :BitRate, :SampleFrequency, :BitsPerSample, :Size)');

	return $this->insertTracksStmt;
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
	    $Res = $r['Preset'];
	else
	    $Res = false;

	$this->CheckURLExistStmt()->reset();

	return $Res;
    }

    public function InsertAlbum($Key, $Preset, $NoTracks, $URI, $ArtistFirst, $Artist, $SortArtist, 
	$Album, $Date, $Genre, $MusicTime, $ImageURI, $TopDirectory, $RootMenuNo)
    {
	$this->InsertAlbumStmt()->bindParam(':Key', $Key);
	//$this->InsertAlbumStmt()->bindParam(':Preset', $Preset);
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
		$A[$row["RootMenuNo"]] = $row["count(RootMenuNo)"];
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

    public function QueryRandomTracks($RootMenuNo, $Limit)
    {
	$SelStmt = "SELECT Preset, NoTracks FROM Album where RootMenuNo == $RootMenuNo order by Random() Limit $Limit";

	$Stmt = $this->prepare($SelStmt);

	$result = $Stmt->execute();

	$R = array();
	$i = 0;
	// fetchArray(SQLITE3_NUM | SQLITE_ASSOC | SQLITE_BOTH) - default both
	while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	    $R[$i] = $row;
	    $i++;
	}

	for ($i = 0; $i < count($R); $i++) {
	    $R[$i]['RandomTrack'] = rand(1, $R[$i]['NoTracks']);
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

	//echo "QuerySearch_BuildSelect: $SelectStmt\n";

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


	//print_r($R);
	return $R;
	//return json_encode($R);
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

function testQueryRandomTracks()
{
    global $NL;

    $musicDB = MusicDB::create("LinnDS-jukebox.db");

    $A = $musicDB->QueryRandomTracks(0, 10);

    print_r($A);


}

//test();
//test();
//testQueryRandomTracks();
?>

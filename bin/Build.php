<?php
//#!/usr/bin/php
/*!
* LinnDS-jukebox
*
* Copyright (c) 2012-2016 Henrik Tolbøl, http://tolbøl.dk
*
* Licensed under the MIT license:
* http://www.opensource.org/licenses/mit-license.php
*/

use MusikBuild\DIDLAlbum;
use MusikBuild\Playlist;
use MusikServerApp\MusicDB;

echo "bin/Build.php: __DIR__=" . dirname(__DIR__) . "\n";

require dirname(__DIR__) . '/vendor/autoload.php';

require_once(dirname(__DIR__) . "/src/MusikServerApp/setup.php");

require_once(dirname(__DIR__) . "/src/MusikBuild/tracks.php");
require_once(dirname(__DIR__) . "/src/MusikBuild/FileUtils.php");


function Make_Tracks(&$didl, &$musicDB)
{
    return Tracks($musicDB, AbsoluteBuildPath($didl->PlaylistFileName()), $didl->Key(), $didl->SequenceNo());
}


function Make_Album(&$didl, &$musicDB)
{
    $rowid = $musicDB->InsertAlbum($didl->Key(), -47, $didl->NoTracks(), $didl->URI(), 
		    $didl->ArtistFirst(), $didl->Artist(), $didl->SortArtist(), 
		    $didl->Album(), $didl->Date(), $didl->Genre(), $didl->MusicTime(), 
		    $didl->ImageURI(), $didl->TopDirectory(), $didl->RootMenuNo());

    $didl->setSequenceNo($rowid);
    return $rowid;
}

function CreateAllGreyImgs($MaxPreset)
{
    global $AppDir;

    for ($i = 1; $i <= $MaxPreset; $i++)
    {
	$newfile = sprintf($AppDir . "folder/80x80_%04d.jpg", $i);
	copy("images/grey.jpg", $newfile);

	$newfile = sprintf($AppDir . "folder/160x160_%04d.jpg", $i);
	copy("images/grey.jpg", $newfile);
    }
}

function CollectFolderImgs(&$didl)
{
    global $NL;
    global $AppDir;

    $img = AbsoluteBuildPath($didl->ImageFileName());
    
    if (strlen($img) <= 4 || !file_exists($img))
    {
	$newfile = sprintf($AppDir . "folder/80x80_%04d.jpg", $didl->SequenceNo());
	copy("images/grey.jpg", $newfile);

	$newfile = sprintf($AppDir . "folder/160x160_%04d.jpg", $didl->SequenceNo());
	copy("images/grey.jpg", $newfile);
	return;
    }
    if (!file_exists(dirname($img) . "/80x80.jpg"))
    {
        $cmd  = 'convert  "' . $img . '" -thumbnail 80x80 +profile "*" "' . dirname($img) . '/80x80.jpg"';
	echo $NL . $cmd . $NL;
	shell_exec($cmd);
    }
    if (!file_exists(dirname($img) . "/160x160.jpg"))
    {
        $cmd  = 'convert  "' . $img . '" -thumbnail 160x160 +profile "*" "' . dirname($img) . '/160x160.jpg"';
	echo $NL . $cmd . $NL;
	shell_exec($cmd);
    }
    $newfile = sprintf($AppDir . "folder/80x80_%04d.jpg", $didl->SequenceNo());
    copy(dirname($img) . "/80x80.jpg", $newfile);
    $newfile = sprintf($AppDir . "folder/160x160_%04d.jpg", $didl->SequenceNo());
    copy(dirname($img) . "/160x160.jpg", $newfile);
}

function Make_CSS($MaxPreset, $CSS1, $CSS2)
{
    global $NL;
    global $AppDir;
    global $NEWEST_COUNT;

    $ImgSize = 80;
    $TileW = 10;
    $TileH = 10;

    $SpriteW = $ImgSize * $TileW + 2 * $TileW;
    $SpriteH = $ImgSize * $TileH + 2 * $TileH;

    // On an ipad somehow the size of a sprite image should be < 1024 pixels 
    // wide / high - otherwise the display of sprite elements are distorted.

    $cmd1 = "montage -background transparent -tile " . $TileW . "x" . $TileH . " -geometry 80x80+1+1 " . $AppDir . "folder/80x80_* " . $AppDir . "sprites/sprite.jpg";
    echo $cmd1 . $NL;
    $cmd2 = "montage -background transparent -tile " . $TileW . "x" . $TileH . " -geometry 160x160+1+1 " . $AppDir . "folder/160x160_* " . $AppDir . "sprites/sprite@2x.jpg";

    shell_exec($cmd1);
    echo $cmd2 . $NL;
    shell_exec($cmd2);

    $css = "";
    $cnt = 0;
    for ($k = 0; $cnt < $MaxPreset; $k++)
    {
	for ($i = 0; $i < $TileW && $cnt < $MaxPreset; $i++)
	{
	    for ($j = 0; $j < $TileH && $cnt < $MaxPreset; $j++)
	    {
		$cnt++;
		$posx = -1 * ($i * $ImgSize + $i*2 +1);
		$posy = -1 * ($j * $ImgSize + $j*2 +1);
		$css .= ".sprite_" . $cnt . $NL;
		$css .= "{\n";
		$css .= "   width: " . $ImgSize . "px;\n";
		$css .= "   height: " . $ImgSize . "px;\n";
		$css .= "   background: url(sprite-" . $k . ".jpg) no-repeat top left;\n";
		$css .= "   background-position: " . $posy . "px " . $posx . "px;\n";
		$css .= "}\n";
	    }
	}
    }

    file_put_contents($CSS1, $css);

    $css = "";
    $cnt = 0;
    for ($k = 0; $cnt < $MaxPreset; $k++)
    {
	for ($i = 0; $i < $TileW && $cnt < $MaxPreset; $i++)
	{
	    for ($j = 0; $j < $TileH && $cnt < $MaxPreset; $j++)
	    {
		$cnt++;
		$posx = -1 * ($i * $ImgSize + $i*2 +1);
		$posy = -1 * ($j * $ImgSize + $j*2 +1);
		$css .= ".sprite_" . $cnt . $NL;
		$css .= "{\n";
		$css .= "   width: " . $ImgSize . "px;\n";
		$css .= "   height: " . $ImgSize . "px;\n";
		$css .= "   background: url(sprite@2x-" . $k . ".jpg) no-repeat top left;\n";
		$css .= "   background-size: " . $SpriteW . "px " . $SpriteH . "px;\n";
		$css .= "   background-position: " . $posy . "px " . $posx . "px;\n";
		$css .= "}\n";
	    }
	}
    }

    file_put_contents($CSS2, $css);
}


// ########## Main  #######################################################################

function Main($DoLevel)
{
    global $NL;
    global $RootMenu;
    global $SubMenuType;
    global $TopDirectory;
    global $AppDir;
    global $DATABASE_FILENAME;

    $AppDir = "site/";

    $DATABASE_FILENAME = dirname(__DIR__) . "/LinnDS-jukebox.db";

    if (!file_exists($AppDir . "folder"))
	mkdir($AppDir . "folder");
    if (!file_exists($AppDir . "sprites"))
	mkdir($AppDir . "sprites");

    $NumNewPlaylists = 0;

    //Create a didl file in each directory containing music
    if ($DoLevel > 3) 
    {
	echo "Removing old .dpl files" . $NL;
	UnlinkDPL($TopDirectory);
    }
    
    echo "Making a didl file in each directory..." . $NL;
    $DirPlaylist = new Playlist();
    $NumNewPlaylists = $DirPlaylist->MakePlaylists($TopDirectory);
    echo " - found $NumNewPlaylists new playlists" . $NL;

    //unlink($DATABASE_FILENAME);
    $musicDB = MusicDB::create($DATABASE_FILENAME);

    echo "Find all didl files and add to Menu tree..." . $NL;
    // Find all didl files and add it to the menus
    try
    {
	//CreateAllGreyImgs($musicDB->MaxPreset());
	foreach ($TopDirectory as $Dir => $RootMenuNo)
	{
	    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($Dir));
	    while($it->valid())
	    {
		if($it->isFile())
		{
		    $ext = pathinfo($it->current(), PATHINFO_EXTENSION);

		    if ($ext == "xml")
		    {
			$didl = new DIDLAlbum($it->getPathName(), $RootMenuNo);
			$rowid = $musicDB->CheckURLExist($didl->URI());
			if ($rowid === false)
			{
			    $rowid = Make_Album($didl, $musicDB);
			    Make_Tracks($didl, $musicDB);
			    //$didl->dump();
			}
			else
			{
			    $didl->setSequenceNo($rowid);
			}

			CollectFolderImgs($didl);
			echo ".";
		    }
		}
		$it->next();
	    }
	}
    }
    catch(Exception $e)
    {
	echo $e->getMessage();
    }

    copy("www/index.html", $AppDir . "index.html");
    //copy("html_parts.php", $AppDir . "html_parts.php");
    copy("www/actions.js", $AppDir . "actions.js");
    copy("www/musik.css", $AppDir . "musik.css");
    //copy("LinnDS-jukebox-daemon.php", $AppDir . "LinnDS-jukebox-daemon.php");
    //copy("ServerState.php", $AppDir . "ServerState.php");
    //copy("LPECClientSocket.php", $AppDir . "LPECClientSocket.php");
    //copy("LinnDSClientSocket.php", $AppDir . "LinnDSClientSocket.php");
    //copy("StringUtils.php", $AppDir . "StringUtils.php");
    //copy("SocketServer.php", $AppDir . "SocketServer.php");
    //copy("websockets.php", $AppDir . "websockets.php");
    //copy("wsListeningSocket.php", $AppDir . "wsListeningSocket.php");
    //copy("users.php", $AppDir . "users.php");
//
    ////copy("LinnDS-jukebox-daemon-old.php", $AppDir . "LinnDS-jukebox-daemon-old.php");
    //copy("S98linn_lpec", $AppDir . "S98linn_lpec");
    copy("images/Transparent.gif", $AppDir . "Transparent.gif");
    //copy("setup.php", $AppDir . "setup.php");
    //copy("Send.php", $AppDir . "Send.php");
    //copy("MusicDB.php", $AppDir . "MusicDB.php");
    //copy("QueryAlbum.php", $AppDir . "QueryAlbum.php");
    //copy("QueryAlbumList.php", $AppDir . "QueryAlbumList.php");
    //copy("QueryAlphabetPresent.php", $AppDir . "QueryAlphabetPresent.php");
    //copy("QueryDB.php", $AppDir . "QueryDB.php");
    //copy("QueryPlayingNowDB.php", $AppDir . "QueryPlayingNowDB.php");

    echo $NL . "Making sprites and css file in " . $AppDir . $NL;
    Make_CSS($musicDB->MaxPreset(), $AppDir . "sprites/sprites.css", $AppDir . "sprites/sprites@2x.css");

    $musicDB->close();
    //copy($DATABASE_FILENAME, $AppDir . $DATABASE_FILENAME);

    echo "Finished..." . $NL;
}

//Main(1);
Main(1);

?>

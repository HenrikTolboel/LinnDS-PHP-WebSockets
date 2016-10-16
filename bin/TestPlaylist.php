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

//use MusikBuild\DIDLAlbum;
use MusikBuild\Playlist;
//use MusikServerApp\MusicDB;

echo mb_internal_encoding() . "\n";
echo "bin/TestPlaylist.php: __DIR__=" . dirname(__DIR__) . "\n";

require dirname(__DIR__) . '/vendor/autoload.php';

require_once(dirname(__DIR__) . "/src/MusikServerApp/setup.php");


    $DirPlaylist = new Playlist();

    $DirPlaylist->PlaylistDir(dirname(__DIR__) . "/test_music");


?>

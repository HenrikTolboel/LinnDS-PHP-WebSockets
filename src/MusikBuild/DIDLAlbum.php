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

namespace MusikBuild;

//require_once("setup.php");
//require_once("tracks.php");


class DIDLAlbum 
{
    private static $NoInstances = 0;
    private $Value = array();

    public function __construct($FileName, $RootMenuNo = -1)
    {
	self::$NoInstances++;
	$this->Value['InstanceNo'] = self::$NoInstances;
	$this->Value['File'] = new \SplFileInfo($FileName);

	$xml = simplexml_load_file(ProtectPath($this->Value['File']->getPathname()));

	foreach ($xml->children() as $info) {
	    $this->Value[$info->getName()]  = (string) $info;
	}
	$this->Value['Path'] = explode("/", RelativePath($this->Value['Playlist']));
	
	$this->Value['RootMenuNo'] = $RootMenuNo;
	//print_r($this->Value);
    }

    public function PlaylistFileName()
    {
	return $this->Value['Playlist'];
    }

    public function ImageFileName()
    {
	return $this->Value['Art'];
    }

    public function TopDirectory()
    {
	return $this->Value['Path'][1];
    }

    public function RootMenuNo()
    {
	global $NL;
	global $TopDirectory;

	if ($this->Value['RootMenuNo'] != -1)
	    return $this->Value['RootMenuNo'];

	foreach ($TopDirectory as $Dir => $RootMenuNo)
	{
	    $RelDir = str_replace("LINN_JUKEBOX_URL/", "", RelativePath($Dir));
	    if ($RelDir == $this->TopDirectory())
		return $RootMenuNo;
	}
	return 0;
    }

    public function dump()
    {
	print_r($this->Value);
    }

    public function URI()
    {
	return RelativePath($this->PlaylistFileName());
    }

    public function ImageURI()
    {
	return RelativePath($this->ImageFileName());
    }

    public function Artist()
    {
	return $this->Value['AlbumArtist'];
    }
    public function ArtistFirst()
    {
	$F = strtoupper(substr($this->SortSkipWords($this->Artist()), 0, 1));

	if ($F >= "A" && $F <= "Z")
	    return $F;
	else
	    return "#";
    }
    public function SortArtist()
    {
	$SA = $this->SortSkipWords($this->Artist());

	return $SA;
    }
    public function Album()
    {
	return $this->Value['Album'];
    }
    public function Date()
    {
	return $this->Value['Date'];
    }
    public function Genre()
    {
	return $this->Value['Genre'];
    }

    public function MusicTime()
    {
	return (int)$this->Value['MusicTime'];
    }

    public function NoTracks()
    {
	return (int)$this->Value['NoTracks'];
    }

    public function Key()
    {
	$Key =  $this->Artist() . "+" . $this->Album() . "+" . $this->Date() . "+" . $this->Genre() . "+" . $this->Value['MusicTime'];
	return $Key;
    }

    private function SortSkipWords($Str)
    {
	global $SortSkipList;

	foreach ($SortSkipList as $w) 
	{
	    if (!strncmp($w, $Str, strlen($w))) 
	    {
		return substr($Str, strlen($w));
	    }
	}
	return $Str;
    }

    public function compare($a, $b) {
	$cmp = strcmp($a->SortSkipWords($a->Artist()), $b->SortSkipWords($b->Artist()));
	if ($cmp != 0)
	    return $cmp;

	$cmp = strcmp($a->Date(), $b->Date());

	if ($cmp != 0)
	    return $cmp;

	$cmp = strcmp($a->SortSkipWords($a->Album()), $b->SortSkipWords($b->Album()));

	return $cmp;
    }

    public function newest($a, $b) {
	$aMT = $a->MusicTime();
	$bMT = $b->MusicTime();

	if ($aMT < $bMT)
	    return 1;

	if ($aMT > $bMT)
	    return -1;

	return 0;
    }
}
?>

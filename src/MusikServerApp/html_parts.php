<?php
/*!
* LinnDS-jukebox
*
* Copyright (c) 2012-2015 Henrik Tolbøl, http://tolbøl.dk
*
* Licensed under the MIT license:
* http://www.opensource.org/licenses/mit-license.php
*/

require_once("setup.php");

function Body($musicDB)
{
    global $NL;

    $Cnt = $musicDB->NumberOfAlbumsInMenuNo(0);

    $html = <<<EOT
    <div data-role="page" data-dom-cache="true" id="musik">
	<div data-role="header" data-position="fixed">
	    <h1>Musik</h1>
EOT;
    $html .= KontrolPanel_button("musik");
    $html .= QueuePanel_button("musik");

    $html .= <<<EOT
	</div><!-- /header -->

	<div data-role="content">
	    <form class="ui-filterable">
		<input id="autocomplete-input" data-type="search" placeholder="Søg...">
	    </form>
	    <ul id="autocomplete" data-role="listview" data-inset="true" data-filter="true" data-input="#autocomplete-input"></ul>
EOT;

    $html .= MainMenuHtml($musicDB);
    $html .= playpopup_popup("musik");

    $html .= <<<EOT
	</div><!-- /content -->

	<div data-role="footer">
	    <h4>LinnDS-jukebox</h4>
	</div><!-- /footer -->
EOT;


    $html .= KontrolPanel_panel($musicDB, "musik");

    $html .= <<<EOT
    </div><!-- /page -->

    <div data-role="page" data-dom-cache="false" id="alphabet">
	<div data-role="header" data-position="fixed">
	    <h1 id="alphabet-title">Alphabet</h1>
EOT;
    $html .= KontrolPanel_button("alphabet");
    $html .= QueuePanel_button("alphabet");

    $html .= <<<EOT
	</div><!-- /header -->

	<div data-role="content">
EOT;
    $html .= AlphabetList("alphabet");
    $html .= <<<EOT
	</div><!-- /content -->

	<div data-role="footer">
	    <h4>LinnDS-jukebox</h4>
	</div><!-- /footer -->
EOT;

    $html .= KontrolPanel_panel($musicDB, "alphabet");

    $html .= <<<EOT
    </div><!-- /page -->

    <div data-role="page" data-dom-cache="false" id="albumlist">
	<div data-role="header" data-position="fixed">
	    <h1 id="albumlist-title">Kunstner / Album - A</h1>
EOT;
    $html .= KontrolPanel_button("albumlist");
    $html .= QueuePanel_button("albumlist");

    $html .= <<<EOT
	</div><!-- /header -->

	<div data-role="content">
	    <ul id="albumlist-list" data-role="listview" data-filter="false">
    <!--
		<li><a id="albumlist-1" class="playpopup" data-rel="popup" href="#" data-musik='{"popupid": "albumlist-popup", "preset": "1"}'><img class="sprite_1" src="Transparent.gif"/><h3>Artist</h3><p>Album (Year)</p></a><a href="#"></a></li>
    -->
	    </ul>
EOT;

    $html .= playpopup_popup("albumlist");

    $html .= <<<EOT
	</div><!-- /content -->

	<div data-role="footer">
	    <h4>LinnDS-jukebox</h4>
	</div><!-- /footer -->
EOT;


    $html .= KontrolPanel_panel($musicDB, "albumlist");

    $html .= <<<EOT
    </div><!-- /page -->


    <div data-role="page" data-dom-cache="false" id="album">
	<div data-role="header" data-position="fixed">
	    <h1 id="album-title">Album</h1>
EOT;
    $html .= KontrolPanel_button("album");
    $html .= QueuePanel_button("album");

    $html .= <<<EOT
	</div><!-- /header -->

	<div data-role="content">
	    <div class="ui-grid-a">
	    <div class="ui-block-a"><div class="ui-bar">
	    <img class="album" style="width: 100%;" src="Transparent.gif" />
	    </div></div>
	    <div class="ui-block-b"><div class="ui-bar">
	    <button href="#" class="albumclick" data-mini="false" data-musik='{"action": "PlayNow", "preset": "194"}'>Play Now</button>
	    <button href="#" class="albumclick" data-mini="false" data-musik='{"action": "PlayNext", "preset": "194"}'>Play Next</button>
	    <button href="#" class="albumclick" data-mini="false" data-musik='{"action": "PlayLater", "preset": "194"}'>Play Later</button>
	    </div></div>
	    </div><!-- /grid-a -->
	    <h3 id="album-artist">Artist</h3>
	    <p id="album-album">Album (Year)</p>
	    <ul id="album-list" data-role="listview" data-inset="true" data-filter="false">
    <!--
	    <li><a id ="album-1" href="#" class="playpopup" data-rel="popup" data-musik='{"popupid": "album-popup", "preset": "194", "track": "1"}'><h3>1. Title</h3><p>Duration</p></a></li>
	    <li><a id ="album-2" href="#" class="playpopup" data-rel="popup" data-musik='{"popupid": "album-popup", "preset": "194", "track": "2"}'><h3>2. Title</h3><p>Duration</p></a></li>
    -->
	    </ul>
EOT;

    $html .= playpopup_popup("album");

    $html .= <<<EOT
	</div><!-- /content -->

	<div data-role="footer">
	    <h4>LinnDS-jukebox</h4>
	</div><!-- /footer -->
EOT;


    $html .= KontrolPanel_panel($musicDB, "album");

    $html .= <<<EOT
    </div><!-- /page -->


    <div data-role="page" data-dom-cache="false" id="queue">
	<div data-role="header" data-position="fixed">
	    <h1 id="queue-title">Queue</h1>
EOT;
    $html .= KontrolPanel_button("queue");
    $html .= HomePanel_button("queue");

    $html .= <<<EOT
	</div><!-- /header -->

	<div data-role="content">
	    <ul id="queue-list" data-role="listview" data-filter="false">
    <!--
	    <li><a id ="album-1" href="#" class="playpopup" data-rel="popup" data-musik='{"popupid": "queue-popup", "preset": "194", "track": "1"}'><h3>1. Title</h3><p>Duration</p></a></li>
	    <li><a id ="album-2" href="#" class="playpopup" data-rel="popup" data-musik='{"popupid": "queue-popup", "preset": "194", "track": "2"}'><h3>2. Title</h3><p>Duration</p></a></li>
    -->
	    </ul>
EOT;

    $html .= queuepopup_popup("queue");

    $html .= <<<EOT
	</div><!-- /content -->

	<div data-role="footer">
	    <h4>LinnDS-jukebox</h4>
	</div><!-- /footer -->
EOT;


    $html .= KontrolPanel_panel($musicDB, "queue");

    $html .= <<<EOT
    </div><!-- /page -->
EOT;
    return $html;
}

function MainMenuHtml($musicDB)
{
    global $RootMenu;
    global $SubMenuType;
    global $NL;

    static $Str = "";

    if (strlen($Str) > 0)
	return $Str;

    $Str .= '<ul id="main" data-role="listview" data-filter="false">' . $NL;

    foreach ($RootMenu as $No => $Title) {
	if ($SubMenuType[$No] != SUBMENU_TYPE_NEWEST)
	    $Str .= '    <li><a href="#" class="menuclick" data-musik=' . "'" . '{"menu": "' . $No . '", "type": "' . SubMenuType2Str($SubMenuType[$No]) . '", "title": "' . $Title . '"}' . "'>" . $Title . '<span class="ui-li-count">' . $musicDB->NumberOfAlbumsInMenuNo($No) . '</span></a></li>' . $NL;
	else
	    $Str .= '    <li><a href="#" class="menuclick" data-musik=' . "'" . '{"menu": "' . $No . '", "type": "' . SubMenuType2Str($SubMenuType[$No]) . '", "title": "' . $Title . '"}' . "'>" . $Title . '</a></li>' . $NL;
    }
    $Str .= '</ul>' . $NL;

    return $Str;
}

function playpopup_popup($id)
{
    global $NL;
    $html = <<<EOT
	<div class="playpopup" data-role="popup" id="$id-popup" data-history="false">
	    <ul data-role="listview" data-inset="true" style="min-width:180px;">
		<li><a href="#" class="playpopupclick" data-musik='{"action": "PlayNow"}'>Play Now</a></li>
		<li><a href="#" class="playpopupclick" data-musik='{"action": "PlayNext"}'>Play Next</a></li>
		<li><a href="#" class="playpopupclick" data-musik='{"action": "PlayLater"}'>Play Later</a></li>
		<li><a href="#" class="playpopupclick" data-musik='{"action": "Cancel"}'>Cancel</a></li>
	    </ul>
	</div><!-- /popup -->
EOT;

    return $html . $NL;
}

function queuepopup_popup($id)
{
    global $NL;
    $html = <<<EOT
	<div class="queuepopup" data-role="popup" id="$id-popup" data-history="false">
	    <ul data-role="listview" data-inset="true" style="min-width:180px;">
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Queue-Delete"}'>Delete</a></li>
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Queue-JumpTo"}'>Jump To</a></li>
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Queue-JumpToNow"}'>Jump Now</a></li>
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Queue-MoveUp"}'>Move Up</a></li>
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Queue-MoveDown"}'>Move Down</a></li>
		<li><a href="#" class="queuepopupclick" data-musik='{"action": "Cancel"}'>Cancel</a></li>
	    </ul>
	</div><!-- /popup -->
EOT;

    return $html . $NL;
}

function KontrolPanel_button($id)
{
    global $NL;
    $html = <<<EOT
	<a id="$id-KontrolPanel" class="ui-btn-left" href="#$id-KontrolPanelPanel" data-icon="bars">Kontrol</a>
EOT;

    return $html . $NL;
}

function KontrolPanel_panel($musicDB, $id)
{
    global $NL;
    $Cnt = $musicDB->NumberOfAlbumsInMenuNo(0);
    $html = <<<EOT
    <div data-role="panel" id="$id-KontrolPanelPanel" data-position="left" data-position-fixed="true">
	<ul data-role="listview" data-theme="a" data-divider-theme="a" style="margin-top:-16px;margin-bottom:16px;" class="nav-search">
	    <li data-icon="delete" style="background-color:#111;">
		<a href="#" data-rel="close">Close</a>
	    </li>
	</ul>
	<h4>Volume = <span class="ShowVolume">30</span></h4>
	<div data-role="controlgroup" data-type="horizontal">
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Volume-Decr5"}'>-5</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Volume-Decr"}'>-1</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Volume-Reset"}'>0</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Volume-Incr"}'>+1</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Volume-Incr5"}'>+5</button>
	</div>
	<h4>Playlist Kontrol</h4>
	<div data-role="controlgroup" data-type="horizontal">
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Control-Play"}'>Play</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Control-Pause"}'>Pause</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Control-Stop"}'>Stop</button>
	</div>
	<div data-role="controlgroup" data-type="horizontal">
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Control-Previous"}'>Previous</button>
	    <button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Control-Next"}'>Next</button>
	</div>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "PlayRandomTracks", "preset": "1", "track": "$Cnt"}'>Add 50 random tracks</button>
	<h4>Source</h4>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Source-Playlist"}'>Playlist</button>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Source-TV"}'>TV</button>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Source-Radio"}'>Radio</button>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Source-NetAux"}'>AirPlay</button>
	<button href="#" class="panelclick" data-mini="true" data-musik='{"action": "Source-Off"}'>Off</button>
    </div><!-- /panel -->
EOT;

    return $html . $NL;
}

function QueuePanel_button($id)
{
    global $NL;
    $html = <<<EOT
	<a id="$id-QueuePanel" class="queueclick ui-btn-right" href="#queue" data-icon="bars">Queue</a>
EOT;

    return $html . $NL;
}

function HomePanel_button($id)
{
    global $NL;
    $html = <<<EOT
	<a id="$id-HomePanel" class="queueclick ui-btn-right" href="#musik" data-icon="home">Home</a>
EOT;

    return $html . $NL;
}

function AlphabetList($id)
{
    global $ALPHABET;
    global $ALPHABET_SIZE;
    global $NL;

    $space = "    ";

    $html = $space . $space . '<div class="ui-grid-c">' . $NL;
    $class = "ui-block-a";
    for ($alpha = 0; $alpha < $ALPHABET_SIZE; $alpha++)
    {
	$Letter = $ALPHABET[$alpha];
	$LetterId = $Letter;

	if ($Letter == "#")
	    $LetterId = "NUM";

	$html .= $space . $space . $space . '<div id="' . $id . '-' . $LetterId . '" class="' . $class . '"><a href="#" class="alphabetclick" data-role="button">' . $Letter . '</a></div>' . $NL;

	$class = "ui-block-b";
    }
    $html .= $space . $space . '</div>' . $NL;
    return $html;
}


?>

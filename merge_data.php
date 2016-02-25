<?php

///////////////////////////////////////////////////////////////////////////////
/////////////////////////////////// License ///////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/*

Copyright (c) 2015, Joshua Bodine
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

///////////////////////////////////////////////////////////////////////////////
//////////////////////////////////// TODO /////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/*

escape characters in original messages that have special meaning to markdown? characters would be \`*_{}[]()#+-.!
do foreach loops same way (array_keys() only if modifying elements)
get records by name/bzid, rather than storing them
team names and message titles have some HTML character codes in them
consistent use of double versus single quotes

*/

///////////////////////////////////////////////////////////////////////////////
//////////////////////////////////// Setup ////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

/*

This script expects the BZION root directory to be in the same directory as
itself. To accomplish this, execute the following command in this directory:

$ ln -s /path/to/bzion/directory bzion

When configuring BZION, make sure you enter proper values under the
bzion->league->duration setting (you might need to enter the 15-minute duration
ratio).

*/

///////////////////////////////////////////////////////////////////////////////
//////////////////////////// BZION Initialization /////////////////////////////
///////////////////////////////////////////////////////////////////////////////

require_once __DIR__."/bzion/bzion-load.php";

$kernel = new AppKernel('prod', FALSE);
$kernel->boot();

///////////////////////////////////////////////////////////////////////////////
//////////////////////////// Script Initialization ////////////////////////////
///////////////////////////////////////////////////////////////////////////////

require_once __DIR__."/config.php";

///////////////////////////////////////////////////////////////////////////////
////////////////////////////// Utility Functions //////////////////////////////
///////////////////////////////////////////////////////////////////////////////

function printData($data) {
	// only supports integers and strings
	if(count($data) < 1)
		return;

	$columnWidths = Array();
	foreach(reset($data) as $key => $values)
		$columnWidths[$key] = is_numeric($key) ? floor(log10($key)) + 1 : strlen($key);

	foreach($data as $row) {
		foreach($row as $key => $value) {
			if(is_numeric($value)) {
				if(floor(log10($value)) + 1 > $columnWidths[$key])
					$columnWidths[$key] = floor(log10($value)) + 1;
			} else {
				if(strlen($value) > $columnWidths[$key])
					$columnWidths[$key] = strlen($value);
			}
		}
	}

	// top border
	echo '+';
	foreach($columnWidths as $column => $width) {
		for($i = 0; $i < $width + 2; ++$i)
			echo '-';
		echo '+';
	}
	echo "\n";

	// header
	echo '|';
	foreach(reset($data) as $key => $values) {
		printf(" %-".$columnWidths[$key].(is_numeric($key) ? "i" : "s")." |", $key);
	}
	echo "\n";

	// middle border
	echo '+';
	foreach($columnWidths as $column => $width) {
		for($i = 0; $i < $width + 2; ++$i)
			echo '-';
		echo '+';
	}
	echo "\n";

	// rows
	foreach($data as $row) {
		echo '|';
		foreach($row as $key => $value)
			printf(" %".$columnWidths[$key].(is_numeric($value) ? "d" : "s")." |", $value);
		echo "\n";
	}

	// bottom border
	echo '+';
	foreach($columnWidths as $column => $width) {
		for($i = 0; $i < $width + 2; ++$i)
			echo '-';
		echo '+';
	}
	echo "\n";
}

function bbCodeToMarkDown($data) {
	// strip all HTML tags first (so we don't end up stripping links in <url> format
	$data = preg_replace("/\<\/?[^\>]+?\>/", "", $data);

	// substitute certain bbcode tags with markdown equivalents
	$data = preg_replace("/\[(?:i|I)\]\s*((?:.|\n)*?)\s*\[\/(?:i|I)\]/", "*$1*", $data);
	$data = preg_replace("/\[(?:b|B)\]\s*((?:.|\n)*?)\s*\[\/(?:b|B)\]/", "**$1**", $data);
	$data = preg_replace("/\[(?:s|S)\]\s*((?:.|\n)*?)\s*\[\/(?:s|S)\]/", "~~$1~~", $data);
	$data = preg_replace("/\[(?:img|IMG)\]\s*((?:.|\n)*?)\s*\[\/(?:img|IMG)\]/", "![]($1)", $data);
	$data = preg_replace("/\[(?:url|URL)\](.*?)\[\/(?:url|URL)\]/", "<$1>", $data);
	$data = preg_replace("/\[(?:url|URL)\s*\=\s*(.*?)\](.*?)\[\/(?:url|URL)\]/", "[$2]($1)", $data);
	$matches = Array();
	while(preg_match("/\[(?:list|LIST)\]\s*((?:\[\*\]\s*(?:.|\n)*?)+)\s*\[\/(?:list|LIST)\]/", $data, $matches))
		$data = preg_replace("/\[(?:list|LIST)\]\s*((?:\[\*\]\s*(?:.|\n)*?)+)\s*\[\/(?:list|LIST)\]/", "\n".preg_replace("/\[\*\]\s*/", "* ", $matches[1])."\n", $data, 1);

	// strip other bbcode tags
	$data = preg_replace("/\[(?:u|U)\]\s*((?:.|\n)*?)\s*\[\/(?:u|U)\]/", "*$1*", $data);
	$data = preg_replace("/\[(?:center|CENTER)\]\s*((?:.|\n)*?)\s*\[\/(?:center|CENTER)\]/", "*$1*", $data);
	$data = preg_replace("/\[(?:color|COLOR)[^\]]*?\]\s*((?:.|\n)*?)\s*\[\/(?:color|COLOR)\]/", "*$1*", $data);
	$data = preg_replace("/\[(?:style|STYLE)[^\]]*?\]\s*((?:.|\n)*?)\s*\[\/(?:style|STYLE)\]/", "*$1*", $data);
	$data = preg_replace("/\[(?:size|SIZE)[^\]]*?\]\s*((?:.|\n)*?)\s*\[\/(?:size|SIZE)\]/", "*$1*", $data);

	// fix newlines
	$data = preg_replace("/(\r?\n)/", "  $1", $data);

	return $data;
}

///////////////////////////////////////////////////////////////////////////////
///////////////////////////////// Processing //////////////////////////////////
///////////////////////////////////////////////////////////////////////////////

// initialize mysql connections
echo "Initializing MySQL database connections... ";

$bzionConnection = NULL;
$ducatiConnection = NULL;
$guConnection = NULL;

function connectToSQL() {
	global $bzionConnection, $bzionConfig;
	global $ducatiConnection, $ducatiConfig;
	global $guConnection, $guConfig;

	if($bzionConnection != NULL)
		$bzionConnection->close();
	$bzionConnection = @new mysqli(NULL, $bzionConfig['mysqlUsername'], $bzionConfig['mysqlPassword'], $bzionConfig['mysqlDatabase']);
	if($bzionConnection->connect_errno)
		die("Failed, could not initiate connection to bzion database.\n");
	if(! $bzionConnection->set_charset('utf8'))
		die("Failed, could not set charset in bzion database connection.\n");

	if($ducatiConnection != NULL)
		$ducatiConnection->close();
	$ducatiConnection = @new mysqli(NULL, $ducatiConfig['mysqlUsername'], $ducatiConfig['mysqlPassword'], $ducatiConfig['mysqlDatabase']);
	if($ducatiConnection->connect_errno)
		die("Failed, could not initiate connection to ducati database.\n");
	if(! $ducatiConnection->set_charset('utf8'))
		die("Failed, could not set charset in ducati database connection.\n");

	if($guConnection != NULL)
		$guConnection->close();
	$guConnection = @new mysqli(NULL, $guConfig['mysqlUsername'], $guConfig['mysqlPassword'], $guConfig['mysqlDatabase']);
	if($guConnection->connect_errno)
		die("Failed, could not initiate connection to GU database.\n");
	if(! $guConnection->set_charset('utf8'))
		die("Failed, could not set charset in GU database connection.\n");
}

connectToSQL();

echo "Done.\n";

// check for proper tables
echo "Checking for proper tables... ";

$bzionTables = Array(
		'players',
		'player_roles',
		'teams',
		'matches',
		'visits',
		'news_categories',
		'bans',
		'news',
		'conversations',
		'messages',
		'player_conversations',
		'team_conversations',
		'countries'
	);
$ducatiTables = Array(
		'countries',
		'players',
		'players_profile',
		'teams',
		'teams_profile',
		'teams_overview',
		'matches',
		'visits',
		'news',
		'bans',
		'messages_storage',
		'messages_users_connection'
	);
$guTables = Array(
		'countries',
		'players',
		'players_profile',
		'teams',
		'teams_profile',
		'teams_overview',
		'matches',
		'visits',
		'newssystem',
		'pmsystem_msg_storage',
		'pmsystem_msg_recipients_users',
		'pmsystem_msg_recipients_teams',
		'pmsystem_msg_users'
	);

$queryResult = $bzionConnection->query('SHOW TABLES');
if(! $queryResult)
	die("Failed, could not execute query on bzion database, error ".$bzionConnection->errno.".\n");
if($queryResult->num_rows == 0)
	die("Failed, could not select bzion database table list.\n");
$tableList = Array();
$resultArray = $queryResult->fetch_array();
while($resultArray != NULL) {
	array_push($tableList, $resultArray[0]);
	$resultArray = $queryResult->fetch_array();
}
foreach($bzionTables as $table)
	if(! in_array($table, $tableList))
		die("Failed, bzion database does not contain a ".$table." table.\n");

$queryResult = $ducatiConnection->query('SHOW TABLES');
if(! $queryResult)
	die("Failed, could not execute query on ducati database, error ".$bzionConnection->errno.".\n");
if($queryResult->num_rows == 0)
	die("Failed, could not select ducati database table list.\n");
$tableList = Array();
$resultArray = $queryResult->fetch_array();
while($resultArray != NULL) {
	array_push($tableList, $resultArray[0]);
	$resultArray = $queryResult->fetch_array();
}
foreach($ducatiTables as $table)
	if(! in_array($table, $tableList))
		die("Failed, ducati database does not contain a ".$table." table.\n");

$queryResult = $guConnection->query('SHOW TABLES');
if(! $queryResult)
	die("Failed, could not execute query on GU database, error ".$bzionConnection->errno.".\n");
if($queryResult->num_rows == 0)
	die("Failed, could not select GU database table list.\n");
$tableList = Array();
$resultArray = $queryResult->fetch_array();
while($resultArray != NULL) {
	array_push($tableList, $resultArray[0]);
	$resultArray = $queryResult->fetch_array();
}
foreach($guTables as $table)
	if(! in_array($table, $tableList))
		die("Failed, GU database does not contain a ".$table." table.\n");

echo "Done.\n";

// build country conversion table
echo "Building country conversion table... ";

$ducatiCountryes = Array();
$guCountries = Array();

$queryResult = $ducatiConnection->query('SELECT name, id FROM countries');
if(! $queryResult)
	die("Failed, could not query ducati database for countries.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati database has no countries.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$ducatiCountries[$resultArray['id']] = $resultArray['name'];

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT name, id FROM countries');
if(! $queryResult)
	die("Failed, could not query GU database for countries.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU database has no countries.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$guCountries[$resultArray['id']] = $resultArray['name'];

	$resultArray = $queryResult->fetch_assoc();
}

// pre-define some country names that we know are different
$bzionCountries = Array("Taiwan" => 209, "Federated States of Micronesia" => 140, "North Korea" => 113, "South Korea" => 114, "Republic of China" => 45, "Iran" => 102, "Vietnam" => 233);

$queryResult = $bzionConnection->query('SELECT name, id FROM countries');
if(! $queryResult)
	die("Failed, could not query BZION database for countries.\n");
if($queryResult->num_rows == 0)
	die("Failed, BZION database has no countries.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzionCountries[$resultArray['name']] = $resultArray['id'];

	$resultArray = $queryResult->fetch_assoc();
}

echo "Done.\n";

// build player list
echo "Building player list... ";

$playerList = Array();

$queryResult = $ducatiConnection->query('SELECT players.id, players.external_playerid, players.name, players.status, players_profile.joined, players_profile.last_login FROM players, players_profile WHERE players.id = players_profile.playerid AND players.external_playerid <> "" ORDER BY players_profile.joined');
if(! $queryResult)
	die("Failed, could not query ducati database for players.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati database has no players.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['external_playerid']);

	if($bzid == 0) {
		echo "WARNING: ducati player ".$resultArray['name']." has bzid of 0, skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($bzid, $playerList)) {
		$playerList[$bzid] = Array('ducatiID' => '', 'ducatiName' => '', 'ducatiJoined' => '', 'ducatiLogin' => '', 'guID' => '', 'guName' => '', 'guJoined' => '', 'guLogin' => '');
	} else if($playerList[$bzid]['ducatiID'] != '' && $resultArray['status'] == 'deleted') {
		// don't overwrite older, active accounts with data from a deleted account when they share the same BZID
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$playerList[$bzid]['ducatiID'] = $resultArray['id'];
	$playerList[$bzid]['ducatiName'] = $resultArray['name'];
	$playerList[$bzid]['ducatiJoined'] = strtotime($resultArray['joined']);
	$playerList[$bzid]['ducatiLogin'] = strtotime($resultArray['last_login']);

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT players.id, players.external_id, players.name, players.status, players_profile.joined, players_profile.last_login FROM players, players_profile WHERE players.id = players_profile.playerid AND players.external_id <> "" ORDER BY players_profile.joined');
if(! $queryResult)
	die("Failed, could not query GU database for players.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU database has no players.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['external_id']);

	if($bzid == 0) {
		echo "WARNING: GU player ".$resultArray['name']." has bzid of 0, skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($bzid, $playerList)) {
		$playerList[$bzid] = Array('ducatiID' => '', 'ducatiName' => '', 'ducatiJoined' => '', 'ducatiLogin' => '', 'guID' => '', 'guName' => '', 'guJoined' => '', 'guLogin' => '');
	} else if($playerList[$bzid]['guID'] != '' && $resultArray['status'] == 'deleted') {
		// don't overwrite older, active accounts with data from a deleted account when they share the same BZID
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$playerList[$bzid]['guID'] = $resultArray['id'];
	$playerList[$bzid]['guName'] = $resultArray['name'];
	$playerList[$bzid]['guJoined'] = strtotime($resultArray['joined']);
	$playerList[$bzid]['guLogin'] = strtotime($resultArray['last_login']);

	$resultArray = $queryResult->fetch_assoc();
}

if(! array_key_exists($ducatiCouncilID, $playerList)) {
	$playerList[$ducatiCouncilID] = Array('ducatiID' => '-1', 'ducatiName' => 'DucCouncil', 'ducatiJoined' => strtotime('2006-05-15 17:20:00'), 'ducatiLogin' => strtotime('2006-05-15 17:20:00'), 'guID' => '', 'guName' => '', 'guJoined' => '', 'guLogin' => '');
	echo "WARNING: user DucCouncil not found in database; creating... ";
}

if(! array_key_exists($guCouncilID, $playerList)) {
	$playerList[$guCouncilID] = Array('ducatiID' => '', 'ducatiName' => '', 'ducatiJoined' => '', 'ducatiLogin' => '', 'guID' => '-1', 'guName' => 'GU League Council', 'guJoined' => strtotime('2005-05-10 19:10:00'), 'guLogin' => strtotime('2005-05-10 19:10:00'));
	echo "WARNING: user GU League Council not found in database; creating... ";
}

$dateSortedPlayerList = Array();
foreach($playerList as $bzid => $playerEntry) {
	if($playerEntry['ducatiJoined'] == '')
		$dateSortedPlayerList[$bzid] = $playerEntry['guJoined'];
	else if($playerEntry['guJoined'] == '')
		$dateSortedPlayerList[$bzid] = $playerEntry['ducatiJoined'];
	else
		$dateSortedPlayerList[$bzid] = $playerEntry['ducatiJoined'] < $playerEntry['guJoined'] ? $playerEntry['ducatiJoined'] : $playerEntry['guJoined'];
}
asort($dateSortedPlayerList);

$extraInfoPlayerList = $playerList;
$playerList = Array();

$ducatiCount = 0;
$guCount = 0;
$bothCount = 0;

foreach($dateSortedPlayerList as $bzid => $joinedDate) {
	$playerList[$bzid]['bzid'] = $bzid;
	$playerList[$bzid]['joined'] = date("Y-m-d H:i:s", $joinedDate);

	if($extraInfoPlayerList[$bzid]['ducatiLogin'] == '') {
		$playerList[$bzid]['name'] = $extraInfoPlayerList[$bzid]['guName'];
		++$guCount;
	} else if($extraInfoPlayerList[$bzid]['guLogin'] == '') {
		$playerList[$bzid]['name'] = $extraInfoPlayerList[$bzid]['ducatiName'];
		++$ducatiCount;
	} else {
		$playerList[$bzid]['name'] = $extraInfoPlayerList[$bzid]['ducatiLogin'] > $extraInfoPlayerList[$bzid]['guLogin'] ? $extraInfoPlayerList[$bzid]['ducatiName'] : $extraInfoPlayerList[$bzid]['guName'];
		++$bothCount;
	}

	$playerList[$bzid]['ducatiID'] = $extraInfoPlayerList[$bzid]['ducatiID'];
	$playerList[$bzid]['guID'] = $extraInfoPlayerList[$bzid]['guID'];
}

echo "Done; ".$ducatiCount." ducati, ".$guCount." GU, ".$bothCount." both, ".($ducatiCount + $guCount + $bothCount)." total.\n";

// check for duplicate player names
echo "Checking for duplicate player names... ";

$duplicatePlayers = Array();

foreach($playerList as $firstIndex => $firstPlayer) {
	foreach($playerList as $secondIndex => $secondPlayer) {
		if($firstIndex != $secondIndex) {
			if(strtolower($firstPlayer['name']) == strtolower($secondPlayer['name'])) {
				$duplicatePlayers[strtolower($firstPlayer['name'])] = $firstPlayer['name'];
			}
		}
	}
}

if(count($duplicatePlayers) > 0)
	echo "WARNING: multiple accounts found for players names ".implode(", ", $duplicatePlayers)." (".count($duplicatePlayers)." players).\n";
else
	echo "None found.\n";

// build team list
echo "Building team list... ";

$teamList = Array();

$queryResult = $ducatiConnection->query('SELECT teams.id, teams.name, teams_profile.created FROM teams, teams_profile WHERE teams.id = teams_profile.teamid');
if(! $queryResult)
	die("Failed, could not query ducati database for teams.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati database has no teams.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	array_push($teamList, Array('name' => $resultArray['name'], 'ducatiID' => $resultArray['id'], 'guID' => '', 'created' => strtotime($resultArray['created'])));

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT teams.id, teams.name, teams_profile.created FROM teams, teams_profile WHERE teams.id = teams_profile.teamid');
if(! $queryResult)
	die("Failed, could not query GU database for teams.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU database has no teams.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	array_push($teamList, Array('name' => $resultArray['name'], 'ducatiID' => '', 'guID' => $resultArray['id'], 'created' => strtotime($resultArray['created'])));

	$resultArray = $queryResult->fetch_assoc();
}

$dateSortedTeamList = Array();
foreach($teamList as $index => $teamEntry)
	$dateSortedTeamList[$index] = $teamEntry['created'];
asort($dateSortedTeamList);

$unsortedTeamList = $teamList;
$teamList = Array();

$ducatiCount = 0;
$guCount = 0;

foreach($dateSortedTeamList as $index => $creationDate) {
	$thisTeam = $unsortedTeamList[$index];
	$thisTeam['created'] = date("Y-m-d", $thisTeam['created']);

	array_push($teamList, $thisTeam);

	if($thisTeam['ducatiID'] != '')
		++$ducatiCount;
	else
		++$guCount;
}

echo "Done; ".$ducatiCount." ducati, ".$guCount." GU, ".($ducatiCount + $guCount)." total.\n";

/*
// check for non-ASCII team names
echo "Checking for non-ASCII characters in team names... ";

$teamNamePrompted = FALSE;
foreach(array_keys($teamList) as $index) {
	if(! mb_check_encoding($teamList[$index]['name'], 'ASCII')) {
		echo ($teamNamePrompted ? "" : "\n")."Team name \"".$teamList[$index]['name']."\" has non-ASCII characters. Please enter corrected team name and press enter: ";
		$teamList[$index]['name'] = trim(fgets(STDIN));
		$teamNamePrompted = TRUE;
	}
}

echo "Done.\n";
*/

// check for duplicate team names
echo "Checking for duplicate team names... ";

$duplicateTeams = Array();

foreach($teamList as $firstIndex => $firstTeam) {
	foreach($teamList as $secondIndex => $secondTeam) {
		if($firstIndex != $secondIndex) {
			if($firstTeam['name'] == $secondTeam['name']) {
				if($firstTeam['ducatiID'] != '' && $secondTeam['ducatiID'] != '')
					die("Failed, duplicate ducati teams named ".$firstTeam['name']."\n");
				if($firstTeam['guID'] != '' && $secondTeam['guID'] != '')
					die("Failed, duplicate GU teams named ".$firstTeam['name']."\n");
				if($firstTeam['ducatiID'] != '' && $secondTeam['guID'] != '')
					array_push($duplicateTeams, Array('name' => $firstTeam['name'], 'ducatiIndex' => $firstIndex, 'guIndex' => $secondIndex));
			}
		}
	}
}

if(count($duplicateTeams) > 0) {
	echo "Appended league designator to teams ";
	$duplicateTeamNames = Array();
	foreach($duplicateTeams as $team) {
		array_push($duplicateTeamNames, $team['name']);

		$teamList[$team['ducatiIndex']]['name'] = $team['name']." (Ducati)";
		$teamList[$team['guIndex']]['name'] = $team['name']." (GU)";
	}
	echo implode(", ", $duplicateTeamNames)." (".count($duplicateTeams)." teams).\n";

} else {
	echo "None found.\n";
}

// build full user hash for input
echo "Building complete hash of user data... ";

foreach(array_keys($playerList) as $bzid) {
	$playerList[$bzid]['ducatiTeam'] = '';
	$playerList[$bzid]['guTeam'] = '';
	$playerList[$bzid]['status'] = '';
	$playerList[$bzid]['location'] = '';
	$playerList[$bzid]['avatar'] = '';
	$playerList[$bzid]['last_login'] = '';
	$playerList[$bzid]['description'] = '';
	$playerList[$bzid]['admin_comments'] = '';
}

$queryResult = $ducatiConnection->query('SELECT players.id, players.external_playerid, players.teamid, players.status, players_profile.location, players_profile.UTC, players_profile.raw_user_comment, players_profile.raw_admin_comments, players_profile.joined, players_profile.last_login, players_profile.logo_url FROM players, players_profile WHERE players.id = players_profile.playerid AND players.external_playerid <> 0');
if(! $queryResult)
	die("Failed, could not query full ducati user data.\n");
if($queryResult->num_rows == 0)
	die("Failed, full ducati user data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['external_playerid']);

	if($resultArray['id'] != $playerList[$bzid]['ducatiID']) {
		// not the main record, different record with same BZID
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if($resultArray['teamid'] != '' && $resultArray['teamid'] != '0')
		$playerList[$bzid]['ducatiTeam'] = $resultArray['teamid'];

	if($resultArray['status'] == "login disabled" || $resultArray['status'] == "banned")
		$playerList[$bzid]['status'] = "banned";
	else if($resultArray['status'] == "deleted")
		$playerList[$bzid]['status'] = "deleted";
	else
		$playerList[$bzid]['status'] = "active";

	$playerList[$bzid]['location'] = $ducatiCountries[$resultArray['location']];

	if($resultArray['logo_url'] != "NULL")
		$playerList[$bzid]['avatar'] = $resultArray['logo_url'];

	$playerList[$bzid]['last_login'] = $resultArray['last_login'];

	$playerList[$bzid]['description'] = html_entity_decode(bbCodeToMarkDown($resultArray['raw_user_comment']), ENT_QUOTES);

	$playerList[$bzid]['admin_comments'] = html_entity_decode(bbCodeToMarkDown($resultArray['raw_admin_comments']), ENT_QUOTES);

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT players.id, players.external_id, players.teamid, players.status, players_profile.location, players_profile.UTC, players_profile.raw_user_comment, players_profile.raw_admin_comments, players_profile.joined, players_profile.last_login, players_profile.logo_url FROM players, players_profile WHERE players.id = players_profile.playerid AND players.external_id <> 0');
if(! $queryResult)
	die("Failed, could not query full GU user data.\n");
if($queryResult->num_rows == 0)
	die("Failed, full GU user data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['external_id']);

	if($resultArray['id'] != $playerList[$bzid]['guID']) {
		// not the main record, different record with same BZID
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if($resultArray['teamid'] != '' && $resultArray['teamid'] != '0')
		$playerList[$bzid]['guTeam'] = $resultArray['teamid'];

	if($resultArray['status'] == "login disabled" || $resultArray['status'] == "banned")
		$playerList[$bzid]['status'] = "banned";
	else if($resultArray['status'] == "deleted" && ($playerList[$bzid]['status'] == "deleted" || $playerList[$bzid]['status'] == ''))
		$playerList[$bzid]['status'] = "deleted";
	else
		$playerList[$bzid]['status'] = "active";

	// for duplicate values, use the value from the league joined first
	if($playerList[$bzid]['location'] == '' || strtotime($playerList[$bzid]['joined']) > strtotime($resultArray['joined']))
		$playerList[$bzid]['location'] = $guCountries[$resultArray['location']];

	if($playerList[$bzid]['avatar'] == '' || ($resultArray['logo_url'] != "NULL" && $resultArray['logo_url'] != '' && strtotime($playerList[$bzid]['joined']) > strtotime($resultArray['joined'])))
		$playerList[$bzid]['avatar'] = $resultArray['logo_url'];

	if($playerList[$bzid]['last_login'] == '' || strtotime($playerList[$bzid]['last_login']) < strtotime($resultArray['last_login']))
		$playerList[$bzid]['last_login'] = $resultArray['last_login'];

	if($playerList[$bzid]['description'] != '')
		$playerList[$bzid]['description'] .= "\n***\n";
	$playerList[$bzid]['description'] .= html_entity_decode(bbCodeToMarkDown($resultArray['raw_user_comment']), ENT_QUOTES);

	if($playerList[$bzid]['admin_comments'] != '')
		$playerList[$bzid]['admin_comments'] .= "\n***\n";
	$playerList[$bzid]['admin_comments'] .= html_entity_decode(bbCodeToMarkDown($resultArray['raw_admin_comments']), ENT_QUOTES);

	$resultArray = $queryResult->fetch_assoc();
}

if($playerList[$ducatiCouncilID]['status'] == '') { // created ducati_council account
	$playerList[$ducatiCouncilID]['status'] = 'active';
	$playerList[$ducatiCouncilID]['location'] = "here be dragons";
	$playerList[$ducatiCouncilID]['last_login'] = $playerList[$ducatiCouncilID]['joined'];
}

if($playerList[$guCouncilID]['status'] == '') { // created GU League Council account
	$playerList[$guCouncilID]['status'] = 'active';
	$playerList[$guCouncilID]['location'] = "here be dragons";
	$playerList[$guCouncilID]['last_login'] = $playerList[$guCouncilID]['joined'];
}

foreach($playerList as $player)
	if($player['bzid'] == '')
		die("Failed, internal data validation shows a missing bzid.\n");
	else if($player['joined'] == '')
		die("Failed, internal data validation shows a missing join date.\n");
	else if($player['name'] == '')
		die("Failed, internal data validation shows a missing name.\n");
	else if($player['ducatiID'] == '' && $player['guID'] == '')
		die("Failed, internal data validation shows both league IDs missing.\n");
	else if($player['status'] == '')
		die("Failed, internal data validation shows a missing status.\n");
	else if($player['location'] == '')
		die("Failed, internal data validation shows a missing location.\n");
	else if($player['last_login'] == '')
		die("Failed, internal data validation shows a missing last login date.\n");

echo "Done.\n";

// prefix player descriptions (if existing) with avatar image links
echo "Prefixing player profile text with avatar image links... ";

foreach(array_keys($playerList) as $bzid)
	if($playerList[$bzid]['avatar'] != '')
		$playerList[$bzid]['description'] = "![previous avatar](".$playerList[$bzid]['avatar'].")".($playerList[$bzid]['description'] != '' ? "\n***\n".$playerList[$bzid]['description'] : '');

echo "Done.\n";

// build full team hash for input
echo "Building complete hash of team data... ";

foreach(array_keys($teamList) as $index) {
	$teamList[$index]['leader'] = '';
	$teamList[$index]['status'] = '';
	$teamList[$index]['avatar'] = '';
	$teamList[$index]['score'] = '';
	$teamList[$index]['description'] = '';
	$teamList[$index]['members'] = Array();
}
$queryResult = $ducatiConnection->query('SELECT teams.id, teams.name, teams.leader_playerid, (SELECT external_playerid FROM players WHERE id = teams.leader_playerid) as leader_bzid, teams_overview.score, teams_overview.any_teamless_player_can_join, teams_overview.deleted, teams_profile.raw_description, teams_profile.logo_url, teams_profile.created FROM teams, teams_overview, teams_profile WHERE teams_overview.teamid = teams.id AND teams_profile.teamid = teams.id');
if(! $queryResult)
	die("Failed, could not query full ducati team data.\n");
if($queryResult->num_rows == 0)
	die("Failed, full ducati team data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$teamIndex = -1;
	foreach($teamList as $index => $team) {
		if($team['ducatiID'] == $resultArray['id']) {
			$teamIndex = $index;
			break;
		}
	}

	if($teamIndex == -1)
		die("Failed, could not locate ducati team ".$resultArray['name']." in team list.\n");

	$teamList[$teamIndex]['leader'] = $resultArray['leader_bzid'];

	if($resultArray['deleted'] == '2')
		$teamList[$teamIndex]['status'] = "deleted";
	else if($resultArray['any_teamless_player_can_join'] == '1')
		$teamList[$teamIndex]['status'] = "open";
	else
		$teamList[$teamIndex]['status'] = "closed";

	if($resultArray['logo_url'] != "NULL")
		$teamList[$teamIndex]['avatar'] = $resultArray['logo_url'];

	$teamList[$teamIndex]['score'] = $resultArray['score'];

	$teamList[$teamIndex]['description'] = html_entity_decode(bbCodeToMarkDown($resultArray['raw_description']), ENT_QUOTES);

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT teams.id, teams.name, teams.leader_userid, (SELECT external_id FROM players WHERE id = teams.leader_userid) as leader_bzid, teams_overview.score, teams_overview.any_teamless_player_can_join, teams_overview.deleted, teams_profile.raw_description, teams_profile.logo_url, teams_profile.created FROM teams, teams_overview, teams_profile WHERE teams_overview.teamid = teams.id AND teams_profile.teamid = teams.id');
if(! $queryResult)
	die("Failed, could not query full GU team data.\n");
if($queryResult->num_rows == 0)
	die("Failed, full GU team data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$teamIndex = -1;
	foreach($teamList as $index => $team) {
		if($team['guID'] == $resultArray['id']) {
			$teamIndex = $index;
			break;
		}
	}

	if($teamIndex == -1)
		die("Failed, could not locate GU team ".$resultArray['name']." in team list.\n");

	$teamList[$teamIndex]['leader'] = $resultArray['leader_bzid'];

	if($resultArray['deleted'] == '2')
		$teamList[$teamIndex]['status'] = "deleted";
	else if($resultArray['any_teamless_player_can_join'] == '1')
		$teamList[$teamIndex]['status'] = "open";
	else
		$teamList[$teamIndex]['status'] = "closed";

	if($resultArray['logo_url'] != "NULL")
		$teamList[$teamIndex]['avatar'] = $resultArray['logo_url'];

	$teamList[$teamIndex]['score'] = $resultArray['score'];

	$teamList[$teamIndex]['description'] = html_entity_decode(bbCodeToMarkDown($resultArray['raw_description']), ENT_QUOTES);

	$resultArray = $queryResult->fetch_assoc();
}

foreach($teamList as $team)
	if($team['status'] == '')
		die("Failed, internal data validation shows a missing team status.\n");
	else if($team['status'] == 'active' && $team['leader'] == '')
		die("Failed, internal data validation shows an active team with no leader.\n");
	else if($team['status'] == 'active' && count($team['members'] == 0))
		die("Failed, internal data validation shows an active team with no members.\n");
	else if($team['ducatiID'] != '' && $team['guID'] != '')
		die("Failed, internal data validation shows a team being from both leagues.\n");
	else if($team['ducatiID'] == '' && $team['guID'] == '')
		die("Failed, internal data validation shows a team being from neither league.\n");
	else if($team['score'] == '')
		die("Failed, internal data validation shows a missing team score.\n");

echo "Done.\n";

// prefix team descriptions (if existing) with avatar image links
echo "Prefixing team profile text with avatar image links... ";

foreach(array_keys($teamList) as $index)
	if($teamList[$index]['avatar'] != '')
		$teamList[$index]['description'] = "![previous avatar](".$teamList[$index]['avatar'].")".($teamList[$index]['description'] != '' ? "\n***\n".$teamList[$index]['description'] : '');

echo "Done.\n";

// build membership rosters and resolve conflicts (pick team created first, unless player is leader of team created second)
echo "Building membership rosters and resolving conflicts... ";

$ducatiTeamIndexesByTeamID = Array();
$guTeamIndexesByTeamID = Array();
foreach(array_keys($teamList) as $index)
	if($teamList[$index]['ducatiID'] != '')
		if(array_key_exists($teamList[$index]['ducatiID'], $ducatiTeamIndexesByTeamID))
			die("Failed, duplicate ducati team IDs found.\n");
		else
			$ducatiTeamIndexesByTeamID[$teamList[$index]['ducatiID']] = $index;
	else
		if(array_key_exists($teamList[$index]['guID'], $guTeamIndexesByTeamID))
			die("Failed, duplicate GU team IDs found.\n");
		else
			$guTeamIndexesByTeamID[$teamList[$index]['guID']] = $index;

foreach(array_keys($playerList) as $index) {
	$playerList[$index]['team'] = '';
	$playerList[$index]['inviteToTeam'] = '';

	if($playerList[$index]['ducatiTeam'] != '' && $playerList[$index]['guTeam'] != '') {
		if(strtotime($teamList[$ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']]]['created']) <= strtotime($teamList[$guTeamIndexesByTeamID[$playerList[$index]['guTeam']]]['created'])) {
			$playerList[$index]['team'] = $ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']];
			array_push($teamList[$playerList[$index]['team']]['members'], $index);

			if($teamList[$guTeamIndexesByTeamID[$playerList[$index]['guTeam']]]['leader'] == $playerList[$index]['bzid'])
				$teamList[$guTeamIndexesByTeamID[$playerList[$index]['guTeam']]]['leader'] = '';

			$playerList[$index]['inviteToTeam'] = $guTeamIndexesByTeamID[$playerList[$index]['guTeam']];
		} else {
			$playerList[$index]['team'] = $guTeamIndexesByTeamID[$playerList[$index]['guTeam']];
			array_push($teamList[$playerList[$index]['team']]['members'], $index);

			if($teamList[$ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']]]['leader'] == $playerList[$index]['bzid'])
				$teamList[$ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']]]['leader'] = '';

			$playerList[$index]['inviteToTeam'] = $ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']];
		}
	} else if($playerList[$index]['ducatiTeam'] != '') {
		$playerList[$index]['team'] = $ducatiTeamIndexesByTeamID[$playerList[$index]['ducatiTeam']];
		array_push($teamList[$playerList[$index]['team']]['members'], $index);
	} else if($playerList[$index]['guTeam'] != '') {
		$playerList[$index]['team'] = $guTeamIndexesByTeamID[$playerList[$index]['guTeam']];
		array_push($teamList[$playerList[$index]['team']]['members'], $index);
	}
}

foreach(array_keys($teamList) as $index)
	if($teamList[$index]['leader'] == '')
		if(count($teamList[$index]['members']) > 0)
			$teamList[$index]['leader'] = $teamList[$index]['members'][0]; // assign member with lowest bzid as new leader
		else
			$teamList[$index]['status'] = "deleted";

echo "Done.\n";

// import matches
echo "Importing matches... ";

$matchesList = Array();

$queryResult = $ducatiConnection->query('SELECT players.external_playerid AS author, matches.timestamp, matches.team1_teamid, matches.team2_teamid, matches.team1_points, matches.team2_points, matches.duration FROM players, matches WHERE players.id = matches.playerid');
if(! $queryResult)
	die("Failed, could not query ducati matches list.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati matches list query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$author = array_key_exists(trim($resultArray['author']), $playerList) ? trim($resultArray['author']) : $ducatiCouncilID;

	if(! strtotime($resultArray['timestamp'])) {
		echo "WARNING: ducati match timestamp ".$resultArray['timestamp']." could not be parsed; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(strtotime($resultArray['timestamp']) < strtotime("2000-01-01 00:00:00")) {
		echo "WARNING: ducati match timestamp ".$resultArray['timestamp']." is too far in the past; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($resultArray['team1_teamid'], $ducatiTeamIndexesByTeamID)) {
		echo "WARNING: ducati match at ".$resultArray['timestamp']." has invalid first team; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($resultArray['team2_teamid'], $ducatiTeamIndexesByTeamID)) {
		echo "WARNING: ducati match at ".$resultArray['timestamp']." involving team ".$teamList[$ducatiTeamIndexesByTeamID[$resultArray['team1_teamid']]]['name']." has invalid second participant; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	array_push($matchesList, Array(
			'timestamp' => strtotime($resultArray['timestamp']),
			'team1ID' => $ducatiTeamIndexesByTeamID[$resultArray['team1_teamid']],
			'team2ID' => $ducatiTeamIndexesByTeamID[$resultArray['team2_teamid']], 
			'team1Points' => $resultArray['team1_points'],
			'team2Points' => $resultArray['team2_points'],
			'duration' => $resultArray['duration'],
			'author' => $author,
			'map' => 'Ducati'
		));

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT players.external_id AS author, matches.timestamp, matches.team1ID, matches.team2ID, matches.team1_points, matches.team2_points, matches.duration FROM players, matches WHERE players.id = matches.userid');
if(! $queryResult)
	die("Failed, could not query GU matches list.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU matches list query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$author = array_key_exists(trim($resultArray['author']), $playerList) ? trim($resultArray['author']) : $guCouncilID;

	if(! strtotime($resultArray['timestamp'])) {
		echo "WARNING: GU match timestamp ".$resultArray['timestamp']." could not be parsed; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(strtotime($resultArray['timestamp']) < strtotime("2000-01-01 00:00:00")) {
		echo "WARNING: GU match timestamp ".$resultArray['timestamp']." is too far in the past; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($resultArray['team1ID'], $guTeamIndexesByTeamID)) {
		echo "WARNING: GU match at ".$resultArray['timestamp']." has invalid first team; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	if(! array_key_exists($resultArray['team2ID'], $guTeamIndexesByTeamID)) {
		echo "WARNING: GU match at ".$resultArray['timestamp']." involving team ".$teamList[$guTeamIndexesByTeamID[$resultArray['team1ID']]]['name']." has invalid second participant; skipping... ";
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	array_push($matchesList, Array(
			'timestamp' => strtotime($resultArray['timestamp']),
			'team1ID' => $guTeamIndexesByTeamID[$resultArray['team1ID']],
			'team2ID' => $guTeamIndexesByTeamID[$resultArray['team2ID']], 
			'team1Points' => $resultArray['team1_points'],
			'team2Points' => $resultArray['team2_points'],
			'duration' => $resultArray['duration'],
			'author' => $author,
			'map' => 'HiX'
		));

	$resultArray = $queryResult->fetch_assoc();
}

$dateSortedMatchesList = Array();
foreach($matchesList as $index => $entry)
	$dateSortedMatchesList[$index] = $entry['timestamp'];
asort($dateSortedMatchesList);

$unsortedMatchesList = $matchesList;
$matchesList = Array();

foreach(array_keys($dateSortedMatchesList) as $index)
	array_push($matchesList, $unsortedMatchesList[$index]);

echo "Done.\n";

// import visits log
echo "Importing visits log... ";

$visitsLog = Array();

$queryResult = $ducatiConnection->query('SELECT players.external_playerid, visits.`ip-address`, visits.host, visits.timestamp FROM players, visits WHERE visits.playerid = players.id AND visits.`ip-address` <> "NULL"');
if(! $queryResult)
	die("Failed, could not query ducati visits log data.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati visits log data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	if(array_key_exists($resultArray['external_playerid'], $playerList))
		array_push($visitsLog, Array('timestamp' => $resultArray['timestamp'], 'bzid' => $resultArray['external_playerid'], 'ip' => $resultArray['ip-address'], 'host' => $resultArray['host'], 'user_agent' => "Ducati Historical Record"));

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT players.external_id, visits.`ip-address`, visits.host, visits.timestamp FROM players, visits WHERE visits.playerid = players.id AND visits.`ip-address` <> "NULL"');
if(! $queryResult)
	die("Failed, could not query GU visits log data.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU visits log data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	if(array_key_exists($resultArray['external_id'], $playerList))
		array_push($visitsLog, Array('timestamp' => $resultArray['timestamp'], 'bzid' => $resultArray['external_id'], 'ip' => $resultArray['ip-address'], 'host' => $resultArray['host'], 'user_agent' => "GU Historical Record"));

	$resultArray = $queryResult->fetch_assoc();
}

$dateSortedVisitsLog = Array();
foreach($visitsLog as $index => $entry)
	$dateSortedVisitsLog[$index] = strtotime($entry['timestamp']);
asort($dateSortedVisitsLog);

$unsortedVisitsLog = $visitsLog;
$visitsLog = Array();

foreach(array_keys($dateSortedVisitsLog) as $index)
	array_push($visitsLog, $unsortedVisitsLog[$index]);

echo "Done.\n";

// import news and bans (they're in the same table in GU)
echo "Importing news and bans... ";

$newsList = Array();
$bansList = Array();

$queryResult = $ducatiConnection->query('SELECT news.timestamp, news.raw_announcement, players.external_playerid AS bzid FROM news, players WHERE news.author_id = players.id');
if(! $queryResult)
	die("Failed, could not query ducati news data.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati news data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['bzid']) != '' ? trim($resultArray['bzid']) : $ducatiCouncilID;

	array_push($newsList, Array('timestamp' => strtotime($resultArray['timestamp']), 'text' => html_entity_decode(bbCodeToMarkDown($resultArray['raw_announcement']), ENT_QUOTES), 'author' => $bzid, 'title' => 'News', 'league' => 'ducati'));

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $ducatiConnection->query('SELECT bans.timestamp, bans.raw_announcement, players.external_playerid AS bzid FROM bans, players WHERE bans.author_id = players.id');
if(! $queryResult)
	die("Failed, could not query ducati bans data.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati bans data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	$bzid = trim($resultArray['bzid']) != '' ? trim($resultArray['bzid']) : $ducatiCouncilID;

	array_push($bansList, Array('timestamp' => strtotime($resultArray['timestamp']), 'text' => html_entity_decode(bbCodeToMarkDown($resultArray['raw_announcement']), ENT_QUOTES) , 'author' => $bzid, 'title' => 'Ban', 'league' => 'ducati'));

	$resultArray = $queryResult->fetch_assoc();
}

$queryResult = $guConnection->query('SELECT title, timestamp, author, raw_msg, page FROM newssystem');
if(! $queryResult)
	die("Failed, could not query GU news and bans data.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU news and bans data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	// GU only stored the author's name (grrr), so search for it
	$bzid = $guCouncilID;
	foreach($playerList as $player) {
		if(strtolower($resultArray['author']) == strtolower($player['name'])) {
			$bzid = $player['bzid'];
			break;
		}
	}

	if($resultArray['page'] == "News/")
		array_push($newsList, Array('timestamp' => strtotime($resultArray['timestamp']), 'text' => html_entity_decode(bbCodeToMarkDown($resultArray['raw_msg']), ENT_QUOTES), 'author' => $bzid, 'title' => html_entity_decode($resultArray['title'], ENT_QUOTES), 'league' => 'GU'));
	else if($resultArray['page'] == "Bans/")
		array_push($bansList, Array('timestamp' => strtotime($resultArray['timestamp']), 'text' => html_entity_decode(bbCodeToMarkDown($resultArray['raw_msg']), ENT_QUOTES), 'author' => $bzid, 'title' => html_entity_decode($resultArray['title'], ENT_QUOTES), 'league' => 'GU'));
	else
		die("Failed, unable to parse GU news system category \"".$resultArray['page']."\".\n");

	$resultArray = $queryResult->fetch_assoc();
}

$dateSortedNewsList = Array();
foreach($newsList as $index => $entry)
	$dateSortedNewsList[$index] = $entry['timestamp'];
asort($dateSortedNewsList);

$unsortedNewsList = $newsList;
$newsList = Array();

foreach(array_keys($dateSortedNewsList) as $index)
	array_push($newsList, $unsortedNewsList[$index]);


$dateSortedBansList = Array();
foreach($bansList as $index => $entry)
	$dateSortedBansList[$index] = $entry['timestamp'];
asort($dateSortedBansList);

$unsortedBansList = $bansList;
$bansList = Array();

foreach(array_keys($dateSortedBansList) as $index)
	array_push($bansList, $unsortedBansList[$index]);

echo "Done.\n";

// import private messages
echo "Importing private messages... ";

$bzidsByDucatiPlayerID = Array();
$bzidsByGUPlayerID = Array();
foreach(array_keys($playerList) as $index) {
	if($playerList[$index]['ducatiID'] != '')
		if(array_key_exists($playerList[$index]['ducatiID'], $bzidsByDucatiPlayerID))
			die("Failed, duplicate ducati player IDs found.\n");
		else
			$bzidsByDucatiPlayerID[$playerList[$index]['ducatiID']] = $index;
	if($playerList[$index]['guID'] != '')
		if(array_key_exists($playerList[$index]['guID'], $bzidsByGUPlayerID))
			die("Failed, duplicate GU player IDs found.\n");
		else
			$bzidsByGUPlayerID[$playerList[$index]['guID']] = $index;
}

$privateMessages = Array();
$ducatiCount = 0;
$guCount = 0;

$queryResult = $ducatiConnection->query('SELECT messages_storage.author_id, messages_storage.subject, messages_storage.message, messages_storage.timestamp, messages_storage.from_team, messages_storage.recipients AS individual_recipients, GROUP_CONCAT(messages_users_connection.playerid SEPARATOR " ") AS all_recipients FROM messages_storage, messages_users_connection WHERE messages_storage.id = messages_users_connection.msgid GROUP by messages_storage.id');
if(! $queryResult)
	die("Failed, could not query ducati private message data.\n");
if($queryResult->num_rows == 0)
	die("Failed, ducati private message data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	if(! array_key_exists($resultArray['author_id'], $bzidsByDucatiPlayerID)) { // we don't have an account for the author
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$individualRecipients = Array();
	if($resultArray['from_team'] == '0')
		foreach(explode(" ", $resultArray['individual_recipients']) as $recipient)
			if(array_key_exists($recipient, $bzidsByDucatiPlayerID))
				array_push($individualRecipients, $bzidsByDucatiPlayerID[$recipient]);

	if($resultArray['from_team'] == '0' && count($individualRecipients) == 0) { // it's an individual message and we don't have accounts for any of the recipients
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$teamMemberRecipients = Array();
	if($resultArray['from_team'] == '1')
		foreach(explode(" ", $resultArray['all_recipients']) as $recipient)
			if(array_key_exists($recipient, $bzidsByDucatiPlayerID) && $recipient != $resultArray['author_id'])
				array_push($teamMemberRecipients, $bzidsByDucatiPlayerID[$recipient]);

	array_push($privateMessages, Array(
			'author' => $bzidsByDucatiPlayerID[$resultArray['author_id']],
			'subject' => html_entity_decode($resultArray['subject'], ENT_QUOTES),
			'message' => html_entity_decode(bbCodeToMarkDown($resultArray['message']), ENT_QUOTES),
			'timestamp' => strtotime($resultArray['timestamp']),
			'individual_recipients' => $individualRecipients,
			'team_member_recipients' => $teamMemberRecipients,
			'ducati_team_recipients' => $resultArray['from_team'] == 1 && array_key_exists($resultArray['individual_recipients'], $ducatiTeamIndexesByTeamID) ? Array($resultArray['individual_recipients']) : Array(),
			'gu_team_recipients' => Array()
		));

	$resultArray = $queryResult->fetch_assoc();
	++$ducatiCount;
}

$queryResult = $guConnection->query('SELECT pmsystem_msg_storage.id, pmsystem_msg_storage.author_id, pmsystem_msg_storage.subject, pmsystem_msg_storage.message, pmsystem_msg_storage.timestamp, GROUP_CONCAT(DISTINCT pmsystem_msg_recipients_users.userid SEPARATOR " ") AS individual_recipients, GROUP_CONCAT(DISTINCT pmsystem_msg_recipients_teams.teamid SEPARATOR " ") as team_recipients, GROUP_CONCAT(DISTINCT pmsystem_msg_users.userid SEPARATOR " ") AS all_recipients FROM pmsystem_msg_storage LEFT JOIN pmsystem_msg_recipients_users ON pmsystem_msg_storage.id = pmsystem_msg_recipients_users.msgid LEFT JOIN pmsystem_msg_recipients_teams ON pmsystem_msg_storage.id = pmsystem_msg_recipients_teams.msgid LEFT JOIN pmsystem_msg_users ON pmsystem_msg_storage.id = pmsystem_msg_users.msgid WHERE 1 GROUP BY CONCAT_WS(" ", pmsystem_msg_storage.author_id, pmsystem_msg_storage.timestamp, pmsystem_msg_storage.subject) ORDER BY pmsystem_msg_storage.id');
if(! $queryResult)
	die("Failed, could not query GU private message data.\n");
if($queryResult->num_rows == 0)
	die("Failed, GU private message data query returned no results.\n");
$resultArray = $queryResult->fetch_assoc();
while($resultArray != NULL) {
	if(! array_key_exists($resultArray['author_id'], $bzidsByGUPlayerID)) { // we don't have an account for the author
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$individualRecipients = Array();
	if($resultArray['individual_recipients'] != '')
		foreach(explode(" ", $resultArray['individual_recipients']) as $recipient)
			if(array_key_exists($recipient, $bzidsByGUPlayerID))
				array_push($individualRecipients, $bzidsByGUPlayerID[$recipient]);

	if($resultArray['team_recipients'] == '' && count($individualRecipients) == 0) { // it's an individual message and we don't have accounts for any of the recipients
		$resultArray = $queryResult->fetch_assoc();
		continue;
	}

	$teamRecipients = Array();
	if($resultArray['team_recipients'] != '')
		foreach(explode(" ", $resultArray['team_recipients']) as $team)
			if(array_key_exists($team, $guTeamIndexesByTeamID))
				array_push($teamRecipients, $team);

	$teamMemberRecipients = Array();
	if($resultArray['team_recipients'] != '')
		foreach(explode(" ", $resultArray['all_recipients']) as $recipient)
			if(array_key_exists($recipient, $bzidsByGUPlayerID) && $recipient != $resultArray['author_id'])
				array_push($teamMemberRecipients, $bzidsByGUPlayerID[$recipient]);

	array_push($privateMessages, Array(
			'author' => $bzidsByGUPlayerID[$resultArray['author_id']],
			'subject' => html_entity_decode($resultArray['subject'], ENT_QUOTES),
			'message' => html_entity_decode(bbCodeToMarkDown($resultArray['message']), ENT_QUOTES),
			'timestamp' => strtotime($resultArray['timestamp']),
			'individual_recipients' => $individualRecipients,
			'team_member_recipients' => $teamMemberRecipients,
			'ducati_team_recipients' => Array(),
			'gu_team_recipients' => $teamRecipients
		));

	$resultArray = $queryResult->fetch_assoc();
	++$guCount;
}

$dateSortedPrivateMessages = Array();
foreach($privateMessages as $index => $entry)
	$dateSortedPrivateMessages[$index] = $entry['timestamp'];
asort($dateSortedPrivateMessages);

$unsortedPrivateMessages = $privateMessages;
$privateMessages = Array();

foreach(array_keys($dateSortedPrivateMessages) as $index)
	array_push($privateMessages, $unsortedPrivateMessages[$index]);

echo "Done; ".$ducatiCount." ducati, ".$guCount." GU, ".($ducatiCount + $guCount)." total.\n";

// clear bzion database
echo "Clearing BZION database... ";

if(! $bzionConnection->query('DELETE FROM team_conversations'))
	die("Could not delete existing team message conversation data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE team_conversations AUTO_INCREMENT=1'))
	die("Could not reset index for team message conversation data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM player_conversations'))
	die("Could not delete existing player message conversation data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE player_conversations AUTO_INCREMENT=1'))
	die("Could not reset index for player message conversation data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM messages'))
	die("Could not delete existing message data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE messages AUTO_INCREMENT=1'))
	die("Could not reset index for message data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM conversations'))
	die("Could not delete existing message conversation data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE conversations AUTO_INCREMENT=1'))
	die("Could not reset index for message conversation data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM notifications'))
	die("Could not delete existing notification data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE notifications AUTO_INCREMENT=1'))
	die("Could not reset index for notification data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM invitations'))
	die("Could not delete existing invitation data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE invitations AUTO_INCREMENT=1'))
	die("Could not reset index for invitation data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM news'))
	die("Could not delete existing news data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE news AUTO_INCREMENT=1'))
	die("Could not reset index for news data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM bans'))
	die("Could not delete existing ban data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE bans AUTO_INCREMENT=1'))
	die("Could not reset index for ban data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM news_categories WHERE name <> "Uncategorized"'))
	die("Could not delete existing news category data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE news_categories AUTO_INCREMENT=2'))
	die("Could not reset index for news category data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM visits'))
	die("Could not delete existing visits log data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE visits AUTO_INCREMENT=1'))
	die("Could not reset index for visits log data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM matches'))
	die("Could not delete existing matches data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE matches AUTO_INCREMENT=1'))
	die("Could not reset index for matches data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM teams'))
	die("Could not delete existing team data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE teams AUTO_INCREMENT=1'))
	die("Could not reset index for team data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM player_roles'))
	die("Could not delete existing player roles data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE player_roles AUTO_INCREMENT=1'))
	die("Could not reset index for player roles data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM past_callsigns'))
	die("Could not delete existing past callsign data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE past_callsigns AUTO_INCREMENT=1'))
	die("Could not reset index for past callsign data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM players'))
	die("Could not delete existing player data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE players AUTO_INCREMENT=1'))
	die("Could not reset index for player data in bzion database.\n");

if(! $bzionConnection->query('DELETE FROM maps'))
	die("Could not delete existing map data in bzion database.\n");
if(! $bzionConnection->query('ALTER TABLE maps AUTO_INCREMENT=1'))
	die("Could not reset index for map data in bzion database.\n");

echo "Done.\n";

// export players
echo "Exporting players... ";

$deletedTeamLeader = Player::newPlayer(-1, "Deleted Team Leader");
$ducatiBanTarget = Player::newPlayer(-2, "Historic Ducati Ban");
$guBanTarget = Player::newPlayer(-3, "Historic GU Ban");
$dummyMessageTarget = Player::newPlayer(-4, "Historic Message");

foreach(array_keys($playerList) as $bzid) {
	if($playerList[$bzid]['location'] == "here be dragons")
		$playerList[$bzid]['location'] = "Unknown";
	if(! array_key_exists($playerList[$bzid]['location'], $bzionCountries)) {
		echo "WARNING: player ".$playerList[$bzid]['name']." has country \"".$playerList[$bzid]['location']."\", which is not found in BZION database; setting to unknown... ";
		$playerList[$bzid]['location'] = "Unknown";
	}

	$playerList[$bzid]['record'] = Player::newPlayer($playerList[$bzid]['bzid'], $playerList[$bzid]['name'], null, $playerList[$bzid]['status'], Player::PLAYER, "", $playerList[$bzid]['description'], $bzionCountries[$playerList[$bzid]['location']], "UTC", $playerList[$bzid]['joined'], $playerList[$bzid]['last_login']);
}

echo "Done.\n";

// create roles
echo "Setting administrator roles... ";

foreach($adminBZIDs as $bzid)
	if(array_key_exists($bzid, $playerList))
		$playerList[$bzid]['record']->addRole(Player::ADMIN);
foreach($developerBZIDs as $bzid)
	if(array_key_exists($bzid, $playerList))
		$playerList[$bzid]['record']->addRole(Player::DEVELOPER);
foreach($copBZIDs as $bzid)
	if(array_key_exists($bzid, $playerList))
		$playerList[$bzid]['record']->addRole(Player::COP);

echo "Done.\n";

// export teams
echo "Exporting teams... ";

foreach(array_keys($teamList) as $index) {
	$teamList[$index]['record'] = Team::createTeam($teamList[$index]['name'], $teamList[$index]['status'] == "deleted" ? $deletedTeamLeader->getId() : $playerList[$teamList[$index]['leader']]['record']->getId(), "", $teamList[$index]['description'], $teamList[$index]['status'], $teamList[$index]['created']." 00:00:00");

	if($teamList[$index]['status'] == "deleted") {
		$teamList[$index]['record']->delete();
		$deletedTeamLeader->refresh();
	}
}
if(! $bzionConnection->query('UPDATE players SET status="deleted" WHERE id = '.$deletedTeamLeader->getId()))
	die("Failed, unable to delete dummy player record for deleted teams.\n");

echo "Done.\n";

// export team memberships
echo "Exporting team memberships... ";

foreach($teamList as $team)
	foreach($team['members'] as $bzid)
		if($bzid != $team['leader'])
			$team['record']->addMember($playerList[$bzid]['record']->getId());

echo "Done.\n";

// export team invitations
echo "Exporting team invites... ";
foreach($playerList as $player) {
	if ($player['inviteToTeam'] !== '') {
		$invite = Invitation::sendInvite($player['record']->getId(), $teamList[$player['inviteToTeam']]['record']->getId(), null, "This invitation was generated automatically", "1 month");
		Service::getDispatcher()->dispatch(BZIon\Event\Events::TEAM_INVITE, new BZIon\Event\TeamInviteEvent($invite));
	}
}

echo "Done.\n";

// export matches
echo "Exporting matches... ";

$ducatiMap = Map::addMap("Ducati");
$hixMap = Map::addMap("HiX");

// Start a transaction so that queries are not sent one-by-one,
// reducing the time needed to store matches
$bzionDatabase = Database::getInstance();
$bzionDatabase->startTransaction();

$i = 1;
foreach($matchesList as $match) {
	// Commit all pending queries once every 200 matches
	if ($i % 200 == 0)
		$bzionDatabase->commit();

	Match::enterMatch(
			$teamList[$match['team1ID']]['record']->getId(),
			$teamList[$match['team2ID']]['record']->getId(),
			$match['team1Points'],
			$match['team2Points'],
			$match['duration'],
			$playerList[$match['author']]['record']->getId(),
			date("Y-m-d H:i:s", $match['timestamp']),
			Array(),
			Array(),
			NULL,
			NULL,
			NULL,
			$match['map'] == "Ducati" ? $ducatiMap->getId() : $hixMap->getId()
		);

	$i++;
}

// Submit any pending queries
$bzionDatabase->finishTransaction();

echo "Done.\n";

// that probably took a while, so let's verify our database connections
if(! $bzionConnection->ping() || ! $ducatiConnection->ping() || ! $guConnection->ping())
	connectToSQL();

// export visits log
echo "Exporting visits log... ";

$bzionConnection->autocommit(false);
$i = 1;
foreach($visitsLog as $entry) {
	if ($i % 100 == 0)
		$bzionConnection->commit();

	if(! $bzionConnection->query('INSERT INTO visits SET player='.$playerList[$entry['bzid']]['record']->getId().', ip="'.$entry['ip'].'", host='.($entry['host'] != '' ? '"'.$entry['host'].'"' : 'NULL').', user_agent="'.$entry['user_agent'].'", timestamp="'.$entry['timestamp'].'"'))
		die("Failed, unable to insert log entry into BZION database.\n");
	
	$i++;
}

$bzionConnection->commit();
$bzionConnection->autocommit(true);

echo "Done.\n";

// export news
echo "Exporting news... ";

$ducatiNewsCategory = NewsCategory::addCategory("Historic Ducati News");
$guNewsCategory = NewsCategory::addCategory("Historic GU News");

foreach($newsList as $item) {
	$tempAdmin = FALSE;

	if(! Player::getFromBZID($item['author'])->hasPermission(Permission::PUBLISH_NEWS)) {
		$playerList[$item['author']]['record']->addRole(Player::ADMIN);
		$tempAdmin = TRUE;
	}

	$thisNews = News::addNews($item['title'], $item['text'], $playerList[$item['author']]['record']->getId(), $item['league'] == "ducati" ? $ducatiNewsCategory->getId() : $guNewsCategory->getId());

	$queryResult = $bzionConnection->query('UPDATE news SET created="'.date("Y-m-d H:i:s", $item['timestamp']).'", updated="'.date("Y-m-d H:i:s", $item['timestamp']).'" WHERE id='.$thisNews->getId());
	if(! $queryResult)
		die("Failed, could not set creation date for news entry.\n");

	if($tempAdmin)
		$playerList[$item['author']]['record']->removeRole(Player::ADMIN);
}

echo "Done.\n";

// export bans
echo "Exporting bans... ";

foreach($bansList as $ban) {
	$thisBan = Ban::addBan($ban['league'] == "ducati" ? $ducatiBanTarget->getId() : $guBanTarget->getId(), $playerList[$ban['author']]['record']->getId(), NULL, $ban['text']);

	$queryResult = $bzionConnection->query('UPDATE bans SET created="'.date("Y-m-d H:i:s", $ban['timestamp']).'", updated="'.date("Y-m-d H:i:s", $ban['timestamp']).'" WHERE id='.$thisBan->getId());
	if(! $queryResult)
		die("Failed, could not set creation date for ban entry.\n");
}

if(! $bzionConnection->query('UPDATE players SET status="deleted" WHERE id = '.$ducatiBanTarget->getId()))
	die("Failed, unable to delete dummy player record for ducati bans.\n");
if(! $bzionConnection->query('UPDATE players SET status="deleted" WHERE id = '.$guBanTarget->getId()))
	die("Failed, unable to delete dummy player record for GU bans.\n");

echo "Done.\n";

// export private messages
echo "Exporting private messages... ";

$conversationsByHash = Array();
foreach($privateMessages as $message) {
//if(! in_array(9972, $message['individual_recipients']) && $message['author'] != 9972 && ! in_array(1167, $message['gu_team_recipients']))
//	continue;

	$individualRecipients = Array();
	foreach($message['individual_recipients'] as $recipientBZID)
		if($recipientBZID != $message['author']) // only add the author once at the end
			array_push($individualRecipients, $playerList[$recipientBZID]['record']);
	array_push($individualRecipients, $playerList[$message['author']]['record']);

	$teamRecipientIndexes = Array();
/*
	foreach($message['ducati_team_recipients'] as $teamID)
		array_push($teamRecipientIndexes, $ducatiTeamIndexesByTeamID[$teamID]);
	foreach($message['gu_team_recipients'] as $teamID)
		array_push($teamRecipientIndexes, $guTeamIndexesByTeamID[$teamID]);
*/

	foreach($message['team_member_recipients'] as $recipientBZID) {
		if(in_array($recipientBZID, $message['individual_recipients'])) // don't duplicate individual/team recipients
			continue;
		if($recipientBZID == $message['author']) // don't add the author twice
			continue;

		$inATeam = FALSE;
		foreach($teamRecipientIndexes as $teamIndex) {
			if(in_array($recipientBZID, $teamList[$teamIndex]['members'])) {
				$inATeam = TRUE;
				break;
			}
		}

//		if(! $inATeam)
			array_push($individualRecipients, $playerList[$recipientBZID]['record']);
	}

	// messages to only ourselves aren't supported in BZION, so add a dummy recipient if applicable
	if(count($individualRecipients) == 1 && count($teamRecipientIndexes) == 0)
		array_push($individualRecipients, $dummyMessageTarget);

	// create a hash of the topic/recipients so we can join associated messages
	$thisHash = "TOPIC/".
		preg_replace("/(Re:\s?)*/", "", $message['subject']).
		"/TEAMS/";
	if(count($teamRecipientIndexes) > 0) {
		sort($teamRecipientIndexes);
		$thisHash .= implode("/", $teamRecipientIndexes);
	}
	$thisHash .= "/PLAYERS/";
	if(count($individualRecipients) > 0) {
		$individualRecipientBZIDs = Array();
		foreach($individualRecipients as $recipient)
			array_push($individualRecipientBZIDs, $recipient->getBZID());
		sort($individualRecipientBZIDs);
		$thisHash .= implode("/", $individualRecipientBZIDs);
	}
	if(array_key_exists($thisHash, $conversationsByHash)) {
		$thisConversation = $conversationsByHash[$thisHash];
	} else {
		$thisConversation = Conversation::createConversation($message['subject'], $playerList[$message['author']]['record']->getId(), $individualRecipients);

		foreach($teamRecipientIndexes as $teamIndex) {
			$thisConversation->addMember($teamList[$teamIndex]['record']);
			die("Failed, team recipients are supposed to be disabled, but one was encountered.\n");
		}
		$conversationsByHash[$thisHash] = $thisConversation;
	}

	$thisConversation->sendMessage($playerList[$message['author']]['record'], $message['message']);

	$queryResult = $bzionConnection->query('UPDATE messages SET timestamp="'.date("Y-m-d H:i:s", $message['timestamp']).'" WHERE conversation_to='.$thisConversation->getId());
	if(! $queryResult)
		die("Failed, could not set date for message entry.\n");

	$queryResult = $bzionConnection->query('UPDATE conversations SET last_activity="'.date("Y-m-d H:i:s", $message['timestamp']).'" WHERE id='.$thisConversation->getId());
	if(! $queryResult)
		die("Failed, could not set last updated date for conversation entry.\n");
}

if(! $bzionConnection->query('UPDATE players SET status="deleted" WHERE id = '.$dummyMessageTarget->getId()))
	die("Failed, unable to delete dummy player record for messages to self.\n");

foreach($playerList as $player)
	$player['record']->markMessagesAsRead();

echo "Done.\n";

// export complete
echo "\nExport complete.\n\n";

// finish
$bzionConnection->close();
$ducatiConnection->close();
$guConnection->close();

echo "Done.\n";

?>

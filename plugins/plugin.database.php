<?php
ManiaControl::addChatCommand('test', 'test', true);
ManiaControl::registerEvent('StartUp', 'db_StartUp');
ManiaControl::registerEvent('ModeScriptCallback', 'db_ModeScriptCallback');
ManiaControl::registerEvent('PlayerConnect', 'db_PlayerConnect');
ManiaControl::registerEvent('PlayerDisconnect', 'db_PlayerDisconnect');

function c_test($control, $sender, $command, $params) {
  if(empty($params)) {
    showScores($control);
  } else {
    resetScore($control);
  }
}

function db_StartUp($control) {
  global $dbc, $whats, $mlid;
  try {
    $dbc = new PDO("mysql:host=".$control->settings['dB_host'].";dbname=".$control->settings['dB_name'], $control->settings['dB_user'], $control->settings['dB_password']);
  } catch(Exception $e) {
    echo "[plugin.database.php] Could not connect to the MySQL server:".nl;
    echo $e->getMessage();
  }
  $mlid = 221;
  $whats = array('captures' => 'Top Captures', 'deaths' => 'Most Deaths', 'hits' => 'Most Hits', 'playtime' => 'Top Playtime');
  
  $dbc->exec("
    CREATE TABLE IF NOT EXISTS `players` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `login` varchar(255) CHARACTER SET utf8 NOT NULL,
      `nickname` varchar(255) CHARACTER SET utf8 NOT NULL,
      `nation` varchar(255) CHARACTER SET utf8 NOT NULL,
      `respawns` int(11) NOT NULL,
      `deaths` int(11) NOT NULL,
      `hits` int(11) NOT NULL,
      `got_hit` int(11) NOT NULL,
      `captures` int(11) NOT NULL,
      `firstvisit` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `lastvisit` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
      `visits` int(11) NOT NULL,
      `playtime` int(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM;"
  );
}

function db_PlayerConnect($control, $player, $params='') {
  global $dbc;
  
  $stmt = $dbc->prepare('SELECT 1 FROM players WHERE login = :login');
  $stmt->bindParam(':login', $player['Login']);
  $stmt->execute();
  if($stmt->fetchColumn() > 0) {
    $stmt = $dbc->prepare('UPDATE players SET lastvisit = NOW(), visits = visits+1, nickname = :nickname, nation = :nation WHERE login = :login');
    $stmt->bindParam(':login', $player['Login']);
    $stmt->bindParam(':nickname', $player['NickName']);
    $stmt->bindParam(':nation', $player['Path']);
    $stmt->execute();
  } else {
    //id,login,firstvisit,lastvisit,visits,playtime,deaths,captures,hits,got_hit,respawns
    $stmt = $dbc->prepare('INSERT INTO players (login,nickname,nation,lastvisit,visits) VALUES (:login, :nickname, :nation, NOW(), 1)');
    $stmt->bindParam(':login', $player['Login']);
    $stmt->bindParam(':nickname', $player['NickName']);
    $stmt->bindParam(':nation', $player['Path']);
    $stmt->execute();
  }
}

function db_PlayerDisconnect($control, $player, $params) {
  global $dbc;
  
  $playtime = time() - $player['joined'];
  $stmt = $dbc->prepare('UPDATE players SET playtime = playtime+:playtime WHERE login = :login');
  $stmt->bindParam(':playtime', $playtime);
  $stmt->bindParam(':login', $player['Login']);
  $stmt->execute();
  
}

function db_ModeScriptCallback($control, $name, $params) {
  global $dbc;
  switch($name) {
    case 'playerDeath':
      $stmt = $dbc->prepare("UPDATE players SET deaths = deaths+1 WHERE login = :login");
      $stmt->bindParam(':login', $params);
    break;
    case 'poleCapture':
      $stmt = $dbc->prepare("UPDATE players SET captures = captures+1 WHERE login = :login");
      $stmt->bindParam(':login', $params);
    break;
    case 'playerHit':
      $players = explode(';', $param);
      $victim = str_replace('Victim:', '', $players[0]);
      $shooter = str_replace('Shooter:', '', $players[1]);
      $points = $players[2];
      $stmt = $dbc->prepare("UPDATE players SET hits = hits+1 WHERE login = :shooter; UPDATE player set got_hit = got_hit+1 WHERE login = :victim");
      $stmt->bindParam(':shooter', $points);
      $stmt->bindParam(':victim', $victim);
    break;
    case 'playerRespawn':
      $stmt = $dbc->prepare("UPDATE players SET respawns = respawns+1 WHERE login = :login");
      $stmt->bindParam(':login', $params);
    break;
    case 'beginRound':
    case 'beginMap':
      resetScore($control);
    break;
    case 'endRound':
    case 'endMap':
      showScores($control);
    break;
  }
  if(isset($stmt)) {
    $stmt->execute();
  }
}

function showScores($control, $limit=6) {
  global $mlid, $whats;
  $lheight = 1.7;
  
  // Template copyright by undef.de - Take a look at the awesome Records eyepiece plugin for XAseco: http://www.labs.undef.de/XAseco2/Records-Eyepiece.php
  $widgetheight = ($lheight * $limit + 3.3);
  $x = 47.5;
  $tpl  = '<manialink id="%id%">';
	$tpl .= '<frame posn="-63.5 %x% 0" id="Container3444">';
	$tpl .= '<quad posn="0 0 0.001" sizen="15.5 '.$widgetheight.'" style="BgsPlayerCard" substyle="ProgressBar"/>';

	// Icon and Title
	$tpl .= '<quad posn="0.4 -0.36 0.002" sizen="14.7 2" style="BgsPlayerCard" substyle="BgRacePlayerName"/>';
	$tpl .= '<quad posn="0.6 -0.15 0.004" sizen="2.5 2.5" style="Icons128x128_1" substyle="Rankings"/>';
	$tpl .= '<label posn="3.2 -0.65 0.004" sizen="10.2 0" textsize="1" text="%title%"/>';
	$tpl .= '<format textsize="1" textcolor="FFFF"/>';
  $tpl .= '%body%';
	$tpl .= '</frame>';
	$tpl .= '</manialink>';
  
  $i = 0;
  $xml = '';
  foreach($whats as $what => $title) {
    $scores = getTop($what);
    $body = "";
    $line = 0;
    $offset = 3;
    
    foreach ($scores as &$item) {
      $y = number_format(($lheight * $line + $offset), 1, '.', ',');
      $body .= '<label posn="4 -'.$y.' 0.002" sizen="3.4 1.7" halign="right" scale="0.9" textcolor="DDDF" text="'.formatScore($item[1], $what).'"/>';
      $body .= '<label posn="4.65 -'.$y.' 0.002" sizen="11.1 1.7" scale="0.9" text="'.$item[0].'"/>';

      $line ++;
      if ($line >= $limit) {
        break;
      }
    }
    $xml .= str_replace("%body%", $body, str_replace("%title%", $title, str_replace("%x%", $x, str_replace("%id%", $mlid.$i, $tpl))));
    $x -= $widgetheight+1;
    $i++;
  }
  $control->client->query('SendDisplayManialinkPage', $xml, 0, false);

}

function resetScore($control) {
  global $mlid, $whats;
  
  $i = 0;
  $xml = '';
  foreach($whats as $what) {
    $xml .= '<manialink id="'.$mlid.$i.'"></manialink>';
    $i++;
  }
  $control->client->query('SendDisplayManialinkPage', $xml, 0, false);
}

function formatScore($score, $what) {
  switch($what) {
    case 'playtime':
      return number_format(round($score / 3600), 0, '.', ' ').' h';
    default:
      return $score;
  }
}

function getTop($what, $limit=6) {
  global $dbc;
  
  $stmt = $dbc->prepare("SELECT nickname, ".$what." FROM players ORDER BY ".$what." DESC LIMIT ".$limit);
  $stmt->bindParam(':what', $what);
  $stmt->bindParam(':limit', $limit);
  $stmt->execute();
  
  return $stmt->fetchAll();
}


?>

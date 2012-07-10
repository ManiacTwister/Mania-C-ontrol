<?php
ManiaControl::registerEvent('PlayerConnect', 'basic_PlayerConnect');
ManiaControl::registerEvent('PlayerDisconnect', 'basic_PlayerDisconnect');
ManiaControl::registerEvent('BeginMap', 'basic_BeginMap');
ManiaControl::registerEvent('EndMap', 'basic_EndMap');
ManiaControl::registerEvent('ModeScriptCallback', 'basic_ModeScriptCallback');

function basic_PlayerConnect($control, $player) {
  if(!$control->isStartup) {
    $control->client->query('ChatSendServerMessage', '$z$sNew Player: '.$player['NickName'].'$z$s Zone: $fff'.$player['Path'].'$z$s Ladder: $fff'.$player['LadderStats']['PlayerRankings'][0]['Ranking']);
    $control->client->query('GetServerName');
    $response = $control->client->getResponse();
    $control->client->query('ChatSendServerMessageToLogin', '$z$sWelcome on '.$response.nl.'$z$sThis Server is running with $l[https://github.com/ManiacTwister/Mania-C-ontrol]$o$aaaMania$09f[C]$aaaontrol$l!', $player['Login']);
  }
  $control->sendConsole('>| player joined the game ['.$player['Login'].' : '.$player['Path'].' : '.$player['LadderStats']['PlayerRankings'][0]['Ranking'].' : '.$player['IPAddress'].']',4);
}
function basic_PlayerDisconnect($control, $player) {
  $control->client->query('ChatSendServerMessage', '$z$sPlayer Left: '.$player['NickName'].'$z');
  $control->sendConsole('|< player left the game ['.$player['Login'].' : '.$player['Path'].' : '.$player['LadderStats']['PlayerRankings'][0]['Ranking'].' : '.$player['IPAddress'].']',4);
}

function basic_BeginMap($control, $map) {
  global $lastmap;
  
  if(!isset($lastmap) || empty($lastmap)) {
    $lastmap = 'None';
  }
  $control->sendConsole('map changed ['.$lastmap.'] >> ['.$map['Name'].']',4);
}

function basic_EndMap($control, $map) {
  global $lastmap;
  
  $lastmap = $map['Name'];
  $control->sendConsole('endMap',4);
}

function basic_ModeScriptCallback($control, $name, $params) {
  switch($name) {
    case 'beginRound':
      $control->sendConsole('beginRound',4);
    break;
    case 'endRound':
      $control->sendConsole('endRound',4);
    break;
  }
}
?>

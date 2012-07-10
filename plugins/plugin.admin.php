<?php
ManiaControl::addChatCommand('admin', 'Admin command', true);

function c_admin($control, $sender, $command, $params) {
  switch($params[0]) {
    case 'setmodescript':
      setmodescript($control, $sender, $params);
      break;
    case 'skip':
      $control->client->query('NextMap');
      $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s skips map');
      break;
    case 'restart':
      $control->client->query('RestartMap');
      $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s restarts map');
      break;
    case 'kick':
      kick($control, $sender, $params);
      break;
    case 'ban':
      ban($control, $sender, $params);
      break;
    case 'unban':
      ban($control, $sender, $params);
      break;
    case 'blacklist':
      blacklist($control, $sender, $params);
      break;
    case 'unblacklist':
      unblacklist($control, $sender, $params);
      break;
    case 'cancelvote':
      $control->client->query('CancelVote');
      $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s canceled vote!');
      break;
    case 'planets':
      $control->client->query('GetServerPlanets');
      $control->sendMessageToLogin('Planets: '.$control->client->getResponse(), $sender);
      break;
  }
}

function setmodescript($control, $sender, $params) {
  if(isset($params[1])) {
    if($control->client->query('SetScriptName', $params[1])) {
      $control->sendMessageToLogin('Set script to '.$params[1].'.Script.txt', $sender);
    } else {
      $control->sendMessageToLogin('$f00Error setting script to '.$params[1].'.Script.txt', $sender);
    }
  } else {
    $control->sendMessageToLogin('Usage: /admin setmodescript <scriptname>', $sender);
  }
}

function kick($control, $sender, $params) {
  if(isset($params[1]) && $control->client->query('Kick', $params[1])) {
    $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s kicked '.$control->players[$params[1]]['NickName'], $sender);
  } else {
    $control->sendMessageToLogin('Usage: /admin kick <login>', $sender);
  }
}

function ban($control, $sender, $params) {
  if(isset($params[1]) && $control->client->query('Ban', $params[1])) {
    $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s banned '.$control->players[$params[1]]['NickName'], $sender);
  } else {
    $control->sendMessageToLogin('Usage: /admin ban <login>', $sender);
  }
}

function unban($control, $sender, $params) {
  if(isset($params[1]) && $control->client->query('UnBan', $params[1])) {
    $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s unbanned '.$params[1], $sender);
  } else {
    $control->sendMessageToLogin('Usage: /admin unban <login>', $sender);
  }
}

function blacklist($control, $sender, $params) {
  if(isset($params[1]) && $control->client->query('BlackList', $params[1])) {
    $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s blacklists '.$control->players[$params[1]]['NickName'], $sender);
    $control->client->query('SaveBlackList', 'blacklist.txt');
    $control->client->query('Kick', $params[1]);
  } else {
    $control->sendMessageToLogin('Usage: /admin blacklist <login>', $sender);
  }
}

function unblacklist($control, $sender, $params) {
  if(isset($params[1]) && $control->client->query('UnBlackList', $params[1])) {
    $control->sendMessage('$z$sAdmin '.$control->players[$sender]['NickName'].'$z$s unblacklists '.$params[1], $sender);
    $control->client->query('SaveBlackList', 'blacklist.txt');
  } else {
    $control->sendMessageToLogin('Usage: /admin unblacklist <login>', $sender);
  }
}
?>

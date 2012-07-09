<?php
ManiaControl::registerEvent('StartUp', 'loadmessages');
ManiaControl::registerEvent('EverySecond', 'infomessages');

function loadmessages() {
  global $lastinfo, $messages;
  $lastinfo = 0;
  
  $messages = array();
  if($xml = @simplexml_load_file('config/infomessages.xml')){
    foreach($xml->messages->message as $message) {
      $messages[] = (string)$message;
    }
  } else { 
    die('[plugin.infomessages.php] Konnte Config File nicht parsen!'); 
  }
}

function infomessages($control) {
  global $lastinfo, $messages;
  if($lastinfo+300 < time()) {
    $control->client->query('SendNotice', '$z$s$09f[Info] $z$s$aaa'.$messages[array_rand($messages)], "", 2);
    $lastinfo = time();
  }
}

?>

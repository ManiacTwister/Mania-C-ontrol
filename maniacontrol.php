<?php
/*
 * maniacontrol.php
 * 
 * Copyright 2012 ManiacTwister <ManiacTwister@s7t.de>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * Some parts of this program are made based on the idea of the fantastic XAseco from Xymph available on http://www.xaseco.org/
 * 
 */

require_once('includes/GbxRemote.inc.php');
define('nl', "\r\n");
@set_time_limit(0);


class ManiaControl {
  public $settings, $isStartup = true, $run = true;
  
  function __construct() {    
    $this->loadSettings();
        
    $this->client = new IXR_Client_Gbx();
    echo 'Connecting to ' . strval($this->settings['ip']) . ':' . strval($this->settings['port']) . ' ...' . nl;
    if (!$this->client->InitWithIp(strval($this->settings['ip']), intval($this->settings['port']))) {
        $this->errorcheck();
        die('[Error] Could not connect to the server!' . nl);
    }
    echo 'Login as ' . strval($this->settings['login']) . '...' . nl;
    if (!$this->client->query('Authenticate', strval($this->settings['login']), strval($this->settings['password']))) {
        $this->errorcheck();
        die('[Error] Wrong username and/or password!' . nl);
    }
    echo 'Enabling callbacks ...' . nl;
    if (!$this->client->query('EnableCallbacks', true)) {
        $this->errorcheck();
        die('[Error] Could not activate callbacks!' . nl);
    }
    echo 'Setting API version ...'.nl;
    if(!$this->client->query('SetApiVersion', '2012-06-19')) {
        $this->errorcheck();
        die('[Error] Could not set API version' . nl);
    }
    echo 'Resetting Manialinkpages ...' . nl;
    if (!$this->client->query('SendHideManialinkPage')) {
        $this->errorcheck();
        die('[Error] Could not reset Manialinkpages!' . nl);
    }        
    echo '... Done!' . nl;
    $this->client->query('GetSystemInfo');
    $response = $this->client->getResponse();
    echo '######################'. nl;
    echo '# Connected with:' . nl;
    echo '# IP: '.$response['PublishedIp'].':'.$response['Port'] .nl;
    echo '# Login: '.$response['ServerLogin'] .nl;
    echo '######################'. nl;
    $this->client->query('EnableCallbacks', true);
    $this->client->query('ChatSendServerMessage', '$o$aaaMania$09f[C]$aaaontrol started!');
    $this->serverlogin = $response['ServerLogin'];
    $this->releaseEvent('StartUp', $this);
    $this->loadPlayers();
    $this->isStartup = false;
  }

  function run() {
    while ($this->run) {
      $this->client->readCB(); 
      $this->errorcheck();
      $calls = $this->client->getCBResponses();
      foreach($calls as $call) {
        $name = $call[0];
        $data = $call[1];
        set_time_limit(20);
        switch ($name) {
          case 'ManiaPlanet.PlayerConnect':
            $this->client->query('GetDetailedPlayerInfo', $calls[0][1][0]);
            $response = $this->client->getResponse();
            $response['joined'] = time();
            $this->players[$calls[0][1][0]] = $response;
            $this->releaseEvent('PlayerConnect', $response, '');
            $this->client->query('ChatSendServerMessage', '$z$sNew Player: '.$response['NickName'].'$z$s Zone: $fff'.$response['Path'].'$z$s Ladder: $fff'.$response['LadderStats']['PlayerRankings'][0]['Ranking']);
            $this->client->query('GetServerName');
            $response = $this->client->getResponse();
            $this->client->query('ChatSendServerMessageToLogin', '$z$sWelcome on '.$response.nl.'$z$sThis Server is running with $l[http://github.com/maniactwister/maniacontrol]$o$aaaMania$09f[C]$aaaontrol$l!', $calls[0][1][0]);
            break;
          case 'ManiaPlanet.PlayerDisconnect':
            $this->releaseEvent('PlayerDisconnect', $this->players[$calls[0][1][0]], '');
            $this->client->query('ChatSendServerMessage', '$z$sPlayer Left: '.$this->players[$calls[0][1][0]]['NickName'].'$z');
            unset($this->players[$calls[0][1][0]]);
            break;
          case 'ManiaPlanet.ModeScriptCallback':
            $this->releaseEvent('ModeScriptCallback', $calls[0][1][0], (isset($calls[0][1][1]) ? $calls[0][1][1] : ''));
            break;
          case 'ManiaPlanet.PlayerChat':
            if($this->StartsWith($calls[0][1][2], "/")) {
              $command = explode(" ", $calls[0][1][2], 2);
              if(isset($command[1])) {
                $params = explode(" ", $command[1]);
              } else {
                $params = array();
              }
              $this->handleCommand($calls[0][1][1], str_replace("/", "", $command[0]), $params);
            } else {
              $this->releaseEvent('PlayerChat', $calls[0][1]);
            }
            break;
            default:
            
          // TODO: Implement all callbacks
        }
        $this->errorcheck();
        usleep(1);
      }
      $this->releaseEvent('EverySecond');
      usleep(1000);
    }
  }
  
  function errorcheck() {
    if ($this->client->isError()) {
      echo '[Client Message ' . strval($this->client->getErrorCode()) . '] ' . strval($this->client->getErrorMessage()) . nl;
      $this->client->resetError();
    }
  }
  
  function loadSettings($config = 'config/config.xml') {
    $this->settings = array();
    if($xml = @simplexml_load_file($config)){
      $this->settings['ip'] = $xml->ip;
      $this->settings['port'] = $xml->port;
      $this->settings['login'] = $xml->login;
      $this->settings['password'] = $xml->password;
      $this->settings['port'] = $xml->port;
      $this->settings['dB_host'] = $xml->db_host;
      $this->settings['dB_user'] = $xml->db_user;
      $this->settings['dB_password'] = $xml->db_password;
      $this->settings['dB_name'] = $xml->db_name;
      $this->settings['plugins'] = $xml->plugins;
      foreach($xml->admins->admin as $admin) {
        $this->settings['admins'][] = (string)$admin;
      }
      $this->loadPlugins();
      
      echo '###############################################################################'. nl;
      echo '#'. nl;
      echo '# Mania[C]ontrol running on'.$this->settings['ip'].':'.$this->settings['port']. nl;
      echo '#'. nl;
      echo '# Author: ManiacTwister (Some ideas from XAseco by Xymph)'. nl;
      echo '#'. nl;
      echo '###############################################################################'. nl;
      echo 'Loading config file ...' . nl;
    } else { 
      die('[Error] Could not load config file!' . nl); 
    }
  }
  
  function loadPlugins() {
    foreach($this->settings['plugins']->plugin as $plugin) {
      require_once('plugins/' . $plugin);
      $this->plugins[] = $plugin;
      echo "Loading [".$plugin."] ...".nl;
    }
  }
  
  function registerEvent($event_type, $event_func) {
    $this->events[$event_type][] = $event_func;
  }

  function releaseEvent($event_type, $func_param='', $extra='') {
    if (!empty($this->events[$event_type])) {
      foreach ($this->events[$event_type] as $func_name) {
        if (is_callable($func_name)) {
          call_user_func($func_name, $this, $func_param, $extra);
        }
      }
    }
  }
  
  function addChatCommand($command_name, $help, $command_is_admin = false) {
    $chat_command = new ChatCommand($command_name, $help, $command_is_admin);
    $this->chat_commands[$command_name] = $chat_command;
  }
  
  function handleCommand($login, $command, $params='') {
  if(!isset($this->chat_commands[$command])) return;
    if(!$this->chat_commands[$command]->isadmin||in_array($login, $this->settings['admins'])) {
      $func_name = str_replace('+', 'plus', 'c_' . $command);
      $func_name = str_replace('-', 'dash', $func_name);
      if (function_exists($func_name)&&is_callable($func_name)) {
        call_user_func($func_name, $this, $login, $command, $params);
      }
    }
  }
  
  function loadPlayers() {
    $this->client->query('GetPlayerList', 300, 0);
    $response['playerlist'] = $this->client->getResponse();
    
    foreach($response['playerlist'] as $player) {
      if($player['Login'] == $this->serverlogin) continue;
      
      $this->client->query('GetDetailedPlayerInfo', $player['Login']);
      $response = $this->client->getResponse();
      $response['joined'] = time();
      $this->players[$player['Login']] = $response;
      $this->releaseEvent('PlayerConnect', $response, '');
      
    }
  }
  
  function StartsWith($Haystack, $Needle){
    return strpos($Haystack, $Needle) === 0;
  }
}

class ChatCommand {
  var $name;
  var $help;
  var $isadmin;

  function ChatCommand($name, $help, $isadmin=false) {
    $this->name = $name;
    $this->help = $help;
    $this->isadmin = $isadmin;
  }
}

$mc = new ManiaControl;
$mc->run();
?> 

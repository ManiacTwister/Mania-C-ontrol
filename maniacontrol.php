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
ini_set('max_execution_time', 0);
ini_set('max_input_time', -1);

class ManiaControl {
  public $settings, $isStartup = true, $run = true;
  
  function __construct() {    
    $this->loadSettings();
        
    $this->client = new IXR_Client_Gbx();
    $this->sendConsole('Connecting to ' . strval($this->settings['ip']) . ':' . strval($this->settings['port']) . ' ...');
    if (!$this->client->InitWithIp(strval($this->settings['ip']), intval($this->settings['port']))) {
        $this->errorcheck();
        $this->sendConsole('[Error] Could not connect to the server!', 3);
    }
    $this->sendConsole('Login as ' . strval($this->settings['login']) . '...');
    if (!$this->client->query('Authenticate', strval($this->settings['login']), strval($this->settings['password']))) {
        $this->errorcheck();
        $this->sendConsole('[Error] Wrong username and/or password!', 3);
    }
    $this->sendConsole('Enabling callbacks ...');
    if (!$this->client->query('EnableCallbacks', true)) {
        $this->errorcheck();
        $this->sendConsole('[Error] Could not activate callbacks!', 3);
    }
    $this->sendConsole('Setting API version ...');
    if(!$this->client->query('SetApiVersion', '2012-06-19')) {
        $this->errorcheck();
        $this->sendConsole('[Error] Could not set API version', 3);
    }
    $this->sendConsole('Resetting Manialinkpages ...');
    if (!$this->client->query('SendHideManialinkPage')) {
        $this->errorcheck();
        $this->sendConsole('[Error] Could not reset Manialinkpages!', 3);
    }        
    $this->sendConsole('... Done!');
    $this->client->query('GetSystemInfo');
    $response = $this->client->getResponse();
    $this->sendConsole(
    '######################'.nl.
    '# Connected with:'.nl.
    '# IP: '.$response['PublishedIp'].':'.$response['Port'].nl.
    '# Login: '.$response['ServerLogin'].nl.
    '######################'
    , 4);
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
        switch ($name) {
          case 'ManiaPlanet.PlayerConnect':
            $this->client->query('GetDetailedPlayerInfo', $call[1][0]);
            $response = $this->client->getResponse();
            $response['joined'] = time();
            $this->players[$call[1][0]] = $response;
            $this->releaseEvent('PlayerConnect', $response, '');
            break;
          case 'ManiaPlanet.PlayerDisconnect':
            $this->releaseEvent('PlayerDisconnect', $this->players[$call[1][0]], '');
            unset($this->players[$call[1][0]]);
            break;
          case 'ManiaPlanet.ModeScriptCallback':
            $this->releaseEvent('ModeScriptCallback', $call[1][0], (isset($call[1][1]) ? $call[1][1] : ''));
            break;
          case 'ManiaPlanet.PlayerChat':
            if($this->StartsWith($call[1][2], "/")) {
              $command = explode(" ", $call[1][2], 2);
              if(isset($command[1])) {
                $params = explode(" ", $command[1]);
              } else {
                $params = array();
              }
              $this->handleCommand($call[1][1], str_replace("/", "", $command[0]), $params);
            } else {
              $this->releaseEvent('PlayerChat', $call[1]);
            }
            break;
          case 'ManiaPlanet.PlayerInfoChanged':
            $this->releaseEvent('PlayerInfoChanged', $call[1][0]);
            break;
          case 'ManiaPlanet.VoteUpdated':
            $this->releaseEvent('VoteUpdated', $call[1]);
            break;
          case 'ManiaPlanet.BeginMap':
            $this->releaseEvent('BeginMap', $call[1][0]);
            break;
          case 'ManiaPlanet.EndMap':
            $this->releaseEvent('EndMap', $call[1][0]);
            break;
          case 'ManiaPlanet.StatusChanged':
            $this->releaseEvent('StatusChanged', $call[1]);
            break;
          case 'ManiaPlanet.MapListModified':
            $this->releaseEvent('MapListModified', $call[1]);
            break;
          default:
            //print_r($call);
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
      $this->sendConsole('Loading config file ...');
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
      $this->sendConsole('... Done!');
      $this->loadPlugins();
      $this->sendConsole(
        '###############################################################################'.nl.
        '#'.nl.
        '# Mania[C]ontrol running on '.$this->settings['ip'].':'.$this->settings['port'].nl.
        '#'.nl.
        '# Author: ManiacTwister (Some ideas from XAseco by Xymph)'. nl.
        '#'.nl.
        '###############################################################################'
      , 4);
    } else { 
      $this->sendConsole('Could not load config file!', 3); 
    }
  }
  
  function loadPlugins() {
    foreach($this->settings['plugins']->plugin as $plugin) {
      require_once('plugins/' . $plugin);
      $this->plugins[] = $plugin;
      $this->sendConsole('Loading ['.$plugin.'] ...');
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
  
  function sendConsole($message, $level=0) {
    switch($level) {
      case 0:
        echo '[Info] '.$message.nl;
      break;
      case 1:
        echo '[Warning] '.$message.nl;
      break;
      case 2:
        echo '[Error] '.$message.nl;
      break;
      case 3:
        die('[Fatal Error] '.$message);
      break;
      case 4:
        echo $message.nl;
      break;
    }
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

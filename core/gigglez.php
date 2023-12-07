<?php

/*=====================================
    Gigglez IRC Bot
    Developed and maintained
    By [REDACTED]
    http://[REDACTED]
    [REDACTED]@[REDACTED]
    [REDACTED]@[REDACTED]
    #[REDACTED] @ irc.gamesurge.net
=====================================*/

/*=================================================================================================================
    This work is licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.
    To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/3.0/
    or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View, California, 94041, USA.
==================================================================================================================*/

// Define some constants for catching IRC error messages
define("IRC_NOTEXTTOSEND", 412);
define("IRC_INVITEONLY", 473);
define("IRC_BANNED", 474);
define("IRC_BADCHANNELKEY", 475);

// Modify include path for local PEAR install
set_include_path('/home/gigglez/pear/php'.PATH_SEPARATOR.get_include_path());

include('Net/DNS.php');
include('Net/GeoIP.php');

// Include the logger class
require_once("includes/logger.class.php");

// Path information
$root = str_replace('/core', '', getcwd());

$path['root'] = $root;
$path['core'] = $root."/core";
$path['logs'] = $root."/logs";
$path['docs'] = $root."/docs";
$path['mods'] = $root."/modules";
$path['incs'] = $root."/includes";


// Create our logger object
$corelog = new Logger($path['logs']."/core.log");

// Set the loglevel. Default is >=INFO, but we want debug logging
$corelog->set_loglevel(DEBUG);

$corelog->write(DEBUG, "Starting up in ".getcwd());

// Record start time
$script_start = time();

// Include Spyc (http://code.google.com/p/spyc/) for parsing YAML config
$corelog->write(DEBUG, "Checking for Spyc class...");
if(file_exists("includes/spyc.php"))
{
    $corelog->write(DEBUG, "Spyc class found");
    require_once("includes/spyc.php");
}
else
{
    $corelog->write(FATAL, "Spyc class file not found; cannot load config");
}

/*=====================
    Config Section
=====================*/

$corelog->write(DEBUG, "Checking for config.yml...");
if(file_exists("config.yml"))
{
    $corelog->write(DEBUG, "config.yml found; loading configuration data");
    $config = Spyc::YAMLLoad("config.yml");            // Load configuration variables from file (config.yml)
}
else
{
    $corelog->write(FATAL, "config.yml not found; cannot continue without configuration");
}

// Bot variables
$bind        = $config['bot']['bind'];            // Set the IP address to bind to
$server        = $config['bot']['server'];            // Set the IRC server to connect to
$port        = $config['bot']['port'];            // Set the port to use
$nick        = $config['bot']['nick'];            // Set the nickname for the bot
$logging    = $config['bot']['logging'];        // Toggle keeping log files
$chans        = $config['bot']['channels'];        // Set channels to join after connecting

// MySQL connection variables
$mysql_host = $config['mysql']['host'];
$mysql_user = $config['mysql']['user'];
$mysql_pass = $config['mysql']['pass'];
$mysql_db    = $config['mysql']['db'];



/*===============
    Includes
===============*/

$corelog->write(INFO, "Loading includes");
foreach($config['include'] as $include)
{
    $corelog->write(INFO, "[INCLUDE] $include");
    include("includes/".$include);
}

// Create the user handler object
$user = new User_Handler($mysql_host, $mysql_user, $mysql_pass, $mysql_db, $path['logs']."/auth.log");

/*==========================
    Start the bot code
==========================*/

echo "\n-----------------\nStarting up...\n-----------------\n\n";

// Prevent PHP from stopping the script after 30 sec
set_time_limit(0);

// Set error reporting level
error_reporting(E_ALL ^ E_NOTICE);

// Set the IP address to bind the socket to
$socket_options = array('socket' => array('bindto' => $bind));

// Pass the socket options
$socket_context = stream_context_create($socket_options);

$corelog->write(DEBUG, "Attempting to create socket...");

// Create the socket...
$socket = stream_socket_client($server.':'.$port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $socket_context);

if(!$socket)
{
    // ...or die on error
    $corelog->write(FATAL, "Socket creation failed; $errstr ($errno)");
}

$corelog->write(DEBUG, "Socket created; sending IRC registration data");

// Send nick/user registration to the server
fputs($socket,"NICK $nick\n");
fputs($socket,"USER $nick $nick $nick $nick :$nick\n");

// Color parsing
$font = array(
    '[c]'    =>    "\x03",
    '[n]'    =>    "\x0f",
    '[b]'    =>    "\x02",
    '[u]'    =>    "\x1f",
    '[r]'    =>    "\x16"
);

// sends messages like "JOIN #mychannel" or "QUIT byebye"
function sendsimple($what, $val) {
    global $socket;
    fputs($socket, "$what $val\n");
    echo "--> $what $val\n";
    return;
}

// sends messages to channels, users etc like "PRIVMSG #mychannel :this is a test"
function send($what, $where, $message, $color = true) {
    global $socket, $font;
 
    if($color) {
        $message = strtr($message, $font);
    }
 
    fputs($socket, "$what $where :$message\n");
    echo "--> $what @ $where :$message\n";
    return;
}

$rotated = true;

$corelog->write(INFO, "Startup good; entering main loop");

// Install a signal handler
// This ignores signals from child procs
// to prevent zombie childrens
pcntl_signal(SIGCHLD, SIG_IGN);

// Begin the infite loop
while(1)
{
    while($data = fgets($socket))
    {
        flush();
        
        $date_info = getdate();
        if($rotated == false && $date_info["hours"] == 0)
        {
            rotate_logs();
            $rotated = true;
        }
        elseif($rotated == true && $date_info["hours"] == 1)
        {
            $rotated = false;
        }
        
        //$user->connect();
        
        // IRC protocol syntax
        // :NICK!USER@HOST TYPE #CHAN MESSAGE

        // Separate the data
        $ex = explode(' ', $data);
        preg_match("/^:(.*?)!(.*?)@(.*?)[\s](.*?)[\s](.*?)[\s]:(.*?)$/",$data, $rawdata);
        preg_match("/^:(.*?)!(.*?)@(.*?)$/", $ex[0], $exIdent);

        // $rawdata array structure
        // [0] => :Gigglez2!~Gigglez2@[REDACTED].net
        // [1] => Gigglez2
        // [2] => ~Gigglez2
        // [3] => [REDACTED].net

        // This next bit is rather confusing
        // A lot of error-checking due to how the various events are formatted

        if($ex[0] == "ERROR")
        {
            array_shift($ex);
            $errstr = implode(' ', $ex);
            die($errstr);
        }

        if(empty($rawdata[1]))
        {
            $nick = $exIdent[1];
        }
        else
        {
            $nick = $rawdata[1];
        }
        if(empty($rawdata[2]))
        {
            $ident = $exIdent[2];
        }
        else
        {
            $ident = $rawdata[2];
        }
        if(empty($rawdata[3]))
        {
            $host = $exIdent[3];
        }
        else
        {
            $host = $rawdata[3];
        }
        if(empty($rawdata[4]))
        {
            $event = $ex[1];
        }
        else
        {
            $event = $rawdata[4];
        }
        if(empty($rawdata[5]))
        {
            $channel = preg_replace('/[\r]|[\n]/', '', $ex[2]);
        }
        else
        {
            $channel = $rawdata[5];
        }
        $message = rtrim($rawdata[6]);

        $word = explode(" ", $message);

        if($word[0] != ".login" && $word[0] != ".register")
        {
            // Write to the logfile
            if($logging) {
                //$date = date("n-j-y");
                $logfile = fopen($path['logs']."/irc.log", "a");
                fwrite($logfile, $data);
                fclose($logfile);
            }
        }

        // Reply to PING events from the server and print the event to STDOUT
        if($ex[0] == "PING") {
            echo "<-- PING?\n";
            fputs($socket, "PONG ".$ex[1]."\n");
            echo "--> PONG!\n";
        }
        
        // Separate channel messages and private messages
        if ($event == 'PRIVMSG' && $channel[0] != '#')
        {
            $event = 'QUERY';
        }

        // Identify an ACTION event
        if($message[0] == chr(1))
        {
            $message = strtr($message, array(chr(1) => '', 'ACTION' => ''));
            $event = 'ACTION';
        }

        if($word[0] != ".login" && $word[0] != ".register")
        {
            // Echo the incoming message/event to STDOUT
            echo "<-- $nick @ $channel - $event: $message\n";
        }

        // Fork to do module work without tying up the bot
        $pid = pcntl_fork();
        
        // As the child...
        if($pid == 0)
        {            
            $user->connect();
            
            // Retrieve the user's access level from the database
            if(!$user->get_access($rawdata[2]."@".$rawdata[3]))
            {
                $level = 1;
            }
            else
            {
                $level = $user->get_access($rawdata[2]."@".$rawdata[3]);
            }
            
            // Set up a hostmask var to use
            $hostmask = $rawdata[2]."@".$rawdata[3];

            // Callbacks for various events
            switch ($event)
            {
                case 'PRIVMSG':
                    on_text($nick, $message, $channel);
                    break;
                case 'QUERY':
                    on_query($nick, $message);
                    break;
                case 'NOTICE':
                    on_notice($nick, $message);
                    break;
                case 'MODE':
                    // We need some extra data
                    // $modes is the mode that changed (ie. "+c" or "-b")
                    // $modeArgs is an array containing arguments for the mode (ie. the hostmask for a ban)
                    $modes = $ex[3];
                    $mode_args = array_slice($ex, 4);
                    on_mode($nick, $channel, $modes, $mode_args);
                    break;
                case 'JOIN':
                    on_join($nick, $channel);
                    break;
                case 'ACTION':
                    on_action($nick, ltrim($message), $channel);
                    break;
                case 'PART':
                    on_part($nick, $message, $channel);
                    break;
                case IRC_NOTEXTTOSEND:
                    $corelog->write(WARN, "[IRC] No text to send");
                    break;
                case IRC_BADCHANNELKEY:
                    $corelog->write(WARN, "[IRC] Bad channel key (+k) ($ex[3])");
                    break;
                case IRC_INVITEONLY:
                    $corelog->write(WARN, "[IRC] Channel is invite only ($ex[3])");
                    break;
                case IRC_BANNED:
                    $corelog->write(WARN, "[IRC] Banned from channel ($ex[3])");
                    break;
                default:
                    break;
            }
            
            // Be sure we're not leaving orphaned DB connections or anything like that
            $user->disconnect();
            
            // Kill off the child process
            exit(0);
        }
    }
}

/*======================
    Event Callbacks
======================*/

// Called for every chat message sent
function on_text($nick, $message, $chan)
{
    global $script_start, $level, $user, $path, $config;

    // Create an array with each word of the message as an element
    // Used for parsing commands and arguments
    // $message is the entire message as a string
    // $word is the array with $word[0] being the first word and so forth
    $word = explode(" ", $message);
    $i = false;
    foreach($word as $oneword)
    {
        if($i)
        $wordsafterfirst .= $oneword.' ';
        $i = true;
    }

    // Load modules for commands sent via channel message
    $modules = glob("../modules/channel/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Called for every private message received
function on_query($nick, $message)
{
    global $level, $hostmask, $user;

    // Crate the word array again
    $word = explode(" ",$message);
    $i = false;
    foreach($word as $oneword)
    {
        if($i)
        $wordsafterfirst .= $oneword.' ';
        $i = true;
    }

    // Load modules for commands sent via private message
    $modules = glob("../modules/query/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }
    
    return;
}

// Called when an ACTION event happens
function on_action($nick, $message, $chan)
{
    global $level;

    // This function UN-tested
    // Extensive testing to come soon

    // Load modules for handling actions
    $modules = glob("../modules/action/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Called when a notice is received
// Used for autojoining channels -- see modules/notice/autojoin.mod
function on_notice($nick, $message)
{
    global $level, $chans, $config;

    // Crate the word array again
    $word = explode(" ",$message);
    $i = false;
    foreach($word as $oneword)
    {
        if($i)
        $wordsafterfirst .= $oneword.' ';
        $i = true;
    }
    
    // Load modules for handling notices
    $modules = glob("../modules/notice/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Called on mode change
function on_mode($nick, $chan, $modes, $modeArgs)
{
    global $level;

    $modules = glob("../modules/mode/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Called when a user joins a channel
function on_join($nick, $chan)
{
    global $level;

    $modules = glob("../modules/join/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Called when I user leaves the channel
function on_part($nick, $message, $chan)
{
    global $level;

    $modules = glob("../modules/part/*.girc");
    foreach($modules as $module)
    {
        include($module);
    }

    return;
}

// Used determine how long the bot has been running
// See the modules/channel/uptime.mod example
function duration($start, $end)
{
    /* Find out the seconds between each dates */
    $timestamp = $end - $start;
 
    /* Cleaver Maths! */
    $years = floor($timestamp / (60 * 60 * 24 * 360));
    $timestamp %= (60 * 60 * 24 * 360);
 
    $months = floor($timestamp / (60 * 60 * 24 * 30));
    $timestamp %= (60 * 60 * 24 * 30);
 
    $weeks = floor($timestamp / (60 * 60 * 24 * 7));
    $timestamp %= (60 * 60 * 24 * 7);
 
    $days = floor($timestamp / (60 * 60 * 24));
    $timestamp %= (60 * 60 * 24);
 
    $hours = floor($timestamp / (60 * 60));
    $timestamp %= (60 * 60);
 
    $mins = floor($timestamp / 60);
    $secs = $timestamp % 60;
 
    /* display */
 
    if($years >= 6)
        return "$years years";
    elseif($years >= 1)
        return "$years years, $months months";
    elseif($months >= 7)
        return "$months months";
    elseif($months >= 1)
        return "$months months, $days days";
    elseif($weeks >= 3)
        return "$weeks weeks";
    elseif($weeks >= 1)
        return "$weeks weeks, $days days";
    elseif($days >= 4)
        return "$days days";
    elseif($days >= 1)
        return "$days days, $hours hours";
    elseif($hours >= 7)
        return "$hours hours";
    elseif($hours >= 1)
        return "$hours hours and $mins minutes";
    elseif($mins >= 16)
        return "$mins minutes";
    elseif($mins >= 1)
        return "$mins minutes, $secs seconds";
    else
        return "$secs seconds";
}

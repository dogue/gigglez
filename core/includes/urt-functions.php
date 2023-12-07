<?php

// Urban Terror-related functions

function get_server($alias)
{
    $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
    mysql_select_db("[REDACTED]", $con);
    $result = mysql_query("SELECT * FROM g_servers WHERE ALIAS = '" . $alias . "';");
    $server = mysql_fetch_array($result);
    mysql_close($con);
    return $server;
}

function all_servers()
{
    $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
    mysql_select_db("[REDACTED]", $con);
    $servers = array();
    $result = mysql_query("SELECT * FROM g_servers");
    while ($row = mysql_fetch_array($result)) {
        array_push($servers, $row);
    }
    return $servers;
}

function add_server($address, $port, $password, $alias, $config, $b3, $logfile)
{
    $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
    mysql_select_db("[REDACTED]", $con);
    mysql_query("INSERT INTO g_servers (address, port, password, alias, config, b3, logfile) VALUES ($address, $port, $password, $alias, $config, $b3, $logfile");
    mysql_close($con);
    return;
}

function remove_server($alias)
{
    $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
    mysql_select_db("[REDACTED]", $con);
    mysql_query("DELETE FROM g_servers WHERE alias = $alias");
    mysql_close($con);
    return;
}

function get_baninfo($server, $search, $arg, $num)
{
    $bans = array();
    /*if($server == "main")
    {
        $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
        mysql_select_db("b3", $con);
    }
    elseif($server == "mod")
    {
        $con = mysql_connect("localhost", "[REDACTED]", "[REDACTED]");
        mysql_select_db("znineb3", $con);
    }*/
    $con = mysql_connect("localhost", $server['b3_user'], $server['b3_pass']);
    mysql_select_db($server['b3_db'], $con);
    switch ($arg) {
        case "":
            $result = mysql_query("SELECT * FROM " . $server['b3_prefix'] . "penalties WHERE client_id = '$search' HAVING type = 'Ban' OR type = 'TempBan' ORDER BY id DESC LIMIT 0,1");
            while ($entry = mysql_fetch_array($result)) {
                $expire = getdate($entry['time_expire']);
                $banned = getdate($entry['time_edit']);
                if ($entry['time_expire'] != "-1") {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | " . $expire['mon'] . "-" . $expire['mday'] . "-" . $expire['year'] . "/" . $expire['hours'] . ":" . $expire['minutes'] . ":" . $expire['seconds'] . " | " . $entry['reason'];
                } else {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | Permanent | " . $entry['reason'];
                }
                array_push($bans, $ban);
            }
            return $bans;
            break;
        case "a":
            $result = mysql_query("SELECT * FROM " . $server['db_prefix'] . "penalties WHERE client_id = '$search' HAVING type = 'Ban' OR type = 'TempBan'");
            while ($entry = mysql_fetch_array($result)) {
                $expire = getdate($entry['time_expire']);
                $banned = getdate($entry['time_edit']);
                if ($entry['time_expire'] != "-1") {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | " . $expire['mon'] . "-" . $expire['mday'] . "-" . $expire['year'] . "/" . $expire['hours'] . ":" . $expire['minutes'] . ":" . $expire['seconds'] . " | " . $entry['reason'];
                } else {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | Permanent | " . $entry['reason'];
                }
                array_push($bans, $ban);
            }
            return $bans;
            break;
        case "l":
            $result = mysql_query("SELECT * FROM " . $server['db_prefix'] . "penalties WHERE client_id = '$search' HAVING type = 'Ban' OR type = 'TempBan' ORDER BY id DESC LIMIT 0," . $num);
            while ($entry = mysql_fetch_array($result)) {
                $expire = getdate($entry['time_expire']);
                $banned = getdate($entry['time_edit']);
                if ($entry['time_expire'] != "-1") {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | " . $expire['mon'] . "-" . $expire['mday'] . "-" . $expire['year'] . "/" . $expire['hours'] . ":" . $expire['minutes'] . ":" . $expire['seconds'] . " | " . $entry['reason'];
                } else {
                    $ban = $entry['client_id'] . " | " . $entry['type'] . " | " . $banned['mon'] . "-" . $banned['mday'] . "-" . $banned['year'] . "/" . $banned['hours'] . ":" . $banned['minutes'] . ":" . $banned['seconds'] . " | Permanent | " . $entry['reason'];
                }
                array_push($bans, $ban);
            }
            return $bans;
            break;
    }
}

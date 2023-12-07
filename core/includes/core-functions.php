<?php

/*============================================
    This file contains some common
    functions used by the bot
    Mostly for things such as user handling
=============================================*/

// Used to set a user's access level in the database
function set_user($host, $level)
{
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;

    // MySQL connection
    $con = mysql_connect($mysql_host, $mysql_user, $mysql_pass);
    mysql_select_db($mysql_db, $con);
    
    // Attempt to retrieve the user's level
    $result = mysql_query("SELECT * FROM users WHERE hostmask = '$host'");
    $userdata = mysql_fetch_row($result);

    // If a match is found, update the entry
    if($userdata)
    {
        mysql_query("UPDATE users SET level = '$level' WHERE hostmask = '$host'");
    }
    // If a match is NOT found, add a new entry
    else
    {
        mysql_query("INSERT INTO users (hostmask, level) VALUES ('$host', '$level')");
    }

    // Close the database connection
    mysql_close($con);

    return;
}

// Used to retrieve the user's access level
function get_user($host)
{
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;

    // MySQL connection
    $con = mysql_connect($mysql_host, $mysql_user, $mysql_pass);
    mysql_select_db($mysql_db, $con);

    // Retrieve the data
    $result = mysql_query("SELECT * FROM users WHERE hostmask = '$host'");
    $userdata = mysql_fetch_row($result);

    // If userdata is found in the database, set the level
    // [0] => row id
    // [1] => hostmask
    // [2] => level
    // [3] => note
    if($userdata)
    {
        $level = $userdata[3];
    }
    // If no data is found, fall back to default level
    else
    {
        $level = 1;
    }

    // Close the database connection and return the level
    mysql_close($con);

    return $level;
}

// Used to remove a user entry completely
// Setting the level to default works the same
// This is useful for cleaning up the database
function del_user($host)
{
    global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;
    // MySQL connection
    $con = mysql_connect($mysql_host, $mysql_user, $mysql_pass);
    mysql_select_db($mysql_db, $con);

    // Remove all entries that match the hostmask
    mysql_query("DELETE FROM users WHERE hostmask = '$host'");

    // Close the database connection
    mysql_close($con);

    return;
}

?>

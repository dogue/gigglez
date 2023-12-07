<?php

// Log rotate function

function rotate_logs()
{
    global $path;
    
    $date = date("n-j-y");
    
    rename($path['logs']."/irc.log", $path['logs']."/irc/$date.log");
    rename($path['logs']."/auth.log", $path['logs']."/auth/$date.log");
    rename($path['logs']."/core.log", $path['logs']."/core/$date.log");
}

?>

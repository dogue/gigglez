<?php

// GIRC user handler class

// Constants for get_access function
// Determines whether to search by hostmask or username
// Default is hostmask
define("GET_BY_USER", true);
define("GET_BY_HOST", false);

// Constants for error return codes
define("ERR_USEREXISTS", 101);
define("ERR_NOSUCHUSER", 102);
define("ERR_BADPASS", 103);

class User_Handler
{
    // Local vars for the database connection reference
    private $db_host;
    private $db_user;
    private $db_pass;
    private $db_name;
    private $db_link;
    
    // Local reference to logger
    private $log;
    
    // Constructor; receive the credentials and connect to the database
    public function __construct($host, $user, $pass, $name, $log_path)
    {
        $this->log = new Logger($log_path);
        $this->db_host = $host;
        $this->db_user = $user;
        $this->db_pass = $pass;
        $this->db_name = $name;
    }
    
    // Destructor; close the database connection
    public function __destruct()
    {
        $this->db_link = null;
    }
    
    // Connect to the database server; called from __construct()
    public function connect()
    {
        try
        {
            $this->db_link = new PDO("mysql:host=".$this->db_host.";dbname=".$this->db_name, $this->db_user, $this->db_pass);
            $this->db_link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e)
        {
            $this->log->write(FATAL, "Database connection failed: ".$e->getMessage());
        }
    }
    
    public function disconnect()
    {
        $this->db_link = null;
    }
    
    // Checks if a specific username exists
    private function user_exists($username)
    {
        // Try the pull data for the given username from the database
        $query = "SELECT * FROM girc_logins WHERE girc_user = '$username'";
        $result = $this->db_link->query($query);
        
        // If there's data, return true because the user exists
        if(!empty($result->fetch()))
        {
            $result->closeCursor();
            return true;
        }
        // If there isn't data, return false because the user does not exist
        else
        {
            $result->closeCursor();
            return false;
        }
    }
    
    // Creates a new user in the database
    public function register($username, $password, $hostmask)
    {
        // Check if the username exists
        if(!$this->user_exists($username))
        {
            // It doesn't; hash the given password and insert the data into the database
            $password_hash = hash("sha512", $password);
            
            $query = "INSERT INTO girc_logins (girc_user, girc_pass, irc_mask) VALUES ('$username', '$password_hash', '$hostmask')";
            $result = $this->db_link->query($query);
            $result->closeCursor();
            
            $query = "INSERT INTO girc_access (girc_user, girc_priv) VALUES ('$username', 1)";
            $result = $this->db_link->query($query);
            $result->closeCursor();
            
        /*    mysql_query("INSERT INTO girc_logins (girc_user, girc_pass, irc_mask) VALUES ('$username', '$password_hash', '$hostmask')");
            mysql_query("INSERT INTO girc_access (girc_user, girc_priv) VALUES ('$username', 1)", $this->db_link);
        */
            // Write some info to the log
            $this->log->write(INFO, "REGISTERED: " . $username);

            return 0;
        }
        else
        {
            // The username exists, so return error
            // We'll use this in the module to determine success/failure
            return ERR_USEREXISTS;
        }
    }
    
    // Stores user's current hostmask
    public function login($username, $password, $hostmask)
    {
        if(!$this->user_exists($username))
        {
            return ERR_NOSUCHUSER;
        }
        
        $query = "SELECT girc_pass FROM girc_logins WHERE girc_user = '$username' LIMIT 1";
        $result = $this->db_link->query($query);

        $stored_hash = $result->fetch()[0];
        $given_hash = hash("sha512", $password);
        $result = null;
        
        if($given_hash !== $stored_hash)
        {
            $this->log->write(WARN, "AUTH FAILURE: " . $username . " - " . $hostmask);
            return ERR_BADPASS;
        }
        
        $query = "SELECT time FROM girc_logins WHERE girc_user = '$username' ORDER BY time ASC";
        $result = $this->db_link->query($query);
        $user_data = $result->fetch()[0];
        
        if($result->rowCount() == 3)
        {
            $this->db_link->query("UPDATE girc_logins SET irc_mask = '$hostmask' WHERE girc_user = '$username' AND time = '$user_data[0]'");
        }
        else
        {
            $this->db_link->query("INSERT INTO girc_logins (girc_user, girc_pass, irc_mask) VALUES ('$username', '$stored_hash', '$hostmask')");
        }
        
        return 0;
    }
    
    // Set's a user's level in the access table
    public function set_access($username, $access_level)
    {
        // Check that the user exists first, just in case
        if($this->user_exists($username))
        {
            $this->db_link->query("UPDATE girc_access SET girc_priv = '$access_level' WHERE girc_user = '$username'");
            
            $this->log->write(INFO, "ACCESS CHANGE: " . $username . " TO " . $access_level);
            
            return 0;
        }
        // If the user does NOT exists, return error to indicate failure
        else
        {
            return ERR_NOSUCHUSER;
        }
    }
    
    // Retrieves a user's access level
    // Default method for searching is by the hostmask
    public function get_access($search, $method = GET_BY_HOST)
    {
        // Search by username instead of hostmask
        if($method == GET_BY_USER)
        {
            // Check that the user exists first
            if($this->user_exists($search))
            {
                // Pull the access level from the database and return it
                $result = $this->db_link->query("SELECT girc_priv FROM girc_access WHERE girc_user = '$search'");
                $access_data = $result->fetch()[0];
                
                $access_level = $access_data[0];
            
                return $access_level;
            }
            // Return false if the user doesn't exist
            else
            {
                return -1;
            }
        }
        // Search by hostmask
        elseif($method == GET_BY_HOST)
        {
            // Retrieve the username by searching the logins table
            $result = $this->db_link->query("SELECT girc_user FROM girc_logins WHERE irc_mask = '$search' LIMIT 1");
            $user_data = $result->fetch()[0];
            
            $username = $user_data[0];    
            
            // Check that the username exists
            if($this->user_exists($username))
            {
                // Pull the access level from the database and return it
                $result = $this->db_link->query("SELECT girc_priv FROM girc_access WHERE girc_user = '$username'");
                $access_data = $result->fetch()[0];
                
                $access_level = $access_data[0];
                
                return $access_level;
            }
            // Return false if the user doesn't exist
            else
            {
                return -1;
            }
        }
        // If the search method isn't a boolean, return false
        else
        {
            return false;
        }
    }    
}

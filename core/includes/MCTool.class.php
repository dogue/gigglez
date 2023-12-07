<?php

// Minecraft Query Tools
// by [REDACTED]

class McTool
{
    private $socket;
    private $header;
    private $session;
    private $request = array ();

    public function __construct($hostname, $port = 25565)
    {
        if (FALSE == $this->socket = fsockopen("udp://$hostname", $port, $errno, $errstr, 1))
        {
            die("$errno: $errstr\n");
        }
        else
        {
            $this->set_bytes();
            
        }
    }
    
    public function __destruct()
    {
        fclose($this->socket);
    }
    
    private function set_bytes()
    {
        $this->header = pack('cc', 0xFE, 0xFD);
        $this->session = pack('cccc', 0x00, 0x00, 0x00, 0x00);
        $this->request["challenge"] = pack('c', 0x09);
        $this->request["status"] = pack('c', 0x00);
    }
    
    private function get_challenge()
    {
        $payload = $this->header.$this->request["challenge"].$this->session;
        fwrite($this->socket, $payload);
        
        $reply = fread($this->socket, 16);
        
        $challenge = pack('N', substr($reply, 5));
        
        return $challenge;
    }
    
    public function get_status($key = '')
    {
        $challenge = $this->get_challenge();
        $payload = $this->header.$this->request["status"].$this->session.$challenge.$this->session;
        
        fwrite($this->socket, $payload);
        
        $reply = fread($this->socket, 2048);
        
        $data = substr($reply, 11);
        $data = explode("\x00\x00\x01player_\x00\x00", $data);
        $data = explode("\x00", $data[0]);
        $data = array_slice($data, 2);
        
        for ($i = 0; $i <= count($data) - 1; $i = $i + 2)
        {
            $name = $data[$i];
            $value = $data[$i + 1];
            
            $status[$name] = $value;
        }
        
        if (!empty($key))
        {
            if (array_key_exists($key, $status))
            {
                return $status[$key];
            }
            else
            {
                return "UNRECOGNIZED_KEY";
            }
        }
        else
        {
            return $status;
        }        
    }
    
    public function get_players()
    {
        $challenge = $this->get_challenge();
        $payload = $this->header.$this->request["status"].$this->session.$challenge.$this->session;
        
        fwrite($this->socket, $payload);
        
        $reply = fread($this->socket, 2048);
        
        $data = substr($reply, 11);
        $data = explode("\x00\x00\x01player_\x00\x00", $data);
        $data = substr($data[1], 0, -2);
        
        $players = explode("\x00", $data);
        
        return $players;
    }
}

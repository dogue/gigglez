<?

// Improved logfile writer
// By [REDACTED]

define('DEBUG', 1);
define('INFO', 2);
define('WARN', 3);
define('ERROR', 4);
define('FATAL', 5);

class Logger
{
    private $logfile;
    private $timezone;
    private $loglevel;
    
    public function __construct($filename, $filemode = "a")
    {
        $this->logfile = fopen($filename, $filemode) or die ("[FATAL] unable to open logfile\n");
        $this->loglevel = INFO;
    }
    
    public function __destruct()
    {
        fclose($this->logfile);
    }
    
    public function set_loglevel($level)
    {
        $this->loglevel = $level;
    }
    
    private function get_timestamp()
    {
        if(empty($this->timezone))
        {
            $this->set_timezone('GMT');
        }
        return(strftime("%Y-%m-%d %H:%M:%S"));
    }
    
    public function set_timezone($id)
    {
        $this->timezone = $id;
        date_default_timezone_set($this->timezone);
    }
    
    public function write($level, $message)
    {
        $time = $this->get_timestamp();
        
        switch($level)
        {
            case DEBUG:
                $prefix = "[DEBUG]";
                break;
            case INFO:
                $prefix = "[INFO]";
                $int = 2;
                break;
            case WARN:
                $prefix = "[WARN]";
                break;
            case ERROR:
                $prefix = "[ERROR]";
                break;
            case FATAL:
                $prefix = "[FATAL]";
                fwrite($this->logfile, "$time $prefix $message\n");
                die("$prefix $message\n");
                break;
            default:
                die("Unknown log level\n");
                break;
        }        
        
        if($level >= $this->loglevel)
        {
            fwrite($this->logfile, "$time $prefix $message\n");
        }
    }
}

?>
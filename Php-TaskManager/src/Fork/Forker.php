<?php
class Forker{

    public $pid                     = NULL;
    public $ppid                    = NULL;

    public $has_started             = false;
    public $has_executed            = false;

        public $entry_point            = NULL;

    protected $has_ended            = false;
    protected $ended_status;

    protected $time_start;
    protected $time_end;


    // purely internal, has smtg to-do with pcntl status reported in master thread
    public $has_ended_with_success  = NULL;
    // should never be filled... otherwise it means that's php could not fork himself for some reasons .....
    protected $failed_reason;

    protected $current_thread_name;

    public function __construct(){
        $this->current_thread_name = "MASTER";
        $this->pid  = getmypid();
        $this->ppid = getmypid();
    }

    /**
     * 
     * @return string
     */
    public function unique_id(){
        return $this->ppid."_".$this->pid;
    }

    
    /**
     * Performs the fork call
     *  Previously it does shutdown share resources
     *  On fork done, it does re open resources
     *
     * @throws Exception
     * @return void
     */
    protected function do_fork(){
        
        $this->log(" Master pid is $this->pid");

        ResourceProtector::shutdown();
        $pid = pcntl_fork();
        ResourceProtector::recover();

        if ($pid == -1) {
            throw new Exception('Fork impossible');
        } else if ($pid > 0) {
            // Master thread
            $this->current_thread_name = "MASTER";
            $this->pid          = $pid;
            $this->time_start   = microtime(true);
            $this->has_executed = false;
            $this->has_ended    = false;
            $this->log(":\t-> started a new child $this->pid");
        } else {
            /*
             * Child thread
             */
            $this->current_thread_name = "CHILD";
            $this->pid          = getmypid();
            $this->log(":\t-> started with pid $this->pid");
        }
    }

    /**
     * Performs start operations for a fork
     * 
     * @throws Exception
     * @return bool
     */
    public function __start(){

        if( $this->has_started ){
            return false;
        }


        $this->do_fork();
        $this->has_started  = true;

        if( $this->current_thread_name == "CHILD" ){
            $this->execute( $this->entry_point );
            $this->time_end     = microtime(true);
            $this->__end();
        }

        return true;
    }

    /**
     * @param function $e
     * @return void
     */
    public function set_entry_point( $e ){
        $this->entry_point  = $e;
        $this->has_executed = false;
    }

    /**
     * @param function $entry_point
     * @return void
     */
    public function execute( $entry_point ){
        $this->log("$this->current_thread_name:\t-> execute");
        call_user_func_array($entry_point, array());
    }

    /**
     * Performs end operations for a fork
     * 
     * @return bool
     */
    public function __end(){
        $this->time_end   = microtime(true);
        $this->log(":\t-> ending $this->pid");

        if( $this->current_thread_name == "MASTER" ){
            if( $this->has_ended() == false ){
                $this->kill();
                return $this->has_ended();
            }
            return true;
        }else{
            $this->log(":\t-> leaving...");
            die();
        }
    }
    
    /**
     * Sets a fork instance as failed
     * 
     * @throws Exception
     * @param Exception $Ex
     * @return void
     */
    public function set_as_failed_fork( Exception $Ex ){
        if( $this->current_thread_name == "CHILD" ){
            throw new Exception("I cannot set myself as failed !!");
        }
        $this->has_started  = false;
        $this->failed_reason  = $Ex;
        $this->current_number_of_simultaneous_forks--;
    }

    /**
     * @return bool
     */
    public function has_executed(){
        if( $this->current_thread_name == "CHILD" ){
            return $this->has_executed;
        }else if( $this->has_ended() ){
            $this->has_executed = true;
        }
        return $this->has_executed;
    }

    /**
     * @return bool
     */
    public function has_started(){
        return $this->has_started;
    }

    /**
     * Test if a child has ended in master process
     *
     * @return bool
     */
    public function has_ended(){
        if( $this->current_thread_name == "CHILD" ){
            return $this->has_ended;
        }else if( !$this->has_ended
                    && $this->has_started){
            $pid_infos = pcntl_waitpid($this->pid, $status, WNOHANG);
            if( $pid_infos === $this->pid ){
                $this->ended_status             = $status;
                $this->has_ended                = true;
                $this->has_ended_with_success   = true;
            }elseif( $pid_infos === -1 ){
                $this->ended_status             = $status;
                $this->has_ended                = false;
                $this->has_ended_with_success   = false;
            }
        }
        return $this->has_ended;
    }

    /**
     * Kills a fork
     * 
     * @throws Exception
     * @return bool
     */
    public function kill(){
        if( $this->current_thread_name == "CHILD" ){
            throw new Exception("I cannot kill myself..");
        }elseif( $this->has_started() ){
            $this->log("$this->current_thread_name:\t-> kill...$this->pid");
            
            posix_kill($this->pid, SIGTERM);
            $pid_infos = pcntl_waitpid($this->pid, $status);

            return true;
        }
        return false;
    }

    /**
     * Return duration in seconds
     * For master this is not really precise
     * 
     * @return int
     */
    public function duration(){
        if( $this->has_ended ){
            return $this->time_end - $this->time_start;
        }
        return $this->time_start - microtime(true);
    }




    /**
     *
     * @var callback
     */
    private $logger_handler = NULL;
    private $is_valid_logger = false;

    /**
     *
     * @param callback $logger_handler
     * @return bool|previous logger_handler
     */
    public function set_logger_handler( $logger_handler ){
        if(is_callable($logger_handler) ){
            $this->is_valid_logger  = true;
            $p_logger               = $this->logger_handler;
            $this->logger_handler   = $logger_handler;
            return $p_logger;
        }
        return false;
    }
    public function log( $msg, $importance=null ){
        if( $this->is_valid_logger ){
            call_user_func_array($this->logger_handler,
                                    array("[".get_class($this)."] "."[$this->current_thread_name] [$this->pid] ".$msg, $importance));
        }
    }

}
?>
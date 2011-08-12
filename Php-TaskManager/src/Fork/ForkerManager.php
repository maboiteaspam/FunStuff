<?php
class ForkerManager{
    protected $to_fork = array();
    protected $forks = array();
    protected $pid;

    protected $maximum_number_of_simultaneous_forks = 5;
    protected $current_number_of_simultaneous_forks = 0;

    /**
     * @param int $max_number_of_fork
     */
    public function  __construct( $max_number_of_fork=5 ) {
        $this->pid = getmypid();
        $this->maximum_number_of_simultaneous_forks = $max_number_of_fork;
    }



    /**
     * Tells if unlimited fork's is allowed
     * 
     * @return bool
     */
    protected function allow_unlimited_forks(){
        $retour = $this->maximum_number_of_simultaneous_forks == -1;
        return $retour;
    }

    /**
     * Return numbers of available slots
     * 
     * @return int
     */
    protected function count_available_slots(){
        $retour = $this->maximum_number_of_simultaneous_forks - (count($this->forks));
        return $retour;
    }

    /**
     * Tells if there is available slots
     * 
     * @return bool
     */
    protected function has_available_slots(){
        $retour = $this->count_available_slots() > 0
                OR $this->allow_unlimited_forks();
        return $retour;
    }

    /**
     * Forge new fork instance
     *
     * @return Forker 
     */
    protected function forge_fork( $entry_point ){
        $Fork = new Forker();
        $Fork->set_entry_point($entry_point);
        $Fork->ppid     = $this->pid;
        $Fork->set_logger_handler( $this->logger_handler );
        $this->forks[]  = $Fork;
        return $Fork;
    }
    
    /**
     *
     * @return bool
     */
    public function can_create_new_forks(){
        if( $this->allow_unlimited_forks() == false ){
            if( $this->has_available_slots() == false ){
                return false;
            }
        }
        return true;
    }
    
    /**
     *
     * @return Forker
     */
    public function create_fork( $entry_point ){
        if( $this->can_create_new_forks() ){
            return $this->forge_fork( $entry_point );
        }
        return null;
    }
    
    /**
     *
     * @param function $entry_point
     * @return bool
     */
    public function add_to_fork( $entry_point ){
        $this->to_fork[] = $entry_point;
        return true;
    }
    
    /**
     *
     * @return int
     */
    public function start_forks(){
        //-
        if( $this->can_create_new_forks() ){
            $to_rm = array();
            foreach( $this->to_fork as $index=>$entry_point ){
                $Fork = $this->create_fork( $entry_point );
                if( $Fork === null ){
                    break;
                }else{
                    $to_rm[] = $index;
                }
            }
            // to clean up to_fork list from newly created forks
            foreach( $to_rm as $index) unset( $this->to_fork[$index] );
        }
        //-
        
        $started = 0;
        if( count($this->forks) > 0 ){
            foreach( $this->forks as $fork ){
               if( $this->start_fork($fork) ){
                   $started++;
               }
            }
        }
        return $started;
    }

    /**
     * From master process
     * Starts a Task and push
     * it in queue
     * Fork process and execute Task
     *
     * @param Forker $fork
     * @return bool
     */
    protected function start_fork( Forker $fork ){

        if( $fork->has_started ){
            return false;
        }

        try{
            $fork->__start();
            $this->current_number_of_simultaneous_forks++;
            $fork->log("Master has $this->current_number_of_simultaneous_forks forks on $this->maximum_number_of_simultaneous_forks");
            usleep(1);
        }catch( Exception $Ex ){
            $fork->set_as_failed_fork($Ex);
            $this->end_fork($fork);
            return false;
        }
        
        return true;
    }


    /**
     * From master process
     * Query each fork to know if
     * it has finished
     * Return an array of finished forks
     * or false if none
     *
     * @return array | false
     */
    protected function get_ended_forks(){
        $items = $this->forks;
        $retour = array();
        foreach( $items as $index=>$fork ){
            if( $fork->has_ended() ){
                $this->log("\t-> Task $fork->pid has ended");
                $retour[] = $fork;
            }
        }

        if( count($retour) == 0 ){
            $retour = false;
        }
        return $retour;
    }

    /**
     * From master process
     * Ends a fork and remove it from manager
     *
     * @param Forker $fork
     * @return bool
     */
    protected function end_fork( Forker $fork ){
        if( $this->remove_fork($fork) ){
            if( $fork->__end() === true ){
                $this->current_number_of_simultaneous_forks--;
                $this->log("\t-> Fork $fork->pid from  $fork->ppid is ended");
                return true;
            }
        }
        return false;
    }

    /**
     * To use in case you want to explicitly kill a fork
     * 
     * @param Forker $fork
     * @return bool
     */
    protected function close_fork( Forker $fork ){
        if( $this->remove_fork($fork) ){
            $fork->__end();
            $fork->kill();
            $this->current_number_of_simultaneous_forks--;
            $this->log("\t-> Fork $fork->pid from  $fork->ppid is closed");
            return true;
        }
        return false;
    }

    /**
     * Removes a fork from manager
     *
     * @param Forker $fork
     * @return bool
     */
    protected function remove_fork( Forker $fork ){
        $index = array_search($fork, $this->forks);
        if( $index !== false ){
            $temp = array();
            $items = $this->forks;
            foreach( $items as $i => $fork_ ){
                if( $i != $index ){
                    $temp[] = $fork_;
                }
            }
            $this->forks = $temp;
            $this->log("\t-> Fork $fork->pid from  $fork->ppid is removed");
            return true;
        }
        return false;
    }

    /**
     * From master process
     * Return true if there is any pending forks
     *
     * @return bool
     */
    public function has_forkers_to_fork( ){
        $retour = count($this->to_fork) > 0;
        return $retour;
    }

    /**
     * From master process
     * Return true if there is any running forks
     *
     * @return bool
     */
    public function has_running_forks( ){
        $retour = $this->current_number_of_simultaneous_forks > 0;
        return $retour;
    }


    
    /**
     * Requires to be called to clean up
     * last forks
     * Make master process hangs and wait
     * for each sub process to finish
     */
    public function clean_up(){
        /**
         * first we finish all forks
         */
        do{
            $this->remove_ended_forks();
            usleep(1);
        }while( $this->has_running_forks() );
    }

    
    /**
     * Look for ended forks
     * and finish them by calling end_fork method
     *
     * @return array 
     */
    public function remove_ended_forks(){
        $ended_forks = $this->get_ended_forks();
        if( $ended_forks !== false ){
            foreach( $ended_forks as $ended_fork ){
                $this->log("ForkerManager:\t-> ended_fork..." . $ended_fork->pid);
                if( $this->end_fork($ended_fork) == false ){
                    $this->log("fork $ended_fork->pid not ended correctly", "");
                }
            }
        }
        return $ended_forks;
    }








    /**
     *
     * @var callback
     */
    protected $logger_handler     = NULL;
    protected $is_valid_logger    = false;

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
                                    array("[".get_class($this)."] ".$msg, $importance));
        }
    }
}
?>
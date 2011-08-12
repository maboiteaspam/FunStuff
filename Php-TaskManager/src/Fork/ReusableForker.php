<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 08/06/11
 * Time: 12:55
 * To change this template use File | Settings | File Templates.
 */
 
class ReusableForker extends Forker {

    public $re_used_times=0;
    public $is_running=false;

    /**
     * @var Memory_holder
     */
    public $memory_holder;
    public function set_memory_holder( $memory_holder ){
        $this->memory_holder = $memory_holder;
    }

    public $max_re_use_times = 2;
    public function set_max_re_use_times( $max_re_use_times ){
        $this->max_re_use_times = $max_re_use_times;
    }
    
    public function re_use(){

        if( $this->re_used_times >= $this->max_re_use_times ){
            throw new Exception("Cannot re use this forks, already reached max number of reuse times");
        }

        $this->has_ended                = false;
        $this->has_ended_with_success   = false;
        $this->failed_reason            = null;
        $this->has_executed             = false;
        $this->time_end                 = null;
        $this->time_start               = null;
        $this->is_running               = false;

        $this->log(":\t-> reuse $this->re_used_times times");
        $this->re_used_times++;
    }
    public function has_reuse_slots(){
        return $this->re_used_times+1 < $this->max_re_use_times;
    }
    public function is_reusable(){
        $retour = false;
        if( $this->current_thread_name === "MASTER" ){
            $this->has_executed();
            if( !$this->is_running
                && $this->has_reuse_slots() )
                $retour = true;
        }else{
            if( !$this->is_running
                && $this->has_reuse_slots() )
                $retour = true;
        }
        return $retour;
    }

    /**
     * @param function $e
     * @return void
     */
    public function set_entry_point( $e ){
        if( $this->re_used_times === 0 ){
            $this->entry_point = $e;
        }else{
            if( $this->current_thread_name == "MASTER" ){
                $this->is_running   = true;
                $this->set_new_entry_point($e, $this->re_used_times );
            }else{
                $this->entry_point = $e;
            }
        }
        $this->has_executed = false;
    }


    /**
     * Starts fork
     *
     * @throws Exception
     * @return bool
     */
    public function __start(){
        
        if( $this->has_started == false
            && $this->current_thread_name == "MASTER" ){
            $this->is_running  = true;
            $this->do_fork();
        }
        
        $this->has_started  = true;

        if( $this->current_thread_name === "CHILD" ){
            $this->execute( $this->entry_point );
            $this->re_used_times++;
            $this->wait_for_incoming_entries_points();
            $this->__end();
        }


        return true;
    }

    protected function wait_for_incoming_entries_points(){
        if( $this->current_thread_name === "MASTER" ){
            throw new Exception("something gone wrong..");
        }
        $n_entry_point      = null;
        do{
            $n_entry_point      = $this->get_new_entry_point($this->re_used_times);
            if( $n_entry_point !== false ){
                $this->set_entry_point($n_entry_point);
                $this->execute($this->entry_point);
                $this->re_used_times++;
            }else{
                usleep(100);
            }

        }while( $this->re_used_times < $this->max_re_use_times );
    }

    public function execute( $entry_point ){
        parent::execute($entry_point);
        $this->time_end     = microtime(true);
        $this->send_executed_signal();
    }


    
    public function set_new_entry_point( $entry_point, $suffix_id ){
        if( $this->current_thread_name === "CHILD" ){
            throw new Exception("something gone wrong..");
        }
        
        $data   = ResourceProtector::inst()->protect($entry_point);
        $key    = $this->unique_id()."_in_".$suffix_id;
        if( $this->memory_holder->exists($key) == false ){
            if( $this->memory_holder->write($key, $data) !== false ){
                $this->log(":\t-> set_new_entry_point success $key");
                return true;
            }else{
                $this->log(":\t-> set_new_entry_point write failed $key");
            }
        }
        $this->log(":\t-> set_new_entry_point failed $key");
        return false;
    }
    public function get_new_entry_point( $suffix_id ){
        if( $this->current_thread_name === "MASTER" ){
            throw new Exception("something gone wrong..");
        }

        
        $key    = $this->unique_id()."_in_".$suffix_id;

        //$this->log("waiting for new entry point : $key");

        if( $this->memory_holder->exists($key) ){
            $data   = $this->memory_holder->read($key);
            $data   = ResourceProtector::inst()->unprotect($data);
            $this->memory_holder->release($key);
            $this->log(":\t-> get_new_entry_point success $key");
            return $data;
        }
        return false;
    }
    protected function send_executed_signal(  ){
        if( $this->current_thread_name === "MASTER" ){
            throw new Exception("something gone wrong..");
        }
        
        $key    = $this->unique_id()."_executed_from_child_".$this->re_used_times;
            $this->log(":\t-> send_executed_signal $key");
        $data = "Executed";
        $this->memory_holder->write($key, $data);
        return true;
    }
    protected function has_signaled_executed_fork(  ){
        if( $this->current_thread_name === "CHILD" ){
            throw new Exception("something gone wrong..");
        }
        
        $key    = $this->unique_id()."_executed_from_child_".$this->re_used_times;
        $retour = $this->memory_holder->exists($key);
        return $retour;
    }

    public function has_executed(){
        if( $this->current_thread_name == "MASTER" ){
            if( $this->has_started && $this->is_running ){
                if( $this->has_signaled_executed_fork() ){
                    $this->has_executed = true;
                    $this->is_running   = false;
                    $this->log(":\t-> has_signaled_executed_fork success ");
                }
            }
        }
        return $this->has_executed;
    }

    public function has_ended(){
        if( $this->current_thread_name == "MASTER" ){
            if( $this->is_reusable() == false ){
                $retour = parent::has_ended();
                return $retour;
            }
        }
        return false;
    }

    /**
     * Performs end of operations for fork
     */
    public function __end(){
        $this->time_end   = microtime(true);
        $this->log(":\t-> ending $this->pid");

        if( $this->current_thread_name === "MASTER" ){
            if( $this->is_reusable() === false ){
                return $this->kill();
            }else
                return false;
        }elseif( $this->current_thread_name === "CHILD" ){
            die();
        }
    }
}










<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 08/06/11
 * Time: 12:55
 * To change this template use File | Settings | File Templates.
 */
 
class ReusableForkerManager extends ForkerManager {


    public $memory_holder;

    public function set_memory_holder( $memory_holder ){
        $this->memory_holder = $memory_holder;
    }

    public $max_reuse_forks = 2;
    public function set_max_reuse_forks( $max_reuse_forks ){
        $this->max_reuse_forks = $max_reuse_forks;
    }

    protected function forge_fork( $entry_point ){
        $this->log("Master has forge new fork");
        $Fork = new ReusableForker();
        $Fork->set_memory_holder( $this->memory_holder );
        $Fork->set_max_re_use_times( $this->max_reuse_forks );
        $Fork->set_entry_point($entry_point);
        $Fork->ppid     = $this->pid;
        $Fork->set_logger_handler( $this->logger_handler );
        $this->forks[]  = $Fork;
        return $Fork;
    }
    
    public function has_available_forker(){
        foreach( $this->forks as $Fork ){
            if( $Fork->is_reusable() ){
                return true;
            }
        }
        return false;
    }

    /**
     * @param $entry_point
     * @return null|ReusableForker
     */
    public function reuse_available_forker( $entry_point ){
        foreach( $this->forks as $Fork ){
            if( $Fork->is_reusable() ){
                $this->log("Re using fork $Fork->pid -- $Fork->re_used_times");
                $Fork->re_use();
                $Fork->set_entry_point( $entry_point );
                return $Fork;
            }
        }
        return null;
    }
    
    /**
     *
     * @return int
     */
    public function start_forks(){
        //-
        $to_rm = array();
        if( $this->has_available_forker() ){
            foreach( $this->to_fork as $index=>$entry_point ){
                $Fork = $this->reuse_available_forker( $entry_point );
                if( $Fork === null ){
                    break;
                }else{
                    $this->log("---------------------- sending entry point $index to active child $Fork->pid");
                    $to_rm[] = $index;
                }
            }
        }

        if( $this->can_create_new_forks() ){
            foreach( $this->to_fork as $index=>$entry_point ){
                $Fork = $this->create_fork( $entry_point );
                if( $Fork === null ){
                    break;
                }else{
                    $this->log("---------------------- sending entry point $index to new child");
                    $to_rm[] = $index;
                }
            }
        }
        // to clean up to_fork list from newly created forks
        foreach( $to_rm as $index) unset( $this->to_fork[$index] );
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
    protected function start_fork( ReusableForker $fork ){
        if( $fork->has_started() ){
            return false;
        }
        
        try{
            $is_new = ! $fork->has_started();

            $fork->__start();

            if( $is_new ){
                $this->log("First forking of $fork->pid");
                $this->current_number_of_simultaneous_forks++;
            }else{
                $this->log("Re using fork $fork->pid, $fork->re_used_times times");
            }
            
            // - needed to let memory holder pass informations to forked process
            usleep(1);
        }catch( Exception $Ex ){
            $this->log("Failed fork pid $fork->pid");
            $fork->set_as_failed_fork($Ex);
            throw $Ex;
            return false;
        }

        return true;
    }



    /**
     * Look for ended forks
     * and finish them by calling end_fork method
     *
     * @return array
     */
    public function remove_ended_forks(){
        $retour = parent::remove_ended_forks();
        if( $this->has_forkers_to_fork() === false ){
            foreach( $this->forks as $F ){
                if( $F->has_executed() ){
                    $this->close_fork($F);
                }
            }
        }
        return $retour;
    }



    
}

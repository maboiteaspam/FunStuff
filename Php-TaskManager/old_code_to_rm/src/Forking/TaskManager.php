<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 16:25
 * To change this template use File | Settings | File Templates.
 */
 
class TaskManager {
    public $task_controllers;
    public $started_task_controllers;
    public $name_task_stats;

    public function __construct(){
        $this->task_controllers = array();
        $this->ended_task_controllers = array();
        $this->name_task_stats = array();
        $this->started_task_controllers = array();
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
                                    array("[".get_class($this)."] ".$msg, $importance));
        }
    }

    public $memory_holder;
    public function set_memory_holder( $memory_holder ){
        $this->memory_holder = $memory_holder;
    }

    /**
     * @param Task $task
     * @return TaskController
     */
    public function add( Task $task ){
        $task->set_memory_holder($this->memory_holder);
        $task->set_logger_handler($this->logger_handler);
        $task->name = "Task_".count($this->task_controllers);
        $retour     = new TaskController($task);
        $retour->set_logger_handler($this->logger_handler);
        $this->task_controllers[] = $retour;
        return $retour;
    }

    public function get_started_task_by_fork( Forker $fork ){
        foreach( $this->started_task_controllers as $task_controller ){
            if( $task_controller->forker === $fork ){
                return $task_controller;
            }
        }
        return null;
    }

    public function execute( ){

        foreach( $this->task_controllers as $index=>$task_controller ){
            if( isset($this->name_task_stats[$task_controller->task_name]) == false ){
                $this->name_task_stats[$task_controller->task_name] = array("number"=>0, "finished"=>0, "started"=>0);
            }
            $this->name_task_stats[$task_controller->task_name]["number"]++;
        }
        /*
        foreach( $this->task_controllers as $index=>$task_controller ){
            $task_controller->embed( $this );
        }*/

        $controllers_count          = count($this->task_controllers);
        $started_controllers_count  = count($this->started_task_controllers);

        $nb_resolved_task   = 0;
        $nb_ended_task      = 0;
        $max_resolved_task  = 2;
        $to_end             = array();
            $to_execute = array();

        $i=0;
        do{
            if( $i / 500 > 1 ){
                $this->log("Task Manager is running with $nb_ended_task ended, $started_controllers_count started, on $controllers_count todo");
                $i=0;
            }


            if( $nb_resolved_task < $max_resolved_task ){
                foreach( $this->task_controllers as $index=>$task_controller ){
                    if( $task_controller->can_execute($this->name_task_stats) ){
                        $this->log("Task Manager is resolving {$task_controller->task->name} num $index");
                        if( $task_controller->resolve($this) ){
                            $this->log("Task Manager has resolved {$task_controller->task->name}e num $index");
                            $to_execute[$index] = $task_controller;
                            $this->log("TASK : {$task_controller->task->name} num $index is ready","");
                            $nb_resolved_task++;
                        }else{
                            $this->log("TASK : {$task_controller->task->name} num $index not ready","");
                        }
                    }
                    if( $nb_resolved_task >= $max_resolved_task ){
                        $this->log("Task Manager has now $nb_resolved_task on $max_resolved_task tasks ready to resolve !");
                        $this->log("");
                        $this->log("");
                        break;
                    }
                }
            }

            foreach( $to_execute as $index=>$task_controller ){
                unset( $this->task_controllers[$index] );
                if( $task_controller->has_executed() ){
                    if( $task_controller->has_ended()  ){

                        $this->log("TASK : {$task_controller->task->name} num $index has ended","");
                        $to_end[$index] = $task_controller;

                    }else{
                        $this->log("TASK : {$task_controller->task->name} num $index has NOT ended","");
                    }
                }else{
                    $this->log("Task Manager is executing {$task_controller->task->name} num $index is going start");
                    $task_controller->execute();
                    $this->log("TASK : {$task_controller->task->name} num $index is started","");
                    $this->name_task_stats[$task_controller->task_name]["started"]++;
                    $this->started_task_controllers[$index] = $task_controller;
                    $started_controllers_count++;
                    //$this->task_controllers[$index] = $task_controller;
                }
            }

            foreach( $to_end as $index=>$task_controller ){
                if( isset($this->started_task_controllers[$index]) ){
                    unset( $this->started_task_controllers[$index] );
                    unset( $to_execute[$index] );
                    $started_controllers_count--;
                    $nb_resolved_task--;
                    $nb_ended_task++;
                }else{
                }
            }

            sleep(1);
            if( $nb_resolved_task > 0 )
                usleep(500);

            $i++;
        }while( $nb_ended_task < $controllers_count || $started_controllers_count > 0 );


        foreach( $to_end as $index=>$task_controller ){
            $this->log("TASK : $task_controller->task_name num $index has released","");
            $task_controller->release();
        }

        $this->log("Task Manager is leaving with $nb_ended_task ended, $started_controllers_count started, on $controllers_count todo");
        return true;
    }

}

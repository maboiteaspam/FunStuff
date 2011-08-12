<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 20:58
 * To change this template use File | Settings | File Templates.
 */
 
class TaskRepeater extends TaskEmbedder {

    protected $repeat_times;

    public function __construct( $repeat_times = 1){
        $this->repeat_times = $repeat_times;
    }
    
    public function __embed( TaskManager $TaskManager, TaskController $task_controller ){
        $repeat_times   = $task_controller->repeat_times;
        $index          = count($task_controller->task->args);
        $task_controller->task->args["repeat_count"]=0;
        for( $i=0; $i< $repeat_times; $i++ ){
            $n_task = clone $task_controller->task;
            $n_task->args["repeat_count"]=$i+1;
            $TaskManager->add_task( $n_task );
        }
        return true;
    }
    public function __resolve( TaskManager $TaskManager, TaskController $controller ){
        return true;
    }
}

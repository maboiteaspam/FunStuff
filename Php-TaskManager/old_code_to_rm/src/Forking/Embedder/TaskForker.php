<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 22:16
 * To change this template use File | Settings | File Templates.
 */
 
class TaskForker extends TaskEmbedder {
    public $forker;
    public $forked_task;
    public $task_embeded;

    public function __construct( ForkerManager $ForkerManager ){
        $this->forker = $ForkerManager;
    }
    public function __embed( TaskManager $TaskManager, TaskController $controller ){
        $this->log(" embeding {$controller->task->name}");
        $this->task_embeded = $controller->task;

        $this->forked_task              = new ForkedTask( null );
        
        $this->forked_task->callback    = $controller->task->name;
        $this->forked_task->args        = $controller->task->args;
        $this->forked_task->resolved    = $controller->task->resolved;

        $this->forked_task->forker      = $this->forker;
        //$this->forked_task->setEmbedded( $controller->task );
        $controller->setTask($this->forked_task);
        return true;
    }
    public function __resolve( TaskManager $TaskManager, TaskController $controller ){
        $this->log(" resolving {$controller->task->name}");
        $fork = $this->forked_task->fork;
        if( $fork == null )
            $fork   = $this->forker->create_fork();

        if( $fork !== null ){
            $this->forked_task->fork = $fork;
            $resolved = $this->forked_task->resolve( $this->task_embeded->resolve()  );
            
            if( $resolved !== null ){
                $controller->task->is_resolved = true;
                $controller->task->resolved = $resolved;
                return true;
            }
            $this->log(get_class($this)." resolved is null, cannot resolve");
        }else{
            $this->log(get_class($this)." forker is null, cannot resolve");
        }
        return false;
    }
}

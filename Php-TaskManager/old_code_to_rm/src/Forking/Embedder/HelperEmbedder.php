<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 09/06/11
 * Time: 15:24
 * To change this template use File | Settings | File Templates.
 */
 
class HelperEmbedder extends TaskEmbedder {
    public $task_embedder;
    public $task_embeded;

    public function __construct( Task $Task ){
        $this->task_embedder = $Task;
    }
    public function __embed( TaskManager $TaskManager, TaskController $controller_to_embed ){
        $this->log(" embeding {$controller_to_embed->task->name}");
        $this->task_embeded = $controller_to_embed->task;
        return true;
    }
    public function __resolve( TaskManager $TaskManager, TaskController $controller ){
        $this->log(" is resolving {$controller->task->name}");
        $resolved = $this->task_embedder->resolve( $this->task_embeded->resolve() );
        if( $resolved !== null ){
            $controller->task->is_resolved = true;
            $controller->task->resolved = $resolved;
            return true;
        }
        return false;
    }
}

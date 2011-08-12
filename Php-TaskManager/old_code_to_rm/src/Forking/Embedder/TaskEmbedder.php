<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 17:48
 * To change this template use File | Settings | File Templates.
 */
 
abstract class TaskEmbedder implements IEmbedder {
    /**
     *
     * @var callback
     */
    private $logger_handler  = NULL;
    private $is_valid_logger = false;
    public $has_embed        = false;
    public $has_resolved     = false;

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
    
    public function __embed( TaskManager $TaskManager, TaskController $task_controller ){
        return false;
    }
    public abstract function __resolve( TaskManager $TaskManager, TaskController $task_controller );
}

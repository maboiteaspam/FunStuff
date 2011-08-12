<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 16:43
 * To change this template use File | Settings | File Templates.
 */
 
class TaskController {

    protected static $count = 0;

    /**
     * @var \Task
     */
    public $task;
    public $task_name;
    public $task_requirements;
    public $task_controls;
    public $task_embedders;

    /**
     *
     * @var callback
     */
    public $logger_handler = NULL;
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


    public function __construct( Task $task ){
        $this->task                 = $task;
        $this->task_name            = "";
        $this->task_id              = self::$count;
        $this->task_requirements    = array();
        $this->task_controls        = array();
        $this->task_embedders       = array();

        self::$count++;
    }

    public function setTask(Task $task ){
        $this->task = $task;
        if( $this->task->name == "" ){
            $this->task->name = $this->task_name."_".$this->task_id;
        }
        $this->task->set_logger_handler($this->logger_handler);
    }

    public function can_execute( $name_task_stats ){
        foreach( $this->task_requirements as $require ){
            if( isset($name_task_stats[$require]) == false ){
                return false;
            }elseif( $name_task_stats[$require]["number"] != $name_task_stats[$require]["finished"] ){
                return false;
            }
        }

        foreach( $this->task_controls as $task_control ){
            if( $task_control->execute() == false ){
                return false;
            }
        }
        return true;
    }

    public function only_after( $task_name ){
        $this->task_requirements[] = $task_name;
        return $this;
    }

    public function only_if( BooleanTask $task ){
        $this->task_controls[] = $task;
        return $this;
    }

    public function embed_with( $embed_with ){
        if( $embed_with instanceof IEmbedder ){
            $embed_with;
        }else{
            $embed_with = new HelperEmbedder( $embed_with );
        }
        $embed_with->set_logger_handler( $this->logger_handler );
        $this->task_embedders[] = $embed_with;
        return $this;
    }

    public function with_name( $name ){
        $this->task_name = $name;
        return $this;
    }

    public function embed( TaskManager $TaskManager ){
        foreach( $this->task_embedders as $task_embedder ){
            if( $task_embedder->has_embed == false ){
                $has_embed = $task_embedder->__embed( $TaskManager, $this );
                if( $has_embed == false ){
                    $this->task->is_resolved = false;
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @return Closure
     */
    public function resolve( TaskManager $TaskManager ){
        foreach( $this->task_embedders as $task_embedder ){
            if( $task_embedder->has_embed == false ){
                $task_embedder->__embed( $TaskManager, $this );
            }
            if( $task_embedder->has_resolved == false ){
                $has_resolved = $task_embedder->__resolve( $TaskManager, $this );
                if( $has_resolved == false ){
                    $this->log(get_class($task_embedder)." has not resolved");
                    $this->task->is_resolved = false;
                    return false;
                }else{
                    $this->log(get_class($task_embedder)." has resolved");
                    $task_embedder->has_resolved = true;
                }
            }
        }
        $this->task->is_resolved = true;
        return $this->task->is_resolved;
    }

    public function is_resolved(){
        return $this->task->is_resolved;
    }

    public function resolved( ){
        return $this->task->resolved;
    }

    public function execute( ){
        return $this->task->execute( );
    }

    public function has_executed( ){
        return $this->task->has_executed;
    }

    public function has_ended( ){
        return $this->task->has_ended( );
    }

    public function release( ){
        return $this->task->release( );
    }
}

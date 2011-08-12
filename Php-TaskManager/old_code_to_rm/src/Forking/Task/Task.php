<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 16:25
 * To change this template use File | Settings | File Templates.
 */
 
abstract class Task {

    public $callback;
    public $args;
    public $name;

    /**
     * @var Closure
     */
    public $resolved;
    public $is_resolved     = false;
    public $has_executed    = false;
    public $has_ended       = false;
    public $has_resolved;
    
    public function __construct($callback=null, $args = array()){
        $this->callback = $callback;
        $this->args     = $args;
    }

    public $memory_holder;
    public function set_memory_holder( $memory_holder ){
        $this->memory_holder = $memory_holder;
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
                                    array("[".get_class($this)."] "."[".$this->name()."] ".$msg, $importance));
        }
    }

    public function name(){
        return $this->name;
    }

    public function has_executed(){
        return $this->has_executed;
    }

    /**
     * @return Closure
     */
    protected abstract function __resolve( $to_embed=null );
    public function resolve( $to_embed=null ){
        if( $this->is_resolved == false){
            $retour = $this->__resolve($to_embed);
            if( $retour == null )
                $this->is_resolved = false;
            $this->resolved = $retour;
        }
        return $this->resolved;
    }

    /**
     */
    public function execute(){
        $this->log("has started execution");
        $this->has_executed = true;
        $this->has_ended    = false;
        call_user_func_array($this->resolved, array());
        $this->has_ended    = true;
        $this->log("has ended execution");
        return true;
    }

    public function release(){
    }

    public function has_ended(){
        return $this->has_ended;
    }
}

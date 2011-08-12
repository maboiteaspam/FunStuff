<?php
class Memory_holder{
    /**
     *
     * @var callback
     */
    private $logger_handler  = NULL;
    private $is_valid_logger = false;
    public $has_embed       = false;
    public $data;

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
                                    array("[".get_class($this)."]".$msg, $importance));
        }
    }
    public function read( $name ){
        return $this->data[$name];
    }
    public function write( $name, $data ){
        $this->data[$name] = $data;
        return strlen($data);
    }
    public function exists( $name ){
        return isset($this->data[$name]);
    }
    public function release( $name ){
        unset($this->data[$name]);
    }
    public function close(  ){
    }
    public function open(  ){
    }
}
?>
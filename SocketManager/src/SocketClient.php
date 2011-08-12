<?php
/**
 * A socket client
 * able to read write
 * a socket
 * 
 */
class SocketClient{
    /**
     * Unique name of the socket
     *
     * @var string
     */
    public $name;
    /**
     * Current socket
     * resource
     *
     * @var resource
     */
    protected $socket;
    /**
     * Current buffer
     *
     * @var string
     */
    public $buffer;
    /**
     * Read data by length of
     *
     * @var int
     */
    protected $read_length;
    /**
     * Tells you if socket is closed
     *
     * @var bool
     */
    protected $is_closed;
    /**
     * Tells you if a closed
     * socket has been closed
     * with sucess or not
     *
     * @var bool
     */
    protected $is_closed_with_success;

    /**
     *
     * @param resource $socket
     * @param string $name
     */
    public function  __construct( $socket, $name ) {
        $this->name                     = $name;
        $this->socket                   = $socket;
        $this->buffer                   = "";
        $this->read_length              = 100;
        $this->is_closed                = false;
        $this->is_closed_with_success   = false;
        $this->logger_handler           = NULL;
    }

    public function __get( $prop ){throw new Exception("unknown attr $prop");}
    public function __set( $prop, $value ){throw new Exception("unknown attr $prop");}

    /**
     *
     * @param string $msg
     * @param string $importance
     */
    protected function log( $msg, $importance=null ){
        if( $this->logger_handler !== NULL ){
            call_user_func_array($this->logger_handler,
                                    array($msg, $importance));
        }
    }
    /**
     *
     * @var callback
     */
    private $logger_handler;
    /**
     *
     * @param callback $logger_handler
     * @return bool
     */
    public function set_logger_handler( $logger_handler ){
        if(is_callable($this->logger_handler) ){
            $p_logger = $this->logger_handler;
            $this->logger_handler = $logger_handler;
            return $p_logger;
        }
        return false;
    }
    /**
     *
     * @param resource $socket
     */
    protected function log_socket_error( $socket=null ){
        list(, $caller) = debug_backtrace(false);
        $this->log($caller."() failed: reason: " . $this->last_error_string() . "");
    }

    /**
     * Tells you
     * if socket is in
     * error state
     *
     * @return bool
     */
    public function is_in_error(){
        return $this->last_error()===false;
    }

    /**
     * Gives you last
     * socket error code
     *
     * @return int
     */
    public function last_error(){
        return socket_last_error($this->socket);
    }

    /**
     * Gives last socket
     * error message
     *
     * @return string
     */
    public function last_error_string(){
        return socket_strerror( $this->last_error() );
    }

    /**
     * Reads pending buffer
     * on socket
     * return false on error, and close socket
     * return true otherwise, and continue
     *
     * @return bool
     */
    public function read(){
        $bytes = socket_recv($this->socket, $buffer, $this->read_length, 0);
        
        if ($bytes === false ) {
            // Abnormal close connection
            $this->log_socket_error($this->socket);
            $this->close(false);
            return false;
        }elseif( $buffer === NULL){
            // Abnormal close connection
            $this->log_socket_error($this->socket);
            $this->close(false);
            return false;
        }else{
            $this->buffer.= $buffer;
            return true;
        }
    }

    /**
     * Write data
     * on the socket
     *
     * @param string $buffer
     * @return bool
     */
    public function write( $buffer ){
        $bytes = socket_write($this->socket, $buffer);
        if ($bytes === false ) {
            $this->log_socket_error($this->socket);
            return false;
        }
        return true;
    }

    /**
     * Close socket
     *
     * @param bool $with_success
     * @return bool
     */
    public function close( $with_success=true ){
        if( ! $this->is_closed ){
            $this->is_closed = true;
            $this->is_closed_with_success = $with_success;
            socket_close($this->socket);
            return true;
        }
        return false;
    }
}
?>
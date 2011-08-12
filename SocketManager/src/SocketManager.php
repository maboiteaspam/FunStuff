<?php
/**
 * A socket manager
 * able to maintain sockets clients
 * to checks state of sockets clients
 * to close sockets clients
 * 
 */
class SocketManager{
    /**
     * Tells you if a manager
     * has opened with success
     *
     * @var bool
     */
    protected $is_opened;
    /**
     * tells you if an opened
     * master socket is 
     * now usable
     *
     * @var bool
     */
    protected $is_usable;
    /**
     * tells you current listening
     * state of an
     * opened and usable
     * socket
     *
     * @var bool
     */
    protected $is_listening;
    /**
     * tells you if current
     * manager is closed
     *
     * @var bool
     */
    protected $is_closed;

    /**
     * An array of args
     * prepended to args
     * of each cacllback
     *
     * @var array
     */
    public $prepend_args_with;

    /**
     * An array of args
     * appended to args
     * of each cacllback
     *
     * @var array
     */
    public $append_args_with;

    // socket_create
    /**
     * @see http://php.net/manual/en/function.socket-create.php
     *
     * @var int
     */
    public $socket_domain;
    /**
     * @see http://php.net/manual/en/function.socket-create.php
     *
     * @var int
     */
    public $commnication_type;
    /**
     * @see http://php.net/manual/en/function.socket-create.php
     *
     * @var int
     */
    public $protocol;

    // socket_bind
    /**
     * @see http://php.net/manual/en/function.socket-bind.php
     *
     * @var int
     */
    public $port;
    /**
     * @see http://php.net/manual/en/function.socket-bind.php
     *
     * @var string
     */
    public $adress;

    //socket_listen
    /**
     * @see http://www.php.net/manual/en/function.socket-listen.php
     *
     * @var int
     */
    public $backlog;

    /**
     * The master socket
     * clients use to connect
     * on this manager
     *
     * @var resource
     */
    protected $master_socket;
    /**
     * List of active sockets
     * during lifetime
     * of this manager
     *
     * @var array
     */
    protected $accepted_sockets;
    protected $active_sockets;


    /**
     * Appears each time manager is started
     * Appears once if it is already started
     *
     * @param SocketManager $SocketManager
     * @var callback
     */
    public $on_manager_starts_listening;
        /**
         * Appears each time listen
         * method is called
         * occurs before sockets
         * are selected
         *
         * @param SocketManager $SocketManager
         * @var callback
         */
        public $on_sockets_select;
        /**
         * Appears each time listen
         * method is called
         * occurs after sockets
         * are selected
         *
         *
         * @param SocketManager $SocketManager
         * @param int $number_of_modified_socket_since_last_listen_query
         * @var callback
         */
        public $on_sockets_selected;
            /**
             * Appears each time
             * a new socket is accepted
             *
             * @param SocketManager $SocketManager
             * @param SocketClient $SocketClient
             * @var callback
             */
            public $on_socket_accepted;
            /**
             * Appears each time
             * an existing socket 
             * has wrote on her buffer
             * (this is incoming data for server)
             *
             * @param SocketManager $SocketManager
             * @param SocketClient $SocketClient
             * @var callback
             */
            public $on_socket_wrote_input_buffer;
            /**
             *
             * Appears each time
             * an existing socket 
             * is closed
             *
             * @param SocketManager $SocketManager
             * @param SocketClient $SocketClient
             * @var callback
             */
            public $on_socket_closed;
        /**
         * Appears each time listen
         * method is called
         * occurs after sockets
         * have been read
         *
         *
         * @param SocketManager $SocketManager
         * @var callback
         */
        public $on_sockets_read;
        /**
         * Appears each time listen
         * method is called
         * occurs after sockets
         * have been verified
         * All remainings sockets are cleaned
         * and ready to use
         *
         * @param SocketManager $SocketManager
         * @var callback
         */
        public $on_sockets_verified;
    /**
     * Appears each time manager is closed
     * Appears once if it is already closed
     *
     * @param SocketManager $SocketManager
     * @var callback
     */
    public $on_manager_closed;

    public function __construct(){
        $this->is_usable        = false;
        $this->is_opened        = false;
        $this->is_listening     = false;
        $this->is_closed        = true;

        $this->backlog          = 5;
        $this->master_socket    = null;
        $this->accepted_sockets = array();
        $this->active_sockets   = array();
        $this->prepend_args_with= array();
        $this->append_args_with = array();

        $this->logger_handler   = NULL;
    }

    public function __get( $prop ){throw new Exception("unknown attr $prop");}
    public function __set( $prop, $value ){throw new Exception("unknown attr $prop");}

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
     * @param resource $socket
     */
    protected function log_socket_error( $socket=null ){
        list(, $caller) = debug_backtrace(false);
        $this->log($caller."() failed: reason: " . socket_strerror(socket_last_error()) . "");
    }
    
    /**
     * Perform callback trigger
     *
     * @param callback $callback
     * @param array $args
     */
    protected function trigger( $callback, $args ){
        if( $callback !== NULL ){
            if(is_callable($callback)){
                $_args = array_merge($this->prepend_args_with, $args, $this->append_args_with);
                call_user_func_array($callback, $_args);
            }
        }
    }

    /**
     *
     * @return bool
     */
    public function is_opened(){
        return $this->is_opened;
    }

    /**
     *
     * @return bool
     */
    public function is_usable(){
        return $this->is_opened && $this->is_usable;
    }

    /**
     *
     * @return bool
     */
    public function is_listening(){
        return $this->is_opened && $this->is_usable && $this->is_listening;
    }

    /**
     * Try to create master socket
     * configure options and
     * then bind it
     * to defined port and adress
     *
     * if it fails, return false
     * return true otherwise
     *
     * @return bool
     */
    public function open(){
        if( $this->is_opened )
            return false;

        $this->master_socket = socket_create($this->socket_domain, $this->commnication_type, $this->protocol);
        if ($this->master_socket === false ) {
            $this->log_socket_error();
            $this->master_socket    = NULL;
            $this->is_opened        = false;
            $this->is_usable        = false;
            return false;
        }

        $option_applied = socket_set_option($this->master_socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if ( $option_applied === false ) {
            $this->log_socket_error($this->master_socket);
            $this->master_socket    = NULL;
            $this->is_opened        = false;
            $this->is_usable        = false;
            return false;
        }

        $socket_binded = socket_bind($this->master_socket, $this->adress, $this->port);
        if ( $socket_binded === false) {
            $this->log_socket_error($this->master_socket);
            $this->master_socket    = NULL;
            $this->is_opened        = false;
            $this->is_usable        = false;
            return false;
        }

        $this->is_usable        = true;
        $this->is_opened        = true;
        $this->is_listening     = false;
        $this->is_closed        = false;
        return true;
    }

    /**
     * Try to start listening
     * an opened master socket
     *
     * if fails, return false
     * otherwise, return true
     *
     * @return bool
     */
    public function start_listening(){
        if( $this->is_listening ){
            return false;
        }
        if( $this->is_usable == false ){
            return false;
        }


        $socket_listened = socket_listen($this->master_socket, $this->backlog);
        if ( $socket_listened === false) {
            $this->log_socket_error($this->master_socket);
            $this->close();
            return false;
        }

        $this->is_listening     = true;

        $this->accepted_sockets = array();
        $this->active_sockets   = array($this->master_socket);

        $this->trigger($this->on_manager_starts_listening, array($this));
    }

    /**
     * Performs listening
     * on master socket to accept new clients
     * Performs listening
     * on accepted socket to read pending buffer
     *
     * return true if there is any modified socket
     * otherwise, return false
     *
     * @return bool
     */
    public function listen(){
        $changed_sockets    = $this->active_sockets;

        $this->trigger($this->on_sockets_select, array($this));
        $num_changed        = socket_select($changed_sockets, $write = NULL, $except = NULL, 0);
        $this->trigger($this->on_sockets_selected, array($this, $num_changed));

        if( $num_changed > 0 ){
            foreach($changed_sockets as $changed_socket) {
                if ($changed_socket == $this->master_socket) {
                    if (($accepted_socket = socket_accept($this->master_socket)) < 0) {
                        $this->log_socket_error($msgsock);
                        continue;
                    } else {
                        $SocketClient   = $this->add_socket($accepted_socket);
                    }
                } else {
                    $SocketClient   = $this->find_socket( $changed_socket );
                    if( $SocketClient->read() == true ){
                        $this->trigger($this->on_socket_wrote_input_buffer, array($this, $SocketClient));
                    }else{
                        $this->close_socket( $SocketClient, false );
                    }
                }
            }
        }
        $this->trigger($this->on_sockets_read, array($this));

        $this->verify_sockets();
        $this->trigger($this->on_sockets_verified, array($this));

        return $num_changed>0;
    }

    /**
     * Perform a call to listen method
     * and proceed to a usleep
     *
     * @param int $sleep_mtime
     */
    public function do_listen( $sleep_mtime=1 ){
        $this->listen();
        usleep($sleep_mtime);
    }

    /**
     * Test all sockets still opened
     * if any is down or unavailable,
     * Close the socket and returns true
     * if none is closed, return false
     *
     * @return boolean
     */
    protected function verify_sockets(){
        $retour = false;
        foreach( $this->accepted_sockets as $__index=>$active_socket ){
            if( $active_socket != NULL && $__index > 0 ){
                if( $active_socket->is_in_error() ){
                    $this->trigger($this->on_socket_closed, array($this, $active_socket));
                    $this->remove_socket( $active_socket );
                    $retour = true;
                }
            }
        }
        return $retour;
    }

    /**
     * Try to find a socket by
     * it s resource value
     *
     * @param resource $socket
     * @return SocketClient
     */
    public function find_socket( $socket ){
        $socket_name = array_search($socket, $this->active_sockets);
        return $this->find_socket_by_name( $socket_name );
    }

    /**
     * Try to find a socket
     * by it s name
     *
     * @param string $socket_name
     * @return SocketClient
     */
    public function find_socket_by_name( $socket_name ){
        if( isset($this->accepted_sockets[ $socket_name ]) )
            return $this->accepted_sockets[ $socket_name ];
        return null;
    }

    /**
     * Force to close a socket
     * trigger attached callback
     *
     * @param SocketClient $SocketClient
     * @param bool $closed_with_success
     */
    public function close_socket( SocketClient $SocketClient, $closed_with_success=false ){
        $this->trigger($this->on_socket_closed, array($this, $SocketClient));
        $SocketClient->close($closed_with_success);
        $this->remove_socket( $SocketClient );
    }

    /**
     * Only removes a socket from
     * current manager,
     *
     * does not close it
     *
     * @param SocketClient $SocketClient
     * @return bool
     */
    protected function remove_socket( SocketClient $SocketClient ){
        $socket_name = $SocketClient->name;
        if( isset ($this->accepted_sockets[ $socket_name ]) ){
            unset($this->accepted_sockets[ $socket_name ]);
            unset($this->active_sockets[ $socket_name ]);
            return true;
        }
        return false;
    }

    /**
     * Add socket resource
     * to current manager
     * Apply unique name to
     *  SocketClient
     *
     * @param resource $socket
     * @return SocketClient
     */
    protected function add_socket( $socket ){
        array_push($this->active_sockets, $socket);
        $socket_name    = array_search($socket, $this->active_sockets);
        
        $SocketClient   = new SocketClient($socket, $socket_name);
        $SocketClient->set_logger_handler($this->logger_handler);

        $this->accepted_sockets[ $socket_name ] = $SocketClient;
        $this->trigger($this->on_socket_accepted, array($this, $SocketClient));
        
        return $SocketClient;
    }

    /**
     * Clean all pending resources
     * associated with manager
     *
     * @return bool
     */
    public function clean_up(){
        //-
    }

    /**
     * Close manager
     *
     * @return bool
     */
    public function close(){
        if( ! $this->is_closed ){
            socket_close($this->master_socket);
            $this->trigger($this->on_manager_closed, array($this));
            $this->is_closed = true;
            return true;
        }
        return false;
    }

    /**
     * Clean all pending resources
     * associated with manager
     * Close manager
     *
     * @return bool
     */
    public function shutdown(){
        $this->clean_up();
        return $this->close();
    }
}
?>
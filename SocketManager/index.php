<?php
/***
This is an example file,
once downloaded
simply run
	
	sudo php index.php

        Go into your fav browser and tpe localhost:154
            You should see messages from server
            popping to your cmd line
        -> ctrl+c to leave

   //continue your life, thanks

*/

include("src/SocketManager.php");
include("src/SocketClient.php");



/**
 * Logging method
*/
if(class_exists("Logger") ){
    /**
     * Using Log4php
     */
    $logger_handler = function( $msg, $importance=NULL ){
        Logger::getLogger("main")->info( $msg );
    };
}else{
    /**
     * Simple handler
     */
    $logger_handler = function( $msg, $importance=NULL ){
        echo "[".date("Ymd_His")."] [".getmypid()."] ".$msg."\n";
    };
}

/**
* Init socket listening on localhost:154
log message to do_log function
*/
$SocketManager                      = new SocketManager();
$SocketManager->socket_domain       = AF_INET;
$SocketManager->commnication_type   = SOCK_STREAM;
$SocketManager->protocol            = SOL_TCP;
$SocketManager->adress              = 'localhost';
$SocketManager->port                = 154;

$SocketManager->set_logger_handler( $logger_handler );

/**
 * Setup callback args
 	i think it Could be replaced by use() 
 		function use(){};
*/
$SocketManager->prepend_args_with               = array("arg 1", " arg 2");
$SocketManager->append_args_with                = array("arg 4", " arg 5");

/**
* Simply attach callbacks
	to some predefined handles
	
	This is a cycle wich does not stop to process 
	until you decided,
	
	Appears once 				-> on_manager_starts_listening
	Appears each time 				-> on_sockets_select
	Appears each time 					-> on_sockets_selected
	Appears foreach new client 				-> on_socket_accepted
	Appears only when a client speaks 			-> on_socket_wrote_input_buffer
	Appears each time a client leave			-> on_sockets_select
	Appears each time				-> on_sockets_read
	Appears each time				-> on_sockets_verified
	Appears once 				-> on_manager_closed
	
	to exit in response to an internal task 
		you can use shutdown method
		to properly  close all opened resources.
	
	other way simply exit from bash process using ctrl+c command
	
	It expect it to be compat with php 5.2.x using different callbacks
		(array( $callback_instance, $callback_methodname))
	but i did not tested, and dont know if socket extension has 
	a lot of differences btw lastest php 5.3 and previsous 5.2
	
	anyway here it is :
*/
$SocketManager->on_manager_starts_listening = function ($arg1, $arg2, $SocketManager, $arg3, $arg4){
	echo "hello\n";
};
	$SocketManager->on_sockets_select = function ($arg1, $arg2, $SocketManager, $arg3, $arg4){
		echo "\tgoing to tests availables sockets\n";
	};
	$SocketManager->on_sockets_selected = function ($arg1, $arg2, $SocketManager, $num_changed, $arg3, $arg4){
		echo "\tHas found $num_changed available socket to talk with\n";
	};
	$SocketManager->on_socket_accepted = function ($arg1, $arg2, $SocketManager, $SocketClient, $arg3, $arg4){
		echo "\tHas engaged a chat with a new client $SocketClient->name\n";
	};
		$SocketManager->on_socket_wrote_input_buffer = function ($arg1, $arg2, $SocketManager, $SocketClient, $arg3, $arg4){
			echo "\t\ta client has spoke to me $SocketClient->name\n";
			echo $SocketClient->buffer;
		};
		$SocketManager->on_socket_closed = function ($arg1, $arg2, $SocketManager, $SocketClient, $arg3, $arg4){
			echo "\t\ta client leaved $SocketClient->name\n";
		};
	$SocketManager->on_sockets_read = function ($arg1, $arg2, $SocketManager, $arg3, $arg4){
		echo "\tsockets have been read\n";
	};
	$SocketManager->on_sockets_verified = function ($arg1, $arg2, $SocketManager, $arg3, $arg4){
		echo "\tall sockets are cleaned\n";
	};
$SocketManager->on_manager_closed = function ($arg1, $arg2, $SocketManager, $arg3, $arg4){
	echo "goodbye\n";
};
/***************************
 * Open sockets
 */
$SocketManager->open();
// verify thats ready
if( $SocketManager->is_usable() ){
    $SocketManager->start_listening();

    /**
     * If you have only one socket and
     *      if all your process goes  throught it,
     *      use a
     *      while( is_listening() ){ do_listen(); } close()
     *
     * In other way you can simply call for do_listen
     *      as soon as you are ready to listen
     *      and treat events
     *
     * .. do something
     *      if( is_listening() ){ do_listen(); }
     * .. do something
     *      if( is_listening() ){ do_listen(); }
     * .. do something else if you want
     *      if( is_listening() ){ do_listen(); }
     * .. do something else if you want
     * .. do something else if you want
     * .. do something else if you want
     *      close()
     * .. do something
     */
    while( $SocketManager->is_listening() ){
        $SocketManager->do_listen();
    }
    // re check
    if( $SocketManager->is_listening() ){
        $SocketManager->close();
    }
    // unlike std socket ext
    // you cannot catch error log here,
    // cause there is no log saved in memory for this instance,
    // everything is directly send to log, i guess this should be added, either via callback, either via an inst buffer
}
?>
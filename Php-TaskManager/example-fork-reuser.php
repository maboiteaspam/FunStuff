<?php

include("src/ResourceProtector/package.include.php");
include("src/Memory_holder/package.include.php");
include("src/Fork/package.include.php");
include("src/SuperClosure.php");

/**
 * We create a memory holder, where the frok manager will
 * write new entries points, and where forked process will read new entries points
 * This is a bridge for both of them...
 */
$memory_holder = new MemcacheMemory_holder("localhost", 11211);

/**
 * We need to protect this resource,
 *  each time we firk, the resource is close, and then re opened after forking
 */
ResourceProtector::inst()->register_resource($memory_holder);

/**
 * Create a fork manager, with a limit to 5 simultaneous childs
 */
$ForkManager = new ReusableForkerManager( 5 );
/**
 * a logger, to listen to debug message
 */
$ForkManager->set_logger_handler(function( $msg ){return;echo $msg."\n";});
/**
 * Attach memory holder
 */
$ForkManager->set_memory_holder($memory_holder);
/**
 * Number of time a fork can be re used before
 * it get's killed
 */
$ForkManager->set_max_reuse_forks( 6 );

$a_no_matter_func = function(){
    echo "Hello Master world ! ";
    echo "From ".  getmypid()." At ".date("H:i:s")."\n";
};

/**
 * Create a func that will be runned in forked process
 * 
 * @param $e
 * @return closure
 */
function make_new_func($e){
    $Forked_func = function () use($e){
            echo "\tHello Forked world ! ";
            echo " From ".$e." My PID is :".  getmypid()." At ".date("H:i:s")."\n";
            sleep(4);
            //echo " still From ".  getmypid()." At ".date("H:i:s")."\n";
        };
    return $Forked_func;
}


/**
 * Add something to do in forked process
 */
for( $e=0; $e< 37; $e++ ){
    $ForkManager->add_to_fork( make_new_func($e) );
}

$a_no_matter_func();

echo "Starting fork \n";
do{
    // required to spawn forks
    $ForkManager->start_forks();
    // required to clean up forks, detect ended etc
    $ForkManager->remove_ended_forks();
    // required to know until when we must continue
}while( $ForkManager->has_forkers_to_fork()
        OR $ForkManager->has_running_forks() );

echo "Ending fork \n";

$a_no_matter_func();
sleep(1);
$a_no_matter_func();

?>

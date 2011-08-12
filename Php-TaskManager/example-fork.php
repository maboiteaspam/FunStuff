<?php

include("src/ResourceProtector/package.include.php");
include("src/Fork/package.include.php");


$ForkManager = new ForkerManager( 2 );
$a_no_matter_func = function(){
    echo "Hello Master world ! \n";
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

<?php

require("src/Forking/Fork/ForkerManager.php");
require("src/Forking/Fork/Forker.php");
require("src/Forking/Fork/ForkProtector.php");
require("src/Forking/Fork/INotForkable.php");
require("src/Forking/Fork/ReusableForkerManager.php");
require("src/Forking/Fork/ReusableForker.php");
require("src/Memory_holder/package.include.php");

exit;
$ForkManager = new ReusableForkerManager();
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );
$ForkManager->add_to_fork( function(){ echo "Hello world ! From ".  getmypid()." At ".date("H:i:s")."\n"; sleep(3); echo date("H:i:s")."\n"; } );

var_dump( getmypid() );
var_dump( date("H:i:s") );

$ForkManager->start_forks();
do{
   $ForkManager->remove_ended_forks();
}while( $ForkManager->has_forkers_to_fork() 
        OR $ForkManager->has_running_forks() );

var_dump( date("H:i:s") );
sleep(1);
var_dump( date("H:i:s") );

?>

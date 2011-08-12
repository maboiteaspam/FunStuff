<?php

include("error-handler.php");

include("src/Forking/Fork/ForkerManager.php");
include("src/Forking/Fork/Forker.php");
include("src/Forking/Fork/ForkProtector.php");
include("src/Forking/Fork/INotForkable.php");
include("src/Forking/Fork/ReusableForkerManager.php");
include("src/Forking/Fork/ReusableForker.php");
include("src/Forking/SuperClosure.php");

include("src/Forking/TaskManager.php");
include("src/Forking/Task/Task.php");
include("src/Forking/Task/EmbedderTask.php");
include("src/Forking/Task/HelperTask.php");
include("src/Forking/Task/BooleanTask.php");
include("src/Forking/Task/ForkedTask.php");

include("src/Forking/TaskController.php");
include("src/Forking/Embedder/IEmbedder.php");
include("src/Forking/Embedder/TaskEmbedder.php");
include("src/Forking/Embedder/TaskForker.php");
include("src/Forking/Embedder/HelperEmbedder.php");


include("src/Memory_holder/Memory_holder.php");
include("src/Memory_holder/ApcMemory_holder.php");
include("src/Memory_holder/FileMemory_holder.php");
include("src/Memory_holder/ProtectedMemoryHolder.php");
include("src/Memory_holder/MemcacheMemory_holder.php");


if( is_dir("data") == false ){
    mkdir("data");
}
if( is_dir("tmp") == false ){
    mkdir("tmp");
}

$files = scandir("data");
foreach( $files as $f ){
    if( $f != "." && $f != ".." && is_file($f) )
        unlink( $f );
}
$files = scandir("tmp");
foreach( $files as $f ){
    if( $f != "." && $f != ".." && is_file($f) )
        unlink( $f );
}

$time_start = microtime(true);
$target_file                = "php://memory";
$target_file                = "data/test_data.csv";
$c                          = "aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb\n";
$target_line_number         = 999995;
$number_of_desired_workers  = 2;
$line_per_worker            = ceil($target_line_number/$number_of_desired_workers);
$line_per_worker = 100000;

$current_line               = 0;
$number_of_part             = ceil($target_line_number/$line_per_worker);

if( is_file($target_file) == false ){
    touch($target_file);
}

/*
$t = function($number_of_part){
    echo $number_of_part;
};
$f = function () use( $number_of_part, $t ){
    $t( $number_of_part );
};

$Rf = new SuperClosure( $f );

$serialized = serialize( $Rf );


$unserialized = unserialize($serialized);

$unserialized();
die();*/

/**
 * A taskManager perform
 * operations of
 *      task add / remove
 *      task surveillance
 *      task kill
 */
$TaskManager = new TaskManager( );
$ForkManager = new ReusableForkerManager( $number_of_desired_workers );

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
        static $messages = array();
        static $skipped = 0;

        $msg = "[".getmypid()."] ".$msg."\n";

        echo $msg;
        return;
        
        $skip = false;
        if( in_array($msg, $messages) ){
            $skip = true;
            $skipped++;
        }else{
            $skipped = 0;
            $messages[] = $msg;
            if( count($messages) > 10 ){
                array_shift($messages);
            }
        }

        if( ! $skip ){
            echo $msg;
        }elseif( $skipped / 1000000000000 > 1 ){
            echo "....".$msg;
        }
    };
}


// set the logger you want to use
$ForkManager->set_logger_handler( $logger_handler );
$memory_holder = new ApcMemory_holder();
$memory_holder = new MemcacheMemory_holder("localhost", 11211);
ForkProtector::register_value($memory_holder);
$memory_holder->set_logger_handler( $logger_handler );
if( $ForkManager instanceof ReusableForkerManager )
    $ForkManager->set_memory_holder( new ProtectedMemoryHolder( $memory_holder ) );

$TaskManager->set_logger_handler( $logger_handler );
$TaskManager->set_memory_holder( new ProtectedMemoryHolder( $memory_holder ) );

for( $i=0; $i<$number_of_part ; $i++ ){
    $t_file = $target_file.".".$i;
    $number_of_line_todo = $current_line+$line_per_worker>$target_line_number?$target_line_number-$current_line:$line_per_worker;

    $TaskManager->add(new HelperTask(function() use($t_file, $c, $number_of_line_todo){

                                        $fhandle = fopen($t_file, "a");
                                        $last_percent = 0;
                                        $writed = 0;
                                        $last_time = microtime(true);

                                        for( $e=0,$f=$number_of_line_todo; $e<$f; $e++ ){
                                            fwrite($fhandle, $c);

                                                $percent = ($writed/$number_of_line_todo) *100;

                                                if( (int)$percent+10>$last_percent ){
                                                    $last_percent+=10;
                                                    $c_time = microtime(true);
                                                    /*echo (int)$percent;
                                                    echo " %";
                                                    echo " in ".($c_time - $last_time);
                                                    echo "\n";*/
                                                    $last_time = $c_time;
                                                }
                                            $writed++;
                                        }
                                          fclose( $fhandle );
                                        echo " $number_of_line_todo lines done \n";
                                    }) )->with_name("generation_fichier_csv")
                                        ->embed_with( new BooleanTask(function(){echo "BooleanTask\n";var_dump(getmypid());return false;}) )
                                        //->embed_with( new OutputRecorder() )
                                        ->embed_with( new TaskForker($ForkManager) )
                                        ->embed_with( new BooleanTask(function(){echo "BooleanTask\n";var_dump(getmypid());return true;}) )
                                    ;
    $current_line += $line_per_worker;
}
$TaskManager->add(new HelperTask(function() use($target_file, $i){
                                        if( file_exists($target_file) )
                                            unlink($target_file);
                                        $fhandle_tgt = fopen($target_file, "a");
                                        for( $e=0,$f=$i; $e<$f; $e++ ){
                                            if( file_exists("$target_file.$e") ){
                                                $fhandle = fopen("$target_file.$e", "r");
                                                while (!feof($fhandle)) {
                                                    $c = fread($fhandle, 8192);
                                                    fwrite($fhandle_tgt, $c);
                                                }
                                                fclose($fhandle);
                                                unlink("$target_file.$e");
                                            }
                                        }
                                          fclose( $fhandle_tgt );
                                    }) )->only_after("generation_fichier_csv")
                                        ->embed_with( new TaskForker($ForkManager) );

$TaskManager->execute();

var_dump( "time : ".(microtime(true) - $time_start) );
?>
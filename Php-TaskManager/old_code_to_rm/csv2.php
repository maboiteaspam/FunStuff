<?php

$target_line_number = 9999995;

$time_start = microtime(true);

$target_file = "php://memory";
$target_file = "data/test_data2.csv";
if( file_exists($target_file) )
    unlink( $target_file );

$files = scandir("data");
foreach( $files as $f ){
    if( $f != "." && $f != ".." && is_file($f) )
        unlink( $f );
}

var_dump( "target : " . $target_file );
$memory = memory_get_usage(true);
var_dump( "memory : " . $memory );

$c = "aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb;aaaa;bbbbb\n";
$h = fopen( $target_file, "a");
$writed = 0;


$last_percent = 0;
$last_time = $time_start;
while( $writed < $target_line_number ){
    fwrite($h, $c);
    $writed++;

    $percent = ($writed/$target_line_number) *100;

    if( (int)$percent+1>$last_percent ){
        $last_percent++;
        $c_time = microtime(true);
        echo (int)$percent;
        echo " %";
        echo " in ".($c_time - $last_time);
        echo "\n";
        $last_time = $c_time;
    }
}

$memory = memory_get_usage(true);
var_dump( "memory : " . $memory );

fclose($h);

$memory = memory_get_usage(true);
var_dump( "memory : " . $memory );
var_dump( "time : ".(microtime(true) - $time_start) );


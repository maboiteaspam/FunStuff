<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 19:02
 * To change this template use File | Settings | File Templates.
 */
 
class ProtectedMemoryHolder extends Memory_holder {

    private static $segment_counts = 0;

    public function __construct( Memory_holder $memory_holder ){
        $this->memory_id        = self::$segment_counts;
        $this->memory_holder    = $memory_holder;
        self::$segment_counts++;
    }
    public function read( $name ){
        return $this->memory_holder->read( $this->memory_id.$name );
    }
    public function write( $name, $data ){
        return $this->memory_holder->write( $this->memory_id.$name, $data  );
    }
    public function exists( $name ){
        return $this->memory_holder->exists( $this->memory_id.$name );
    }
    public function release( $name ){
        return $this->memory_holder->release( $this->memory_id.$name );
    }
}
?>
<?php
class ApcMemory_holder extends Memory_holder{
    public function read( $name ){
        return apc_fetch( $name );
    }
    public function write( $name, $data ){
        if( apc_store($name, $data) ){
            return strlen($data);
        }
    }
    public function exists( $name ){
        apc_fetch($name, $sucess);
        return $sucess;
    }
    public function release( $name ){
        return apc_delete($name);
    }
}
?>
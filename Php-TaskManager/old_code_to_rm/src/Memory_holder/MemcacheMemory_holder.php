<?php
class MemcacheMemory_holder extends Memory_holder implements INotForkable{
    protected $host;
    protected $port;
    protected $memcached;
    protected $is_opened;
    public function __construct($host, $port){
        $this->host = $host;
        $this->port = $port;
        $this->is_opened = false;
    }
    public function __destruct(){
        unset( $this->memcached );
    }
    protected function memcached( ){
        if( $this->memcached == null ){
            $this->open();
        }
        return $this->memcached;
    }
    public function read( $name ){
        return ($this->memcached( )->get($name));
    }
    public function write( $name, $data ){
        if( $this->memcached( )->set($name, ($data)) ){
            return strlen($data);
        }
    }
    public function exists( $name ){
        $d = $this->memcached( )->get($name);
        if( $d === false ){
            return false;
        }
        return true;
    }
    public function release( $name ){
        return $this->memcached( )->delete($name);
    }
    public function close(  ){
        if( $this->memcached !== null ){
            $this->memcached->close();
            $this->memcached = null;
        }
        $this->is_opened = false;
    }
    public function open(  ){
        $this->memcached = new Memcache;
        if( $this->memcached->connect($this->host, $this->port) == false ){
            $this->memcached = null;
            throw new Exception("Cannot connect");
        }
        //$this->memcached->flush();
        $this->is_opened = true;
    }

    public function shutdown(){
        if( $this->is_opened ){
            $this->close();
            $this->is_opened = true;
        }
    }

    public function recover(){
        if( $this->is_opened )
            $this->open();
    }
}
?>
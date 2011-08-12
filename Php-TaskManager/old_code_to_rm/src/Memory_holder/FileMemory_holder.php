<?php
class FileMemory_holder extends Memory_holder{
    public $tmp_path;
    public function  __construct($tmp_path) {
        $this->tmp_path = $tmp_path."/";
    }
    public function read( $name ){
        return file_get_contents( $this->tmp_path.$name );
    }
    public function write( $name, $data ){
        $retour = file_put_contents($this->tmp_path.$name.".tmp", $data);
        rename($this->tmp_path.$name.".tmp", $this->tmp_path.$name);
        return $retour;
    }
    public function exists( $name ){
        return file_exists($this->tmp_path.$name);
    }
    public function release( $name ){
        return unlink($this->tmp_path.$name);
    }
}
?>
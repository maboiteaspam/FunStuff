<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 09/06/11
 * Time: 16:09
 * To change this template use File | Settings | File Templates.
 */
 
abstract class EmbedderTask extends Task {

    public $embedded;
    public function setEmbedded( Task $e ){
        $this->embedded = $e;
    }
    public function name(  ){
        return $this->embedded->name;
    }
    /**
     * @return Closure
     */
    public abstract function __get_embeded( $to_embed );

    /**
     * @return Closure
     */
    protected function __resolve( $to_embed=null ){
    }

}

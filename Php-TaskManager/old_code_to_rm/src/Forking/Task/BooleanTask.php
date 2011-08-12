<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 16:50
 * To change this template use File | Settings | File Templates.
 */
 
class BooleanTask extends Task {
    /**
     * @return Closure
     */
    protected function __resolve( $to_embed=null ){
        $this->log("BooleanTask is creating embedded");
        $callback   = $this->callback;
        $args___    = $this->args;
        return function () use($to_embed, $callback, $args___){
            $retour = (bool)call_user_func_array($callback, $args___);
            if( $retour === true )
                $to_embed();
            else
                return false;
        };
    }
}

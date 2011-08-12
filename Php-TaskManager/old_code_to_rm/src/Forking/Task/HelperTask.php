<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 07/06/11
 * Time: 19:31
 * To change this template use File | Settings | File Templates.
 */
 
class HelperTask extends Task {

    /**
     * @return Closure
     */
    public function __resolve($to_embed=null ){
        $retour = NULL;
        if( is_callable($this->callback) ){
            $callback   = $this->callback;
            $args       = $this->args;
            $retour     = function () use($callback, $args){
                return call_user_func_array($callback, $args);
            };
        }elseif( is_file($this->callback) ){
            $file = $this->callback;
            $args = $this->args;
            $retour = function () use($file, $args){
                foreach( $args as $name => $value ){
                    ${$name} = $value;
                }
                unset($args);
                unset($name);
                unset($value);
                return include ($file);
            };
        }
        return $retour;
    }
}

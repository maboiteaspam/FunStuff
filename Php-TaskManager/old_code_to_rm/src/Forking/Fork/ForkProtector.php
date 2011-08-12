<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 09/06/11
 * Time: 12:07
 * To change this template use File | Settings | File Templates.
 */
 
class ForkProtector {
    protected $values = array();
    protected static $inst;

    /**
     * @static
     * @return ForkProtector
     */
    public static function inst(){
        if( self::$inst == null )
            self::$inst = new ForkProtector();
        return self::$inst;
    }
    
    function protect( $value ){
        if( $value instanceof Closure ){
            $value = new SuperClosure($value);
        }
        $value   = serialize( $value );
        if( $value === false ){
            throw new Exception("non serializable data");
        }
        return $value;
    }
    function unprotect( $value ){
        $value = unserialize($value);
        if( $value === false ){
            throw new Exception("non unserializable data");
        }
        if( $value instanceof Closure ){
            $value = $value->getClosure();
        }
        return $value;
    }


    /**
     * @static
     * @param  INotForkable $value
     * @return void
     */
    public static function register_value( INotForkable $value ){
        self::inst()->add_value( $value );
        return $value;
    }
    public static function shutdown( ){
        self::inst()->shutdown_all( );
    }
    public static function recover( ){
        self::inst()->recover_all( );
    }
    function add_value( $value ){
        $this->values[] = $value;
    }
    
    function shutdown_all(  ){
        foreach( $this->values as $v ){
            $v->shutdown();
        }
    }
    function recover_all(  ){
        foreach( $this->values as $v ){
            $v->recover();
        }
    }
}

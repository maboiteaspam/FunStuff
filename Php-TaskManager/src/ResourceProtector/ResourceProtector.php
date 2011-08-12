<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 09/06/11
 * Time: 12:07
 * To change this template use File | Settings | File Templates.
 */
 
class ResourceProtector {
    protected $resources = array();
    protected static $inst;

    /**
     * @static
     * @return ResourceProtector
     */
    public static function inst(){
        if( self::$inst == null )
            self::$inst = new ResourceProtector();
        return self::$inst;
    }
    
    function protect( $value ){
        if( $value instanceof Closure ){
            $value = new SuperClosure($value);
        }
        $value   = serialize( $value );
        if( $value === false ){
            throw new Exception("serialization failed !");
        }
        return $value;
    }
    function unprotect( $value ){
        $value = unserialize($value);
        if( $value === false ){
            throw new Exception("unserialization failed !");
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
    public static function register_resource( IResourceProtectable $value ){
        self::inst()->add_resource( $value );
        return $value;
    }
    public static function shutdown( ){
        self::inst()->shutdown_all( );
    }
    public static function recover( ){
        self::inst()->recover_all( );
    }
    function add_resource( $resource ){
        $this->resources[] = $resource;
    }
    
    function shutdown_all(  ){
        foreach( $this->resources as $resource ){
            $resource->shutdown();
        }
    }
    function recover_all(  ){
        foreach( $this->resources as $resource ){
            $resource->recover();
        }
    }
}

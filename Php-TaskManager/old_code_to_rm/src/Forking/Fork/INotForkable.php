<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 09/06/11
 * Time: 11:36
 * To change this template use File | Settings | File Templates.
 */
 
interface INotForkable{
    function shutdown();
    function recover();
}
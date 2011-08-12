<?php
/**
 * Created by JetBrains PhpStorm.
 * User: clement
 * Date: 08/06/11
 * Time: 15:47
 * To change this template use File | Settings | File Templates.
 */

class ForkedTask extends Task{
    /**
     * @var ForkManager
     */
    public $forker;
    /**
     * @var Forker
     */
    public $fork;
    
    /**
     * @return Closure
     */
    protected function __resolve( $to_embed=null ){
        $this->log("ForkedTask is creating embedded");
        $fork   = $this->fork;
        $forker = $this->forker;
        $retour = function () use($forker, $fork, $to_embed){
            $fork->set_entry_point($to_embed);
            $forker->start_fork($fork);
        };
        return $retour;
    }

    /**
     */
    public function execute(){
        if( parent::execute() ){
            if( $this->fork === null ){
                throw new Exception("something goes wrong");
            }
            if( $this->fork->has_started() ){
                $this->has_ended    = false;
            }
            
        }
    }

    public function has_executed(){
        return $this->fork->has_started() && $this->fork->has_executed();
    }

    public function has_ended(){
        if( $this->fork !== null ){
            $this->has_ended = $this->fork->has_started() && $this->fork->has_executed();
        }
        return $this->has_ended;
    }

    public function release(){
        $this->forker->close_fork($this->fork);
    }
}
<?php
error_reporting(E_ALL);

class TaskManager{

    public $tasks;
        public $not_ready_tasks;
        public $ready_tasks;
        public $started_tasks;
        public $finished_tasks;

    public function __construct(){
        $this->tasks            = new ArrayObject();
        $this->not_ready_tasks  = new ArrayObject();
        $this->ready_tasks      = new ArrayObject();
        $this->queued_tasks     = new ArrayObject();
        $this->started_tasks    = new ArrayObject();
        $this->finished_tasks   = new ArrayObject();
    }


    public function setup_tasks(){
        foreach( $this->tasks as $index=>$t ){
            $t->setup();
            $this->not_ready_tasks[$index] = $t;
        }
    }
    
    public function get_ready_tasks(){
        $retour = new ArrayObject();
        foreach( $this->not_ready_tasks as $index=>$task ){
            if( $task->is_ready() === true ){
                $task->resolve();
                $retour[$index] = $task;
            }
        }
        return $retour;
    }


    public function run_once(){
        $ready_tasks = $this->get_ready_tasks();
        if( $ready_tasks->count() == 0 ){
            return false;
        }

        foreach( $ready_tasks as $index => $r ){
            $this->ready_tasks[$index] = $r;
            unset( $this->not_ready_tasks[$index] );

            $this->queued_tasks[$index] = $r;
            unset( $this->ready_tasks[$index] );
        }

            foreach( $this->queued_tasks as $index => $task ){
                $task->call();
                if( $task->has_started() ){
                    $this->started_tasks[$index] = $task;
                }
            }

        foreach( $this->started_tasks as $index => $task ){
            unset( $this->queued_tasks[$index] );
            if( $task->has_finished() ){
                $this->finished_tasks[$index] = $task;
            }
        }

        foreach( $this->finished_tasks as $index => $task ){
            unset( $this->started_tasks[$index] );
        }
        


        return true;
    }

    public function run(){
        $this->setup_tasks();
        do{
            if( $this->run_once() === false ){
                echo "no ready tasks\n";
                sleep(1);
            }
        }while( $this->not_ready_tasks->count() > 0
                OR $this->started_tasks->count() > 0
                OR $this->queued_tasks->count() > 0
                OR $this->finished_tasks->count() < $this->tasks->count() );
    }

    public function add( ResolvableOp $op ){
        $Task = new Task( $op );
        
        $this->tasks[] = $Task;

        return $Task;
    }

}
class Task{
    /**
     * @var OpController
     */
    public $controller;
    
    /**
     * @var function
     */
    public $_resolved;
    
    public function  __construct( ResolvableOp $op ) {
        $concrete_classname     = get_class($op);
        $controller_classname   = "OpCoaterController";
        if(class_exists($concrete_classname."Controller") ){
            $controller_classname = $concrete_classname."Controller";
        }

        $this->controller = new $controller_classname();
        $this->controller->_resolvable = $op;
        $this->is_ready = false;
    }

    public function embed_with( ResolvableOp $op ){
        $concrete_classname     = get_class($op);
        if(class_exists($concrete_classname."Controller") ){
            $controller_classname = $concrete_classname."Controller";
        }else{
            $controller_classname = "OpCoaterController";
        }

        $prev_controller    = $this->controller;
        $new_controller     = new $controller_classname();
        if( ! $new_controller instanceof OpCoaterController ){
            throw new Exception("Cannot embed with a ".get_class($new_controller)."");
        }
        $this->controller = new $controller_classname();
        $this->controller->_resolvable = $op;
        $this->controller->_inner_controller = $prev_controller;
    }

    public function setup( ){
        $this->controller->setup();
    }
    public function is_ready( ){
        return $this->controller->is_ready();
    }
    public function has_started(){
        return true;
        //- return $this->controller->has_started();
    }
    public function has_finished(){
        return true;
        //- return $this->controller->has_finished();
    }
    public function has_completed(){
        return true;
        //- return $this->controller->has_completed();
    }
    public function resolve( ){
        $this->_resolved = $this->controller->resolve();
        return $this->_resolved;
    }
    public function call( ){
        $r = $this->_resolved;
        return $r();
    }
}
abstract class OpController{
    /**
     * @var ResolvableOp
     */
    public $_resolvable;
    public $name;
    public $_is_ready       = false;
    public $_has_started    = false;
    public $_has_finished   = false;

    public abstract function is_ready();

    public abstract function setup();

    public abstract function has_started_execution();

    public abstract function has_finished_execution();

    public function resolve( ){
        return $this->_resolvable->resolve();
    }
}
    class OpCoaterController extends OpController{
        public $_inner_controller;

        public function is_ready(){
            if( $this->_is_ready == false ){
                if( $this->_inner_controller !== null )
                    return $this->_inner_controller->is_ready();
                else
                    return true;
            }
            return $this->_is_ready;
        }

        public function setup(){
            if( $this->_inner_controller !== null ){
                $this->_inner_controller->setup();
            }
        }

        public function has_started_execution(){
            if( $this->_has_started == false ){
                if( $this->_inner_controller !== null ){
                    return $this->_inner_controller->has_started_execution();
                }else{
                    return true;
                }
            }
            return $this->_has_started;
        }

        public function has_finished_execution(){
            if( $this->_has_finished == false ){
                if( $this->_inner_controller !== null ){
                    return $this->_inner_controller->has_finished_execution();
                }else{
                    return true;
                }
            }
            return $this->_has_finished;
        }
        
        public function resolve( ){
            $e = null;
            if( $this->_inner_controller !== null )
                $e = $this->_inner_controller->resolve( null );
            return $this->_resolvable->resolve( $e );
        }

    }
class ExecOpController extends OpController{

    public function is_ready(){
        return true;
    }

    public function setup(){
        return true;
    }

    public function has_started_execution(){
        return true;
    }

    public function has_finished_execution(){
        return true;
    }
}

        class SchedulerOpController extends OpCoaterController{
            public $start_time;

            public function setup(){
                parent::setup();
                return $this->start_time = time();
            }
            public function is_ready(){
                if( parent::is_ready() ){
                    if( time() - $this->start_time
                            >= $this->_resolvable->schedule_time ){
                        $this->_is_ready = true;
                    }
                    return $this->_is_ready;
                }
            }

        }
        class ForkOpController extends OpCoaterController{

            protected static $ForkerManager;

            public function setup(){
                var_dump( $this->_resolvable );
                parent::setup();
            }

            public function is_ready(){
                if( parent::is_ready() == true ){
                    if( true ){
                        $this->_is_ready = true;
                    }
                }
                return $this->_is_ready;
            }
            
            public function has_started_execution(){
                if( parent::has_started_execution() == true ){
                    if( /**/ true ){
                        $this->_has_started = true;
                    }
                }
                return $this->_has_started;
            }

            public function has_finished_execution(){
                if( parent::has_finished_execution() == true ){
                    if( true ){
                        $this->_has_finished = true;
                    }
                }
                return $this->_has_finished;
            }
        }

class ResolvableOp{
    //-
}
class ifOp extends ResolvableOp{
    public $input_call;
    public function __construct($input_call){
        $this->input_call = $input_call;
    }
    public function resolve($_coated){
        $_coater = $this->input_call;
        return function() use ($_coater, $_coated){
            $retour = $_coater();
            if( $retour === true )
                $_coated();
        };
    }
}
class ifNotOp extends ResolvableOp{
    public $input_call;
    public function __construct($input_call){
        $this->input_call = $input_call;
    }
    public function resolve($_coated){
        $_coater = $this->input_call;
        return function() use ($_coater, $_coated){
            $retour = $_coater();
            if( $retour === false )
                $_coated();
        };
    }
}
class ForkOp extends ResolvableOp{
    public function resolve($_coated){
        return function() use($_coated){
            echo "Fork \n";
            $_coated();
        };
    }
}
class WaitOp extends ResolvableOp{
    public $time_to_wait;
    public function __construct($time_to_wait){
        $this->time_to_wait = $time_to_wait;
    }
    public function resolve($_coated){
        $time_to_wait = $this->time_to_wait;
        return function() use($time_to_wait, $_coated){
            echo "im waiting !!\n";
            sleep($time_to_wait);
            $_coated();
        };
    }
}
class SchedulerOp extends ResolvableOp{
    public $schedule_time;
    public function __construct($schedule_time){
        $this->schedule_time = $schedule_time;
    }
    public function resolve($_coated){
        return $_coated;
    }
}
class ExecOp extends ResolvableOp{
    public $input_call;
    public function __construct($input_call){
        $this->input_call = $input_call;
    }
    public function resolve(){
        return $this->input_call;
    }
}






$TaskManager = new TaskManager();
$Task = $TaskManager->add(new ExecOp(function(){ echo "hello world ! should be runned into a forked process, needs to be done...."; }));
$Task->embed_with(new ifOp( function(){ return FALSE;} ));
$Task->embed_with(new ForkOp());
$Task->embed_with(new ifOp( function(){ return TRUE;} ));
$Task->embed_with(new WaitOp( 1 ));

$TaskManager->run();

echo "\nended";
exit;

?>
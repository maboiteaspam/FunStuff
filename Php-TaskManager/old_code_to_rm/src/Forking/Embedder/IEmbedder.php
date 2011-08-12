<?php

interface IEmbedder{
    public function __embed( TaskManager $TaskManager, TaskController $task );
}

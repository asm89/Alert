<?php

namespace Alert;

use Ev, EvLoop;

class EvReactor implements Reactor {
    
    private $loop;
    private $watchers = [];
    private $lastWatcherId = 0;
    private $isRunning = FALSE;
    
    function __construct() {
        $this->loop = new EvLoop;
    }

    function run() {
        $this->isRunning = TRUE;
        while ($this->isRunning) {
            $this->tick();
        }
    }
    
    function tick() {
        $this->loop->run(Ev::RUN_ONCE | Ev::RUN_NOWAIT);
    }
    
    function stop() {
        $this->isRunning = FALSE;
        $this->loop->stop();
    }
    
    function immediately(callable $callback) {
        return $this->once($callback, $delay = 0);
    }
    
    function once(callable $callback, $delay) {
        $watcherId = $this->getNextWatcherId();
        $wrapper = function() use ($callback, $watcherId) {
            try {
                $callback();
                $this->cancel($watcherId);
            } catch (\Exception $e) {
                $this->stop();
                throw $e;
            }
        };
        
        $watcher = $this->loop->timer($delay, 0., $wrapper);
        $this->watchers[$watcherId] = [$watcher, $wrapper];
        
        return $watcherId;
    }
    
    function schedule(callable $callback, $interval) {
        $watcherId = $this->getNextWatcherId();
        $wrapper = function($timer) use ($callback, $watcherId) {
            try {
                $callback();
                $timer->start();
            } catch (\Exception $e) {
                $this->stop();
                throw $e;
            }
        };
        
        $watcher = $this->loop->timer($interval, $interval, $wrapper);
        
        $this->watchers[$watcherId] = [$watcher, $wrapper];
        
        return $watcherId;
    }
    
    function cancel($watcherId) {
        unset($this->watchers[$watcherId]);
    }
    
    function disable($watcherId) {
        if (isset($this->watchers[$watcherId])) {
            $watcher = $this->watchers[$watcherId][0];
            return $watcher->is_active ? $watcher->stop() : NULL;
        }
    }
    
    function enable($watcherId) {
        if (isset($this->watchers[$watcherId])) {
            $watcher = $this->watchers[$watcherId][0];
            return $watcher->is_active ? NULL : $watcher->start();
        }
    }
    
    function onReadable($stream, callable $callback) {
        return $this->watchIoStream($stream, Ev::READ, $callback);
    }
    
    function onWritable($stream, callable $callback) {
        return $this->watchIoStream($stream, Ev::WRITE, $callback);
    }
    
    private function watchIoStream($stream, $flag, $callback) {
        $watcherId = $this->getNextWatcherId();
        
        $wrapper = function() use ($callback, $watcherId) {
            try {
                $callback();
            } catch (\Exception $e) {
                $this->stop();
                throw $e;
            }
        };
        
        $watcher = $this->loop->io($stream, $flag, $wrapper);
        $this->watchers[$watcherId] = [$watcher, $wrapper];
        
        return $watcherId;
    }
    
    private function getNextWatcherId() {
        if (($watcherId = ++$this->lastWatcherId) === PHP_INT_MAX) {
            $this->lastWatcherId = 0;
        }
        
        return $watcherId;
    }
    
}

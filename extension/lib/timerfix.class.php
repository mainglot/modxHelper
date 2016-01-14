<?php

class TimerFix {
    
    protected $_list = array();
    protected $_timeStart;
    protected $_timeLast;
    protected $_templateHtml = '%2$s - %1$s s';
    
    public function __construct () {
        $this->_timeStart = microtime(true);
        $this->_timeLast = $this->_timeStart;
        $this->_list[0] = array($this->_timeStart, 'Object was created');
    }
    
    public function __toString () {
        $o = $this->getReport(true);
        $this->clear();
        return $o;
    }
    
    public function showExitReport () {
        register_shutdown_function(array($this, 'echoReport'));
        return true;
    }
    
    public function echoReport () {
        echo $this->getReport(true);
    } 
    
    public function exportExitReport () {
        register_shutdown_function(array($this, 'exportReport'));
        return true;
    }
    
    public function exportReport () {
        var_export($this->getReport());
    } 
    
    public function fix ($msg) {
        $this->_list[] = array($this->_getDeltaTime(), $msg);
        return true;
    }
    
    public function clear () {
        $this->_list = array(
            array(microtime(true), 'Clear story of '. __CLASS__)
            );
        $this->_lastTime = $this->_list[0][0];
        return true;
    }
    
    public function getReport ($isHtml = false) {
        $this->_list[] = array($this->_getTotalTime(), 'Total time');
        return $this->{'_getReport' . ($isHtml ? 'Html' : 'Array') }();
    }
    
    protected function _getReportArray () {
        return $this->_list;
    }
    
    protected function _getReportHtml () {
        return implode("<br>", array_map(function($item){
                return sprintf($this->_templateHtml, $item[0], $item[1]);
            }, $this->_list)).'<br>';
    }
    
    protected function _getTotalTime () {
        return $this->_round(microtime(true) - $this->_timeStart);
    }
    
    protected function _getDeltaTime () {
        $currentTime = microtime(true);
        $deltaTime = $currentTime - $this->_timeLast;
        $this->_timeLast = $currentTime;
        return $this->_round($deltaTime);
    }
    
    protected function _round ($number) {
        return round($number, 6);
    }
}

return 'TimerFix';

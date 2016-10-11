<?php

class TimerFix {
    
    protected $_list = array();
    protected $_timeStart;
    protected $_timeLast;
    protected $_templateHtml = '%2$s - %1$s s';

    /**
     * TimerFix constructor.
     */
    public function __construct () {
        $this->_timeStart = microtime(true);
        $this->_timeLast = $this->_timeStart;
        $this->_list[0] = array($this->_timeStart, 'Object was created');
    }

    /**
     * @return string
     */
    public function __toString () {
        $o = $this->getReport(true);
        $this->clear();
        return (string) $o;
    }

    /**
     * @return bool
     */
    public function showExitReport () {
        register_shutdown_function(array($this, 'echoReport'));
        return true;
    }

    /**
     * @description Send to output buffer
     */
    public function echoReport () {
        echo $this->getReport(true);
    }

    /**
     * @return bool
     */
    public function exportExitReport () {
        register_shutdown_function(array($this, 'exportReport'));
        return true;
    }

    /**
     * @description Send to output buffer
     */
    public function exportReport () {
        var_export($this->getReport());
    }

    /**
     * @param $msg
     * @return bool
     */
    public function fix ($msg) {
        $this->_list[] = array($this->_getDeltaTime(), $msg);
        return true;
    }

    /**
     * @return bool
     */
    public function clear () {
        $this->_list = array(
            array(microtime(true), 'Clear story of '. __CLASS__)
            );
        $this->_timeLast = $this->_list[0][0];
        return true;
    }

    /**
     * @param bool $isHtml
     * @return mixed
     */
    public function getReport ($isHtml = false) {
        $this->_list[] = array($this->_getTotalTime(), 'Total time');
        return $this->{'_getReport' . ($isHtml ? 'Html' : 'Array') }();
    }

    /**
     * @return array
     */
    protected function _getReportArray () {
        return $this->_list;
    }

    /**
     * @return string
     */
    protected function _getReportHtml () {
        $html = $this->_templateHtml;
        return implode('<br>', array_map(function($item) use ($html) {
                return sprintf($html, $item[0], $item[1]);
            }, $this->_list)).'<br>';
    }

    /**
     * @return float
     */
    protected function _getTotalTime () {
        return $this->_round(microtime(true) - $this->_timeStart);
    }

    /**
     * @return float
     */
    protected function _getDeltaTime () {
        $currentTime = microtime(true);
        $deltaTime = $currentTime - $this->_timeLast;
        $this->_timeLast = $currentTime;
        return $this->_round($deltaTime);
    }

    /**
     * @param $number
     * @return float
     */
    protected function _round ($number) {
        return round($number, 6);
    }
}

return 'TimerFix';

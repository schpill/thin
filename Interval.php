<?php
    namespace Thin;
    class stopwatch
    {
        # initialize properties
        # NvP array of time intervals
        private $intervals = array();

        # elapsed microtime for the latest entry
        private $lastInterval = 0;

        # microtime when the stopwatch started
        private $startTime = false;

        private $microsec = false;

        ### constructor
        function __construct()
        {
            # start the timer by default
            $this->start();
        }

        ### make one entry with an ID name in the interval array - AddInterval($id, $oneTime= 0)
        private function addInterval($id, $oneTime= 0)
        {
            # enter the time for the named interval
            $this->intervals[$id] = $oneTime;

        }

        ### return all the intervals so far - AllIntervals()
        function allIntervals()
        {
            # return the complete NvP interval array
            return $this->intervals;
        }

        ### get the time for one named interval or the latest interval - GetNamedInterval($id = null)
        function getNamedInterval($id = null)
        {
            # if $id name is null
            if ($id === null) {
                # return the latest interval
                foreach($this->intervals as $id =>$val) ;
                $ret = "$id = $val";
            # else if there is a $id name
            } elseif (Arrays::exists($id, $this->intervals)) {
                # return the named interval
                $ret = "$id = {$this->intervals[$id]}";
            }

            # else return 'no value'
            else $ret = "$id = no value";
            return $ret;
        }

        ### record the duration for one interval - Interval($id = null)
        function get($id = null)
        {
            # get the current microtime
            $now = microtime(true);

            # if no $id name is given for the interval
            if ($id == null) {
                # give it a counter interval name
                $cnt = count($this->intervals) ;
                $cnt++;
                $id = "~interval $cnt";
            }

            # if starting the stop watch
            if ($this->startTime == false) {
                # set start time interval to zero
                $incr = 0;
                # note the starting time
                $this->startTime = $now;
            } else {
                # compute time since the prior interval
                $incr = $now - $this->lastInterval;

                # if calling for microseconds round the time to microseconds otherwise to milliseconds
                $incr = ($this->microsec)
                    ? round($incr, 6)
                    : round($incr, 3);
            }

            # add it to the interval array
            $this->addInterval($id, $incr);

            # update the last interval time
            $this->lastInterval  = $now;

        }

        ### start or restart the interval timer - Start($id = null, $microseconds=false )
        function start($id = null, $microseconds=false )
        {
            # reset the interval array
            $this->intervals = array();

            # if no $id name is sent call it '~start'
            if ($id == null) {
                $id = '~start';
            }

            # set or clear the microsecond flag
            $this->microsec = $microseconds;

            # make the starting entry in the interval array
            $this->startTime = false;
            $this->get($id);
        }

        ### stop timing and return the interval array - Stop($id = null)
        function stop($id = null)
        {
            # if no $id name is sent call it '~stopped'
            if ($id == null) {
                $id = '~stopped';
            }

            # enter a stop interval time
            $this->get($id);

            # sum all intervals
            $tot = 0;
            foreach ($this->intervals as $t) {
                $tot += $t;
            }

            # make a total time entry in the array
            $this->addInterval("~total time", $tot);

            # return the complete array
            return $this->allIntervals();
        }
    }

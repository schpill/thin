<?php
    namespace Thin;
    declare(ticks = 1);
    class Multithread
    {
        protected $maxProcesses     = 10;
        protected $sleepTime        = 1;
        protected $jobsStarted      = 0;
        protected $currentJobs      = array();
        protected $signalQueue      = array();
        protected $parentPID        = 0;
        protected $argv             = "";

        public function __construct($argvs)
        {
            $this->argv         = $argvs;
            $this->parentPID    = getmypid();
            pcntl_signal(SIGCHLD, array($this, "childSignalHandler"));
        }

        /**
         * Run the Daemon
         */
        public function run($jobs)
        {
            $lenTab = count($jobs);
            for ($i = 0 ; $i < $lenTab ; $i++) {
                $jobID = rand(0, 100);
                while (count($this->currentJobs) >= $this->maxProcesses) {
                    sleep($this->sleepTime);
                }

                $launched = $this->launchJobProcess($jobID, "Jobs", $jobs[$i]);
            }

            while (count($this->currentJobs)) {
                sleep($this->sleepTime);
            }
        }

        protected function launchJobProcess($jobID, $name, $job)
        {
            $pid = pcntl_fork();
            if ($pid == -1) {
                error_log('Could not launch new job, exiting');
                return false;
            } else if ($pid) {
                $this->currentJobs[$pid] = $jobID;
                if (isset($this->signalQueue[$pid])) {
                    $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                    unset($this->signalQueue[$pid]);
                }
            } else {
                $exitStatus = 0;
                $job->run($this->argv);
                exit($exitStatus);
            }
            return true;
        }

        public function childSignalHandler($signo, $pid = null, $status = null)
        {
            if (!$pid) {
                $pid = pcntl_waitpid(-1, $status, WNOHANG);
            }

            while ($pid > 0) {
                if ($pid && isset($this->currentJobs[$pid])) {
                    $exitCode = pcntl_wexitstatus($status);
                    if ($exitCode != 0) {}
                    unset($this->currentJobs[$pid]);
                } else if ($pid) {
                    $this->signalQueue[$pid] = $status;
                }
                $pid = pcntl_waitpid(-1, $status, WNOHANG);
            }
            return true;
        }

        /**
         * Launch a job from the job queue
         */
        protected function launchJob($jobID)
        {
            $pid = pcntl_fork();
            if ($pid == -1) {
                error_log('Could not launch new job, exiting');
                echo 'Could not launch new job, exiting';
                return false;
            } else if ($pid) {
                $this->currentJobs[$pid] = $jobID;
                if (isset($this->signalQueue[$pid])) {
                    $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]);
                    unset($this->signalQueue[$pid]);
                }
            } else {
                $exitStatus = 0; //Error code if you need to or whatever
                exit($exitStatus);
            }
            return true;
        }
    }

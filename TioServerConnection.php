<?php 
class TioServerConnection {
    var $s;
    var $receiveBuffer;
    var $pendingEvents;
    var $sinks;
    var $poppers;
    var $dontWaitForAnswers;
    var $pendingAnswerCount;
    var $containers;
    var $stop;
    var $log_sends;
    var $running_queries;
	
    function TioServerConnection($host = "", $port = "") {
        if (($this->s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false)
            Throw new Exception("socket_create() failed: reason: ".socket_strerror(socket_last_error())."\n");
        $this->receiveBuffer = '';
        $this->pendingEvents = array();
        $this->sinks = array();
        $this->poppers = array();
        $this->dontWaitForAnswers = False;
        $this->pendingAnswerCount = 0;
        $this->containers = array();
        $this->stop = False;
        $this->log_sends = False;
        $this->running_queries = array();
        
        if ($host)
            $this->connect($host, $port);
    }
    
    function connect($host, $port) {
        if (socket_connect($this->s, $host, $port) == false)
            Throw new Exception("socket_connect() failed.\nReason: ($result) ".socket_strerror(socket_last_error($socket))."\n");
    }
	
	function close(){
		socket_close($this->s);
	}
	
	function receiveLine(){
		$i =  ereg("\r\n", $this->receiveBuffer);
		while(!$i){
			socket_recv ($socket , $buf , 4096);
			$this->receiveBuffer += $buf;
			if(!$this->receiveBuffer)
				Throw new Exception("error reading from connection socket");
			$i =  ereg("\r\n", $this->receiveBuffer);
		}
	}
    
}

?>

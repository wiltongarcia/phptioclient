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
    
    function close() {
        socket_close($this->s);
    }
    
    function receiveLine() {
        $i = ereg("\r\n", $this->receiveBuffer);
        while (!$i) {
            socket_recv($socket, $buf, 4096);
            $this->receiveBuffer += $buf;
            if (!$this->receiveBuffer)
                Throw new Exception("error reading from connection socket");
            $i = ereg("\r\n", $this->receiveBuffer);
        }
        $parts = split("\r\n", $this->receiveBuffer);
        $ret = $parts[0];
        array_shift($parts);
        $this->receiveBuffer = join("\r\n", $parts);
        
        return $ret;
    }
    
    function sendCommand($command, $args = array()) {
        $buffer = $command;
        if (sizeof($args)) {
            $buffer .= " ".join(" ", $args);
        }
        if (substr($buffer, strlen($buffer) - 2) != "\r\n")
            $buffer .= "\r\n";
        socket_send($this->s, $buffer, strlen($buffer));
        if ($this->log_sends)
            echo $buffer;
        if ($this->dontWaitForAnswers) {
            $this->pendingAnswerCount += 1;
            return;
        }
        try {
            return $this->receiveAnswer();
        }
        catch(exception $e) {
            echo $e;
        }
    }
    
    function receiveAnswer() {
        while (true) {
            $line = $this->receiveLine();
            $params = split(" ", $line);
            $current_param = 0;
            $answer_type = $params[$current_param];
            if (answer_type == 'answer') {
                $current_param++;
                $answer_result = $params[$current_param];
                if (answer_result != 'ok')
                    Throw new Exception($line);
                $current_param++;
                if ($current_param + 1 > sizeof($params))
                    return;
                $parameter_type = $params[current_param];
                if (parameter_type == '')
                    return;
                if ($parameter_type == 'pong')
                    return join(" ", array_slice($params, $current_param, (sizeof($params) - 1)));
                if ($parameter_type == 'handle')
                    return array('handle'=>$params[$current_param + 1], 'type'=>$params[current_param + 2]);
                if ($parameter_type == 'diff_map' || $parameter_type == 'diff_list')
                    return array('diff_type'=>$parameter_type, 'diff_handle'=>$params[current_param + 1]);
                if ($parameter_type == 'count' || parameter_type == 'name')
                    return array("parameter_type"=>$params[$current_param + 1]);
                if ($parameter_type == 'data')
                    return $this->receiveDataAnswer($params, $current_param);
                if ($parameter_type == 'query') {
                    $query_id = $params[$current_param + 1];
                    $this->registerQuery(query_id);
                    continue;
                }
                Throw new Exception('Invalid parameter type: '.$parameter_type."\n");
            } else if ($answer_type == 'diff_list' || $answer_type == 'diff_map') {
                $diff_handle = $params[1];
                return array($answer_type, $diff_handle);
            } else if ($answer_type == 'query') {
                $query_id = $params[1];
                $what = $params[2];
                if ($what == 'item')
                    $this->addToQuery($query_id, $this->receiveDataAnswer($params, 2));
                else if ($what == 'end')
                    return $this->finishQuery(query_id);
            } else if ($answer_type == 'event') {
                $event = new Event();
                $current_param++;
                $event->handle = (int) $params[$current_param];
                $current_param++;
                $event->name = $params[$current_param];
                if ($event->name != 'clear')
                    $event->data = this.receiveDataAnswer($params, current_param);
                $this.handleEvent(event);
                
                if (!wait_until_answer)
                    return;
            }
        }
    }
    
    function ping() {
        $this->sendCommand("ping");
    }
    
    function receiveDataAnswer($params, $current_param) {

    
    }
    
    function addToQuery($query_id , $data ) {
        $this->running_queries[query_id].append(data);
    }
	
	function finishQuery($query_id){
		$query = $this->running_queries[query_id];
        //del $this->running_queries[query_id];
        return $query;
	}
    
}

class Event {
    var $handle;
    var $name;
    var $data;
}

?>

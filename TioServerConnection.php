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
        $i = preg_match("/\r\n/", $this->receiveBuffer);
        while ($i <= 0) {
            socket_recv($this->s, $buf, 4096, 0);
            $this->receiveBuffer .= $buf;
            if (!$this->receiveBuffer)
                Throw new Exception("error reading from connection socket");
                
            $i = preg_match("/\r\n/", $this->receiveBuffer);
        }
        $parts = preg_split("/\r\n/", $this->receiveBuffer);
        $ret = $parts[0];
        $parts = array_shift($parts);
        for ($i = 1; $i < sizeof($parts); $i++)
            $nparts[$i - 1] = $parts[$i];
        $parts = $nparts;
        if (sizeof($parts) > 0)
            $this->receiveBuffer = join("\r\n", $parts);
        else
            $this->receiveBuffer = "";
        return $ret;
    }
    
    function sendCommand($command, $args = array()) {
        $buffer = $command;
        if (sizeof($args) > 0) {
            $buffer .= " ".join(" ", $args);
        }
        if (substr($buffer, strlen($buffer) - 2) != "\r\n")
            $buffer .= "\r\n";
        socket_send($this->s, $buffer, strlen($buffer), 0);
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
            if ($answer_type == 'answer') {
                $current_param++;
                $answer_result = $params[$current_param];
                if ($answer_result != 'ok')
                    Throw new Exception($line);
                $current_param++;
                if ($current_param + 1 > sizeof($params))
                    return;
                $parameter_type = $params[$current_param];
                if ($parameter_type == '')
                    return;
                if ($parameter_type == "pong") {
                    return join(" ", array_slice($params, $current_param, (sizeof($params) - 1)));
                }
                if ($parameter_type == 'handle')
                    return array('handle'=>$params[$current_param + 1], 'type'=>$params[$current_param + 2]);
                if ($parameter_type == 'diff_map' || $parameter_type == 'diff_list')
                    return array('diff_type'=>$parameter_type, 'diff_handle'=>$params[$current_param + 1]);
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
                    $event->data = $this->receiveDataAnswer($params, $current_param);
                $this->handleEvent($event);
                
                if (!wait_until_answer)
                    return;
            }
        }
    }
    
    function sendDataCommand($command, $parameter, $key, $value, $metadata) {
        $buffer = $command;
        if (strlen($parameter) > 0 && $parameter != NULL)
            $buffer .= " $parameter";
        $key = $this->serializeData($key);
        $value = $this->serializeData($value);
        $metadata = $this->serializeData($metadata);
        $buffer .= $this->getFieldSpec('key', $key);
        $buffer .= $this->getFieldSpec('value', $value);
        $buffer .= $this->getFieldSpec('metadata', $metadata);
        $buffer .= "\r\n";
        if ($key)
            $buffer .= $key[0]."\r\n";
        if ($value)
            $buffer .= $value[0]."\r\n";
        if ($metadata)
            $buffer .= $metadata[0]."\r\n";
        if ($this->log_sends)
            echo $buffer;
        return $this->sendCommand($buffer);
    }
    
    function serializeData($data) {
        if (($data == NULL || $data == "") && !is_numeric($data))
            return NULL;
        if (is_string($data))
            return array($data, 'string');
        if (is_int($data) || is_numeric($data))
            return array((string) $data, 'int');
        if (is_float($var))
            return array((string) $data, 'double');
        Throw new Exception("not supported data type\n");
    }
    
    function getFieldSpec($field_name, $field_data_and_type) {
        if ($field_data_and_type != NULL)
            return ' '.$field_name.' '.$field_data_and_type[1].' '.(string) strlen($field_data_and_type[0]);
        else
            return "";
    }
    
    function ping() {
        $this->sendCommand("ping");
    }
    
    function receiveDataAnswer($params, $current_param) {
        $fields = array();
        while ($current_param + 1 < sizeof($params)) {
            $current_param++;
            $name = $params[$current_param];
            $current_param++;
            $type = $params[$current_param];
            $current_param++;
            $size = (int) $params[$current_param];
            
            $data_buffer = substr($this->receiveData($size + 2), 0, $size);
            if ($type == 'int')
                $value = (int) $data_buffer;
            else if ($type == 'double')
                $value = (float) $data_buffer;
            else if ($type == 'string')
                $value = $data_buffer;
            else
                Throw new Exception("not supported data type: $type\n");
            $fields[$name] = $value;
            return array($fields['key'], $fields['value'], $fields['key']);
        }
    }
    
    function receiveData($size) {
        while (strlen(receiveBuffer) < $size) {
            socket_recv($this->s, $buf, 4096, 0);
            $this->receiveBuffer .= $buf;
            $ret = substr($string, $size);
            $this->receiveBuffer = substr($string, $size, (strlen($this->receiveBuffer)) - $size);
            return $ret;
        }
    }
	
	/*def RegisterQuery(self, query_id):
        self.running_queries[query_id] = []*/
	
	function registerQuery($query_id){
		$this->running_queries = array();
	}
    
    function handleEvent($event) {
        if (!isset($this->pendingEvents[$event->handle]))
            $this->pendingEvents[$event->handle] = array($event);
        else
            array_push($this->pendingEvents[$event->handle], $event);
    }
    
    function addToQuery($query_id, $data) {
    	if (!isset($this->running_queries[$query_id]))
            $this->running_queries[query_id] = array($data);
        else
            array_push($this->running_queries[query_id], $data);
    }
    
    function finishQuery($query_id) {
        $query = $this->running_queries[query_id];
        return $query;
    }
    
    function createContainer($name, $type) {
        return $this->createOrOpenContainer('create', $name, $type);
    }
    
    function createOrOpenContainer($command, $name, $type) {
        if (!type)
            $args = array($name);
        else
            $args = array($name, $type);
        $info = $this->sendCommand($command, $args);
        $handle = $info['handle'];
        $type = $info['type'];
        $container = new RemoteContainer($this, $handle, $type, $name);
        $this->containers[(int) $handle] = $container;
        return $container;
    }
    
    function subscribe($handle, $sink, $filter = '*', $start = NULL) {
        $param = (string) $handle;
        if ($start != NULL)
            $param .= " ".(string) $start;
        if (!isset($this->sinks[$handle][$filter]))
            $this->sinks[$handle][$filter] = array($sink);
        else
            array_push($this->sinks[$handle][$filter], $sink);
        $this->sendCommand("subscribe", array($param));
    }
    
    /*def DispatchEvents(self, handle):
     events = self.pendingEvents.get(handle)
     if not events:
     return
     
     for e in events:
     if e.name == 'wnp_key':
     key = e.data[0]
     f = self.poppers[int(handle)]['wnp_key'][key].pop()
     if f:
     f(self.containers[e.handle], e.name, *e.data)
     elif e.name == 'wnp_next':
     f = self.poppers[int(handle)]['wnp_next'].pop()
     if f:
     f(self.containers[e.handle], e.name, *e.data)
     else:
     handle = int(handle)
     sinks = self.sinks[handle].get(e.name)
     if sinks is None:
     sinks = self.sinks[handle].get('*', [])
     for sink in sinks:
     sink(self.containers[e.handle], e.name, *e.data)*/


    function dispatchEvents($handle) {
        $events = $this->pendingEvents[$handle];
        if ( empty($events))
            return;
        foreach ($events as $e) {
            if ($e->name == 'wnp_key') {
                /*$key = $e->data[0];
                 $f = array_pop($this->poppers[int(handle)]['wnp_key']);
                 if($f)
                 f(self.containers[e.handle], e.name, *e.data);*/
            } else if ($e->name == 'wnp_next') {
                /*f = self.poppers[int(handle)]['wnp_next'].pop()
                 if f:
                 f(self.containers[e.handle], e.name, *e.data)*/
            } else {
                $handle = (int) $handle;
                $sinks = $this->sinks[$handle][$e->name];
                if (is_null($sinks))
                    $sinks = $this->sinks[$handle]['*'];
                foreach ($sinks as $sink) {
                    $sink($this->containers[$e->handle], $e->name, @$e->data);
                }
            }
        }
    }
    
    function dispatchAllEvents() {
        foreach ($this->pendingEvents as $a) {
            $this->dispatchEvents(key($this->pendingEvents));
            next($this->pendingEvents);
        }
    }
    
    function diffStart($handle) {
        return $this->sendCommand("diff_start $handle");
    }
	
	function diff($diff_handle){
		return $this->sendCommand("diff $diff_handle");
	}
    
}

class Event {
    var $handle;
    var $name;
    var $data;
}

class RemoteContainer {
    var $manager;
    var $handle;
    var $type;
    var $name;
    function RemoteContainer($manager, $handle, $type, $name) {
        $this->manager = $manager;
        $this->handle = $handle;
        $this->type = $type;
        $this->name = $name;
    }
    
    function clear() {
        return $this->manager->sendCommand('clear', array($this->handle));
    }
    
    function subscribe($sink, $event_filter = '*', $start = NULL) {
        $this->manager->subscribe($this->handle, $sink, $event_filter, $start);
    }
    
    function pushBack($value, $metadata = NULL) {
        return $this->sendDataCommand('push_back', NULL, $value, $metadata);
    }
    
    function sendDataCommand($command, $key = NULL, $value = NULL, $metadata = NULL) {
        return $this->manager->sendDataCommand($command, $this->handle, $key, $value, $metadata);
    }
    
    function insert($key, $value, $metadata = NULL) {
        return $this->sendDataCommand('insert', $key, $value, $metadata);
    }
    
    function pushFront($value, $metadata = NULL) {
        return $this->sendDataCommand('push_front', NULL, $value, $metadata);
    }
    
    function get($key, $withKeyAndMetadata = False) {
        $rtr = $this->sendDataCommand('get', $key, NULL, NULL);
        if (!$withKeyAndMetadata)
            return $rtr[1];
        else
            return $rtr;
    }
    
    function set($key, $value, $metadata = NULL) {
        return $this->sendDataCommand('set', $key, $value, NULL);
    }
    
    function diffStart() {
        $result = $this->manager->diffStart($this->handle);
        return $result['diff_handle'];
    }
    
    function diffQuery($diff_handle) {
        return $this->manager->diff($diff_handle);
    }
}

?>

<?php 
require "TioServerConnection.php";
class TioClient extends TioServerConnection {
	var $host;
	var $port;
	var $container;
    function connect($url) {
        $this->parseUrl($url);
		if($this->container)
			Throw new Exception("container specified, you must inform a url with just the server/port");
		return new TioServerConnection($this->host,$this->port);
    }
    
    function parseUrl($url) {
        if (substr($url, 0, 6) != "tio://")
            Throw new Exception("protocol not supported");
        try {
        	$url = split("/",substr($url, 6));
			if(sizeof($url)>1)
				$this->container = $url[1];
			$split_url = split(":", $url[0]);
			$this->host =  $split_url[0];
			$this->port = $split_url[1];
        }catch(exception $e){
        	echo $e;
        }
    }
}
?>

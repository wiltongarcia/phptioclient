<?php 
require "TioClient.php";
$tioclient = new TioClient();
$man = $tioclient->connect("tio://192.168.56.101:6666");
$rtr = $man->sendCommand("ping");
if($rtr=="pong")
	echo "resposta: $rtr\n";
else
	echo "error\n";
?>

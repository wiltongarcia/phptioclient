<?php 
require "TioClient.php";
$tioclient = new TioClient();
$man = $tioclient->connect("tio://peq.nu:6666");
$rtr = $man->sendCommand("ping");
if($rtr=="pong")
	echo "resposta: $rtr\n";
else
	echo "error\n";

$man->close()
?>

<?php 

require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://127.0.0.1:6666");
$rtr = $man->sendCommand("ping");
echo "$rtr\n";
?>

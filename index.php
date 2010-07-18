<?php 
require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://127.0.0.1:6666");
$man->sendCommand("ping", array("teste","seila"));
?>

<?php 

require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://192.168.56.101:6666");
$container = $man->createContainer('teste', 'volatile/list');
echo $container->clear();

?>

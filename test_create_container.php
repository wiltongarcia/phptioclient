<?php 
require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://192.168.56.101:6666");
$container = $man->createContainer('teste', 'volatile/list');
echo $container->clear();
$sink = function($parm) {
    echo $parm;
}
;
$container->subscribe($sink);
while(TRUE) {
	$container->pushBack(10, 'metadata');
	$container[0] = 'Tio Client';
	$container->insert(0, 'test');
	$container->push_front($value=12334567);
	if(sizeof($containet)==3)
		echo "ok";
	else echo "error";
	break;
}

?>

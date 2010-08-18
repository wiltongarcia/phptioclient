<?php 
require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://localhost:6666");
//$man->log_sends = TRUE;
$container = $man->createContainer('teste123', 'volatile_list');
echo $container->clear();
$sink = function($v1,$v2,$v3) {
    echo "<event:$v2 />\n";
};
$container->subscribe($sink);
while(TRUE) {
	$container->pushBack(1, 'metadata');
	$container->set(0, "Tio Client");
	$container->insert(0, 'test123');
	$container->pushFront($value=12334567);
	$vlr = $container->get(0, True);
	break;
}
$man->dispatchAllEvents();
?>

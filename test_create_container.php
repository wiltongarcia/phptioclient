<?php 
require "TioClient.php";

$tioclient = new TioClient();
$man = $tioclient->connect("tio://localhost:6666");
$container = $man->createContainer('teste123', 'volatile_list');
echo $container->clear();
$sink = function($parm) {
    echo $parm;
};
$container->subscribe($sink);
while(TRUE) {
	$container->pushBack(1, 'metadata');
	$container->insert(0, 'test123');
	$container->pushFront($value=12334567);
	$vlr = $container->get(0, True);
	break;
}
$man->DispatchAllEvents();

?>

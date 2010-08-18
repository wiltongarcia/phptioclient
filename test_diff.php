<?php 
/*
 def DiffTest_Map():
 tio = Connect('tio://127.0.0.1:6666')
 vm = tio.CreateContainer('vm', 'volatile_map')
 diff = vm.diff_start()
 for x in range(20) : vm[str(x)] = x*x
 print vm.diff_query(diff)
 for x in range(10) : vm[str(x)] = x*x
 vm.clear()
 print vm.diff_query(diff)
 */
require "TioClient.php";

function diffTestMap() {
    $tioclient = new TioClient();
    $man = $tioclient->connect("tio://localhost:6666");
    $man->log_sends = TRUE;
    $container = $man->createContainer('map1', 'volatile_map');
	$diff = $container->diffStart();
	for($i=1;$i<=20;$i++)
		$container->set((string)$i, $i*$i);
	echo $container->diffQuery($diff);
	for($i=1;$i<=20;$i++)
		$container->set((string)$i, $i*$i);
	$container->clear();
	echo $container->diffQuery($diff);
}

/*
 def DiffTest_List():
 tio = Connect('tio://127.0.0.1:6666')
 vl = tio.CreateContainer('vl', 'volatile_list')
 diff = vl.diff_start()
 vl.extend(range(100))
 print vl.diff_query(diff)
 vl.extend(range(10))
 vl.clear()
 print vl.diff_query(diff)
 */


/*
 DiffTest_List()
 DiffTest_Map()*/
 
 diffTestMap();
?>

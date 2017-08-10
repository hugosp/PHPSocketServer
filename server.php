<?php 
	require_once(__DIR__ . '/server/Server.php');

	$socketServer = new SocketServer();

	$socketServer->run();
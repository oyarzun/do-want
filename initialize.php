<?php

	include_once 'config.php';
	require_once 'db.class.php';

	$init = new db;
	
	$init->dbhost = $dbhost;
	$init->dbname = $dbname;
	$init->dbuser = $dbuser;
	$init->dbpass = $dbpass;
	$init->dbcharSet = $dbcharSet;
	
	$init->dbConnect();
		
	$ops = $init->dbQuery("select * from options");
	
	$init->options = $init->dbAssoc($ops);
	$_SESSION['options'] = $init->options;

?>

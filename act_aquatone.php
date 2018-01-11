#!/usr/bin/php
<?php

/**
 * Use Wfuzz to find Amazon S3 buckets
 * https://github.com/xmendez/wfuzz
 * 
 */

function usage( $err=null ) {
  echo 'Usage: '.$_SERVER['argv'][0]." <domain_id>\n";
  if( $err ) {
    echo 'Error: '.$err."!\n";
  }
  exit(-1);
}

if( $_SERVER['argc'] != 2 ) {
  usage( 'Domain not found' );
}


{ // init
	define( 'ACTARUS_PATH', '/var/www/html/actarus' );
	define( 'OUTPUT_DIR', '/opt/aquatone/domains/' );
	
	require_once( ACTARUS_PATH.'/vendor/actarus/Config.php' );
	require_once( dirname(__FILE__).'/Utils.php' );
	
	
	// config
	$config = Config::getInstance();
	$config->actarusPath = ACTARUS_PATH;
	$config->appPath     = $config->actarusPath.'/app';
	$config->consolePath = $config->actarusPath.'/app/console';
	$config->configPath  = $config->appPath.'/config';
	$config->logPath     = $config->appPath.'/logs';
	$config->loadParameters( $config->configPath.'/parameters.yml', 'parameters' );
	$config->loadParameters( $config->configPath.'/act_parameters.yml', 'parameters' );
	//var_dump( $config );
	//exit();

	$db = $config->db = mysqli_connect( $config->parameters['database_host'], $config->parameters['database_user'], $config->parameters['database_password'], $config->parameters['database_name'] );
} //


{ // get domain
	$domain_id = (int)$_SERVER['argv'][1];
	$q = "SELECT * FROM arus_domain WHERE id='".$domain_id."'";
	//echo $q."\n";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get domain)' );
	}
	if( !$r->num_rows ) {
		usage( '"'.$domain_id.'" domain not found' );
	}
	
	$domain = $r->fetch_object();
	echo $domain->name." loaded\n";
	$output_dir = OUTPUT_DIR.$domain->name;
	//var_dump( $output_dir );
	if( !is_dir($output_dir) && !mkdir($output_dir,true) ) {
		usage( 'Cannot create output directory '.$output_dir );
	}
} //


{ // get host
	$q = "SELECT * FROM arus_host WHERE domain_id='".$domain_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get hosts)' );
	}
	if( !$r->num_rows ) {
		usage( '"'.$domain_id.'" host not found' );
	}
	echo $r->num_rows." hosts found\n";
	
	$t_host = [];
	while ($h=$r->fetch_object() ) {
		$t_host[ $h->name ] = '';
	}
	
	//var_dump( $t_host );
	$output_file = $output_dir.'/hosts.json';
	echo "Wrting data in ".$output_file."\n\n";
	file_put_contents( $output_file, json_encode($t_host) );
} //


{ // launch aquatone
	$cmd = "aquatone-takeover --domain ".$domain->name;
	echo $cmd."\n";
	Utils::_exec( $cmd );
}


{ // the end
	$db->close();
} //


exit( 0 );

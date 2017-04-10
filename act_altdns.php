#!/usr/bin/php
<?php

/**
 * Overlay for AltDNS
 * https://github.com/infosec-au/altdns
 * 
 */

function usage( $err=null ) {
  echo 'Usage: '.$_SERVER['argv'][0]." <domain_id> <wordlist>\n";
  if( $err ) {
    echo 'Error: '.$err."!\n";
  }
  exit(-1);
}

if( $_SERVER['argc'] != 3 ) {
  usage();
}


{ // init
	define( 'ACTARUS_PATH', '/var/www/html/actarus.10degres.net' );
	define( 'WILDCARD_ALERT', 100 );
	
	require_once( ACTARUS_PATH.'/vendor/actarus/custom/Config.php' );
	require_once( dirname(__FILE__).'/Utils.php' );
	
	
	// config
	$config = Config::getInstance();
	$config->actarusPath = ACTARUS_PATH;
	$config->appPath     = $config->actarusPath.'/app';
	$config->consolePath = $config->actarusPath.'/app/console';
	$config->configPath  = $config->appPath.'/config';
	$config->logPath     = $config->appPath.'/logs';
	$config->loadParameters( $config->configPath.'/parameters.yml', 'parameters' );
	$config->loadParameters( $config->configPath.'/myparameters.yml', 'parameters' );
	//var_dump( $config );
	//exit();
	
	$domain_id = (int)$_SERVER['argv'][1];
	$wordlist = trim( $_SERVER['argv'][2] );
	if ( !is_file($wordlist) ) {
		usage( '"'.$wordlist.'" file not found' );
	}
	
	$db = $config->db = mysqli_connect( $config->parameters['database_host'], $config->parameters['database_user'], $config->parameters['database_password'], $config->parameters['database_name'] );
} //


{ // check domain
	$q = "SELECT * FROM arus_domain AS d WHERE d.id='".$domain_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		exit( -1 );
	}
	if( !$r->num_rows ) {
		usage( '"'.$domain_id.'" domain not found' );
	}
	
	$domain = $r->fetch_object();
} //


{ // get known subdomains
	$q = "SELECT * FROM arus_host AS h WHERE h.domain_id='".$domain_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		exit( -1 );
	}
	
	$host_file = tempnam( '/tmp', 'act_' );
	$combin_file = tempnam( '/tmp', 'act_' );
	$final_file = tempnam( '/tmp', 'act_' );
	
	while( ($host=$r->fetch_object()) ) {
		file_put_contents( $host_file, $host->name, FILE_APPEND );
	}
} //


{ // call altdns and parse output
	$t_result = [];
	$cmd = '/opt/bin/altdns -i '.$host_file.' -o '.$combin_file.' -w '.$wordlist.' -r -s '.$final_file;
	echo $cmd."\n";
	exec( $cmd, $output ); 
	//var_dump( $output );
	
	foreach( $output as $r ) {
		if( !stristr($r,$domain->name) ) {
			continue;
		}
		$m = preg_match( '#(.*)\s*:\s*(.*)#', $r, $matches );
		if( $m ) {
			list( $h, $ip ) = explode( ':', $r );
			$t_result[ trim($h) ] = trim( $ip );
		}
	}
	//var_dump( $t_result );
} //


{ // display final result
	$t_host = array_unique( array_keys( $t_result ) );
	$cnt_host = count( $t_host );
	$t_ip = array_values( $t_result );
	$t_ip_uniq = array_unique( $t_result );
	$cnt_ip = count( $t_ip );
	$cnt_ip_uniq = count( $t_ip_uniq );
	//var_dump( $cnt_ip );
	//var_dump( $cnt_ip_uniq );
	echo "\n";
	echo $cnt_host." unique host found.\n";
	echo $cnt_ip_uniq." unique ip found.\n\n";
	
	$percent_uniq = 100 / $cnt_ip_uniq; // jajaja
	//var_dump( $percent_uniq );
	
	if( $percent_uniq >= WILDCARD_ALERT ) {
		usage( 'wildcard detected' );
	}
	
	foreach( $t_host as $h ) {
		echo Utils::cleanOutput($h)."\n";
	}
} //


{ // the end
	$db->close();
	
	@unlink( $host_file );
	@unlink( $combin_file );
	@unlink( $final_file );
} //


exit( 0 );

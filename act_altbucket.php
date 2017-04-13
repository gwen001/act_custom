#!/usr/bin/php
<?php

/**
 * Overlay for AltDNS
 * https://github.com/infosec-au/altdns
 * 
 */

function usage( $err=null ) {
  echo 'Usage: '.$_SERVER['argv'][0]." <project_id> <wordlist>\n";
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
	
	$project_id = (int)$_SERVER['argv'][1];
	$wordlist = trim( $_SERVER['argv'][2] );
	if ( !is_file($wordlist) ) {
		usage( '"'.$wordlist.'" file not found' );
	}

	$db = $config->db = mysqli_connect( $config->parameters['database_host'], $config->parameters['database_user'], $config->parameters['database_password'], $config->parameters['database_name'] );
	
	define( 'SEPARATOR_KEYWORD', '__SEP__' );
	$t_todo = [];
	$t_separator = [ '.', '-' ];
} //


{ // check domain
	$q = "SELECT * FROM arus_project AS p WHERE p.id='".$project_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get project)' );
	}
	if( !$r->num_rows ) {
		usage( '"'.$project_id.'" project not found' );
	}
	
	$project = $r->fetch_object();
} //


{ // get known buckets
	$q = "SELECT * FROM arus_bucket AS b WHERE b.project_id='".$project_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get buckets)' );
	}
	
	$bucket_file = tempnam( '/tmp', 'act_' );
	$combin_file = tempnam( '/tmp', 'act_' );
	$final_file = tempnam( '/tmp', 'act_' );
	//var_dump($bucket_file);
	//var_dump($combin_file);
	//var_dump($final_file);
	
	while( ($bucket=$r->fetch_object()) ) {
		$t_todo[] = preg_replace( '#[^0-9a-zA-Z]#i', SEPARATOR_KEYWORD, $bucket->name );
	}
} //

/*
{ // get known subdomains, finally we don't want to apply variation (altdns) to subdomains
	$q = "SELECT * FROM arus_host AS h WHERE h.project_id='".$project_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get hosts)' );
	}
	
	while( ($host=$r->fetch_object()) ) {
		$t_todo[] = preg_replace( '#[^0-9a-zA-Z]#i', SEPARATOR_KEYWORD, $host->name );
		//file_put_contents( $bucket_file, str_replace('-','.',$bucket->name)."\n", FILE_APPEND );
	}
} //
*/

{ // alter bucket and create variations
	$t_all = [];
	foreach( $t_todo as $w ) {
		foreach( $t_separator as $s ) {
			$t_all[] = str_replace( SEPARATOR_KEYWORD, $s, $w );
		}
	}
	file_put_contents( $bucket_file, implode("\n",$t_all), FILE_APPEND );

	$cmd = '/opt/bin/altdns -i '.$bucket_file.' -o '.$combin_file.' -w '.$wordlist;
	//echo $cmd."\n";
	exec( $cmd );

	$t_combin = file( $combin_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	array_walk( $t_combin, function(&$v,$k){ $v=str_replace('.','-',$v);$v=trim($v,'-');} );
} //


{ // get known subdomains, and create some small variations
	$t_todo = [];
	$q = "SELECT * FROM arus_host AS h WHERE h.project_id='".$project_id."'";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get hosts)' );
	}
	
	while( ($host=$r->fetch_object()) ) {
		$h = preg_replace( '#[^0-9a-zA-Z]#i', SEPARATOR_KEYWORD, $host->name );
		foreach( $t_separator as $s ) {
			$t_combin[] = str_replace( SEPARATOR_KEYWORD, $s, $h );
		}
	}
	
	$t_final = array_unique( $t_combin );
	file_put_contents( $final_file, implode("\n",$t_combin), FILE_APPEND );
} //


{ // call the bruteforcer and display output
	$cmd = '/opt/bin/s3-buckets-bruteforce '.$final_file;
	//echo $cmd."\n";
	exec( $cmd, $output );
	
	echo implode( "\n", $output )."\n";
} //


{ // the end
	$db->close();
	
	@unlink( $bucket_file );
	@unlink( $combin_file );
	@unlink( $final_file );
} //


exit( 0 );

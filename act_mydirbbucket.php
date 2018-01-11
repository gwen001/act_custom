#!/usr/bin/php
<?php

/**
 * Use Wfuzz to find Amazon S3 buckets
 * https://github.com/xmendez/wfuzz
 * 
 */

function usage( $err=null ) {
  echo 'Usage: '.$_SERVER['argv'][0]." <wordlist> <term>\n";
  if( $err ) {
    echo 'Error: '.$err."!\n";
  }
  exit(-1);
}

if( $_SERVER['argc'] != 3 ) {
  usage();
}


function createFuzzTerm( $term, $glue, $mode, $add )
{
	if( $mode == 'p' ) {
		return $add.$glue.$term;
	} else {
		return $term.$glue.$add;
	}
}


function testBuckets( $t_buckets )
{
	if( isset($t_buckets) && is_array($t_buckets) && count($t_buckets) )
	{
		$tmpfile = tempnam( '/tmp/', 's3bf-' );
		//var_dump( $tmpfile );
		file_put_contents( $tmpfile, implode("\n",$t_buckets) );
		//echo count($t_buckets)." buckets found, testing permissions...\n\n";
		
		$cmd = 's3-buckets-bruteforcer --detect-region --verbosity 1 --thread 10 --no-color --bucket '.$tmpfile;
		echo "\n".$cmd."\n";
		Utils::_exec( $cmd );
		//exec( $cmd, $output );
		//$output = implode( "\n", $output );
		//echo $output."\n";
		echo "\n\n";

		@unlink( $tmpfile );
	}
}


function run( $wordlist, $term, $glue, $mode )
{
	global $t_buckets;
	
	$tmp = [];
	$t = createFuzzTerm( $term, $glue, $mode, 'FUZZ' );
	
	$cmd = 'myphp-dirb -u https://s3.amazonaws.com/'.$t.' -w '.$wordlist.' -t 50 -i 0,400,404,500,503 -b';
	echo $cmd."\n\n";
	$output = Utils::_exec( $cmd );
	//exec( $cmd, $output );
	//$output = implode( "\n", $output );
	//$output = file_get_contents( '/tmp/gg' );
	//echo $output."\n";
	//echo "\n";
	
	$m = preg_match_all( '#([^\s]+)\s+C=[0-9]+\s+L=[0-9]+#i', $output, $match );
	//var_dump( $match );

	if( $m ) {
		foreach( $match[1] as $m ) {
			$b = createFuzzTerm( $term, $glue, $mode, $m );
			$t_buckets[] = $b;
			$tmp[] = $b;
		}

		testBuckets( $tmp );
		
		//echo "\n";
		foreach( $tmp as $b ) {
			run( $wordlist, $b, $glue, $mode );
		}
	} else {
		echo "Nothing found!\n\n";
	}
}


{ // init
	require_once( dirname(__FILE__).'/Utils.php' );
	
	$term = $_SERVER['argv'][2];
	$wordlist = trim( $_SERVER['argv'][1] );
	if ( !is_file($wordlist) ) {
		usage( '"'.$wordlist.'" file not found' );
	}
} //


{ // find buckets
	$t_mode = ['s','p'];
	//$t_mode = ['s'];
	$t_glue = ['-','.','_'];
	//$t_glue = ['-'];
	$t_buckets = [];
	
	foreach( $t_mode as $mode ) {
		foreach( $t_glue as $glue ) {
			run( $wordlist, $term, $glue, $mode );
		}
	}
	
	$t_buckets = array_unique( $t_buckets );
	sort( $t_buckets );
	//var_dump( $t_buckets );
}


{ // test buckets
	//testBuckets( $t_buckets );
}


exit( 0 );

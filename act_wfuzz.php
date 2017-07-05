#!/usr/bin/php
<?php

/**
 * Overlay for WFuzz
 * https://github.com/xmendez/wfuzz
 * 
 * wfuzz -c -z file,/opt/SecLists/Discovery/Web_Content/RobotsDisallowed/Top10000-RobotsDisallowed.txt --hc 404,503,400,500,403 https://techops.dotloop.com/FUZZ
 * 
 */

function buildCommand( $url, $wordlist, $t_code_exclude, $t_length_exclude, $t_word_exclude, $t_char_exclude )
{

	$cmd = 'wfuzz -o gcustom -t 5 -z file,'.$wordlist;
	
	if( count($t_code_exclude) ) {
		$cmd .= ' --hc '.implode( ',', $t_code_exclude );
	}
	if( count($t_length_exclude) ) {
		$cmd .= ' --hl '.implode( ',', $t_length_exclude );
	}
	if( count($t_word_exclude) ) {
		$cmd .= ' --hw '.implode( ',', $t_word_exclude );
	}
	if( count($t_char_exclude) ) {
		$cmd .= ' --hh '.implode( ',', $t_char_exclude );
	}
	
	$cmd .= ' '.$url.'/FUZZ';

	return $cmd;
}


function cleanOutput( $str )
{
	$str = str_replace( '[0K', '', $str );
	$str = str_replace( '[0;0m', '', $str );
	$str = str_replace( '[0;0m', '', $str );
	$str = str_replace( '[0;0m', '', $str );
	$str = str_replace( '\r', '', $str );
	
	return $str;
}


function usage( $err=null ) {
	echo 'Usage: '.$_SERVER['argv'][0]." <url> <wordlist>\n";
	if( $err ) {
		echo 'Error: '.$err."!\n";
	}
	exit(-1);
}


if( $_SERVER['argc'] != 3 ) {
	usage();
}


{ // init
	define( 'ACTARUS_PATH', '/var/www/html/actarus' );
	define( 'TEST_LIST', '/opt/SecLists/mine/test.txt' );
	define( 'EXCLUDE_RATIO', 3 ); // minimum percent (of the total lines of the TEST_LIST) for a code to be excluded

	require_once( dirname(__FILE__).'/Utils.php' );
		
	$t_code_exclude = [400,404,500,503,505];
	$t_length_exclude = [];
	$t_word_exclude = [];
	$t_char_exclude = [];
} //


{ // init
	$url = trim( $_SERVER['argv'][1], ' /' );
	$reallist = trim( $_SERVER['argv'][2] );
	$code_exclude = implode( ',', $t_code_exclude );
	$n_test = (int)exec( 'wc -l '.TEST_LIST );
	//var_dump( $n_test );
	$n_exclude = EXCLUDE_RATIO * $n_test / 100;
	//var_dump( $n_exclude );
}


{ // get exclude codes
	echo "perform test:\n";
	$cmd = buildCommand( $url, TEST_LIST, $t_code_exclude, $t_length_exclude, $t_word_exclude, $t_char_exclude );
	echo $cmd."\n\n";
	exec( $cmd, $output );
	//$output = file( '/tmp/gt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$output = implode( "\n", $output );
	$output = cleanOutput( $output );
	//var_dump( $output );
	//exit();
	
	$m = preg_match_all( '#([0-9]+)\:\s+C=([0-9]+)\s+([0-9]+)\s+L\s+([0-9]+)\s+W\s+([0-9]+)\s+Ch\s+"(.*)"#', $output, $matches );
	//var_dump( $matches );
	
	if( $m )
	{
		$acv = array_count_values( $matches[2] );
		foreach( $acv as $v=>$n ) {
			if( $n >= $n_exclude ) {
				$t_code_exclude[] = $v;
			}
		}
		/*
		$acv = array_count_values( $matches[3] );
		foreach( $acv as $v=>$n ) {
			if( $n >= $n_exclude ) {
				$t_length_exclude[] = $v;
			}
		}
		
		$acv = array_count_values( $matches[4] );
		foreach( $acv as $v=>$n ) {
			if( $n >= $n_exclude ) {
				$t_word_exclude[] = $v;
			}
		}
		
		$acv = array_count_values( $matches[5] );
		foreach( $acv as $v=>$n ) {
			if( $n >= $n_exclude ) {
				$t_char_exclude[] = $v;
			}
		}
		*/
	}
} //


{ // run the real command
	echo "perform real command:\n";
	$cmd = buildCommand( $url, $reallist, $t_code_exclude, $t_length_exclude, $t_word_exclude, $t_char_exclude );
	echo $cmd."\n\n";
	//exec( $cmd, $output );
	$output = Utils::_exec( $cmd );
	//$output = implode( "\n", $output );
	$output = cleanOutput( $output );
	//echo $output."\n\n";
	echo "\n\n";
}


exit( 0 );

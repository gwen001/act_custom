#!/usr/bin/php
<?php

/**
 * Detect available Cloudfront subdomains
 * 
 */

class testSubdomain
{
	private $t_headers = [];
	private $ssl = true;
	protected $host = '';
	
	protected $http_code = null;
	protected $real_http_code = null;
	protected $real_http_message = null;
	protected $result = null;
	
	private $t_user_agent = [
		'Mozilla/5.0 (X11; Linux x86_64; rv:31.0) Gecko/20100101 Firefox/31.0 Iceweasel/31.7.0',
		'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
		'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A',
		'Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0',
		'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
		'Mozilla/5.0 (X11; Linux 3.5.4-1-ARCH i686; es) KHTML/4.9.1 (like Gecko) Konqueror/4.9',
		'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:52.0) Gecko/20100101 Firefox/52.0',
	];

	public function __construct( $host, $ssl=true )
	{
		$this->ssl = $ssl;
		$this->host = 'http'.($ssl?'s':'').'://'.$host;
	}
	
	private function request()
	{
		$user_agent = $this->t_user_agent[ rand(0,count($this->t_user_agent)-1) ];
		$headers = array_merge( $this->t_headers, ['User-Agent: '.$user_agent] );
	
		$c = curl_init();
		curl_setopt( $c, CURLOPT_URL, $this->host );
		curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $c, CURLOPT_HEADER, true );
		curl_setopt( $c, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $c, CURLOPT_FOLLOWLOCATION, false );
		curl_setopt( $c, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
		$this->result = curl_exec( $c );
		//var_dump( $this->result );
		
		$this->http_code = curl_getinfo( $c, CURLINFO_HTTP_CODE );
		
		return $this->http_code;
	}
	
	public function runTest()
	{
		$this->request();
	
		$m = preg_match( '#HTTP/1\.1 ([0-9]+) (.*)#', $this->result, $match );
		
		if( $m ) {
			$this->real_http_code = (int)$match[1];
			$this->real_http_message = trim( $match[2] );
		} else {
			$this->real_http_code = 0;
			$this->real_http_message = '';
		}
	}
}

class subdomainCloudfront extends testSubdomain
{
	const DOMAIN = 'cloudfront.net';
	
	public function interpretResult()
	{
		$txt = 'Tested '.$this->host.' ('.$this->http_code.') -> ';
		
		if( !$this->http_code )
		{
			$txt .= 'looks dead';
			$color = 'light_grey';
		}
		else
		{
			if( $this->http_code == 404 ) {
				$txt .= 'nothing here';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'Bad request.') ) {
				$txt .= 'try subdomain takeover';
				$color = 'light_cyan';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'AccessDenied') ) {
				$txt .= 'access denied';
				$color = 'white';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'AllAccessDisabled') ) {
				$txt .= 'access denied';
				$color = 'white';
			}
			elseif( $this->http_code == 403 && strstr($this->result,"<p>You don't have permission to access") ) {
				$txt .= 'server error (cloundfront)';
				$color = 'white';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'<center><h1>403 Forbidden</h1></center>') ) {
				$txt .= 'server error (extern - nginx)';
				$color = 'white';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'<title>403 - Forbidden: Access is denied.</title>') ) {
				$txt .= 'server error (extern - iis)';
				$color = 'white';
			}
			elseif( $this->http_code == 403 && strstr($this->result,'o8888oo  ooo  oooo  ooo. .oo.  .oo.   888oooo.   888  oooo d8b') ) {
				$txt .= 'tumblr';
				$color = 'blue';
			}
			elseif( $this->http_code == 502 && strstr($this->result,"CloudFront wasn't able to connect to the origin.") ) {
				$txt .= ' origin dead';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 502 && strstr($this->result,"CloudFront attempted to establish a connection with the origin, but either the attempt failed or the origin closed the connection.") ) {
				$txt .= ' origin dead';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 503 && strstr($this->result,"Failed to contact the origin.") ) {
				$txt .= ' origin dead';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 504 && strstr($this->result,"CloudFront attempted to establish a connection with the origin, but either the attempt failed or the origin closed the connection.") ) {
				$txt .= ' origin dead';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 200 && strstr($this->result,'ListBucketResult') ) {
				$m = preg_match( '#<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Name>(.*)</Name>#i', $this->result, $matches );
				//var_dump( $matches );
				$txt .= 'try bucket takeover -> ';
				if( $m ) {
					$txt .= $matches[1].' ';
				}
				$color = 'green';
			}
			elseif( $this->http_code == 200 ) {
				$txt .= ' looks like there is a website here';
				$color = 'orange';
			}
			elseif( $this->http_code == 302 ) {
				$txt .= ' looks like there is a website here';
				$color = 'orange';
			}
			elseif( $this->http_code == 301 ) {
				$txt .= ' looks like there is a website here';
				$color = 'orange';
			}
			else {
				$txt .= 'dunno';
				$color = 'yellow';
			}
		}
		
		Utils::_println( $txt, $color );
	}
}

class subdomainHeroku extends testSubdomain
{
	const DOMAIN = 'herokuapp.com';
	
	public function interpretResult()
	{
		$txt = 'Tested '.$this->host.' ('.$this->http_code.') -> ';
		
		if( !$this->http_code )
		{
			$txt .= 'looks dead';
			$color = 'light_grey';
		}
		else
		{
			if( $this->http_code == 404 && strstr($this->result,'no-such-app.html') ) {
				$txt .= 'try subdomain takeover';
				$color = 'light_cyan';
			}
			elseif( $this->http_code == 404 ) {
				$txt .= 'nothing here';
				$color = 'light_grey';
			}
			elseif( $this->http_code == 502 ) {
				$txt .= $this->real_http_message;
				$color = 'light_grey';
			}
			elseif( $this->http_code == 503 ) {
				$txt .= $this->real_http_message;
				$color = 'light_grey';
			}
			elseif( $this->http_code == 302 /*&& strstr($this->result,'Found.')*/ ) {
				$txt .= ' looks like there is a website here';
				$color = 'orange';
			}
			elseif( $this->http_code == 200 ) {
				$txt .= ' looks like there is a website here';
				$color = 'orange';
			}
			else {
				$txt .= 'dunno';
				$color = 'yellow';
			}
		}
		
		Utils::_println( $txt, $color );
	}
}


function usage( $err=null ) {
	echo 'Usage: '.$_SERVER['argv'][0]." <cloudfront|heroku> [<single host or file or an entity_id or nothing (Actarus)>] [<nossl>]\n";
	if( $err ) {
		echo 'Error: '.$err."!\n";
	}
	exit(-1);
}

if( $_SERVER['argc'] < 2 || $_SERVER['argc'] > 4 ) {
	usage();
} else {
	$type = strtolower( trim($_SERVER['argv'][1]) );
	$the_class = 'subdomain'.ucfirst($type);
	if( !class_exists($the_class) ) {
		usage();
	}
}

if( $_SERVER['argc'] >= 3 && !stristr($_SERVER['argv'][2],'null') ) {
	$host = $_SERVER['argv'][2];
} else {
	$host = null;
}

if( $_SERVER['argc'] >= 4 ) {
	$ssl = false;
} else {
	$ssl = true;
}


{ // init
	define( 'ACTARUS_PATH', '/var/www/html/actarus' );
	define( 'SEARCH_DOMAIN', 'herokuapp.com' );

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


{ // find concerned host
	$t_host = [];
	
	if( $host && is_file($host) )
	{
		// load from file
		echo "Load host from file...\n\n";
		$tmp = file( $host, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		echo count($tmp)." lines found.\n";
		
		foreach( $tmp as $h ) {
			if( preg_match('#([a-z0-9\-_\.]+\.'.$the_class::DOMAIN.')#', $h) ) {
				$t_host[] = [
					'host_id' => null,
					'host' => null,
					'subd' => $h,
				];
			}
		}
	}
	elseif( $host && preg_match('#([a-z0-9\-_\.]+\.'.$the_class::DOMAIN.')#', $host) )
	{
		// single host
		echo "Load host from command line...\n\n";
		echo "1 host input.\n";

		$t_host[] = [ 
			'host_id' => null, 
			'host' => null, 
			'subd' => $host, 
		];
	}
	else
	{
		// load from Actarus db
		echo "Load host from Actarus...\n\n";
		$q = "SELECT * FROM arus_entity_task AS et WHERE et.command LIKE 'host%' AND et.output LIKE '%is an alias for%' AND et.output LIKE '%".$the_class::DOMAIN."%'";
		if( $host ) {
			$q .= " AND et.entity_id='".$host."'";
		}
		//var_dump( $q );
		$r = $db->query( $q );
		if( !$r ) {
			usage( 'query error (get entity task)' );
		}
		
		$n = $r->num_rows;
		echo $n." tasks found.\n";
		if( !$n ) {
			$db->close();
			exit( 0 );
		}
		
		$t_host = [];
		
		for( $i=0 ; /*$i<50 &&*/ ($task=$r->fetch_object()) ; $i++ )
		{
			$m = preg_match_all( '#(.*) is an alias for ([a-z0-9\-_\.]+\.'.$the_class::DOMAIN.')\.#', $task->output, $matches );
			//var_dump( $task->output );
			//var_dump( $matches );
			
			if( $m ) {
				$t_host[] = [
					'host_id' => $task->entity_id,
					'host' => $matches[1][0],
					'subd' => $matches[2][0],
				];
			}
		}
	}
	
	//var_dump( $t_host );
	echo count($t_host)." hosts to test.\n\n";
} //


{ // perform the tests
	$tested = 0;
	
	foreach( $t_host as $h )
	{
		if( $h['host'] ) {
			echo "Host: ".$h['host']."\n";
		}
		
		$o = new $the_class( $h['subd'], $ssl );
		$o->runTest();
		$o->interpretResult();
		
		$tested++;
	}
} //


{ // the end
	echo "\n".$tested." host tested.\n\n";

	$db->close();
} //


exit( 0 );

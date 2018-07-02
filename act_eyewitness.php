#!/usr/bin/php
<?php

/**
 * Overlay for EyeWitness
 * https://github.com/FortyNorthSecurity/EyeWitness
 * 
 */

function resizeAndCopy( $src, $dst )
{
	$t_infos = getimagesize( $src );
	if( !$t_infos ) {
		return false;
	}
	
	$img_src = imagecreatefrompng( $src );
	if( !$img_src ) {
		return false;
	}
	
	$width = ($t_infos[0] > IMG_MAX_WIDTH) ? IMG_MAX_WIDTH : $t_infos[0];
	$ratio = $t_infos[0] / $width;
	$height = $t_infos[1] / $ratio;
	$true_height = ($height > IMG_MAX_HEIGHT) ? IMG_MAX_HEIGHT : $height;
	
	$img_dst = imagecreatetruecolor( $width, $true_height );
	if( !$img_dst ) {
		return false;
	}
	
	imagesavealpha( $img_dst, true );
	imagealphablending( $img_dst, false );
	$transparent = imagecolorallocatealpha( $img_dst, 0, 0, 0, 127 );
	imagefill( $img_dst, 0, 0, $transparent );
	
	$a = imagecopyresampled( $img_dst, $img_src, 0, 0, 0, 0, $width, $height, $t_infos[0], $t_infos[1] );
	if( !$a ) {
		return false;
	}
	
	$a = imagepng( $img_dst, $dst );
	if( !$a ) {
		return false;
	}
	
	return true;;
}


function clean() {
	global $output_path;
	exec( 'rm -rf '.$output_path );
}


function usage( $err=null ) {
	echo 'Usage: '.$_SERVER['argv'][0]." <entity_id> [<url>]\n";
	if( $err ) {
		echo 'Error: '.$err."!\n";
	}
	exit(-1);
}


if( $_SERVER['argc'] < 2 || $_SERVER['argc'] > 3 ) {
	usage();
}


{ // init
	define( 'ACTARUS_PATH', '/var/www/html/actarus' );
	define( 'OUTPUT_DIR', '/tmp' );
	define( 'SCREENSHOT_EXTENSION', 'png' );
	define( 'HTML_EXTENSION', 'html' );
	define( 'IMG_MAX_WIDTH', 1024 );
	define( 'IMG_MAX_HEIGHT', 768 );
	define( 'HEADLESS_MODE', true );

	require_once( ACTARUS_PATH.'/vendor/actarus/Config.php' );
	require_once( dirname(__FILE__).'/Utils.php' );
	
	// config
	$config = Config::getInstance();
	$config->actarusPath = ACTARUS_PATH;
	$config->appPath     = $config->actarusPath.'/app';
	$config->consolePath = $config->actarusPath.'/app/console';
	$config->configPath  = $config->appPath.'/config';
	$config->webPath     = $config->actarusPath.'/web';
	$config->logPath     = $config->appPath.'/logs';
	$config->loadParameters( $config->configPath.'/parameters.yml', 'parameters' );
	$config->loadParameters( $config->configPath.'/act_parameters.yml', 'parameters' );
	//var_dump( $config );
	//exit();
	
	$entity_id = trim( $_SERVER['argv'][1] );
	
	$db = $config->db = mysqli_connect( $config->parameters['database_host'], $config->parameters['database_user'], $config->parameters['database_password'], $config->parameters['database_name'] );
} //


{ // check host/server
	if( $entity_id[0] == 4 ) {
		$entity_type = 'host';
	} elseif( $entity_id[0] == 2 ) {
		$entity_type = 'server';
	} else {
		usage( 'bad entity id' );
	}
	
	$q = "SELECT * FROM arus_".$entity_type." AS h WHERE h.entity_id='".$entity_id."'";
	//echo $q."\n";
	$r = $db->query( $q );
	if( !$r ) {
		usage( 'query error (get entity)' );
	}
	if( !$r->num_rows ) {
		usage( '"'.$entity_id.'" entity not found' );
	}
	
	$entity = $r->fetch_object();
	
	if( $_SERVER['argc'] == 3 ) {
		$url = trim( $_SERVER['argv'][2] );
	} else {
		$url = 'http://'.$entity->name;
	}
	
	$output_path = OUTPUT_DIR.'/'.uniqid();
	$screenshot_path = $output_path.'/screens';
	
	$attachments_path = $config->webPath.$config->parameters['attachments_path'];
	$destination_name = md5( uniqid('',true) ).'.'.SCREENSHOT_EXTENSION;
	$destination_path = $attachments_path.$entity->project_id.'/';
	if( !is_dir($destination_path) ) {
		mkdir( $destination_path, 0777, true );
		if( !is_dir($destination_path) ) {
			usage( 'destination directory not available ('.$destination_path.')' );
		}
	}
	$destination_path .= $destination_name;
	echo $entity->name." -> ".$url."\n";
	//var_dump( $screenshot_name );
	//var_dump( $screenshot_path );
	//var_dump( $attachments_path );
	//var_dump( $destination_path );
} //


{ // call httpscreenshot and parse output
	clean();
	
	echo "Calling EyeWitness\n";
	$cmd = 'EyeWitness --single '.$url.' --no-prompt -d '.$output_path;
	if( HEADLESS_MODE ) {
		$cmd .= ' --headless';
	}
	var_dump( $cmd );
	@exec( $cmd );
	
	$t_screens = glob( $screenshot_path.'/*.'.SCREENSHOT_EXTENSION );

	if( is_array($t_screens) && count($t_screens) )
	{
		$screenshot = $t_screens[0];
		$screenshot_name = basename( $screenshot );

		if( resizeAndCopy($screenshot,$destination_path) )
		{
			$q = "INSERT INTO arus_entity_attachment (project_id,entity_id,filename,realname,title,created_at,updated_at) VALUES (
							'".$entity->project_id."',
							'".$entity->entity_id."',
							'".$destination_name."',
							'".$screenshot_name."',
							'".$url."',
							NOW(),
							NOW()
					)";
			$r = $db->query( $q );
			//echo $q."\n";
			if( !$r ) {
				clean();
				usage( 'query error (create attachment)' );
			}
			
			echo "Screenshot generated: ".$screenshot_name." -> ".$destination_name."\n";
			echo "Attachment ID: ".$db->insert_id."\n";
		}
		else
		{
			echo "copy error\n";
		}
	}
	else
	{
		echo "screenshot not generated\n";
	}
} //


{ // the end
	$db->close();

	clean();
} //


exit( 0 );

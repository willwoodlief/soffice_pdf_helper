<?php
/**
 * @package soffice_pdf_helper
 * @version 1.0
 */
/*
 *
Plugin Name: SOffice PDF Helper
Description: Requires <b>Open Office</b> Running on Linux with the /tmp directory having no initial folders created by open office (delete any .config and .cache in /tmp when first starting, it uses a home of /tmp when running soffice). This plugin provides a function <b>soffice_pdf_helper_add</b> which other plugins can call to get a pdf made from any kind of text file. This plugin makes sure that only one file at a time can be processed and will add an admin warning if something goes really wrong
Author: Will Woodlief
Version: 1.0
Author URI: mailto:willwoodlief@gmail.com
*/


/**
 * Standard Error Log Wrapper
 * @param $log
 */
function soffice_pdf_helper_error_log($log) {
	if (true === WP_DEBUG) {
		if (is_array($log) || is_object($log)) {
			error_log(print_r($log, true));
		} else {
			error_log($log);
		}
	}
}

/**
 * @param $message
 * @param null $other
 *
 * @throws Exception
 */
function soffice_pdf_helper_action_log( $message, $other = null ) {
	global $module_path;

	$stamp =  date("Y-m-d H:i:s");
	$message = $stamp . ' ' . $message;
	if ( $other !== null ) {
		$message .= ':' . print_r( $other, true );
	}
	$log_file = $module_path . "process_log.txt";

	$fp = fopen( $log_file, "a+" );
	if ( ! $fp ) {
		throw new Exception( "could not open log file [$log_file]" );
	}
	$getLock = flock( $fp, LOCK_EX );

	if ( $getLock ) {  // acquire an exclusive lock

		fwrite( $fp, $message . "\n" );
		fflush( $fp );            // flush output before releasing the lock
		flock( $fp, LOCK_UN );    // release the lock
	} else {
		throw new Exception( "could not get lock for log file" );
	}

	fclose( $fp );

}

/**
 * @param null $prefix
 * @param null $suffix
 * @param null $dir
 *
 * @return string
 * @throws Exception
 */
function soffice_pdf_helper_tempnam($prefix = null, $suffix = null, $dir = null)
{
	if (func_num_args() > 3) {
		throw new Exception(__FUNCTION__.'(): passed '.func_num_args().' args, should pass 0, 1, 2, or 3 args.  Usage: '.__FUNCTION__.'(optional filename prefix, optional filename suffix, optional directory)');
	}

	$prefix = trim($prefix);
	$suffix = trim($suffix);
	$dir = trim($dir);

	empty($dir) and $dir = trim(sys_get_temp_dir());
	empty($dir) and exit(__FUNCTION__.'(): could not get system temp dir');
	is_dir($dir) or exit(__FUNCTION__."(): \"$dir\" is not a directory");

	//    posix valid filename characters. exclude "similar" characters 0, O, 1, l, I to enhance readability. add - _
	$fn_chars = array_flip(array_diff(array_merge(range(50,57), range(65,90), range(97,122), array(95,45)), array(73,79,108)));

	//  create random filename 20 chars long for security
	/** @noinspection PhpStatementHasEmptyBodyInspection */
	for($fn = rtrim($dir, '/') . '/' . $prefix, $loop = 0, $x = 0; $x++ < 20; $fn .= chr(array_rand($fn_chars)));
	while (file_exists($fn.$suffix))
	{
		$fn .= chr(array_rand($fn_chars));
		$loop++ > 10 and exit(__FUNCTION__."(): looped too many times trying to create a unique file name in directory \"$dir\"");
		clearstatcache();
	}

	$fn = $fn.$suffix;
	touch($fn) or exit(__FUNCTION__."(): could not create tmp file \"$fn\"");
	return $fn;
}

/**
 * Makes sure that only one file at a time is converted, will sleep 1 second at a time until its turn
 * uses a file lock, released on finally
 * Will not crash the calling function
 *
 * @param string $file_path <p>
 *    This needs to be absolute path, not partial
 * </p>
 * @return string|false <p>
 *   returns the temp file path of the pdf, or false on failure
 *   will print out exception messages to the admin screen
 *
 *   callee is expected to unlink the returning file
 * </p>
 */
function soffice_pdf_helper_add($file_path) {
	$module_path =  plugin_dir_path(__FILE__);
	$lock_file   = $module_path . 'lock/.lock';
	$copy_file_path = null;
	$max_seconds_allowed = 60 * 2;
	$ret = false;
	try {
		$safety = 0;
		$file_check = is_readable( $lock_file );
		while ( $file_check ) {
			sleep(1);
			$safety ++;
			if ($safety > $max_seconds_allowed) {
				throw new Exception("Waited more than $safety seconds to get the pdf done for $file_path. Timed out");
			}
			$file_check = is_readable( $lock_file );
		}

		//create the lock file, it will be erased in the finally
		$b_ok = file_put_contents( $lock_file, 'if the process dies before finishing naturally, please erase this file' );
		if (!$b_ok ) {
			throw new Exception("Could not create lock file $lock_file, check permissions");
		}

		if (! is_readable($file_path)) {
			throw new Exception("$file_path is not readable");
		}

		//copy file and put on original extension
		$copy_file_path = soffice_pdf_helper_tempnam('soffice_pdf_helper_');
		@copy($file_path,$copy_file_path);


		$output_path = sys_get_temp_dir();

		$command_line = "export HOME=/tmp &&  \
						/usr/bin/soffice                    \
					  --headless                           \
					  --convert-to pdf:writer_pdf_Export   \
					  --outdir $output_path                \
					  $copy_file_path";

		$output = shell_exec("$command_line 2>&1");
		soffice_pdf_helper_action_log("ran open office",['command_line'=>$command_line,'output'=>$output]);
		$info = pathinfo($copy_file_path);
		$ret = $info['filename'] . '.pdf' ;

	}
	catch ( Exception $e ) {

		$message = $e->getFile() . "\n<br>" . $e->getFile() . "\n<br>" . $e->getMessage() . "\n<br>" . implode( "\n<br>", explode( "\n", $e->getTraceAsString() ) );
		soffice_pdf_helper_error_log($message);

	}
	finally {
		if ($copy_file_path) {
			unlink($copy_file_path);
		}
		unlink( $lock_file );
	}
	return $ret;
}



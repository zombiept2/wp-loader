<?php
/**
 * WordPress setup wizard
 */

/**
 * Please copy this file into your webserver root and open it with a browser. The setup wizard checks the dependency, downloads the newest WordPress version, unpacks it and redirects to the WordPress installer.
 */


// init
ob_start();
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
ini_set('display_errors', 1);
@set_time_limit(0);

/**
 * Setup class with a few helper functions
 */
class Setup 
{
	private static $requirements = array(
		array(
			'classes' => array(
				'ZipArchive' => 'zip',
				'DOMDocument' => 'dom',
				'XMLWriter' => 'XMLWriter'
			),
			'functions' => array(
				'xml_parser_create' => 'libxml',
				'mb_detect_encoding' => 'mb multibyte',
				'ctype_digit' => 'ctype',
				'json_encode' => 'JSON',
				'gd_info' => 'GD',
				'gzencode' => 'zlib',
				'iconv' => 'iconv',
				'simplexml_load_string' => 'SimpleXML',
				'hash' => 'HASH Message Digest Framework',
				'curl_init' => 'curl',
			),
			'defined' => array(
				'PDO::ATTR_DRIVER_NAME' => 'PDO'
			),
		)
	);

	/**
	* Checks if all of the dependencies are installed
	* @return string with error messages
	*/
	static public function checkDependencies() 
	{
		$error = '';
		$missingDependencies = array();

		// do we have PHP 5.3.0 or newer?
		if (version_compare(PHP_VERSION, '5.3.0', '<')) 
		{
			$error.='PHP 5.3.0 is required. Please ask your server administrator to update PHP to version 5.3.0 or higher.<br/>';
		}

		foreach (self::$requirements[0]['classes'] as $class => $module) 
		{
			if (!class_exists($class)) 
			{
				$missingDependencies[] = array($module);
			}
		}
		foreach (self::$requirements[0]['functions'] as $function => $module) 
		{
			if (!function_exists($function)) 
			{
				$missingDependencies[] = array($module);
			}
		}
		foreach (self::$requirements[0]['defined'] as $defined => $module) 
		{
			if (!defined($defined)) 
			{
				$missingDependencies[] = array($module);
			}
		}

		if (!empty($missingDependencies)) {
			$error .= 'The following PHP modules are required to use WordPress:<br/>';
		}
		foreach ($missingDependencies as $missingDependency) 
		{
			$error .= '<li>'.$missingDependency[0].'</li>';
		}
		if (!empty($missingDependencies)) 
		{
			$error .= '</ul><p style="text-align:center">Please contact your server administrator to install the missing modules.</p>';
		}

		// do we have write permission?
		if (!is_writable('.')) 
		{
			$error.='Can\'t write to the current directory. Please fix this by giving the web server user write access to the directory.<br/>';
		}

		return($error);
	}

	/**
	* Check the cURL version
	* @return bool status of CURLOPT_CERTINFO implementation
	*/
	static public function isCertInfoAvailable() 
	{
		$curlDetails =  curl_version();
		return version_compare($curlDetails['version'], '7.19.1') != -1;
	}

	/**
	* Performs the WordPress install.
	* @return string with error messages
	*/
	static public function install() 
	{
		$error = '';

		$dev = false;
		if (isset($_REQUEST['dev']))
		{
			$dev = true;
		}

		// Test if folder already exists
		if (file_exists('./wp-config.php')) 
		{
			return 'There seems to already be an WordPress installation. - You cannot use this script to update existing installation.';
		}

		// downloading latest release
		if (!file_exists('latest.zip')) 
		{
			if ($dev)
			{
				$error .= Setup::getFile('https://wordpress.org/latest.zip','latest.zip');
			}
			else
			{
				$error .= Setup::getFile('https://wordpress.org/latest.zip','latest.zip');
			}
		}

		// unpacking into WordPress folder
		$zip = new ZipArchive;
		$res = $zip->open('latest.zip');
		if ($res == true) 
		{
			// Extract it
			$zip->extractTo(dirname(__FILE__));
			$zip->close();
            Setup::recursiveCopy(dirname(__FILE__) . '/wordpress', dirname(__FILE__));
            Setup::recursiveRemoveDirectory(dirname(__FILE__) . '/wordpress');
		} 
		else 
		{
			$error .= 'unzip of WordPress source file failed.<br />';
		}

		// deleting zip file
		$result = @unlink('latest.zip');
		if ($result == false) 
		{
			$error .= 'deleting of latest.zip failed.<br />';
		}
		return($error);
	}
    
    /**
	* Recursively copies a file or directory
	* @param string $src
	* @param string $dst
	*/
    static public function recursiveCopy($src,$dst) 
    { 
        $dir = opendir($src); 
        @mkdir($dst); 
        while(false !== ( $file = readdir($dir)) ) 
        { 
            if (( $file != '.' ) && ( $file != '..' )) 
            { 
                if ( is_dir($src . '/' . $file) ) 
                { 
                    Setup::recursiveCopy($src . '/' . $file,$dst . '/' . $file); 
                } 
                else 
                { 
                    copy($src . '/' . $file,$dst . '/' . $file); 
                } 
            } 
        } 
        closedir($dir); 
    } 
    
    /**
	* Recursively removes a directory
	* @param string $dir
	*/
    static public function recursiveRemoveDirectory($dir) 
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) 
        {
            (is_dir("$dir/$file")) ? Setup::recursiveRemoveDirectory("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

	/**
	* Downloads a file and stores it in the local filesystem
	* @param string $url
	* @param string$path
	* @return string with error messages
	*/
	static public function getFile($url,$path) 
	{
		$error='';
		$fp = fopen ($path, 'w+');
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		if (Setup::isCertInfoAvailable())
		{
			curl_setopt($ch, CURLOPT_CERTINFO, TRUE);
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		$data=curl_exec($ch);
		$curlError=curl_error($ch);
		curl_close($ch);
		fclose($fp);

		if ($data == false)
		{
			$error .= 'download of WordPress source file failed.<br />'.$curlError;
		}
		return($error.$curlError);
	}

	/**
	* Shows the html header of the setup page
	*/
	static public function showHeader() 
	{
		echo('
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<meta http-equiv="X-UA-Compatible" content="IE=edge">
				<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0"/>
				<link rel="icon" href="https://s.w.org/about/images/logos/wordpress-logo-simplified-rgb.png">
				<title>WordPress Loader</title>
				<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
				<script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
				<style type="text/css">
				html {
					position: relative;
					min-height: 100%;
					background-color: #393939;
				}
				body {
					background-color: #393939;
					color: #3d3d3d;
					min-height: 100%;
					margin-top: 0;
					margin-bottom: 0;
				}
				.container {
					max-width: 600px;
					margin-top: 44px;
				}
				.logo {
					width: 100%;
					max-width: 100px;
				}
				</style>
			</head>
			<body>
				<div class="container">
					<div class="card text-center">
						<div class="card-header">
							<a href="https://wordpress.org" target="_blank">
								<img src="https://s.w.org/about/images/logos/wordpress-logo-notext-rgb.png" alt="WordPress" class="logo" />
							</a>
							<h1>WordPress Loader</h1>
						</div>
						<div class="card-body">
		');
	}

	/**
	* Shows the html footer of the setup page
	*/
	static public function showFooter() 
	{
		echo('
		</div>
		</div>
		</body>
		</html>
		');
	}

	/**
	* Shows the html content part of the setup page
	* @param string $title
	* @param string $content
	* @param string $nextpage
	*/
	static public function showContent($title, $content, $nextpage='')
	{
		$dev = false;
		if (isset($_REQUEST['dev']))
		{
			$dev = true;
		}
		echo('
			<p style="text-align:left; font-size:28px; color:#444; font-weight:bold;">'.$title.'</p>
			<div style="text-align:left; font-size:16px; color:#666; font-weight:normal; ">'.$content.'</div>
			</div>
			<div class="card-footer">
			<form method="get">
				<input type="hidden" name="dev" value="'.$dev.'" />
				<input type="hidden" name="step" value="'.$nextpage.'" />
		');

		if ($nextpage<>'') 
		{
			echo('
				<div class="row centered">
	      			<div class="col-md-12">
		  				<input type="submit" id="submit" class="btn btn-success" value="Next" />
		  			</div>
		  		</div>
			');
		}

		echo('
		</form>
		</div>
		</div>

		');
	}

	/**
	* Shows the welcome screen of the setup wizard
	*/
	static public function showWelcome() 
	{
		$txt = '<p>Welcome to the WordPress Loader Wizard.</p><p>This wizard will check the WordPress dependencies, download the latest version of WordPress and redirect to the installer in a few simple steps.</p>';
		Setup::showContent('Setup Wizard',$txt,1);
	}

	/**
	* Shows the check dependencies screen
	*/
	static public function showCheckDependencies() 
	{
		$error=Setup::checkDependencies();
		if ($error=='') 
		{
			$txt='<p>All WordPress dependencies found</p>';
			Setup::showContent('Dependency Check',$txt,2);
		}
		else
		{
			$txt='<p>Dependencies not found.<br />'.$error.'</p>';
			Setup::showContent('Dependency Check',$txt);
		}
	}

	/**
	* Shows the install screen
	*/
	static public function showInstall() 
	{
		$error=Setup::install();

		if ($error == '') 
		{
			$txt='<p>WordPress is now loaded</p>';
			Setup::showContent('Success',$txt,3);
		}
		else
		{
			$txt='<p>WordPress is NOT loaded<br />'.$error.'</p>';
			Setup::showContent('Error',$txt);
		}
	}

	/**
	* Shows the redirect screen
	*/
	static public function showRedirect() 
	{
		// delete own file
		@unlink($_SERVER['SCRIPT_FILENAME']);

		// redirect to WordPress
		$protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
		$link = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		$link = str_replace("wp-loader.php", "", $link);
		header("Location: ".$link);
	}

}


// read the step get variable
if (isset($_GET['step'])) 
{
	$step=$_GET['step']; 
}
else 
{
	$step=0;
}

// show the header
Setup::showHeader();

// show the right step
if     ($step==0) Setup::showWelcome();
elseif ($step==1) Setup::showCheckDependencies();
elseif ($step==2) Setup::showInstall();
elseif ($step==3) Setup::showRedirect();
else  echo('Internal error. Please try again.');

// show the footer
Setup::showFooter();

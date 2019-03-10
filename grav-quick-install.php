<?php
/**
 * @package    Quick Install Grav
 *
 * @copyright  Copyright (C) 2019 CaDJoU <cadjou@gmail.com>.
 * @license    MIT License; see LICENSE file for details.
 * @version    v1.0
 * @date       10/03/2019.
 */
 
/** Check PHP Version */
if (version_compare(PHP_VERSION, '5.6.4') === -1) {
	http_response_code(500);
	echo '<!doctype html>
			<html lang="en">
				<head>
					<meta charset="utf-8">
					<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
					<meta name="description" content="Grav Installation">
					<meta name="author" content="CaDJoU contact@cadjou.net">
					<meta name="generator" content="Jekyll v3.8.5">
					<title>Install Grav</title>
				</head>
				<body>
					<p>
						This version of Nextcloud requires at least PHP 5.6.0<br/>
						You are currently running ' . PHP_VERSION . '. Please update your PHP version.
					</p>
				</body>
			</html>';
	exit(-1);
}

/** Rerror Management */
ob_start();
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
ini_set('display_errors', 1);
@set_time_limit(0);


class cadInstallGrav
{
	private static $urlPackage	= 'https://getgrav.org/download/core/grav-admin/1.5.8';
	
	/** @var urlGravBase */
	private static $urlGravBase	= 'https://getgrav.org/downloads';
	
	/** @var urlGravSkeleton */
	private static $urlGravSkeleton = 'https://getgrav.org/downloads/skeletons';
	
	/** @var messages */
	private static $messages	= [];
	
	/** @var tableForm */
	private static $tableForm = [
		[
			'name'			=> 'package',
			'placeholder'	=> 'Click on Choose Me in the selection',
			'label'			=> 'Package',
			'type'			=> 'text',
			'parameters'	=> 'required',
		],[ 'name'			=> 'directory',
			'placeholder'	=> 'Empty or . or my/folder/root',
			'label'			=> 'Directory',
			'type'			=> 'text',
		],[ 'name'			=> 'user',
			'placeholder'	=> 'admin_grav',
			'label'			=> 'User Admin',
			'type'			=> 'text',
			'value'			=> 'admin_grav',
			'parameters'	=> 'required',
		],[ 'name'			=> 'email',
			'placeholder'	=> 'Email address',
			'label'			=> 'Email',
			'type'			=> 'email',
			'parameters'	=> 'required',
		],[ 'name'			=> 'fullname',
			'placeholder'	=> 'Mr Grav',
			'label'			=> 'Full Name',
			'type'			=> 'text',
			'parameters'	=> 'required',
		],[ 'name'			=> 'title',
			'placeholder'	=> 'Adminstrator',
			'label'			=> 'Title',
			'type'			=> 'text',
			'parameters'	=> 'required',
		],[ 'name'			=> 'remove',
			'label'			=> 'Remove the install script',
			'type'			=> 'checkbox',
			'parameters'	=> 'checked',
		],
	];
	
	/** @var modelUser */
	private static $modelUser = 'email: %email%
fullname: %fullname%
title: %title%
state: enabled
access:
  admin:
    login: true
    super: true
  site:
    login: true
hashed_password: %hashed_password%';
	
	/** @var modelForm */
	private static $modelForm = '
		<form class="form-signin" method="GET">
			<h4>Please enter the informations needed</h1>
				%data%
			<button class="btn btn-lg btn-primary btn-block" type="submit">Install</button>
		</form>';
	
	/** @var modelInput */
	private static $modelInput = '<div class="form-group row">
				<label for="name" class="col-sm-2 col-form-label">%label%</label>
				<div class="col-sm-10">
					<input type="%type%" id="%name%" name="%name%" class="form-control" placeholder="%placeholder%" value="%get%" %parameters%>
				</div>
			</div>';
	
	/** @var modelMessages */
	private static $modelMessages = '<div class="card">
				<h5 class="card-header">
					Messages
				</h5>
				<div class="card-body">
					%data%
				</div>
			</div><br><hr><br>';
			
	/** @var modelMessage */
	private static $modelMessage = '<div class="alert alert-%type%" role="alert">%message%</div>';
	
	static public function getFile($url,$path)
	{
		$context = [];
		$context['https']['method']  = 'GET';
		$context['https']['header']  = $_SERVER['HTTP_USER_AGENT'] . "\r\n";
		$retour	= file_get_contents($url, false, stream_context_create($context));
		if (!$retour)
		{
			self::$messages[] = '!Error to Download the package ' . $url;
			return false;
		}
		return file_put_contents($path, $retour);
	}
	
	static public function install($directory = '.', $package = "", $infoAdmin = [], $removeScript= true)
	{
		$directory = empty($directory) ? '.' : $directory ;
		
		if(file_exists('./'.$directory.'/system/defines.php'))
		{
			self::$messages[] = '!The selected folder seems to already contain a Grav installation. - You cannot use this script in this case';
			return false;
		}
		
		if(!is_dir('./' . $directory) and $directory <> '.' and $directory <> '')
		{
			if (!mkdir('./'.$directory,'0755',true))
			{
				self::$messages[] = '!The directory <b>' . $directory . '</b> is not good';
				return false;
			}
		}
		
		if (!file_exists('grav.zip'))
		{
			$package = !empty($package) ? $package : self::$urlPackage;
			if (!cadInstallGrav::getFile($package ,'grav.zip'))
			{
				self::$messages[] = '!The Package <b>' . $package . '</b> is not good';
				return false;
			}
		}
		
		$zip = new ZipArchive;
		$res = $zip->open('grav.zip');
		
		if (!$res)
		{
			self::$messages[] = '!Unzip of Grav source file failed from ' . self::$urlPackage;
			return false;
		}

		$grav_tmp_dir = 'tmp-grav'.time();
		$zip->extractTo($grav_tmp_dir);
		$zip->close();
		
		$tableFolderFlip = array_flip(scandir($grav_tmp_dir));
		unset($tableFolderFlip['.']);
		unset($tableFolderFlip['..']);
		list($folder,) = array_values(array_flip($tableFolderFlip));
		
		if ($directory === '.')
		{
			foreach (array_diff(scandir($grav_tmp_dir.'/' . $folder), array('..', '.')) as $item)
			{
				rename($grav_tmp_dir.'/' . $folder . '/'.$item, './'.$item);
			}
			rmdir($grav_tmp_dir.'/' . $folder);
		}
		else
		{
			rename($grav_tmp_dir . '/' . $folder, './' . $directory);
		}
		
		if ($infoAdmin)
		{
			$pass = cadInstallGrav::createAdmin($infoAdmin, './' . $directory . '/user/accounts');
			$user = isset($infoAdmin['user']) ? $infoAdmin['user'] : '';
			
			$validText		= '&The user <b>' . $user . '</b> is created with the password <b>' . $pass . '</b><br>Be carefull to save this password. It\'s the only time you can have it !<br><script>
		window.onbeforeunload = function(){return "Are you sure to close this page? Do you save your Admin Password ?";};</script>';
			$invalidText	= '!The user is not created.<br>';
			
			self::$messages[] = $pass ? $validText : $invalidText;
		}
		
		rmdir($grav_tmp_dir);
		@unlink('grav.zip');
		@unlink('index.html');
		if ($removeScript)
		{
			unlink(__FILE__);
		}
		self::$messages[] = '&The installation is finished with succes. Congratulations !!<br>';
		self::$messages[] = '&<h5>You can have acces to your Grav website <a target="_blank" href="./' . $directory . '/">here</a> or the admin zone <a target="_blank" href="./' . $directory . '/admin/">here</a></h5>';
		return true;
	}
	
	static public function checkGet($get)
	{
		if(is_string($get))
		{
			return strtolower($get);
		}
		return null;
    }
	
	static public function createAdmin($infoAdmin,$pathAccounts)
	{
		$infoNeeded = 'email fullname title';
		$valid		= !empty($infoAdmin['user']);
		$infoCreate = [];
		foreach(explode(' ',$infoNeeded) as $item)
		{
			$valid &= !empty($infoAdmin[$item]);
			$infoCreate[$item] = $infoAdmin[$item];
		}
		
		if (!$valid)
		{
			self::$messages[] = '!Error to create the Admin User<br>';
			return false;
		}
		
		$password			= cadInstallGrav::passGenerator();
		$hashed_password	= password_hash($password, PASSWORD_DEFAULT);
		$infoCreate['hashed_password'] = $hashed_password;
		
		$infoUser = cadInstallGrav::parseModel(self::$modelUser,$infoCreate);
		
		if (!is_dir($pathAccounts))
		{
			self::$messages[] = '!The path to create the accounts <b>' . $pathAccounts . '</b> is not good.<br>';
			return false;
		}
		
		if (!$infoUser)
		{
			self::$messages[] = '!The User can not be created. Sorry.<br>';
			return false;
		}
		return file_put_contents($pathAccounts . '/' . $infoAdmin['user'] . '.yaml',$infoUser) ? $password : false;
	}
	
	static public function parseModel($model,$data)
	{
		$tabkeKey = $tabkeValue = [];
		foreach($data as $key=>$value)
		{
			$tabkeKey[]		= "%$key%";
			$tabkeValue[]	= $value;
		}
		return str_replace($tabkeKey,$tabkeValue,$model);
	}
	
	static public function passGenerator($carateres = true)
	{
		$liste_lettres    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$liste_chiffres	  = '0123456789';
		$liste_caracteres = '-+!,*';

		$tab_lettres      = str_split($liste_lettres);
		shuffle($tab_lettres);

		$tab_chiffres     = str_split($liste_chiffres);
		shuffle($tab_chiffres);

		$tab_caracteres   = str_split($liste_caracteres);
		shuffle($tab_caracteres);

		$tab_lettres_flip    = array_flip($tab_lettres);
		$tab_chiffres_flip   = array_flip($tab_chiffres);
		$tab_caracteres_flip = array_flip($tab_caracteres);

		$tab_lettre = [];
		for($i=0;$i<=rand(10,11);$i++)
		{
			$tab_lettre[]     = array_rand($tab_lettres_flip);
		}
		for($i=0;$i<=rand(2,4);$i++)
		{
			$tab_lettre[]     = array_rand($tab_chiffres_flip);
		}
		if ($carateres)
		{
			for($i=0;$i<=rand(2,4);$i++)
			{
				$tab_lettre[] = array_rand($tab_caracteres_flip);
			}
		}

		shuffle($tab_lettre);
		return implode('',$tab_lettre);
	}

	static public function getMessages()
	{
		$tableTypeMessages['!'] = 'danger';
		$tableTypeMessages[':'] = 'warning';
		$tableTypeMessages['?'] = 'info';
		$tableTypeMessages['&'] = 'success';
		$tableTypeMessages['#']	= 'secondary';
		
		$return = [];
		
		foreach(self::$messages as $message)
		{
			$message	= trim($message);
			$letter_1	= substr($message,0,1);
			$message_1	= substr($message,1);
			
			$type		= isset($tableTypeMessages[$letter_1]) ? $tableTypeMessages[$letter_1] : $tableTypeMessages['#'];
			$message	= isset($tableTypeMessages[$letter_1]) ? $message_1 : $message;
			
			$return[]	= cadInstallGrav::parseModel(self::$modelMessage,['type'=>$type,'message'=>$message]);
		}
		if ($return)
		{
			return cadInstallGrav::parseModel(self::$modelMessages,['data'=>implode("\n",$return)]);
		}
		return '';
	}

	static public function getLinkGrav()
	{
		$re			= '/<li>Download either the <a href=".*or <a href="(.*)">Grav/m';
		
		$sourceGrav	= file_get_contents('https://getgrav.org/downloads');
		
		preg_match_all($re, $sourceGrav, $matches, PREG_SET_ORDER, 0);
		$grav		= ((isset($matches[0]) and isset($matches[0][1])) ? $matches[0][1] : '');
		
		return $grav;
	}
	
	static public function getLinkSkeleton()
	{
		$re					= '/<ul class="item-cards">(.*\n*)<\/ul>/s';
		
		$sourceSkeleton		= file_get_contents('https://getgrav.org/downloads/skeletons');
		
		preg_match_all($re, $sourceSkeleton, $matches, PREG_SET_ORDER, 0);
		$skeleton			= ((isset($matches[0]) and isset($matches[0][1])) ? $matches[0][1] : '');
		
		list($skeleton,)	= explode('</ul>',$skeleton);

		$pattern			= '/<a class="button button-vsmall download" href="(.*)">.*<\/a>/U';
		$replacement		= '<button type="button" class="button button-vsmall download alerte alert-danger" onclick="$(\'#package\').val(\'$1\');">Choose Me</button><br>';
		$skeleton			= preg_replace($pattern, $replacement, $skeleton);
		return $skeleton;
	}
	
	static public function getFormInstall()
	{
		$listInfoInput = 'name placeholder label type value parameters';
		
		$tableInputHtml = [];
		foreach(self::$tableForm as $input)
		{
			$infoInput = [];
			foreach(explode(' ',$listInfoInput) as $info)
			{
				$infoInput[$info] = isset($input[$info]) ? $input[$info] : '';
			}
			$infoInput['get'] = isset($_GET[$input['name']])	? cadInstallGrav::checkGet($_GET[$input['name']]) : $infoInput['value'];
			$tableInputHtml[] = cadInstallGrav::parseModel(self::$modelInput,$infoInput);
		}
		return cadInstallGrav::parseModel(self::$modelForm,['data'=>implode("\n",$tableInputHtml)]);
	}
	
}

/** Form Management */
$validInstall = false;
if ($_GET)
{
	$directory				= isset($_GET['directory'])	? cadInstallGrav::checkGet($_GET['directory'])	: '.';
	$package				= isset($_GET['package'])	? cadInstallGrav::checkGet($_GET['package'])	: '';
	$infoAdmin['user']		= isset($_GET['user'])		? cadInstallGrav::checkGet($_GET['user'])		: null;
	$infoAdmin['email']		= isset($_GET['email'])		? cadInstallGrav::checkGet($_GET['email'])		: null;
	$infoAdmin['fullname']	= isset($_GET['fullname'])	? cadInstallGrav::checkGet($_GET['fullname'])	: null;
	$infoAdmin['title']		= isset($_GET['title'])		? cadInstallGrav::checkGet($_GET['title'])		: null;
	$removeScript			= isset($_GET['remove']);

	/** Form Management */
	$validInstall = cadInstallGrav::install($directory,$package,$infoAdmin,$removeScript);
}

?>

<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="description" content="Grav Installation">
		<meta name="author" content="CaDJoU contact@cadjou.net">
		<meta name="generator" content="Jekyll v3.8.5">
		<title>Install Grav</title>

		<!-- Bootstrap core CSS -->
		<link rel="icon" type="image/png" href="https://getgrav-grav.netdna-ssl.com/user/themes/planetoid/images/favicon.png" />
		<link href="https://getbootstrap.com/docs/4.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
		<link href="https://getgrav-grav.netdna-ssl.com/assets/049497eb3545d8a323bfc0c96047c2e5.css" type="text/css" rel="stylesheet">
		<style>
			.bd-placeholder-img {
				font-size: 1.125rem;
				text-anchor: middle;
				-webkit-user-select: none;
				-moz-user-select: none;
				-ms-user-select: none;
				user-select: none;
			}

			@media (min-width: 768px) {
				.bd-placeholder-img-lg {
					font-size: 3.5rem;
				}
			}
		</style>
		<!-- Custom styles for this template -->
		<link href="https://getbootstrap.com/docs/4.3/examples/checkout/form-validation.css" rel="stylesheet">
	</head>
	<body class="bg-light">
		<div class="container">
		<div class="py-5 text-center">
			<img class="mb-4" src="https://getgrav-grav.netdna-ssl.com/user/pages/media/grav-logo.svg" alt="" width="671" height="186">
			<h2>Quick Grav Install</h2>
			<p class="lead">Created by CaDJoU <i><a href="https://github.com/cadjou/grav-quick-install" target="_blank">https://github.com/cadjou/grav-quick-install</a></i> with <b><a href="https://kwa.digital/" target="_blank">KWA Digital</a></b></p>
		</div>
		<div class="row">
			<div class="container">
				<?php echo cadInstallGrav::getMessages() ?>
			</div>
		</div>
		<?php if (!$validInstall){ ?>
			<div class="row">
				<div class="container">
					<h4>Choose your install</h4>
					<p>
						<button class="btn btn-primary" type="button" onclick="$('#package').val('<?php echo cadInstallGrav::getLinkGrav(); ?>');">
							GRAV CORE + ADMIN PLUGIN Last Version
						</button>
						<button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#multiCollapseExample1" aria-expanded="false" aria-controls="multiCollapseExample1">
							Choose in Grav Skeleton 
						</button>
					</p>
					<div class="row">
						<div class="collapse multi-collapse" id="multiCollapseExample1">
							<div class="card card-body">
								<ul class="item-cards" style="height:400px;overflow-y: scroll;">
									<?php echo cadInstallGrav::getLinkSkeleton(); ?>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="container">
					<?php echo cadInstallGrav::getFormInstall(); ?>
				</div>
			</div>
		<?php }; ?>
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="https://getbootstrap.com/docs/4.3/assets/js/vendor/jquery-slim.min.js"><\/script>')</script>
		<script src="https://getbootstrap.com/docs/4.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-xrRywqdh3PHs8keKZN+8zzc5TX0GRTLCcmivcbNJWm2rs5C8PRhcEn3czEjhAO9o" crossorigin="anonymous"></script>
		<script src="https://getbootstrap.com/docs/4.3/examples/checkout/form-validation.js"></script>
	</body>
</html>

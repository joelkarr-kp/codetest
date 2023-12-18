<?php
define('SOCIAL_HTTP_START', 113034);
define('AD_DELIVERY_ENGINE', 'dfp');

$strOption = '';
if (isset($_POST['option']))
	$strOption = $_POST['option'];

//we are no longer allowing files (even in rsforms) - if there is a file anywhere, then don't let the request through
if (!empty($_FILES)){
	header('HTTP/1.0 403 Forbidden');
	echo '403 - Forbidden';
	die();
}

//block specific head requests

if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
	if (isset($_GET['link']) && !empty($_GET['link'])){
		header('HTTP/1.0 403 Forbidden');
		echo '403 - Forbidden';
		die();
	}
}

//only allow specific type of posts
if (!empty($_POST) && $strOption != 'com_search' && $strOption != 'com_mailto'){
	if (empty($_POST['form']['formId'])){
		header('HTTP/1.0 403 Forbidden');
		echo '403 - Forbidden';
		die();
	}
}

if (!empty($_GET['searchword'])){
	$_GET['searchword'] = str_replace(array("'", "%27"), " ", $_GET['searchword']);
	$_SERVER['REQUEST_URI'] = '/search.html?'.http_build_query($_GET);
}
	

$currentURL = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
if ((stripos($currentURL, '\'') !== FALSE) || (stripos($currentURL, '%27') !== FALSE) || stripos($currentURL, 'com_jce') || stripos($currentURL, 'imgmanager') || stripos($currentURL, '../../../../') || stripos($currentURL, 'com_ajax') || stripos($currentURL, 'base64') || stripos($currentURL, 'passwd')){
	if (!isset($_GET['utm_campaign']))
		$_GET['utm_campaign'] = '';
	if ($_GET['utm_campaign'] != '05.13.20 Naveen\'s Territory' && $_GET['utm_campaign'] != 'Women\'s Leadership VE July 2020' && $_GET['utm_campaign'] != 'Becker\'s Webinar'){
		header('HTTP/1.0 403 Forbidden');
		if (isset($_GET['utm_campaign']))
			die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0001)'.$_GET['utm_campaign'].'</h1>');
		else
			die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0001)</h1>');
	}
}

if (isset($_GET['start'])){
	$myStart = intval($_GET['start']);
	if ($myStart > 980){
		header('HTTP/1.0 403 Forbidden');
		die('Pagination Limit Exceeded');
	}
}

if (stripos($_SERVER['REQUEST_URI'], 'jtags') !== FALSE){
	header('HTTP/1.0 403 Forbidden');
	die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0002)</h1>');
}

if (stripos($_SERVER['REMOTE_ADDR'], '91.108.88') === 0 || stripos($_SERVER['REMOTE_ADDR'], '5.157.57') === 0){
	header('HTTP/1.0 403 Forbidden');
	die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0003)</h1>');
}

if (isset($_SERVER['HTTP_USER_AGENT'])){
	if ((stripos($_SERVER['HTTP_USER_AGENT'], '}') !== FALSE) || (strlen($_SERVER['HTTP_USER_AGENT']) > 288)){
		header('HTTP/1.0 403 Forbidden');
		die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0004)</h1>');
	}
}

if (stripos($_SERVER['REQUEST_URI'], 'wp-login') !== FALSE){
	header('HTTP/1.0 403 Forbidden');
	die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0005)</h1>');
}

if (stripos($_SERVER['REQUEST_URI'], 'xmlrpc') !== FALSE){
	header('HTTP/1.0 403 Forbidden');
	die('<h1>Hacking Attempt: We will report your IP '.$_SERVER['REMOTE_ADDR'].' to your local authorities. (0006)</h1>');
}


//remove index.php from the URL - note: this gives a conflict with the send an email to a friend functionality
/*if (stripos($currentURL, 'index.php') !== FALSE){
	$currentURL = str_replace('/index.php', '', $currentURL);
	header("HTTP/1.1 301 Moved Permanently"); 
	header("Location: ".$currentURL); 
	exit();	
}*/


if (!isset($_SERVER['HTTP_USER_AGENT']))
	$_SERVER['HTTP_USER_AGENT'] = '';

//the below part ensures that indexed URLs or URLs from Disqus that have an olytics ID do not end up assigning that olytics ID to the visitor (who is someone else)
$httpReferer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (stripos ($httpReferer, 'dragonforms.com') !== FALSE)
	$httpReferer = '';
if ((!empty($httpReferer) || stripos($_SERVER['HTTP_USER_AGENT'], 'disqus') !== FALSE || stripos($_SERVER['HTTP_USER_AGENT'], 'Python-urllib') !== FALSE) && stripos ($currentURL, 'oly_enc_id') !== FALSE){
	$arrParsedURL = parse_url($currentURL);
	$strQueryURL = '';
	if (isset($arrParsedURL['query']))
		$strQueryURL = $arrParsedURL['query'];
	
	parse_str($strQueryURL, $arrURLParameters);
	unset($arrURLParameters['oly_enc_id']);
	unset($arrURLParameters['origin']);
	unset($arrURLParameters['utm_source']);
	$strNewQueryURL = http_build_query($arrURLParameters);
	$arrOldURLWithoutParameters = explode('?', $currentURL);
	$strOldURLWithoutParameters = $arrOldURLWithoutParameters[0];
	if (empty($strNewQueryURL))
		$strNewURL = $strOldURLWithoutParameters;
	else
		$strNewURL = $strOldURLWithoutParameters.'?'.$strNewQueryURL;
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: ".$strNewURL); 
	exit();
	die();
}


//block non html and php files (with the exception of the "index.php" file)
$strRequestURI = $_SERVER['REQUEST_URI'];
$arrRequestURI = explode('.', $strRequestURI);
if (count($arrRequestURI) > 1){
	//get the file extension
	$strFileExtension = strtolower($arrRequestURI[count($arrRequestURI) - 1]);
	//now check if there are ? signs after the extension
	$arrFileExtension = explode('?', $strFileExtension);
	$strFileExtension = $arrFileExtension[0];
	//now check if there are a forward slash / after the extension
	$arrFileExtension = explode('/', $strFileExtension);
	$strFileExtension = $arrFileExtension[0];
	//now remove all non-alphanumeric characters
	$strFileExtension = preg_replace("/[^a-z0-9 ]/", '', $strFileExtension);
	if (strlen($strFileExtension) <= 4 && strlen($strFileExtension) > 2){
		if (($strFileExtension != 'html' && $strFileExtension != 'htm' && $strFileExtension != 'php' && $strFileExtension != 'feed') || ($strFileExtension == 'php' && $arrRequestURI[count($arrRequestURI) - 2] != '/index')){
			if (isset($_GET['utm_campaign']) && $_GET['utm_campaign'] != 'amer_cbaw'){
				//send a different header and then die
				header('HTTP/1.0 404 Not Found');
				echo '404 - File not found';
				die();
			}
		}
	}
}

/*if (!empty($_POST) && $_POST['option'] != 'com_search' && $_POST['option'] != 'com_mailto'){
	$file = 'project-honeypot.php';
	$projectHoneypotData = file_get_contents($file);
	$projectHoneypotData .= 'Date: '.date("Y-m-d H:i:s")."\n";
	$projectHoneypotData .= 'IP: '.$_SERVER['REMOTE_ADDR']."\n";
	$projectHoneypotData .= print_r($_POST, true);
	$projectHoneypotData .= '--------------------------------------------'."\n\n\n\n";
	file_put_contents($file, $projectHoneypotData);
}


if (!empty($_FILES)){
	$file = 'project-honeypot.php';
	$projectHoneypotData = file_get_contents($file);
	$projectHoneypotData .= 'Date: '.date("Y-m-d H:i:s")."\n";
	$projectHoneypotData .= 'IP: '.$_SERVER['REMOTE_ADDR']."\n";
	$projectHoneypotData .= 'fILES'."\n";
	$projectHoneypotData .= print_r($_FILES, true);
	$projectHoneypotData .= '--------------------------------------------'."\n\n\n\n";
	file_put_contents($file, $projectHoneypotData);
}*/

$arrRequestURI = explode('/', $_SERVER['REQUEST_URI']);
$arrKnownNumbersInAliases = array('0725', '0808', '1000', '1800', '2008', '2009', '2010', '2011', '2012', '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '3600', '4000', '4500', '6000', '6100', '7700', '8400', '8900', '14000', '062714', '081514', '082914', '230000', '3945820');

if (count($arrRequestURI) == 3){
	$arrArticleAlias = explode('-', $arrRequestURI[2]);
	if (count($arrArticleAlias) > 1 && is_numeric($arrArticleAlias[0])){
		if ($arrArticleAlias[0] > 3000){
			if (strlen($arrArticleAlias[0]) > 3){ //this is a match, redirect
				if (!in_array($arrArticleAlias[0], $arrKnownNumbersInAliases)){
					$newRequestURI = str_replace('/'.$arrArticleAlias[0].'-', '/', $_SERVER['REQUEST_URI']);
					$currentURL = 'https://'.$_SERVER['HTTP_HOST'].$newRequestURI;
					header("HTTP/1.1 301 Moved Permanently"); 
					header("Location: ".$currentURL); 
					exit();	
				}
			}
		}
	}
}
	
	?>
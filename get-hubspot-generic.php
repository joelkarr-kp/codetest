<?php
	
header('Access-Control-Allow-Origin: *');

//get the type
$url = '';
if (isset($_GET['url'])){
	$url = $_GET['url'];
	$url = explode('?', $url);
	$url = $url[0];
}
elseif (isset($_POST['url'])){
	$url = $_POST['url'];
	$url = explode('?', $url);
	$url = $url[0];
}

if (stripos($url, 'https://beckers.dragonforms.com/') !== 0){ //we don't want any URL that doesn't contain these
	die('no url');
}

//do not allow URLs with weird characters
if(preg_match('/[^a-z_\-\:\/\.0-9]/i', $url)){
	die('');
}


define('_JEXEC', 1);

defined('_JEXEC') or die('not defined...');

// Saves the start time and memory usage.
$startTime = microtime(1);
$startMem  = memory_get_usage();

$currentDirectory = __DIR__;



if (!defined('_JDEFINES')) {
    define('JPATH_BASE', $currentDirectory);
    require_once JPATH_BASE . '/includes/defines.php';
}


// Check for presence of vendor dependencies not included in the git repository
if (!file_exists(JPATH_LIBRARIES . '/vendor/autoload.php') || !is_dir(JPATH_ROOT . '/media/vendor')) {
    echo file_get_contents(JPATH_ROOT . '/templates/system/build_incomplete.html');

    exit;
}


require_once JPATH_BASE . '/includes/framework.php';

// Set profiler start time and memory usage and mark afterLoad in the profiler.
JDEBUG && \Joomla\CMS\Profiler\Profiler::getInstance('Application')->setStart($startTime, $startMem)->mark('afterLoad');

// Boot the DI container
$container = \Joomla\CMS\Factory::getContainer();

/*
 * Alias the session service keys to the web session service as that is the primary session backend for this application
 *
 * In addition to aliasing "common" service keys, we also create aliases for the PHP classes to ensure autowiring objects
 * is supported.  This includes aliases for aliased class names, and the keys for aliased class names should be considered
 * deprecated to be removed when the class name alias is removed as well.
 */
$container->alias('session.web', 'session.web.site')
    ->alias('session', 'session.web.site')
    ->alias('JSession', 'session.web.site')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
    ->alias(\Joomla\Session\Session::class, 'session.web.site')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

// Instantiate the application.
$app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
$app->createExtensionNamespaceMap();

$maxArticles = 5;


function getSponsor($strCampaign, $arrType){
	return;
	for ($i = 0; $i < count($arrType); $i++){
		$strType = $arrType[$i];
		$arrCampaign = preg_split("/".$strType."/i", $strCampaign);
		//print_r($arrCampaign);
		if (count($arrCampaign) < 2)
			continue;;
		$strCampaign = str_replace('_', ' ', $arrCampaign[0]);
		$strCampaign = trim($strCampaign);
		if (!empty($strCampaign))
			return "<span class='sponsor'>Brought to you by ".$strCampaign."</span>";
	}
	return;
}


function formatContent($arrData, $type){
	switch ($type){
		case 'webinar': $messageTitle = "Webinar: "; $messageLink = 'Register Now'; $arrType = array('Webinar'); break;
		case 'ondemand webinar': $messageTitle = "On-Demand Webinar: "; $messageLink = 'Watch Now'; $arrType = array('On-Demand Webinar'); break;
		case 'whitepaper': $messageTitle = "Whitepaper: "; $messageLink = 'Read Now'; $arrType = array('WP', 'E-Book');  break;
		case 'podcast': $messageTitle = "Podcast: "; $messageLink = 'Listen Now'; $arrType = array('Podcast');  break;
	}
	for ($i = 0; $i < count($arrData); $i++){
		$message .= '<li>'.$messageTitle.$arrData[$i]['title'];
		if ($type == 'webinar'){
			$objWebinarDate = DateTime::createFromFormat('Y-m-d H:i:s', $arrData[$i]['start_date']);
			if ($objWebinarDate){
				$strWebinarDate = $objWebinarDate->format('l, F jS, Y \a\t g:i A');
				$message .= ' - '.$strWebinarDate.' CST';
			}
		}
		$message .= ' <a href="'.$arrData[$i]['url'].'?utm_campaign='.$arrData[$i]['campaign'].'&utm_source=registration&utm_content=recommended" target="_blank">'.$messageLink.'</a>'.getSponsor($arrData[$i]['campaign'], $arrType);
		$message .= '</li>';
	}
	return $message;
}


function formatMultipleContent($strResult){
	return '<style>#recommendations {display: block;font-size: 0.9em;font-weight: normal;line-height: 1.2em;color: #003974;background-color: #fafafa;padding: 0.5em;border: 2px solid #a80000 !important;border-radius: 5px;margin-top: 1em;margin-bottom: 1em;}#recommendations span{font-weight: bold;color: #a80000;}#recommendations li{color: #003974;font-weight: normal;padding-left: 0em;padding-top: 1em;}#recommendations li a{color: #003974;text-decoration: underline;font-weight: bold;}#recommendations .sponsor{display:block;color: #003974; font-size: 0.9em; font-style: italic;}</style>'."\n".'<span>Recommended for Healthcare Professionals:</span><ul>'.$strResult.'</ul>';
}

//cache the results
function storeCache($url, $content){
	$md5URL = md5($url);
	if (!file_exists(JPATH_CACHE.'/hubspot-generic-articles/')) {
	    mkdir(JPATH_CACHE.'/hubspot-generic-articles/', 0755, true);
	}
	$strFileName = JPATH_CACHE.'/hubspot-generic-articles/'.$md5URL.'.html';
	file_put_contents($strFileName, $content);
}


function getCache($url){
	$md5URL = md5($url);
	$strFile = JPATH_CACHE.'/hubspot-generic-articles/'.$md5URL.'.html';
	$now   = time();
	if (file_exists ($strFile) && ($now - filemtime($strFile) < 14400) ) { //refresh every 4 hours
		die(file_get_contents ($strFile));
	}
}

function sortWebinarByDate($a, $b){
	return $a['start_date'] > $b['start_date'];
}

function getRandomNumbers($min=1,$max=10,$count=1,$margin=0) {
    $range = range(0,$max-$min);
    $return = array();
    for( $i=0; $i<$count; $i++) {
        if( !$range) {
            trigger_error("Not enough numbers to pick from!",E_USER_WARNING);
            return $return;
        }
        $next = rand(0,count($range)-1);
        $return[] = $range[$next]+$min;
        array_splice($range,max(0,$next-$margin),$margin*2+1);
    }
    return $return;
}



/*this function will get the recommended webinars*/
function getWebinarsRaw($maxArticles, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`start_date` >= NOW() + INTERVAL 14 DAY;";
	$db->setQuery($sql);
	$arrRandom = $db->loadColumn();
	if (empty($arrRandom))
		return array();
	$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
	for ($i = 0; $i < count($arrRandomNumbers); $i++){
		$sql = "SELECT `title`, `url`, `campaign`, `start_date` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	usort ($arrResult, 'sortWebinarByDate');
	return $arrResult;
}


/*this function will get the ondemand webinars*/
function getOnDemandWebinarsRaw($maxArticles, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'ondemand webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
	$db->setQuery($sql);
	$arrRandom = $db->loadColumn();
	if (empty($arrRandom))
		return array();
	$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
	for ($i = 0; $i < count($arrRandomNumbers); $i++){
		$sql = "SELECT `title`, `url`, `campaign`, `start_date` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	return $arrResult;
}

/*this function will get the recommended whitepapers*/
function getWhitepapersRaw($maxArticles, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'whitepaper' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
	$db->setQuery($sql);
	$arrRandom = $db->loadColumn();
	if (empty($arrRandom))
		return array();
	$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
	for ($i = 0; $i < count($arrRandomNumbers); $i++){
		$sql = "SELECT `title`, `url`, `campaign`, `start_date` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	return $arrResult;
}


/*this function will get the recommended podcasts*/
function getPodcastsRaw($maxArticles, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'podcast' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
	$db->setQuery($sql);
	$arrRandom = $db->loadColumn();
	if (empty($arrRandom))
		return array();
	$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
	for ($i = 0; $i < count($arrRandomNumbers); $i++){
		$sql = "SELECT `title`, `url`, `campaign`, `start_date` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	return $arrResult;
}

function getHubSpotIdByURL($url, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `url`='".$url."';";
	$db->setQuery($sql);
	$id = trim($db->loadResult());
	$id = intval($id);
	return $id;
}

function getData($type, $maxArticles, $db){
	if (!in_array($type, array('webinar', 'ondemand webinar', 'whitepaper', 'podcast')))
		die();
	switch($type){
		case 'webinar': return getWebinarsRaw($maxArticles, $db);
		case 'ondemand webinar': return getOnDemandWebinarsRaw($maxArticles, $db);
		case 'whitepaper': return getWhitepapersRaw($maxArticles, $db);
		case 'podcast': return getPodcastsRaw($maxArticles, $db);
	}
}


$db = JFactory::getDbo();

function getMultipleData($url, $db){
	getCache($url);
	$arrResult = getData('webinar', '2', $db);
	$strResult = formatContent($arrResult, 'webinar');
	
	$arrResult = getData('ondemand webinar', '1', $db);
	$strResult .= formatContent($arrResult, 'ondemand webinar');
	
	$arrResult = getData('whitepaper', '2', $db);
	$strResult .= formatContent($arrResult, 'whitepaper');
	
	$strResult = formatMultipleContent($strResult);
	storeCache($url, $strResult);
	die($strResult);
}

//$id = getHubSpotIdByURL($url, $db);
getMultipleData($url, $db);
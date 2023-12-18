<?php
	
header('Access-Control-Allow-Origin: *');

$type = '';
if (isset($_GET['type']))
	$type = $_GET['type'];
elseif (isset($_POST['type']))
	$type = $_POST['type'];

if (!in_array($type, array('webinar', 'ondemand webinar', 'whitepaper', 'podcast')))
	die('no type');

if (empty($type))
	die('no type');

//get the type
$url = '';
if (isset($_GET['url'])){
	$url = $_GET['url'];
}
elseif (isset($_POST['url'])){
	$_POST['url'];
}

$url = explode('?', $url);
$url = $url[0];


if (stripos($url, 'https://go.beckershospitalreview.com/') !== 0){ //we don't want any URL that doesn't contain these
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


use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\CMS\Uri\Uri;

$maxArticles = 5;



function formatContent($arrData, $type, $random = false){
	switch ($type){
		case 'webinar': $messageTitle = "Webinar: "; $messageLink = 'Register Now'; $arrType = array('Webinar'); break;
		case 'ondemand webinar': $messageTitle = "On-Demand Webinar: "; $messageLink = 'Watch Now'; $arrType = array('On-Demand Webinar'); break;
		case 'whitepaper': $messageTitle = "Whitepaper: "; $messageLink = 'Read Now'; $arrType = array('WP', 'E-Book');  break;
		case 'podcast': $messageTitle = "Podcast: "; $messageLink = 'Listen Now'; $arrType = array('Podcast');  break;
	}
	for ($i = 0; $i < count($arrData); $i++){
		$message .= '<li>'.$arrData[$i]['title'];
		if ($type == 'webinar'){
			$objWebinarDate = DateTime::createFromFormat('Y-m-d H:i:s', $arrData[$i]['start_date']);
			if ($objWebinarDate){
				$strWebinarDate = $objWebinarDate->format('l, F jS, Y \a\t g:i A');
				$message .= ' - '.$strWebinarDate.' CST';
			}
		}
		
		$message .= ' <a href="#" onclick="registerAfter(`'.$arrData[$i]['form_id'].'`,`'.$arrData[$i]['url'].'`,'.strval($i).');">'.$messageLink.'</a>'.getSponsor($arrData[$i]['campaign'], $arrType);
		$message .= '</li>';
	}
	return $message;
}

function formatMultipleContent($strResult){
	return '<span>Other Opportunities you may be interested in (Register in 1 click):</span><ul>'.$strResult.'</ul></div>';
}

//cache the results
/*function storeCache($type, $id, $content){
	if (!in_array($type, array('webinar', 'whitepaper', 'podcast')))
		die();
	if (!file_exists(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/')) {
	    mkdir(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/', 0755, true);
	}
	if (!file_exists(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$type.'/')) {
	    mkdir(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$type.'/', 0755, true);
	}
	$strFileName = JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$type.'/'.$id.'.html';
	file_put_contents($strFileName, $content);
}


function getCache($type, $id){
	$strFile = JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$type.'/'.$id.'.html';
	$now   = time();
	if (file_exists ($strFile) && ($now - filemtime($strFile) < 14400) ) { //refresh every 4 hours
		die(file_get_contents ($strFile));
	}
}*/


//cache the results
function storeCache($url, $content){
	$md5URL = md5($url);
	if (!file_exists(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/')) {
	    mkdir(JPATH_CACHE.'/related-hubspot-to-hubspot-articles/', 0755, true);
	}
	$strFileName = JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$md5URL.'.html';
	file_put_contents($strFileName, $content);
}


function getCache($url){
	$md5URL = md5($url);
	$strFile = JPATH_CACHE.'/related-hubspot-to-hubspot-articles/'.$md5URL.'.html';
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

function getSponsor($strCampaign, $arrType){
	return;
	for ($i = 0; $i < count($arrType); $i++){
		$strType = $arrType[$i];
		$arrCampaign = preg_split("/".$strType."/i", $strCampaign);
		if (count($arrCampaign) < 2)
			continue;;
		$strCampaign = str_replace('_', ' ', $arrCampaign[0]);
		$strCampaign = trim($strCampaign);
		if (!empty($strCampaign))
			return "<span class='sponsor'>Brought to you by: ".$strCampaign."</span>";
	}
	return;
}


/*this function will get the recommended webinars*/
function getWebinarsRaw($id, $maxArticles, $db){
	//first get the metatags for the article
	$sql = "SELECT `mid`, `occurrences` FROM `#__metatags_hubspot_articles` WHERE `aid`= '".$id."'";
	$db->setQuery($sql);
	$arrArticleMeta = $db->loadAssocList();
	$arrHubSpotMatchingArticles = array();
	$randomEntry = false;
	
	//now get all the articles which id is < $id - 1000 and that have the same meta articles
	for ($i = 0; $i < count($arrArticleMeta); $i++){
		//this is the mid (the metatag id)
		$mid = $arrArticleMeta[$i]['mid'];
		
		//this is the number of occurences of that keyword in the article (which is a positive)
		$numOccurrencesArticle = $arrArticleMeta[$i]['occurrences'];
		
		//let us get get the number of occurrences in the metatags table (this is a negative)
		$sql = "SELECT `occurrences` FROM `#__metatags` WHERE `id`= '".$mid."'";
		$db->setQuery($sql);
		$numOccurrencesMeta = $db->loadResult();
		
		//get webinars
		$sql = "SELECT `aid` FROM `#__metatags_hubspot_articles`, `#__hubspot_article` WHERE `mid`= '".$mid."' AND `#__hubspot_article`.`type` = 'webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`start_date` >= NOW() + INTERVAL 5 HOUR AND `#__hubspot_article`.`start_date` <= NOW() + INTERVAL 31 DAY AND `#__hubspot_article`.`id` = `aid` AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrHubSpotArticlesWithSameMetatag = $db->loadAssocList();
		
		//now let us loop through the matching articles
		$weight = (10 * $numOccurrencesArticle)/$numOccurrencesMeta;
		
		for ($j = 0; $j < count($arrHubSpotArticlesWithSameMetatag); $j++){
			$arrHubSpotMatchingArticles[$arrHubSpotArticlesWithSameMetatag[$j]['aid']] += $weight;
		}
	}
	
	krsort ($arrHubSpotMatchingArticles); //first order by ID descending
	arsort ($arrHubSpotMatchingArticles);
	
	//now sort by start date ASC
	
	$arrHubSpotMatchingArticles = array_slice($arrHubSpotMatchingArticles, 0, 10, true);
	
	$i = 0;
	$arrResult = array();
	foreach ($arrHubSpotMatchingArticles as $key=>$value){
		if ($i++ >= $maxArticles)
			break;
		$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$key."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	
	if (empty($arrResult)){ // if we don't have a match, then let's get a random webinar
		$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`start_date` >= NOW() + INTERVAL 5 HOUR AND `#__hubspot_article`.`start_date` <= NOW() + INTERVAL 31 DAY;";
		$db->setQuery($sql);
		$arrRandom = $db->loadColumn();
		if (empty($arrRandom))
			return array();
		$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
		for ($i = 0; $i < count($arrRandomNumbers); $i++){
			$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
			$db->setQuery($sql);
			$arrResult[] = $db->loadAssoc();
		}
	}
	
	usort ($arrResult, 'sortWebinarByDate');
	return array('result'=>$arrResult, 'random'=>$randomEntry);
}


/*this function will get the recommended On-Demand Webinars*/
function getOnDemandWebinarsRaw($id, $maxArticles, $db){
	//first get the metatags for the article
	$sql = "SELECT `mid`, `occurrences` FROM `#__metatags_hubspot_articles` WHERE `aid`= '".$id."'";
	$db->setQuery($sql);
	$arrArticleMeta = $db->loadAssocList();
	$arrHubSpotMatchingArticles = array();
	$randomEntry = false;
	
	for ($i = 0; $i < count($arrArticleMeta); $i++){
		//this is the mid (the metatag id)
		$mid = $arrArticleMeta[$i]['mid'];
		
		//this is the number of occurences of that keyword in the article (which is a positive)
		$numOccurrencesArticle = $arrArticleMeta[$i]['occurrences'];
		
		//let us get get the number of occurrences in the metatags table (this is a negative)
		$sql = "SELECT `occurrences` FROM `#__metatags` WHERE `id`= '".$mid."'";
		$db->setQuery($sql);
		$numOccurrencesMeta = $db->loadResult();
		
		//get ondemand webinars
		$sql = "SELECT `aid` FROM `#__metatags_hubspot_articles`, `#__hubspot_article` WHERE `mid`= '".$mid."' AND `#__hubspot_article`.`type` = 'ondemand webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` = `aid` AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrHubSpotArticlesWithSameMetatag = $db->loadAssocList();
		
		//now let us loop through the matching articles
		$weight = (10 * $numOccurrencesArticle)/$numOccurrencesMeta;
		
		for ($j = 0; $j < count($arrHubSpotArticlesWithSameMetatag); $j++){
			$arrHubSpotMatchingArticles[$arrHubSpotArticlesWithSameMetatag[$j]['aid']] += $weight;
		}
	}
	
	krsort ($arrHubSpotMatchingArticles); //first order by ID descending
	arsort ($arrHubSpotMatchingArticles);
	
	$arrHubSpotMatchingArticles = array_slice($arrHubSpotMatchingArticles, 0, 10, true);
	
	$i = 0;
	$arrResult = array();
	foreach ($arrHubSpotMatchingArticles as $key=>$value){
		if ($i++ >= $maxArticles)
			break;
		$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$key."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	
	if (empty($arrResult)){ // if we don't have a match, then let's get a random ondemand webinar
		$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'ondemand webinar' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrRandom = $db->loadColumn();
		if (empty($arrRandom))
			return array();
		$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
		for ($i = 0; $i < count($arrRandomNumbers); $i++){
			$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
			$db->setQuery($sql);
			$arrResult[] = $db->loadAssoc();
		}
	}
	return array('result'=>$arrResult, 'random'=>$randomEntry);
}


/*this function will get the recommended whitepapers*/
function getWhitepapersRaw($id, $maxArticles, $db){
	//first get the metatags for the article
	$sql = "SELECT `mid`, `occurrences` FROM `#__metatags_hubspot_articles` WHERE `aid`= '".$id."'";
	$db->setQuery($sql);
	$arrArticleMeta = $db->loadAssocList();
	$arrHubSpotMatchingArticles = array();
	$randomEntry = false;
	
	for ($i = 0; $i < count($arrArticleMeta); $i++){
		//this is the mid (the metatag id)
		$mid = $arrArticleMeta[$i]['mid'];
		
		//this is the number of occurences of that keyword in the article (which is a positive)
		$numOccurrencesArticle = $arrArticleMeta[$i]['occurrences'];
		
		//let us get get the number of occurrences in the metatags table (this is a negative)
		$sql = "SELECT `occurrences` FROM `#__metatags` WHERE `id`= '".$mid."'";
		$db->setQuery($sql);
		$numOccurrencesMeta = $db->loadResult();
		
		//get whitepapers
		$sql = "SELECT `aid` FROM `#__metatags_hubspot_articles`, `#__hubspot_article` WHERE `mid`= '".$mid."' AND `#__hubspot_article`.`type` = 'whitepaper' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` = `aid` AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrHubSpotArticlesWithSameMetatag = $db->loadAssocList();
		
		//now let us loop through the matching articles
		$weight = (10 * $numOccurrencesArticle)/$numOccurrencesMeta;
		
		for ($j = 0; $j < count($arrHubSpotArticlesWithSameMetatag); $j++){
			$arrHubSpotMatchingArticles[$arrHubSpotArticlesWithSameMetatag[$j]['aid']] += $weight;
		}
	}
	
	krsort ($arrHubSpotMatchingArticles); //first order by ID descending
	arsort ($arrHubSpotMatchingArticles);
	
	$arrHubSpotMatchingArticles = array_slice($arrHubSpotMatchingArticles, 0, 10, true);
	
	$i = 0;
	$arrResult = array();
	foreach ($arrHubSpotMatchingArticles as $key=>$value){
		if ($i++ >= $maxArticles)
			break;
		$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$key."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	
	if (empty($arrResult)){ // if we don't have a match, then let's get a random whitepaper
		$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'whitepaper' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrRandom = $db->loadColumn();
		if (empty($arrRandom))
			return array();
		$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
		for ($i = 0; $i < count($arrRandomNumbers); $i++){
			$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
			$db->setQuery($sql);
			$arrResult[] = $db->loadAssoc();
		}
	}
	return array('result'=>$arrResult, 'random'=>$randomEntry);
}


/*this function will get the recommended podcasts*/
function getPodcastsRaw($id, $maxArticles, $db){
	//first get the metatags for the article
	$sql = "SELECT `mid`, `occurrences` FROM `#__metatags_hubspot_articles` WHERE `aid`= '".$id."'";
	$db->setQuery($sql);
	$arrArticleMeta = $db->loadAssocList();
	$arrHubSpotMatchingArticles = array();
	$randomEntry = false;
	
	for ($i = 0; $i < count($arrArticleMeta); $i++){
		//this is the mid (the metatag id)
		$mid = $arrArticleMeta[$i]['mid'];
		
		//this is the number of occurences of that keyword in the article (which is a positive)
		$numOccurrencesArticle = $arrArticleMeta[$i]['occurrences'];
		
		//let us get get the number of occurrences in the metatags table (this is a negative)
		$sql = "SELECT `occurrences` FROM `#__metatags` WHERE `id`= '".$mid."'";
		$db->setQuery($sql);
		$numOccurrencesMeta = $db->loadResult();
		
		//get podcasts
		$sql = "SELECT `aid` FROM `#__metatags_hubspot_articles`, `#__hubspot_article` WHERE `mid`= '".$mid."' AND `#__hubspot_article`.`type` = 'podcast' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` = `aid` AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrHubSpotArticlesWithSameMetatag = $db->loadAssocList();
		
		//now let us loop through the matching articles
		$weight = (10 * $numOccurrencesArticle)/$numOccurrencesMeta;
		
		for ($j = 0; $j < count($arrHubSpotArticlesWithSameMetatag); $j++){
			$arrHubSpotMatchingArticles[$arrHubSpotArticlesWithSameMetatag[$j]['aid']] += $weight;
		}
	}
	
	krsort ($arrHubSpotMatchingArticles); //first order by ID descending
	arsort ($arrHubSpotMatchingArticles);
	
	$arrHubSpotMatchingArticles = array_slice($arrHubSpotMatchingArticles, 0, 10, true);
	
	$i = 0;
	$arrResult = array();
	foreach ($arrHubSpotMatchingArticles as $key=>$value){
		if ($i++ >= $maxArticles)
			break;
		$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$key."'";
		$db->setQuery($sql);
		$arrResult[] = $db->loadAssoc();
	}
	
	if (empty($arrResult)){ // if we don't have a match, then let's get a random podcast
		$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `#__hubspot_article`.`type` = 'podcast' AND `#__hubspot_article`.`published` = '1'  AND `#__hubspot_article`.`do_not_recommend` = '0' AND `#__hubspot_article`.`hubspot_date` >= NOW() - INTERVAL 60 DAY AND `#__hubspot_article`.`id` != '".$id."';";
		$db->setQuery($sql);
		$arrRandom = $db->loadColumn();
		if (empty($arrRandom))
			return array();
		$arrRandomNumbers = getRandomNumbers(0, count($arrRandom) -1, $maxArticles);
		for ($i = 0; $i < count($arrRandomNumbers); $i++){
			$sql = "SELECT `title`, `url`, `campaign`, `start_date`, `form_id` FROM `#__hubspot_article` WHERE `id` = '".$arrRandom[$arrRandomNumbers[$i]]."'";
			$db->setQuery($sql);
			$arrResult[] = $db->loadAssoc();
		}
	}
	return array('result'=>$arrResult, 'random'=>$randomEntry);
}

function getHubSpotIdByURL($url, $db){
	$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `url`='".$url."';";
	$db->setQuery($sql);
	$id = trim($db->loadResult());
	$id = intval($id);
	return $id;
}

function getData($id, $type, $maxArticles, $db){
	if (!in_array($type, array('webinar', 'ondemand webinar', 'whitepaper', 'podcast')))
		die();
	switch($type){
		case 'webinar': $arrResult = getWebinarsRaw($id, $maxArticles, $db); break;
		case 'ondemand webinar': $arrResult = getOnDemandWebinarsRaw($id, $maxArticles, $db); break;
		case 'whitepaper': $arrResult = getWhitepapersRaw($id, $maxArticles, $db); break;
		case 'podcast': $arrResult = getPodcastsRaw($id, $maxArticles, $db); break;
	}
	if (is_array($arrResult) && isset($arrResult['result']))
		return $arrResult['result'];
	return array();
}

function getMultipleData($url, $id, $db){
	getCache($url);
	$arrResult = getData($id, 'webinar', '2', $db);
	$strResult = formatContent($arrResult, 'webinar');

	$arrResult = getData($id, 'ondemand webinar', '1', $db);
	$strResult .= formatContent($arrResult, 'ondemand webinar');
	
	$arrResult = getData($id, 'whitepaper', '2', $db);
	$strResult .= formatContent($arrResult, 'whitepaper');
	
	$strResult = formatMultipleContent($strResult);
	storeCache($url, $strResult);
	die($strResult);
}

$db = JFactory::getDbo();

$id = getHubSpotIdByURL($url, $db);
if (is_int($id) && $id > 0)
	getMultipleData($url, $id, $db);
die();
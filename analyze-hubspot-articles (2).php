<?php
if ($_GET['hash'] != 'YYjcIlavn932L4IP')
	die('wrong hash');

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 240);

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

function convertTimeStampToMySQLDateTime($strTimeStamp){
	return date("Y-m-d H:i:s", $strTimeStamp);
}

function cleanupDate($strDate){
	$strDate = strip_tags($strDate);
	$strDate = strtolower($strDate);
	$strDate = str_replace(array('st,', 'nd,', 'rd,', 'th,'), ',', $strDate);
	$strDate = str_replace(array('st ', 'nd ', 'rd ', 'th '), ' ', $strDate);
	$strDate = str_replace('augu', 'august', $strDate);
	$strDate = str_replace('&nbsp;', '', $strDate);
	$strDate = trim($strDate);
	$strDate = ucwords($strDate);
	return $strDate;
}

function fixTime($strTime){ //this function will add AM/PM and the minutes if they are not there
	$arrTime = explode(':', $strTime); //add the minutes if they are not there
	if (count($arrTime) == 1){
		$strTime = str_replace(array('am', 'AM', 'Am', 'pm', 'PM', 'Pm'), '', $strTime);
		$strTime = $strTime.':00';
	}
	if (!stripos($strTime, 'am') && !stripos($strTime, 'pm')){ //no am/pm - add them
		if ($arrTime[0] > 6 && $arrTime[0] < 12)
			$strTime .= ' am';
		else
			$strTime .= ' pm';
	}
	return $strTime;
}

function getWebinarOrPodcastTimeStampsAuxiliary($strContent, $separator = 'CDT'){
	$arrEventDate = explode(' '.$separator, $strContent);
	$strEventDate = trim($arrEventDate[0]);
	$arrEventDate = explode('>', $arrEventDate[0]);
	$strEventDate = array_pop($arrEventDate);
	$arrEventDate = explode('|', $strEventDate);
	$strEventDate = $arrEventDate[0];
	
	$strEventDate = trim($strEventDate);
	$strEventDate = cleanupDate($strEventDate);
	$strEventTime = $arrEventDate[1]; //get the current date
	$arrEventTime = explode('-', $strEventTime);
	$startTime = trim($arrEventTime[0]);
	$startTime = fixTime($startTime);
	$endTime = trim($arrEventTime[1]);
	$endTime = fixTime($endTime);
	$startTime .= ' 00';
	$endTime .= ' 00';

	
	$strStartDate = $strEventDate.' '.$startTime;
	$strEndDate = $strEventDate.' '.$endTime;
	
	$objStartDate = DateTime::createFromFormat('l, F j, Y g:i A s', $strStartDate);
	$objEndDate = DateTime::createFromFormat('l, F j, Y g:i A s', $strEndDate);
	
	if (!$objStartDate || !$objEndDate ){
		$objStartDate = DateTime::createFromFormat('F j, Y g:i A s', $strStartDate);
		$objEndDate = DateTime::createFromFormat('F j, Y g:i A s', $strEndDate);
	}
	if (!$objStartDate || !$objEndDate ){
		$objStartDate = DateTime::createFromFormat('l, F j Y g:i A s', $strStartDate);
		$objEndDate = DateTime::createFromFormat('l, F j Y g:i A s', $strEndDate);
	}
	if (!$objStartDate || !$objEndDate ){
		$objStartDate = DateTime::createFromFormat('F j Y g:i A s', $strStartDate);
		$objEndDate = DateTime::createFromFormat('F j Y g:i A s', $strEndDate);
	}
	
	if (!$objStartDate || !$objEndDate ){
		return FALSE;
	}
	return array('startDateTimeStamp'=> $objStartDate->getTimestamp(), 'endDateTimeStamp'=>$objEndDate->getTimestamp());
}


function getWebinarOrPodcastTimeStamps($strContent){
	$arrDateSeparators = array('CDT', 'CST', 'CT');
	for ($i = 0; $i < count($arrDateSeparators); $i++){
		$arrTimeStamps = getWebinarOrPodcastTimeStampsAuxiliary($strContent, $arrDateSeparators[$i]);
		if ($arrTimeStamps !== FALSE)
			return $arrTimeStamps;
	}
	return false;
}


function getVirtualEventStartDateTime($title){
	$title = strtolower($title);
	$arrTitle = explode('virtual_event', $title);
	if (count($arrTitle) < 2) //not a valid virtual event, we can skip it
		return FALSE;
	$strStartDate = $arrTitle[1]; //the second part is the date and time
	$strStartDate = str_replace('_', '', $strStartDate);
	$arrDate = explode('.', $strStartDate);
	
	if (count($arrDate) == 5) //we have the date and the time
		 $objStartDate = DateTime::createFromFormat('n.j.Y.G.i', $strStartDate);
	elseif (count($arrDate) == 3) //we only have the date
		 $objStartDate = DateTime::createFromFormat('n.j.Y.G.i', $strStartDate.'.0.00');
		 
	if (!$objStartDate){
		return FALSE;
	}
	return array('startDateTimeStamp'=> $objStartDate->getTimestamp());
}

function getVirtualEventTitle($title){
	$originalTitle = $title;
	$arrTitle = explode('virtual_event', strtolower($title));
	$title = $arrTitle[0];
	$title = substr ($originalTitle, 0, strlen($title));
	$title = str_replace('_', ' ', $title);
	$title = str_replace(' \'', '\'', $title);
	$title = trim($title);
	return $title;
}

function isWebinar($strTitle, $strBody){
	$strContent = $strTitle.' '.$strBody;
	return (stripos($strContent, '_webinar_') !== FALSE);
}

function isVirtualEvent($strFormTitle){
	$strContent = $strFormTitle;
	$strContent = str_replace('_', ' ', $strContent);
	return (stripos($strContent, 'virtual event') !== FALSE || stripos($strContent, 'virtual forum') !== FALSE);
}


function isOnDemandWebinar($strTitle, $strBody){
	$strContent = $strTitle.' '.$strBody;
	return (stripos($strContent, 'on_demand') !== FALSE);
}

function isWhitePaper($strTitle, $strBody){
	if (stripos($strTitle, 'book') !== FALSE || stripos($strTitle, 'wp') !== FALSE || stripos($strTitle, 'white') !== FALSE || stripos($strTitle, 'paper') !== FALSE || stripos($strTitle, 'case') !== FALSE)
		return true;
	if (stripos($strBody, 'whitepaper') !== FALSE || stripos($strBody, 'white paper') !== FALSE || stripos($strBody, 'ebook') !== FALSE || stripos($strBody, 'e-book') !== FALSE)
		return true;
	
}

function isPodcast($strTitle, $strBody){
	$strContent = $strTitle.' '.$strBody;
	return (stripos($strContent, 'podcast') !== FALSE);
}

function getHubSpotPageType($strTitle, $strBody, $strFormTitle){
	$strBody = str_replace('webinarinfo', '', $strbody);
	if (isVirtualEvent($strFormTitle)){
		return 'virtual event';
	}
	if (isOnDemandWebinar($strTitle, $strBody))
		return 'ondemand webinar';
	if (isWebinar($strTitle, $strBody))
		return 'webinar';
	if (isWhitePaper($strTitle, $strBody))
		return 'whitepaper';
	if (isPodcast($strTitle, $strBody))
		return 'podcast';
	return '';
}




function getGenericHubSpotData($singleHubSpotEntry, $arrForms){
	$objHubSpot = new stdClass();
	$fullTitle = $singleHubSpotEntry['title'].' '.$singleHubSpotEntry['html_title'].' '.$singleHubSpotEntry['page_title'].' '.$singleHubSpotEntry['campaign_name'];
	if (stripos($fullTitle, 'thank you') !== FALSE)
		return NULL;
	$objHubSpot->id = $singleHubSpotEntry['id'];
	$objHubSpot->published =  ($singleHubSpotEntry['is_published'])? 1 : 0;
	$objHubSpot->title =  $singleHubSpotEntry['html_title'];
	$objHubSpot->url =  $singleHubSpotEntry['url'];
	$objHubSpot->createdDateTime =  convertTimeStampToMySQLDateTime(intval($singleHubSpotEntry['created'] / 1000));
	$objHubSpot->formID = '';
	
	/*if ($objHubSpot->id == '41548752401')
		print_r($singleHubSpotEntry);*/
	
	foreach ($singleHubSpotEntry['widget_containers'] as $value){
		if (isset($value['deleted_at']))
			continue;
		$objHubSpot->introtextraw .= str_replace('&nbsp;', ' ', $value['widgets'][0]['body']['html']);
		$objHubSpot->introtext .= str_replace('&nbsp;', ' ', strip_tags($value['widgets'][0]['body']['html']));
		if (empty($objHubSpot->formID) && isset($value['widgets'][0]['body']['form_to_use']))
			$objHubSpot->formID = $value['widgets'][0]['body']['form_to_use'];
		if (empty($objHubSpot->formID) && isset($value['widgets'][1]['body']['form_to_use']))
			$objHubSpot->formID = $value['widgets'][1]['body']['form_to_use'];
		$objHubSpot->response_message .= $value['widgets'][0]['body']['response_message'].$value['widgets'][1]['body']['response_message'];
	}
	foreach ($singleHubSpotEntry['widgets'] as $value){
		$objHubSpot->introtextraw .= str_replace('&nbsp;', ' ', $value['body']['html']);
		$objHubSpot->introtext .= str_replace('&nbsp;', ' ', strip_tags($value['body']['html']));
		if (empty($objHubSpot->formID) && isset($value['body']['form_to_use']))
			$objHubSpot->formID = $value['body']['form_to_use'];
		$objHubSpot->response_message .= $value['body']['response_message'];
	}
	
	preg_match('/<a href="(.*?)">/', $objHubSpot->response_message, $arrLinks);
	$strAssetLink = '';
	if (!empty($arrLinks) && isset($arrLinks[1])){
		$arrAssetLink = explode('"', $arrLinks[1]);
		$strAssetLink = $arrAssetLink[0];
		if (stripos($strAssetLink, 'mailto') !== FALSE){
			$strAssetLink = '';
		}
		if (stripos($strAssetLink, '.pdf') === FALSE){ //not a whitepaper
			$strAssetLink = '';
		}
		if ($strAssetLink && stripos($strAssetLink, 'http:') === FALSE && stripos($strAssetLink, 'https:') === FALSE){ //we don't have any http link, we need to change this
			$strAssetLink = 'https://cdn2.hubspot.net/hubfs/498900/'.$strAssetLink;
		}
		
		$strAssetLink = str_replace('http://cdn2.hubspot.net/hubfs/498900/', 'https://go.beckershospitalreview.com/hubfs/', $strAssetLink);
		$strAssetLink = str_replace('https://cdn2.hubspot.net/hubfs/498900/', 'https://go.beckershospitalreview.com/hubfs/', $strAssetLink);
		$strAssetLink = str_replace('://', '***', $strAssetLink);
		$strAssetLink = str_replace('//', '/', $strAssetLink);
		$strAssetLink = str_replace('***', '://', $strAssetLink);
		$strAssetLink = str_replace('hubfs/hubfs', 'hubfs', $strAssetLink);
	}
	
	$objHubSpot->asset_link = $strAssetLink;
	
	
	if (stripos($objHubSpot->introtextraw, '<!--notrcm-->') !== FALSE || stripos($objHubSpot->introtextraw, '<!--notrcm!-->') !== FALSE){
		$doNotRecommend = 1;
	}
	else
		$doNotRecommend = 0;
	$objHubSpot->do_not_recommend = $doNotRecommend;
	
	if (stripos($objHubSpot->introtextraw, '<!--notrcmc-->') !== FALSE || stripos($objHubSpot->introtextraw, '<!--notrcmc!-->') !== FALSE){
		$doNotRecommendCheckbox = 1;
	}
	else
		$doNotRecommendCheckbox = 0;
	$objHubSpot->do_not_recommend_checkbox = $doNotRecommendCheckbox;
	
	if (stripos($objHubSpot->introtextraw, '<!--not-featured-->') !== FALSE || stripos($objHubSpot->introtextraw, '<!--not-featured!-->') !== FALSE){
		$doNotFeature = 1;
	}
	else
		$doNotFeature = 0;
	$objHubSpot->do_not_feature = $doNotFeature;
	
	if (stripos($objHubSpot->introtextraw, '<!--not-google-event-->') !== FALSE || stripos($objHubSpot->introtextraw, '<!--not-google-event-->') !== FALSE){
		$doNotAddAsGoogleEvent = 1;
	}
	else
		$doNotAddAsGoogleEvent = 0;
	$objHubSpot->do_not_add_to_google_events = $doNotAddAsGoogleEvent;

	
	
	$objHubSpot->introtext = preg_replace('/\s+/', ' ', trim($objHubSpot->introtext));
	
	$strFormTitle = getFormTitleById($objHubSpot->formID, $arrForms);
	
	$pageType = getHubSpotPageType($fullTitle, $objHubSpot->introtextraw, $strFormTitle);
	$objHubSpot->type = $pageType;
	if (empty($pageType)){
		return NULL;
	}
	
	if ($pageType == 'ondemand webinar'){ //the url should be that of the event
		$url = getFormURLById($objHubSpot->formID, $arrForms);
		if (!empty($url))
			$objHubSpot->url = $url;
	}

	$objHubSpot->eventStartDateTimeStamp = '0000-00-00 00:00:00';
	$objHubSpot->eventEndDateTimeStamp = '0000-00-00 00:00:00';
	$objHubSpot->campaign = trim($singleHubSpotEntry['campaign_name']);
	
	if ($pageType == 'webinar'){
		$arrStartAndEndDates = getWebinarOrPodcastTimeStamps($objHubSpot->introtextraw);
		if (!empty($arrStartAndEndDates)){
			$objHubSpot->eventStartDateTimeStamp = convertTimeStampToMySQLDateTime($arrStartAndEndDates['startDateTimeStamp']);
			$objHubSpot->eventEndDateTimeStamp = convertTimeStampToMySQLDateTime($arrStartAndEndDates['endDateTimeStamp']);
		}
		else return NULL;
	}
	
	return $objHubSpot;
}

function insertHubSpotArticle($objHubSpot, $db){ //effectively insert the HubSpot article in the database
	//first check if it exists
	$sql = "SELECT `id`, `hubspot_date` FROM `#__hubspot_article` WHERE `hid`='".$objHubSpot->id."'";
	$db->setQuery($sql);
	$arrResult = $db->loadAssoc();
	$objHubSpot->title = preg_replace('/[^[:print:]]/', '', $objHubSpot->title);
	$objHubSpot->introtext = preg_replace('/[^[:print:]]/', '', $objHubSpot->introtext);
	
	if (!empty($arrResult)){
		if(strtotime($arrResult['hubspot_date']) > strtotime('-90 days')) { // skip updating content that is older than 3 months
			//if it's older than 3 months, skip it, if not, update it
			$sql = "UPDATE `#__hubspot_article` SET `hid` = '".addslashes($objHubSpot->id)."', `title` = '".addslashes($objHubSpot->title)."', `content` = '".addslashes($objHubSpot->introtext)."', `url` = '".addslashes($objHubSpot->url)."', `type` = '".addslashes($objHubSpot->type)."', `campaign` = '".addslashes($objHubSpot->campaign)."', `form_id` = '".addslashes($objHubSpot->formID)."', `asset_link` = '".addslashes($objHubSpot->asset_link)."', `start_date` = '".addslashes($objHubSpot->eventStartDateTimeStamp)."', `end_date` = '".addslashes($objHubSpot->eventEndDateTimeStamp)."', `hubspot_date` = '".addslashes($objHubSpot->createdDateTime)."', `update_date` = current_timestamp(), `published` = '".addslashes($objHubSpot->published)."', `do_not_recommend` = '".addslashes($objHubSpot->do_not_recommend)."', `do_not_recommend_checkbox` = '".addslashes($objHubSpot->do_not_recommend_checkbox)."', `do_not_feature` = '".addslashes($objHubSpot->do_not_feature)."', `do_not_add_to_google_events` = '".addslashes($objHubSpot->do_not_add_to_google_events)."' WHERE `myj63_hubspot_article`.`id` = '".$arrResult['id']."';";
			$db->setQuery($sql);
			$db->execute();
		}
	}
	else{
		$sql = "INSERT INTO `#__hubspot_article` (`id`, `hid`, `title`, `content`, `url`, `type`, `campaign`, `form_id`, `asset_link`, `start_date`, `end_date`, `hubspot_date`, `insert_date`, `update_date`, `published`, `do_not_recommend`, `do_not_recommend_checkbox`, `do_not_feature`, `do_not_add_to_google_events`) VALUES (NULL, '".addslashes($objHubSpot->id)."', '".addslashes($objHubSpot->title)."', '".addslashes($objHubSpot->introtext)."', '".addslashes($objHubSpot->url)."','".addslashes($objHubSpot->type)."', '".addslashes($objHubSpot->campaign)."', '".addslashes($objHubSpot->formID)."', '".addslashes($objHubSpot->asset_link)."', '".addslashes($objHubSpot->eventStartDateTimeStamp)."', '".addslashes($objHubSpot->eventEndDateTimeStamp)."', '".addslashes($objHubSpot->createdDateTime)."', current_timestamp(), current_timestamp(), '".addslashes($objHubSpot->published)."', '".addslashes($objHubSpot->do_not_recommend)."', '".addslashes($objHubSpot->do_not_recommend_checkbox)."', '".addslashes($objHubSpot->do_not_feature)."', '".addslashes($objHubSpot->do_not_add_to_google_events)."');";
		$db->setQuery($sql);
		$db->execute();
	}
}

function insertHubSpotArticles($arrForms, $db){
	//if the HubSpot article is more than 3 months old, then don't bother updating it
	$hubSpotJson = file_get_contents('/home/hospital/public_html/cache/hubspot.json');
	$data = json_decode($hubSpotJson,true);
	$data = $data['objects'];
	for ($i = 0; $i < count($data); $i++){ //insert articles
		if (isset($data[$i]['label']) && !empty($data[$i]['label']) && $data[$i]['label'] != $data[$i]['campaign_name']) //this condition is to handle a bug where the campaign name doesn't change for an asset 17/03/2023 5:39 AM
			$data[$i]['campaign_name'] = $data[$i]['label'];
	
		$objHubSpot = getGenericHubSpotData($data[$i], $arrForms);
		if ($objHubSpot){
			insertHubSpotArticle($objHubSpot, $db);
		}
	}
}

function insertHubSpotVirtualEvents($arrForms, $db){
	foreach ($arrForms as $key=>$value){
		//first check if it is a virtual event or not
		$pageType = getHubSpotPageType('', '', $value['name']);
		if ($pageType != 'virtual event')
			continue;
		$arrStartAndEndDates = getVirtualEventStartDateTime($value['name']);
		if (!empty($arrStartAndEndDates)){
			$eventStartDateTimeStamp = convertTimeStampToMySQLDateTime($arrStartAndEndDates['startDateTimeStamp']);
		}
		else{
			continue;
		}
		$title = getVirtualEventTitle($value['name']);
		
		$sql = "SELECT `id` FROM `#__hubspot_article` WHERE `form_id`='".$key."'";
		$db->setQuery($sql);
		$arrResult = $db->loadAssoc();
		if (empty($arrResult)){
			//now we can add the virtual event to the database
			$sql = "INSERT INTO `#__hubspot_article` (`id`, `hid`, `title`, `content`, `url`, `type`, `campaign`, `form_id`, `asset_link`, `start_date`, `end_date`, `hubspot_date`, `insert_date`, `update_date`, `published`, `do_not_recommend`, `do_not_recommend_checkbox`) VALUES (NULL, '', '".addslashes($title)."', '', '".addslashes($value['url'])."','virtual event', '".addslashes($value['name'])."', '".addslashes($key)."', '', '".addslashes($eventStartDateTimeStamp)."', '0000-00-00 00:00:00', '".addslashes($value['created'])."', current_timestamp(), current_timestamp(), '".addslashes($value['published'])."', '".addslashes($value['do-not-recommend'])."', '".addslashes($value['do-not-recommend-checkbox'])."');";
			$db->setQuery($sql);
			$db->execute();
		}
		else{ //it is merely an update, let us just update
			$sql = "UPDATE `#__hubspot_article` SET `title`='".addslashes($title)."', `url`='".addslashes($value['url'])."', `type`='virtual event', `campaign`='".addslashes($value['name'])."', `start_date`='".addslashes($eventStartDateTimeStamp)."', `end_date`='0000-00-00 00:00:00', `update_date`= current_timestamp(), `published` = '".addslashes($value['published'])."', `do_not_recommend` = '".addslashes($value['do-not-recommend'])."', `do_not_recommend_checkbox` = '".addslashes($value['do-not-recommend-checkbox'])."' WHERE `#__hubspot_article`.`form_id` = '".$key."';";
			$db->setQuery($sql);
			$db->execute();
		}
	}
}


function insertArticleMeta($aid, $mid, $occurrences, $db){
	$sql = "INSERT INTO `#__metatags_hubspot_articles` (`id`, `aid`, `mid`, `occurrences`) VALUES (NULL, '".$aid."', '".$mid."', '".$occurrences."');";
	$db->setQuery($sql);
	$db->execute();
}

function getAllMetatags($db){
	$sql = "SELECT `id`, `title` FROM `#__metatags` WHERE `exclude` = 0";
	$db->setQuery($sql);
	$arrMeta = $db->loadAssocList();
	return ($arrMeta);
}


function generateAllArticleMeta($db){
	$sql = "SELECT MAX(`aid`) FROM `#__metatags_hubspot_articles`";
	$db->setQuery($sql);
	$currentId = $db->loadResult();
	if (empty($currentId))
		$currentId = 0;
	
	$arrMeta = getAllMetatags($db);
	
	$sql = "Select `id` FROM `#__hubspot_article`  WHERE `id` > ".$currentId." ORDER BY `id` ASC";
	$db->setQuery($sql);
	$arrArticle = $db->loadAssocList();
	
	for ($i = 0; $i < count($arrArticle); $i++){
		generateSingleArticleMeta($arrArticle[$i]['id'], $arrMeta, $db);
	}
}

/*This function generates and inserts the article meta of a single article */
function generateSingleArticleMeta($id, $arrMeta, $db){
	$sql = "SELECT `content` FROM `#__hubspot_article` WHERE `id` = '".$id."'";
	$db->setQuery($sql);
	$articleText = $db->loadResult();
	$articleText = preg_replace('#<[^>]+>#', ' ', $articleText);
	$articleText = " ".str_replace(array(',', '.', ';', ':', '!', "\n"), ' ', $articleText)." ";
	
	for ($i = 0; $i < count($arrMeta); $i++){
		$strMeta = $arrMeta[$i]['title'];
		$strMeta = stripslashes($strMeta);
		$strMeta = ' '.$strMeta.' ';
		$numOccurrences = substr_count(strtolower($articleText), strtolower($strMeta));
		if ($numOccurrences > 0){
			insertArticleMeta($id, $arrMeta[$i]['id'], $numOccurrences, $db);
		}
	}
}


function getAllFormTitlesByIDs(){
	$arrForms = array();
	$hubSpotJson = file_get_contents('/home/hospital/public_html/cache/forms.hubspot.json');
	$data = json_decode($hubSpotJson,true);
	for ($i = 0; $i < count($data); $i++){

		$url = '';
		$doNotRecommend = 0;
		if (stripos($data[$i]['inlineMessage'], '&lt;!--notrcm--&gt;') !== FALSE || stripos($data[$i]['inlineMessage'], '&lt;!--notrcm!--&gt;') !== FALSE){
			$doNotRecommend = 1;
		}
		$doNotRecommendCheckbox = 0;
		if (stripos($data[$i]['inlineMessage'], '&lt;!--notrcmc--&gt;') !== FALSE || stripos($data[$i]['inlineMessage'], '&lt;!--notrcmc!--&gt;') !== FALSE){
			$doNotRecommendCheckbox = 1;
		}
		preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $data[$i]['inlineMessage'], $arrLinks);
		$arrLinks = $arrLinks[0];
		for ($j = 0; $j < count ($arrLinks); $j++){
			if (stripos($arrLinks[$j], 'https://view.ceros.com/') !== FALSE || stripos($arrLinks[$j], 'https://events.beckershospitalreview.com/') !== FALSE || stripos($arrLinks[$j], 'https://event.on24.com/') !== FALSE || stripos($arrLinks[$j], 'https://conferences.beckershospitalreview.com/') !== FALSE){
				$url = $arrLinks[$j];
				break;
			}
		}
		$createdDate =  convertTimeStampToMySQLDateTime(intval($data[$i]['createdAt'] / 1000));
		$arrForms[$data[$i]['guid']] = array('name'=>$data[$i]['name'], 'created'=> $createdDate, 'url'=> $url, 'do-not-recommend'=> $doNotRecommend, 'do-not-recommend-checkbox'=> $doNotRecommendCheckbox, 'published'=>$data[$i]['isPublished']);
	}
	return $arrForms;
}

function associateFormTitles($arrForms, $db){
	//let us loop through the forms and update the pages
	foreach ($arrForms as $key=>$value){
		//let us update each and every form
		$sql = "UPDATE `#__hubspot_article` SET `form_title` = '".addslashes($value['name'])."' WHERE `form_id` = '".addslashes($key)."';";
		$db->setQuery($sql);
		$db->execute();
	}
	return $arrForms;
}

function getFormTitleById($formId, $arrForms){
	return $arrForms[$formId]['name'];
}

function getFormURLById($formId, $arrForms){
	return $arrForms[$formId]['url'];
}

$arrForms = getAllFormTitlesByIDs();

$db = JFactory::getDbo();
insertHubSpotArticles($arrForms, $db);

insertHubSpotVirtualEvents($arrForms, $db);
associateFormTitles($arrForms, $db);
generateAllArticleMeta($db);

$currentHour = date('H');
if ($currentHour == '4' || $currentHour == '04'){
	$headers = 'From: Becker\'s Healthcare<donotreply@asccommunications.com>' . "\r\n" .
	'Reply-To: Becker\'s Healthcare<donotreply@asccommunications.com>' . "\r\n" .
	'MIME-Version: 1.0' . "\r\n" .
	'Content-type:text/html;charset=UTF-8' . "\r\n" .
	'X-Mailer: PHP/' . phpversion();
	
	$subject = 'HubSpot articles fully analyzed';

	//mail($to, $subject, $message, $headers);
	mail('fadi@itoctopus.com', $subject, $subject, $headers);
		
}


die('done...');
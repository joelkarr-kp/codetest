<?php
	
	$strHash = $_GET['hash'];
	if ($strHash != 'avn9YYjcIl32L4IP')
		die();
	
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
	
	function getAlternateLink($url){
		$arrAlternateLinks = array('https://blubrry.com/beckershealthcarepodcast/64044678/gary-guthart-ceo-of-intuitive-maker-of-the-da-vinci-surgical-system-and-ion-endoluminal-system/' => 'https://bit.ly/3fd4VJj');
		if (isset($arrAlternateLinks[$url]))
			return $arrAlternateLinks[$url];
		return $url;
	}
	
	function replaceLinkText($strContent, $oldLinkText, $newLinkText){
		return str_replace(' target="_blank">'.$oldLinkText.'</a>', ' target="_blank">'.$newLinkText.'</a>', $strContent);
	}
	
	function generateAlias($title){
		return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
	}
	
	function getContentIDByPodcastID($podcastID, $db){
		$sql = "SELECT `contentid` FROM `#__content_podcast` WHERE `podcastid` ='".addslashes($podcastID)."'"; 
		$db->setQuery($sql);
		return $db->loadResult();
	}

	function getContentIDByAlias($alias, $db){
		$sql = "SELECT `id` FROM `#__content` WHERE `alias` ='".addslashes($alias)."'"; 
		$db->setQuery($sql);
		return $db->loadResult();
	}
	
	function generatePodcastObjects($arrPodcasts){
		$arrObjPodcasts = array();
		foreach ($arrPodcasts as $catid=>$podcast){
			//read the podcasts
			$ordering = 1;
			$rss = simplexml_load_file('/home/hospital/public_html/cache/'.$podcast.'.xml');
			foreach ($rss->channel->item as $item) {
				$itunesObject = $item->children('itunes', true);
				$title= $item->title;
				$title = preg_replace('/&(?![A-Za-z0-9#]{1,7};)/', '&amp;', $title);
				$publishDate = $item->pubDate;
				$publishDate = new DateTime($publishDate, $timeZone);
				$publishDateTimeStamp = $publishDate->getTimestamp();
				$description = $item->description;
				$originalDescription = $description;
				
				if (($publishDateTimeStamp < $ninteyDaysAgo) &&  stripos($description, 'sponsored by')){ //don't show sponsored podcasts that are older than 90 days
					continue;
				}
				
				if ($publishDateTimeStamp < $yearAgo){ //don't show sponsored podcasts that are older than 90 days
					continue;
				}
				if (empty($itunesObject)){
					
					$description = strip_tags($description, '<p><a><ul><li>');
					$description = str_replace(array(' class="MsoNormal"', ' class="MsoListParagraph"'), '', $description);
					$description = str_replace(array('<p></p>', '<p> </p>'), '', $description);
				}
				else{
					$description = $itunesObject->summary;
					//$originalDescription = $description;
					
					
					//$description = preg_replace('/[\x00-\x1F\x7F\xA0]/u', ' ', $itunesObject->summary); //remove hidden weird characters
					$description = preg_replace( '/[^[:print:]\r\n]/', ' ', $description); //remove hidden weird characters
					$description = trim($description); //trim description
					$description = preg_replace("/[[:blank:]]+/"," ",$description); //replace more than one space with just one space
					$description = preg_replace("/(\r?\n){2,}/", "\n\n", $description); //replace more than one newline with one newline
					$description = preg_replace('/(http[s]{0,1}\:\/\/\S{4,})\s{0,}/ims', '<a href="$1" target="_blank">$1</a> ', $description); //generate links
					$description = str_replace(array(')" target="_blank">', '.)" target="_blank">', ')." target="_blank">', ')/" target="_blank">'), '" target="_blank">', $description); //fix HTML issues caused by the previous command
					$description = str_replace(array(')</a> ', '.)</a> ', ').</a>', '/)/</a>'), '</a>).', $description);  //fix HTML issues caused by the previous command
					$description = str_replace("\n", '<br />', $description); //replace newlines with <br />
					$description = trim($description); //trim description
					$description = replaceLinkText($description, 'https://www.intuitive.com/en-us?utm_source=beckers-podcast-platform&utm_medium=podcast&utm_campaign=gary-scott-interview', 'https://www.intuitive.com/');
					$description = str_replace('https://oliveai.com/?utm_campaign=CPC_May20_PP&utm_source=google&utm_medium=cpc&utm_term=olive%20healthcare&gclid=Cj0KCQiAqdP9BRDVARIsAGSZ8AkB4sAdS4yeDbOkCpjY9SxAvxqz0kTQW0icG1q18RC8434uT9qSLsAaAv-aEALw_wcB', 'https://oliveai.com/?utm_campaign=CPC_May20_PP&utm_source=google&utm_medium=cpc&utm_term=olive%20healthcare', $description);
					$description = replaceLinkText($description, 'https://oliveai.com/?utm_campaign=CPC_May20_PP&utm_source=google&utm_medium=cpc&utm_term=olive%20healthcare', 'https://oliveai.com/', $description);
				}

				if (isset($item->link)){
					$podcastId = (int)str_replace(array('https://www.blubrry.com/' .$podcast. '/', 'https://blubrry.com/' .$podcast. '/'), '',  $item->link);
					$link = (string)$item->link;
				}
				else{
					continue;
				}
				
				$objPodcast = new stdClass();



				$objPodcast->title = htmlspecialchars_decode($title);
				$objPodcast->catid = $catid;
				$publishDateTime = date('Y-m-d H:i:s', $publishDateTimeStamp);
				$objPodcast->publishdatetime = $publishDateTime;
				$objPodcast->createddatetime = $publishDateTime;
				
				$objPodcast->introtext = '<p>'.htmlspecialchars_decode($originalDescription).'</p>';
				$link = getAlternateLink($link);
				//$objPodcast->fulltext = $description."\n\n".'***|||***'.$link;
				//$objPodcast->fulltext = 'https://player.blubrry.com/id/' . $podcastId;
				$objPodcast->fulltext = '<p><iframe style="margin: 0px auto; display: block;" xml="lang" src="https://player.blubrry.com/id/'. $podcastId .'/#time-0&amp;darkOrLight-Light&amp;shownotes-ffffff&amp;shownotesBackground-006d9b&amp;download-ffffff&amp;downloadBackground-840d10&amp;subscribe-ffffff&amp;subscribeBackground-006d9b&amp;share-ffffff&amp;shareBackground-840D10" width="100%" height="auto" frameborder="0" scrolling="no" data-service="player.blubrry"></iframe></p>';
				
				$objPodcast->link =  $link; //currently not being used as we are only using the embed link (which is in the full text)
				$objPodcast->id = $podcastId;
				$objPodcast->alias = generateAlias($title.' '.$podcastId);
				$objPodcast->ordering = 0;
				if (stripos($description, 'sponsored by')){
					$arrSponsor = explode('ponsored by', $description);
					$arrSponsor = explode('<br ', $arrSponsor[1]);
					$sponsor = trim($arrSponsor[0]);
					$objPodcast->sponsor = $sponsor;
					if (strtotime($objPodcast->publishdatetime) > strtotime('-14 days')){
						$strPodcastTime = $ordering++ * 10;
						$strPodcastTime = strtotime('-'.$strPodcastTime.' second');
						$objPodcast->createddatetime = date('Y-m-d H:i:s', $strPodcastTime);
					}
				}
				
				$arrObjPodcasts[] = $objPodcast;
			}
		}
		return $arrObjPodcasts;
	}
	
	function insertPodcast($objPodcast, $db){
		//first check if the podcast exists in the database
		$contentID = getContentIDByPodcastID($objPodcast->id, $db);
		if (empty($contentID)){
			$objPodcast->sponsor = substr($objPodcast->sponsor,0,255);

			//new item, insert it in the #__content and the #__content_podcast
			$sql = "INSERT INTO `#__content` (`id`, `asset_id`, `title`, `alias`, `introtext`, `fulltext`, `state`, `catid`, `created`, `created_by`, `created_by_alias`, `modified`, `modified_by`, `checked_out`, `checked_out_time`, `publish_up`, `publish_down`, `images`, `urls`, `attribs`, `version`, `ordering`, `metakey`, `metadesc`, `access`, `hits`, `metadata`, `featured`, `language`, `note`) VALUES ('0', '0', '".addslashes($objPodcast->title)."', '".addslashes($objPodcast->alias)."', '".addslashes($objPodcast->introtext)."', '".addslashes($objPodcast->fulltext)."', '1', '".addslashes($objPodcast->catid)."', '".addslashes($objPodcast->createddatetime)."', '82', '".addslashes($objPodcast->sponsor)."', '".addslashes($objPodcast->publishdatetime)."', '0', NULL, NULL, '".date('Y-m-d h:i:s')."', NULL, '{\"image_intro\":\"\",\"float_intro\":\"\",\"image_intro_alt\":\"\",\"image_intro_caption\":\"\",\"image_fulltext\":\"\",\"float_fulltext\":\"\",\"image_fulltext_alt\":\"\",\"image_fulltext_caption\":\"\"}', '{\"urla\":false,\"urlatext\":\"\",\"targeta\":\"\",\"urlb\":false,\"urlbtext\":\"\",\"targetb\":\"\",\"urlc\":false,\"urlctext\":\"\",\"targetc\":\"\"}', '{\"article_layout\":\"\",\"show_title\":\"\",\"link_titles\":\"\",\"show_tags\":\"\",\"show_intro\":\"\",\"info_block_position\":\"\",\"info_block_show_title\":\"\",\"show_category\":\"\",\"link_category\":\"\",\"show_parent_category\":\"\",\"link_parent_category\":\"\",\"show_associations\":\"\",\"show_author\":\"\",\"link_author\":\"\",\"show_create_date\":\"\",\"show_modify_date\":\"\",\"show_publish_date\":\"\",\"show_item_navigation\":\"\",\"show_icons\":\"\",\"show_print_icon\":\"\",\"show_email_icon\":\"\",\"show_vote\":\"\",\"show_hits\":\"\",\"show_noauth\":\"\",\"urls_position\":\"\",\"alternative_readmore\":\"\",\"article_page_title\":\"\",\"show_publishing_options\":\"\",\"show_article_options\":\"\",\"show_urls_images_backend\":\"\",\"show_urls_images_frontend\":\"\"}', '1', '".$objPodcast->ordering."', '', '', '1', '0', '{\"robots\":\"\",\"author\":\"\",\"rights\":\"\",\"xreference\":\"\"}', '0', '*', '');";
			$db->setQuery($sql."\n");
			$db->execute();
						
			$contentID = getContentIDByAlias($objPodcast->alias, $db);
			$sql = "INSERT INTO `#__content_podcast` (`id`, `contentid`, `podcastid`) VALUES (NULL, '".addslashes($contentID)."', '".addslashes($objPodcast->id)."');";
			$db->setQuery($sql);
			$db->execute();
		}
		else{
			
			//check if the item is manually modified, this happens when the modification date is different than the creation date, if it is, then don't update it
			$sql = "SELECT * FROM `#__content` WHERE `id` = '".addslashes($contentID)."'";
			$db->setQuery($sql);
			$arrContentData = $db->loadAssoc();
			
			if (empty($arrContentData))
				return;
			if (empty($arrContentData['created_by_alias'])){
				
				if ($arrContentData['created'] != $arrContentData['modified']) //item is manually modified, skip
					return;
				if ($arrContentData['title'] == $objPodcast->title && $arrContentData['alias'] == $objPodcast->alias && $arrContentData['introtext'] == $objPodcast->introtext && $arrContentData['catid'] == $objPodcast->catid && $arrContentData['created'] == $objPodcast->publishdatetime && $arrContentData['created_by_alias'] == $objPodcast->sponsor && $arrContentData['ordering'] == $objPodcast->ordering){ //item hasn't changed, do not update it
					return;
				}
			}
			
			//update item
			//DO NOT UNCOMMENT THE BELOW, AS IT WILL DESTROY ANY CHANGES 
			//$sql = "UPDATE `#__content` SET `title` = '".addslashes($objPodcast->title)."', `alias` = '".addslashes($objPodcast->alias)."', `introtext` = '".addslashes($objPodcast->introtext)."', `fulltext` = '".addslashes($objPodcast->fulltext)."', `catid` = '".addslashes($objPodcast->catid)."', `created` = '".addslashes($objPodcast->createddatetime)."', `created_by` = '82', `created_by_alias` = '".addslashes($objPodcast->sponsor)."', `modified` = '".addslashes($objPodcast->publishdatetime)."', `ordering` = '".addslashes($objPodcast->ordering)."' WHERE `id`='".addslashes($contentID)."'";
			$sql = "UPDATE `#__content` SET `title` = '".addslashes($objPodcast->title)."', `alias` = '".addslashes($objPodcast->alias)."', `catid` = '".addslashes($objPodcast->catid)."', `created` = '".addslashes($objPodcast->createddatetime)."', `created_by` = '82', `modified` = '".addslashes($objPodcast->publishdatetime)."', `ordering` = '".addslashes($objPodcast->ordering)."' WHERE `id`='".addslashes($contentID)."'";
			$db->setQuery($sql);
			$arrResult = $db->execute();
		}
		
	}
	
	
	$db = JFactory::getDbo();
	
	$arrPodcasts = array('297'=>'womensleadership', '298'=>'standingroomonly', '299'=>'ambulatorysurgerycenters', '300'=>'spineandorthopodspodcast', '301'=>'beckersdentaldsopodcast', '302'=>'beckerspayerissuespodcast', '303'=>'pediatricleadershippodcast', '311'=>'cardiologypodcast', '312'=>'clinicalleadershippodcast', '314'=>'beckershealthcarepodcast', '321'=> '1461261');
	$arrObjPodcasts = generatePodcastObjects($arrPodcasts);
	for ($i = 0; $i < count($arrObjPodcasts); $i++){
		insertPodcast($arrObjPodcasts[$i], $db);
	}
	
	//print_r($arrObjPodcasts);
	
die('done...');
<?php
	
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



//get the ID of the current article
$id = Joomla\CMS\Factory::getApplication()->getInput()->get('articleId', 0);
$id = intval($id);
if (!is_int($id)) //do not continue if the article id is not an integer
	die('not an integer...');
	
	
if ($id > 0){
	
	//first check if there is a cached file containing the article data
	$relatedArticlesProcessedFile = JPATH_CACHE.'/related-articles/'.$id.'.html';
	if (file_exists ($relatedArticlesProcessedFile)) { //the file exists, let's load it and return it
		die(file_get_contents ($relatedArticlesProcessedFile));
	}

	
	//additional check if this is an article or not (the article has to have 2 parts in the URL, and has to have .html at the end of the URL)
	$currentURL = $_SERVER['REQUEST_URI'];
	$arrCurrentURL = explode('/', $currentURL);
	$generateCache = false; //do not generate the cached file unless explicitly told to do so
	$strCachedFile = '';

	if (1 || (count($arrCurrentURL == 3) && stripos($currentURL, '.html') !== FALSE)){
		
		//this is an article, let us continue
		$db = JFactory::getDbo();
		$sql = "SELECT `related` FROM `#__metatags_articles_related` WHERE `aid` = '".$id."'";
		$db->setQuery($sql);
		$arrRelatedArticlesTemp = $db->loadAssocList();

		if (count($arrRelatedArticlesTemp) >= 1)
			$generateCache = true;
		else
			die(); //no need to do anything - it might be that the cron didn't run yet to generate the related articles list

		$strRelatedArticles = $arrRelatedArticlesTemp[0]['related'];
		
		$strRelatedArticlesResult = '';
		$numRelatedArticles = 0;
		if (!empty($strRelatedArticles)){

			$arrRelatedArticles = explode(',', $strRelatedArticles);
			
			echo('<span></span>');
			if (count($arrRelatedArticles) >= 5){ //only display if we have 5 related articles
				$rootURL = rtrim(JURI::Root(),'/');
				
				for ($i = 0; $i < count($arrRelatedArticles); $i++){ //let us loop through all the related articles and get their title and URL
					if ($numRelatedArticles == 5)
						break;
					if (is_numeric($arrRelatedArticles[$i])){
						$sql = "SELECT `#__content`.`id`, `#__content`.`title`, `#__content`.`catid`, `#__content`.`alias`, `#__categories`.`alias` AS `catalias` FROM `#__content`, `#__categories` WHERE `#__content`.`catid` = `#__categories`.`id` AND `#__content`.`state` = 1 AND `#__content`.`id` = '".$arrRelatedArticles[$i]."'";
						$db->setQuery($sql);
						$arrRelatedArticle = $db->loadAssoc();

						if (!empty($arrRelatedArticle)){
							
							$articleURL = Route::_(RouteHelper::getArticleRoute($arrRelatedArticle['alias'], $arrRelatedArticle['catalias']));
							if(!$articleURL){$articleURL = 'https://www.beckershospitalreview.com/'.$arrRelatedArticle['catalias'].'/'.$arrRelatedArticle['alias'];}
							$articleURL = $articleURL.'?utm_campaign=bhr&utm_source=website&utm_content=related';
							$articleTitle = $arrRelatedArticle['title'];

							if (stripos($articleTitle, ' issue of ') !== FALSE || stripos($articleTitle, 'Webinars') !== FALSE || stripos($articleTitle, 'White papers') !== FALSE || stripos($articleTitle, 'Whitepapers') !== FALSE || stripos($articleTitle, 'books') !== FALSE || stripos($articleTitle, 'podcasts') !== FALSE || stripos($articleTitle, 'roundtables') !== FALSE || stripos($articleTitle, 'videos') !== FALSE || stripos($articleTitle, 'weekly') !== FALSE || stripos($articleTitle, 'issue') !== FALSE || stripos($articleURL, 'whitepaper') !== FALSE|| stripos($articleURL, 'webinar') !== FALSE){ //we don't want those
								continue;
							}
							$numRelatedArticles++;
							$articleTitle = str_replace('"', '&quot;', $articleTitle);
							$strRelatedArticlesResult .= "<li><a href='".$articleURL."' title='".$articleTitle."'>".$articleTitle."</a></li>";
							
						}
					}
				}
				
			}
		}
		
		if (!empty($strRelatedArticlesResult && $numRelatedArticles >= 3)){
			$strCachedFile = '<div class="moduletable-sidebar"><h3>Related Articles</h3><ul>'.$strRelatedArticlesResult.'</ul></div>';
		}
	}
	
	if (!file_exists(JPATH_CACHE.'/related-articles/')) {
	    mkdir(JPATH_CACHE.'/related-articles/', 0755, true);
	}
	
	file_put_contents($relatedArticlesProcessedFile, $strCachedFile);
	die($strCachedFile);
}

	?>
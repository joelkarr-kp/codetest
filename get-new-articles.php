<?php
	
//allow only the Arizona IP to access this server
/*if ($_SERVER['REMOTE_ADDR'] != '50.28.99.4')
	die('unauthorized...');*/


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

//first get the last 500 articles
$db = JFactory::getDbo();
$sql = "SELECT `#__content`.`id`, `#__content`.`title`, `#__categories`.`title` AS `category_name`, `catid`, `publish_up`, `state`, `#__article_keywords`.`originalkeywords` , `#__article_keywords`.`mixedkeywords`, 'BHR' AS `source`  FROM `#__content`, `#__categories`, `#__article_keywords` WHERE `#__content`.`catid` = `#__categories`.`id` AND `#__content`.`id` = `#__article_keywords`.`aid` AND `#__content`.`id` > '233800' ORDER BY `#__content`.`id` DESC LIMIT 0, 500";
$db->setQuery($sql);
$arrArticleList = $db->loadAssocList();
$rootURL = rtrim(JURI::Root(),'/');

for ($i = 0; $i < count($arrArticleList); $i++){
	$articleURL = $rootURL.JRoute::_(ContentHelperRoute::getArticleRoute($arrArticleList[$i]['id'], $arrArticleList[$i]['catid']));
	$arrArticleList[$i]['url'] = $articleURL;
}

die(json_encode($arrArticleList));
?>
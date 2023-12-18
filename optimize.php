<?php
if ($_GET['hash'] != 'YYjcIlavn932L4IP')
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



$db = JFactory::getDbo();
$sql = "DELETE FROM #__session WHERE guest='1'";
$db->setQuery($sql);
$db->execute();

$sql = "TRUNCATE TABLE `#__session`";
$db->setQuery($sql);
$db->execute();

$sql = "DELETE FROM #__assets WHERE `name` LIKE '%com_content.article.%'";
$db->setQuery($sql);
$db->execute();

$sql = "DELETE FROM #__assets WHERE `name` LIKE '%#__ucm_content.%'";
$db->setQuery($sql);
$db->execute();

$sql = "OPTIMIZE TABLE `#__assets`";
$db->setQuery($sql);
$db->execute();

$sql = "DELETE FROM #__content_frontpage WHERE `ordering` > 1000";
$db->setQuery($sql);
$db->execute();

$sql = "OPTIMIZE TABLE `#__content_frontpage`";
$db->setQuery($sql);
$db->execute();

/* Get the archived whitepapers and store the introtext in a file - this file will later be used by the whitepapers widget to exclude archived whitepapers */
$strArchivedWhitePapers = file_get_contents ('https://www.beckershospitalreview.com/whitepapers/archives.html');
$whitepaperFile = JPATH_CACHE.'/whitepapers-archived.cache';
file_put_contents($whitepaperFile, $strArchivedWhitePapers);


die('Everything is optimized...');
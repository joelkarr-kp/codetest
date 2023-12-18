<?php
if ($_GET['hash'] != 'YYjcIlavn932L4IP')
	die('wrong hash');

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

//first get all the metakeywords from all the articles

//This function will check if a metakey field is a real metakey
function isRealMeta($strData){
	$strData = trim($strData);
	if (empty($strData))
		return false;
	$arrData = explode(',', $strData);
	for ($i = 0; $i < count($arrData); $i++){
		$arrSingleData = explode(' ', $arrData[$i]);
		if (count($arrSingleData) > 7) //a keyword cannot consist of more than 7 words
			return false;
	}
	return true;
}

function insertArticleKeywords($aid, $mid, $occurrences, $db){
	$sql = "INSERT INTO `#__article_keyword` (`id`, `aid`, `mid`, `occurrences`) VALUES (NULL, '".$aid."', '".$mid."', '".$occurrences."');";
	$db->setQuery($sql);
	$db->execute();
}

function getAllMetatags($db){
	$arrCommonWords = array('a','ability','able','about','above','accept','according','account','across','act','action','activity','actually','add','address','admit','adult','affect','after','again','against','age','agency','agent','ago','agree','ahead','air','all','allow','almost','alone','along','already','also','although','always','american','among','amount','and','animal','another','answer','any','anyone','anything','appear','apply','approach','area','argue','arm','around','arrive','art','article','artist','as','ask','assume','at','attack','attention','audience','author','authority','available','avoid','away','baby','back','bad','bag','ball','bank','bar','base','be','beat','beautiful','because','become','before','begin','behavior','behind','believe','benefit','best','better','between','beyond','big','billion','bit','black','blue','board','body','book','born','both','box','boy','break','bring','brother','build','building','business','but','buy','by','call','can','capital','car','card','care','career','carry','case','catch','cause','center','central','century','certain','certainly','chair','challenge','chance','change','character','charge','check','child','choice','choose','citizen','city','civil','claim','class','clear','clearly','close','coach','cold','collection','color','come','commercial','common','community','company','compare','computer','concern','condition','consider','contain','continue','cost','could','country','couple','course','cover','create','crime','cultural','culture','cup','current','customer','cut','dark','data','daughter','day','deal','debate','decade','decide','decision','deep','defense','degree','describe','design','despite','detail','determine','develop','development','die','difference','different','difficult','dinner','direction','director','discover','discuss','discussion','do','doctor','dog','door','down','draw','dream','drive','drop','drug','during','each','early','east','easy','eat','edge','education','effect','effort','eight','either','election','else','employee','end','energy','enjoy','enough','enter','entire','especially','establish','even','evening','ever','every','everybody','everyone','everything','evidence','exactly','example','exist','expect','experience','expert','explain','eye','face','fact','factor','fail','fall','family','far','fast','father','fear','feel','feeling','few','field','fight','figure','fill','film','final','finally','financial','find','fine','finger','finish','fire','firm','first','fish','five','floor','fly','focus','follow','food','foot','for','force','foreign','forget','form','former','forward','four','free','friend','from','front','full','fund','future','game','garden','gas','general','get','girl','give','glass','go','goal','good','great','ground','group','grow','growth','guess','guy','hair','half','hand','hang','happen','happy','hard','have','he','head','health','hear','heart','heat','heavy','help','her','here','herself','high','him','himself','his','history','hit','hold','home','hope','hospital','hot','hotel','hour','house','how','however','huge','human','hundred','husband','i','idea','identify','if','image','imagine','impact','important','improve','in','include','including','increase','indeed','indicate','individual','industry','information','inside','instead','interest','interesting','interview','into','involve','issue','it','item','its','itself','job','join','just','keep','key','kid','kill','kind','kitchen','know','knowledge','land','language','large','last','late','later','laugh','law','lay','lead','leader','learn','least','leave','left','leg','legal','less','let','letter','level','lie','life','light','like','likely','line','list','listen','little','live','local','long','look','lose','loss','lot','love','low','machine','magazine','main','maintain','major','majority','make','man','manage','management','manager','many','marriage','material','matter','may','maybe','me','mean','measure','media','medical','meet','meeting','member','memory','mention','message','method','middle','might','million','mind','minute','miss','mission','model','modern','moment','money','month','more','morning','most','mother','mouth','move','movement','movie','mr','mrs','much','music','must','my','myself','name','nation','natural','nature','near','nearly','necessary','need','network','never','new','news','newspaper','next','nice','night','no','none','nor','north','not','note','nothing','notice','now','number','occur','of','off','offer','office','officer','often','oh','oil','ok','old','on','once','one','only','onto','open','operation','opportunity','option','or','order','organization','other','others','our','out','outside','over','own','owner','page','pain','painting','paper','parent','part','participant','particular','particularly','partner','party','pass','past','pattern','pay','peace','people','per','perform','performance','perhaps','period','person','personal','phone','physical','pick','picture','piece','place','plan','plant','play','player','pm','point','police','policy','political','politics','poor','popular','population','position','positive','possible','power','practice','prepare','present','president','pressure','pretty','prevent','price','private','probably','problem','process','produce','product','production','professional','professor','program','project','property','protect','prove','provide','public','pull','purpose','push','put','quality','question','quickly','quite','race','radio','raise','range','rate','rather','reach','read','ready','real','reality','realize','really','reason','receive','recent','recently','recognize','record','red','reduce','reflect','region','relate','relationship','remain','remember','remove','report','represent','require','research','resource','respond','response','responsibility','rest','result','return','reveal','rich','right','rise','risk','road','rock','role','room','rule','run','safe','same','save','say','scene','school','science','scientist','score','sea','season','seat','second','section','see','seek','seem','sell','send','senior','sense','series','serious','serve','service','set','seven','several','sex','sexual','shake','share','she','shoot','short','shot','should','shoulder','show','side','sign','significant','similar','simple','simply','since','sing','single','sister','sit','site','situation','six','size','skill','skin','small','smile','so','social','society','soldier','some','somebody','someone','something','sometimes','son','song','soon','sort','sound','source','south','southern','space','speak','special','specific','speech','spend','sport','spring','staff','stage','stand','standard','star','start','state','statement','station','stay','step','still','stock','stop','store','story','strategy','street','strong','structure','student','study','stuff','style','subject','success','successful','such','suddenly','suffer','suggest','summer','support','sure','surface','system','table','take','talk','task','teach','teacher','team','technology','television','tell','ten','tend','term','test','than','thank','that','the','their','them','themselves','then','theory','there','these','they','thing','think','third','this','those','though','thought','thousand','threat','three','through','throughout','throw','thus','time','to','today','together','tonight','too','top','total','tough','toward','town','trade','traditional','travel','treat','treatment','tree','trial','trip','trouble','true','truth','try','turn','tv','two','type','under','understand','unit','until','up','upon','us','use','usually','value','various','very','victim','view','visit','voice','vote','wait','walk','wall','want','war','watch','water','way','we','weapon','wear','week','weight','well','west','western','what','whatever','when','where','whether','which','while','white','who','whole','whom','whose','why','wide','wife','will','win','wind','window','wish','with','within','without','woman','wonder','word','work','worker','world','worry','would','write','writer','wrong','yard','yeah','year','yes','yet','you','young','your','yourself');
	
	
	$sql = "SELECT `id`, `title` FROM `#__metatags` WHERE `occurrences` < 10000 AND `occurrences` > 100 AND `exclude` = 0 ORDER BY `title` ASC";
	$db->setQuery($sql);
	$arrMeta = $db->loadAssocList();
	
	$arrFilteredMeta = array();
	for ($i = 0; $i < count($arrMeta); $i++){
		$arrMeta[$i]['title'] = strtolower($arrMeta[$i]['title']);
		if (in_array ($arrMeta[$i]['title'], $arrCommonWords) === FALSE)
			$arrFilteredMeta[] = $arrMeta[$i];
	}
	
	
	return ($arrFilteredMeta);
}


function generateAllArticleKeywords($db){
	$sql = "SELECT MAX(`aid`) FROM `#__article_keyword`";
	$db->setQuery($sql);
	$currentId = $db->loadResult();
	$currentId = (int)$currentId;
	
	$arrMeta = getAllMetatags($db);
	
	$sql = "Select `id`, `metakey` FROM `#__content`  WHERE `id` > ".$currentId." ORDER BY `id` ASC LIMIT 0, 8000";
	$db->setQuery($sql);
	$arrArticle = $db->loadAssocList();
	
	for ($i = 0; $i < count($arrArticle); $i++){
		generateSingleArticleKeywords($arrArticle[$i]['id'], $arrMeta, $db);
	}
}



/*This function generates and inserts the article keywords of a single article */
function generateSingleArticleKeywords($id, $arrMeta, $db){
	$sql = "SELECT `introtext` FROM `#__content` WHERE `id` = '".$id."'";
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
			insertArticleKeywords($id, $arrMeta[$i]['id'], $numOccurrences, $db);
		}
	}
}


/*this function will get the keywords of a certain article.*/
function calculateArticleKeywords($id, $db){
	$sql = "Select `metakey` FROM `#__content`  WHERE `id` ='".$id."'";
	$db->setQuery($sql);
	$originalKeywords = $db->loadResult();
	if (!isRealMeta($originalKeywords))
		$originalKeywords = '';
	
	//first get the metatags for the article
	$sql = "SELECT `mid`, `occurrences` FROM `#__article_keyword` WHERE `aid`= '".$id."'";
	$db->setQuery($sql);
	$arrArticleMeta = $db->loadAssocList();
	$arrKeywords = array();
	
	
	//now get all the articles which id is < $id - 1000 and that have the same meta articles
	for ($i = 0; $i < count($arrArticleMeta); $i++){
		//this is the mid (the metatag id)
		$mid = $arrArticleMeta[$i]['mid'];
		
		//this is the number of occurences of that keyword in the article (which is a positive)
		$numOccurrencesArticle = $arrArticleMeta[$i]['occurrences'];
		
		//let us get get the number of occurrences in the metatags table (this is a negative)
		$sql = "SELECT `occurrences`, `title` FROM `#__metatags` WHERE `id`= '".$mid."'";
		$db->setQuery($sql);
		$arrSingleMetaTag = $db->loadAssoc();
		
		$numOccurrencesMeta = $arrSingleMetaTag['occurrences'];
		$strMetatagTitle = strtolower($arrSingleMetaTag['title']);
		
		//now let us loop through the matching articles
		$weight = (10 * $numOccurrencesArticle)/$numOccurrencesMeta;
		
		$arrKeywords[$strMetatagTitle] = $weight;
		
	}
	
	
	arsort ($arrKeywords);
	$arrFullKeywords = $arrKeywords;
	$arrKeywords = array_slice($arrKeywords, 0, 10); //get only the first 10 keywords
	
	
	$strKeywords = implode(', ', array_keys($arrKeywords));
	
	$mixedKeywords = '';
	
	$arrOriginalKeywords = explode(',', $originalKeywords);
	$arrOriginalKeywordsCleaned = array();
	for ($i = 0; $i < count($arrOriginalKeywords); $i++){
		$currentOriginalKeyword = trim($arrOriginalKeywords[$i]);
		if (!empty($currentOriginalKeyword))
			$arrOriginalKeywordsCleaned[] = $currentOriginalKeyword;
	}
	$arrOriginalKeywordsCleaned = array_slice($arrOriginalKeywordsCleaned, 0, 10); //get a maximum of 10 elements
	
	$strOriginalKeywords = implode(', ', $arrOriginalKeywordsCleaned);
	
	$remainingNumberOfKeywords = 10 - count($arrOriginalKeywordsCleaned);
	
	$arrComplementingKeywords = array();
	foreach ($arrFullKeywords as $key=>$value){
		if (!in_array (trim($key), $arrOriginalKeywordsCleaned))
			$arrComplementingKeywords[] = $key;
	}
	
	$arrComplementingKeywords =  array_slice($arrComplementingKeywords, 0, $remainingNumberOfKeywords);
	$arrMixedKeywords = array_merge($arrOriginalKeywordsCleaned, $arrComplementingKeywords);
	$mixedKeywords = implode(', ', $arrMixedKeywords);
	$mixedKeywords = str_replace('  ', ' ', $mixedKeywords);


	
	$strKeywords = preg_replace('/[^[:print:]]/', '', $strKeywords);
	$strKeywords = substr($strKeywords, 0, 512);
	$strOriginalKeywords = preg_replace('/[^[:print:]]/', '', $strOriginalKeywords);
	$strOriginalKeywords = substr($strOriginalKeywords, 0, 512);
	$mixedKeywords = preg_replace('/[^[:print:]]/', '', $mixedKeywords);
	$mixedKeywords = substr($mixedKeywords, 0, 512);
	
	
	//now let us insert in the database
	$sql = "INSERT INTO `#__article_keywords` (`id`, `aid`, `keywords`, `originalkeywords`, `mixedkeywords`) VALUES (NULL, '".$id."', '".addslashes($strKeywords)."', '".addslashes($strOriginalKeywords)."', '".addslashes($mixedKeywords)."');";
	
	$db->setQuery($sql);
	$db->execute();
}



//generate recommended articles for all articles
function calculateArticlesKeywords($db){
	$sql = "SELECT MAX(`aid`) FROM `#__article_keywords`";
	$db->setQuery($sql);
	$currentId = $db->loadResult();
	
	$sql = "SELECT MAX(`id`) FROM `#__content`";
	$db->setQuery($sql);
	$currentArticleId = $db->loadResult();
	
	if (is_numeric($currentId))
		$currentId = $currentId + 1;
	else
		$currentId = 0;
	
	for ($i = $currentId; $i <= $currentArticleId; $i++){
		$arrRecommended = calculateArticleKeywords($i, $db);
	}
}


function calculateArticleOriginalKeywordsEmpty($id, $db){
	$sql = "Select `metakey` FROM `#__content`  WHERE `id` ='".$id."'";
	$db->setQuery($sql);
	$originalKeywords = $db->loadResult();
	if (!isRealMeta($originalKeywords)){
		$originalKeywords = '';
		return;
	}
	
	$arrOriginalKeywords = explode(',', $originalKeywords);
	$arrOriginalKeywordsCleaned = array();
	for ($i = 0; $i < count($arrOriginalKeywords); $i++){
		$currentOriginalKeyword = trim($arrOriginalKeywords[$i]);
		if (!empty($currentOriginalKeyword))
			$arrOriginalKeywordsCleaned[] = $currentOriginalKeyword;
	}
	$arrOriginalKeywordsCleaned = array_slice($arrOriginalKeywordsCleaned, 0, 10); //get a maximum of 10 elements
	$remainingNumberOfKeywords = 10 - count($arrOriginalKeywordsCleaned);
	
	$sql = "SELECT `#__article_keywords`.`keywords` FROM `#__article_keywords` WHERE `#__article_keywords`.`aid` = '".$id."'";
	$db->setQuery($sql);
	$strKeywords = $db->loadResult();
	$arrKeywords = explode(', ', $strKeywords);
	$arrKeywords =  array_slice($arrKeywords, 0, $remainingNumberOfKeywords);
	$arrMixedKeywords = array_merge($arrOriginalKeywordsCleaned, $arrKeywords);
	$arrMixedKeywords = array_unique($arrMixedKeywords);
	$strMixedKeywords = implode(', ', $arrMixedKeywords);
	
	$strOriginalKeywords = implode(', ', $arrOriginalKeywordsCleaned);
	
	//now let us insert in the database
	$sql = "UPDATE `#__article_keywords` SET `originalkeywords` = '".addslashes($strOriginalKeywords)."', `mixedkeywords`= '".addslashes($strMixedKeywords)."' WHERE `aid`='".$id."'";
	
	$db->setQuery($sql);
	$db->execute();
}


//this function is added to handle the case where the original keywords field is empty because the author first saved the article without the meta tags and later added the meta tags
function calculateArticlesOriginalKeywordsEmpty($db){
	$sql = "SELECT `#__article_keywords`.`aid` FROM `#__article_keywords`, `#__content` WHERE `#__article_keywords`.`aid` = `#__content`.`id` AND `#__content`.`metakey` != '' AND `#__article_keywords`.`originalkeywords` = '' AND `#__content`.`created` >= DATE(NOW() - INTERVAL 2 DAY)";
	$db->setQuery($sql);
	$arrIds = $db->loadColumn();
	
	for ($i = 0; $i <= count($arrIds); $i++){
		$arrRecommended = calculateArticleOriginalKeywordsEmpty($arrIds[$i], $db);
	}
}



$db = JFactory::getDbo();

generateAllArticleKeywords($db);
calculateArticlesKeywords($db);
calculateArticlesOriginalKeywordsEmpty($db);

die('done...');
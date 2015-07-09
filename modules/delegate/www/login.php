<?php
/**
 * actual login page for Delegate
 *
 * @author Mathias Meisfjordskar.
 * @package Delegate
 * @version $Id:
 */
$globalConfig = SimpleSAML_Configuration::getInstance();

/* Find the authentication state. */
if (!array_key_exists('AuthState', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing mandatory parameter: AuthState');
}

/* Retrieve the authentication state. */
$state = SimpleSAML_Auth_State::loadState($_REQUEST['AuthState'], 'delegate:login_form');
$session = SimpleSAML_Session::getInstance();

$authSource = SimpleSAML_Auth_Source::getById($state['delegate:AuthId']);
if ($authSource === NULL) {
	throw new SimpleSAML_Error_BadRequest('Invalid AuthId "' . $state['delegate:AuthId'] . '" - not found.');
}
$cookieName = 'backend_' . $state['delegate:AuthId'];

$username = array_key_exists('username', $_REQUEST) ? $_REQUEST['username'] : '';
$password = array_key_exists('password', $_REQUEST) ? $_REQUEST['password'] : '';
$spMetadata = array_key_exists('SPMetadata', $state) ? $state['SPMetadata'] : array();

$recentBackend = sspmod_delegate_Common::getBackendCookie();
$backends = sspmod_delegate_Common::getBackends();

if (strpos($username, '@') !== FALSE) {
	list($username, $backend) = explode('@', $username, 2);
	SimpleSAML_Logger::info('Using backend "' . $backend . '" from username.');
} elseif (array_key_exists('backend', $_REQUEST)) {
	$backend = $_REQUEST['backend'];
	SimpleSAML_Logger::info('Using backend "' . $backend . '" from request.');
} elseif (!empty($recentBackends)) {
	$backend = $recentBackends[0];
	SimpleSAML_Logger::info('Using backend "' . $backend . '" from cookie.');
} else {
	$tmp = reset($backends);
	$backend = $tmp->getAuthId();
	SimpleSAML_Logger::info('No selected backend for user. Setting "'. $backend .'"');
}

$error = null;

if (!empty($username) && isset($_REQUEST['login'])) {

	try {
		// This function will not return on a valid login. On an error, it
		// will throw an exception.
		$authSource->login($state, $username, $password, $backend);
	} catch (SimpleSAML_Error_User $e) {
		$error = 'error_user';
	} catch (SimpleSAML_Error_AuthSource $e) {
		$error = 'error_external';
	} catch (Exception $e) {
		$error = 'error_internal';
		SimpleSAML_Logger::error(
			'AUTH-' . $state['delegate:AuthId']
			. '(User:' . $username . ')'
			. '(ExType:' . get_class($e) . ')'
			. '(Message:' . $e->getMessage() . ')'
		);
		SimpleSAML_Logger::stats('AUTH-' . $state['delegate:AuthId'] . ' Failed');
	}
} elseif (isset($_REQUEST['forward'])) {
	$authSource->forward($state, $_REQUEST['forward']);
	assert('false');
}

$backendData = array();
foreach (sspmod_delegate_Common::getAllowedBackendNames($spMetadata) as $be) {
    $backendData[] = sspmod_delegate_SourceData::createFromAuthId($be);
}
$idpData = array();
foreach (sspmod_delegate_Common::getRemoteIdpsEnabledSp($spMetadata) as $idp) {
    $idpData[] = sspmod_delegate_SourceData::createFromMetadata($idp);
}
$suppData = array();
foreach (sspmod_delegate_Common::getSupplementary($spMetadata) as $supp) {
    $suppData[] = sspmod_delegate_SourceData::createFromArray($supp);
}


$info = array();
$hookinfo = array('info' => &$info);
SimpleSAML_Logger::debug('Delegate - login: Calling hooks');
SimpleSAML_Module::callHooks('loginpage', $hookinfo);

$t = new SimpleSAML_XHTML_Template($globalConfig, 'delegate:login.php', 'login');
$t->data['error'] = $error;
$t->data['backend'] = $backend;
$t->data['backends'] = $backends;
$t->data['trackid'] = $session->getTrackID();
$t->data['sp'] = $spMetadata;
$t->data['hookinfo'] = $hookinfo;
$t->data['state'] = array('AuthState' => $_REQUEST['AuthState']);

$t->data['backends'] = $backendData;
$t->data['idps'] = $idpData;
$t->data['supp'] = $suppData;

$t->show();
exit();

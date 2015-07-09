<?php
/**
 * Handles requests where the user is authenticated with an invalid backend
 *
 * @package ModuleDelegate
 * @author  TODO <foo@bar.no>
 * @version $Id$
 */

$globalConfig = SimpleSAML_Configuration::getInstance();

/* Retrieve the authentication state. */
if (!array_key_exists('AuthState', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing mandatory parameter: AuthState');
}
$state = SimpleSAML_Auth_State::loadState(
	(string)$_REQUEST['AuthState'], 'delegate:no_access_auth'
);

if (isset($_POST['login'])) {
	$jsSupported = isset($_POST['js']) && $_POST['js'] === 'true';

	/* Continue the login operation. */
	$authSource = SimpleSAML_Auth_Source::getById(
		$state['delegate:AuthId'], 'sspmod_delegate_Auth_Source_Delegate'
	);
	$authSource->reauthLogout($state, $jsSupported);
	assert('FALSE');
}

$spMetadata = $state['SPMetadata'];
$backend = $state['delegate:no_access_auth:backend'];
$remote_idp = $state['delegate:no_access_auth:remote_idp'];

$t = new SimpleSAML_XHTML_Template($globalConfig, 'delegate:no_access_auth.php');
$t->data['AuthState'] = (string) $_REQUEST['AuthState'];

$t->data['Backend'] = empty($backend)
	? sspmod_delegate_Common::getMetadataName($remote_idp, 'saml20-idp-remote')
	: sspmod_delegate_Common::getAuthSourceTranslation($backend, 'name');
$t->data['SPName'] = sspmod_delegate_Common::getMetadataName($spMetadata['entityid'], 'saml20-sp-remote');

$t->data['SPContactURL'] = array_key_exists('delegate:contactURL', $spMetadata)
	? $spMetadata['delegate:contactURL'] : NULL;
$t->data['SPContactMail'] = array_key_exists('delegate:contactMail', $spMetadata)
	? $spMetadata['delegate:contactMail'] : NULL;

$t->show();

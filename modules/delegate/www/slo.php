<?php
/**
 * Delegate SingleLogoutService endpoint for upstream IdP
 *
 * @package Delegate
 * @author  Mathias Meisfjordskar, University of Oslo.
 * @author  Fredrik Larsen, University of Oslo.
 * @version $Id:
 */

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

if (!array_key_exists('PATH_INFO', $_SERVER)) {
	throw new SimpleSAML_Error_BadRequest(
		'Missing SP entity id in SingleLogoutService URL'
	);
}
$spEntityId = $idp->decodeEntityId(substr($_SERVER['PATH_INFO'], 1));

$binding = new SAML2_HTTPRedirect();
$message = $binding->receive();

if ($message instanceof SAML2_LogoutResponse) {
	/**
	 * This response always comes from an upstream IdP.
	 *
	 * It should always be a response to a request sent by the
	 * Delegate->logoutUpstream method call, which is initiated by
	 * SimpleSAML_Auth_Simple logout pattern.
	 */
	$issuer = $message->getIssuer();
	SimpleSAML_Logger::debug(
		sprintf('delegate - slo response: issuer=%s target=%s', $issuer, $spEntityId)
	);

	$stateId = $message->getRelayState();
	$state = SimpleSAML_Auth_State::loadState(
		$stateId, 'delegate:Logout:afterbridge'
	);
	SimpleSAML_Auth_Source::completeLogout($state);

	assert('FALSE');
} elseif ($message instanceof SAML2_LogoutRequest) {
	/**
	 * This request always comes from an upstream IdP.
	 *
	 * Requests are sent here when the upstream IdP wants to log out our SPs. It
	 * happens because:
	 *  - one of its direct SPs sent it a logout request
	 *  - the delegate module sent it a logout request on behalf of one of our
	 *    SPs
	 */
	$issuer = $message->getIssuer();
	SimpleSAML_Logger::debug(
		sprintf('delegate - slo request: issuer=%s target=%s', $issuer, $spEntityId)
	);

	$state = array(
		'core:IdP' => 'saml2:' . $idpEntityId,
		'delegate:ID' => $message->getId(),
		'delegate:RelayState' => $message->getRelayState(),
		'delegate:upstream' => $message->getIssuer(),
		'delegate:downstream' => $spEntityId,
	);

	if ($idp->isAuthenticated()) {
		/* We haven't killed off the authenticated session locally. We need to
		 * call logout.
		 */
		SimpleSAML_Logger::debug("delegate - Upstream IdP=$issuer initiated logout");
		$idp->handleUpstreamLogoutRequest($state);
		// Will call forwardLogoutRequestDownstream after logout.
	}

	// We've already logged out
	$idp->forwardLogoutRequestDownstream($state);

	assert('FALSE');
} else {
	throw new SimpleSAML_Error_Exception(
		'Message received on logout endpoint was not a logout message.'
	);
}
?>

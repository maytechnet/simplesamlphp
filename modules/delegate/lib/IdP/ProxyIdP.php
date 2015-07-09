<?php
/**
 * Delegate IdP class
 *
 * @package Delegate
 * @author  Olav Morken, UNINETT AS.
 * @author  Mathias Meisfjordskar, University of Oslo.
 * @author  Fredrik Larsen, University of Oslo.
 * @version $Id:
 */

class sspmod_delegate_IdP_ProxyIdP extends SimpleSAML_IdP
{
	/**
	 * A structure to temporarily store data that should be piggy-backed onto
	 * new associations
	 */
	private $upstreamAssociation = array();


	/**
	 * Encode an SP entityID for use in a URL.
	 *
	 * @param string $spEntityId The SP entity ID.
	 *
	 * @return string The encoded SP entity ID.
	 */
	public static function encodeEntityId($spEntityId)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP::encodeEntityId');
		assert('is_string($spEntityId)');

		// Here we just use url-base64-encoding. Could be replaced with an "SP id"?
		$ret = base64_encode($spEntityId);
		$ret = strtr($ret, '+/', '-_');
		$ret = rtrim($ret, '=');
		return $ret;
	}


	/**
	 * Decode an SP entityID from an URL.
	 *
	 * @param string $spEntityId The SP entity ID.
	 *
	 * @return string  The encoded SP entity ID.
	 */
	public static function decodeEntityId($spEntityId)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP::decodeEntityId');
		assert('is_string($spEntityId)');

		$ret = $spEntityId;
		while ( (strlen($ret) % 4) != 0) {
			$ret .= '=';
		}
		$ret = strtr($ret, '-_', '+/');
		$ret = base64_decode($ret);
		return $ret;
	}


	/**
	 * Get *our* (generated) SP metadata for the given downstream SP.
	 *
	 * @param string $spEntityId EntityID of the downstream SP.
	 *
	 * @return SimpleSAML_Configuration Generated metadata for our SP.
	 */
	public static function getSPProxy($spEntityId)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP::getSPProxy');
		$metadata = array(
			'entityid' => SimpleSAML_Module::getModuleURL(
				'delegate/metadata.php/' . self::encodeEntityId($spEntityId)
			),
		);
		return SimpleSAML_Configuration::loadFromArray($metadata);
	}


	/**
	 * Prepare remote IdP info for addAssociation
	 *
	 * @param string $idp    The entityId of the upstream IdP
	 * @param string $sp     The entityId of the associated SP
	 * @param string $nameId The SP NameID for a future logout request
	 * @param string $index  The SessonIndex for a future logout request
	 *
	 * @return void
	 */
	private function prepareUpstreamAssociation($idp, $sp, $nameId, $index)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->prepareUpstreamAssociation');
		assert('is_string($idp)');
		assert('is_string($sp)');
		$this->upstreamAssociation[$sp] = array(
			'delegate:idp' => $idp,
			'delegate:sp' => $sp,
			'delegate:nameid' => $nameId,
			'delegate:index' => $index,
			'delegate:forwarded' => true,
		);
	}


	/**
	 * Add an SP association.
	 *
	 * @param array $association The SP association.
	 *
	 * @return void
	 */
	public function addAssociation(array $association)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->addAssociation');
		assert('isset($association["saml:entityID"])');
		if (array_key_exists($association['saml:entityID'], $this->upstreamAssociation)) {
			// Add delegate upstream info to the assocaition.
			$association = array_merge(
				$association,
				$this->upstreamAssociation[$association['saml:entityID']]
			);
			unset($this->upstreamAssociation[$association['saml:entityID']]);
		}
		parent::addAssociation($association);
	}


	/**
	 * Get an SP association by id
	 *
	 * @param string $id The association ID.
	 *
	 * @return array|null The association, or null if it isn't found.
	 */
	public function getAssociation($id)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->getAssociation(id='.$id.')');
		$associations = $this->getAssociations();
		if (!isset($associations[$id])) {
			return null;
		}
		return $associations[$id];
	}


	/**
	 * Fetch an association with delegate upstream info, if any.
	 *
	 * @return string|null The upstream IdP or null.
	 */
	public function getUpstreamAssociation()
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->getUpstreamAssociation');
		foreach ($this->getAssociations() as $assoc) {
			if (isset($assoc['delegate:forwarded']) && $assoc['delegate:forwarded']) {
				return $assoc;
			}
		}
		return null;
	}


	/**
	 * Check whether we are handling a request that we can forward.
	 *
	 * @param array $state The state array.
	 *
	 * @return bool TODO
	 */
	public function canForwardRequest(array &$state)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->canForwardRequest');
		if (!isset($state['Responder'])) {
			return false;
		}
		if ($state['Responder'] !== array('sspmod_saml_IdP_SAML2', 'sendResponse')) {
			return false;
		}
		return true;
	}


	/**
	 * Process authentication requests.
	 *
	 * @param array $state The authentication request state.
	 *
	 * @return void Never returns
	 */
	public function handleAuthenticationRequest(array &$state)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->handleAuthenticationRequest');

		// Can't forward?
		if (!$this->canForwardRequest($state)) {
			return parent::handleAuthenticationRequest($state);
		}

		// No upstream associations?
		$association = $this->getUpstreamAssociation();
		if (is_null($association)) {
			return parent::handleAuthenticationRequest($state);
		}

		$idp_name = $association['delegate:idp'];

		// Autoforward not enabled?
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idp_meta = $metadata->getMetaData($idp_name, 'saml20-idp-remote');
		if (!array_key_exists('delegate:enable_autoforwarding', $idp_meta)
			|| $idp_meta['delegate:enable_autoforwarding'] === false
		) {
			return parent::handleAuthenticationRequest($state);
		}

		$state['core:IdP'] = $this->id;
		$this->forwardAuthRequest($state, $idp_name);

		assert('false');
	}


	/**
	 * Complete the logout.
	 *
	 * @param array $state The logout state
	 *
	 * @return void Never returns, will call the Responder callback.
	 */
	public function finishLogout(array &$state)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->finsishLogout");
		assert('isset($state["core:IdP"])');
		assert('isset($state["Responder"])');

		$idp = SimpleSAML_IdP::getByState($state);
		SimpleSAML_Logger::debug(
			'delegate: ProxyIdP::finishLogout calling Responder='
			. var_export($state['Responder'], true)
		);
		call_user_func($state['Responder'], $idp, $state);
		assert('false');
	}


	/**
	 * Set logout state for association.
	 *
	 * This function will add information we've added to our associations to the
	 * logout state, if there's an upstream IdP to log out.
	 *
	 * @param array $association The association to log out.
	 *
	 * @return null Sets the session logout state and returns
	 */
	public static function updateLogoutState(array $association)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->updateLogoutState");
		$session = SimpleSAML_Session::getInstance();
		$logoutState = $session->getLogoutState();

		if (isset($association['delegate:forwarded'])
			&& $association['delegate:forwarded']
		) {
			// TODO: Handle Expire -- if the upstream session is expired,
			// there's no need for logout
			$proxy = self::getSPProxy($association['delegate:sp']);

			$upstreamLogout = array(
				'subject' => $association['delegate:idp'],
				'issuer' => $proxy->getstring('entityid'),
				'NameID' => $association['delegate:nameid'],
			);
			if (isset($association['delegate:index'])) {
				$upstreamLogout['SessionIndex'] = $association['delegate:index'];
			}
			$logoutState['delegate:logout:upstream'] = $upstreamLogout;

		}
		if (!empty($logoutState)) {
			$session->setLogoutState($logoutState);
		}
	}


	/**
	 * Process a logout request.
	 *
	 * @param array       $state   The logout request state.
	 * @param string|null $assocId The association we received the logout
	 *                             request from, or null if there was no
	 *                             association.
	 *
	 * @return void Never returns
	 */
	public function handleLogoutRequest(array &$state, $assocId)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->handleLogoutRequest");
		assert('is_string($assocId) || is_null($assocId)');

		$assoc = $this->getAssociation($assocId);

		assert('!is_null($assoc)');
		$this->updateLogoutState($assoc);

		parent::handleLogoutRequest($state, $assocId);
		assert('false');
	}


	/**
	 * Process a logoutRequest from proxy-slo (from upstream IdP)
	 *
	 * @param array $state The logout request state.
	 *
	 * @return void Never returns
	 */
	public function handleUpstreamLogoutRequest(array &$state)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->handleUpstreamLogoutRequest");
		$state['ReturnCallback'] = array(
			'sspmod_delegate_IdP_ProxyIdP', 'forwardLogoutRequestDownstream'
		);
		$this->authSource->logout($state);
		assert('false');
	}


	/**
	 * Initiate a logout
	 *
	 * @param array $state The logout request state.
	 *
	 * @return void Never returns
	 */
	public function handleLocalLogoutRequest(array &$state)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->handleLocalLogoutRequest");
		$association = $this->getUpstreamAssociation();

		if (!is_null($association)) {
			$this->setLogoutState($association);
		}

		parent::handleLogoutRequest($state, null);
		assert('false');
	}


	/**
	 * Process a logout response.
	 *
	 * @param string                          $assocId    The association that is terminated.
	 * @param string|null                     $relayState The RelayState from the start of the logout.
	 * @param SimpleSAML_Error_Exception|null $error      The error that occurred during session termination (if any).
	 *
	 * @return void Never returns
	 */
	public function handleLogoutResponse($assocId, $relayState, SimpleSAML_Error_Exception $error = null)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->handleLogoutResponse");

		// Is this a response for a forwarded request?
		$this->tryForwardLogoutResponseUpstream($assocId, $relayState, $error);

		// If not, it's a response for a request that this IdP has generated.
		parent::handleLogoutResponse($assocId, $relayState, $error);
		assert('false');
	}


	/**
	 * Forward this authentication request to the upstream IdP.
	 *
	 * @param array  $state    The authentication request state.
	 * @param string $idp_name EntityId for the upstream IdP
	 *
	 * @return void Never returns
	 */
	public function forwardAuthRequest(array $state, $idp_name)
	{
		SimpleSAML_Logger::debug("delegate: ProxyIdP->forwardAuthRequest");

		if (!$this->canForwardRequest($state)) {
			/* Apparently not a SAML 2.0 authentication request. */
			throw new Exception('Attempted to forward a non-saml2.0 authentication request.');
		}

		assert('isset($state["SPMetadata"]["entityid"])');
		$entityId = $state['SPMetadata']['entityid'];

		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$upstream = $metadataHandler->getMetaDataConfig($idp_name, 'saml20-idp-remote');

		$session = SimpleSAML_Session::getInstance();

		$id = SimpleSAML_Auth_State::saveState($state, 'delegate:forwarded-request', true);

		/* Build our authentication request. */
		$ar = new SAML2_AuthnRequest();
		$ar->setId($id);

		$issuer = SimpleSAML_Module::getModuleURL(
			'delegate/metadata.php/' . $this->encodeEntityId($entityId)
		);
		$ar->setIssuer($issuer);

		$dst = $upstream->getDefaultEndpoint(
			'SingleSignOnService', array(SAML2_Const::BINDING_HTTP_REDIRECT)
		);
		$ar->setDestination($dst['Location']);

		if (isset($state['saml:RelayState'])) {
			$ar->setRelayState($state['saml:RelayState']);
		}

		$nameIdPolicy = array();
		if (isset($state['saml:NameIDFormat'])) {
			$nameIdPolicy['Format'] = $state['saml:NameIDFormat'];
		}
		if (isset($state['saml:AllowCreate'])) {
			$nameIdPolicy['AllowCreate'] = $state['saml:AllowCreate'];
		}
		$ar->setNameIdPolicy($nameIdPolicy);

		$ar->setForceAuthn($state['ForceAuthn']);
		$ar->setIsPassive($state['isPassive']);

		/* Send the authentication request. */
		$b = new SAML2_HTTPRedirect();
		$b->send($ar);
		assert('false');
	}


	/**
	 * Forward an authentication response that is recieved on the acs endpoint
	 *
	 * @param string $spEntityId        The entityId of the target SP
	 * @param string $remoteIdpEntityId The entityId of the upstream IdP
	 *
	 * @return void Never returns
	 */
	public function forwardAuthResponse($spEntityId, $remoteIdpEntityId)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->forwardAuthResponse');
		assert('is_string($spEntityId)');
		assert('is_string($remoteIdpEntityId)');

		$spProxy = $this->getSPProxy($spEntityId);

		// Should this not be done by www/acs.php?
		$binding = new SAML2_HTTPPost();
		$response = $binding->receive();
		if (!($response instanceof SAML2_Response)) {
			throw new Exception('Message received on ACS endpoint is not a saml:Response.');
		}

		$stateId = $response->getInResponseTo();
		$state = null;
		if ($stateId !== null) {
			$state = SimpleSAML_Auth_State::loadState($stateId, 'delegate:forwarded-request');
		} else {
			throw new Exception("Missing state in saml:Response");
		}

		try {
			$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
			$upstream = $metadataHandler->getMetaDataConfig(
				$remoteIdpEntityId, 'saml20-idp-remote'
			);
			$assertions = sspmod_saml_Message::processResponse($spProxy, $upstream, $response);
		} catch (sspmod_saml_Error $e) {
			/* The status of the response wasn't "success". */
			$e = $e->toException();
			SimpleSAML_Auth_State::throwException($state, $e);
		}

		/* The following code assumes that the upstream IdP is sane. */
		assert('count($assertions) === 1');
		$assertion = $assertions[0];

		/* Prepare NameID */
		$nameId = $assertion->getNameId();
		assert('$nameId !== null');
		assert('$nameId["Format"] !== null');
		$nameIdFmt = $nameId['Format'];

		/* Mark the session as acting as a proxy. */
		$this->prepareUpstreamAssociation(
			$remoteIdpEntityId, $spEntityId, $nameId,
			$assertion->getSessionIndex()
		);

		$state['saml:AuthnContextClassRef'] = $assertion->getAuthnContext();
		$state['AuthnInstant'] = $assertion->getAuthnInstant();
		$state['saml:NameIDFormat'] = $nameIdFmt;
		$state['saml:NameID'] = array($nameIdFmt => $nameId);
		$state['Attributes'] = $assertion->getAttributes();
		$state['saml:sp:SessionIndex'] = $assertion->getSessionIndex();
		$state['saml:sp:IdP'] = $this->id;

		/* Store the login state */
		// TODO: Is this the assocaition data we need for upstream logout?!
		$as = $this->authSource->getAuthSource();
		$session = SimpleSAML_Session::getInstance();
		$session->doLogin(
			$as->getAuthId(),
			SimpleSAML_Auth_Default::extractPersistentAuthState($state)
		);

		// TODO: Is this data stored correctly?
		$session->setData(
			'authenticated:remote_idp', 'delegate', $remoteIdpEntityId,
			SimpleSAML_Session::DATA_TIMEOUT_LOGOUT
		);

		/* We have now extracted the relevant information from the assertion. Send the response. */
		SimpleSAML_IdP::postAuthProc($state);
		assert('false');
	}


	/**
	 * Forward a logout response to a downstream SP.
	 *
	 * @param string               $spEntityId The SP entityID.
	 * @param SAML2_LogoutResponse $lr         The logout response.
	 *
	 * @return void Never returns
	 */
	public function forwardLogoutResponseDownstream($spEntityId, SAML2_LogoutResponse $lr)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->forwardLogoutResponseDownstream');
		assert('is_string($spEntityId)');

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $this->getConfig();
		$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');
		$dst = $spMetadata->getDefaultEndpoint(
			'SingleLogoutService', array(SAML2_Const::BINDING_HTTP_REDIRECT), false
		);
		$new_lr = sspmod_saml_Message::buildLogoutResponse($idpMetadata, $spMetadata);
		$new_lr->setRelayState($lr->getRelayState());
		$new_lr->setInResponseTo($lr->getInResponseTo());
		$new_lr->setDestination($dst['Location']);
		$new_lr->setStatus($lr->getStatus());

		$binding = new SAML2_HTTPRedirect();
		$binding->send($new_lr);
		assert('false');
	}


	/**
	 * Forward a logout request to a downstream SP.
	 *
	 * @param array $state The logout state.
	 *
	 * @return void Never returns
	 */
	public static function forwardLogoutRequestDownstream($state)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP::forwardLogoutRequestDownstream');
		assert('isset($state["core:IdP"])');
		assert('isset($state["delegate:downstream"])');
		// Needed in state on response:
		assert('isset($state["delegate:ID"])');
		assert('isset($state["delegate:RelayState"])');
		assert('isset($state["delegate:upstream"])');

		SimpleSAML_Logger::info('PID: forwardLogoutRequestDownstream');
		$idp = SimpleSAML_IdP::getByState($state);

		$assocId = 'saml:' . $state['delegate:downstream'];
		$association = $idp->getAssociation($assocId);

		if ($association === null) {
			/* Hmmm... No association for this SP. Have we lost our session? */
			$err = new sspmod_saml_Error(
				SAML2_Const::STATUS_RESPONDER, null, 'No association with SP.'
			);
			self::forwardLogoutResponseUpstream($state, $err);
			assert('FALSE');
		}

		$id = SimpleSAML_Auth_State::saveState($state, 'delegate:logoutrequest', true);
		SimpleSAML_Logger::debug(
			'delegate: redirect to getLogoutUrl() from Handler='
			. var_export($association['Handler'], true)
		);
		$url = call_user_func(
			array($association['Handler'], 'getLogoutURL'), $idp, $association, $id
		);
		SimpleSAML_Utilities::redirect($url);
	}


	/**
	 * Try to forward a logout response to the upstream IdP.
	 *
	 * @param string $assocId  The association that is terminated.
	 * @param string|null $relayState  The RelayState from the start of the logout.
	 * @param SimpleSAML_Error_Exception|null $error  The error that occurred during session termination (if any).
	 *
	 * @return null Returns if the association is not an upstream association,
	 *              of if the state is not from an upstream logout request.
	 *              If the response belongs to a logout request from upstream,
	 *              the function will not return.
	 */
	public function tryForwardLogoutResponseUpstream($assocId, $relayState, SimpleSAML_Error_Exception $error = null)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP->tryForwardLogoutResponseUpstream');
		assert('is_string($assocId) || is_null($assocId)');
		if ($assocId === null) {
			return;
		}
		$association = $this->getAssociation($assocId);
		if ($association === null) {
			return;
		}

		if (!isset($association['delegate:forwarded']) || !$association['delegate:forwarded']) {
			return;
		}

		// TODO/FIXME: Is there a better way to check the stage of a stored
		//             state?
		//             We'll always get a WARNING log message here when the
		//             stage is incorrect.
		try {
			$state = SimpleSAML_Auth_State::loadState($relayState, 'delegate:logoutrequest');
		} catch (Exception $e) {
			/* Not correct state type. */
			return;
		}

		/* Everything OK. Forward it upstream. */
		$this->terminateAssociation($assocId);
		$this->forwardLogoutResponseUpstream($state, $error);
		assert('false');
	}


	/**
	 * Forward a logout request to the upstream IdP.
	 *
	 * @param array                  &$state The logout state.
	 * @param sspmod_saml_Error|null $error  The logout error, if occured.
	 *
	 * @return void Never returns
	 */
	private static function forwardLogoutResponseUpstream(array &$state, sspmod_saml_Error $error = null)
	{
		SimpleSAML_Logger::debug('delegate: ProxyIdP::forwardLogoutResponseUpstream');
		assert('isset($state["delegate:downstream"])');
		assert('isset($state["delegate:upstream"])');
		assert('isset($state["delegate:ID"])');

		$spEntityId = $state['delegate:downstream'];
		$idp_name = $state['delegate:upstream'];

		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$upstream = $metadataHandler->getMetaDataConfig(
			$idp_name, 'saml20-idp-remote'
		);
		$spProxy = self::getSPProxy($spEntityId);

		$dst = $upstream->getDefaultEndpoint(
			'SingleLogoutService', array(SAML2_Const::BINDING_HTTP_REDIRECT), false
		);
		assert('$dst !== false'); // We assume that our upstream supports logout.

		$lr = new SAML2_LogoutResponse();
		$lr->setIssuer($spProxy->getString('entityid'));
		$lr->setDestination($dst['Location']);
		$lr->setId($state['delegate:ID']);
		$lr->setRelayState($state['delegate:RelayState']);

		// Attach error if we got an error from the SP
		if ($error !== null) {
			$lr->setStatus(
				array(
					'Code' => $error->getStatus(),
					'SubCode' => $error->getSubStatus(),
					'Message' => $error->getStatusMessage(),
				)
			);
		}

		$b = new SAML2_HTTPRedirect();
		$b->send($lr);
		assert('false');
	}
}

?>

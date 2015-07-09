<?php
/**
 * Delegate core class
 *
 * @package Delegate
 * @author  Olav Morken, UNINETT AS.
 * @author  Mathias Meisfjordskar, University of Oslo.
 * @author  Fredrik Larsen, University of Oslo.
 * @version $Id:
 */

class sspmod_delegate_Auth_Source_Delegate extends SimpleSAML_Auth_Source
{
	/**
	 * Kill off the current authenticated session. This method is called by the 
	 * SimpleSAML_Auth_Simple authsource at logout.
	 *
	 * @param array $params Parameters passed from Auth_Simple->logout
	 *
	 * @return void Returns null if no upstream logout is required. Otherwise
	 *              this method calls logoutUpstream, which never returns.
	 */
	public function logout(array $params)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->logout');
		assert('isset($params["ReturnTo"]) || isset($params["ReturnCallback"])');

		if (!array_key_exists('delegate:logout:upstream', $params)) {
			SimpleSAML_Logger::debug("Delegate - No upstream logout data");
			return;
		}

		// TODO: Check validity. If the upstream assoc is expired, we don't
		// need to request logout from it

		$returnTo = $params['ReturnTo'];
		$upstream = $params['delegate:logout:upstream'];
		unset($params['delegate:logout:upstream']);

		$this->logoutUpstream($params, $upstream);
	}


	/**
	 * Send a logout request to the upstream IdP
	 *
	 * @param array $state    The state before logout
	 * @param array $upstream Information on the upstream IdP and
	 *                        authentication state. This information comes
	 *                        from ProxyIdP::handleLogoutRequest.
	 *
	 * @return void Never returns.
	 */
	private function logoutUpstream(array $state, array $upstream)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->logoutUpstream');
		assert('isset($upstream["subject"])');
		assert('isset($upstream["issuer"])');
		assert('isset($upstream["NameID"])');
		assert('isset($upstream["SessionIndex"])');

		$relayState = SimpleSAML_Auth_State::saveState(
			$state, 'delegate:Logout:afterbridge'
		);

		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$remoteIdP = $metadataHandler->getMetaDataConfig(
			$upstream['subject'], 'saml20-idp-remote'
		);
		$dst = $remoteIdP->getDefaultEndpoint(
			'SingleLogoutService', array(SAML2_Const::BINDING_HTTP_REDIRECT), false
		);
		assert('$dst !== false'); // We assume that our remoteIdP supports logout.

		$lr = new SAML2_LogoutRequest();
		$lr->setRelayState($relayState);
		$lr->setIssuer($upstream['issuer']);
		$lr->setDestination($dst['Location']);
		$lr->setNameId($upstream['NameID']);
		$lr->setSessionIndex($upstream['SessionIndex']);

		SimpleSAML_Logger::debug(
			"delegateLogout - sending logout request to {$upstream['subject']}"
		);
		$b = new SAML2_HTTPRedirect();
		$b->send($lr);
		assert('false');
	}


	/**
	 * Start authentication by sending a user to the logon page.
	 *
	 * @param array $state The logon state
	 *
	 * @return void Never returns
	 */
	public function authenticate(&$state)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->authenticate');
		assert('is_array($state)');

		// Store the current authId, so that we're able to fetch this Auth
		// Source on delegate/login.php
		$state['delegate:AuthId'] = $this->authId;
		$id = SimpleSAML_Auth_State::saveState($state, 'delegate:login_form');

		$url = SimpleSAML_Module::getModuleURL('delegate/login.php');
		$params = array('AuthState' => $id);
		if (array_key_exists('SessionLostURL', $state)) {
			$params['SessionLostURL'] = $state['SessionLostURL'];
		}
		SimpleSAML_Utilities::redirect($url, $params);
	}


	/**
	 * Delegate reauthentication handler.
	 *
	 * @param array $state The authentication state.
	 *
	 * @return void Never returns
	 */
	public function reauthenticate(array &$state)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->reauthenticate');

		/* Retrieve our authentication data from the previous authentication. */
		$session = SimpleSAML_Session::getInstance();
		$data = $session->getAuthState($this->authId);

		/* Is this an authentication request on behalf of an SP? If not, just
		 * use the default handler. */
		if (!isset($state['SPMetadata'])) {
			return parent::reauthenticate($state);
		}

		// TODO: Is this data stored correctly?
		$backend = $session->getData('authenticated:backend', 'delegate');
		$remote_idp = $session->getData('authenticated:remote_idp', 'delegate');

		if (!empty($backend) && empty($remote_idp)) {
			$allowed_backends = sspmod_delegate_Common::getAllowedBackendNames($state['SPMetadata']);

			// Backend allowed?
			if (sspmod_delegate_Common::isAllowedBackend($backend, $state['SPMetadata'])) {
				SimpleSAML_Logger::debug(
					"delegate: reauthenticate - backend is enabled for SP"
				);
				return parent::reauthenticate($state);
			}

			if (array_key_exists('delegate:fallback', $state['SPMetadata'])
				&& array_key_exists($backend, $state['SPMetadata']['delegate:fallback'])
			) {
				SimpleSAML_Logger::debug(
					"delegate: reauthenticate - backend has fallback"
				);
				return parent::reauthenticate($state);
			}
		} elseif (!empty($remote_idp) && empty($backend)) {
			if (sspmod_delegate_Common::isAllowedIdp($remote_idp, $state['SPMetadata'])) {
				$state['delegate:AuthId'] = $this->authId;
				self::forward($state, $remote_idp);
			}
		} else {
			/* Invalid session data: If reauth is called, the session is valid.
			 * We should have information on previous backend OR remote_idp, and
			 * they are mutually exclusive! */
			SimpleSAML_Logger::error(
				'delegate: reauthenticate - session has backend AND remote_idp '
				. '(backend='.$backend.' remote_idp='.$remote_idp.')'
			);
			throw new SimpleSAML_Error_Error("BADSESSION");
		}

		// Backend or remote IdP is NOT enabled for the SP
		SimpleSAML_Logger::info(
			'delegate: reauthenticate - SP does not have access to current session'
		);

		if (isset($state['isPassive']) && (bool)$state['isPassive']) {
			// Passive request - we cannot authenticate the user.
			throw new SimpleSAML_Error_NoPassive('Reauth required');
		}

		/* OK, the user's backend does not have access to this SP. Let us give
		 * the user a chance to do something about it. */
		$state['delegate:AuthId'] = $this->authId; // Pass this auth source to no_access_auth
		$state['delegate:no_access_auth:backend'] = $backend;
		$state['delegate:no_access_auth:remote_idp'] = $remote_idp;
		$id = SimpleSAML_Auth_State::saveState($state, 'delegate:no_access_auth');
		$url = SimpleSAML_Module::getModuleURL('delegate/no_access_auth.php');
		SimpleSAML_Utilities::redirect($url, array('AuthState' => $id));
	}


	/**
	 * Log out after no-access error.
	 *
	 * @param array   $state       The authentication state.
	 * @param boolean $jsSupported Whether the user's browser supports javascript.
	 *
	 * @return void Never returns
	 */
	public function reauthLogout(array $state, $jsSupported)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->reauthLogout');

		if (isset($state['Responder'])) {
			$state['delegate:reauthLogout:PrevResponder'] = $state['Responder'];
		}
		$state['Responder'] = array(
			'sspmod_delegate_Auth_Source_Delegate', 'reauthPostLogout'
		);
		$state['core:Logout-IFrame:InitType'] = ($jsSupported ? 'js' : 'nojs');

		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
		$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

		$idp->handleLocalLogoutRequest($state);
	}


	/**
	 * Post-logout handler for reauth.
	 *
	 * @param SimpleSAML_IdP $idp   The IdP we are logging out from.
	 * @param array          $state The logout state from doLogoutRedirect().
	 *
	 * @return void Never returns
	 */
	public static function reauthPostLogout(SimpleSAML_IdP $idp, array $state)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->reauthPostLogout()');
		assert('isset($state["delegate:AuthId"])');

		if (isset($state['delegate:reauthLogout:PrevResponder'])) {
			$state['Responder'] = $state['delegate:reauthLogout:PrevResponder'];
			unset($state['delegate:reauthLogout:PrevResponder']);
		}

		$authSource = SimpleSAML_Auth_Source::getById(
			$state['delegate:AuthId'], 'sspmod_delegate_Auth_Source_Delegate'
		);
		$authSource->reauthLogin($state);
	}

	/**
	 * Continue logging in after no-access error.
	 *
	 * @param array $state The authentication state.
	 *
	 * @return void Never returns
	 */
	public function reauthLogin(array $state)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->reauthLogin');

		$state['LoginCompletedHandler'] = array(
			'sspmod_delegate_Auth_Source_Delegate', 'reauthPostLogin'
		);
		$this->authenticate($state);
	}

	/**
	 * Complete login operation after receiving the no-access error.
	 *
	 * @param array $state The authentication state.
	 *
	 * @return void Never returns
	 */
	public static function reauthPostLogin(array $state)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->reauthPostLogin');
		assert('isset($state["ReturnCallback"])');

		/* Update session state. */
		$as = $this->authSource->getAuthSource();
		$session = SimpleSAML_Session::getInstance();
		$session->doLogin(
			$as->getAuthId(),
			SimpleSAML_Auth_Default::extractPersistentAuthState($state)
		);
		call_user_func($state['ReturnCallback'], $state);
		assert('false');
	}

	/**
	 * Forward the authentication request to the upstream IdP.
	 *
	 * @param array  $state    The login state
	 * @param string $upstream The entityId of the upstream IdP
	 *
	 * @return void Never returns
	 */
	public static function forward(array &$state, $upstream)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->forward');
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		// TODO: We should probably store core:IdP in the state, and load from
		// state
		$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
		$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);
		$idp->forwardAuthRequest($state, $upstream);
		assert('false');
	}


	/**
	 * Attempt login using a given backend. Called from www/login.php
	 *
	 * @param array  $state    The login state
	 * @param string $username Username-input from the user
	 * @param string $password Password-input from the user
	 * @param string $backend  The selected backend
	 *
	 * @return void Never returns
	 */
	public function login(array &$state, $username, $password, $backend)
	{
		SimpleSAML_Logger::debug('delegate: Delegate->login');
		if (empty($username) || empty($password)) {
			throw new SimpleSAML_Error_BadUserInnput('Missing username or password');
		}

		if (!sspmod_delegate_Common::isAllowedBackend($backend, $state['SPMetadata'])) {
			throw new SimpleSAML_Error_BadUserInnput('Backend seems tampered with: '. $backend);
		}

		$fallback = sspmod_delegate_Common::getFallback($backend, $state['SPMetadata']);

		$session = SimpleSAML_Session::getInstance();
		try {
			SimpleSAML_Logger::debug(
				"delegate: login - trying backend '{$backend}'"
			);
			$b_obj = SimpleSAML_Auth_Source::getById($backend);
			$session->setData(
				'authenticated:backend', 'delegate', $backend,
				SimpleSAML_Session::DATA_TIMEOUT_LOGOUT
			);
			$state['Attributes'] = $b_obj->login($username, $password);
		} catch(SimpleSAML_Error_UserNotFound $e) {
			if (!$fallback) {
				throw $e;
			}
			SimpleSAML_Logger::debug(
				"delegate: login - trying fallback '{$fallback}'"
			);
			$b_obj = SimpleSAML_Auth_Source::getById($fallback);
			$session->setData(
				'authenticated:backend', 'delegate', $fallback,
				SimpleSAML_Session::DATA_TIMEOUT_LOGOUT
			);
			$state['Attributes'] = $b_obj->login($username, $password);
		}

		SimpleSAML_Logger::debug('delegate: successful login');
		SimpleSAML_Auth_Source::completeAuth($state);
	}
}

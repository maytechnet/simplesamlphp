<?php

/**
	* Delegate helper functions
	*
	* @author Mathias Meisfjordskar.
	* @package Delegate
	* @version $Id:
	*/

class sspmod_delegate_Common
{
	const COOKIE_NAME = 'backend_delegate';

	/**
	 * Check if a backend name is allowed for use for the current SP
	 *
	 * @param string $backend    The backend name
	 * @param array  $spMetadata The metadata for the current SP
	 *
	 * @return bool True if the backend exists in the SPs metadata
	 */
	public static function isAllowedBackend($backend, array $spMetadata)
	{
		assert('is_string($backend)');
		$allowed = self::getAllowedBackendNames($spMetadata);
		return in_array($backend, $allowed);
	}

	/**
	 * Get list of allowed backends for the given SP.
	 *
	 * @param array $spMetadata The SP's metadata.
	 *
	 * @return array List of allowed backends.
	 */
	public static function getAllowedBackends(array $spMetadata)
	{
		if (array_key_exists('delegate:backends', $spMetadata)) {
			$sp_be_names =  $spMetadata['delegate:backends'];
			// Filter against available backends in authsources
			$avail_be = self::getBackends();
			$backends = array();
			foreach ($sp_be_names as $b) {
				$b_obj = SimpleSAML_Auth_Source::getById($b);
				if (!in_array($b_obj, $avail_be)) {
					throw new Exception("Invalid backend in SP metadata: '{$b}'");
				}
				$backends[] = $b_obj;
			}
			return $backends;
		}
		return array();
	}

	/**
	 * List supported backends
	 *
	 * @return array List of backends
	 */
	public static function getBackends()
	{
		// List of supported backends (as of now)
		$SUPPORTED_BACKENDS = array('ldap:LDAP',);
		$backends = array();
		foreach ($SUPPORTED_BACKENDS as $be) {
			$backends = array_merge(
				$backends, SimpleSAML_Auth_Source::getSourcesOfType($be)
			);
		}
		return $backends;
	}

	/**
	 * Get list of allowed backend names for the given SP
	 *
	 * @param array $spMetadata The SP's metadata.
	 *
	 * @return array List of backend names
	 */
	public static function getAllowedBackendNames(array $spMetadata)
	{
		$ret = array();
		foreach (self::getAllowedBackends($spMetadata) as $b_obj) {
			$ret[] = $b_obj->getAuthId();
		}
		return $ret;
	}

	/**
	 * Check if an IdP is allowed for use for the current SP
	 *
	 * @param string $idp        The IdP name
	 * @param array  $spMetadata The metadata for the current SP
	 *
	 * @return bool True if the IdP exists in the SPs metadata
	 */
	public static function isAllowedIdp($idp, array $spMetadata)
	{
		assert('is_string($idp)');
		$allowed = self::getRemoteIdpsEnabledSp($spMetadata);
		return in_array($idp, $allowed);
	}

	/**
	 * Get list of allowed IdPs for the given SP
	 *
	 * @param array $spMetadata The SP's metadata.
	 *
	 * @return array List of IdP metadata
	 */
	public static function getRemoteIdpsEnabledSp(array $spMetadata)
	{
		if (array_key_exists('delegate:remote_idp', $spMetadata)) {
			$sp_idps = $spMetadata['delegate:remote_idp'];
			//Check against available IdPs in metadata/saml20-idp-remote.php
			$avail_idps = self::getRemoteIdps();
			foreach ($sp_idps as $i) {
				if (!array_key_exists($i, $avail_idps)) {
					throw new Exception("Invalid backend in SP metadata: '{$i}'");
				}
			}
			return $sp_idps;
		}
		return array();
	}

	/**
	 * Get list of all configured remote IdPs
	 *
	 * @return array List of IdP metadata
	 */
	public static function getRemoteIdps()
	{
		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idps = $metadataHandler->getList('saml20-idp-remote');
		return $idps;
	}

	/**
	 * Get the fallback for a given backend.
	 *
	 * @param string $backend    The backend name
	 * @param array  $spMetadata The metadata for the current SP
	 *
	 * @return string|null Returns a string with the fallback backend, if
	 *                     found. Returns null if no fallback is found for the
	 *                     backend.
	 */
	public static function getFallback($backend, array $spMetadata)
	{
		assert('is_string($backend)');
		if (!self::isAllowedBackend($backend, $spMetadata)) {
			return null;
		} elseif (!array_key_exists('delegate:fallback', $spMetadata)
			|| !array_key_exists($backend, $spMetadata['delegate:fallback'])
		) {
			return null;
		}
		return $spMetadata['delegate:fallback'][$backend];
	}


	/**
	 * Get the supplementary links for a given set sp-metadata.
	 *
	 * @param array $spMetadata The metadata for the current SP
	 *
	 * @return array Returns an array with info about the supplementary url.
	 */
	public static function getSupplementary(array $spMetadata)
	{
		if (array_key_exists('delegate:supplementary', $spMetadata)
			&& is_array($spMetadata['delegate:supplementary'])
		) {
			return $spMetadata['delegate:supplementary'];
		}
		return array();
	}


	public static function getSelectableAllowedBackends()
	{
		// TODO return all modules allowed for an SP that should be presented
		// to the user. Filter out mods like Negotiate.
	}

	public static function getUser()
	{
		//TODO extract user from attributes based on setup for backend
	}

	public static function getBackendCookie()
	{
		//TODO extract user from attributes based on setup for backend
	}


	/**
	 * Get a name for the given auth source
	 *
	 * @param string $authId  The auth source AuthId
	 * @param string $key     The config key in authsource
	 * @param string $default A default translation string
	 *
	 * @return array Returns the localized name array of an auth source, if
	 *               configured. If no name is given in authsources.php, a
	 *               translation array that maps 'en' => to a default value is
	 *               returned.
	 */
	public static function getAuthSourceTranslation($authId, $key, $default = "")
	{
		$default = array('en' => (string) $default);
		// TODO: Ugh, is there another way to fetch this config?
		//       Maybe we could keep the original config array in the SimpleSAML_Auth_Source?
		$authSources = SimpleSAML_Configuration::getOptionalConfig('authsources.php');
		if (!$authSources->hasValue($authId)) {
			return $default;
		}
		$authSource = $authSources->getValue($authId);
		if (array_key_exists($key, $authSource)
			&& is_array($authSource[$key])
			&& !empty($authSource[$key])
		) {
			return $authSource[$key];
		} elseif (array_key_exists($key, $authSource)
			&& is_string($authSource[$key])
			&& !empty($authSource[$key])
		) {
			return array('en' => $authSource[$key]);
		}

		return $default;
	}


	/**
	 * Get a name from the metadata of an entity.
	 *
	 * @param string $entityId The metadata id
	 * @param string $set      The metadata set
	 *
	 * @return array Returns the localized name array from the metadata. If no 
	 *               localized names are available, the organization name or 
	 *               entity id is returned.
	 */
	public static function getMetadataName($entityId, $set)
	{
		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$mdc = $metadataHandler->getMetaDataConfig($entityId, $set);
		$default = array(
			'en' => $mdc->getValue('OrganizationDisplayName', $entityId)
		);
		return $mdc->getLocalizedString('name', $default);
	}


	/**
	 * Get a name from the metadata of an entity.
	 *
	 * @param string $entityId The metadata id
	 * @param string $set      The metadata set
	 * @param string $key      The metadata attribute key to get
	 * @param string $default  A default translation string
	 *
	 * @return array Returns the localized attribute array from the metadata. If
	 *               no localized names are available, an array with a default
	 *               value is returned.
	 */
	public static function getMetadataTranslation($entityId, $set, $key, $default = "")
	{
		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$mdc = $metadataHandler->getMetaDataConfig($entityId, $set);
		$default = array('en' => (string) $default);
		return $mdc->getLocalizedString($key, $default);
	}
}

<?php
/**
 * Backend container
 *
 * @package Delegate
 * @version $Id:
 */

/**
 * Source data for the delegate login sources.
 */
class sspmod_delegate_SourceData
{
	const BACKEND = 'backend';
	const IDP = 'remote_idp';
	const SUPPLEMENTARY = 'just_flap';

	protected $id;
	protected $type;
	protected $name;
	protected $description;
	protected $logo;
	protected $url;

	/**
	 * Create a new data container
	 *
	 * @param string     $type            One of the constants
	 * @param string     $id              The ID of this flap
	 * @param array|null $name            The name of this thingy
	 * @param array|null $description     The description
	 * @param array|null $logo            The logo URL
     * @param array|null $login_text      The login help text
     * @param array|null $info_text       The info text
     * @param array|null $url         The url
	 */
	protected function __construct(
		$type, $id, $name, $description, $logo, $login_text, $info_text, $url
	) {
		$this->type = $type;
		$this->id = $id;

		if (is_array($name) && !empty($name)) {
			$this->name = $name;
		}

		if (is_array($description) && !empty($description)) {
			$this->description = $description;
		}

		if (is_array($logo) && !empty($logo)) {
			$this->logo = $logo;
		}

        if (is_array($login_text) && !empty($login_text)) {
            $this->login_text = $login_text;
        }

        if (is_array($info_text) && !empty($info_text)) {
            $this->info_text = $info_text;
        }

		$this->url = $url;
	}

	/**
	 * Get the id of this auth source
	 *
	 * @return string The Id
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Get a localized name
	 *
	 * @return array A localized array of names for this backend
	 */
	public function getName()
	{
		if (!is_array($this->name)) {
			return array('en' => $this->id);
		}
		return $this->name;
	}

	/**
	 * Get a localized description for this backend
	 *
	 * @return array A localized array of descriptions for this backend
	 */
	public function getDescription()
	{
		if (!is_array($this->description)) {
			return $this->getName();
		}
		return $this->description;
	}

	/**
	 * Get a localized logo url for this backend
	 *
	 * @return array A localized array of logo-urls
	 */
	public function getLogoUrl()
	{
		if (!is_array($this->logo)) {
			return array('en' => '');
		}
		return $this->logo;
	}

	/**
	 * Get a localized alt text for the backend logo
	 *
	 * @return array A localized array of alt texts
	 */
	public function getLogoAlt()
	{
		return $this->getName();
	}

    /**
     * Get a localized text for help regarding login for this backend
     *
     * @return array A localized array of URLs regarding login for this backend
     */
    public function getLoginText()
    {
        if (!is_array($this->login_text)) {
            return array('en' => '');
        }
        return $this->login_text;
    }

    /**
     * Get a localized text regarding info about login for this backend
     *
     * @return array A localized array of help-texts regarding login
     */
    public function getInfoText()
    {
        if (!is_array($this->info_text)) {
            return array('en' => '');
        }
        return $this->info_text;
    }

	/**
	 * Get a localized url to a remote service
	 *
	 * @return array A localized array of urls
	 */
	public function getUrl()
	{
		if (!is_array($this->url)) {
			return '';
		}
		return $this->url;
	}

	/**
	 * Check if source is of a given type
	 *
	 * @param string $types     String type
	 * @param string $types,... Multiple types.
	 *
	 * @return boolean If the array is one of the types.
	 */
	public function isType($types)
	{
		if (in_array($this->type, func_get_args())) {
			return true;
		}
		return false;
	}

	/**
	 * Create from auth source.
	 *
	 * @param string $authId The source name
	 *
	 * @return object A new instance
	 */
	public static function createFromAuthId($authId)
	{
		$sources = SimpleSAML_Configuration::getOptionalConfig(
			'authsources.php'
		);
		if (!$sources->hasValue($authId)) {
			throw new Exception("No '{$authId}' in authsources.php");
		}

        $metadata = array(
            'type' => self::BACKEND,

        );
		$as = SimpleSAML_Configuration::loadFromArray($sources->getValue($authId));
		$name = $as->getLocalizedString('name', null);
		$desc = $as->getLocalizedString('delegate:flap_description', null);
		$logo = $as->getLocalizedString('delegate:flap_logo', null);
        $login_text = $as->getLocalizedString('delegate:login_text', null);
        $info_text = $as->getLocalizedString('delegate:info_text', null);

		return new self(self::BACKEND, $authId, $name, $desc, $logo, $login_text, $info_text, null);
	}

	/**
	 * Create from metadata.
	 *
	 * @param string $entityId The entity id
	 *
	 * @return object A new instance
	 */
	public static function createFromMetadata($entityId)
	{
		$mh = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$mdc = $mh->getMetaDataConfig($entityId, 'saml20-idp-remote');
        $orgname = $mdc->getLocalizedString('OrganizationDisplayName', null);
		$name = $mdc->getLocalizedString('name', $orgname);
		$desc = $mdc->getLocalizedString('delegate:flap_description', null);
		$logo = $mdc->getLocalizedString('delegate:flap_logo', null);

		return new self(self::IDP, $entityId, $name, $desc, $logo, null, null, null);
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Some data
	 *
	 * @return object A new instance
	 */
	public static function createFromArray(array $data)
	{
		$config = SimpleSAML_Configuration::loadFromArray($data);
		$id = $config->getValue('flap_id');
		$url = $config->getLocalizedString('url');
		$desc = $config->getLocalizedString('flap_description');
		$logo = $config->getLocalizedString('flap_logo');
		$name = $config->getLocalizedString('name');

		return new self(self::SUPPLEMENTARY, $id, $name, $desc, $logo, null, null, $url);
	}
}

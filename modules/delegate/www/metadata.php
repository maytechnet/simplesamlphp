<?php

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

if (!array_key_exists('PATH_INFO', $_SERVER)) {
	throw new SimpleSAML_Error_BadRequest('Missing authentication source id in metadata URL');
}
$spEntityId = $idp->decodeEntityId(substr($_SERVER['PATH_INFO'], 1));
$encodedEntityId = $idp->encodeEntityId($spEntityId);

$spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

$ed = new SAML2_XML_md_EntityDescriptor();
$ed->entityID = SimpleSAML_Module::getModuleURL('delegate/metadata.php/' . $encodedEntityId);

$sp = new SAML2_XML_md_SPSSODescriptor();
$ed->RoleDescriptor[] = $sp;
$sp->protocolSupportEnumeration = array('urn:oasis:names:tc:SAML:2.0:protocol');

$slo = new SAML2_XML_md_EndpointType();
$slo->Binding = SAML2_Const::BINDING_HTTP_REDIRECT;
$slo->Location = SimpleSAML_Module::getModuleURL('delegate/slo.php/' . $encodedEntityId);
$sp->SingleLogoutService[] = $slo;

$acs = new SAML2_XML_md_IndexedEndpointType();
$acs->index = 0;
$acs->isDefault = TRUE;
$acs->Binding = SAML2_Const::BINDING_HTTP_POST;
$acs->Location = SimpleSAML_Module::getModuleURL('delegate/acs.php/' . $encodedEntityId);
$sp->AssertionConsumerService[] = $acs;

$xml = $ed->toXML();
SimpleSAML_Utilities::formatDOMElement($xml);
$xml = $xml->ownerDocument->saveXML($xml);

header('Content-Type: text/plain');
echo $xml;

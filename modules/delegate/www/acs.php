<?php

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$sp = $metadataHandler->getMetaDataCurrentEntityID();
$spMetadata = $metadataHandler->getMetaDataConfig($sp, 'saml20-sp-hosted');

$b = SAML2_Binding::getCurrentBinding();
if ($b instanceof SAML2_HTTPArtifact) {
    $b->setSPMetadata($spMetadata);
}

$response = $b->receive();
if (!($response instanceof SAML2_Response)) {
    throw new SimpleSAML_Error_BadRequest('Invalid message received to AssertionConsumerService endpoint.');
}

$remote_idp_name = $response->getIssuer();
if ($remote_idp_name === NULL) {
    throw new Exception('Missing <saml:Issuer> in message delivered to AssertionConsumerService.');
}

$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

if (!array_key_exists('PATH_INFO', $_SERVER)) {
    throw new SimpleSAML_Error_BadRequest('Missing SP entity id in AssertionConsumerService URL');
}
$spEntityId = $idp->decodeEntityId(substr($_SERVER['PATH_INFO'], 1));

$idp->forwardAuthResponse($spEntityId, $remote_idp_name);

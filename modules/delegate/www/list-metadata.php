<?php

SimpleSAML_Utilities::requireAdmin();

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$idp = SimpleSAML_IdP::getById('saml2:' . $idpEntityId);

$sps = $metadata->getList('saml20-sp-remote');
$sps = array_keys($sps);
?><!DOCTYPE html>
<html>
<body>
<ul>
<?php
foreach ($sps as $sp) {
	$mdURL = SimpleSAML_Module::getModuleURL('delegate/metadata.php/' . $idp->encodeEntityId($sp));
	echo '<li><a href="' . htmlspecialchars($mdURL) . '">' . htmlspecialchars($sp) . '</a></li>';
}
?>
</ul>
</body>
</html>


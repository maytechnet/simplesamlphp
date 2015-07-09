<?php
/**
 * Default template for www/no_access_auth.php
 *
 * @package ModuleDelegate
 * @author  TODO <foo@bar.no>
 * @version $Id$
 */

$this->data['header'] = $this->t('{delegate:no_access_dictionary:access_denied}');

$this->data['head'] = isset($this->data['head']) ? $this->data['head'] : '';
$this->data['head'] .= '<script type="text/javascript" src="/' . $this->data['baseurlpath'] . 'resources/jquery.js"></script>';

$this->includeAtTemplateBase('includes/header.php');

$spName = $this->t($this->data['SPName']);
$authState = $this->data['AuthState'];
$backend = $this->t($this->data['Backend']);

/* TODO: Change to something that Delegate understands */
$params = array(
    '%SPNAME%' => htmlspecialchars($spName),
    '%BACKEND%' => htmlspecialchars($backend),
);
?>

<h2><?php echo $this->t('{delegate:no_access_dictionary:access_denied}'); ?></h2>
<p><?php echo $this->t('{delegate:no_access_dictionary:intro}', $params); ?></p>
<p><?php echo $this->t('{delegate:no_access_dictionary:contact}', $params); ?></p>

<ul>
<?php
$contactOptions = array(
	// $this->data key => translation key
	'SPContactURL' => '{delegate:no_access_dictionary:help_desk_link}',
	'SPContactMail' => '{delegate:no_access_dictionary:help_desk_email}',
);
foreach ($contactOptions as $key => $trans) {
	if (isset($this->data[$key]) && !empty($this->data[$key])) {
		echo '<li><a href="' . htmlspecialchars($this->data[$key]) . '">' .
			$this->t($trans) . '</a></li>';
	}
}
?>
</ul>

<p><?php echo $this->t('{delegate:no_access_dictionary:reauth}', $params); ?></p>
<form method="post" action="?">
	<input type="hidden" name="AuthState" value="<?php echo htmlspecialchars($authState); ?>" />
	<input type="hidden" id="js_support_test" name="js" value="false" />
	<input type="submit" name="login" value="<?php echo $this->t('{delegate:no_access_dictionary:login}', $params); ?>" />
</form>

<?php /* TODO: Remove */ ?>
<h2>Debug</h2>
<pre>
<?php print_r($this->data); ?>
</pre>

<?php
$this->includeAtTemplateBase('includes/footer.php');
?>

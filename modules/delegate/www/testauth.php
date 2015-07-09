<?php
if (!array_key_exists('AuthState', $_GET)) {
	throw new SimpleSAML_Error_BadRequest('Missing AuthState parameter.');
}
$authStateId = $_GET['AuthState'];

/* Retrieve the authentication state. */
$state = SimpleSAML_Auth_State::loadState($authStateId, 'proxytest:loginpage');

$source = SimpleSAML_Auth_Source::getById($state['proxytest:authsource']);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $state[sspmod_core_Auth_UserPassBase::AUTHID]);
}

if (isset($_POST['login']) && !empty($_POST['username'])) {
	/* Login with username. */
	$username = (string)$_POST['username'];
	$source->login($state, $username);
	assert('FALSE');
} elseif (isset($_POST['forward'])) {
	$source->forward($state);
	assert('FALSE');
}
?><!DOCTYPE html>
<html>
<body>
<h1>ProxyTest authentication</h1>
<p>
Log in with username:
</p>
<form method="post" action="?AuthState=<?php echo htmlspecialchars(urlencode($authStateId)); ?>">
<input type="text" name="username">
<input type="submit" name="login" value="Login">
</form>

<p>
Log in with Feide:
</p>
<form method="post" action="?AuthState=<?php echo htmlspecialchars(urlencode($authStateId)); ?>">
<input type="submit" name="forward" value="Feide">
</form>

</body>
</html>

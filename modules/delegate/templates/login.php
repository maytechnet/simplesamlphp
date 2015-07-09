<?php
/**
 * Template for delegate/login.php
 *
 * @package Delegate
 * @author  UAIT @ University of Oslo <@bar.no>
 * @version $Id:
 */

$this->data['autofocus'] = 'username';
$this->data['pageid'] = 'delegate_login';
$this->includeAtTemplateBase('includes/header.php');

// SP name
if (!empty($this->data['sp']['name'])) {
	$spname = $this->data['sp']['name'];
} elseif (!empty($this->data['sp']['OrganizationDisplayName'])) {
	$spname = $this->data['sp']['OrganizationDisplayName'];
} elseif (isset($this->data['sp']['entityid'])) {
	$spname = $this->data['sp']['entityid'];
} else {
	$spname = $this->t('{delegate:login:generic_delegate_sp_name}');
}
if (is_array($spname)) {
	$spname = $this->getTranslation($spname);
}
$spname = htmlspecialchars($spname);

/* Prepare error message */
$error = "";
if (isset($this->data['error'])) {
	switch($this->data['error']) {

	case 'error_internal':
		$error_param = array('%MAIL%' => $globalConfig->getString('technicalcontact_email'));
		break;

	case 'error_external':
		/* Neither contactMail nor contactURL in organization. */
		$contactLink = $globalConfig->getString('technicalcontact_email');

		$error_param = array(
			'%LINK%' => htmlspecialchars($contactLink),
		);
		break;

	default:
		$error_param = array();
	}

	if ($this->getTag($this->data['error']) !== null) {
		$error = $this->t($this->data['error'], array('%TRACKID%' => $this->data['trackid']));
	} else {
		$error = sprintf(
			"%s<br /><span id='error-message-trackno'>%s #%s</span>",
			$this->t('{delegate:login:' . $this->data['error'] . '}', $error_param),
			$this->t('{delegate:login:error_trackno}'),
			$this->data['trackid']
		);
	}
}

$formURL = SimpleSAML_Utilities::addURLparameter('?', $this->data['state']);

$statestrs = array();
foreach ($this->data['state'] AS $key => $value) {
	$statestrs[] = urlencode($key) . '=' . urlencode($value);
}
$statestr = join('&amp;', $statestrs);

/**
 * Prepare a flap selector
 *
 * @param SimpleSAML_XHTML_Template  $t        $this
 * @param sspmod_delegate_SourceData $flap     Info about the source
 * @param bool                       $selected The currently selected id
 * @param string                     $baseurl  URL to self.
 *
 * @return string HTML representation of the data source.
 */
function htmlFlap($t, sspmod_delegate_SourceData $flap, $selected, $baseurl)
{
    $flapFmt = <<<HTML
<div class="backend-wrapper">
	<a class="%s" id="%s" %s>
		%s
		<span class="text">%s</span>
	</a>
</div>
HTML;

    $hrefAttr = '';
    $imgTag = '';
    $class = sprintf(
        "%s-backend%s",
        $flap->isType($flap::IDP, $flap::SUPPLEMENTARY) ? 'external' : 'internal',
        $selected == $flap->getId() ? ' active' : ''
    );

    $elementId = sprintf(
        "%s-backend-%s",
        $flap->isType($flap::IDP, $flap::SUPPLEMENTARY) ? 'external' : 'internal',
        $flap->getId()
    );
    $text = $t->t($flap->getName());

    if ($flap->isType($flap::IDP)) {
        $hrefAttr = sprintf(
            ' href="%s"',
            SimpleSAML_Utilities::addURLparameter(
                $baseurl, array('forward' => $flap->getId())
            )
        );
    } elseif ($flap->isType($flap::SUPPLEMENTARY)) {
        $hrefAttr = sprintf(' href="%s"', htmlspecialchars($t->t($flap->getUrl())));
    }

    $logo_url = $t->t($flap->getLogoUrl());
    if (!empty($logo_url)) {

        $id = $flap->getId();
        if ($flap->isType($flap::IDP)) {
            $id = str_replace(array('http://', 'https://'), '', $id);
        }
        $imgTag = sprintf(
            '<img class="logo flap-%s" src="%s" alt="%s">',
            htmlspecialchars($id),
            htmlspecialchars($t->t($flap->getLogoUrl())),
            htmlspecialchars($t->t($flap->getLogoAlt()))
        );
    }

    return sprintf($flapFmt, $class, $elementId, $hrefAttr, $imgTag, $text);
}
?>

<div id="login-box-wrapper">
	<div id="login-box">
        <form id="login-box-form" action="<?php echo htmlspecialchars($formURL); ?>" method="post" name="f">
            <fieldset>
            <div class="input-wrapper">
                <label for="username">
                    <?php echo($this->t('{delegate:login:username}')); ?>:
                </label>
                <input class="inputtext" type="text" id="username" name="username" placeholder="<?php echo($this->t('{delegate:login:username}')); ?>"/>
            </div>
            <div class="input-wrapper">
                <label for="password">
                    <?php echo($this->t('{delegate:login:password}')); ?>:
                </label>
                <input class="inputtext" type="password" id="password" name="password" placeholder="<?php echo($this->t('{delegate:login:password}')); ?>" />
            </div>

            <?php

            foreach ($this->data['state'] as $name => $value) {
                printf(
                    '<input type="hidden" name="%s" value="%s" />'."\n",
                    htmlspecialchars($name), htmlspecialchars($value)
                );
            }
            if ($error != "") {
                echo ('<p id="error-message">' . $error . '</p>');
            }

            echo '<button type="submit" class="submit" name="login"> ';
            printf(
                '<span>%s</span>',
                $this->t('{delegate:login:login_button}')
            );
            echo '</button>'
            ?>

            <?php
            // Sorting backends
            if (count($this->data['backends']) > 0
                && count($this->data['backends']) <= 1
            ) {
                printf(
                    '<input type="hidden" name="backend" value="%s" />'."\n",
                    $this->data['backends'][0]->getId()
                );
            } elseif (sizeof($this->data['backends']) > 1) {
                if (!$this->data['backend']) {
                    reset($this->data['backends']);
                    $this->data['backend'] = $this->data['backends'][0]->getId();
                }
                echo('<div id="backend-selectors">'."\n");
                foreach ($this->data['backends'] as $flap) {
                    echo('<div class="backend-selector-wrapper">');
                    printf(
                        '<input id="backend-selector-%s" class="backend-selector" type="radio" name="backend" value="%s" %s/>' ."\n",
                        $flap->getId(),
                        htmlspecialchars($flap->getId()),
                        ($flap->getId()  == $this->data['backend'] ? 'checked' : '')
                    );
                    printf(
                        '<label for="backend-choice-%s">%s</label>'."\n",
                        $flap->getId(),
                        htmlspecialchars($flap->getId())
                    );
                    echo("</div>\n");
                }
                echo("</div>");
            }

            // Inject URL and link for login-help from local backend-config
            echo '<div id="login-text-wrapper">';
            foreach ($this->data['backends'] as $flap) {
                $selected = $this->data['backend'];
                $login_text = $this->t($flap->getLoginText());
                if (!empty($login_text)) {
                    printf('<div id="login-text-%s" class="login-text%s">%s</div>',
                        $flap->getId(),
                        $selected == $flap->getId() ? ' active' : '',
                        $this->t($flap->getLoginText())
                    );
                }
            }
            echo '</div>';

            echo '<div id="info-text-wrapper">';
            // Inject infotext from local backend-config
            foreach ($this->data['backends'] as $flap) {
                $selected = $this->data['backend'];
                $info_text = $this->t($flap->getInfoText());
                if (!empty($info_text)) {
                    printf('<div id="info-text-%s" class="info-text%s">%s</div>',
                        $flap->getId(),
                        $selected == $flap->getId() ? ' active' : '',
                        $info_text
                    );
                }
            }

            echo '</div>';
            // Inject infotext from loginpage-hook
            if (!empty($this->data['hookinfo']['info'])) {
                echo '<div id="hookinfo-info-text-wrapper"><div class="hookinfo-info-text">';
                foreach ($this->data['hookinfo']['info'] as $hookinfo_info_text) {
                    printf('<div class="hookinfo-info-text-content">%s</div>',
                        $hookinfo_info_text
                    );
                }
                echo '</div></div>';
            }
            ?>
            </fieldset>
        </form>
        <div id="backend-list">
            <!-- text for responsive layout -->
            <?php
                printf(
                    '<h2 class="additional-login">%s</h2>'."\n",
                    $this->t('{delegate:login:use_other_system}')
                );
                foreach ($this->data['idps'] as $flapData) {
                    echo htmlFlap($this, $flapData, $this->data['backend'], $formURL);
                }
                foreach ($this->data['supp'] as $flapData) {
                    echo htmlFlap($this, $flapData, $this->data['backend'], $formURL);
                }
            ?>

        </div><!-- ^backend-list -->
	</div> <!-- ^login-box -->
</div> <!-- ^login-box-wrapper -->

<?php $this->includeAtTemplateBase('includes/footer.php'); ?>

	</body>
</html>

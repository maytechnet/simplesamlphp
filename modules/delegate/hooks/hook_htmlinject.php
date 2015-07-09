<?php

function delegate_hook_htmlinject(&$hookinfo)
{
    $conf = SimpleSAML_Configuration::getInstance();
    /* Check config to see which theme is active */
    $module = null;
    $theme = $conf->getString('theme.use', 'default');
    if (strpos($theme, ':') !== false) {
        list($module, $theme) = explode(':', $theme, 2);
    }

    $use_flaps = $module == 'delegate' && $theme == 'delegate-flaps';

    /* Insert required js & css files if rendering delegate_login page */
    if ($hookinfo['page'] == 'delegate_login') {
        $hookinfo['head'][] = '<link rel="stylesheet" type="text/css" href="' . SimpleSAML_Module::getModuleURL("delegate/delegate.css") . '" />';
        $hookinfo['head'][] = '<script type="text/javascript" src="' . SimpleSAML_Module::getModuleURL("delegate/delegate.js") . '"></script>';

        /* Load extra reqs for delegate-flaps theme if active */
        if ($use_flaps) {
            $hookinfo['head'][] = '<link rel="stylesheet" type="text/css" href="' . SimpleSAML_Module::getModuleURL("delegate/delegate-flaps.css") . '" />';
            $hookinfo['head'][] = '<script type="text/javascript" src="' . SimpleSAML_Module::getModuleURL("delegate/delegate-flaps.js") . '"></script>';
        }
    }
}
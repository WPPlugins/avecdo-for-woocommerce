<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete all options assigned by the Avecdo plugin
delete_option('avecdo_plugin_activated');
delete_option('avecdo_public_key');
delete_option('avecdo_private_key');

?>

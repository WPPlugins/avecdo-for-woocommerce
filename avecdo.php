<?php
/**
 * Plugin Name: avecdo for WooCommerce
 * Plugin URI: http://avecdo.com/
 * Description: avecdo connector plugin for WooCommerce
 * Version: 1.2.4
 * Author: Modified Solutions ApS
 * Author URI: https://www.modified.dk/
 * Developer: Modified Solutions ApS
 * Developer URI: https://www.modified.dk/
 * Requires at least: 3.5
 * Tested up to: 4.8
 *
 * Text Domain: avecdo-for-woocommerce
 * Domain Path: /languages/
 *
 * @package avecdo Connector
 * @category WooCommerce
 * @author Modified Solutions ApS
 */
if (!defined('ABSPATH')) {
    exit;
}
/** @var float Plugin start time */
define('AVECDO_WOOCOMMERCE_PLUGIN_START_TIME', microtime(true));

/**
 * The current version of this plugin.
 * @var string Version string
 */
define('AVECDO_WOOCOMMERCE_PLUGIN_VERSION', '1.2.4');

/**
 * plugin file name
 * @var string
 */
define('AVECDO_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once(dirname(__FILE__).'/classes/Plugin.php');
require_once(dirname(__FILE__).'/models/WooQueries.php');
require_once(dirname(__FILE__).'/models/Model.php');
require_once(dirname(__FILE__).'/SDK/loader.php');
require_once(dirname(__FILE__).'/classes/WooAPI.php');

// Register feed.
$avecdoPlugin = Avecdo\Plugin::make();
$avecdoPlugin->loadTextdomain();
$avecdoPlugin->registerPluginActions();

/*
 * Initializer
 */

function avecdo_connect()
{
    $plugin = Avecdo\Plugin::make();

    // Check if WooCommerce is activated.
    if (!$plugin->isWoocommerceActive()) {
        $plugin->error(__(Avecdo\Plugin::WOOCOMMERCE_NOT_ACTIVE, 'avecdo-for-woocommerce'));
    }

    $permissions = current_user_can('update_plugins') || current_user_can('delete_plugins')
        || current_user_can('install_plugins') || current_user_can('activate_plugins')
        || current_user_can('manage_network_plugins') || current_user_can('edit_plugins');
    if (!$permissions) {
        $plugin->error(__(Avecdo\Plugin::NOT_SUFFICIENT_PERMISSIONS));
    }

    $nonceMessage = $plugin->checkNonce();
    if ($nonceMessage !== true) {
        $plugin->error($nonceMessage);
    }

    $plugin->render();
}





function avecdo_safe_redirect($url)
{
    if (!headers_sent()) {
        wp_redirect($url);
        exit;
    } else {
        echo '<script>window.location.assign("'.$url.'");</script>';
        exit;
    }
}

if (!function_exists('avecdoShowNotice')) {
    /*
     * Show nitifications on admin pages.
     */
    function avecdoShowNotice()
    {
        $updateSatatus = get_transient('__avecdo_update_check');

        if (current_user_can('update_plugins') && $updateSatatus && $updateSatatus['update_available']) {
            
            $slig = "avecdo-for-woocommerce";
            
            $detailsUrl = self_admin_url('plugin-install.php?tab=plugin-information&plugin='.$slig.'&section=changelog&TB_iframe=true&width=600&height=800' );
            $updateUrl = wp_nonce_url(
                self_admin_url('update.php?action=upgrade-plugin&plugin=avecdo-for-woocommerce/avecdo.php'),
                'upgrade-plugin_avecdo-for-woocommerce/avecdo.php'
            );

            $class   = 'notice avecdobgcolor avecdobglogo';
            $message = sprintf(
                __(
                    '<strong>WARNING!</strong> An important update is available to your avecdo plugin. '
                    . '<a href="%s" class="update-link" aria-label="Update avecdo for WooCommerce now">Click here to update</a> or '
                    . '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="avecdo-for-woocommerce-details">View details</a>', 'avecdo-for-woocommerce'
                ),
                $updateUrl, $detailsUrl
            );
            $message = sprintf('<div class="%s"><p>%s</p></div>', esc_attr($class), $message);

            echo $message;
        }
    }
}
/*
 * -----------------------------------------------------------
 *
 * Helper methods.
 * @todo up the min. PHP ver to 5.4 and start using triat
 * -----------------------------------------------------------
 */

if (!function_exists('avecdoEchoNotice')) {

    /**
     * echo a message in html
     * @param string $message
     * @param string $type error | warning | success | info
     * @param boolean $isDismissible
     */
    function avecdoEchoNotice($message, $type = "info", $isDismissible = false)
    {
        $type .= $isDismissible ? " is-dismissible" : "";
        echo '<div class="notice notice-'.$type.'"><p>'.$message.'</p></div>';
    }
}

if (!function_exists('avecdoValidateWooCommerceVersion')) {

    /**
     * Validate the current WooCommerce version
     * 
     * @staticvar type $wooCommerceVersion
     * @staticvar array $cachedVersionCompare
     * @param type $version
     * @param type $operator
     * @return boolean
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    function avecdoValidateWooCommerceVersion($version, $operator = "=")
    {
        static $wooCommerceVersion   = null;
        static $cachedVersionCompare = array();
        if (!is_null($wooCommerceVersion)) {
            if (!isset($cachedVersionCompare["{$version}_{$operator}"])) {
                $cachedVersionCompare["{$version}_{$operator}"] = version_compare($wooCommerceVersion, $version, $operator);
            }
            return $cachedVersionCompare["{$version}_{$operator}"];
        }
        if (!function_exists('get_plugins')) {
            require_once( ABSPATH.'wp-admin/includes/plugin.php' );
        }
        $plugin_folder = get_plugins('/'.'woocommerce');
        $plugin_file   = 'woocommerce.php';
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            $wooCommerceVersion = $plugin_folder[$plugin_file]['Version'];
            return version_compare($wooCommerceVersion, $version, $operator);
        }
        return false;
    }
}
if (!function_exists('avecdoGetImageTitleFromMeta')) {

    /**
     * Get image title/caption from metadata array.
     * @param array $metadata
     * @param string $imagealt
     * @return string defaults to $imagealt
     */
    function avecdoGetImageTitleFromMeta($metadata, $imagealt)
    {
        if (!is_array($metadata)) {
            return $imagealt;
        }
        if (!empty($metadata) && isset($metadata['image_meta'])) {
            $metadata = $metadata['image_meta'];
        }
        $text = "";
        if (isset($metadata['caption'])) {
            $text = $metadata['caption'];
        }
        if (empty($text) && isset($metadata['title'])) {
            $text = $metadata['title'];
        }
        if (empty($text)) {
            $text = $imagealt;
        }
        return $text;
    }
}


if (!function_exists('avecdoGetAttachmentRelativePath')) {

    /**
     * Get the relative path to an attachment under the upload folder
     * @param file $file
     * @return string
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    function avecdoGetAttachmentRelativePath($file)
    {
        $dirname = dirname($file);
        if ('.' === $dirname) {
            return '';
        }
        if (false !== strpos($dirname, 'wp-content/uploads')) {
            // Get the directory name relative to the upload directory (back compat for pre-2.7 uploads)
            $dirname = substr($dirname, strpos($dirname, 'wp-content/uploads') + 18);
            return ltrim($dirname, '/');
        }
        return $dirname;
    }
}

if (!function_exists('avecdoBuildFullMediaUrl')) {

    /**
     * Build and return the full web url to $file
     * @param string $file partial path to file.
     * @param int $fileId database id of the file
     * @return string
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    function avecdoBuildFullMediaUrl($file, $fileId)
    {
        $url     = '';
        // Get upload directory.
        if (($uploads = wp_upload_dir(null, false)) && false === $uploads['error']) {
            // Check that the upload base exists in the file location.
            if (0 === strpos($file, $uploads['basedir'])) {
                // Replace file location with url location.
                $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
            } elseif (false !== strpos($file, 'wp-content/uploads')) {
                // Get the directory name relative to the basedir (back compat for pre-2.7 uploads)
                $url = trailingslashit($uploads['baseurl'].'/'.avecdoGetAttachmentRelativePath($file)).basename($file);
            } else {
                // It's a newly-uploaded file, therefore $file is relative to the basedir.
                $url = $uploads['baseurl']."/$file";
            }
        }
        if (empty($url)) {
            $url = avecdoQueryPostGuid($fileId);
        }
        if (empty($url)) {
            return "";
        }
        return set_url_scheme($url);
    }
}

if (!function_exists('avecdoQueryPostGuid')) {

    /**
     * get value of guid from posts table
     * @global wpdb $wpdb
     * @param int $fileId
     * @return string
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    function avecdoQueryPostGuid($fileId)
    {
        if (((int) $fileId > 0)) {
            global $wpdb;
            $query_result = $wpdb->get_results("SELECT guid FROM ".$wpdb->prefix."posts WHERE ID=".intval($fileId), OBJECT);
            return !empty($query_result) ? (is_array($query_result) ? $query_result[0]->guid
                    : "") : "";
        }
        return "";
    }
}

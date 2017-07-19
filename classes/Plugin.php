<?php

namespace Avecdo;

use Avecdo\Classes\WooAPI;
use AvecdoSDK\Exceptions\AuthException;
use AvecdoSDK\POPO\KeySet;
use AvecdoSDK\POPO\Shop;
use AvecdoSDK\POPO\Shop\ShopSystem;
use AvecdoSDK\POPO\Shop\ShopExtras;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    const WP_NONCE_NOT_SET           = 'WP nonce not set.';
    const INVALID_WP_NONCE           = 'WP nonce not valid! Please go back and refresh the page to try again.';
    const WOOCOMMERCE_NOT_ACTIVE     = 'WooCommerce is not activated.';
    const NOT_SUFFICIENT_PERMISSIONS = 'You do not have sufficient permissions to access this page.';
    const ERROR_CODE_INTERFACE       = 6872;

    /** @var Model */
    public $model;
    public $apiPath;

    /**
     * holds the currently loaded keyset.
     * @var KeySet
     */
    private $keySet;

    /**
     * Holds a reference to the current Plugin instance
     * @var Plugin
     */
    private static $instance;
    private $messages = array();

    /**
     * @return Plugin
     */
    public static function make()
    {
        if (is_null(static::$instance)) {
            new static();
        }

        return static::$instance;
    }

    public function __construct()
    {
        $this->model   = new Model();
        $this->apiPath = rtrim(site_url(), '/').'/?avecdo-api';
        $this->updateKeySet();

        
        // do update check..
        $updateSatatus = get_transient('__avecdo_update_check');
        if (!$updateSatatus) {
            if (!function_exists('plugins_api')) {
                require_once( ABSPATH.'wp-admin/includes/plugin-install.php' );
            }
            $args          = array(
                'slug'   => 'avecdo-for-woocommerce',
                'fields' => array('version' => true)
            );
            $data          = plugins_api('plugin_information', $args);
            $newVersion    = version_compare(AVECDO_WOOCOMMERCE_PLUGIN_VERSION, $data->version, '<');
            $updateSatatus = array(
                'update_available' => $newVersion,
                'latest'           => $data->version
            );
            // store update status for 12 hours
            set_transient('__avecdo_update_check', $updateSatatus, ((60 * 60) * 12));
        }


        static::$instance = $this;
    }

    /**
     * Register an admin menu item to access the plugin page.
     */
    public function registerAdminMenuItem()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_menu_page('Avecdo Connect', 'Avecdo Connect', 'manage_options', 'avecdo', 'avecdo_connect', 'dashicons-rss');
    }

    /**
     * Register a custom CSS file inside the administration on
     * the Avecdo plugin page.
     */
    public function registerAdminCss()
    {
        wp_register_style('avecdo_admin_style', plugins_url('../assets/css/styles.css', __FILE__), array(), AVECDO_WOOCOMMERCE_PLUGIN_VERSION, 'all');
        wp_enqueue_style('avecdo_admin_style');
    }

    /**
     * Runs a security check when form data is being posted.
     * The request verification is WP's own system called "nonce".
     */
    public function checkNonce()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!isset($_REQUEST['_wpnonce'])) {
                $this->messages['warning'][] = __(self::WP_NONCE_NOT_SET, 'avecdo-for-woocommerce');
            }
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'avecdo_activation_form')) {
                $this->messages['warning'][] = __(self::INVALID_WP_NONCE, 'avecdo-for-woocommerce');
            }
        }
        return true;
    }

    /**
     * Calls wp_die() with an error message.
     */
    public function error($message)
    {
        wp_die('<div class="wrap"><div class="error settings-error notice"><p>'.$message.'</p></div></div>');
    }

    public function render()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->catchFormRequest();
        }

        ob_start();

        $activationKey = $this->keySet->asString();
        $mesages       = $this->messages;
        $activation    = false;
        if (isset($_GET['activation']) && !isset($_POST['avecdo_submit_reset'])) {
            $activation = true;
        }

        if (!$this->isActivated()) {
            include (dirname(__FILE__).'/../views/index.php');
        } else {
            include (dirname(__FILE__).'/../views/activated.php');
        }

        $content = ob_get_clean();
        echo $content;
    }

    public function catchFormRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['avecdo_submit_activation'])) {
                $this->activationSubmitted();
            } else if (isset($_POST['avecdo_submit_reset'])) {
                $this->resetSubmitted();
            }
        }
    }

    public function getProducts($page, $limit, $lastRun)
    {
        $data = $this->model->getProducts($page, $limit, $lastRun);
        return $data;
    }

    public function getCategories()
    {
        $data = $this->model->getCategories();
        return $data;
    }

    public function catchApiRequest()
    {
        WooAPI::make()->bindContext($this)->routeRequest($this->getKeySet());
    }

    private function activationSubmitted()
    {
        $_aak          = @$_POST['avecdo_activation_key'];
        $activationKey = isset($_aak) ? sanitize_text_field($_aak) : '';
        $keySet        = KeySet::fromActivationKey($activationKey);

        if ($keySet == null) {
            $this->messages['error'][] = __('Invalid activation key', 'avecdo-for-woocommerce');
            return;
        }
        $this->storeKeys($keySet);
        $webService = new \AvecdoSDK\Classes\WebService();

        try {
            $webService->authenticate($keySet, $this->apiPath);
        } catch (AuthException $e) {

            $errorMessage = $e->getMessage();
            $payload      = $e->getPayload();

            if (isset($payload->message)) {
                $errorMessage .= ': '.$payload->message;
            }
            $this->messages['error'][] = $errorMessage;
            return;
        } catch (Exception $e) {
            $this->messages['error'][] = $e->getMessage();
            return;
        }

        $this->activateAvecdo();
    }

    /**
     * Set avecdo feed as active
     */
    public function activateAvecdo()
    {
        update_option('avecdo_plugin_activated', 1);
        $this->messages['success'][] = __('Your avecdo connection is now active.', 'avecdo-for-woocommerce');
    }

    /**
     * Runs the activation process when the plugin gets activated inside WP.
     *
     * CHANGED BY: Christian M. Jensen <christian@modified.dk>
     * @return void
     * @version 1.1.2
     *  - added wp_rewrite->flush_rules() to support clean avecdo api uri. (load new url rewrites)
     */
    public function activate()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    /**
     * Runs the deactivation process when the plugin gets deactivated inside WP.
     *
     * CHANGED BY: Christian M. Jensen <christian@modified.dk>
     * @return void
     * @version 1.1.2
     *  - added wp_rewrite->flush_rules() to support clean avecdo api uri. (remove old url rewrites)
     */
    public function deactivate()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }

    /**
     * Get shop data
     * @return array
     */
    public function getShop()
    {
        $shop = new Shop();
        $shop
            ->setUrl(get_site_url())
            ->setPrimaryCurrency(get_woocommerce_currency())
            ->setName(get_bloginfo('name'))
            ->setImage(get_header_image())
            ->setEmail(get_option('admin_email'))
            ->setCountry(get_option('woocommerce_default_country'))
            ->setShopSystem(ShopSystem::WOOCOMMERCE)
            ->setPluginVersion(AVECDO_WOOCOMMERCE_PLUGIN_VERSION)
            ->setAddress(/* address */null)
            ->setOwner(/* owner */null)
            ->setPhone(/* phone */null)
            /* ->setPrimaryLanguage(get_bloginfo('language')) */
            ->setPrimaryLanguage(get_locale())
            ->addToExtras(ShopExtras::WORDPRESS_VERSION, get_bloginfo('version'));

        if (class_exists('WooCommerce')) {
            $wooCommerce = $this->model->getWooCommerceInstance();
            $shop->setShopSystemVersion($wooCommerce->version);
        }
        $shop->addToExtras('description', get_bloginfo('description'));

        /* just because */
        $shop->addToExtras('MAX_EXECUTION_TIME', @ini_get('max_execution_time'));
        $shop->addToExtras('MEMORY_LIMIT', @ini_get('memory_limit'));
        $shop->addToExtras('IS_MULTISITE', is_multisite() ? 1 : 0);
        //$shop->addToExtras('ACTIVE_PLUGINS', $this->getActivePlugins());

        $data = $shop->getAll();
        return $data;
    }

    /**
     * Get the currently loaded keyset, if not loaded
     * loaded the current one from database
     * @return KeySet
     */
    protected function getKeySet()
    {
        if (is_null($this->keySet)) {
            $this->updateKeySet();
        }
        return $this->keySet;
    }

    /**
     * Reset the plugin and delete private/public keys from the database.
     */
    private function resetSubmitted()
    {
        update_option('avecdo_plugin_activated', 0);
        update_option('avecdo_public_key', '');
        update_option('avecdo_private_key', '');
        $this->messages['success'][] = __('Your avecdo keys have been removed from your shop.', 'avecdo-for-woocommerce');
    }

    /**
     * Save the keyset for this shop in database.
     * @param KeySet $keySet
     */
    private function storeKeys(KeySet $keySet)
    {
        update_option('avecdo_public_key', $keySet->getPublicKey());
        update_option('avecdo_private_key', $keySet->getPrivateKey());
        // load keyset.
        $this->keySet = null;
        $this->getKeySet();
    }

    /**
     * Update the loaded provate and public key keys
     * @return void
     */
    public function updateKeySet()
    {
        $this->keySet = new KeySet(get_option('avecdo_public_key'), get_option('avecdo_private_key'));
    }

    /**
     * gets a boolean value indicating if the plug in is activated.
     * @return boolean
     */
    private function isActivated()
    {
        return (bool) get_option('avecdo_plugin_activated', false);
    }

    /**
     * Load localization files.
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    public function loadTextdomain()
    {
        load_plugin_textdomain('avecdo-for-woocommerce', false, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    /**
     * Gets a boolean value indicating if WooCommerce is active
     * @return boolean
     */
    public function isWoocommerceActive()
    {
        $active_plugins = ( is_multisite() ) ?
            array_keys(get_site_option('active_sitewide_plugins', array())) : apply_filters('active_plugins', get_option('active_plugins', array()));

        foreach ($active_plugins as $active_plugin) {
            $active_plugin = explode('/', $active_plugin);
            if (isset($active_plugin[1]) && 'woocommerce.php' === $active_plugin[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param	mixed $links Plugin Row Meta
     * @param	mixed $file  Plugin Base file
     * @return	array
     */
    public function pluginRowMeta($links, $file)
    {
        if (AVECDO_WOOCOMMERCE_PLUGIN_BASENAME == $file) {
            $row_meta = array(
                'channels' => '<a href="http://avecdo.com/channels/" title="'.__('View supported channels', 'avecdo-for-woocommerce').'">'.__('Channels', 'avecdo-for-woocommerce').'</a>',
                'features' => '<a href="http://avecdo.com/features/" title="'.__('View supported features', 'avecdo-for-woocommerce').'">'.__('Features', 'avecdo-for-woocommerce').'</a>',
                'support'  => '<a href="mailto:support@avecdo.com?subject=Help with WooCommerce" title="'.__('Send a mail to customer support', 'avecdo-for-woocommerce').'">'.__('Mail support', 'avecdo-for-woocommerce').'</a>',
            );
            if ($this->isActivated()) {
                $row_meta['login'] = '<a href="https://app.avecdo.com/login" target="_blank">'.__('Go to avecdo', 'avecdo-for-woocommerce').'</a>';
            } else {
                $row_meta['register'] = '<a href="https://app.avecdo.com/register" target="_blank">'.__('Register at avecdo', 'avecdo-for-woocommerce').'</a>';
            }
            return array_merge($links, $row_meta);
        }
        return (array) $links;
    }

    /**
     * Register plugin action links
     * @param array $links
     * @return array
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    public function registerActionLinks($links)
    {
        $links[] = '<a href="'.admin_url('admin.php?page=avecdo').'">'.__('Settings', 'avecdo-for-woocommerce').'</a>';
        return $links;
    }

    /**
     * Get all active plugins.
     * @return array
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function getActivePlugins()
    {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        // Get both site plugins and network plugins
        $active_plugins = (array) get_option('active_plugins', array());
        if (is_multisite()) {
            $network_activated_plugins = array_keys(get_site_option('active_sitewide_plugins', array()));
            $active_plugins            = array_merge($active_plugins, $network_activated_plugins);
        }
        $active_plugins_data = array();
        foreach ($active_plugins as $plugin) {
            $data                  = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin);
            // convert plugin data to json response format.
            $active_plugins_data[] = array(
                'plugin'            => $plugin,
                'name'              => $data['Name'],
                'version'           => $data['Version'],
                'url'               => $data['PluginURI'],
                'author_name'       => $data['AuthorName'],
                'author_url'        => esc_url_raw($data['AuthorURI']),
                'network_activated' => $data['Network'],
            );
        }
        return $active_plugins_data;
    }

    /**
     * parse web request and check for avecdo api calls.
     * @param \WP $wp
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    public function parseRequest($wp)
    {
        if (!isset($wp->query_vars['avecdo-api'])) {
            return;
        }
        $valid_actions = array('product', 'category', 'shop');
        $action        = $wp->query_vars['action'];
        if (!empty($action) && in_array($action, $valid_actions)) {

            // SDK hack,  uses $_GET
            $_GET = array_merge($_GET, $wp->query_vars);

            WooAPI::make()->bindContext($this)->routeRequest($this->getKeySet());
        }
    }

    /**
     * call WP function 'add_rewrite_rule' and register rewrite routes
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    public function registerRewriteRules()
    {
        // avecdo-api/ACTION/-page-
        add_rewrite_rule(
            '^avecdo-api/(product|category|shop)/([0-9]+)/?', 'index.php?avecdo-api=true&action=$matches[1]&page=$matches[2]'
            , 'top');

        // avecdo-api/(product | category | shop)
        add_rewrite_rule(
            '^avecdo-api/(product|category|shop)/?', 'index.php?avecdo-api=true&action=$matches[1]'
            , 'top');
    }

    /**
     * append routed vars for use in avecdo api routes
     * @param array $vars
     * @return array
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    public function routeQueryVars($vars)
    {
        $vars[] = 'avecdo-api';
        $vars[] = 'action';
        $vars[] = 'page';
        return $vars;
    }

    public function registerPluginActions()
    {
        // register plguin rewrite rules
        add_action('init', $this->getCallBackToMe('registerRewriteRules'));
        // append supported query vars
        add_filter('query_vars', $this->getCallBackToMe('routeQueryVars'));
        // parse page request if query == avecdo-api
        add_action('parse_request', $this->getCallBackToMe('parseRequest'), 0);
        // show update notice etc..
        add_action( 'admin_notices', 'avecdoShowNotice' );

        // activate / deactivate hhoks
        register_activation_hook(__FILE__, $this->getCallBackToMe('activate'));
        register_deactivation_hook(__FILE__, $this->getCallBackToMe('deactivate'));

        // admin page hooks (plugin page)
        add_action('admin_enqueue_scripts', $this->getCallBackToMe('registerAdminCss'));
        if (is_admin()) {
            add_action('admin_menu', $this->getCallBackToMe('registerAdminMenuItem'));
            add_filter('plugin_row_meta', $this->getCallBackToMe('pluginRowMeta'), 10, 2);
            add_filter('plugin_action_links_'.AVECDO_WOOCOMMERCE_PLUGIN_BASENAME, $this->getCallBackToMe('registerActionLinks'));
        }
    }

    private function getCallBackToMe($method)
    {
        return array($this, $method);
    }
}
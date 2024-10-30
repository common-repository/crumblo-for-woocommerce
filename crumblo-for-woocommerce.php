<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Crumblo for WooCommerce
 * Version:             1.2.1
 * Requires at least:   4.0
 * Requires PHP:        5.6
 * Description:         Online payments for WooCommerce powered by Crumblo
 * Text Domain:         crumblo-for-woocommerce
 * Author:              Crumblo
 * Author URI:          https://crumblo.com/
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('CRUMBLO_VERSION', '1.2.1');

/**
 * The code that runs during plugin activation.
 */
function crumblo_for_woocommerce_activate()
{
}

/**
 * The code that runs during plugin deactivation.
 */
function crumblo_for_woocommerce_deactivate()
{
}

register_activation_hook(__FILE__, 'crumblo_for_woocommerce_activate');
register_deactivation_hook(__FILE__, 'crumblo_for_woocommerce_deactivate');

require plugin_dir_path(__FILE__) . 'includes/crumblo.php';

/**
 * Begins execution of the plugin.
 */
function crumblo_run()
{
    $plugin = new Crumblo('crumblo', CRUMBLO_VERSION);
    $plugin->run();
}

add_action('plugins_loaded', 'crumblo_run', 0);


<?php
/**
 * Plugin Name:       Mousa Abdulaziz Plugin
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Handle the basics with this plugin.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Mousa Abdulaziz
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mousa-abdulaziz-plugin
 * Domain Path:       /languages
 */

function mousa_abdulaziz_plugin_activate() {
    // Activation code here.
}
register_activation_hook( __FILE__, 'mousa_abdulaziz_plugin_activate' );

function mousa_abdulaziz_plugin_deactivate() {
    // Deactivation code here.
}
register_deactivation_hook( __FILE__, 'mousa_abdulaziz_plugin_deactivate' );

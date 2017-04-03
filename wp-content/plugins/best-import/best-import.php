<?php
    /**
    * Plugin Name: Best Import
    * Plugin URI: http://zefirstudio.pl/wp-best-import/
    * Description: This plugin allows to import data from XML/CSV/ZIP files.
    * Version: 1.1.3
    * Author: Zefir Studio
    * Author URI: http://www.zefirstudio.pl/
    * License: GPL2
    */

    defined('ABSPATH') or die('No script kiddies please!');

    add_action('admin_init', 'bi_init');
    add_action('admin_menu', 'bi_menu');

    function bi_init() {
        wp_register_style('bi_styles', plugins_url('css/styles.css', __FILE__));
        wp_register_script('bi_scripts', plugins_url('js/scripts.js', __FILE__));
        wp_register_script('bi_jquery_form', plugins_url('js/jquery.form.js', __FILE__));
        wp_enqueue_style('bi_styles');
        wp_enqueue_script('bi_scripts');
        wp_enqueue_script('bi_jquery_form');
        wp_enqueue_script('suggest');
    }

    function bi_menu() {
        add_menu_page('Best Import', 'Best Import', 'activate_plugins', 'best_import', 'bi_options');
    }

    function bi_options(){
        if(!current_user_can('manage_options'))wp_die(__('You do not have sufficient permissions to access this page.'));
        include 'admin/admin.php';
    }

?>
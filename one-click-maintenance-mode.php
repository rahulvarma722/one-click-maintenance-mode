<?php
/**
 * Plugin Name: One-Click Maintenance Mode
 * Description: A simple one-click solution to enable maintenance mode for non-logged-in users.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: one-click-maintenance-mode
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class One_Click_Maintenance_Mode {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_item'), 100);
        add_action('template_redirect', array($this, 'check_maintenance_mode'));
        add_action('wp_ajax_ocmm_toggle_maintenance', array($this, 'ajax_toggle_maintenance'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function register_settings() {
        register_setting('ocmm_settings', 'ocmm_enabled');
        register_setting('ocmm_settings', 'ocmm_message');
        register_setting('ocmm_settings', 'ocmm_sub_message');
        register_setting('ocmm_settings', 'ocmm_logo');
    }
    
    public function add_settings_page() {
        add_options_page(
            'Maintenance Mode',
            'Maintenance',
            'manage_options',
            'one-click-maintenance-mode',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_settings_page() {
        $enabled = get_option('ocmm_enabled', false);
        $message = get_option('ocmm_message', 'We\'ll be back soon!');
        $sub_message = get_option('ocmm_sub_message', '');
        $logo = get_option('ocmm_logo', '');
        ?>
        <div class="wrap">
            <h1>Maintenance Mode Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ocmm_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Enable Maintenance Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ocmm_enabled" value="1" <?php checked($enabled); ?>>
                                Activate maintenance mode for non-logged-in users
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Maintenance Message</th>
                        <td>
                            <textarea name="ocmm_message" rows="3" cols="50"><?php echo esc_textarea($message); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Sub Message</th>
                        <td>
                            <textarea name="ocmm_sub_message" rows="2" cols="50"><?php echo esc_textarea($sub_message); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Logo</th>
                        <td>
                            <div class="logo-preview">
                                <?php if ($logo): ?>
                                    <img src="<?php echo esc_url($logo); ?>" style="max-width:200px">
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="ocmm_logo" id="ocmm_logo" value="<?php echo esc_attr($logo); ?>">
                            <button type="button" class="button" id="upload_logo_button">Upload Logo</button>
                            <?php if ($logo): ?>
                                <button type="button" class="button" id="remove_logo_button">Remove Logo</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#upload_logo_button').click(function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Select Logo',
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#ocmm_logo').val(attachment.url);
                    $('.logo-preview').html('<img src="' + attachment.url + '" style="max-width:200px">');
                    $('#upload_logo_button').after('<button type="button" class="button" id="remove_logo_button">Remove Logo</button>');
                });
                frame.open();
            });
            
            $(document).on('click', '#remove_logo_button', function(e) {
                e.preventDefault();
                $('#ocmm_logo').val('');
                $('.logo-preview').empty();
                $(this).remove();
            });
        });
        </script>
        <?php
    }
    
    public function add_admin_bar_item($admin_bar) {
        if (!current_user_can('manage_options')) return;
        
        $enabled = get_option('ocmm_enabled', false);
        $status = $enabled ? 'ON' : 'OFF';
        $class = $enabled ? 'ocmm-on' : 'ocmm-off';
        
        $admin_bar->add_node(array(
            'id'    => 'ocmm-status',
            'title' => 'Maintenance Mode: <span class="' . $class . '">' . $status . '</span>',
            'href'  => '#',
            'meta'  => array(
                'class' => 'ocmm-toggle',
                'title' => 'Click to toggle maintenance mode'
            )
        ));
    }
    
    public function check_maintenance_mode() {
        if (get_option('ocmm_enabled', false) && !is_user_logged_in()) {
            $message = get_option('ocmm_message', 'We\'ll be back soon!');
            $sub_message = get_option('ocmm_sub_message', '');
            $logo = get_option('ocmm_logo', '');
            
            status_header(503);
            nocache_headers();
            
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Maintenance Mode</title>
                <style>
                    body {
                        font-family: -apple-system, sans-serif;
                        background: #f1f1f1;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .maintenance-box {
                        background: white;
                        padding: 40px;
                        border-radius: 5px;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                        text-align: center;
                        max-width: 500px;
                    }
                    .logo {
                        max-width: 200px;
                        margin-bottom: 20px;
                    }
                    .message {
                        font-weight: bold;
                        font-size: 18px;
                    }
                    .sub-message {
                        font-size: 14px;
                        margin-top: 10px;
                    }
                </style>
            </head>
            <body>
                <div class="maintenance-box">
                    ' . ($logo ? '<img src="' . esc_url($logo) . '" alt="Logo" class="logo">' : '') . '
                    <div class="message">' . wp_kses_post($message) . '</div>
                    ' . ($sub_message ? '<div class="sub-message">' . wp_kses_post($sub_message) . '</div>' : '') . '
                </div>
            </body>
            </html>';
            exit;
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_one-click-maintenance-mode' === $hook) {
            wp_enqueue_media();
        }
        
        if (is_admin_bar_showing()) {
            wp_add_inline_style('admin-bar', '
                #wp-admin-bar-ocmm-status .ocmm-on { color: #46b450; font-weight: bold; }
                #wp-admin-bar-ocmm-status .ocmm-off { color: #dc3232; font-weight: bold; }
            ');
            
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $(".ocmm-toggle").on("click", function(e) {
                        e.preventDefault();
                        $.post(ajaxurl, {
                            action: "ocmm_toggle_maintenance",
                            nonce: "' . wp_create_nonce('ocmm_toggle_nonce') . '"
                        }, function(response) {
                            if (response.success) {
                                var $status = $("#wp-admin-bar-ocmm-status .ab-item span");
                                if (response.data.enabled) {
                                    $status.removeClass("ocmm-off").addClass("ocmm-on").text("ON");
                                } else {
                                    $status.removeClass("ocmm-on").addClass("ocmm-off").text("OFF");
                                }
                                location.reload();
                            }
                        });
                    });
                });
            ');
        }
    }
    
    public function ajax_toggle_maintenance() {
        if (!current_user_can('manage_options') || !check_ajax_referer('ocmm_toggle_nonce', 'nonce', false)) {
            wp_send_json_error();
            return;
        }
        
        $current = get_option('ocmm_enabled', false);
        update_option('ocmm_enabled', !$current);
        
        wp_send_json_success(array('enabled' => !$current));
    }
}

// Initialize the plugin
One_Click_Maintenance_Mode::get_instance();
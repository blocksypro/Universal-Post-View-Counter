<?php
/*
Plugin Name: Universal Post View Counter
Plugin URI: https://blocksystartersites.com/universal-post-view-counter/
Description: Counts and displays the total views and live views for WordPress posts, pages, WooCommerce products, and custom post types using 'AJAX for best performane'.
Version: 1.1
Author: Richmond Ayisi
Text Domain: universal-post-view-counter
License:  GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// =============================
// 1️⃣ COUNT TOTAL VIEWS (EXISTING FEATURE)
// =============================

function upvc_get_countable_post_types() {
    return apply_filters('upvc_countable_post_types', array('post', 'page', 'product', 'custom_post_type_slug'));
}

function upvc_enqueue_scripts() {
    if (is_singular(upvc_get_countable_post_types())) {
        wp_enqueue_script('upvc-ajax-script', plugins_url('/js/upvc-ajax.js', __FILE__), array('jquery'), '2.0', true);
        wp_localize_script('upvc-ajax-script', 'upvc_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => get_the_ID(),
        ));
    }
}
add_action('wp_enqueue_scripts', 'upvc_enqueue_scripts');

function upvc_count_post_view() {
    if (!isset($_POST['post_id'])) {
        wp_send_json_error('No post ID sent');
    }

    $post_id = intval($_POST['post_id']);
    $views = get_post_meta($post_id, '_upvc_views', true);
    $views = $views ? $views + 1 : 1;
    update_post_meta($post_id, '_upvc_views', $views);

    wp_send_json_success(array('views' => $views));
}
add_action('wp_ajax_nopriv_upvc_count_post_view', 'upvc_count_post_view');
add_action('wp_ajax_upvc_count_post_view', 'upvc_count_post_view');

function upvc_display_post_views() {
    global $post;

    if (!$post || !in_array(get_post_type($post), upvc_get_countable_post_types())) {
        return '';
    }

    $views = get_post_meta($post->ID, '_upvc_views', true);
    $views = $views ? $views : 0;

    return "<div class='upvc-view-count'>👁️ Views: {$views}</div>";
}
add_shortcode('post_views', 'upvc_display_post_views');

// =============================
// 2️⃣ LIVE VIEWERS COUNTER (NEW FEATURE)
// =============================

function upvc_track_live_viewers() {
    if (!isset($_POST['post_id'])) {
        wp_send_json_error('No post ID');
    }

    $post_id = intval($_POST['post_id']);
    $current_time = time();
    $timeout = 15; // Users are removed after 15 seconds of inactivity

    $viewers = get_transient("upvc_live_{$post_id}") ?: array();
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Update or add user to active viewers list
    $viewers[$user_ip] = $current_time;

    // Remove inactive users
    foreach ($viewers as $ip => $timestamp) {
        if ($current_time - $timestamp > $timeout) {
            unset($viewers[$ip]);
        }
    }

    set_transient("upvc_live_{$post_id}", $viewers, $timeout);

    wp_send_json_success(array('viewers' => count($viewers)));
}
add_action('wp_ajax_nopriv_upvc_live_viewers', 'upvc_track_live_viewers');
add_action('wp_ajax_upvc_live_viewers', 'upvc_track_live_viewers');

function upvc_live_viewers_shortcode() {
    global $post;
    if (!$post || !in_array(get_post_type($post), upvc_get_countable_post_types())) {
        return '';
    }

    return "<div id='upvc-live-viewers'>👥 Live Viewers: <span id='upvc-live-count'>0</span></div>
            <script>
                function updateLiveViewers() {
                    jQuery.post('".admin_url('admin-ajax.php')."', {
                        action: 'upvc_live_viewers',
                        post_id: {$post->ID}
                    }, function(response) {
                        if (response.success) {
                            jQuery('#upvc-live-count').text(response.data.viewers);
                        }
                    });
                }
                setInterval(updateLiveViewers, 10000); // Update every 10 seconds
                updateLiveViewers();
            </script>";
}
add_shortcode('live_viewers', 'upvc_live_viewers_shortcode');

// ==============================
// 3️⃣ ADMIN SETTINGS PAGE (UNCHANGED)
// ==============================

function upvc_admin_menu() {
    add_menu_page(
        'View Counter Settings',
        'View Counter',
        'manage_options',
        'upvc-settings',
        'upvc_settings_page',
        'dashicons-visibility',
        100
    );
}
add_action('admin_menu', 'upvc_admin_menu');

function upvc_register_settings() {
    register_setting('upvc_settings_group', 'upvc_label');
    register_setting('upvc_settings_group', 'upvc_dashicon');
    register_setting('upvc_settings_group', 'upvc_icon_size');
}
add_action('admin_init', 'upvc_register_settings');

function upvc_settings_page() {
    ?>
    <div class="wrap">
        <h1>View Counter Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('upvc_settings_group'); ?>
            <?php do_settings_sections('upvc_settings_group'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">View Label</th>
                    <td>
                        <input type="text" name="upvc_label" value="<?php echo esc_attr(get_option('upvc_label', 'Views')); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Dashicon Class</th>
                    <td>
                        <input type="text" name="upvc_dashicon" value="<?php echo esc_attr(get_option('upvc_dashicon', 'dashicons-visibility')); ?>" />
                        <p><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">View Dashicon Classes</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Icon Size (px)</th>
                    <td>
                        <input type="number" name="upvc_icon_size" value="<?php echo esc_attr(get_option('upvc_icon_size', '20')); ?>" />
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

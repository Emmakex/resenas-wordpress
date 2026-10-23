<?php
/**
 * Plugin Name: Kairoseth Reviews
 * Plugin URI: https://kairoseth.com/products/resenas-wordpress
 * Description: Collect customer ratings and feedback, manage one or multiple locations, and support service recovery workflows.
 * Version: 1.2.0-rc3
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Kairoseth
 * Author URI: https://kairoseth.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kairoseth-reviews
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

class Kairoseth_Reviews {
    const VERSION = '1.2.0-rc3';
    const OPTION_KEY = 'lad_rf_settings'; // Se conserva para migrar sin perder la configuración 1.0.1.
    const LOCATIONS_KEY = 'rw_locations';
    const SCHEMA_KEY = 'rw_schema_version';
    const TABLE = 'lad_review_funnel_entries'; // Se conserva para no perder respuestas existentes.

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('plugins_loaded', [$this, 'maybe_upgrade']);

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_rw_save_location', [$this, 'save_location']);
        add_action('admin_post_rw_delete_location', [$this, 'delete_location']);

        add_shortcode('resenas_wordpress', [$this, 'shortcode']);
        add_shortcode('lad_review_funnel', [$this, 'shortcode']); // Compatibilidad con páginas ya creadas.

        add_action('wp_enqueue_scripts', [$this, 'enqueue_front']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);

        add_action('wp_ajax_rw_save_rating', [$this, 'ajax_save_rating']);
        add_action('wp_ajax_nopriv_rw_save_rating', [$this, 'ajax_save_rating']);
        add_action('wp_ajax_rw_save_feedback', [$this, 'ajax_save_feedback']);
        add_action('wp_ajax_nopriv_rw_save_feedback', [$this, 'ajax_save_feedback']);

        // Compatibilidad con acciones AJAX de la versión 1.0.1.
        add_action('wp_ajax_lad_rf_save_rating', [$this, 'ajax_save_rating']);
        add_action('wp_ajax_nopriv_lad_rf_save_rating', [$this, 'ajax_save_rating']);
        add_action('wp_ajax_lad_rf_save_feedback', [$this, 'ajax_save_feedback']);
        add_action('wp_ajax_nopriv_lad_rf_save_feedback', [$this, 'ajax_save_feedback']);

        add_action('rw_send_low_rating_email', [$this, 'send_low_rating_email_async'], 10, 7);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_links']);
    }

    public function activate() {
        $this->deactivate_legacy_plugin();
        $this->install_schema();
        if (!get_option(self::OPTION_KEY)) {
            update_option(self::OPTION_KEY, $this->default_settings());
        }
        if (get_option(self::LOCATIONS_KEY, null) === null) {
            add_option(self::LOCATIONS_KEY, []);
        }
        update_option(self::SCHEMA_KEY, self::VERSION);
    }

    private function deactivate_legacy_plugin() {
        $current_plugin = plugin_basename(__FILE__);
        $active_plugins = (array) get_option('active_plugins', []);
        $legacy_plugins = [];

        foreach ($active_plugins as $plugin) {
            if ($plugin === $current_plugin) continue;
            if (basename($plugin) === 'resenas-wordpress.php') {
                $legacy_plugins[] = $plugin;
            }
        }

        if (!$legacy_plugins) return;

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Preserve all legacy options, locations and feedback data; only the
        // old plugin code is deactivated to avoid duplicate hooks/endpoints.
        deactivate_plugins($legacy_plugins, true);
    }

    public function maybe_upgrade() {
        if (get_option(self::SCHEMA_KEY) !== self::VERSION) {
            $this->install_schema();
            if (get_option(self::LOCATIONS_KEY, null) === null) {
                add_option(self::LOCATIONS_KEY, []);
            }
            update_option(self::SCHEMA_KEY, self::VERSION);
        }
    }

    private function install_schema() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            ref_code VARCHAR(191) NULL,
            company_name VARCHAR(191) NULL,
            location_slug VARCHAR(191) NULL,
            location_name VARCHAR(191) NULL,
            rating TINYINT UNSIGNED NULL,
            feedback_reason VARCHAR(191) NULL,
            feedback_text LONGTEXT NULL,
            customer_id VARCHAR(191) NULL,
            service_type VARCHAR(191) NULL,
            order_id VARCHAR(191) NULL,
            tech_name VARCHAR(191) NULL,
            source VARCHAR(191) NULL,
            user_ip VARCHAR(100) NULL,
            user_agent TEXT NULL,
            page_url TEXT NULL,
            status VARCHAR(50) NULL,
            PRIMARY KEY (id),
            KEY ref_code (ref_code),
            KEY rating (rating),
            KEY location_slug (location_slug),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private function default_settings() {
        return [
            'company_name' => get_bloginfo('name'),
            'logo_url' => '',
            'primary_color' => '#1f2937',
            'accent_color' => '#f59e0b',
            'background_color' => '#ffffff',
            'card_color' => '#ffffff',
            'text_color' => '#111827',
            'button_text_color' => '#ffffff',
            'google_review_url' => '',
            'low_rating_threshold' => 3,
            'headline' => __('How was your experience with our service?', 'kairoseth-reviews'),
            'subheadline' => __('Your feedback helps us improve.', 'kairoseth-reviews'),
            'low_title' => __('We are sorry your experience was not the best', 'kairoseth-reviews'),
            'low_text' => __('Tell us what happened so we can help and keep improving.', 'kairoseth-reviews'),
            'high_title' => __('Thank you for your rating', 'kairoseth-reviews'),
            'high_text' => __('Would you share your experience on Google?', 'kairoseth-reviews'),
            'thanks_title' => __('Thank you for your feedback', 'kairoseth-reviews'),
            'thanks_text' => __('We received your response successfully.', 'kairoseth-reviews'),
            'low_button' => __('Send feedback', 'kairoseth-reviews'),
            'high_button' => __('Leave a review on Google', 'kairoseth-reviews'),
            'show_reason_buttons' => 1,
            'reason_options' => implode("\n", [__('Customer service', 'kairoseth-reviews'), __('Wait time', 'kairoseth-reviews'), __('Price', 'kairoseth-reviews'), __('Service outcome', 'kairoseth-reviews'), __('Other', 'kairoseth-reviews')]),
            'custom_css' => '',
            'notify_email' => get_option('admin_email'),
            'send_low_rating_email' => 1,
        ];
    }

    private function get_settings() {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_settings());
    }

    private function get_locations($active_only = false) {
        $locations = get_option(self::LOCATIONS_KEY, []);
        if (!is_array($locations)) $locations = [];

        if ($active_only) {
            $locations = array_filter($locations, function($location) {
                return !empty($location['active']);
            });
        }

        uasort($locations, function($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $locations;
    }

    private function resolve_location() {
        $active = $this->get_locations(true);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only URL routing parameter.
        $requested_slug = sanitize_title(wp_unslash($_GET['sede'] ?? ''));

        if ($requested_slug && isset($active[$requested_slug])) {
            return [
                'location' => $active[$requested_slug],
                'requested_slug' => $requested_slug,
                'error' => '',
            ];
        }

        if ($requested_slug && !isset($active[$requested_slug])) {
            return [
                'location' => null,
                'requested_slug' => $requested_slug,
                'error' => __('The selected location is not available.', 'kairoseth-reviews'),
            ];
        }

        if (count($active) === 1) {
            return [
                'location' => reset($active),
                'requested_slug' => '',
                'error' => '',
            ];
        }

        if (count($active) > 1) {
            return [
                'location' => null,
                'requested_slug' => '',
                'error' => __('This business has multiple locations. The link must specify a location.', 'kairoseth-reviews'),
            ];
        }

        return [
            'location' => null,
            'requested_slug' => '',
            'error' => '',
        ];
    }

    public function plugin_links($links) {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=rw-dashboard')) . '">' . esc_html__('Dashboard', 'kairoseth-reviews') . '</a>';
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=rw-settings')) . '">' . esc_html__('Settings', 'kairoseth-reviews') . '</a>';
        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            __('Kairoseth Reviews', 'kairoseth-reviews'),
            __('Kairoseth Reviews', 'kairoseth-reviews'),
            'manage_options',
            'rw-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-star-filled',
            58
        );
        add_submenu_page('rw-dashboard', __('Dashboard', 'kairoseth-reviews'), __('Dashboard', 'kairoseth-reviews'), 'manage_options', 'rw-dashboard', [$this, 'dashboard_page']);
        add_submenu_page('rw-dashboard', __('Feedback', 'kairoseth-reviews'), __('Feedback', 'kairoseth-reviews'), 'manage_options', 'rw-feedback', [$this, 'entries_page']);
        add_submenu_page('rw-dashboard', __('Locations', 'kairoseth-reviews'), __('Locations', 'kairoseth-reviews'), 'manage_options', 'rw-locations', [$this, 'locations_page']);
        add_submenu_page('rw-dashboard', __('Appearance and settings', 'kairoseth-reviews'), __('Appearance and settings', 'kairoseth-reviews'), 'manage_options', 'rw-settings', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('rw_settings_group', self::OPTION_KEY, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $defaults = $this->default_settings();
        $out = [];

        $out['company_name'] = sanitize_text_field($input['company_name'] ?? $defaults['company_name']);
        $out['logo_url'] = esc_url_raw($input['logo_url'] ?? '');
        $out['primary_color'] = sanitize_hex_color($input['primary_color'] ?? $defaults['primary_color']) ?: $defaults['primary_color'];
        $out['accent_color'] = sanitize_hex_color($input['accent_color'] ?? $defaults['accent_color']) ?: $defaults['accent_color'];
        $out['background_color'] = sanitize_hex_color($input['background_color'] ?? $defaults['background_color']) ?: $defaults['background_color'];
        $out['card_color'] = sanitize_hex_color($input['card_color'] ?? $defaults['card_color']) ?: $defaults['card_color'];
        $out['text_color'] = sanitize_hex_color($input['text_color'] ?? $defaults['text_color']) ?: $defaults['text_color'];
        $out['button_text_color'] = sanitize_hex_color($input['button_text_color'] ?? $defaults['button_text_color']) ?: $defaults['button_text_color'];
        $out['google_review_url'] = esc_url_raw($input['google_review_url'] ?? '');
        $out['low_rating_threshold'] = max(1, min(5, intval($input['low_rating_threshold'] ?? 3)));

        foreach (['headline','subheadline','low_title','low_text','high_title','high_text','thanks_title','thanks_text','low_button','high_button'] as $field) {
            $out[$field] = sanitize_text_field($input[$field] ?? $defaults[$field]);
        }

        $out['show_reason_buttons'] = !empty($input['show_reason_buttons']) ? 1 : 0;
        $out['reason_options'] = sanitize_textarea_field($input['reason_options'] ?? $defaults['reason_options']);
        $out['custom_css'] = wp_kses_post($input['custom_css'] ?? '');
        $out['notify_email'] = sanitize_email($input['notify_email'] ?? get_option('admin_email'));
        $out['send_low_rating_email'] = !empty($input['send_low_rating_email']) ? 1 : 0;

        return $out;
    }

    public function enqueue_front() {
        wp_register_style('rw-front', plugins_url('assets/front.css', __FILE__), [], self::VERSION);
        wp_register_script('rw-front', plugins_url('assets/front.js', __FILE__), ['jquery'], self::VERSION, true);
    }

    public function enqueue_admin($hook) {
        if (strpos($hook, 'rw-') === false) return;

        wp_enqueue_style('rw-admin', plugins_url('assets/admin.css', __FILE__), [], self::VERSION);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');

        $media_title = wp_json_encode(__('Select a logo', 'kairoseth-reviews'));
        $media_button = wp_json_encode(__('Use logo', 'kairoseth-reviews'));
        wp_add_inline_script(
            'wp-color-picker',
            "jQuery(function($){
                $('.lad-rf-color').wpColorPicker();
                $(document).on('click', '.rw-upload-logo', function(e){
                    e.preventDefault();
                    const target = $('#rw_logo_url');
                    const frame = wp.media({title:{$media_title},button:{text:{$media_button}},multiple:false});
                    frame.on('select', function(){
                        const attachment = frame.state().get('selection').first().toJSON();
                        target.val(attachment.url);
                    });
                    frame.open();
                });
            });"
        );
    }

    public function dashboard_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live metrics from the plugin's own table.
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live metrics from the plugin's own table.
        $avg = (float) $wpdb->get_var($wpdb->prepare('SELECT AVG(rating) FROM %i WHERE rating IS NOT NULL', $table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live metrics from the plugin's own table.
        $low = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE rating <= %d', $table, (int) $this->get_settings()['low_rating_threshold']));
        $locations = $this->get_locations();
        ?>
        <div class="wrap rw-admin">
            <div class="rw-admin-hero">
                <h1><?php esc_html_e('Kairoseth Reviews', 'kairoseth-reviews'); ?></h1>
                <p><?php esc_html_e('Collect ratings, detect customers who need follow-up, and help them share their public experience from a page on your own WordPress site.', 'kairoseth-reviews'); ?></p>
            </div>

            <div class="rw-metric-grid" aria-label="<?php esc_attr_e('Activity overview', 'kairoseth-reviews'); ?>">
                <?php $cards = [
                    [__('Total feedback', 'kairoseth-reviews'), $total],
                    [__('Average rating', 'kairoseth-reviews'), $total ? number_format_i18n($avg, 2) . ' / 5' : '—'],
                    [__('Need follow-up', 'kairoseth-reviews'), $low],
                    [__('Configured locations', 'kairoseth-reviews'), count($locations)],
                ]; foreach ($cards as $card): ?>
                    <div class="rw-metric-card">
                        <span class="rw-metric-label"><?php echo esc_html($card[0]); ?></span>
                        <strong class="rw-metric-value"><?php echo esc_html($card[1]); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rw-admin-grid">
                <section class="rw-admin-panel" aria-labelledby="rw-howto-title">
                    <h2 id="rw-howto-title"><?php esc_html_e('How it works', 'kairoseth-reviews'); ?></h2>
                    <p class="rw-admin-intro"><?php esc_html_e('The link you send to the customer is the URL of the WordPress page where you placed the shortcode.', 'kairoseth-reviews'); ?></p>
                    <ol class="rw-howto">
                        <li><strong><?php esc_html_e('Create a page.', 'kairoseth-reviews'); ?></strong> <?php esc_html_e('For example, create a page called Reviews.', 'kairoseth-reviews'); ?></li>
                        <li><strong><?php esc_html_e('Insert the shortcode.', 'kairoseth-reviews'); ?></strong> <?php esc_html_e('Add [resenas_wordpress] to the page and publish it.', 'kairoseth-reviews'); ?></li>
                        <li><strong><?php esc_html_e('Configure your business.', 'kairoseth-reviews'); ?></strong> <?php esc_html_e('Add your logo, copy, Google review link, and optional locations.', 'kairoseth-reviews'); ?></li>
                        <li><strong><?php esc_html_e('Send the page URL.', 'kairoseth-reviews'); ?></strong> <?php esc_html_e('Share a URL such as', 'kairoseth-reviews'); ?> <code>https://example.com/reviews/</code>. <?php esc_html_e('You can add', 'kairoseth-reviews'); ?> <code>?ref=ORDER-123</code> <?php esc_html_e('and, for multiple locations,', 'kairoseth-reviews'); ?> <code>?sede=downtown</code>.</li>
                    </ol>
                </section>

                <aside class="rw-admin-panel" aria-labelledby="rw-quick-title">
                    <h2 id="rw-quick-title"><?php esc_html_e('Quick actions', 'kairoseth-reviews'); ?></h2>
                    <p class="rw-admin-intro"><?php esc_html_e('Configure the plugin and review customer responses.', 'kairoseth-reviews'); ?></p>
                    <div class="rw-quick-actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rw-settings')); ?>"><?php esc_html_e('Appearance and settings', 'kairoseth-reviews'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations')); ?>"><?php esc_html_e('Manage locations', 'kairoseth-reviews'); ?></a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-feedback')); ?>"><?php esc_html_e('View feedback', 'kairoseth-reviews'); ?></a>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    public function settings_page() {
        $s = $this->get_settings(); ?>
        <div class="wrap rw-admin rw-settings-page">
            <div class="rw-admin-hero">
                <h1><?php esc_html_e('Appearance and settings', 'kairoseth-reviews'); ?></h1>
                <p><?php esc_html_e('Configure the customer experience, visual identity, and follow-up notifications without leaving WordPress.', 'kairoseth-reviews'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('rw_settings_group'); ?>

                <nav class="rw-settings-nav" aria-label="<?php esc_attr_e('Settings sections', 'kairoseth-reviews'); ?>">
                    <a href="#rw-settings-general"><?php esc_html_e('General', 'kairoseth-reviews'); ?></a>
                    <a href="#rw-settings-appearance"><?php esc_html_e('Appearance', 'kairoseth-reviews'); ?></a>
                    <a href="#rw-settings-copy"><?php esc_html_e('Copy', 'kairoseth-reviews'); ?></a>
                    <a href="#rw-settings-notifications"><?php esc_html_e('Notifications', 'kairoseth-reviews'); ?></a>
                </nav>

                <section id="rw-settings-general" class="rw-settings-section" aria-labelledby="rw-settings-general-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-general-title"><?php esc_html_e('General', 'kairoseth-reviews'); ?></h2><p><?php esc_html_e('Business identity, Google settings, and follow-up behavior.', 'kairoseth-reviews'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Business name', 'kairoseth-reviews'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[company_name]" value="<?php echo esc_attr($s['company_name']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Logo', 'kairoseth-reviews'); ?></th><td><div class="rw-inline-field"><input type="url" class="regular-text" id="rw_logo_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[logo_url]" value="<?php echo esc_attr($s['logo_url']); ?>"><button type="button" class="button rw-upload-logo"><?php esc_html_e('Upload / choose', 'kairoseth-reviews'); ?></button></div></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Global Google review URL', 'kairoseth-reviews'); ?></th><td><input type="url" class="large-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_review_url]" value="<?php echo esc_attr($s['google_review_url']); ?>"><p class="description"><?php esc_html_e('Used when no locations are configured. With multiple locations, each location can have its own URL.', 'kairoseth-reviews'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Follow-up threshold', 'kairoseth-reviews'); ?></th><td><input type="number" min="1" max="5" name="<?php echo esc_attr(self::OPTION_KEY); ?>[low_rating_threshold]" value="<?php echo esc_attr($s['low_rating_threshold']); ?>"><p class="description"><?php esc_html_e('Ratings at or below this value request internal feedback to support service recovery.', 'kairoseth-reviews'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-appearance" class="rw-settings-section" aria-labelledby="rw-settings-appearance-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-appearance-title"><?php esc_html_e('Appearance', 'kairoseth-reviews'); ?></h2><p><?php esc_html_e('Colors and visual settings for the public form.', 'kairoseth-reviews'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Primary color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Accent / star color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr($s['accent_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Background color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($s['background_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Card color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[card_color]" value="<?php echo esc_attr($s['card_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Text color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Button text color', 'kairoseth-reviews'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[button_text_color]" value="<?php echo esc_attr($s['button_text_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Custom CSS', 'kairoseth-reviews'); ?></th><td><textarea class="large-text code" rows="8" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_css]"><?php echo esc_textarea($s['custom_css']); ?></textarea><p class="description"><?php esc_html_e('For advanced adjustments only. The base design is already optimized for mobile and accessibility.', 'kairoseth-reviews'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-copy" class="rw-settings-section" aria-labelledby="rw-settings-copy-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-copy-title"><?php esc_html_e('Copy', 'kairoseth-reviews'); ?></h2><p><?php esc_html_e('Customize the messages customers see during the rating flow.', 'kairoseth-reviews'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                    <?php
                    $text_fields = [
                        'headline' => __('Main title', 'kairoseth-reviews'),
                        'subheadline' => __('Subtitle', 'kairoseth-reviews'),
                        'low_title' => __('Follow-up title', 'kairoseth-reviews'),
                        'low_text' => __('Follow-up text', 'kairoseth-reviews'),
                        'high_title' => __('High-rating title', 'kairoseth-reviews'),
                        'high_text' => __('High-rating text', 'kairoseth-reviews'),
                        'thanks_title' => __('Thank-you title', 'kairoseth-reviews'),
                        'thanks_text' => __('Thank-you text', 'kairoseth-reviews'),
                        'low_button' => __('Feedback button text', 'kairoseth-reviews'),
                        'high_button' => __('Google button text', 'kairoseth-reviews'),
                    ];
                    foreach ($text_fields as $key => $label): ?>
                        <tr><th scope="row"><?php echo esc_html($label); ?></th><td><input type="text" class="large-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></td></tr>
                    <?php endforeach; ?>
                        <tr><th scope="row"><?php esc_html_e('Show quick reasons', 'kairoseth-reviews'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_reason_buttons]" value="1" <?php checked($s['show_reason_buttons'], 1); ?>> <?php esc_html_e('Yes', 'kairoseth-reviews'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Quick reasons', 'kairoseth-reviews'); ?></th><td><textarea class="large-text" rows="6" name="<?php echo esc_attr(self::OPTION_KEY); ?>[reason_options]"><?php echo esc_textarea($s['reason_options']); ?></textarea><p class="description"><?php esc_html_e('One per line.', 'kairoseth-reviews'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-notifications" class="rw-settings-section" aria-labelledby="rw-settings-notifications-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-notifications-title"><?php esc_html_e('Notifications', 'kairoseth-reviews'); ?></h2><p><?php esc_html_e('Choose where to receive notifications when a rating needs follow-up.', 'kairoseth-reviews'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Global notification email', 'kairoseth-reviews'); ?></th><td><input type="email" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_email]" value="<?php echo esc_attr($s['notify_email']); ?>"><p class="description"><?php esc_html_e('Each location can have its own email. This address is used when the location email is empty.', 'kairoseth-reviews'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Notify when follow-up is required', 'kairoseth-reviews'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[send_low_rating_email]" value="1" <?php checked($s['send_low_rating_email'], 1); ?>> <?php esc_html_e('Send an automatic email', 'kairoseth-reviews'); ?></label></td></tr>
                    </table>
                </section>

                <div class="rw-settings-save"><?php submit_button(__('Save settings', 'kairoseth-reviews'), 'primary', 'submit', false); ?></div>
            </form>
        </div>
        <?php
    }

    public function locations_page() {
        $locations = $this->get_locations();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter.
        $editing_slug = sanitize_title(wp_unslash($_GET['edit'] ?? ''));
        $editing = $editing_slug && isset($locations[$editing_slug]) ? $locations[$editing_slug] : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag after a nonce-protected POST redirect.
        $updated = !empty($_GET['updated']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice flag after a nonce-protected POST redirect.
        $deleted = !empty($_GET['deleted']);
        ?>
        <div class="wrap rw-admin">
            <h1><?php esc_html_e('Kairoseth Reviews — Locations', 'kairoseth-reviews'); ?></h1>
            <p class="rw-admin-intro"><?php esc_html_e('Each location can have its own Google review link and notification email. If you only have one location, you can use the global settings.', 'kairoseth-reviews'); ?></p>

            <?php if ($updated): ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Location saved successfully.', 'kairoseth-reviews'); ?></p></div><?php endif; ?>
            <?php if ($deleted): ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Location deleted successfully.', 'kairoseth-reviews'); ?></p></div><?php endif; ?>

            <h2><?php echo esc_html($editing ? __('Edit location', 'kairoseth-reviews') : __('Add location', 'kairoseth-reviews')); ?></h2>
            <form class="rw-admin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="rw_save_location">
                <input type="hidden" name="old_slug" value="<?php echo esc_attr($editing_slug); ?>">
                <?php wp_nonce_field('rw_save_location'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e('Name', 'kairoseth-reviews'); ?></th><td><input required type="text" class="regular-text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" placeholder="<?php esc_attr_e('Example: Downtown', 'kairoseth-reviews'); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Identifier', 'kairoseth-reviews'); ?></th><td><input required type="text" class="regular-text" name="slug" value="<?php echo esc_attr($editing['slug'] ?? ''); ?>" placeholder="downtown"><p class="description"><?php esc_html_e('Used in the page URL, for example:', 'kairoseth-reviews'); ?> <code>?sede=downtown</code>.</p></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Google review URL', 'kairoseth-reviews'); ?></th><td><input required type="url" class="large-text" name="google_review_url" value="<?php echo esc_attr($editing['google_review_url'] ?? ''); ?>" placeholder="https://g.page/r/.../review"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Notification email', 'kairoseth-reviews'); ?></th><td><input type="email" class="regular-text" name="notify_email" value="<?php echo esc_attr($editing['notify_email'] ?? ''); ?>"><p class="description"><?php esc_html_e('Optional. The global notification email is used when this is empty.', 'kairoseth-reviews'); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e('Status', 'kairoseth-reviews'); ?></th><td><label><input type="checkbox" name="active" value="1" <?php checked($editing ? !empty($editing['active']) : true); ?>> <?php esc_html_e('Active', 'kairoseth-reviews'); ?></label></td></tr>
                </table>
                <?php submit_button($editing ? __('Update location', 'kairoseth-reviews') : __('Add location', 'kairoseth-reviews')); ?>
                <?php if ($editing): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations')); ?>"><?php esc_html_e('Cancel', 'kairoseth-reviews'); ?></a><?php endif; ?>
            </form>

            <h2><?php esc_html_e('Configured locations', 'kairoseth-reviews'); ?></h2>
            <table class="widefat striped rw-admin-table">
                <thead><tr><th><?php esc_html_e('Name', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Identifier', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Google Reviews', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Notification email', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Status', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Actions', 'kairoseth-reviews'); ?></th></tr></thead>
                <tbody>
                <?php if ($locations): foreach ($locations as $slug => $location): ?>
                    <tr>
                        <td><strong><?php echo esc_html($location['name']); ?></strong></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td><a href="<?php echo esc_url($location['google_review_url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open listing', 'kairoseth-reviews'); ?></a></td>
                        <td><?php echo esc_html($location['notify_email'] ?: __('Global email', 'kairoseth-reviews')); ?></td>
                        <td><span class="rw-status-chip <?php echo !empty($location['active']) ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html(!empty($location['active']) ? __('Active', 'kairoseth-reviews') : __('Inactive', 'kairoseth-reviews')); ?></span></td>
                        <td class="rw-actions">
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations&edit=' . rawurlencode($slug))); ?>"><?php esc_html_e('Edit', 'kairoseth-reviews'); ?></a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this location?', 'kairoseth-reviews')); ?>');">
                                <input type="hidden" name="action" value="rw_delete_location">
                                <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                <?php wp_nonce_field('rw_delete_location_' . $slug); ?>
                                <button class="button button-small"><?php esc_html_e('Delete', 'kairoseth-reviews'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="rw-empty" colspan="6"><?php esc_html_e('No locations have been configured yet. If your business has one location, you can continue using the global Google review URL in Settings.', 'kairoseth-reviews'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_location() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not authorized.', 'kairoseth-reviews'));
        check_admin_referer('rw_save_location');

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        $google = esc_url_raw(wp_unslash($_POST['google_review_url'] ?? ''));
        $notify_email = sanitize_email(wp_unslash($_POST['notify_email'] ?? ''));
        $active = !empty($_POST['active']) ? 1 : 0;
        $old_slug = sanitize_title(wp_unslash($_POST['old_slug'] ?? ''));

        if (!$name || !$slug || !$google) {
            wp_safe_redirect(admin_url('admin.php?page=rw-locations'));
            exit;
        }

        $locations = $this->get_locations();
        if ($old_slug && $old_slug !== $slug) unset($locations[$old_slug]);

        $locations[$slug] = [
            'slug' => $slug,
            'name' => $name,
            'google_review_url' => $google,
            'notify_email' => $notify_email,
            'active' => $active,
        ];

        update_option(self::LOCATIONS_KEY, $locations, false);
        wp_safe_redirect(admin_url('admin.php?page=rw-locations&updated=1'));
        exit;
    }

    public function delete_location() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not authorized.', 'kairoseth-reviews'));
        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('rw_delete_location_' . $slug);

        $locations = $this->get_locations();
        unset($locations[$slug]);
        update_option(self::LOCATIONS_KEY, $locations, false);

        wp_safe_redirect(admin_url('admin.php?page=rw-locations&deleted=1'));
        exit;
    }

    private function get_status_label($status) {
        $labels = [
            'low_pending_feedback' => __('Waiting for feedback', 'kairoseth-reviews'),
            'low_feedback_received' => __('Feedback received', 'kairoseth-reviews'),
            'high_sent_to_google' => __('Google review option shown', 'kairoseth-reviews'),
        ];
        return $labels[$status] ?? $status;
    }

    public function entries_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $per_page = 50;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($paged - 1) * $per_page;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameter.
        $selected_location = sanitize_title(wp_unslash($_GET['location'] ?? ''));

        if ($selected_location) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reporting from the plugin's own table.
            $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE location_slug = %s', $table, $selected_location));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reporting from the plugin's own table.
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE location_slug = %s ORDER BY created_at DESC LIMIT %d OFFSET %d', $table, $selected_location, $per_page, $offset));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reporting from the plugin's own table.
            $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only reporting from the plugin's own table.
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d', $table, $per_page, $offset));
        }
        $locations = $this->get_locations();
        ?>
        <div class="wrap rw-admin">
            <h1><?php esc_html_e('Kairoseth Reviews — Feedback', 'kairoseth-reviews'); ?></h1>
            <p class="rw-admin-intro"><?php esc_html_e('Review received ratings and quickly find the cases that need follow-up.', 'kairoseth-reviews'); ?></p>
            <form class="rw-filter-bar" method="get">
                <input type="hidden" name="page" value="rw-feedback">
                <div class="rw-filter-field">
                    <label for="rw-filter-location"><?php esc_html_e('Location', 'kairoseth-reviews'); ?></label>
                    <select id="rw-filter-location" name="location">
                        <option value=""><?php esc_html_e('All locations', 'kairoseth-reviews'); ?></option>
                        <?php foreach ($locations as $slug => $location): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_location, $slug); ?>><?php echo esc_html($location['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button-primary"><?php esc_html_e('Filter', 'kairoseth-reviews'); ?></button>
                <?php if ($selected_location): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-feedback')); ?>"><?php esc_html_e('Clear filter', 'kairoseth-reviews'); ?></a><?php endif; ?>
            </form>
            <p><?php esc_html_e('Total records:', 'kairoseth-reviews'); ?> <strong><?php echo esc_html($total); ?></strong></p>
            <table class="widefat striped rw-admin-table">
                <thead><tr><th>ID</th><th><?php esc_html_e('Date', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Location', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Ref', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Rating', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Reason', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Comment', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Service', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Order', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Agent', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Source', 'kairoseth-reviews'); ?></th><th><?php esc_html_e('Status', 'kairoseth-reviews'); ?></th></tr></thead>
                <tbody>
                <?php if (!empty($rows)): foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->id); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($row->location_name ?: $row->location_slug); ?></td>
                        <td><?php echo esc_html($row->ref_code); ?></td>
                        <td><strong><?php echo esc_html($row->rating); ?>/5</strong></td>
                        <td><?php echo esc_html($row->feedback_reason); ?></td>
                        <td><?php echo esc_html(wp_trim_words($row->feedback_text, 18)); ?></td>
                        <td><?php echo esc_html($row->service_type); ?></td>
                        <td><?php echo esc_html($row->order_id); ?></td>
                        <td><?php echo esc_html($row->tech_name); ?></td>
                        <td><?php echo esc_html($row->source); ?></td>
                        <td><span class="rw-status-chip"><?php echo esc_html($this->get_status_label($row->status)); ?></span></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="rw-empty" colspan="12"><?php esc_html_e('There are no responses to display yet.', 'kairoseth-reviews'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function shortcode($atts = []) {
        $s = $this->get_settings();
        $resolved = $this->resolve_location();
        $location = $resolved['location'];

        $location_slug = $location['slug'] ?? '';
        $location_name = $location['name'] ?? '';
        $google_review_url = $location['google_review_url'] ?? '';

        if (!$google_review_url && empty($this->get_locations(true))) {
            $google_review_url = $s['google_review_url'];
        }

        // Carga directa y robusta desde el shortcode. Esto evita depender del
        // momento en que el theme ejecute wp_enqueue_scripts y garantiza que
        // el JS del formulario llegue al footer incluso en themes/builders.
        wp_enqueue_style(
            'rw-front',
            plugins_url('assets/front.css', __FILE__),
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'rw-front',
            plugins_url('assets/front.js', __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only URL context parameters; no state is changed here.
        $ref = sanitize_text_field(wp_unslash($_GET['ref'] ?? ''));
        $customer_id = sanitize_text_field(wp_unslash($_GET['customer_id'] ?? ''));
        $service_type = sanitize_text_field(wp_unslash($_GET['service'] ?? ''));
        $order_id = sanitize_text_field(wp_unslash($_GET['order_id'] ?? ''));
        $tech_name = sanitize_text_field(wp_unslash($_GET['agent'] ?? ''));
        $source = sanitize_text_field(wp_unslash($_GET['source'] ?? 'WhatsApp'));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $reason_options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $s['reason_options']))));

        wp_localize_script('rw-front', 'ladRfData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lad_rf_nonce'),
            'ratingAction' => 'rw_save_rating',
            'feedbackAction' => 'rw_save_feedback',
            'messages' => [
                'sending' => __('Sending...', 'kairoseth-reviews'),
                'send' => $s['low_button'],
                'error' => __('Could not submit. Please try again.', 'kairoseth-reviews'),
                'ratingGroupLabel' => __('Rate your experience from 1 to 5 stars', 'kairoseth-reviews'),
                'feedbackLabel' => __('Tell us about your experience', 'kairoseth-reviews'),
                'followupSaved' => __('We recorded your feedback so the team can review it and follow up.', 'kairoseth-reviews'),
                'shareGoogleSecondary' => __('Also share my experience on Google', 'kairoseth-reviews'),
                'shareGoogleSecondaryAria' => __('Also share my experience on Google. Opens in a new tab.', 'kairoseth-reviews'),
                'finish' => __('Finish', 'kairoseth-reviews'),
                'savingRating' => __('Saving your rating…', 'kairoseth-reviews'),
                'missingEntry' => __('The rating identifier is missing.', 'kairoseth-reviews'),
                'sendingFeedback' => __('Sending your feedback…', 'kairoseth-reviews'),
            ],
            'context' => [
                'ref' => $ref,
                'customer_id' => $customer_id,
                'service_type' => $service_type,
                'order_id' => $order_id,
                'tech_name' => $tech_name,
                'source' => $source,
                'location_slug' => $location_slug,
                'location_name' => $location_name,
            ]
        ]);

        ob_start(); ?>
        <div class="lad-rf-wrap" style="--lad-rf-primary: <?php echo esc_attr($s['primary_color']); ?>; --lad-rf-accent: <?php echo esc_attr($s['accent_color']); ?>; --lad-rf-bg: <?php echo esc_attr($s['background_color']); ?>; --lad-rf-card: <?php echo esc_attr($s['card_color']); ?>; --lad-rf-text: <?php echo esc_attr($s['text_color']); ?>; --lad-rf-button-text: <?php echo esc_attr($s['button_text_color']); ?>;">
            <div class="lad-rf-card">
                <?php if (!empty($s['logo_url'])): ?><div class="lad-rf-logo"><img src="<?php echo esc_url($s['logo_url']); ?>" alt="<?php echo esc_attr($s['company_name']); ?>"></div><?php endif; ?>

                <?php if ($resolved['error']): ?>
                    <div class="lad-rf-step is-active" role="alert">
                        <h2><?php esc_html_e('We cannot identify the location', 'kairoseth-reviews'); ?></h2>
                        <p><?php echo esc_html($resolved['error']); ?></p>
                    </div>
                <?php else: ?>
                    <div class="lad-rf-step lad-rf-step-rating is-active" aria-hidden="false">
                        <h2 id="lad-rf-rating-title"><?php echo esc_html($s['headline']); ?></h2>
                        <p><?php echo esc_html($s['subheadline']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <div class="lad-rf-stars" role="radiogroup" aria-labelledby="lad-rf-rating-title">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php /* translators: %d is the selected star rating from 1 to 5. */ ?>
                                <button type="button" class="lad-rf-star" data-rating="<?php echo esc_attr($i); ?>" role="radio" aria-checked="false" tabindex="<?php echo $i === 1 ? '0' : '-1'; ?>" aria-label="<?php echo esc_attr(sprintf(__('%d out of 5 stars', 'kairoseth-reviews'), $i)); ?>">★</button>
                            <?php endfor; ?>
                        </div>
                        <div class="lad-rf-status" role="status" aria-live="polite" aria-atomic="true"></div>
                    </div>

                    <div class="lad-rf-step lad-rf-step-low" aria-hidden="true">
                        <h2><?php echo esc_html($s['low_title']); ?></h2>
                        <p><?php echo esc_html($s['low_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>

                        <?php if (!empty($s['show_reason_buttons']) && !empty($reason_options)): ?>
                            <div class="lad-rf-reasons" aria-label="<?php esc_attr_e('Main reason', 'kairoseth-reviews'); ?>">
                                <?php foreach ($reason_options as $reason): ?>
                                    <button type="button" class="lad-rf-reason-btn" data-reason="<?php echo esc_attr($reason); ?>" aria-pressed="false"><?php echo esc_html($reason); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form class="lad-rf-feedback-form">
                            <input type="hidden" name="entry_id" value="">
                            <input type="hidden" name="feedback_reason" value="">
                            <label class="lad-rf-field-label" for="lad-rf-feedback-text"><?php esc_html_e('Tell us about your experience', 'kairoseth-reviews'); ?></label>
                            <textarea id="lad-rf-feedback-text" name="feedback_text" rows="5" placeholder="<?php esc_attr_e('Briefly describe what happened', 'kairoseth-reviews'); ?>"></textarea>
                            <div class="lad-rf-status" role="status" aria-live="polite" aria-atomic="true"></div>
                            <button type="submit" class="lad-rf-btn lad-rf-submit-btn"><?php echo esc_html($s['low_button']); ?></button>
                        </form>
                    </div>

                    <div class="lad-rf-step lad-rf-step-high" aria-hidden="true">
                        <h2><?php echo esc_html($s['high_title']); ?></h2>
                        <p><?php echo esc_html($s['high_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <?php if ($google_review_url): ?>
                            <?php /* translators: %s is the configured Google review button label. */ ?>
                            <a class="lad-rf-btn lad-rf-google-btn" href="<?php echo esc_url($google_review_url); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr(sprintf(__('%s. Opens in a new tab.', 'kairoseth-reviews'), $s['high_button'])); ?>"><?php echo esc_html($s['high_button']); ?></a>
                        <?php else: ?>
                            <div class="lad-rf-status is-error" role="alert"><?php esc_html_e('No Google review URL is configured for this location.', 'kairoseth-reviews'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="lad-rf-step lad-rf-step-thanks" aria-hidden="true">
                        <h2><?php echo esc_html($s['thanks_title']); ?></h2>
                        <p><?php echo esc_html($s['thanks_text']); ?></p>
                    </div>

                    <?php if ($ref): ?><div class="lad-rf-meta">Ref: <?php echo esc_html($ref); ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($s['custom_css'])): ?><style><?php echo wp_kses_post($s['custom_css']); ?></style><?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_save_rating() {
        check_ajax_referer('lad_rf_nonce', 'nonce');
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $s = $this->get_settings();

        $rating = max(1, min(5, intval($_POST['rating'] ?? 0)));
        if (!$rating) wp_send_json_error(['message' => __('Invalid rating.', 'kairoseth-reviews')], 400);

        $location_slug = sanitize_title(wp_unslash($_POST['location_slug'] ?? ''));
        $location_name = sanitize_text_field(wp_unslash($_POST['location_name'] ?? ''));

        if ($location_slug) {
            $locations = $this->get_locations(true);
            if (!isset($locations[$location_slug])) {
                wp_send_json_error(['message' => __('The selected location is invalid.', 'kairoseth-reviews')], 400);
            }
            $location_name = $locations[$location_slug]['name'];
        } else {
            $location_name = '';
        }

        $data = [
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'ref_code' => sanitize_text_field(wp_unslash($_POST['ref'] ?? '')),
            'company_name' => sanitize_text_field($s['company_name']),
            'location_slug' => $location_slug,
            'location_name' => $location_name,
            'rating' => $rating,
            'customer_id' => sanitize_text_field(wp_unslash($_POST['customer_id'] ?? '')),
            'service_type' => sanitize_text_field(wp_unslash($_POST['service_type'] ?? '')),
            'order_id' => sanitize_text_field(wp_unslash($_POST['order_id'] ?? '')),
            'tech_name' => sanitize_text_field(wp_unslash($_POST['tech_name'] ?? '')),
            'source' => sanitize_text_field(wp_unslash($_POST['source'] ?? 'WhatsApp')),
            'user_ip' => '',
            'user_agent' => '',
            'page_url' => '',
            'status' => ($rating <= intval($s['low_rating_threshold'])) ? 'low_pending_feedback' : 'high_sent_to_google',
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes a rating to the plugin's own custom table.
        $inserted = $wpdb->insert($table, $data);
        if (!$inserted) wp_send_json_error(['message' => __('The rating could not be saved.', 'kairoseth-reviews')], 500);

        wp_send_json_success([
            'entry_id' => $wpdb->insert_id,
            'rating' => $rating,
            'is_low' => ($rating <= intval($s['low_rating_threshold'])),
        ]);
    }

    public function ajax_save_feedback() {
        check_ajax_referer('lad_rf_nonce', 'nonce');
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $s = $this->get_settings();

        $entry_id = intval($_POST['entry_id'] ?? 0);
        $feedback_reason = sanitize_text_field(wp_unslash($_POST['feedback_reason'] ?? ''));
        $feedback_text = sanitize_textarea_field(wp_unslash($_POST['feedback_text'] ?? ''));

        if (!$entry_id) wp_send_json_error(['message' => __('Invalid entry.', 'kairoseth-reviews')], 400);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads one workflow record from the plugin's own custom table.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, ref_code, rating, location_slug, location_name FROM %i WHERE id = %d',
                $table,
                $entry_id
            )
        );
        if (!$row) wp_send_json_error(['message' => __('The rating could not be found.', 'kairoseth-reviews')], 404);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Updates one workflow record in the plugin's own custom table.
        $updated = $wpdb->update(
            $table,
            [
                'updated_at' => current_time('mysql'),
                'feedback_reason' => $feedback_reason,
                'feedback_text' => $feedback_text,
                'status' => 'low_feedback_received',
            ],
            ['id' => $entry_id],
            ['%s','%s','%s','%s'],
            ['%d']
        );

        if ($updated === false) wp_send_json_error(['message' => __('The feedback could not be saved.', 'kairoseth-reviews')], 500);

        if (!empty($s['send_low_rating_email'])) {
            $notify_email = sanitize_email($s['notify_email']);
            if (!empty($row->location_slug)) {
                $locations = $this->get_locations();
                if (!empty($locations[$row->location_slug]['notify_email'])) {
                    $notify_email = sanitize_email($locations[$row->location_slug]['notify_email']);
                }
            }

            if ($notify_email) {
                wp_schedule_single_event(
                    time() + 5,
                    'rw_send_low_rating_email',
                    [
                        $notify_email,
                        sanitize_text_field($s['company_name']),
                        (int) $entry_id,
                        sanitize_text_field($row->location_name),
                        sanitize_text_field($row->ref_code),
                        (int) $row->rating,
                        $feedback_reason . ($feedback_text ? "\n\n" . $feedback_text : '')
                    ]
                );
            }
        }

        wp_send_json_success(['message' => __('Feedback saved successfully.', 'kairoseth-reviews'), 'entry_id' => $entry_id]);
    }

    public function send_low_rating_email_async($email, $company_name, $entry_id, $location_name, $ref_code, $rating, $feedback) {
        if (empty($email)) return;
        /* translators: %s is the configured company name. */
        /* translators: %s is the configured company name. */
        $subject = sprintf(__('[%s] New rating received', 'kairoseth-reviews'), $company_name);
        $message = __('A rating that requires follow-up has been received.', 'kairoseth-reviews') . "\n\n";
        /* translators: %d is the feedback entry ID. */
        /* translators: %d is the feedback entry ID. */
        /* translators: %d is the feedback entry ID. */
        /* translators: %d is the feedback entry ID. */
        /* translators: %d is the feedback entry ID. */
        $message .= sprintf(__('ID: %d', 'kairoseth-reviews'), $entry_id) . "\n";
        /* translators: %s is the location name. */
        /* translators: %s is the location name. */
        $message .= sprintf(__('Location: %s', 'kairoseth-reviews'), ($location_name ?: __('No location', 'kairoseth-reviews'))) . "\n";
        /* translators: %s is an optional external reference code. */
        /* translators: %s is an optional external reference code. */
        $message .= sprintf(__('Reference: %s', 'kairoseth-reviews'), $ref_code) . "\n";
        /* translators: %d is the customer rating from 1 to 5. */
        /* translators: %d is the customer rating from 1 to 5. */
        $message .= sprintf(__('Rating: %d/5', 'kairoseth-reviews'), $rating) . "\n";
        /* translators: %s is the customer feedback text. */
        /* translators: %s is the customer feedback text. */
        /* translators: %s is the customer feedback text. */
        /* translators: %s is the customer feedback text. */
        /* translators: %s is the customer feedback text. */
        $message .= sprintf(__('Feedback: %s', 'kairoseth-reviews'), $feedback) . "\n";
        wp_mail($email, $subject, $message);
    }


}

new Kairoseth_Reviews();

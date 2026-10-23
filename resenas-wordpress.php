<?php
/**
 * Plugin Name: Reseñas WordPress
 * Plugin URI: https://kairoseth.com/products/resenas-wordpress
 * Description: Recoge valoraciones y feedback de clientes, gestiona múltiples sedes y facilita el seguimiento de la experiencia desde WordPress.
 * Version: 1.2.0-rc2
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Kairoseth
 * Author URI: https://kairoseth.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: resenas-wordpress
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

class Resenas_WordPress {
    const VERSION = '1.2.0-rc2';
    const OPTION_KEY = 'lad_rf_settings'; // Se conserva para migrar sin perder la configuración 1.0.1.
    const LOCATIONS_KEY = 'rw_locations';
    const SCHEMA_KEY = 'rw_schema_version';
    const TABLE = 'lad_review_funnel_entries'; // Se conserva para no perder respuestas existentes.

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('plugins_loaded', [$this, 'maybe_upgrade']);
        add_action('init', [$this, 'load_textdomain']);

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

    public function load_textdomain() {
        load_plugin_textdomain('resenas-wordpress', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        $this->install_schema();
        if (!get_option(self::OPTION_KEY)) {
            update_option(self::OPTION_KEY, $this->default_settings());
        }
        if (get_option(self::LOCATIONS_KEY, null) === null) {
            add_option(self::LOCATIONS_KEY, []);
        }
        update_option(self::SCHEMA_KEY, self::VERSION);
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
            'headline' => __('¿Qué te ha parecido nuestro servicio?', 'resenas-wordpress'),
            'subheadline' => __('Tu opinión nos ayuda a mejorar.', 'resenas-wordpress'),
            'low_title' => __('Sentimos que tu experiencia no haya sido la mejor', 'resenas-wordpress'),
            'low_text' => __('Cuéntanos qué ha pasado para poder ayudarte y seguir mejorando.', 'resenas-wordpress'),
            'high_title' => __('Muchas gracias por tu valoración', 'resenas-wordpress'),
            'high_text' => __('¿Nos ayudas dejando tu reseña en Google?', 'resenas-wordpress'),
            'thanks_title' => __('Gracias por tu opinión', 'resenas-wordpress'),
            'thanks_text' => __('Hemos recibido tu respuesta correctamente.', 'resenas-wordpress'),
            'low_button' => __('Enviar comentario', 'resenas-wordpress'),
            'high_button' => __('Dejar reseña en Google', 'resenas-wordpress'),
            'show_reason_buttons' => 1,
            'reason_options' => "Atención\nTiempo de espera\nPrecio\nResultado del servicio\nOtro",
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
                'error' => __('La sede indicada no está disponible.', 'resenas-wordpress'),
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
                'error' => __('Este negocio tiene varias sedes. El enlace debe indicar la sede.', 'resenas-wordpress'),
            ];
        }

        return [
            'location' => null,
            'requested_slug' => '',
            'error' => '',
        ];
    }

    public function plugin_links($links) {
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=rw-dashboard')) . '">Dashboard</a>';
        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=rw-settings')) . '">Ajustes</a>';
        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            __('Reseñas WordPress', 'resenas-wordpress'),
            __('Reseñas WordPress', 'resenas-wordpress'),
            'manage_options',
            'rw-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-star-filled',
            58
        );
        add_submenu_page('rw-dashboard', __('Dashboard', 'resenas-wordpress'), __('Dashboard', 'resenas-wordpress'), 'manage_options', 'rw-dashboard', [$this, 'dashboard_page']);
        add_submenu_page('rw-dashboard', __('Feedback', 'resenas-wordpress'), __('Feedback', 'resenas-wordpress'), 'manage_options', 'rw-feedback', [$this, 'entries_page']);
        add_submenu_page('rw-dashboard', __('Sedes', 'resenas-wordpress'), __('Sedes', 'resenas-wordpress'), 'manage_options', 'rw-locations', [$this, 'locations_page']);
        add_submenu_page('rw-dashboard', __('Apariencia y ajustes', 'resenas-wordpress'), __('Apariencia y ajustes', 'resenas-wordpress'), 'manage_options', 'rw-settings', [$this, 'settings_page']);
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
        wp_add_inline_script('wp-color-picker', "
            jQuery(function($){
                $('.lad-rf-color').wpColorPicker();
                $(document).on('click', '.rw-upload-logo', function(e){
                    e.preventDefault();
                    const target = $('#rw_logo_url');
                    const frame = wp.media({title:'Selecciona un logo',button:{text:'Usar logo'},multiple:false});
                    frame.on('select', function(){
                        const attachment = frame.state().get('selection').first().toJSON();
                        target.val(attachment.url);
                    });
                    frame.open();
                });
            });
        ");
    }

    public function dashboard_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $avg = (float) $wpdb->get_var("SELECT AVG(rating) FROM $table WHERE rating IS NOT NULL");
        $low = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE rating <= %d", (int) $this->get_settings()['low_rating_threshold']));
        $locations = $this->get_locations();
        ?>
        <div class="wrap rw-admin">
            <div class="rw-admin-hero">
                <h1>Reseñas WordPress</h1>
                <p>Recoge valoraciones, detecta clientes que necesitan seguimiento y facilita que compartan su experiencia pública desde una página de tu propio WordPress.</p>
            </div>

            <div class="rw-metric-grid" aria-label="Resumen de actividad">
                <?php $cards = [
                    ['Feedback total', $total],
                    ['Valoración media', $total ? number_format_i18n($avg, 2) . ' / 5' : '—'],
                    ['Requieren seguimiento', $low],
                    ['Sedes configuradas', count($locations)],
                ]; foreach ($cards as $card): ?>
                    <div class="rw-metric-card">
                        <span class="rw-metric-label"><?php echo esc_html($card[0]); ?></span>
                        <strong class="rw-metric-value"><?php echo esc_html($card[1]); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rw-admin-grid">
                <section class="rw-admin-panel" aria-labelledby="rw-howto-title">
                    <h2 id="rw-howto-title">Cómo funciona</h2>
                    <p class="rw-admin-intro">El enlace que envías al cliente es la URL de la página de WordPress donde hayas insertado el shortcode.</p>
                    <ol class="rw-howto">
                        <li><strong>Crea una página.</strong> Por ejemplo, una página llamada <em>Reseñas</em>.</li>
                        <li><strong>Inserta el shortcode.</strong> Añade <code>[resenas_wordpress]</code> dentro de esa página y publícala.</li>
                        <li><strong>Configura tu negocio.</strong> Añade logo, textos, enlace de Google y, si lo necesitas, una o varias sedes.</li>
                        <li><strong>Envía la URL.</strong> Comparte por WhatsApp una dirección como <code>https://tuweb.com/resenas/</code>. Puedes añadir <code>?ref=ABC123</code> y, en multisede, <code>?sede=sede-centro</code>.</li>
                    </ol>
                </section>

                <aside class="rw-admin-panel" aria-labelledby="rw-quick-title">
                    <h2 id="rw-quick-title">Acciones rápidas</h2>
                    <p class="rw-admin-intro">Configura el producto y revisa las respuestas desde aquí.</p>
                    <div class="rw-quick-actions">
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rw-settings')); ?>">Apariencia y ajustes</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations')); ?>">Gestionar sedes</a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-feedback')); ?>">Ver feedback</a>
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
                <h1><?php esc_html_e('Apariencia y ajustes', 'resenas-wordpress'); ?></h1>
                <p><?php esc_html_e('Configura la experiencia del cliente, la identidad visual y los avisos de seguimiento sin salir de WordPress.', 'resenas-wordpress'); ?></p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('rw_settings_group'); ?>

                <nav class="rw-settings-nav" aria-label="<?php esc_attr_e('Secciones de ajustes', 'resenas-wordpress'); ?>">
                    <a href="#rw-settings-general"><?php esc_html_e('General', 'resenas-wordpress'); ?></a>
                    <a href="#rw-settings-appearance"><?php esc_html_e('Apariencia', 'resenas-wordpress'); ?></a>
                    <a href="#rw-settings-copy"><?php esc_html_e('Textos', 'resenas-wordpress'); ?></a>
                    <a href="#rw-settings-notifications"><?php esc_html_e('Notificaciones', 'resenas-wordpress'); ?></a>
                </nav>

                <section id="rw-settings-general" class="rw-settings-section" aria-labelledby="rw-settings-general-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-general-title"><?php esc_html_e('General', 'resenas-wordpress'); ?></h2><p><?php esc_html_e('Identidad del negocio, Google y comportamiento del seguimiento.', 'resenas-wordpress'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Nombre de la empresa', 'resenas-wordpress'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[company_name]" value="<?php echo esc_attr($s['company_name']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Logo', 'resenas-wordpress'); ?></th><td><div class="rw-inline-field"><input type="url" class="regular-text" id="rw_logo_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[logo_url]" value="<?php echo esc_attr($s['logo_url']); ?>"><button type="button" class="button rw-upload-logo"><?php esc_html_e('Subir / elegir', 'resenas-wordpress'); ?></button></div></td></tr>
                        <tr><th scope="row"><?php esc_html_e('URL Google global', 'resenas-wordpress'); ?></th><td><input type="url" class="large-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_review_url]" value="<?php echo esc_attr($s['google_review_url']); ?>"><p class="description"><?php esc_html_e('Se utiliza cuando no hay sedes configuradas. En multisede, cada sede puede tener su propia URL.', 'resenas-wordpress'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Umbral de seguimiento', 'resenas-wordpress'); ?></th><td><input type="number" min="1" max="5" name="<?php echo esc_attr(self::OPTION_KEY); ?>[low_rating_threshold]" value="<?php echo esc_attr($s['low_rating_threshold']); ?>"><p class="description"><?php esc_html_e('Las valoraciones iguales o inferiores a este valor pedirán feedback interno para facilitar la recuperación del servicio.', 'resenas-wordpress'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-appearance" class="rw-settings-section" aria-labelledby="rw-settings-appearance-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-art" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-appearance-title"><?php esc_html_e('Apariencia', 'resenas-wordpress'); ?></h2><p><?php esc_html_e('Colores y ajustes visuales del formulario público.', 'resenas-wordpress'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Color principal', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Color acento / estrellas', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr($s['accent_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Color de fondo', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($s['background_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Color de tarjeta', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[card_color]" value="<?php echo esc_attr($s['card_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Color de texto', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Color del texto de botones', 'resenas-wordpress'); ?></th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[button_text_color]" value="<?php echo esc_attr($s['button_text_color']); ?>"></td></tr>
                        <tr><th scope="row"><?php esc_html_e('CSS personalizado', 'resenas-wordpress'); ?></th><td><textarea class="large-text code" rows="8" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_css]"><?php echo esc_textarea($s['custom_css']); ?></textarea><p class="description"><?php esc_html_e('Solo para ajustes avanzados. El diseño base ya está optimizado para móvil y accesibilidad.', 'resenas-wordpress'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-copy" class="rw-settings-section" aria-labelledby="rw-settings-copy-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-copy-title"><?php esc_html_e('Textos', 'resenas-wordpress'); ?></h2><p><?php esc_html_e('Personaliza los mensajes que verá el cliente durante la valoración.', 'resenas-wordpress'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                    <?php
                    $text_fields = [
                        'headline' => __('Título principal', 'resenas-wordpress'),
                        'subheadline' => __('Subtítulo', 'resenas-wordpress'),
                        'low_title' => __('Título para seguimiento', 'resenas-wordpress'),
                        'low_text' => __('Texto para seguimiento', 'resenas-wordpress'),
                        'high_title' => __('Título valoración alta', 'resenas-wordpress'),
                        'high_text' => __('Texto valoración alta', 'resenas-wordpress'),
                        'thanks_title' => __('Título de agradecimiento', 'resenas-wordpress'),
                        'thanks_text' => __('Texto de agradecimiento', 'resenas-wordpress'),
                        'low_button' => __('Texto botón comentario', 'resenas-wordpress'),
                        'high_button' => __('Texto botón Google', 'resenas-wordpress'),
                    ];
                    foreach ($text_fields as $key => $label): ?>
                        <tr><th scope="row"><?php echo esc_html($label); ?></th><td><input type="text" class="large-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></td></tr>
                    <?php endforeach; ?>
                        <tr><th scope="row"><?php esc_html_e('Mostrar motivos rápidos', 'resenas-wordpress'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_reason_buttons]" value="1" <?php checked($s['show_reason_buttons'], 1); ?>> <?php esc_html_e('Sí', 'resenas-wordpress'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Motivos rápidos', 'resenas-wordpress'); ?></th><td><textarea class="large-text" rows="6" name="<?php echo esc_attr(self::OPTION_KEY); ?>[reason_options]"><?php echo esc_textarea($s['reason_options']); ?></textarea><p class="description"><?php esc_html_e('Uno por línea.', 'resenas-wordpress'); ?></p></td></tr>
                    </table>
                </section>

                <section id="rw-settings-notifications" class="rw-settings-section" aria-labelledby="rw-settings-notifications-title">
                    <div class="rw-settings-section-head">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                        <div><h2 id="rw-settings-notifications-title"><?php esc_html_e('Notificaciones', 'resenas-wordpress'); ?></h2><p><?php esc_html_e('Define dónde recibir los avisos cuando una valoración requiere seguimiento.', 'resenas-wordpress'); ?></p></div>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Email global de aviso', 'resenas-wordpress'); ?></th><td><input type="email" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_email]" value="<?php echo esc_attr($s['notify_email']); ?>"><p class="description"><?php esc_html_e('Cada sede puede tener su propio email. Si está vacío, se utiliza este.', 'resenas-wordpress'); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Avisar cuando requiere seguimiento', 'resenas-wordpress'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[send_low_rating_email]" value="1" <?php checked($s['send_low_rating_email'], 1); ?>> <?php esc_html_e('Enviar email automático', 'resenas-wordpress'); ?></label></td></tr>
                    </table>
                </section>

                <div class="rw-settings-save"><?php submit_button(__('Guardar ajustes', 'resenas-wordpress'), 'primary', 'submit', false); ?></div>
            </form>
        </div>
        <?php
    }

    public function locations_page() {
        $locations = $this->get_locations();
        $editing_slug = sanitize_title(wp_unslash($_GET['edit'] ?? ''));
        $editing = $editing_slug && isset($locations[$editing_slug]) ? $locations[$editing_slug] : null;
        ?>
        <div class="wrap rw-admin">
            <h1>Reseñas WordPress — Sedes</h1>
            <p class="rw-admin-intro">Cada sede puede tener su propia ficha de Google y su propio email de avisos. Si solo tienes una ubicación, puedes trabajar con la configuración global.</p>

            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Sede guardada correctamente.</p></div><?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Sede eliminada correctamente.</p></div><?php endif; ?>

            <h2><?php echo $editing ? 'Editar sede' : 'Añadir sede'; ?></h2>
            <form class="rw-admin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="rw_save_location">
                <input type="hidden" name="old_slug" value="<?php echo esc_attr($editing_slug); ?>">
                <?php wp_nonce_field('rw_save_location'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Nombre</th><td><input required type="text" class="regular-text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" placeholder="Ej. Sede Centro"></td></tr>
                    <tr><th scope="row">Identificador</th><td><input required type="text" class="regular-text" name="slug" value="<?php echo esc_attr($editing['slug'] ?? ''); ?>" placeholder="sede-centro"><p class="description">Se utiliza en la URL: <code>?sede=sede-centro</code>.</p></td></tr>
                    <tr><th scope="row">URL de reseña Google</th><td><input required type="url" class="large-text" name="google_review_url" value="<?php echo esc_attr($editing['google_review_url'] ?? ''); ?>" placeholder="https://g.page/r/.../review"></td></tr>
                    <tr><th scope="row">Email de avisos</th><td><input type="email" class="regular-text" name="notify_email" value="<?php echo esc_attr($editing['notify_email'] ?? ''); ?>"><p class="description">Opcional. Si está vacío se usa el email global.</p></td></tr>
                    <tr><th scope="row">Estado</th><td><label><input type="checkbox" name="active" value="1" <?php checked($editing ? !empty($editing['active']) : true); ?>> Activa</label></td></tr>
                </table>
                <?php submit_button($editing ? 'Actualizar sede' : 'Añadir sede'); ?>
                <?php if ($editing): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations')); ?>">Cancelar</a><?php endif; ?>
            </form>

            <h2>Sedes configuradas</h2>
            <table class="widefat striped rw-admin-table">
                <thead><tr><th>Nombre</th><th>Identificador</th><th>Google Reviews</th><th>Email avisos</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if ($locations): foreach ($locations as $slug => $location): ?>
                    <tr>
                        <td><strong><?php echo esc_html($location['name']); ?></strong></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td><a href="<?php echo esc_url($location['google_review_url']); ?>" target="_blank" rel="noopener">Abrir ficha</a></td>
                        <td><?php echo esc_html($location['notify_email'] ?: 'Email global'); ?></td>
                        <td><span class="rw-status-chip <?php echo !empty($location['active']) ? 'is-active' : 'is-inactive'; ?>"><?php echo !empty($location['active']) ? 'Activa' : 'Inactiva'; ?></span></td>
                        <td class="rw-actions">
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=rw-locations&edit=' . rawurlencode($slug))); ?>">Editar</a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('¿Eliminar esta sede?');">
                                <input type="hidden" name="action" value="rw_delete_location">
                                <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                <?php wp_nonce_field('rw_delete_location_' . $slug); ?>
                                <button class="button button-small">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="rw-empty" colspan="6">Todavía no hay sedes. Si tu negocio tiene una sola ubicación, puedes seguir utilizando la URL Google global de Ajustes.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_location() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('No autorizado.', 'resenas-wordpress'));
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
        if (!current_user_can('manage_options')) wp_die(esc_html__('No autorizado.', 'resenas-wordpress'));
        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        check_admin_referer('rw_delete_location_' . $slug);

        $locations = $this->get_locations();
        unset($locations[$slug]);
        update_option(self::LOCATIONS_KEY, $locations, false);

        wp_safe_redirect(admin_url('admin.php?page=rw-locations&deleted=1'));
        exit;
    }

    public function entries_page() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $per_page = 50;
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($paged - 1) * $per_page;
        $selected_location = sanitize_title(wp_unslash($_GET['location'] ?? ''));

        if ($selected_location) {
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE location_slug = %s", $selected_location));
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE location_slug = %s ORDER BY created_at DESC LIMIT %d OFFSET %d", $selected_location, $per_page, $offset));
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
        }
        $locations = $this->get_locations();
        ?>
        <div class="wrap rw-admin">
            <h1>Reseñas WordPress — Feedback</h1>
            <p class="rw-admin-intro">Revisa las valoraciones recibidas y localiza rápidamente los casos que requieren seguimiento.</p>
            <form class="rw-filter-bar" method="get">
                <input type="hidden" name="page" value="rw-feedback">
                <div class="rw-filter-field">
                    <label for="rw-filter-location">Sede</label>
                    <select id="rw-filter-location" name="location">
                        <option value="">Todas las sedes</option>
                        <?php foreach ($locations as $slug => $location): ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_location, $slug); ?>><?php echo esc_html($location['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="button button-primary">Filtrar</button>
                <?php if ($selected_location): ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rw-feedback')); ?>">Limpiar filtro</a><?php endif; ?>
            </form>
            <p>Total registros: <strong><?php echo esc_html($total); ?></strong></p>
            <table class="widefat striped rw-admin-table">
                <thead><tr><th>ID</th><th>Fecha</th><th>Sede</th><th>Ref</th><th>Rating</th><th>Motivo</th><th>Comentario</th><th>Servicio</th><th>Pedido</th><th>Técnico / Comercial</th><th>Origen</th><th>Estado</th></tr></thead>
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
                        <td><span class="rw-status-chip"><?php echo esc_html($row->status); ?></span></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td class="rw-empty" colspan="12">Aún no hay respuestas para mostrar.</td></tr>
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

        $ref = sanitize_text_field(wp_unslash($_GET['ref'] ?? ''));
        $customer_id = sanitize_text_field(wp_unslash($_GET['customer_id'] ?? ''));
        $service_type = sanitize_text_field(wp_unslash($_GET['service'] ?? ''));
        $order_id = sanitize_text_field(wp_unslash($_GET['order_id'] ?? ''));
        $tech_name = sanitize_text_field(wp_unslash($_GET['agent'] ?? ''));
        $source = sanitize_text_field(wp_unslash($_GET['source'] ?? 'WhatsApp'));
        $reason_options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $s['reason_options']))));

        wp_localize_script('rw-front', 'ladRfData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lad_rf_nonce'),
            'ratingAction' => 'rw_save_rating',
            'feedbackAction' => 'rw_save_feedback',
            'messages' => [
                'sending' => __('Enviando...', 'resenas-wordpress'),
                'send' => $s['low_button'],
                'error' => __('No se pudo enviar. Inténtalo de nuevo.', 'resenas-wordpress'),
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
                        <h2>No podemos identificar la sede</h2>
                        <p><?php echo esc_html($resolved['error']); ?></p>
                    </div>
                <?php else: ?>
                    <div class="lad-rf-step lad-rf-step-rating is-active" aria-hidden="false">
                        <h2 id="lad-rf-rating-title"><?php echo esc_html($s['headline']); ?></h2>
                        <p><?php echo esc_html($s['subheadline']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <div class="lad-rf-stars" role="radiogroup" aria-labelledby="lad-rf-rating-title">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="lad-rf-star" data-rating="<?php echo esc_attr($i); ?>" role="radio" aria-checked="false" tabindex="<?php echo $i === 1 ? '0' : '-1'; ?>" aria-label="<?php echo esc_attr($i . ' de 5 estrellas'); ?>">★</button>
                            <?php endfor; ?>
                        </div>
                        <div class="lad-rf-status" role="status" aria-live="polite" aria-atomic="true"></div>
                    </div>

                    <div class="lad-rf-step lad-rf-step-low" aria-hidden="true">
                        <h2><?php echo esc_html($s['low_title']); ?></h2>
                        <p><?php echo esc_html($s['low_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>

                        <?php if (!empty($s['show_reason_buttons']) && !empty($reason_options)): ?>
                            <div class="lad-rf-reasons" aria-label="Motivo principal">
                                <?php foreach ($reason_options as $reason): ?>
                                    <button type="button" class="lad-rf-reason-btn" data-reason="<?php echo esc_attr($reason); ?>" aria-pressed="false"><?php echo esc_html($reason); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form class="lad-rf-feedback-form">
                            <input type="hidden" name="entry_id" value="">
                            <input type="hidden" name="feedback_reason" value="">
                            <label class="lad-rf-field-label" for="lad-rf-feedback-text">Cuéntanos tu experiencia</label>
                            <textarea id="lad-rf-feedback-text" name="feedback_text" rows="5" placeholder="Describe brevemente qué ha ocurrido"></textarea>
                            <div class="lad-rf-status" role="status" aria-live="polite" aria-atomic="true"></div>
                            <button type="submit" class="lad-rf-btn lad-rf-submit-btn"><?php echo esc_html($s['low_button']); ?></button>
                        </form>
                    </div>

                    <div class="lad-rf-step lad-rf-step-high" aria-hidden="true">
                        <h2><?php echo esc_html($s['high_title']); ?></h2>
                        <p><?php echo esc_html($s['high_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <?php if ($google_review_url): ?>
                            <a class="lad-rf-btn lad-rf-google-btn" href="<?php echo esc_url($google_review_url); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($s['high_button'] . '. Se abre en una pestaña nueva.'); ?>"><?php echo esc_html($s['high_button']); ?></a>
                        <?php else: ?>
                            <div class="lad-rf-status is-error" role="alert">No hay una URL de Google configurada para esta sede.</div>
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
        if (!$rating) wp_send_json_error(['message' => __('Valoración inválida.', 'resenas-wordpress')], 400);

        $location_slug = sanitize_title(wp_unslash($_POST['location_slug'] ?? ''));
        $location_name = sanitize_text_field(wp_unslash($_POST['location_name'] ?? ''));

        if ($location_slug) {
            $locations = $this->get_locations(true);
            if (!isset($locations[$location_slug])) {
                wp_send_json_error(['message' => __('La sede indicada no es válida.', 'resenas-wordpress')], 400);
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
            'user_ip' => $this->get_user_ip(),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'page_url' => esc_url_raw(wp_unslash($_POST['page_url'] ?? '')),
            'status' => ($rating <= intval($s['low_rating_threshold'])) ? 'low_pending_feedback' : 'high_sent_to_google',
        ];

        $inserted = $wpdb->insert($table, $data);
        if (!$inserted) wp_send_json_error(['message' => __('No se pudo guardar la valoración.', 'resenas-wordpress')], 500);

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

        if (!$entry_id) wp_send_json_error(['message' => __('Entrada inválida.', 'resenas-wordpress')], 400);

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, ref_code, rating, location_slug, location_name FROM $table WHERE id = %d", $entry_id));
        if (!$row) wp_send_json_error(['message' => __('No se encontró la valoración.', 'resenas-wordpress')], 404);

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

        if ($updated === false) wp_send_json_error(['message' => __('No se pudo guardar el comentario.', 'resenas-wordpress')], 500);

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

        wp_send_json_success(['message' => __('Comentario guardado correctamente.', 'resenas-wordpress'), 'entry_id' => $entry_id]);
    }

    public function send_low_rating_email_async($email, $company_name, $entry_id, $location_name, $ref_code, $rating, $feedback) {
        if (empty($email)) return;
        $subject = sprintf('[%s] Nueva valoración recibida', $company_name);
        $message = "Se ha recibido una valoración que requiere seguimiento.\n\n";
        $message .= "ID: " . $entry_id . "\n";
        $message .= "Sede: " . ($location_name ?: 'Sin sede') . "\n";
        $message .= "Referencia: " . $ref_code . "\n";
        $message .= "Valoración: " . $rating . "/5\n";
        $message .= "Feedback: " . $feedback . "\n";
        wp_mail($email, $subject, $message);
    }

    private function get_user_ip() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = explode(',', $_SERVER[$key])[0];
                return sanitize_text_field(trim($value));
            }
        }
        return '';
    }
}

new Resenas_WordPress();

<?php
/**
 * Plugin Name: Reseñas WordPress
 * Description: Recoge valoraciones y feedback de clientes, gestiona múltiples sedes y dirige cada experiencia a la ficha de Google correspondiente.
 * Version: 1.1.3
 * Author: Kairoseth
 * Author URI: https://kairoseth.com
 * Text Domain: resenas-wordpress
 */

if (!defined('ABSPATH')) exit;

class Resenas_WordPress {
    const VERSION = '1.1.3';
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
            'headline' => '¿Qué te ha parecido nuestro servicio?',
            'subheadline' => 'Tu opinión nos ayuda a mejorar.',
            'low_title' => 'Sentimos que tu experiencia no haya sido la mejor',
            'low_text' => 'Cuéntanos qué ha pasado para poder ayudarte y seguir mejorando.',
            'high_title' => 'Muchas gracias por tu valoración',
            'high_text' => '¿Nos ayudas dejando tu reseña en Google?',
            'thanks_title' => 'Gracias por tu opinión',
            'thanks_text' => 'Hemos recibido tu respuesta correctamente.',
            'low_button' => 'Enviar comentario',
            'high_button' => 'Dejar reseña en Google',
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
                'error' => 'La sede indicada no está disponible.',
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
                'error' => 'Este negocio tiene varias sedes. El enlace debe indicar la sede.',
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
        add_menu_page('Reseñas WordPress', 'Reseñas WordPress', 'manage_options', 'rw-dashboard', [$this, 'dashboard_page'], 'dashicons-star-filled', 58);
        add_submenu_page('rw-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'rw-dashboard', [$this, 'dashboard_page']);
        add_submenu_page('rw-dashboard', 'Feedback', 'Feedback', 'manage_options', 'rw-feedback', [$this, 'entries_page']);
        add_submenu_page('rw-dashboard', 'Sedes', 'Sedes', 'manage_options', 'rw-locations', [$this, 'locations_page']);
        add_submenu_page('rw-dashboard', 'Apariencia y ajustes', 'Apariencia y ajustes', 'manage_options', 'rw-settings', [$this, 'settings_page']);
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
            <h1>Reseñas WordPress</h1>
            <p>Gestiona el feedback de tus clientes y las reseñas de todas tus sedes desde WordPress.</p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin:24px 0;">
                <?php $cards = [
                    ['Feedback total', $total],
                    ['Valoración media', $total ? number_format_i18n($avg, 2) . ' / 5' : '—'],
                    ['Valoraciones bajas', $low],
                    ['Sedes configuradas', count($locations)],
                ]; foreach ($cards as $card): ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 22px;min-width:180px;">
                        <div style="font-size:13px;color:#646970;"><?php echo esc_html($card[0]); ?></div>
                        <div style="font-size:28px;font-weight:700;margin-top:6px;"><?php echo esc_html($card[1]); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <h2>Cómo usarlo</h2>
            <p>Inserta <code>[resenas_wordpress]</code> en la página que quieras utilizar para recoger opiniones.</p>
            <p>Para negocios multisede, envía enlaces como <code>https://tuweb.com/resenas/?sede=sede-centro&ref=ABC123</code>.</p>
        </div>
        <?php
    }

    public function settings_page() {
        $s = $this->get_settings(); ?>
        <div class="wrap">
            <h1>Reseñas WordPress - Apariencia y ajustes</h1>
            <p>Configuración global. Si no creas sedes, la URL de Google global se seguirá utilizando como en la versión anterior.</p>
            <form method="post" action="options.php">
                <?php settings_fields('rw_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Nombre de la empresa</th><td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[company_name]" value="<?php echo esc_attr($s['company_name']); ?>"></td></tr>
                    <tr><th scope="row">Logo</th><td><input type="url" class="regular-text" id="rw_logo_url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[logo_url]" value="<?php echo esc_attr($s['logo_url']); ?>"> <button class="button rw-upload-logo">Subir / elegir</button></td></tr>
                    <tr><th scope="row">URL Google global</th><td><input type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[google_review_url]" value="<?php echo esc_attr($s['google_review_url']); ?>"><p class="description">Se usa cuando no hay sedes configuradas. En multisede, cada sede tiene su propia URL.</p></td></tr>
                    <tr><th scope="row">Umbral valoración baja</th><td><input type="number" min="1" max="5" name="<?php echo esc_attr(self::OPTION_KEY); ?>[low_rating_threshold]" value="<?php echo esc_attr($s['low_rating_threshold']); ?>"></td></tr>
                    <tr><th scope="row">Color principal</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>"></td></tr>
                    <tr><th scope="row">Color acento / estrellas</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accent_color]" value="<?php echo esc_attr($s['accent_color']); ?>"></td></tr>
                    <tr><th scope="row">Color fondo</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($s['background_color']); ?>"></td></tr>
                    <tr><th scope="row">Color tarjeta</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[card_color]" value="<?php echo esc_attr($s['card_color']); ?>"></td></tr>
                    <tr><th scope="row">Color texto</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>"></td></tr>
                    <tr><th scope="row">Color texto botón</th><td><input type="text" class="lad-rf-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[button_text_color]" value="<?php echo esc_attr($s['button_text_color']); ?>"></td></tr>
                    <tr><th colspan="2"><h2>Textos</h2></th></tr>
                    <?php
                    $text_fields = [
                        'headline' => 'Título principal', 'subheadline' => 'Subtítulo',
                        'low_title' => 'Título valoración baja', 'low_text' => 'Texto valoración baja',
                        'high_title' => 'Título valoración alta', 'high_text' => 'Texto valoración alta',
                        'thanks_title' => 'Título gracias', 'thanks_text' => 'Texto gracias',
                        'low_button' => 'Texto botón comentario', 'high_button' => 'Texto botón Google',
                    ];
                    foreach ($text_fields as $key => $label): ?>
                        <tr><th scope="row"><?php echo esc_html($label); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></td></tr>
                    <?php endforeach; ?>
                    <tr><th scope="row">Mostrar motivos rápidos</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_reason_buttons]" value="1" <?php checked($s['show_reason_buttons'], 1); ?>> Sí</label></td></tr>
                    <tr><th scope="row">Motivos rápidos</th><td><textarea class="large-text" rows="6" name="<?php echo esc_attr(self::OPTION_KEY); ?>[reason_options]"><?php echo esc_textarea($s['reason_options']); ?></textarea><p class="description">Uno por línea.</p></td></tr>
                    <tr><th scope="row">Email global de aviso</th><td><input type="email" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_email]" value="<?php echo esc_attr($s['notify_email']); ?>"><p class="description">Cada sede puede tener su propio email. Si no lo tiene, se usa este.</p></td></tr>
                    <tr><th scope="row">Enviar aviso en valoración baja</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[send_low_rating_email]" value="1" <?php checked($s['send_low_rating_email'], 1); ?>> Sí</label></td></tr>
                    <tr><th scope="row">CSS personalizado</th><td><textarea class="large-text code" rows="8" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_css]"><?php echo esc_textarea($s['custom_css']); ?></textarea></td></tr>
                </table>
                <?php submit_button('Guardar ajustes'); ?>
            </form>
        </div>
        <?php
    }

    public function locations_page() {
        $locations = $this->get_locations();
        $editing_slug = sanitize_title(wp_unslash($_GET['edit'] ?? ''));
        $editing = $editing_slug && isset($locations[$editing_slug]) ? $locations[$editing_slug] : null;
        ?>
        <div class="wrap">
            <h1>Reseñas WordPress - Sedes</h1>
            <p>Cada sede puede tener su propia ficha de Google y su propio email de avisos.</p>

            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Sede guardada correctamente.</p></div><?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?><div class="notice notice-success is-dismissible"><p>Sede eliminada correctamente.</p></div><?php endif; ?>

            <h2><?php echo $editing ? 'Editar sede' : 'Añadir sede'; ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:820px;background:#fff;border:1px solid #dcdcde;padding:20px;margin-bottom:28px;">
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
            <table class="widefat striped" style="max-width:1100px;">
                <thead><tr><th>Nombre</th><th>Identificador</th><th>Google Reviews</th><th>Email avisos</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if ($locations): foreach ($locations as $slug => $location): ?>
                    <tr>
                        <td><strong><?php echo esc_html($location['name']); ?></strong></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td><a href="<?php echo esc_url($location['google_review_url']); ?>" target="_blank" rel="noopener">Abrir ficha</a></td>
                        <td><?php echo esc_html($location['notify_email'] ?: 'Email global'); ?></td>
                        <td><?php echo !empty($location['active']) ? 'Activa' : 'Inactiva'; ?></td>
                        <td style="display:flex;gap:8px;align-items:center;">
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
                    <tr><td colspan="6">Todavía no hay sedes. Si tu negocio tiene una sola ubicación, puedes seguir utilizando la URL Google global de Ajustes.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_location() {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
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
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
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
        <div class="wrap">
            <h1>Reseñas WordPress - Feedback</h1>
            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="page" value="rw-feedback">
                <select name="location">
                    <option value="">Todas las sedes</option>
                    <?php foreach ($locations as $slug => $location): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected_location, $slug); ?>><?php echo esc_html($location['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Filtrar</button>
            </form>
            <p>Total registros: <strong><?php echo esc_html($total); ?></strong></p>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Fecha</th><th>Sede</th><th>Ref</th><th>Rating</th><th>Motivo</th><th>Comentario</th><th>Servicio</th><th>Pedido</th><th>Técnico / Comercial</th><th>Origen</th><th>Estado</th></tr></thead>
                <tbody>
                <?php if (!empty($rows)): foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->id); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td><?php echo esc_html($row->location_name ?: $row->location_slug); ?></td>
                        <td><?php echo esc_html($row->ref_code); ?></td>
                        <td><?php echo esc_html($row->rating); ?></td>
                        <td><?php echo esc_html($row->feedback_reason); ?></td>
                        <td><?php echo esc_html(wp_trim_words($row->feedback_text, 18)); ?></td>
                        <td><?php echo esc_html($row->service_type); ?></td>
                        <td><?php echo esc_html($row->order_id); ?></td>
                        <td><?php echo esc_html($row->tech_name); ?></td>
                        <td><?php echo esc_html($row->source); ?></td>
                        <td><?php echo esc_html($row->status); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="12">Aún no hay respuestas.</td></tr>
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
                'sending' => 'Enviando...',
                'send' => $s['low_button'],
                'error' => 'No se pudo enviar. Inténtalo de nuevo.',
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
                    <div class="lad-rf-step is-active">
                        <h2>No podemos identificar la sede</h2>
                        <p><?php echo esc_html($resolved['error']); ?></p>
                    </div>
                <?php else: ?>
                    <div class="lad-rf-step lad-rf-step-rating is-active">
                        <h2><?php echo esc_html($s['headline']); ?></h2>
                        <p><?php echo esc_html($s['subheadline']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <div class="lad-rf-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="lad-rf-star" data-rating="<?php echo esc_attr($i); ?>" aria-label="<?php echo esc_attr($i); ?> estrellas">★</button>
                            <?php endfor; ?>
                        </div>
                        <div class="lad-rf-status" aria-live="polite"></div>
                    </div>

                    <div class="lad-rf-step lad-rf-step-low">
                        <h2><?php echo esc_html($s['low_title']); ?></h2>
                        <p><?php echo esc_html($s['low_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>

                        <?php if (!empty($s['show_reason_buttons']) && !empty($reason_options)): ?>
                            <div class="lad-rf-reasons">
                                <?php foreach ($reason_options as $reason): ?>
                                    <button type="button" class="lad-rf-reason-btn" data-reason="<?php echo esc_attr($reason); ?>"><?php echo esc_html($reason); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form class="lad-rf-feedback-form">
                            <input type="hidden" name="entry_id" value="">
                            <input type="hidden" name="feedback_reason" value="">
                            <textarea name="feedback_text" rows="5" placeholder="Cuéntanos tu experiencia"></textarea>
                            <div class="lad-rf-status" aria-live="polite"></div>
                            <button type="submit" class="lad-rf-btn lad-rf-submit-btn"><?php echo esc_html($s['low_button']); ?></button>
                        </form>
                    </div>

                    <div class="lad-rf-step lad-rf-step-high">
                        <h2><?php echo esc_html($s['high_title']); ?></h2>
                        <p><?php echo esc_html($s['high_text']); ?></p>
                        <?php if ($location_name): ?><div class="lad-rf-location"><?php echo esc_html($location_name); ?></div><?php endif; ?>
                        <?php if ($google_review_url): ?>
                            <a class="lad-rf-btn lad-rf-google-btn" href="<?php echo esc_url($google_review_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($s['high_button']); ?></a>
                        <?php else: ?>
                            <div class="lad-rf-status is-error">No hay una URL de Google configurada para esta sede.</div>
                        <?php endif; ?>
                    </div>

                    <div class="lad-rf-step lad-rf-step-thanks">
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
        if (!$rating) wp_send_json_error(['message' => 'Valoración inválida.'], 400);

        $location_slug = sanitize_title(wp_unslash($_POST['location_slug'] ?? ''));
        $location_name = sanitize_text_field(wp_unslash($_POST['location_name'] ?? ''));

        if ($location_slug) {
            $locations = $this->get_locations(true);
            if (!isset($locations[$location_slug])) {
                wp_send_json_error(['message' => 'La sede indicada no es válida.'], 400);
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
        if (!$inserted) wp_send_json_error(['message' => 'No se pudo guardar la valoración.'], 500);

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

        if (!$entry_id) wp_send_json_error(['message' => 'Entrada inválida.'], 400);

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, ref_code, rating, location_slug, location_name FROM $table WHERE id = %d", $entry_id));
        if (!$row) wp_send_json_error(['message' => 'No se encontró la valoración.'], 404);

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

        if ($updated === false) wp_send_json_error(['message' => 'No se pudo guardar el comentario.'], 500);

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

        wp_send_json_success(['message' => 'Comentario guardado correctamente.', 'entry_id' => $entry_id]);
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

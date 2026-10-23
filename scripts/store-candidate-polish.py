from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# 1) Translation loading hook/method.
needle = "        add_action('plugins_loaded', [$this, 'maybe_upgrade']);\n"
if "[$this, 'load_textdomain']" not in text:
    text = text.replace(needle, needle + "        add_action('init', [$this, 'load_textdomain']);\n", 1)

activate_marker = "    public function activate() {\n"
if "public function load_textdomain()" not in text:
    text = text.replace(
        activate_marker,
        "    public function load_textdomain() {\n"
        "        load_plugin_textdomain('resenas-wordpress', false, dirname(plugin_basename(__FILE__)) . '/languages');\n"
        "    }\n\n" + activate_marker,
        1,
    )

# 2) Translate defaults and user-facing errors/messages.
replacements = {
    "'headline' => '¿Qué te ha parecido nuestro servicio?',": "'headline' => __('¿Qué te ha parecido nuestro servicio?', 'resenas-wordpress'),",
    "'subheadline' => 'Tu opinión nos ayuda a mejorar.',": "'subheadline' => __('Tu opinión nos ayuda a mejorar.', 'resenas-wordpress'),",
    "'low_title' => 'Sentimos que tu experiencia no haya sido la mejor',": "'low_title' => __('Sentimos que tu experiencia no haya sido la mejor', 'resenas-wordpress'),",
    "'low_text' => 'Cuéntanos qué ha pasado para poder ayudarte y seguir mejorando.',": "'low_text' => __('Cuéntanos qué ha pasado para poder ayudarte y seguir mejorando.', 'resenas-wordpress'),",
    "'high_title' => 'Muchas gracias por tu valoración',": "'high_title' => __('Muchas gracias por tu valoración', 'resenas-wordpress'),",
    "'high_text' => '¿Nos ayudas dejando tu reseña en Google?',": "'high_text' => __('¿Nos ayudas dejando tu reseña en Google?', 'resenas-wordpress'),",
    "'thanks_title' => 'Gracias por tu opinión',": "'thanks_title' => __('Gracias por tu opinión', 'resenas-wordpress'),",
    "'thanks_text' => 'Hemos recibido tu respuesta correctamente.',": "'thanks_text' => __('Hemos recibido tu respuesta correctamente.', 'resenas-wordpress'),",
    "'low_button' => 'Enviar comentario',": "'low_button' => __('Enviar comentario', 'resenas-wordpress'),",
    "'high_button' => 'Dejar reseña en Google',": "'high_button' => __('Dejar reseña en Google', 'resenas-wordpress'),",
    "'error' => 'La sede indicada no está disponible.',": "'error' => __('La sede indicada no está disponible.', 'resenas-wordpress'),",
    "'error' => 'Este negocio tiene varias sedes. El enlace debe indicar la sede.',": "'error' => __('Este negocio tiene varias sedes. El enlace debe indicar la sede.', 'resenas-wordpress'),",
    "'sending' => 'Enviando...',": "'sending' => __('Enviando...', 'resenas-wordpress'),",
    "'error' => 'No se pudo enviar. Inténtalo de nuevo.',": "'error' => __('No se pudo enviar. Inténtalo de nuevo.', 'resenas-wordpress'),",
    "wp_die('No autorizado.');": "wp_die(esc_html__('No autorizado.', 'resenas-wordpress'));",
    "['message' => 'Valoración inválida.']": "['message' => __('Valoración inválida.', 'resenas-wordpress')]",
    "['message' => 'La sede indicada no es válida.']": "['message' => __('La sede indicada no es válida.', 'resenas-wordpress')]",
    "['message' => 'No se pudo guardar la valoración.']": "['message' => __('No se pudo guardar la valoración.', 'resenas-wordpress')]",
    "['message' => 'Entrada inválida.']": "['message' => __('Entrada inválida.', 'resenas-wordpress')]",
    "['message' => 'No se encontró la valoración.']": "['message' => __('No se encontró la valoración.', 'resenas-wordpress')]",
    "['message' => 'No se pudo guardar el comentario.']": "['message' => __('No se pudo guardar el comentario.', 'resenas-wordpress')]",
    "['message' => 'Comentario guardado correctamente.', 'entry_id'": "['message' => __('Comentario guardado correctamente.', 'resenas-wordpress'), 'entry_id'",
}
for old, new in replacements.items():
    text = text.replace(old, new)

# 3) Replace admin_menu with gettext-aware labels.
start = text.index("    public function admin_menu() {")
end = text.index("    public function register_settings() {", start)
admin_menu = '''    public function admin_menu() {
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

'''
text = text[:start] + admin_menu + text[end:]

# 4) Replace Settings UI with four clear sections.
start = text.index("    public function settings_page() {")
end = text.index("    public function locations_page() {", start)
settings_page = r'''    public function settings_page() {
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

'''
text = text[:start] + settings_page + text[end:]

PLUGIN.write_text(text, encoding="utf-8")
print("Store Candidate polish applied")

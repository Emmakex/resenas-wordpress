from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# Localize plugin action links.
text = text.replace(
    "$links[] = '<a href=\"' . esc_url(admin_url('admin.php?page=rw-dashboard')) . '\">Dashboard</a>';",
    "$links[] = '<a href=\"' . esc_url(admin_url('admin.php?page=rw-dashboard')) . '\">' . esc_html__('Dashboard', 'resenas-wordpress') . '</a>';",
)
text = text.replace(
    "$links[] = '<a href=\"' . esc_url(admin_url('admin.php?page=rw-settings')) . '\">Ajustes</a>';",
    "$links[] = '<a href=\"' . esc_url(admin_url('admin.php?page=rw-settings')) . '\">' . esc_html__('Ajustes', 'resenas-wordpress') . '</a>';",
)

# Localizable quick reasons for new installs.
text = text.replace(
    "'reason_options' => \"Atención\\nTiempo de espera\\nPrecio\\nResultado del servicio\\nOtro\",",
    "'reason_options' => implode(\"\\n\", [__('Atención', 'resenas-wordpress'), __('Tiempo de espera', 'resenas-wordpress'), __('Precio', 'resenas-wordpress'), __('Resultado del servicio', 'resenas-wordpress'), __('Otro', 'resenas-wordpress')]),",
)

# Expand localized frontend messages passed to JavaScript.
old_messages = """            'messages' => [
                'sending' => __('Enviando...', 'resenas-wordpress'),
                'send' => $s['low_button'],
                'error' => __('No se pudo enviar. Inténtalo de nuevo.', 'resenas-wordpress'),
            ],"""
new_messages = """            'messages' => [
                'sending' => __('Enviando...', 'resenas-wordpress'),
                'send' => $s['low_button'],
                'error' => __('No se pudo enviar. Inténtalo de nuevo.', 'resenas-wordpress'),
                'ratingGroupLabel' => __('Valora tu experiencia de 1 a 5 estrellas', 'resenas-wordpress'),
                'feedbackLabel' => __('Cuéntanos tu experiencia', 'resenas-wordpress'),
                'followupSaved' => __('Hemos registrado tu comentario para que el equipo pueda revisarlo y hacer seguimiento.', 'resenas-wordpress'),
                'shareGoogleSecondary' => __('Compartir también mi experiencia en Google', 'resenas-wordpress'),
                'shareGoogleSecondaryAria' => __('Compartir también mi experiencia en Google. Se abre en una pestaña nueva.', 'resenas-wordpress'),
                'finish' => __('Finalizar', 'resenas-wordpress'),
                'savingRating' => __('Guardando tu valoración…', 'resenas-wordpress'),
                'missingEntry' => __('Falta el identificador de la valoración.', 'resenas-wordpress'),
                'sendingFeedback' => __('Enviando tu comentario…', 'resenas-wordpress'),
            ],"""
if old_messages in text:
    text = text.replace(old_messages, new_messages, 1)

# Localize public static labels rendered by PHP.
replacements = {
    '<h2>No podemos identificar la sede</h2>': "<h2><?php esc_html_e('No podemos identificar la sede', 'resenas-wordpress'); ?></h2>",
    '<div class="lad-rf-reasons" aria-label="Motivo principal">': '<div class="lad-rf-reasons" aria-label="<?php esc_attr_e(\'Motivo principal\', \'resenas-wordpress\'); ?>">',
    '<label class="lad-rf-field-label" for="lad-rf-feedback-text">Cuéntanos tu experiencia</label>': "<label class=\"lad-rf-field-label\" for=\"lad-rf-feedback-text\"><?php esc_html_e('Cuéntanos tu experiencia', 'resenas-wordpress'); ?></label>",
    '<textarea id="lad-rf-feedback-text" name="feedback_text" rows="5" placeholder="Describe brevemente qué ha ocurrido"></textarea>': "<textarea id=\"lad-rf-feedback-text\" name=\"feedback_text\" rows=\"5\" placeholder=\"<?php esc_attr_e('Describe brevemente qué ha ocurrido', 'resenas-wordpress'); ?>\"></textarea>",
    '<div class="lad-rf-status is-error" role="alert">No hay una URL de Google configurada para esta sede.</div>': "<div class=\"lad-rf-status is-error\" role=\"alert\"><?php esc_html_e('No hay una URL de Google configurada para esta sede.', 'resenas-wordpress'); ?></div>",
}
for old, new in replacements.items():
    text = text.replace(old, new)

text = text.replace(
    "aria-label=\"<?php echo esc_attr($i . ' de 5 estrellas'); ?>\"",
    "aria-label=\"<?php echo esc_attr(sprintf(__('%d de 5 estrellas', 'resenas-wordpress'), $i)); ?>\"",
)
text = text.replace(
    "aria-label=\"<?php echo esc_attr($s['high_button'] . '. Se abre en una pestaña nueva.'); ?>\"",
    "aria-label=\"<?php echo esc_attr(sprintf(__('%s. Se abre en una pestaña nueva.', 'resenas-wordpress'), $s['high_button'])); ?>\"",
)

# Privacy/data minimization for new records: retain schema for backwards compatibility,
# but do not store technical identifiers that are not needed for the product workflow.
text = text.replace("            'user_ip' => $this->get_user_ip(),", "            'user_ip' => '',")
text = text.replace("            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),", "            'user_agent' => '',")
text = text.replace("            'page_url' => esc_url_raw(wp_unslash($_POST['page_url'] ?? '')),", "            'page_url' => '',")

# Localize email notification content.
old_email = '''        $subject = sprintf('[%s] Nueva valoración recibida', $company_name);
        $message = "Se ha recibido una valoración que requiere seguimiento.\\n\\n";
        $message .= "ID: " . $entry_id . "\\n";
        $message .= "Sede: " . ($location_name ?: 'Sin sede') . "\\n";
        $message .= "Referencia: " . $ref_code . "\\n";
        $message .= "Valoración: " . $rating . "/5\\n";
        $message .= "Feedback: " . $feedback . "\\n";'''
new_email = '''        $subject = sprintf(__('[%s] Nueva valoración recibida', 'resenas-wordpress'), $company_name);
        $message = __('Se ha recibido una valoración que requiere seguimiento.', 'resenas-wordpress') . "\\n\\n";
        $message .= sprintf(__('ID: %d', 'resenas-wordpress'), $entry_id) . "\\n";
        $message .= sprintf(__('Sede: %s', 'resenas-wordpress'), ($location_name ?: __('Sin sede', 'resenas-wordpress'))) . "\\n";
        $message .= sprintf(__('Referencia: %s', 'resenas-wordpress'), $ref_code) . "\\n";
        $message .= sprintf(__('Valoración: %d/5', 'resenas-wordpress'), $rating) . "\\n";
        $message .= sprintf(__('Feedback: %s', 'resenas-wordpress'), $feedback) . "\\n";'''
if old_email in text:
    text = text.replace(old_email, new_email, 1)

PLUGIN.write_text(text, encoding="utf-8")
print("Frontend i18n and privacy hardening applied")

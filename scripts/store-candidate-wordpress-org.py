from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# WordPress.org directory identity. Keep legacy shortcode/options/table unchanged.
text = text.replace(" * Plugin Name: Reseñas WordPress", " * Plugin Name: Reseñas WordPress", 1)
text = text.replace(
    " * Description: Recoge valoraciones y feedback de clientes, gestiona múltiples sedes y facilita el seguimiento de la experiencia desde WordPress.",
    " * Description: Collect customer ratings and feedback, manage one or multiple locations, and support service recovery workflows.",
    1,
)
text = text.replace(" * Version: 1.2.0-rc2", " * Version: 1.2.0-rc3", 1)
text = text.replace("    const VERSION = '1.2.0-rc2';", "    const VERSION = '1.2.0-rc3';", 1)
text = text.replace(" * Text Domain: resenas-wordpress", " * Text Domain: gestion-resenas", 1)
text = text.replace("'resenas-wordpress'", "'gestion-resenas'")

# WordPress.org loads translations for hosted plugins automatically.
text = text.replace("        add_action('init', [$this, 'load_textdomain']);\n", "")
start = text.find("    public function load_textdomain() {")
if start != -1:
    end = text.find("    public function activate() {", start)
    text = text[:start] + text[end:]

# Remove unused legacy IP helper now that new records no longer store IP addresses.
start = text.find("    private function get_user_ip() {")
if start != -1:
    end = text.find("\n}\n\nnew Resenas_WordPress();", start)
    if end != -1:
        text = text[:start] + text[end:]

# Translator notes required for placeholders.
text = text.replace(
    "                                <button type=\"button\" class=\"lad-rf-star\" data-rating=\"<?php echo esc_attr($i); ?>\" role=\"radio\" aria-checked=\"false\" tabindex=\"<?php echo $i === 1 ? '0' : '-1'; ?>\" aria-label=\"<?php echo esc_attr(sprintf(__('%d de 5 estrellas', 'gestion-resenas'), $i)); ?>\">★</button>",
    "                                <?php /* translators: %d is the selected star rating from 1 to 5. */ ?>\n                                <button type=\"button\" class=\"lad-rf-star\" data-rating=\"<?php echo esc_attr($i); ?>\" role=\"radio\" aria-checked=\"false\" tabindex=\"<?php echo $i === 1 ? '0' : '-1'; ?>\" aria-label=\"<?php echo esc_attr(sprintf(__('%d de 5 estrellas', 'gestion-resenas'), $i)); ?>\">★</button>",
)
text = text.replace(
    "                            <a class=\"lad-rf-btn lad-rf-google-btn\" href=\"<?php echo esc_url($google_review_url); ?>\" target=\"_blank\" rel=\"noopener\" aria-label=\"<?php echo esc_attr(sprintf(__('%s. Se abre en una pestaña nueva.', 'gestion-resenas'), $s['high_button'])); ?>\"><?php echo esc_html($s['high_button']); ?></a>",
    "                            <?php /* translators: %s is the configured Google review button label. */ ?>\n                            <a class=\"lad-rf-btn lad-rf-google-btn\" href=\"<?php echo esc_url($google_review_url); ?>\" target=\"_blank\" rel=\"noopener\" aria-label=\"<?php echo esc_attr(sprintf(__('%s. Se abre en una pestaña nueva.', 'gestion-resenas'), $s['high_button'])); ?>\"><?php echo esc_html($s['high_button']); ?></a>",
)

# Translator notes in notification email.
text = text.replace(
    "        $subject = sprintf(__('[%s] Nueva valoración recibida', 'gestion-resenas'), $company_name);",
    "        /* translators: %s is the configured company name. */\n        $subject = sprintf(__('[%s] Nueva valoración recibida', 'gestion-resenas'), $company_name);",
)
text = text.replace(
    "        $message .= sprintf(__('ID: %d', 'gestion-resenas'), $entry_id) . \"\\n\";",
    "        /* translators: %d is the feedback entry ID. */\n        $message .= sprintf(__('ID: %d', 'gestion-resenas'), $entry_id) . \"\\n\";",
)
text = text.replace(
    "        $message .= sprintf(__('Sede: %s', 'gestion-resenas'), ($location_name ?: __('Sin sede', 'gestion-resenas'))) . \"\\n\";",
    "        /* translators: %s is the location name. */\n        $message .= sprintf(__('Sede: %s', 'gestion-resenas'), ($location_name ?: __('Sin sede', 'gestion-resenas'))) . \"\\n\";",
)
text = text.replace(
    "        $message .= sprintf(__('Referencia: %s', 'gestion-resenas'), $ref_code) . \"\\n\";",
    "        /* translators: %s is an optional external reference code. */\n        $message .= sprintf(__('Referencia: %s', 'gestion-resenas'), $ref_code) . \"\\n\";",
)
text = text.replace(
    "        $message .= sprintf(__('Valoración: %d/5', 'gestion-resenas'), $rating) . \"\\n\";",
    "        /* translators: %d is the customer rating from 1 to 5. */\n        $message .= sprintf(__('Valoración: %d/5', 'gestion-resenas'), $rating) . \"\\n\";",
)
text = text.replace(
    "        $message .= sprintf(__('Feedback: %s', 'gestion-resenas'), $feedback) . \"\\n\";",
    "        /* translators: %s is the customer feedback text. */\n        $message .= sprintf(__('Feedback: %s', 'gestion-resenas'), $feedback) . \"\\n\";",
)

PLUGIN.write_text(text, encoding="utf-8")
print("WordPress.org identity hardening applied")

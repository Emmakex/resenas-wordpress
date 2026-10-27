from pathlib import Path
import re

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# WordPress.org directory identity. Keep legacy shortcode/options/table unchanged.
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
    if end != -1:
        text = text[:start] + text[end:]

# Remove unused legacy IP helper now that new records no longer store IP addresses.
start = text.find("    private function get_user_ip() {")
if start != -1:
    for terminator in ("\n}\n\nnew Resenas_WordPress();", "\n}\n\nnew Gestion_Resenas();"):
        end = text.find(terminator, start)
        if end != -1:
            text = text[:start] + text[end:]
            break

# Collapse duplicate translator notes left by earlier generated runs.
text = re.sub(
    r"(?m)^(\s*/\* translators: .*? \*/\n)(?:\1)+",
    r"\1",
    text,
)


def ensure_note_before(target: str, note: str) -> None:
    global text
    if target not in text:
        return
    intended = note + "\n" + target
    if intended in text:
        return
    text = text.replace(target, intended, 1)


# Translator notes for placeholder-bearing strings. These are deliberately
# idempotent because this script can run more than once on the candidate branch.
ensure_note_before(
    "        $subject = sprintf(__('[%s] Nueva valoración recibida', 'gestion-resenas'), $company_name);",
    "        /* translators: %s is the configured company name. */",
)
ensure_note_before(
    "        $subject = sprintf(__('[%s] New rating received', 'gestion-resenas'), $company_name);",
    "        /* translators: %s is the configured company name. */",
)
ensure_note_before(
    "        $message .= sprintf(__('ID: %d', 'gestion-resenas'), $entry_id) . \"\\n\";",
    "        /* translators: %d is the feedback entry ID. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Sede: %s', 'gestion-resenas'), ($location_name ?: __('Sin sede', 'gestion-resenas'))) . \"\\n\";",
    "        /* translators: %s is the location name. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Location: %s', 'gestion-resenas'), ($location_name ?: __('No location', 'gestion-resenas'))) . \"\\n\";",
    "        /* translators: %s is the location name. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Referencia: %s', 'gestion-resenas'), $ref_code) . \"\\n\";",
    "        /* translators: %s is an optional external reference code. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Reference: %s', 'gestion-resenas'), $ref_code) . \"\\n\";",
    "        /* translators: %s is an optional external reference code. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Valoración: %d/5', 'gestion-resenas'), $rating) . \"\\n\";",
    "        /* translators: %d is the customer rating from 1 to 5. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Rating: %d/5', 'gestion-resenas'), $rating) . \"\\n\";",
    "        /* translators: %d is the customer rating from 1 to 5. */",
)
ensure_note_before(
    "        $message .= sprintf(__('Feedback: %s', 'gestion-resenas'), $feedback) . \"\\n\";",
    "        /* translators: %s is the customer feedback text. */",
)

PLUGIN.write_text(text, encoding="utf-8")
print("WordPress.org identity hardening applied")

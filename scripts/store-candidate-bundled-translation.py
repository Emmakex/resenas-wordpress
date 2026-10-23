from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

hook = "        add_action('plugins_loaded', [$this, 'maybe_upgrade']);\n"
if "[$this, 'maybe_load_bundled_translation']" not in text:
    text = text.replace(
        hook,
        hook + "        add_action('init', [$this, 'maybe_load_bundled_translation'], 0);\n",
        1,
    )

marker = "    public function activate() {\n"
if "public function maybe_load_bundled_translation()" not in text:
    method = r'''    public function maybe_load_bundled_translation() {
        $domain = 'kairoseth-reviews';
        $locale = determine_locale();

        // WordPress.org language packs always take priority when available.
        $language_pack_mo = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
        $language_pack_php = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.l10n.php';
        if (is_readable($language_pack_mo) || is_readable($language_pack_php)) {
            return;
        }

        // Manual ZIP installs can use the bundled catalog until a directory
        // language pack exists. Loading happens at init to avoid early-i18n notices.
        $bundled_mo = plugin_dir_path(__FILE__) . 'languages/' . $domain . '-' . $locale . '.mo';
        if (is_readable($bundled_mo)) {
            load_textdomain($domain, $bundled_mo, $locale);
        }
    }

'''
    text = text.replace(marker, method + marker, 1)

PLUGIN.write_text(text, encoding="utf-8")
print("Bundled translation fallback applied")

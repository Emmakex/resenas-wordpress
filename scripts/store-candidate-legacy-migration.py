from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# The WordPress.org plugin uses a new Store slug/folder. A distinct class name
# prevents a fatal redeclaration if the legacy manually-installed plugin is
# still loaded during the activation request.
text = text.replace("class Resenas_WordPress {", "class Kairoseth_Reviews {", 1)
text = text.replace("new Resenas_WordPress();", "new Kairoseth_Reviews();", 1)

constructor_hook = "        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_links']);\n"
if "[$this, 'legacy_migration_notice']" not in text:
    text = text.replace(
        constructor_hook,
        constructor_hook + "        add_action('admin_notices', [$this, 'legacy_migration_notice']);\n",
        1,
    )

activate_old = '''    public function activate() {
        $this->install_schema();'''
activate_new = '''    public function activate() {
        $this->deactivate_legacy_plugin();
        $this->install_schema();'''
text = text.replace(activate_old, activate_new, 1)

marker = "    public function maybe_upgrade() {\n"
if "private function deactivate_legacy_plugin()" not in text:
    methods = r'''    private function deactivate_legacy_plugin() {
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

        deactivate_plugins($legacy_plugins, true);
        set_transient('kairoseth_reviews_legacy_migrated', 1, 120);
    }

    public function legacy_migration_notice() {
        if (!current_user_can('activate_plugins')) return;
        if (!get_transient('kairoseth_reviews_legacy_migrated')) return;

        delete_transient('kairoseth_reviews_legacy_migrated');
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Kairoseth Reviews activated successfully. The previous Reviews plugin was deactivated and its existing settings, locations, and feedback data were preserved.', 'kairoseth-reviews'); ?></p>
        </div>
        <?php
    }

'''
    text = text.replace(marker, methods + marker, 1)

PLUGIN.write_text(text, encoding="utf-8")
print("Legacy Store-slug migration hardening applied")

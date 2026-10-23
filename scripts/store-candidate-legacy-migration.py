from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# The WordPress.org plugin uses a new Store slug/folder. A distinct class name
# prevents a fatal redeclaration if the legacy manually-installed plugin is
# still loaded during the activation request.
text = text.replace("class Resenas_WordPress {", "class Kairoseth_Reviews {", 1)
text = text.replace("new Resenas_WordPress();", "new Kairoseth_Reviews();", 1)

activate_old = '''    public function activate() {
        $this->install_schema();'''
activate_new = '''    public function activate() {
        $this->deactivate_legacy_plugin();
        $this->install_schema();'''
text = text.replace(activate_old, activate_new, 1)

marker = "    public function maybe_upgrade() {\n"
if "private function deactivate_legacy_plugin()" not in text:
    method = r'''    private function deactivate_legacy_plugin() {
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

'''
    text = text.replace(marker, method + marker, 1)

PLUGIN.write_text(text, encoding="utf-8")
print("Legacy Store-slug migration hardening applied")

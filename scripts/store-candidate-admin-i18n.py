from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")


def replace_function(source: str, name: str, next_name: str, replacement: str) -> str:
    start = source.index(f"    public function {name}(")
    end = source.index(f"    public function {next_name}(", start)
    return source[:start] + replacement.rstrip() + "\n\n" + source[end:]

# Admin asset loading with localized media picker labels.
text = replace_function(text, "enqueue_admin", "dashboard_page", r'''    public function enqueue_admin($hook) {
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
    }''')

text = replace_function(text, "dashboard_page", "settings_page", r'''    public function dashboard_page() {
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
    }''')

text = replace_function(text, "locations_page", "save_location", r'''    public function locations_page() {
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
    }''')

# Add human-readable status labels immediately before entries_page().
start = text.index("    public function entries_page() {")
status_helper = r'''    private function get_status_label($status) {
        $labels = [
            'low_pending_feedback' => __('Waiting for feedback', 'kairoseth-reviews'),
            'low_feedback_received' => __('Feedback received', 'kairoseth-reviews'),
            'high_sent_to_google' => __('Google review option shown', 'kairoseth-reviews'),
        ];
        return $labels[$status] ?? $status;
    }

'''
if "private function get_status_label" not in text:
    text = text[:start] + status_helper + text[start:]

text = replace_function(text, "entries_page", "shortcode", r'''    public function entries_page() {
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
    }''')

PLUGIN.write_text(text, encoding="utf-8")
print("Admin i18n and query hardening applied")

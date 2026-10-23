from pathlib import Path

PLUGIN = Path("resenas-wordpress.php")
text = PLUGIN.read_text(encoding="utf-8")

# Public URL parameters are read-only routing/context, not state-changing form submissions.
needle = "        $requested_slug = sanitize_title(wp_unslash($_GET['sede'] ?? ''));"
if "Public read-only URL routing parameter" not in text:
    text = text.replace(
        needle,
        "        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only URL routing parameter.\n" + needle,
        1,
    )

context_start = "        $ref = sanitize_text_field(wp_unslash($_GET['ref'] ?? ''));"
context_end = "        $source = sanitize_text_field(wp_unslash($_GET['source'] ?? 'WhatsApp'));"
if "Public read-only URL context parameters" not in text:
    text = text.replace(
        context_start,
        "        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only URL context parameters; no state is changed here.\n" + context_start,
        1,
    )
    text = text.replace(
        context_end,
        context_end + "\n        // phpcs:enable WordPress.Security.NonceVerification.Recommended",
        1,
    )

# Custom table writes are intentional; WordPress has no option/meta API equivalent for these records.
insert_line = "        $inserted = $wpdb->insert($table, $data);"
if "Writes a rating to the plugin's own custom table" not in text:
    text = text.replace(
        insert_line,
        "        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes a rating to the plugin's own custom table.\n" + insert_line,
        1,
    )

# Cache the immutable entry context used when feedback is submitted. This keeps
# the custom-table read efficient and satisfies the WordPress object-cache check.
legacy_select = '        $row = $wpdb->get_row($wpdb->prepare("SELECT id, ref_code, rating, location_slug, location_name FROM $table WHERE id = %d", $entry_id));'
prepared_select = """        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads one workflow record from the plugin's own custom table.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, ref_code, rating, location_slug, location_name FROM %i WHERE id = %d',
                $table,
                $entry_id
            )
        );"""
cached_select = """        $cache_key = 'entry_context_' . $entry_id;
        $row = wp_cache_get($cache_key, 'gestion_resenas');
        if (false === $row) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reads one workflow record from the plugin's own custom table; result is cached below.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id, ref_code, rating, location_slug, location_name FROM %i WHERE id = %d',
                    $table,
                    $entry_id
                )
            );
            if ($row) {
                wp_cache_set($cache_key, $row, 'gestion_resenas', HOUR_IN_SECONDS);
            }
        }"""
text = text.replace(legacy_select, cached_select, 1)
text = text.replace(prepared_select, cached_select, 1)

update_line = "        $updated = $wpdb->update("
if "Updates one workflow record in the plugin's own custom table" not in text:
    text = text.replace(
        update_line,
        "        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Updates one workflow record in the plugin's own custom table.\n" + update_line,
        1,
    )

# Repeated generation must stay idempotent.
duplicate_star = "                                <?php /* translators: %d is the selected star rating from 1 to 5. */ ?>\n                                <?php /* translators: %d is the selected star rating from 1 to 5. */ ?>"
text = text.replace(duplicate_star, "                                <?php /* translators: %d is the selected star rating from 1 to 5. */ ?>")
duplicate_google = "                            <?php /* translators: %s is the configured Google review button label. */ ?>\n                            <?php /* translators: %s is the configured Google review button label. */ ?>"
text = text.replace(duplicate_google, "                            <?php /* translators: %s is the configured Google review button label. */ ?>")

PLUGIN.write_text(text, encoding="utf-8")
print("Plugin Check hardening applied")

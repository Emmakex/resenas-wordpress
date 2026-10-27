from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "resenas-wordpress.php"
README = ROOT / "readme.txt"

php = PHP.read_text()

# RC4 identity/version.
php = php.replace("Version: 1.2.0-rc3", "Version: 1.2.0-rc4")
php = php.replace("const VERSION = '1.2.0-rc3';", "const VERSION = '1.2.0-rc4';")

# Premium defaults apply only to fresh installs; saved settings remain authoritative.
php = php.replace("'primary_color' => '#1f2937',", "'primary_color' => '#173b71',")
php = php.replace("'accent_color' => '#f59e0b',", "'accent_color' => '#f6b910',")
php = php.replace("'background_color' => '#ffffff',", "'background_color' => '#f6f9ff',")
php = php.replace("'text_color' => '#111827',", "'text_color' => '#24324a',")

old_cards = """<?php $cards = [
                    [__('Total feedback', 'gestion-resenas'), $total],
                    [__('Average rating', 'gestion-resenas'), $total ? number_format_i18n($avg, 2) . ' / 5' : '—'],
                    [__('Need follow-up', 'gestion-resenas'), $low],
                    [__('Configured locations', 'gestion-resenas'), count($locations)],
                ]; foreach ($cards as $card): ?>
                    <div class=\"rw-metric-card\">
                        <span class=\"rw-metric-label\"><?php echo esc_html($card[0]); ?></span>
                        <strong class=\"rw-metric-value\"><?php echo esc_html($card[1]); ?></strong>
                    </div>
                <?php endforeach; ?>"""
new_cards = """<?php $cards = [
                    [__('Total feedback', 'gestion-resenas'), $total, 'dashicons-star-filled'],
                    [__('Average rating', 'gestion-resenas'), $total ? number_format_i18n($avg, 2) . ' / 5' : '—', 'dashicons-chart-line'],
                    [__('Need follow-up', 'gestion-resenas'), $low, 'dashicons-warning'],
                    [__('Configured locations', 'gestion-resenas'), count($locations), 'dashicons-location-alt'],
                ]; foreach ($cards as $index => $card): ?>
                    <div class=\"rw-metric-card rw-metric-card-<?php echo esc_attr((string) ($index + 1)); ?>\">
                        <span class=\"rw-metric-icon dashicons <?php echo esc_attr($card[2]); ?>\" aria-hidden=\"true\"></span>
                        <span class=\"rw-metric-label\"><?php echo esc_html($card[0]); ?></span>
                        <strong class=\"rw-metric-value\"><?php echo esc_html($card[1]); ?></strong>
                    </div>
                <?php endforeach; ?>"""
if old_cards in php:
    php = php.replace(old_cards, new_cards)

old_locations = """        <div class=\"wrap rw-admin\">
            <h1><?php esc_html_e('Gestión de Reseñas — Locations', 'gestion-resenas'); ?></h1>
            <p class=\"rw-admin-intro\"><?php esc_html_e('Each location can have its own Google review link and notification email. If you only have one location, you can use the global settings.', 'gestion-resenas'); ?></p>
"""
new_locations = """        <div class=\"wrap rw-admin\">
            <div class=\"rw-admin-hero rw-admin-hero-compact\">
                <h1><?php esc_html_e('Gestión de Reseñas — Locations', 'gestion-resenas'); ?></h1>
                <p><?php esc_html_e('Each location can have its own Google review link and notification email. If you only have one location, you can use the global settings.', 'gestion-resenas'); ?></p>
            </div>
"""
if old_locations in php:
    php = php.replace(old_locations, new_locations)

old_feedback = """        <div class=\"wrap rw-admin\">
            <h1><?php esc_html_e('Gestión de Reseñas — Feedback', 'gestion-resenas'); ?></h1>
            <p class=\"rw-admin-intro\"><?php esc_html_e('Review received ratings and quickly find the cases that need follow-up.', 'gestion-resenas'); ?></p>
"""
new_feedback = """        <div class=\"wrap rw-admin\">
            <div class=\"rw-admin-hero rw-admin-hero-compact\">
                <h1><?php esc_html_e('Gestión de Reseñas — Feedback', 'gestion-resenas'); ?></h1>
                <p><?php esc_html_e('Review received ratings and quickly find the cases that need follow-up.', 'gestion-resenas'); ?></p>
            </div>
"""
if old_feedback in php:
    php = php.replace(old_feedback, new_feedback)

old_rating = "<td><strong><?php echo esc_html($row->rating); ?>/5</strong></td>"
new_rating = """<td><span class=\"rw-rating\" aria-label=\"<?php echo esc_attr(sprintf(__('%d out of 5 stars', 'gestion-resenas'), (int) $row->rating)); ?>\"><span class=\"rw-rating-stars\" aria-hidden=\"true\"><?php echo esc_html(str_repeat('★', (int) $row->rating) . str_repeat('☆', max(0, 5 - (int) $row->rating))); ?></span><span class=\"rw-rating-number\"><?php echo esc_html($row->rating); ?>/5</span></span></td>"""
if old_rating in php:
    php = php.replace(old_rating, new_rating)

old_status = "<td><span class=\"rw-status-chip\"><?php echo esc_html($this->get_status_label($row->status)); ?></span></td>"
new_status = """<td><span class=\"rw-status-chip rw-status-<?php echo esc_attr(sanitize_html_class((string) $row->status)); ?>\"><?php echo esc_html($this->get_status_label($row->status)); ?></span></td>"""
if old_status in php:
    php = php.replace(old_status, new_status)

pagination_marker = """            </table>
        </div>
        <?php
    }

    public function shortcode($atts = []) {"""
if 'class="rw-pagination"' not in php and pagination_marker in php:
    pagination = """            </table>
            <?php
            $total_pages = max(1, (int) ceil($total / $per_page));
            if ($total_pages > 1):
                $big = 999999999;
                $pagination_url = add_query_arg(
                    ['page' => 'rw-feedback', 'paged' => $big],
                    admin_url('admin.php')
                );
                if ($selected_location) {
                    $pagination_url = add_query_arg('location', $selected_location, $pagination_url);
                }
                $pagination = paginate_links([
                    'base' => str_replace((string) $big, '%#%', $pagination_url),
                    'format' => '',
                    'current' => $paged,
                    'total' => $total_pages,
                    'type' => 'list',
                ]);
                if ($pagination): ?>
                    <nav class=\"rw-pagination\" aria-label=\"<?php esc_attr_e('Feedback', 'gestion-resenas'); ?>\"><?php echo wp_kses_post($pagination); ?></nav>
                <?php endif;
            endif;
            ?>
        </div>
        <?php
    }

    public function shortcode($atts = []) {"""
    php = php.replace(pagination_marker, pagination)

old_logo = """                <?php if (!empty($s['logo_url'])): ?><div class=\"lad-rf-logo\"><img src=\"<?php echo esc_url($s['logo_url']); ?>\" alt=\"<?php echo esc_attr($s['company_name']); ?>\"></div><?php endif; ?>

                <?php if ($resolved['error']): ?>"""
new_logo = """                <?php if (!empty($s['logo_url'])): ?>
                    <div class=\"lad-rf-logo\"><img src=\"<?php echo esc_url($s['logo_url']); ?>\" alt=\"<?php echo esc_attr($s['company_name']); ?>\"></div>
                <?php elseif (!empty($s['company_name'])): ?>
                    <div class=\"lad-rf-business-name\"><span class=\"lad-rf-business-mark\" aria-hidden=\"true\">★</span><span><?php echo esc_html($s['company_name']); ?></span></div>
                <?php endif; ?>

                <?php if ($resolved['error']): ?>"""
if old_logo in php:
    php = php.replace(old_logo, new_logo)

PHP.write_text(php)

readme = README.read_text()
readme = readme.replace("Stable tag: 1.2.0-rc3", "Stable tag: 1.2.0-rc4")
preview_lines = """= 1.2.0 =
* RC4 production preview aligns the real frontend and admin UI with the final premium visual direction.
* Added admin feedback pagination and visual star/status presentation.
"""
if "RC4 production preview" not in readme:
    readme = readme.replace("= 1.2.0 =\n", preview_lines)
README.write_text(readme)

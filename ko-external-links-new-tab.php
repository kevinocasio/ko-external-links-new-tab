<?php
/**
 * Plugin Name: KO External Links New Tab
 * Plugin URI:  https://kevinocasio.com/tools/ko-external-links-new-tab/
 * Description: Automatically opens outbound external links in a new browser tab with secure rel="noopener noreferrer" attributes.
 * Version:     1.0.0
 * Author:      Kevin Ocasio
 * Author URI:  https://kevinocasio.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ko-external-links-new-tab
 */

if (!defined('ABSPATH')) {
	exit;
}

define('KO_ELNT_VERSION', '1.0.0');
define('KO_ELNT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KO_ELNT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Helper: Smart Brand URL Resolver (Keeps links local on .local, points to live domain on production)
 */
function ko_elnt_brand_url($path = '/tools/') {
	if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '.local') !== false) {
		return home_url($path);
	}
	return 'https://kevinocasio.com' . $path;
}

/**
 * 1. Activation: Seed Default Option
 */
function ko_elnt_activate()
{
	if (get_option('ko_elnt_enabled') === false) {
		update_option('ko_elnt_enabled', '1');
	}
}
register_activation_hook(__FILE__, 'ko_elnt_activate');

/**
 * 2. Core Logic: Parse External Outbound Links
 */
function ko_elnt_filter_content($content)
{
	if ((int) get_option('ko_elnt_enabled', '1') !== 1 || empty($content)) {
		return $content;
	}

	$site_host = wp_parse_url(home_url(), PHP_URL_HOST);
	$pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1(?:[^>]*?)>/i';

	return preg_replace_callback($pattern, function ($matches) use ($site_host) {
		$original_tag = $matches[0];
		$url = $matches[2];

		// Skip relative links, anchors, or mailto/tel
		if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
			return $original_tag;
		}

		$link_host = wp_parse_url($url, PHP_URL_HOST);

		// Skip internal links pointing to own domain
		if (empty($link_host) || strtolower($link_host) === strtolower($site_host)) {
			return $original_tag;
		}

		// Skip if target already exists
		if (stripos($original_tag, 'target=') !== false) {
			return $original_tag;
		}

		// Inject target="_blank" and secure rel attributes
		$new_attrs = ' target="_blank" rel="noopener noreferrer"';
		return str_replace('>', $new_attrs . '>', $original_tag);
	}, $content);
}
add_filter('the_content', 'ko_elnt_filter_content', 20);
add_filter('widget_text', 'ko_elnt_filter_content', 20);

/**
 * 3. Admin Menu Placement (KO Plugins Suite at Position 2)
 */
function ko_elnt_admin_menu()
{
	// Idempotent Parent Menu Registration
	if (empty($GLOBALS['admin_page_hooks']['ko-plugins-main'])) {
		$icon_url = plugins_url('assets/favicon.svg', __FILE__);

		add_menu_page(
			'KO Plugins',
			'KO Plugins',
			'manage_options',
			'ko-plugins-main',
			'ko_plugins_suite_dashboard_html',
			$icon_url,
			2
		);

		// Explicitly rename first child to Dashboard
		add_submenu_page(
			'ko-plugins-main',
			'KO Plugins',
			'Dashboard',
			'manage_options',
			'ko-plugins-main',
			'ko_plugins_suite_dashboard_html'
		);
	}
}
add_action('admin_menu', 'ko_elnt_admin_menu');

/**
 * 4. AJAX Handler for Instant Dashboard Toggle
 */
function ko_elnt_ajax_toggle_status()
{
	check_ajax_referer('ko_elnt_toggle_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => 'Unauthorized'), 403);
	}

	$enabled = isset($_POST['enabled']) && '1' === $_POST['enabled'] ? '1' : '0';
	update_option('ko_elnt_enabled', $enabled);

	wp_send_json_success(array(
		'enabled' => $enabled,
		'message' => 'Saved!',
	));
}
add_action('wp_ajax_ko_elnt_toggle_status', 'ko_elnt_ajax_toggle_status');

/**
 * 5. Action Link on Plugins Screen
 */
function ko_elnt_settings_action_link($links)
{
	$settings_url = admin_url('admin.php?page=ko-plugins-main');
	$settings_link = '<a href="' . esc_url($settings_url) . '">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ko_elnt_settings_action_link');

/**
 * 6. Conditional Admin Asset Enqueuing (Strict Anti-Bloat)
 */
function ko_elnt_enqueue_admin_assets($hook)
{
	if ('toplevel_page_ko-plugins-main' !== $hook) {
		return;
	}
	$ver = file_exists(KO_ELNT_PLUGIN_DIR . 'assets/admin.css') ? filemtime(KO_ELNT_PLUGIN_DIR . 'assets/admin.css') : KO_ELNT_VERSION;
	wp_enqueue_style('ko-elnt-admin-css', plugins_url('assets/admin.css', __FILE__), array(), $ver);
}
add_action('admin_enqueue_scripts', 'ko_elnt_enqueue_admin_assets');

/**
 * 9. Master KO Plugins Suite Dashboard Callback (17-Plugin Mission Control Grid)
 */
if (!function_exists('ko_plugins_suite_dashboard_html')) {
	function ko_plugins_suite_dashboard_html()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$hub_url = ko_elnt_brand_url('/tools/');
		$author_url = ko_elnt_brand_url('/');

		// The 17 Plugin Registry with Live AJAX Config
		$suite_plugins = array(
			array(
				'name' => 'KO Admin Bar Hider',
				'slug' => 'ko-admin-bar-hider',
				'file' => 'ko-admin-bar-hider/ko-admin-bar-hider.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_abh_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_abh_toggle_nonce'),
				'toggle_label' => 'Hide Toolbar',
				'toggle_val' => (int) get_option('ko_abh_enabled', '1'),
				'wporg' => false,
				'desc' => 'Hides the WordPress admin bar for non-administrators on the front end and cleans up dashboard toolbar clutter.',
			),
			array(
				'name' => 'KO Admin Username Changer',
				'slug' => 'ko-admin-username-changer',
				'file' => 'ko-admin-username-changer/ko-admin-username-changer.php',
				'type' => 'action-page',
				'settings_page' => 'ko-admin-username-changer',
				'action_label' => 'Change Username &rarr;',
				'wporg' => false,
				'desc' => 'Safely changes the default admin or any existing username in the database without SQL scripts.',
			),
			array(
				'name' => 'KO Auto Copyright Year',
				'slug' => 'ko-auto-copyright-year',
				'file' => 'ko-auto-copyright-year/ko-auto-copyright-year.php',
				'type' => 'action-page',
				'settings_page' => 'ko-auto-copyright-year',
				'action_label' => 'View Shortcodes &rarr;',
				'wporg' => false,
				'desc' => 'Keeps footer copyright notices automatically up to date every year using a simple, dynamic shortcode.',
			),
			array(
				'name' => 'KO Clean Image Filenames',
				'slug' => 'ko-clean-image-filenames',
				'file' => 'ko-clean-image-filenames/ko-clean-image-filenames.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_cif_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_cif_toggle_nonce'),
				'toggle_label' => 'Auto-Clean Names',
				'toggle_val' => (int) get_option('ko_cif_enabled', '1'),
				'wporg' => false,
				'desc' => 'Automatically sanitizes uploaded media file names, removing special characters and spaces for better SEO.',
			),
			array(
				'name' => 'KO Comment Link Remover',
				'slug' => 'ko-comment-link-remover',
				'file' => 'ko-comment-link-remover/ko-comment-link-remover.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_clr_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_clr_toggle_nonce'),
				'toggle_label' => 'Remove Website Field',
				'toggle_val' => (int) get_option('ko_clr_enabled', '1'),
				'wporg' => false,
				'desc' => 'Strips author website links and URLs from WordPress comments to eliminate backlink spam.',
			),
			array(
				'name' => 'KO Disable Comments Globally',
				'slug' => 'ko-disable-comments-globally',
				'file' => 'ko-disable-comments-globally/ko-disable-comments-globally.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_dcg_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_dcg_toggle_nonce'),
				'toggle_label' => 'Disable Comments',
				'toggle_val' => (int) get_option('ko_dcg_enabled', '1'),
				'wporg' => false,
				'desc' => 'Completely disables and removes the WordPress comment system across all posts, pages, and media.',
			),
			array(
				'name' => 'KO Disable Emojis',
				'slug' => 'ko-disable-emojis',
				'file' => 'ko-disable-emojis/ko-disable-emojis.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_de_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_de_toggle_nonce'),
				'toggle_label' => 'Disable Emoji Scripts',
				'toggle_val' => (int) get_option('ko_de_enabled', '1'),
				'wporg' => false,
				'desc' => 'Removes extra emoji scripts, inline styles, and DNS prefetch links to speed up page load times.',
			),
			array(
				'name' => 'KO Disable Gutenberg',
				'slug' => 'ko-disable-gutenberg',
				'file' => 'ko-disable-gutenberg/ko-disable-gutenberg.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_dg_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_dg_toggle_nonce'),
				'toggle_label' => 'Restore Classic Editor',
				'toggle_val' => (int) get_option('ko_dg_enabled', '1'),
				'wporg' => false,
				'desc' => 'Restores the Classic Editor cleanly and prevents block editor CSS stylesheets from loading.',
			),
			array(
				'name' => 'KO Disable XML-RPC',
				'slug' => 'ko-disable-xml-rpc',
				'file' => 'ko-disable-xml-rpc/ko-disable-xml-rpc.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_dx_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_dx_toggle_nonce'),
				'toggle_label' => 'Block XML-RPC',
				'toggle_val' => (int) get_option('ko_dx_enabled', '1'),
				'wporg' => false,
				'desc' => 'Blocks the XML-RPC endpoint to protect your website against brute-force login attacks and pingback exploits.',
			),
			array(
				'name' => 'KO Duplicate Post Button',
				'slug' => 'ko-duplicate-post-button',
				'file' => 'ko-duplicate-post-button/ko-duplicate-post-button.php',
				'type' => 'action-page',
				'settings_page' => 'ko-duplicate-post-button',
				'action_label' => 'Configure &rarr;',
				'wporg' => false,
				'desc' => 'Adds a 1-click clone button to posts, pages, and custom post types while keeping all custom fields.',
			),
			array(
				'name' => 'KO Estimated Reading Time',
				'slug' => 'ko-estimated-reading-time',
				'file' => 'ko-estimated-reading-time/ko-estimated-reading-time.php',
				'type' => 'action-page',
				'settings_page' => 'ko-estimated-reading-time',
				'action_label' => 'Configure &rarr;',
				'wporg' => false,
				'desc' => 'Calculates and displays article reading time with custom shortcodes and automatic content placement.',
			),
			array(
				'name' => 'KO External Links New Tab',
				'slug' => 'ko-external-links-new-tab',
				'file' => 'ko-external-links-new-tab/ko-external-links-new-tab.php',
				'type' => 'micro-toggle',
				'ajax_action' => 'ko_elnt_toggle_status',
				'ajax_nonce' => wp_create_nonce('ko_elnt_toggle_nonce'),
				'toggle_label' => 'Open in New Tab',
				'toggle_val' => (int) get_option('ko_elnt_enabled', '1'),
				'wporg' => false,
				'desc' => 'Automatically opens outbound links in a new browser tab with secure rel="noopener" attributes.',
			),
			array(
				'name' => 'KO Hide Version',
				'slug' => 'ko-hide-version',
				'file' => 'ko-hide-version/ko-hide-version.php',
				'type' => 'action-page',
				'settings_page' => 'ko-hide-version',
				'action_label' => 'Configure &rarr;',
				'wporg' => false,
				'desc' => 'Strips WordPress version generator meta tags from page headers, scripts, and RSS feeds for security.',
			),
			array(
				'name' => 'KO Limit Login Attempts',
				'slug' => 'ko-limit-login-attempts',
				'file' => 'ko-limit-login-attempts/ko-limit-login-attempts.php',
				'type' => 'action-page',
				'settings_page' => 'ko-limit-login-attempts',
				'action_label' => 'Configure Security &rarr;',
				'wporg' => false,
				'desc' => 'Blocks IP addresses after repeated failed password attempts to stop automated brute-force attacks.',
			),
			array(
				'name' => 'KO Show Current Template',
				'slug' => 'ko-show-current-template',
				'file' => 'ko-show-current-template/ko-show-current-template.php',
				'type' => 'action-page',
				'settings_page' => 'ko-show-current-template',
				'action_label' => 'Configure &rarr;',
				'wporg' => false,
				'desc' => 'Displays the active PHP theme template file name in the top admin bar for administrators.',
			),
			array(
				'name' => 'KO Simple 301 Redirects',
				'slug' => 'ko-simple-301-redirects',
				'file' => 'ko-simple-301-redirects/ko-simple-301-redirects.php',
				'type' => 'action-page',
				'settings_page' => 'ko-simple-301-redirects',
				'action_label' => 'Manage Redirects &rarr;',
				'wporg' => false,
				'desc' => 'Lightweight 301 redirect manager to fix broken links and handle URL migrations without database bloat.',
			),
			array(
				'name' => 'KO Simple Maintenance Mode',
				'slug' => 'ko-simple-maintenance-mode',
				'file' => 'ko-simple-maintenance-mode/ko-simple-maintenance-mode.php',
				'type' => 'action-page',
				'settings_page' => 'ko-simple-maintenance-mode',
				'action_label' => 'Configure Maintenance &rarr;',
				'wporg' => false,
				'desc' => 'Clean 503 maintenance splash screen for site redesigns and launch countdowns.',
			),
		);

		// Calculate active count
		$active_count = 0;
		foreach ($suite_plugins as $p) {
			if (is_plugin_active($p['file'])) {
				$active_count++;
			}
		}
		?>
		<div class="wrap ko-dash-wrap">
			<!-- Hidden heading so WordPress outputs third-party notices above the dashboard -->
			<h1 class="wp-heading-inline" style="display:none;"></h1>

			<!-- Dashboard Hero Banner -->
			<div class="ko-dash-hero">
				<div class="ko-dash-hero-left">
					<h1>
						<span class="ko-dash-logo">
							<span class="ko-logo-k">K</span><span class="ko-logo-o">O</span><span class="ko-logo-text">PLUGINS</span>
						</span>
					</h1>
				</div>
				<div class="ko-dash-hero-right">
					<span class="ko-dash-count-pill"><?php echo esc_html($active_count); ?> of <?php echo count($suite_plugins); ?> Plugins Active</span>
				</div>
			</div>

			<!-- 17-Plugin Grid -->
			<div class="ko-dash-grid">
				<?php foreach ($suite_plugins as $plugin):
					$is_active = is_plugin_active($plugin['file']);
					$is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin['file']);
					$plugin_url = ko_elnt_brand_url('/tools/' . $plugin['slug'] . '/');
					?>
					<div class="ko-dash-card" id="<?php echo esc_attr($plugin['slug']); ?>">
						<div>
							<div class="ko-dash-card-header">
								<h3 class="ko-dash-card-title"><?php echo esc_html($plugin['name']); ?></h3>
								<?php if ($is_active): ?>
									<span class="ko-dash-badge badge-active">Active</span>
								<?php elseif ($is_installed): ?>
									<span class="ko-dash-badge badge-inactive">Inactive</span>
								<?php else: ?>
									<span class="ko-dash-badge badge-available">Available</span>
								<?php endif; ?>
							</div>
							<p class="ko-dash-card-desc"><?php echo esc_html($plugin['desc']); ?></p>
						</div>

						<div class="ko-dash-card-footer">
							<?php if ($is_active): ?>
								<?php if (isset($plugin['type']) && 'micro-toggle' === $plugin['type']): ?>
									<div class="ko-dash-card-toggle-row">
										<span class="ko-dash-toggle-label"><?php echo esc_html($plugin['toggle_label'] ?? 'Enable Feature'); ?></span>
										<div class="ko-dash-toggle-action">
											<span class="ko-dash-saved-pill" id="feedback-<?php echo esc_attr($plugin['slug']); ?>" style="display:none;">Saved!</span>
											<label class="ko-switch">
												<input type="checkbox"
													class="ko-suite-live-toggle"
													data-slug="<?php echo esc_attr($plugin['slug']); ?>"
													data-action="<?php echo esc_attr($plugin['ajax_action']); ?>"
													data-nonce="<?php echo esc_attr($plugin['ajax_nonce']); ?>"
													<?php checked($plugin['toggle_val'] ?? 1, 1); ?>>
												<span class="ko-slider"></span>
											</label>
										</div>
									</div>
								<?php else: ?>
									<a href="<?php echo esc_url(admin_url('admin.php?page=' . ($plugin['settings_page'] ?? 'ko-plugins-main'))); ?>" class="ko-dash-btn-primary"><?php echo esc_html($plugin['action_label'] ?? 'Configure Settings &rarr;'); ?></a>
								<?php endif; ?>
							<?php elseif ($is_installed): ?>
								<?php
								$activate_url = wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . urlencode($plugin['file'])), 'activate-plugin_' . $plugin['file']);
								?>
								<a href="<?php echo esc_url($activate_url); ?>" class="ko-dash-btn-activate">Activate</a>
							<?php elseif (!empty($plugin['wporg'])): ?>
								<?php
								$install_url = wp_nonce_url(admin_url('update.php?action=install-plugin&plugin=' . urlencode($plugin['slug'])), 'install-plugin_' . $plugin['slug']);
								?>
								<a href="<?php echo esc_url($install_url); ?>" class="ko-dash-btn-activate">Install Now</a>
								<a href="<?php echo esc_url($plugin_url); ?>" target="_blank" rel="noopener noreferrer" class="ko-dash-btn-outline">Learn More &rarr;</a>
							<?php else: ?>
								<a href="<?php echo esc_url($plugin_url); ?>" target="_blank" rel="noopener noreferrer" class="ko-dash-btn-outline">Learn More &rarr;</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Global Footer -->
			<div class="ko-dash-global-footer">
				<p><a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">Kevin Ocasio</a> built this and other <a href="<?php echo esc_url($hub_url); ?>" target="_blank" rel="noopener noreferrer">WordPress plugins</a>.</p>
			</div>
		</div>

		<!-- Suite Universal AJAX Toggle Script -->
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var toggles = document.querySelectorAll('.ko-suite-live-toggle');
			toggles.forEach(function(toggle) {
				toggle.addEventListener('change', function() {
					var slug = this.getAttribute('data-slug');
					var action = this.getAttribute('data-action');
					var nonce = this.getAttribute('data-nonce');
					var feedback = document.getElementById('feedback-' + slug);
					var isChecked = this.checked ? '1' : '0';

					var formData = new FormData();
					formData.append('action', action);
					formData.append('nonce', nonce);
					formData.append('enabled', isChecked);

					fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
						method: 'POST',
						body: formData
					})
					.then(function(res) { return res.json(); })
					.then(function(data) {
						if (data.success && feedback) {
							feedback.style.display = 'inline-block';
							setTimeout(function() {
								feedback.style.display = 'none';
							}, 1800);
						}
					})
					.catch(function(err) {
						console.error('KO Suite toggle error for ' + slug + ':', err);
					});
				});
			});
		});
		</script>
		<?php
	}
}


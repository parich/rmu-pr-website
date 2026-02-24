<?php
/**
 * Plugin Name:       RMU PR Website
 * Plugin URI:        https://github.com/parich/rmu-pr-website
 * Description:       แสดงข่าวจากเว็บไซต์ มหาวิทยาลัย.
 * Version:           0.1.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Mr.Parich Suriya
 * Author URI:        https://github.com/parich
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rmu-pr-website
 *
 * @package CreateBlock
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function create_block_rmu_pr_website_block_init()
{
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if (function_exists('wp_register_block_types_from_metadata_collection')) {
		wp_register_block_types_from_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if (function_exists('wp_register_block_metadata_collection')) {
		wp_register_block_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach (array_keys($manifest_data) as $block_type) {
		register_block_type(__DIR__ . "/build/{$block_type}");
	}
}
add_action('init', 'create_block_rmu_pr_website_block_init');

// ฟังก์ชันนี้ใช้สำหรับการโหลดไฟล์ JavaScript และ CSS ที่จำเป็นสำหรับ block
function rmu_pr_website_enqueue_assets()
{
	if (is_singular() && has_shortcode(get_post()->post_content, 'rmu_pr_website')) {
		wp_enqueue_style(
			'rmu-pr-website-style',
			plugins_url('build/rmu-pr-website/style-index.css', __FILE__),
			array(),
			'1.0'
		);
		wp_enqueue_script(
			'rmu-pr-website-view',
			plugins_url('build/rmu-pr-website/view.js', __FILE__),
			array(),
			'1.0',
			true
		);

		// ดึงค่าตัวเลือกจากฐานข้อมูล
		$category_slugs = get_option('rmu_pr_website_category_slugs', '');
		$base_url = get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/');
		// แปลง category slugs เป็น array ของ object [{slug:..., name:...}, ...]
		$cats = array_filter(array_map('trim', explode(',', $category_slugs)));
		$cat_objs = array();
		foreach ($cats as $cat) {
			$cat_objs[] = array('slug' => $cat, 'name' => $cat);
		}
		// ส่งข้อมูลไปยัง JavaScript
		wp_localize_script('rmu-pr-website-view', 'POSTS_PR_RMU_DATA', array(
			'categorySlugs' => $cat_objs,
			'baseUrl' => $base_url,
		));

	}
}
add_action('wp_enqueue_scripts', 'rmu_pr_website_enqueue_assets');


/**
 * Shortcode สำหรับแสดงผล block
 */
function rmu_pr_website_shortcode($atts)
{
	ob_start();
	$render_file = plugin_dir_path(__FILE__) . 'build/rmu-pr-website/render.php';
	if (file_exists($render_file)) {
		include $render_file;
	} else {
		echo '<!-- rmu-pr-website render.php not found -->';
	}
	return ob_get_clean();
}
add_shortcode('rmu_pr_website', 'rmu_pr_website_shortcode');

add_action('admin_menu', function () {
	add_options_page(
		'RMU PR Settings',
		'RMU PR Website',
		'manage_options',
		'rmu_pr_website_settings',
		'rmu_pr_website_settings_page'
	);
});

function rmu_pr_website_settings_page()
{
	// ตรวจสอบการกดปุ่ม Reset
	if (isset($_POST['rmu_pr_website_reset_defaults'])) {
		update_option('rmu_pr_website_tab_active_color', '#e0ecff');
		update_option('rmu_pr_website_tab_text_color', '#2874fc');
		update_option('rmu_pr_website_pagination_bg', '#2874fc');
		update_option('rmu_pr_website_pagination_hover', '#d0e2ff');
		update_option('rmu_pr_website_pagination_active', '#e0ecff');
		update_option('rmu_pr_website_pagination_active_text', '#2874fc');
		echo '<div class="notice notice-success is-dismissible"><p>รีเซ็ตค่าสำเร็จ</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Posts Settings</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields('rmu_pr_website_options');
			do_settings_sections('rmu_pr_website_settings');
			?>
			<table class="form-table">
				<tr>
					<th scope="row">ซ่อนช่องค้นหา (Hide Input Search)</th>
					<td>
						<input type="checkbox" name="rmu_pr_website_hide_input" value="1" <?php checked(get_option('rmu_pr_website_hide_input', false)); ?>>
					</td>
				</tr>
				<tr>
					<th scope="row">Tab Active Color</th>
					<td>
						<input type="color" name="rmu_pr_website_tab_active_color"
							value="<?php echo esc_attr(get_option('rmu_pr_website_tab_active_color', '#e0ecff')); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Tab Text Color</th>
					<td>
						<input type="color" name="rmu_pr_website_tab_text_color"
							value="<?php echo esc_attr(get_option('rmu_pr_website_tab_text_color', '#2874fc')); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Pagination Background</th>
					<td>
						<input type="color" name="rmu_pr_website_pagination_bg"
							value="<?php echo esc_attr(get_option('rmu_pr_website_pagination_bg', '#eee')); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Pagination Hover</th>
					<td>
						<input type="color" name="rmu_pr_website_pagination_hover"
							value="<?php echo esc_attr(get_option('rmu_pr_website_pagination_hover', '#d0e2ff')); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Pagination Active</th>
					<td>
						<input type="color" name="rmu_pr_website_pagination_active"
							value="<?php echo esc_attr(get_option('rmu_pr_website_pagination_active', '#e0ecff')); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">Pagination Active Text</th>
					<td>
						<input type="color" name="rmu_pr_website_pagination_active_text"
							value="<?php echo esc_attr(get_option('rmu_pr_website_pagination_active_text', '#2874fc')); ?>">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Category Slugs</th>
					<td>
						<input type="text" name="rmu_pr_website_category_slugs"
							value="<?php echo esc_attr(get_option('rmu_pr_website_category_slugs', '')); ?>" />
						<p class="description">
							ใส่ slug หมวดหมู่ คั่นด้วย comma เช่น <code>news,activity,announce</code> หรือภาษาไทย เช่น
							<code>ภาพกิจกรรม,จดหมายข่าวพระวรุณ,ข่าวประชาสัมพันธ์,ประกาศ</code><br>
							<strong>slug คืออะไร?</strong> คือค่าในฟิลด์ <code>"slug"</code> ของหมวดหมู่ใน WordPress
							อาจเป็นภาษาอังกฤษหรือภาษาไทยก็ได้ ขึ้นอยู่กับที่เว็บต้นทางตั้งไว้<br>
							<strong>วิธีดู slug จริง:</strong> เปิดลิงก์ใดลิงก์หนึ่ง แล้วดูค่า <code>"slug"</code>
							ของแต่ละหมวด<br>
							&bull; <a
								href="<?php echo esc_url(rtrim(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/'), '/')); ?>/wp-json/wp/v2/categories?per_page=100"
								target="_blank"><code><?php echo esc_html(rtrim(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/'), '/')); ?>/wp-json/wp/v2/categories?per_page=100</code></a>
							(pretty URL)<br>
							&bull; <a
								href="<?php echo esc_url(rtrim(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/'), '/')); ?>/?rest_route=/wp/v2/categories&per_page=100"
								target="_blank"><code><?php echo esc_html(rtrim(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/'), '/')); ?>/?rest_route=/wp/v2/categories&amp;per_page=100</code></a>
							(fallback)
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Base URL</th>
					<td>
						<input type="text" name="rmu_pr_website_base_url"
							value="<?php echo esc_attr(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/')); ?>" />
						<p class="description">URL หลัก เช่น https://pr.rmu.ac.th/ สำคัญตรวจสอบเว็บไซต์ wordpress
							ต้นทางก่อนว่าเปิดให้โดยเข้า https://pr.rmu.ac.th/wp-json/wp/v2/posts/ ถ้ามี response ใช้ได้</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<form method="post" style="margin-top:1em;">
			<input type="hidden" name="rmu_pr_website_reset_defaults" value="1">
			<button type="submit" class="button button-secondary"
				onclick="return confirm('ต้องการรีเซ็ตค่ากลับเป็นค่าเริ่มต้นหรือไม่?')">Reset Default</button>
		</form>
		<hr>
		<h2>วิธีใช้งาน Shortcode</h2>
		<p>
			คัดลอก <code>[rmu_pr_website]</code> ไปวางในหน้า/โพสต์ หรือใน Elementor (ผ่าน Shortcode Widget)
			เพื่อแสดงฟอร์มค้นหาโพสต์
		</p>
	</div>
	<?php
}

add_action('admin_init', function () {
	register_setting('rmu_pr_website_options', 'rmu_pr_website_tab_active_color');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_tab_text_color');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_pagination_bg');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_pagination_hover');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_pagination_active');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_pagination_active_text');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_hide_input');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_category_slugs');
	register_setting('rmu_pr_website_options', 'rmu_pr_website_base_url');
});
/**
 * GitHub Update Checker
 */
class RMU_PR_GitHub_Updater
{
	private $slug = 'rmu-pr-website';
	private $plugin_file;
	private $plugin_basename;
	private $github_owner = 'parich';
	private $github_repo = 'rmu-pr-website';
	private $current_version;
	private $github_response;
	private $cache_key = 'rmu_pr_website_github_update';
	private $cache_expiry = 21600; // 6 hours

	public function __construct($plugin_file)
	{
		$this->plugin_file = $plugin_file;
		$this->plugin_basename = plugin_basename($plugin_file);

		$plugin_data = get_file_data($plugin_file, ['Version' => 'Version']);
		$this->current_version = $plugin_data['Version'];

		add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
		add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
		add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
	}

	private function get_github_release()
	{
		if ($this->github_response !== null) {
			return $this->github_response;
		}

		$cached = get_transient($this->cache_key);
		if ($cached !== false) {
			$this->github_response = $cached;
			return $cached;
		}

		$url = "https://api.github.com/repos/{$this->github_owner}/{$this->github_repo}/releases/latest";
		$response = wp_remote_get($url, [
			'headers' => [
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo('version'),
			],
		]);

		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
			$this->github_response = false;
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response));
		if (empty($body) || !isset($body->tag_name)) {
			$this->github_response = false;
			return false;
		}

		$this->github_response = $body;
		set_transient($this->cache_key, $body, $this->cache_expiry);

		return $body;
	}

	public function check_update($transient)
	{
		if (empty($transient->checked)) {
			return $transient;
		}

		$release = $this->get_github_release();
		if (!$release) {
			return $transient;
		}

		$remote_version = ltrim($release->tag_name, 'v');

		if (version_compare($remote_version, $this->current_version, '>')) {
			$download_url = $release->zipball_url;

			if (!empty($release->assets)) {
				foreach ($release->assets as $asset) {
					if (substr($asset->name, -4) === '.zip') {
						$download_url = $asset->browser_download_url;
						break;
					}
				}
			}

			$transient->response[$this->plugin_basename] = (object) [
				'slug' => $this->slug,
				'plugin' => $this->plugin_basename,
				'new_version' => $remote_version,
				'url' => $release->html_url,
				'package' => $download_url,
			];
		}

		return $transient;
	}

	public function plugin_info($result, $action, $args)
	{
		if ($action !== 'plugin_information' || $args->slug !== $this->slug) {
			return $result;
		}

		$release = $this->get_github_release();
		if (!$release) {
			return $result;
		}

		$remote_version = ltrim($release->tag_name, 'v');

		$download_url = $release->zipball_url;
		if (!empty($release->assets)) {
			foreach ($release->assets as $asset) {
				if (substr($asset->name, -4) === '.zip') {
					$download_url = $asset->browser_download_url;
					break;
				}
			}
		}

		return (object) [
			'name' => 'RMU PR Website',
			'slug' => $this->slug,
			'version' => $remote_version,
			'author' => '<a href="https://github.com/parich">Mr.Parich Suriya</a>',
			'homepage' => "https://github.com/{$this->github_owner}/{$this->github_repo}",
			'requires' => '6.7',
			'requires_php' => '7.4',
			'sections' => [
				'description' => 'แสดงข่าวจากเว็บไซต์ PR มหาวิทยาลัยราชมงคลมหานคร',
				'changelog' => nl2br(esc_html($release->body ?? '')),
			],
			'download_link' => $download_url,
		];
	}

	public function after_install($response, $hook_extra, $result)
	{
		if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
			return $result;
		}

		global $wp_filesystem;

		$install_dir = plugin_dir_path($this->plugin_file);

		if ($wp_filesystem->exists($install_dir)) {
			$wp_filesystem->delete($install_dir, true);
		}

		$wp_filesystem->move($result['destination'], $install_dir);
		$result['destination'] = $install_dir;

		activate_plugin($this->plugin_basename);

		return $result;
	}
}

new RMU_PR_GitHub_Updater(__FILE__);

add_action('wp_head', function () {
	$active_tab = esc_attr(get_option('rmu_pr_website_tab_active_color', '#e0ecff'));
	$text_tab = esc_attr(get_option('rmu_pr_website_tab_text_color', '#2874fc'));
	$bg_pagination = esc_attr(get_option('rmu_pr_website_pagination_bg', '#e0ecff'));
	$hover_pagination = esc_attr(get_option('rmu_pr_website_pagination_hover', '#d0e2ff'));
	$active_pagination = esc_attr(get_option('rmu_pr_website_pagination_active', '#fff'));
	$active_text_pagination = esc_attr(get_option('rmu_pr_website_pagination_active_text', '#2874fc'));
	echo "<style>
	.our-search .tab {
			background-color: {$bg_pagination};
		}
        .our-search .tab.active,
        .our-search .tab:hover,
        .our-search .tab:focus  {
            background-color: {$active_tab} !important;
            color: {$text_tab} !important;
        }
        .our-search #pagination .pagination-btn {
            background: {$bg_pagination};
        }
        .our-search #pagination .pagination-btn:hover {
            background-color: {$hover_pagination};
        }
        .our-search #pagination .pagination-btn.active {
            background-color: {$active_pagination};
            color: {$active_text_pagination};
        }
    </style>";
});
<?php
/**
 * Plugin Name:       RMU PR Website
 * Description:       Example block scaffolded with Create Block tool.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
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
						<p class="description">ใส่ slug หมวดหมู่ (คั่นด้วย comma เช่น news,activity,announce)</p>
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
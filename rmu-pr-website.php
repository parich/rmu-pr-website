<?php
/**
 * Plugin Name:       RMU PR Website
 * Plugin URI:        https://github.com/parich/rmu-pr-website
 * Description:       แสดงข่าวจากเว็บไซต์ มหาวิทยาลัย.
 * Version:           0.2.0
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

// handle ของ view.js / style-index.css ที่ WordPress ลงทะเบียนให้จาก block.json ใช้ตัวเดียวกันทั้ง block และ shortcode
// เพื่อให้ทั้งสองแบบได้ข้อมูลตั้งค่าชุดเดียวกัน และไม่ถูกโหลดซ้ำเมื่อหน้าเดียวกันมีทั้งสองแบบ
// (?ver= ของ CSS มาจาก "version" ใน block.json ต้องเปลี่ยนทุกครั้งที่ออกเวอร์ชัน ดู RELEASE.md)
function rmu_pr_website_view_script_handle()
{
	return generate_block_asset_handle('create-block/rmu-pr-website', 'viewScript');
}

function rmu_pr_website_style_handle()
{
	return generate_block_asset_handle('create-block/rmu-pr-website', 'style');
}

// ฟังก์ชันนี้ใช้สำหรับการโหลดไฟล์ JavaScript และ CSS ที่จำเป็นสำหรับ block
function rmu_pr_website_enqueue_assets()
{
	// สีและขนาดตัวอักษรจากหน้าตั้งค่า พิมพ์ต่อท้าย CSS ของ block เฉพาะหน้าที่โหลด CSS นั้นจริง
	wp_add_inline_style(rmu_pr_website_style_handle(), rmu_pr_website_inline_css());

	// ดึงค่าตัวเลือกจากฐานข้อมูล
	$category_slugs = get_option('rmu_pr_website_category_slugs', '');
	$base_url = get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/');
	// แปลง category slugs เป็น array ของ object [{slug:..., name:...}, ...]
	$cats = array_filter(array_map('trim', explode(',', $category_slugs)));
	$cat_objs = array();
	foreach ($cats as $cat) {
		$cat_objs[] = array('slug' => $cat, 'name' => $cat);
	}
	// ส่งข้อมูลไปยัง JavaScript — ผูกไว้กับ handle ของ block เสมอ ข้อมูลจะถูกพิมพ์ออกเฉพาะหน้าที่ enqueue view.js จริง
	wp_localize_script(rmu_pr_website_view_script_handle(), 'POSTS_PR_RMU_DATA', array(
		'categorySlugs' => $cat_objs,
		'baseUrl' => $base_url,
	));

	// หน้าที่รู้ล่วงหน้าว่ามี shortcode ให้โหลดตั้งแต่ <head> กันหน้ากระพริบตอน CSS มาช้า
	if (is_singular() && has_shortcode(get_post()->post_content, 'rmu_pr_website')) {
		rmu_pr_website_enqueue_shortcode_assets();
	}
}
add_action('wp_enqueue_scripts', 'rmu_pr_website_enqueue_assets');

function rmu_pr_website_enqueue_shortcode_assets()
{
	wp_enqueue_style(rmu_pr_website_style_handle());
	wp_enqueue_script(rmu_pr_website_view_script_handle());
}


/**
 * Shortcode สำหรับแสดงผล block
 */
function rmu_pr_website_shortcode($atts)
{
	// enqueue ตอน shortcode ทำงานจริง จึงใช้ได้ทุกที่ (widget, Elementor Theme Builder/popup, หน้า archive)
	// ถ้าเลย <head> ไปแล้ว WordPress จะพิมพ์ CSS/JS ไว้ใน footer แทน
	rmu_pr_website_enqueue_shortcode_assets();

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

// สีตัวอักษรของแท็บที่ไม่ได้เลือกและเลขหน้า (ตายตัว ตรงกับ --rmu-pr-text ใน style.scss) ใช้ตรวจคอนทราสต์กับสีพื้นที่ตั้งได้
const RMU_PR_WEBSITE_TEXT_COLOR = '#1e293b';

// ค่าเริ่มต้นของสี ใช้ร่วมกันทั้งฟอร์ม, ปุ่ม Reset, sanitize และ CSS หน้าเว็บ
// ต้องเป็น hex 6 หลัก เพราะ <input type="color"> ไม่รับรูปแบบอื่น (#eee จะแสดงเป็นสีดำ)
// ทุกคู่ตัวอักษร/พื้นผ่าน WCAG AA (อย่างน้อย 4.5:1) — ตรวจได้ที่หน้าตั้งค่า
function rmu_pr_website_color_defaults()
{
	return array(
		'rmu_pr_website_tab_active_color' => '#e0ecff',
		'rmu_pr_website_tab_text_color' => '#1d4ed8',
		'rmu_pr_website_pagination_bg' => '#eeeeee',
		'rmu_pr_website_pagination_hover' => '#d0e2ff',
		'rmu_pr_website_pagination_active' => '#e0ecff',
		'rmu_pr_website_pagination_active_text' => '#1d4ed8',
	);
}

// อ่านค่าสีโดยรับประกันว่าเป็น hex เสมอ (ค่าที่ไม่ถูกต้องใช้ค่าเริ่มต้นแทน) จึงพิมพ์ลง <style> ได้ปลอดภัย
function rmu_pr_website_get_color($option)
{
	$defaults = rmu_pr_website_color_defaults();
	return sanitize_hex_color(get_option($option, '')) ?: $defaults[$option];
}

// ขนาดตัวอักษร (px) — เก็บเป็น px เพราะกรอกง่าย แต่พิมพ์ออกเป็น rem
// เพื่อให้ขยายตามขนาดตัวอักษรที่ผู้ใช้ตั้งไว้ใน browser (WCAG 1.4.4)
function rmu_pr_website_font_size_defaults()
{
	return array(
		'rmu_pr_website_tab_font_size' => 16,
		'rmu_pr_website_title_font_size' => 16,
		'rmu_pr_website_excerpt_font_size' => 14,
	);
}

// รับเฉพาะ 10–40px ค่านอกช่วงหรือไม่ใช่ตัวเลขใช้ค่าเริ่มต้นแทน
function rmu_pr_website_valid_font_size($value, $default)
{
	$px = absint($value);
	return ($px >= 10 && $px <= 40) ? $px : $default;
}

function rmu_pr_website_get_font_size($option)
{
	$defaults = rmu_pr_website_font_size_defaults();
	return rmu_pr_website_valid_font_size(get_option($option, $defaults[$option]), $defaults[$option]);
}

// %F ไม่ขึ้นกับ locale (PHP 7.4 อาจพิมพ์ทศนิยมเป็น , ตาม locale ของ server)
function rmu_pr_website_px_to_rem($px)
{
	return rtrim(rtrim(sprintf('%.4F', $px / 16), '0'), '.') . 'rem';
}

// ค่าเริ่มต้น 500 เพราะ 600 (ค่าของหัวข้อในหลายธีม) ดูหนามากกับฟอนต์ไทยแบบมีหัว
function rmu_pr_website_font_weights()
{
	return array(
		300 => 'บาง (300)',
		400 => 'ปกติ (400)',
		500 => 'กลาง (500)',
		600 => 'ค่อนข้างหนา (600)',
		700 => 'หนา (700)',
	);
}

function rmu_pr_website_valid_font_weight($value)
{
	$weight = absint($value);
	return array_key_exists($weight, rmu_pr_website_font_weights()) ? $weight : 500;
}

// ค่าที่ตั้งไว้ทั้งหมดในรูป CSS custom properties (ทุกค่าผ่านการตรวจแล้ว: hex หรือตัวเลข จึงพิมพ์ลง <style> ได้ปลอดภัย)
function rmu_pr_website_inline_css()
{
	$vars = array(
		'--rmu-pr-tab-active-bg' => rmu_pr_website_get_color('rmu_pr_website_tab_active_color'),
		'--rmu-pr-tab-active-text' => rmu_pr_website_get_color('rmu_pr_website_tab_text_color'),
		'--rmu-pr-pagination-bg' => rmu_pr_website_get_color('rmu_pr_website_pagination_bg'),
		'--rmu-pr-pagination-hover' => rmu_pr_website_get_color('rmu_pr_website_pagination_hover'),
		'--rmu-pr-pagination-active-bg' => rmu_pr_website_get_color('rmu_pr_website_pagination_active'),
		'--rmu-pr-pagination-active-text' => rmu_pr_website_get_color('rmu_pr_website_pagination_active_text'),
		'--rmu-pr-tab-font-size' => rmu_pr_website_px_to_rem(rmu_pr_website_get_font_size('rmu_pr_website_tab_font_size')),
		'--rmu-pr-title-font-size' => rmu_pr_website_px_to_rem(rmu_pr_website_get_font_size('rmu_pr_website_title_font_size')),
		'--rmu-pr-title-font-weight' => rmu_pr_website_valid_font_weight(get_option('rmu_pr_website_title_font_weight', 500)),
		'--rmu-pr-excerpt-font-size' => rmu_pr_website_px_to_rem(rmu_pr_website_get_font_size('rmu_pr_website_excerpt_font_size')),
	);
	$css = '';
	foreach ($vars as $name => $value) {
		$css .= "{$name}:{$value};";
	}
	return ".our-search{{$css}}";
}

// อัตราคอนทราสต์ตามสูตร WCAG 2.x (1 ถึง 21)
function rmu_pr_website_contrast_ratio($hex_a, $hex_b)
{
	$luminance = function ($hex) {
		$channels = array_map(function ($pair) {
			$c = hexdec($pair) / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}, str_split(ltrim($hex, '#'), 2));
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	};
	$a = $luminance($hex_a);
	$b = $luminance($hex_b);
	return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

// คู่สีที่ต้องตรวจ: ตัวอักษรบนพื้นต้องอย่างน้อย 4.5:1, กรอบโฟกัสบนพื้นขาวอย่างน้อย 3:1
function rmu_pr_website_contrast_checks()
{
	$tab_text = rmu_pr_website_get_color('rmu_pr_website_tab_text_color');
	return array(
		array('แท็บที่เลือก / ชี้: Tab Text บน Tab Active', $tab_text, rmu_pr_website_get_color('rmu_pr_website_tab_active_color'), 4.5),
		array('เลขหน้า: ตัวอักษร (' . RMU_PR_WEBSITE_TEXT_COLOR . ') บน Pagination Background', RMU_PR_WEBSITE_TEXT_COLOR, rmu_pr_website_get_color('rmu_pr_website_pagination_bg'), 4.5),
		array('เลขหน้าที่ชี้: ตัวอักษร (' . RMU_PR_WEBSITE_TEXT_COLOR . ') บน Pagination Hover', RMU_PR_WEBSITE_TEXT_COLOR, rmu_pr_website_get_color('rmu_pr_website_pagination_hover'), 4.5),
		array('หน้าปัจจุบัน: Pagination Active Text บน Pagination Active', rmu_pr_website_get_color('rmu_pr_website_pagination_active_text'), rmu_pr_website_get_color('rmu_pr_website_pagination_active'), 4.5),
		array('กรอบโฟกัส (ใช้สี Tab Text) บนพื้นขาว', $tab_text, '#ffffff', 3),
	);
}

// ค่าเริ่มต้นเดิม #2874fc บน #e0ecff ได้แค่ 3.5:1 ไม่ผ่าน WCAG — เว็บที่ยังใช้ค่าเดิมอยู่เปลี่ยนเป็นค่าใหม่ให้ครั้งเดียว
// สีที่ผู้ดูแลเลือกเอง (ไม่ตรงกับค่าเดิม) ไม่แตะ ใช้ init เพราะการอัปเดตผ่าน GitHub ไม่เรียก activation hook
add_action('init', function () {
	if ((int) get_option('rmu_pr_website_data_version', 0) >= 1) {
		return;
	}
	foreach (array('rmu_pr_website_tab_text_color', 'rmu_pr_website_pagination_active_text') as $option) {
		if (strtolower((string) get_option($option, '')) === '#2874fc') {
			update_option($option, '#1d4ed8');
		}
	}
	update_option('rmu_pr_website_data_version', 1);
});

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
	// ตรวจสอบการกดปุ่ม Reset (ต้องผ่าน nonce กันเว็บอื่นส่ง form มาแทน admin ที่ login อยู่)
	if (isset($_POST['rmu_pr_website_reset_defaults']) && check_admin_referer('rmu_pr_website_reset_defaults')) {
		foreach (rmu_pr_website_color_defaults() + rmu_pr_website_font_size_defaults() as $option => $default) {
			update_option($option, $default);
		}
		update_option('rmu_pr_website_title_font_weight', 500);
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
					<th scope="row"><label for="rmu_pr_website_hide_input">ซ่อนช่องค้นหา (Hide Input Search)</label></th>
					<td>
						<input type="checkbox" id="rmu_pr_website_hide_input" name="rmu_pr_website_hide_input" value="1" <?php checked(get_option('rmu_pr_website_hide_input', false)); ?>>
					</td>
				</tr>
				<?php
				$color_labels = array(
					'rmu_pr_website_tab_active_color' => 'Tab Active Color',
					'rmu_pr_website_tab_text_color' => 'Tab Text Color',
					'rmu_pr_website_pagination_bg' => 'Pagination Background',
					'rmu_pr_website_pagination_hover' => 'Pagination Hover',
					'rmu_pr_website_pagination_active' => 'Pagination Active',
					'rmu_pr_website_pagination_active_text' => 'Pagination Active Text',
				);
				foreach ($color_labels as $option => $label): ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($label); ?></label></th>
						<td>
							<input type="color" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>"
								value="<?php echo esc_attr(rmu_pr_website_get_color($option)); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row">ตรวจคอนทราสต์ (WCAG 2.1 AA)</th>
					<td>
						<table class="widefat striped" style="max-width:640px">
							<thead>
								<tr>
									<th scope="col">คู่สี</th>
									<th scope="col">ตัวอย่าง</th>
									<th scope="col">คอนทราสต์</th>
									<th scope="col">ผล</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach (rmu_pr_website_contrast_checks() as list($label, $fg, $bg, $min)):
									$ratio = rmu_pr_website_contrast_ratio($fg, $bg); ?>
									<tr>
										<td><?php echo esc_html($label); ?></td>
										<td><span style="display:inline-block;padding:2px 10px;border:1px solid #8c8f94;color:<?php echo esc_attr($fg); ?>;background:<?php echo esc_attr($bg); ?>">ตัวอย่าง 12</span></td>
										<td><?php echo esc_html(sprintf('%.2F:1', $ratio)); ?></td>
										<td>
											<?php if ($ratio >= $min): ?>
												<strong>✓ ผ่าน</strong>
											<?php else: ?>
												<strong style="color:#b32d2e">✗ ไม่ผ่าน</strong> (ต้องอย่างน้อย <?php echo esc_html($min); ?>:1)
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description">คำนวณจากค่าที่บันทึกแล้ว เปลี่ยนสีแล้วกด Save Changes เพื่อดูผลใหม่</p>
					</td>
				</tr>
				<?php
				$font_size_labels = array(
					'rmu_pr_website_tab_font_size' => 'ขนาดตัวอักษรแท็บหมวดหมู่',
					'rmu_pr_website_title_font_size' => 'ขนาดตัวอักษรหัวข้อข่าว',
					'rmu_pr_website_excerpt_font_size' => 'ขนาดตัวอักษรคำโปรย',
				);
				foreach ($font_size_labels as $option => $label): ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($label); ?></label></th>
						<td>
							<input type="number" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>"
								min="10" max="40" step="1" class="small-text" aria-describedby="rmu_pr_website_font_size_note"
								value="<?php echo esc_attr(rmu_pr_website_get_font_size($option)); ?>"> px
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="rmu_pr_website_title_font_weight">ความหนาหัวข้อข่าว</label></th>
					<td>
						<select id="rmu_pr_website_title_font_weight" name="rmu_pr_website_title_font_weight">
							<?php foreach (rmu_pr_website_font_weights() as $weight => $label): ?>
								<option value="<?php echo esc_attr($weight); ?>" <?php selected(rmu_pr_website_valid_font_weight(get_option('rmu_pr_website_title_font_weight', 500)), $weight); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description" id="rmu_pr_website_font_size_note">
							ขนาดตัวอักษร 10–40 px (16 px = ขนาดปกติของ browser) แสดงผลเป็นหน่วย rem จึงขยายตามที่ผู้ใช้ตั้งขนาดตัวอักษรไว้ใน browser<br>
							แบบอักษรใช้ของธีม ถ้าหัวข้อดูหนาเกินไปให้เลือก 400 หรือ 500
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="rmu_pr_website_category_slugs">Category Slugs</label></th>
					<td>
						<input type="text" id="rmu_pr_website_category_slugs" name="rmu_pr_website_category_slugs"
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
					<th scope="row"><label for="rmu_pr_website_base_url">Base URL</label></th>
					<td>
						<input type="text" id="rmu_pr_website_base_url" name="rmu_pr_website_base_url"
							value="<?php echo esc_attr(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/')); ?>" />
						<?php $check_url = rtrim(get_option('rmu_pr_website_base_url', 'https://pr.rmu.ac.th/'), '/') . '/?rest_route=/wp/v2/posts&per_page=1&_fields=id,link,title'; ?>
						<p class="description">
							URL หลักของเว็บ WordPress ต้นทาง เช่น <code>https://pr.rmu.ac.th/</code><br>
							<strong>วิธีตรวจสอบ:</strong> เปิดลิงก์ด้านล่าง ถ้าเห็นข้อมูล JSON (ขึ้นต้นด้วย <code>[</code>) แสดงว่าใช้ได้
							(ใช้ได้ทุกการตั้งค่า Permalink รวมถึงแบบ Plain <code>?p=123</code>)<br>
							&bull; <a href="<?php echo esc_url($check_url); ?>" target="_blank"><code><?php echo esc_html($check_url); ?></code></a>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<form method="post" style="margin-top:1em;">
			<?php wp_nonce_field('rmu_pr_website_reset_defaults'); ?>
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
	foreach (rmu_pr_website_color_defaults() as $option => $default) {
		register_setting('rmu_pr_website_options', $option, array(
			'sanitize_callback' => function ($value) use ($default) {
				return sanitize_hex_color((string) $value) ?: $default;
			},
		));
	}
	foreach (rmu_pr_website_font_size_defaults() as $option => $default) {
		register_setting('rmu_pr_website_options', $option, array(
			'type' => 'integer',
			'sanitize_callback' => function ($value) use ($default) {
				return rmu_pr_website_valid_font_size($value, $default);
			},
		));
	}
	register_setting('rmu_pr_website_options', 'rmu_pr_website_title_font_weight', array(
		'type' => 'integer',
		'sanitize_callback' => 'rmu_pr_website_valid_font_weight',
	));
	register_setting('rmu_pr_website_options', 'rmu_pr_website_hide_input', array(
		'sanitize_callback' => 'rest_sanitize_boolean',
	));
	register_setting('rmu_pr_website_options', 'rmu_pr_website_category_slugs', array(
		'sanitize_callback' => 'sanitize_text_field',
	));
	register_setting('rmu_pr_website_options', 'rmu_pr_website_base_url', array(
		'sanitize_callback' => function ($value) {
			return esc_url_raw(trim((string) $value), array('http', 'https')) ?: 'https://pr.rmu.ac.th/';
		},
	));
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

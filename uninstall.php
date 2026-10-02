<?php
/**
 * ลบ plugin = ลบค่าตั้งค่าของ plugin นี้ออกจาก wp_options (เฉพาะของ plugin นี้)
 * ปิดใช้งาน (Deactivate) เฉยๆ ค่ายังอยู่
 *
 * @package CreateBlock
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;
// เลือกตามชื่อขึ้นต้น: ค่าตั้งค่าทั้งหมด (rmu_pr_website_*) และ cache ผลเช็คอัปเดตจาก GitHub (transient)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$rmu_pr_website_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like('rmu_pr_website_') . '%',
		$wpdb->esc_like('_transient_rmu_pr_website_') . '%',
		$wpdb->esc_like('_transient_timeout_rmu_pr_website_') . '%'
	)
);
foreach ($rmu_pr_website_options as $rmu_pr_website_option) {
	delete_option($rmu_pr_website_option);
}

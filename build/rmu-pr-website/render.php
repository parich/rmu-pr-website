<?php
// ตรวจสอบ option ซ่อน input search
$hide_input = get_option('rmu_pr_website_hide_input', false);
// id ไม่ซ้ำกันเมื่อหน้าเดียวมีหลายชุด label จึงผูกกับช่องค้นหาของตัวเองได้ถูก
$search_id = wp_unique_id('rmu-pr-search-');
?>

<div class="our-search">
	<?php if (!$hide_input): ?>
		<label class="rmu-pr-sr-only" for="<?php echo esc_attr($search_id); ?>">ค้นหาโพสต์กลุ่มงานประชาสัมพันธ์</label>
		<input type="search" id="<?php echo esc_attr($search_id); ?>" placeholder="ค้นหาโพสต์กลุ่มงานประชาสัมพันธ์" />
	<?php endif; ?>
	<div class="results"></div>
	<p class="rmu-pr-status rmu-pr-sr-only" role="status"></p>
</div>

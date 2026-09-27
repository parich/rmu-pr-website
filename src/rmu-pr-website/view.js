import dompurify from "dompurify";

const CATEGORY_SLUGS = window.POSTS_PR_RMU_DATA?.categorySlugs || [];
const BASE_URL = window.POSTS_PR_RMU_DATA?.baseUrl || "https://pr.rmu.ac.th/";

// ดึงเฉพาะฟิลด์ที่การ์ดใช้ ห้ามดึง content: ถ้าเว็บต้นทางใช้ Elementor จะพิมพ์ <style> ออกมาก่อน JSON
// ทำให้ parse ไม่ได้ และ header X-WP-TotalPages / Access-Control-Allow-Origin ถูกส่งไม่ออก (browser บล็อก CORS)
// ต้องมี _links และ _embedded ด้วย ไม่งั้น _embed จะไม่ทำงาน
const POST_FIELDS = "id,link,title,excerpt,_links,_embedded";

// รอให้หยุดพิมพ์ก่อนค่อยค้นหา ไม่ยิง request ทุกตัวอักษร
const SEARCH_DEBOUNCE_MS = 300;

// cache id ของหมวดหมู่ ไม่ต้องยิง request ซ้ำทุกครั้งที่พิมพ์ค้นหาหรือเปลี่ยนหน้า
// (ต้องประกาศก่อน bringSearchToLife ถูกเรียกด้านล่าง)
const categoryIdCache = new Map();

// ใช้ ?rest_route= เสมอ เพราะใช้ได้ทุก Permalink รวมถึงแบบ Plain (?p=123)
// ส่วน /wp-json/ ใช้ได้เฉพาะเว็บที่ตั้ง Permalink แบบ pretty URL
function buildApiUrl(path, params = {}) {
	const base = BASE_URL.replace(/\/$/, "");
	const qs = new URLSearchParams(params).toString();
	return `${base}/?rest_route=/wp/v2/${path}${qs ? `&${qs}` : ""}`;
}

async function fetchJson(url, signal) {
	const res = await fetch(url, { signal });
	if (!res.ok) {
		throw new Error(`HTTP ${res.status} ${url}`);
	}
	return { data: await res.json(), res };
}

const allSearchResults = document.querySelectorAll(".our-search");

allSearchResults.forEach((el) => bringSearchToLife(el));

function bringSearchToLife(el) {
	const input = el.querySelector("input");
	const resultsContainer = el.querySelector(".results");
	const tabsContainer = document.createElement("div");
	const paginationContainer = document.createElement("div");

	tabsContainer.id = "tabs";
	paginationContainer.id = "pagination";
	// ถ้ามี input ให้แทรก tabs หลัง input, ถ้าไม่มีให้แทรกเป็น element แรก
	if (input) {
		el.insertBefore(tabsContainer, input.nextSibling);
	} else {
		el.insertBefore(tabsContainer, resultsContainer);
	}
	el.appendChild(paginationContainer);

	CATEGORY_SLUGS.forEach((cat, index) => {
		const tab = document.createElement("button");
		tab.className = "tab";
		tab.textContent = cat.name;
		tab.dataset.slug = cat.slug;
		if (index === 0) tab.classList.add("active");
		tabsContainer.appendChild(tab);
	});

	let controller = null;
	let debounceTimer;

	function loadPosts(page) {
		const activeTab = tabsContainer.querySelector(".tab.active");
		if (!activeTab) return;

		// ยกเลิก request ก่อนหน้า ไม่ให้ response ที่มาช้ามาทับผลล่าสุด
		controller?.abort();
		controller = new AbortController();

		fetchAndRenderPosts({
			slug: activeTab.dataset.slug,
			searchTerm: input ? input.value.trim() : "",
			page,
			container: resultsContainer,
			paginationContainer,
			signal: controller.signal,
			onPageClick: loadPosts,
		});
	}

	loadPosts(1); // Initial load

	tabsContainer.querySelectorAll(".tab").forEach((tab) => {
		tab.addEventListener("click", () => {
			tabsContainer
				.querySelectorAll(".tab")
				.forEach((t) => t.classList.remove("active"));
			tab.classList.add("active");
			loadPosts(1);
		});
	});

	if (input) {
		input.addEventListener("input", () => {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => loadPosts(1), SEARCH_DEBOUNCE_MS);
		});
	}
}

async function fetchAndRenderPosts({
	slug,
	searchTerm,
	page,
	container,
	paginationContainer,
	signal,
	onPageClick,
}) {
	try {
		// ไม่ส่ง signal ให้ request หมวดหมู่ เพราะ promise นี้ถูก cache และแชร์กันทุก instance
		const catId = await getCategoryIdBySlug(slug);
		if (signal.aborted) return;
		if (!catId) {
			container.innerHTML = "ไม่พบหมวดหมู่";
			return;
		}

		const query = buildApiUrl("posts", {
			categories: catId,
			search: searchTerm,
			page,
			per_page: 8,
			_embed: "wp:featuredmedia",
			_fields: POST_FIELDS,
		});

		const { data: posts, res } = await fetchJson(query, signal);
		const totalPages = parseInt(res.headers.get("X-WP-TotalPages")) || 1;

		if (posts.length) {
			container.innerHTML = generateHTML(posts);
			renderPagination(paginationContainer, totalPages, page, onPageClick);
		} else {
			container.innerHTML = "ไม่พบโพสต์ในหมวดนี้";
			paginationContainer.innerHTML = "";
		}
	} catch (error) {
		// ถูกยกเลิกเพราะมี request ใหม่กว่าเข้ามา ไม่ใช่ error จริง
		if (signal.aborted) return;
		console.error("Error fetching posts:", error);
		container.innerHTML = "เกิดข้อผิดพลาดในการโหลดโพสต์";
	}
}

function getCategoryIdBySlug(slug) {
	if (!categoryIdCache.has(slug)) {
		const url = buildApiUrl("categories", { slug, _fields: "id" });
		const promise = fetchJson(url).then(({ data }) => data[0]?.id || null);
		// ถ้า error ให้ลบออกจาก cache เพื่อลองใหม่ครั้งถัดไป
		promise.catch(() => categoryIdCache.delete(slug));
		categoryIdCache.set(slug, promise);
	}
	return categoryIdCache.get(slug);
}

function generateHTML(posts) {
	return dompurify.sanitize(
		posts
			.map((post) => {
				const image =
					post._embedded?.["wp:featuredmedia"]?.[0]?.source_url || "";
				return `
          <a href="${post.link}" class="card" target="_blank">
            ${
							image
								? `<img src="${image}" alt="${post.title.rendered}" class="card-image"/>`
								: ""
						}
            <div class="card-body">
              <h3 class="card-title">${post.title.rendered}</h3>
              <div class="card-excerpt">${post.excerpt.rendered}</div>
            </div>
          </a>
        `;
			})
			.join(""),
		// DOMPurify ลบ target ทิ้งโดย default ต้องอนุญาตเอง ไม่งั้นลิงก์จะไม่เปิดแท็บใหม่
		{ ADD_ATTR: ["target"] },
	);
}
function renderPagination(container, totalPages, currentPage, onPageClick) {
	if (totalPages <= 1) {
		container.innerHTML = "";
		return;
	}

	let buttons = "";

	// ปุ่มก่อนหน้า
	if (currentPage > 1) {
		buttons += `<button class="pagination-btn" data-page="${
			currentPage - 1
		}">&lt;</button>`;
	}

	// ปุ่มตัวเลขสูงสุด 5 รายการ (centered around currentPage)
	const maxButtons = 5;
	let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
	let end = Math.min(totalPages, start + maxButtons - 1);

	if (end - start < maxButtons - 1) {
		start = Math.max(1, end - maxButtons + 1);
	}

	for (let i = start; i <= end; i++) {
		buttons += `<button class="pagination-btn${
			i === currentPage ? " active" : ""
		}" data-page="${i}">${i}</button>`;
	}

	// ปุ่มถัดไป
	if (currentPage < totalPages) {
		buttons += `<button class="pagination-btn" data-page="${
			currentPage + 1
		}">&gt;</button>`;
	}

	container.innerHTML = buttons;

	container.querySelectorAll(".pagination-btn").forEach((btn) => {
		btn.addEventListener("click", () => {
			const selectedPage = parseInt(btn.dataset.page);
			if (selectedPage !== currentPage) {
				onPageClick(selectedPage);
			}
		});
	});
}

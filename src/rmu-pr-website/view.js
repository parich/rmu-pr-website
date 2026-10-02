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

// id ของแท็บ/แผงผลลัพธ์ต้องไม่ซ้ำกันเมื่อหน้าเดียวมีหลายชุด (aria-controls / aria-labelledby อ้างด้วย id)
let instanceCount = 0;

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
	const resultsContainer = el.querySelector(".results");
	const status = el.querySelector(".rmu-pr-status");

	if (!CATEGORY_SLUGS.length) {
		showMessage(resultsContainer, status, "ยังไม่ได้กำหนดหมวดหมู่ข่าว");
		return;
	}

	const prefix = `rmu-pr-${++instanceCount}`;
	const input = el.querySelector("input");
	const tabsContainer = document.createElement("div");
	const paginationContainer = document.createElement("nav");

	// ARIA tabs: โปรแกรมอ่านหน้าจอจะบอกว่าเป็นแท็บที่เท่าไรจากทั้งหมด และแท็บไหนถูกเลือก
	tabsContainer.className = "rmu-pr-tabs";
	tabsContainer.setAttribute("role", "tablist");
	tabsContainer.setAttribute("aria-label", "หมวดหมู่ข่าว");
	resultsContainer.id = `${prefix}-panel`;
	resultsContainer.setAttribute("role", "tabpanel");
	resultsContainer.tabIndex = -1; // ให้ย้ายโฟกัสมาที่ผลลัพธ์ได้หลังเปลี่ยนหน้า
	paginationContainer.className = "rmu-pr-pagination";
	paginationContainer.setAttribute("aria-label", "เลือกหน้า");
	paginationContainer.hidden = true;

	// ถ้ามี input ให้แทรก tabs หลัง input, ถ้าไม่มีให้แทรกเป็น element แรก
	if (input) {
		el.insertBefore(tabsContainer, input.nextSibling);
	} else {
		el.insertBefore(tabsContainer, resultsContainer);
	}
	el.appendChild(paginationContainer);

	const tabs = CATEGORY_SLUGS.map((cat, index) => {
		const tab = document.createElement("button");
		tab.type = "button";
		tab.className = "tab";
		tab.id = `${prefix}-tab-${index}`;
		tab.textContent = cat.name;
		tab.dataset.slug = cat.slug;
		tab.setAttribute("role", "tab");
		tab.setAttribute("aria-controls", resultsContainer.id);
		tabsContainer.appendChild(tab);
		return tab;
	});

	let activeTab = null;
	let controller = null;
	let debounceTimer;

	// กด Tab เข้ามาจะหยุดที่แท็บที่เลือกอยู่ตัวเดียว แท็บอื่นเลื่อนด้วยปุ่มลูกศร
	function selectTab(tab) {
		activeTab = tab;
		tabs.forEach((t) => {
			t.setAttribute("aria-selected", String(t === tab));
			t.tabIndex = t === tab ? 0 : -1;
		});
		resultsContainer.setAttribute("aria-labelledby", tab.id);
	}

	function loadPosts(page, { moveFocus = false } = {}) {
		// ยกเลิก request ก่อนหน้า ไม่ให้ response ที่มาช้ามาทับผลล่าสุด
		controller?.abort();
		controller = new AbortController();
		const { signal } = controller;

		fetchAndRenderPosts({
			slug: activeTab.dataset.slug,
			label: activeTab.textContent,
			searchTerm: input ? input.value.trim() : "",
			page,
			container: resultsContainer,
			paginationContainer,
			status,
			signal,
			onPageClick: (selectedPage) => loadPosts(selectedPage, { moveFocus: true }),
		}).then(() => {
			// ปุ่มเลขหน้าที่กดถูกสร้างใหม่ โฟกัสจะหลุดไปที่ต้นหน้า จึงพาไปที่ผลลัพธ์หน้าใหม่แทน
			if (moveFocus && !signal.aborted) {
				resultsContainer.focus();
			}
		});
	}

	selectTab(tabs[0]);
	loadPosts(1); // Initial load

	tabs.forEach((tab) => {
		tab.addEventListener("click", () => {
			selectTab(tab);
			loadPosts(1);
		});
	});

	// ลูกศรเลื่อนโฟกัสไปแท็บอื่น ส่วน Enter/Space (คลิก) ค่อยโหลดหมวดนั้น
	// ไม่โหลดทันทีที่เลื่อน เพราะแต่ละแท็บต้องรอข้อมูลจากเว็บต้นทาง
	tabsContainer.addEventListener("keydown", (event) => {
		const index = tabs.indexOf(event.target);
		const targets = {
			ArrowRight: index + 1,
			ArrowDown: index + 1,
			ArrowLeft: index - 1,
			ArrowUp: index - 1,
			Home: 0,
			End: tabs.length - 1,
		};
		if (index < 0 || !(event.key in targets)) {
			return;
		}
		event.preventDefault();
		tabs[(targets[event.key] + tabs.length) % tabs.length].focus();
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
	label,
	searchTerm,
	page,
	container,
	paginationContainer,
	status,
	signal,
	onPageClick,
}) {
	if (!container.firstChild) {
		showMessage(container, null, "กำลังโหลด…");
	}
	container.setAttribute("aria-busy", "true");

	try {
		// ไม่ส่ง signal ให้ request หมวดหมู่ เพราะ promise นี้ถูก cache และแชร์กันทุก instance
		const catId = await getCategoryIdBySlug(slug);
		if (signal.aborted) return;
		if (!catId) {
			showMessage(container, status, "ไม่พบหมวดหมู่");
			renderPagination(paginationContainer, 1, 1, onPageClick);
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
		const total = parseInt(res.headers.get("X-WP-Total")) || posts.length;

		if (posts.length) {
			container.innerHTML = generateHTML(posts);
			renderPagination(paginationContainer, totalPages, page, onPageClick);
			announce(status, describeResults({ label, searchTerm, total, page, totalPages }));
		} else {
			showMessage(
				container,
				status,
				searchTerm ? `ไม่พบโพสต์ที่ตรงกับ “${searchTerm}” ในหมวดนี้` : "ไม่พบโพสต์ในหมวดนี้",
			);
			renderPagination(paginationContainer, 1, 1, onPageClick);
		}
	} catch (error) {
		// ถูกยกเลิกเพราะมี request ใหม่กว่าเข้ามา ไม่ใช่ error จริง
		if (signal.aborted) return;
		console.error("Error fetching posts:", error);
		showMessage(container, status, "เกิดข้อผิดพลาดในการโหลดโพสต์");
	} finally {
		if (!signal.aborted) {
			container.removeAttribute("aria-busy");
		}
	}
}

function describeResults({ label, searchTerm, total, page, totalPages }) {
	const scope = searchTerm ? `หมวด ${label} คำค้น “${searchTerm}”` : `หมวด ${label}`;
	const pageInfo = totalPages > 1 ? ` หน้า ${page} จาก ${totalPages}` : "";
	return `${scope}: พบ ${total} โพสต์${pageInfo}`;
}

// ข้อความแจ้งผลลัพธ์ใส่ด้วย textContent เสมอ (มีคำค้นที่ผู้ใช้พิมพ์อยู่ในข้อความ)
function showMessage(container, status, text) {
	const message = document.createElement("p");
	message.className = "rmu-pr-message";
	message.textContent = text;
	container.replaceChildren(message);
	announce(status, text);
}

// role="status" ให้โปรแกรมอ่านหน้าจออ่านผลลัพธ์ใหม่ โดยไม่ต้องย้ายโฟกัสออกจากช่องค้นหา
function announce(status, text) {
	if (status) {
		status.textContent = text;
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

// ลิงก์อยู่ที่หัวข้ออย่างเดียว (CSS ขยายให้คลิกได้ทั้งใบ) ชื่อลิงก์จึงเป็นแค่ชื่อข่าว ไม่รวมคำโปรยทั้งย่อหน้า
// รูปเป็น alt="" เพราะชื่อข่าวอยู่ในลิงก์แล้ว ไม่ให้โปรแกรมอ่านหน้าจออ่านชื่อซ้ำสองรอบ
function generateHTML(posts) {
	const items = posts
		.map((post) => {
			const image = post._embedded?.["wp:featuredmedia"]?.[0]?.source_url || "";
			return `
          <li class="card">
            ${image ? `<img src="${image}" alt="" class="card-image" loading="lazy" decoding="async"/>` : ""}
            <div class="card-body">
              <h3 class="card-title">
                <a href="${post.link}" target="_blank" rel="noopener">${post.title.rendered}<span class="rmu-pr-sr-only"> (เปิดในแท็บใหม่)</span></a>
              </h3>
              <div class="card-excerpt">${post.excerpt.rendered}</div>
            </div>
          </li>
        `;
		})
		.join("");

	return dompurify.sanitize(
		`<ul class="rmu-pr-cards" role="list">${items}</ul>`,
		// DOMPurify ลบ target ทิ้งโดย default ต้องอนุญาตเอง ไม่งั้นลิงก์จะไม่เปิดแท็บใหม่
		{ ADD_ATTR: ["target"] },
	);
}

function renderPagination(container, totalPages, currentPage, onPageClick) {
	if (totalPages <= 1) {
		container.innerHTML = "";
		container.hidden = true; // ไม่เหลือ landmark nav ว่างๆ ให้โปรแกรมอ่านหน้าจอเจอ
		return;
	}

	let buttons = "";

	// ปุ่มก่อนหน้า
	if (currentPage > 1) {
		buttons += `<button type="button" class="pagination-btn" data-page="${
			currentPage - 1
		}" aria-label="หน้าก่อนหน้า">&lt;</button>`;
	}

	// ปุ่มตัวเลขสูงสุด 5 รายการ (centered around currentPage)
	const maxButtons = 5;
	let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
	let end = Math.min(totalPages, start + maxButtons - 1);

	if (end - start < maxButtons - 1) {
		start = Math.max(1, end - maxButtons + 1);
	}

	for (let i = start; i <= end; i++) {
		buttons += `<button type="button" class="pagination-btn" data-page="${i}" aria-label="หน้า ${i}"${
			i === currentPage ? ' aria-current="page"' : ""
		}>${i}</button>`;
	}

	// ปุ่มถัดไป
	if (currentPage < totalPages) {
		buttons += `<button type="button" class="pagination-btn" data-page="${
			currentPage + 1
		}" aria-label="หน้าถัดไป">&gt;</button>`;
	}

	container.innerHTML = buttons;
	container.hidden = false;

	container.querySelectorAll(".pagination-btn").forEach((btn) => {
		btn.addEventListener("click", () => {
			const selectedPage = parseInt(btn.dataset.page);
			if (selectedPage !== currentPage) {
				onPageClick(selectedPage);
			}
		});
	});
}

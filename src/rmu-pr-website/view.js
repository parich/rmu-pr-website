import dompurify from "dompurify";

const CATEGORY_SLUGS = window.POSTS_PR_RMU_DATA?.categorySlugs || [];
const BASE_URL = window.POSTS_PR_RMU_DATA?.baseUrl || "https://pr.rmu.ac.th/";

// ตรวจสอบว่า REST API ใช้ pretty URL หรือ ?rest_route= (cache ผลไว้ไม่ต้องเช็คซ้ำ)
let useRestRoute = null;

async function detectApiStyle() {
	if (useRestRoute !== null) return;
	const prettyUrl = `${BASE_URL}wp-json/wp/v2/posts?per_page=1`;
	try {
		const res = await fetch(prettyUrl);
		const contentType = res.headers.get("content-type") || "";
		if (res.ok && contentType.includes("json")) {
			useRestRoute = false;
		} else {
			useRestRoute = true;
		}
	} catch {
		useRestRoute = true;
	}
}

function buildApiUrl(path, params = {}) {
	const base = BASE_URL.replace(/\/$/, "");
	const qs = new URLSearchParams(params).toString();
	const queryString = qs ? `&${qs}` : "";
	if (useRestRoute) {
		return `${base}/?rest_route=/wp/v2/${path}${queryString}`;
	}
	return `${base}/wp-json/wp/v2/${path}?${qs}`;
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

	let currentPage = 1;

	function refreshResults() {
		const activeTab = tabsContainer.querySelector(".tab.active");
		if (activeTab) {
			fetchAndRenderPosts(
				activeTab.dataset.slug,
				input ? input.value.trim() : "",
				resultsContainer,
				currentPage,
				paginationContainer,
			);
		}
	}

	refreshResults(); // Initial load

	tabsContainer.querySelectorAll(".tab").forEach((tab) => {
		tab.addEventListener("click", () => {
			tabsContainer
				.querySelectorAll(".tab")
				.forEach((t) => t.classList.remove("active"));
			tab.classList.add("active");
			currentPage = 1;
			refreshResults();
		});
	});

	if (input) {
		input.addEventListener("input", () => {
			currentPage = 1;
			refreshResults();
		});
	}
}

async function fetchAndRenderPosts(
	slug,
	searchTerm,
	container,
	page = 1,
	paginationContainer,
) {
	try {
		await detectApiStyle();
		const catId = await getCategoryIdBySlug(slug);
		if (!catId) {
			container.innerHTML = "ไม่พบหมวดหมู่";
			return;
		}

		const query = buildApiUrl("posts", {
			categories: catId,
			search: searchTerm,
			page,
			per_page: 8,
			_embed: 1,
		});

		const res = await fetch(query);
		const posts = await res.json();
		const totalPages = parseInt(res.headers.get("X-WP-TotalPages"));

		if (posts.length) {
			container.innerHTML = generateHTML(posts);
			renderPagination(paginationContainer, totalPages, page, (newPage) => {
				fetchAndRenderPosts(
					slug,
					searchTerm,
					container,
					newPage,
					paginationContainer,
				);
			});
		} else {
			container.innerHTML = "ไม่พบโพสต์ในหมวดนี้";
			paginationContainer.innerHTML = "";
		}
	} catch (error) {
		console.error("Error fetching posts:", error);
		container.innerHTML = "เกิดข้อผิดพลาดในการโหลดโพสต์";
	}
}

async function getCategoryIdBySlug(slug) {
	const url = buildApiUrl("categories", { slug });
	const res = await fetch(url);
	const categories = await res.json();
	return categories[0]?.id || null;
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

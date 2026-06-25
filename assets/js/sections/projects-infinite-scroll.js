const DEFAULT_PROJECTS_PER_PAGE = 9;

const GRID_SELECTOR = "[data-projects-grid]";
const SENTINEL_SELECTOR = "[data-projects-sentinel]";
const LOADER_SELECTOR = "[data-projects-loader]";
const END_SELECTOR = "[data-projects-end]";

export default function initProjectsInfiniteScroll() {
  const grid = document.querySelector(GRID_SELECTOR);
  if (!grid) return;

  const sentinel = document.querySelector(SENTINEL_SELECTOR);
  if (!sentinel) return;

  const state = {
    endpoint: grid.dataset.projectsEndpoint || "/site-api/projects",
    limit: parsePositiveInt(
      grid.dataset.projectsPerPage,
      DEFAULT_PROJECTS_PER_PAGE,
    ),
    offset: parsePositiveInt(grid.dataset.projectsOffset, grid.children.length),
    total: parsePositiveInt(grid.dataset.projectsTotal, 0),
    hasMore: grid.dataset.projectsHasMore === "true",
    loading: false,
  };

  const loader = document.querySelector(LOADER_SELECTOR);
  const end = document.querySelector(END_SELECTOR);

  if (!state.hasMore) {
    showEnd(end, sentinel);
    return;
  }

  const loadNext = () => loadNextProjects(grid, sentinel, loader, end, state);

  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (!entry?.isIntersecting) return;

        loadNext();
      },
      {
        root: null,
        rootMargin: "700px 0px",
        threshold: 0.01,
      },
    );

    observer.observe(sentinel);
    return;
  }

  window.addEventListener(
    "scroll",
    () => {
      const rect = sentinel.getBoundingClientRect();
      if (rect.top <= window.innerHeight + 700) {
        loadNext();
      }
    },
    { passive: true },
  );
}

async function loadNextProjects(grid, sentinel, loader, end, state) {
  if (state.loading || !state.hasMore) return;

  state.loading = true;
  grid.setAttribute("aria-busy", "true");

  if (loader) loader.hidden = false;

  try {
    const url = new URL(state.endpoint, window.location.origin);
    url.searchParams.set("limit", String(state.limit));
    url.searchParams.set("offset", String(state.offset));

    const response = await fetch(url.toString(), {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    const data = await response.json().catch(() => null);

    if (!response.ok || !data?.ok) {
      throw new Error(data?.message || "No se pudieron cargar los proyectos.");
    }

    const projects = Array.isArray(data.projects) ? data.projects : [];

    projects.forEach((project) => {
      grid.insertAdjacentHTML("beforeend", renderProjectCard(project));
    });

    state.offset = Number(data.next_offset ?? state.offset + projects.length);
    state.total = Number(data.total ?? state.total);
    state.hasMore = Boolean(data.has_more);

    grid.dataset.projectsOffset = String(state.offset);
    grid.dataset.projectsTotal = String(state.total);
    grid.dataset.projectsHasMore = state.hasMore ? "true" : "false";

    if (!state.hasMore) {
      showEnd(end, sentinel);
    }
  } catch (error) {
    console.error(error);
  } finally {
    state.loading = false;
    grid.removeAttribute("aria-busy");

    if (loader) loader.hidden = true;
  }
}

function renderProjectCard(project = {}) {
  const title = String(project.title || "Proyecto");
  const href = String(project.href || "#");
  const image = String(project.image || "/images/JTRON.jpg");
  const imageAlt = String(project.image_alt || title);
  const category = String(project.category || "Proyecto");
  const location = String(project.location || "");
  const year = String(project.year || "");
  const brief = String(project.brief || "");
  const number = String(project.number || "");
  const tags = Array.isArray(project.tags) ? project.tags : [];
  const isFeatured = Boolean(project.is_featured);

  return `
    <a
      class="latest__projects__item${isFeatured ? " latest__projects__item--featured" : ""}"
      href="${escapeAttr(href)}"
      aria-label="Ver proyecto ${escapeAttr(title)}">

      <div class="latest__projects__item__image">
        <span class="entry__category${isFeatured ? " entry__category--featured" : ""}">
          ${escapeHtml(isFeatured ? "Más nuevo" : category)}
        </span>

        <img
          src="${escapeAttr(image)}"
          alt="${escapeAttr(imageAlt)}"
          loading="lazy"
          decoding="async"
          width="1200"
          height="800">
      </div>

      <div class="latest__projects__item__text">
        <div class="entry__top">
          <ul class="entry__tags">
            ${
              isFeatured
                ? '<li class="entry__tag--featured">Destacado</li>'
                : ""
            }

            ${tags
              .filter(Boolean)
              .map((tag) => `<li>${escapeHtml(tag)}</li>`)
              .join("")}
          </ul>

          ${number ? `<span class="entry__number">${escapeHtml(number)}</span>` : ""}
        </div>

        <h4>${escapeHtml(title)}</h4>

        <div class="entry__data">
          ${location ? `<p>${escapeHtml(location)}</p>` : ""}
          ${year ? `<p class="author">${escapeHtml(year)}</p>` : ""}
        </div>

        ${brief ? `<p class="entry__brief">${escapeHtml(brief)}</p>` : ""}

        <span class="entry__read-more">Ver proyecto <span>→</span></span>
      </div>
    </a>
  `;
}

function showEnd(end, sentinel) {
  if (end) end.hidden = false;
  if (sentinel) sentinel.hidden = true;
}

function parsePositiveInt(value, fallback) {
  const number = Number.parseInt(value, 10);

  if (!Number.isFinite(number) || number < 0) {
    return fallback;
  }

  return number;
}

function escapeHtml(value = "") {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function escapeAttr(value = "") {
  return escapeHtml(value);
}
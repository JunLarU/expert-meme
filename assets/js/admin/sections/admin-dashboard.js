export default function initAdminDashboard() {
  initAdminSidebar();
  initAdminInitials();
  initAdminUserMenu();
  initAdminCounters();
  initAdminSearch();
}

function initAdminSidebar() {
  const shell = document.querySelector("[data-admin-shell]");
  const sidebar = document.querySelector("[data-admin-sidebar]");
  const toggle = document.querySelector("[data-admin-sidebar-toggle]");

  if (!shell || !sidebar || !toggle) return;

  const storageKey = "le-admin-sidebar-collapsed";
  const mobileQuery = window.matchMedia("(max-width: 1023px)");

  const isMobile = () => mobileQuery.matches;

  const setExpanded = (isExpanded) => {
    toggle.setAttribute("aria-expanded", String(isExpanded));
  };

  const openMobileSidebar = () => {
    document.body.classList.add("admin-sidebar-open");
    document.body.classList.remove("admin-sidebar-collapsed");
    setExpanded(true);
  };

  const closeMobileSidebar = () => {
    document.body.classList.remove("admin-sidebar-open");
    setExpanded(false);
  };

  const applyDesktopState = (isCollapsed) => {
    document.body.classList.toggle("admin-sidebar-collapsed", isCollapsed);
    document.body.classList.remove("admin-sidebar-open");
    setExpanded(!isCollapsed);
  };

  const syncStateWithViewport = () => {
    if (isMobile()) {
      document.body.classList.remove("admin-sidebar-collapsed");
      setExpanded(document.body.classList.contains("admin-sidebar-open"));
      return;
    }

    const saved = localStorage.getItem(storageKey);
    applyDesktopState(saved === "true");
  };

  toggle.addEventListener("click", () => {
    if (isMobile()) {
      const isOpen = document.body.classList.contains("admin-sidebar-open");

      if (isOpen) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }

      return;
    }

    const nextState = !document.body.classList.contains(
      "admin-sidebar-collapsed"
    );

    applyDesktopState(nextState);
    localStorage.setItem(storageKey, String(nextState));
  });

  sidebar.addEventListener("click", (event) => {
    const link = event.target.closest("a");

    if (link && isMobile()) {
      closeMobileSidebar();
    }
  });

  document.addEventListener("click", (event) => {
    if (!isMobile()) return;

    const isOpen = document.body.classList.contains("admin-sidebar-open");
    const clickedSidebar = sidebar.contains(event.target);
    const clickedToggle = toggle.contains(event.target);

    if (isOpen && !clickedSidebar && !clickedToggle) {
      closeMobileSidebar();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;

    if (isMobile()) {
      closeMobileSidebar();
    }

    closeAdminUserMenu();
  });

  if (typeof mobileQuery.addEventListener === "function") {
    mobileQuery.addEventListener("change", syncStateWithViewport);
  } else {
    mobileQuery.addListener(syncStateWithViewport);
  }

  syncStateWithViewport();
}

function initAdminInitials() {
  const avatars = document.querySelectorAll("[data-admin-initials]");

  avatars.forEach((avatar) => {
    const name =
      avatar.dataset.adminName ||
      avatar.dataset.name ||
      avatar.textContent ||
      "Admin";

    avatar.textContent = getInitials(name);
    avatar.setAttribute("title", name.trim());
  });
}

function getInitials(name) {
  const words = String(name || "")
    .trim()
    .replace(/\s+/g, " ")
    .split(" ")
    .filter(Boolean);

  if (!words.length) return "AD";

  if (words.length === 1) {
    return words[0].slice(0, 2).toUpperCase();
  }

  return `${words[0][0]}${words[1][0]}`.toUpperCase();
}

function initAdminUserMenu() {
  const wrapper = document.querySelector("[data-admin-user-menu-wrapper]");
  const toggle = document.querySelector("[data-admin-user-menu-toggle]");
  const menu = document.querySelector("[data-admin-user-menu]");

  if (!wrapper || !toggle || !menu) return;

  const openMenu = () => {
    wrapper.classList.add("is-open");
    menu.hidden = false;
    toggle.setAttribute("aria-expanded", "true");
  };

  const closeMenu = () => {
    wrapper.classList.remove("is-open");
    menu.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
  };

  const toggleMenu = () => {
    if (wrapper.classList.contains("is-open")) {
      closeMenu();
      return;
    }

    openMenu();
  };

  toggle.addEventListener("click", (event) => {
    event.stopPropagation();
    toggleMenu();
  });

  menu.addEventListener("click", (event) => {
    event.stopPropagation();
  });

  document.addEventListener("click", (event) => {
    if (!wrapper.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });
}

function closeAdminUserMenu() {
  const wrapper = document.querySelector("[data-admin-user-menu-wrapper]");
  const toggle = document.querySelector("[data-admin-user-menu-toggle]");
  const menu = document.querySelector("[data-admin-user-menu]");

  if (!wrapper || !toggle || !menu) return;

  wrapper.classList.remove("is-open");
  menu.hidden = true;
  toggle.setAttribute("aria-expanded", "false");
}

function initAdminCounters() {
  const counters = document.querySelectorAll("[data-admin-count]");

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  );

  counters.forEach((counter) => {
    const target = Number(counter.dataset.value || counter.textContent || 0);

    if (!Number.isFinite(target)) {
      counter.textContent = "0";
      return;
    }

    if (prefersReducedMotion.matches) {
      counter.textContent = formatNumber(target);
      return;
    }

    animateCounter(counter, target);
  });
}

function animateCounter(element, target) {
  const duration = 700;
  const startTime = performance.now();

  const tick = (now) => {
    const progress = Math.min((now - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(target * eased);

    element.textContent = formatNumber(current);

    if (progress < 1) {
      requestAnimationFrame(tick);
    }
  };

  requestAnimationFrame(tick);
}

function formatNumber(value) {
  return new Intl.NumberFormat("es-MX").format(value);
}

function initAdminSearch() {
  const input = document.querySelector("[data-admin-search]");
  const filterables = document.querySelectorAll("[data-admin-filterable]");

  if (!input || !filterables.length) return;

  input.addEventListener("input", () => {
    const query = normalize(input.value);

    filterables.forEach((container) => {
      const items = getFilterableItems(container);

      items.forEach((item) => {
        const text = normalize(item.textContent);
        item.hidden = query.length > 0 && !text.includes(query);
      });
    });
  });
}

function getFilterableItems(container) {
  if (container.matches("table")) {
    return Array.from(container.querySelectorAll("tbody tr"));
  }

  return Array.from(container.children);
}

function normalize(value) {
  return String(value || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/\p{Diacritic}/gu, "")
    .trim();
}
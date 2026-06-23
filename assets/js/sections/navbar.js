export default function initNavbar() {
  const navbar = document.querySelector(".navbar");
  const btn = document.querySelector(".navbar__burger__btn-menu");
  const links = document.querySelector(".navbar__links");
  const footer = document.querySelector(".site-footer, footer");
  const body = document.body;
  const html = document.documentElement;
  const mqTablet = window.matchMedia("(min-width: 1024px)");

  if (!navbar) return;

  let ticking = false;
  let isSticky = false;
  let dropTimer = null;
  let returnTimer = null;

  const isMobileMenuOpen = () => {
    return !mqTablet.matches && links?.classList.contains("active");
  };

  const lockScroll = () => {
    html.classList.add("scroll-lock");
    body.classList.add("scroll-lock");
  };

  const unlockScroll = () => {
    html.classList.remove("scroll-lock");
    body.classList.remove("scroll-lock");
  };

  const openMenu = () => {
    btn?.classList.add("menu-open");
    links?.classList.add("active");
    navbar.classList.add("active");
    navbar.classList.remove("is-hidden-for-footer");

    btn?.setAttribute("aria-expanded", "true");

    if (!mqTablet.matches) {
      links.scrollTop = 0;
      lockScroll();
    }
  };

  const closeMenu = () => {
    btn?.classList.remove("menu-open");
    links?.classList.remove("active");
    navbar.classList.remove("active");

    btn?.setAttribute("aria-expanded", "false");

    unlockScroll();
    requestNavbarUpdate();
  };

  const toggleMenu = () => {
    if (links?.classList.contains("active")) closeMenu();
    else openMenu();
  };

  const thresholdPx = () => window.innerHeight * 0.25;

  const getVisibleHeight = (element) => {
    if (!element) return 0;

    const rect = element.getBoundingClientRect();
    const viewportHeight =
      window.innerHeight || document.documentElement.clientHeight;

    const visibleTop = Math.max(rect.top, 0);
    const visibleBottom = Math.min(rect.bottom, viewportHeight);

    return Math.max(0, visibleBottom - visibleTop);
  };

  const getLargestVisibleContentHeight = () => {
    const candidates = new Set([
      ...document.querySelectorAll("main > section"),
      ...document.querySelectorAll("main > div"),
      ...document.querySelectorAll(".home__jumbotron"),
      ...document.querySelectorAll("section"),
    ]);

    candidates.delete(footer);
    candidates.delete(navbar);

    let maxVisible = 0;

    candidates.forEach((element) => {
      const visibleHeight = getVisibleHeight(element);
      if (visibleHeight > maxVisible) maxVisible = visibleHeight;
    });

    return maxVisible;
  };

const updateSticky = () => {
  if (isMobileMenuOpen()) return;

  const nextSticky = window.scrollY > thresholdPx();

  if (nextSticky === isSticky) return;

  isSticky = nextSticky;

  window.clearTimeout(dropTimer);
  window.clearTimeout(returnTimer);

  if (isSticky) {
    navbar.classList.remove("navbar--return-top");
    navbar.classList.add("sticky", "navbar--drop-in");

    dropTimer = window.setTimeout(() => {
      navbar.classList.remove("navbar--drop-in");
    }, 560);

    return;
  }

  navbar.classList.remove("sticky", "navbar--drop-in");
  navbar.classList.add("navbar--return-top");

  returnTimer = window.setTimeout(() => {
    navbar.classList.remove("navbar--return-top");
  }, 520);
};
  const updateFooterVisibility = () => {
    if (!footer) {
      navbar.classList.remove("is-hidden-for-footer");
      return;
    }

    if (isMobileMenuOpen()) {
      navbar.classList.remove("is-hidden-for-footer");
      return;
    }

    const footerVisibleHeight = getVisibleHeight(footer);
    const largestContentVisibleHeight = getLargestVisibleContentHeight();
    const minFooterVisibleToHide = Math.min(window.innerHeight * 0.28, 240);

    const footerIsDominant =
      footerVisibleHeight > minFooterVisibleToHide &&
      footerVisibleHeight >= largestContentVisibleHeight;

    navbar.classList.toggle("is-hidden-for-footer", footerIsDominant);
  };

  const updateNavbar = () => {
    ticking = false;
    updateSticky();
    updateFooterVisibility();
  };

  function requestNavbarUpdate() {
    if (ticking) return;

    ticking = true;
    requestAnimationFrame(updateNavbar);
  }

  document.addEventListener("click", (e) => {
    const clickedBtn = e.target.closest(".navbar__burger__btn-menu");
    if (!clickedBtn) return;

    e.preventDefault();
    toggleMenu();
  });

  links?.addEventListener("click", (e) => {
    const clickedLink = e.target.closest("a");
    if (!clickedLink || !isMobileMenuOpen()) return;

    closeMenu();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape" || !isMobileMenuOpen()) return;

    closeMenu();
  });

  window.addEventListener("scroll", requestNavbarUpdate, { passive: true });
  window.addEventListener("resize", requestNavbarUpdate, { passive: true });
  window.addEventListener("orientationchange", requestNavbarUpdate, {
    passive: true,
  });

  mqTablet.addEventListener?.("change", () => {
    if (mqTablet.matches) {
      closeMenu();
    }

    requestNavbarUpdate();
  });

  updateNavbar();
}
const RELATED_SELECTOR = "[data-project-entry-related]";
const TOGGLE_SELECTOR = "[data-project-entry-related-toggle]";
const TOGGLE_TEXT_SELECTOR = "[data-project-entry-related-toggle-text]";
const PANEL_SELECTOR = "[data-project-entry-related-panel]";

const START_SELECTOR = ".project-entry__intro";
const FOOTER_SELECTOR = ".site-footer, footer";
const NAVBAR_SELECTOR = ".navbar";

const STORAGE_KEY = "projectEntryRelatedOpen";

export default function initProjectEntryRelated() {
  const related = document.querySelector(RELATED_SELECTOR);
  const toggle = related?.querySelector(TOGGLE_SELECTOR);
  const toggleText = related?.querySelector(TOGGLE_TEXT_SELECTOR);
  const panel = related?.querySelector(PANEL_SELECTOR);

  const startSection = document.querySelector(START_SELECTOR);
  const footer = document.querySelector(FOOTER_SELECTOR);
  const navbar = document.querySelector(NAVBAR_SELECTOR);

  if (!related || !toggle || !panel || !startSection) return null;

  const mqDesktop = window.matchMedia("(min-width: 1024px)");

  let ticking = false;
  let isOpen = getInitialOpenState();

  function getStoredState() {
    try {
      return window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
      return null;
    }
  }

  function setStoredState(value) {
    try {
      window.localStorage.setItem(STORAGE_KEY, value ? "true" : "false");
    } catch (error) {}
  }

  function getInitialOpenState() {
    const storedState = getStoredState();

    if (storedState === "true") return true;
    if (storedState === "false") return false;

    return mqDesktop.matches;
  }

  function refreshLazyContent() {
    /*
      Tu framework escucha whis:load y refresca lazy images/videos.
      Esto ayuda cuando el panel estaba oculto y luego se abre.
    */
    requestAnimationFrame(() => {
      document.dispatchEvent(
        new CustomEvent("whis:load", {
          bubbles: true,
        }),
      );
    });
  }

  function setOpen(nextOpen, options = {}) {
    const { persist = true } = options;

    isOpen = Boolean(nextOpen);

    related.classList.toggle("is-open", isOpen);
    toggle.setAttribute("aria-expanded", String(isOpen));

    if (toggleText) {
      toggleText.textContent = isOpen ? "Ocultar obras" : "Otras obras";
    }

    if (persist) {
      setStoredState(isOpen);
    }

    if (isOpen) {
      refreshLazyContent();
    }
  }

  const getNavbarOffset = () => {
    const navbarHeight = navbar?.getBoundingClientRect().height || 0;
    const baseOffset = window.innerWidth >= 1024 ? 18 : 12;

    return Math.max(92, navbarHeight + baseOffset);
  };

  const getVisibleHeight = (element) => {
    if (!element) return 0;

    const rect = element.getBoundingClientRect();
    const viewportHeight =
      window.innerHeight || document.documentElement.clientHeight;

    const visibleTop = Math.max(rect.top, 0);
    const visibleBottom = Math.min(rect.bottom, viewportHeight);

    return Math.max(0, visibleBottom - visibleTop);
  };

  const updateRelated = () => {
    ticking = false;

    const topOffset = getNavbarOffset();
    const startRect = startSection.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();

    related.style.setProperty("--project-related-top", `${topOffset}px`);

    const hasReachedIntro = startRect.top <= topOffset;

    let shouldHideForFooter = false;

    if (footer) {
      const footerRect = footer.getBoundingClientRect();
      const footerVisibleHeight = getVisibleHeight(footer);
      const minFooterVisibleToHide = Math.min(window.innerHeight * 0.18, 180);

      const floatingHeight = isOpen ? panelRect.height + 72 : 72;

      const floatingWouldTouchFooter =
        footerRect.top <= topOffset + floatingHeight + 24;

      shouldHideForFooter =
        footerVisibleHeight > minFooterVisibleToHide || floatingWouldTouchFooter;
    }

    const isAvailable = hasReachedIntro && !shouldHideForFooter;

    related.classList.toggle("is-available", isAvailable);
    related.classList.toggle("is-hidden-for-footer", shouldHideForFooter);
    related.setAttribute("aria-hidden", isAvailable ? "false" : "true");
  };

  const requestUpdate = () => {
    if (ticking) return;

    ticking = true;
    requestAnimationFrame(updateRelated);
  };

  toggle.addEventListener("click", () => {
    setOpen(!isOpen);
    requestUpdate();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || !isOpen) return;

    setOpen(false);
    requestUpdate();
  });

  mqDesktop.addEventListener?.("change", () => {
    /*
      Solo cambia automático cuando el usuario todavía no guardó preferencia.
    */
    if (getStoredState() === null) {
      setOpen(mqDesktop.matches, { persist: false });
    }

    requestUpdate();
  });

  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate, { passive: true });
  window.addEventListener("orientationchange", requestUpdate, { passive: true });

  setOpen(isOpen, { persist: false });
  updateRelated();

  return {
    refresh: updateRelated,
    open: () => setOpen(true),
    close: () => setOpen(false),
    toggle: () => setOpen(!isOpen),
    destroy: () => {
      window.removeEventListener("scroll", requestUpdate);
      window.removeEventListener("resize", requestUpdate);
      window.removeEventListener("orientationchange", requestUpdate);
    },
  };
}
const DEFAULT_SELECTOR = "[data-valuation-clients-carousel]";
const TRACK_SELECTOR = "[data-valuation-clients-track]";
const ITEM_SELECTOR = "[data-valuation-logo-item]";
const CLONE_SELECTOR = '[data-valuation-clients-clone="true"]';

const DEFAULTS = {
  speed: 58,
  minCycles: 4,
  resizeDebounceMs: 180,
};

const instances = new WeakMap();

export default function initValuationClientsCarousel(options = {}) {
  if (!hasDOM()) return [];

  const config = {
    ...DEFAULTS,
    ...options,
  };

  return Array.from(document.querySelectorAll(options.selector || DEFAULT_SELECTOR))
    .map((carousel) => prepareCarousel(carousel, config))
    .filter(Boolean);
}

function prepareCarousel(carousel, config) {
  if (!(carousel instanceof HTMLElement)) return null;

  const previous = instances.get(carousel);

  if (previous?.cleanup) {
    previous.cleanup();
  }

  const track = carousel.querySelector(TRACK_SELECTOR);

  if (!(track instanceof HTMLElement)) {
    carousel.dataset.valuationClientsState = "missing-track";
    return null;
  }

  const reducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)");
  let resizeTimer = 0;
  let current = null;

  const build = () => {
    stopTrack(track);
    removeClones(carousel);

    /*
     * Defensa extra para el caso que estás viendo:
     * si por HTML mal balanceado el navegador dejó tarjetas fuera del track
     * (por ejemplo debajo del slider), las regresamos al track antes de medir.
     */
    collectStrayItems(carousel, track);

    const originalItems = getOriginalItems(track);
    const originalCount = originalItems.length;

    carousel.dataset.valuationClientsOriginalItems = String(originalCount);

    if (originalCount === 0) {
      carousel.dataset.valuationClientsState = "empty";
      carousel.dataset.valuationClientsReady = "true";
      carousel.dataset.valuationClientsTotalItems = "0";
      return null;
    }

    originalItems.forEach((item, index) => prepareOriginalItem(item, index));

    /*
     * Este marquee se mueve con transform. No dependemos del lazy loader para
     * los logos, porque puede dejarlos fuera de medición o cargarlos tarde.
     */
    hydrateMedia(track);

    if (reducedMotion?.matches) {
      carousel.dataset.valuationClientsState = originalCount === 1 ? "single-static" : "static";
      carousel.dataset.valuationClientsReady = "true";
      carousel.dataset.valuationClientsTotalItems = String(originalCount);
      return { carousel, track, originalItems: originalCount, totalItems: originalCount };
    }

    const cycleWidth = measureOriginalCycleWidth(track, originalItems);
    const viewportWidth = Math.max(
      1,
      carousel.getBoundingClientRect().width || window.innerWidth || 1,
    );

    if (!Number.isFinite(cycleWidth) || cycleWidth <= 0) {
      carousel.dataset.valuationClientsState = "not-measurable";
      carousel.dataset.valuationClientsReady = "true";
      carousel.dataset.valuationClientsTotalItems = String(originalCount);
      return null;
    }

    const minCycles = readInteger(
      carousel.dataset.minCycles,
      config.minCycles,
      2,
      30,
    );

    const requiredCycles = Math.max(
      minCycles,
      Math.ceil((viewportWidth + cycleWidth) / cycleWidth) + 2,
    );

    const totalItems = ensureCycles(track, originalItems, requiredCycles);
    hydrateMedia(track);

    const speed = readNumber(carousel.dataset.speed, config.speed, 20, 180);
    const duration = Math.max(20, cycleWidth / speed);

    carousel.style.setProperty("--valuation-marquee-cycle-width", `${round(cycleWidth)}px`);
    carousel.style.setProperty("--valuation-marquee-duration", `${round(duration)}s`);

    carousel.dataset.valuationClientsState = originalCount === 1 ? "single-loop" : "multiple-loop";
    carousel.dataset.valuationClientsReady = "true";
    carousel.dataset.valuationClientsTotalItems = String(totalItems);

    startTrack(track);

    return {
      carousel,
      track,
      originalItems: originalCount,
      totalItems,
      duration,
      cycleWidth,
    };
  };

  const scheduleBuild = () => {
    window.clearTimeout(resizeTimer);

    resizeTimer = window.setTimeout(() => {
      current = build();
      instances.set(carousel, { cleanup, current });
    }, config.resizeDebounceMs);
  };

  const onResize = () => {
    scheduleBuild();
  };

  const cleanup = () => {
    window.clearTimeout(resizeTimer);
    window.removeEventListener("resize", onResize);
    stopTrack(track);
    removeClones(carousel);

    delete carousel.dataset.valuationClientsReady;
    delete carousel.dataset.valuationClientsState;
    delete carousel.dataset.valuationClientsOriginalItems;
    delete carousel.dataset.valuationClientsTotalItems;
  };

  current = build();
  window.addEventListener("resize", onResize, { passive: true });

  instances.set(carousel, { cleanup, current });

  return current;
}

function collectStrayItems(carousel, track) {
  const strayItems = Array.from(carousel.querySelectorAll(ITEM_SELECTOR)).filter((item) => {
    if (!(item instanceof HTMLElement)) return false;
    if (item.dataset.valuationClientsClone === "true") return false;
    return item.parentElement !== track;
  });

  if (strayItems.length === 0) return;

  const fragment = document.createDocumentFragment();

  strayItems.forEach((item) => {
    fragment.appendChild(item);
  });

  track.appendChild(fragment);
}

function prepareOriginalItem(item, index) {
  item.dataset.valuationClientsOriginal = "true";
  item.dataset.valuationClientsOriginalIndex = String(index);
  item.removeAttribute("aria-hidden");

  if (!item.hasAttribute("role")) {
    item.setAttribute("role", "listitem");
  }

  if (!item.hasAttribute("tabindex")) {
    item.setAttribute("tabindex", "0");
  }
}

function getOriginalItems(track) {
  return Array.from(track.children).filter((item) => (
    item instanceof HTMLElement
    && item.matches(ITEM_SELECTOR)
    && item.dataset.valuationClientsClone !== "true"
  ));
}

function removeClones(scope) {
  scope.querySelectorAll(CLONE_SELECTOR).forEach((clone) => clone.remove());
}

function stopTrack(track) {
  track.style.animationName = "none";
  track.style.animationDuration = "";
  track.style.animationTimingFunction = "";
  track.style.animationIterationCount = "";
  track.style.animationPlayState = "paused";
}

function startTrack(track) {
  // Reinicia la animación después de medir y clonar, sin reconstruir por carga de imágenes.
  void track.offsetWidth;

  track.style.animationName = "valuationClientsMarqueeScroll";
  track.style.animationDuration = "var(--valuation-marquee-duration)";
  track.style.animationTimingFunction = "linear";
  track.style.animationIterationCount = "infinite";
  track.style.animationPlayState = "running";
}

function measureOriginalCycleWidth(track, originalItems) {
  const styles = window.getComputedStyle(track);
  const gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;

  const width = originalItems.reduce((total, item) => {
    const rect = item.getBoundingClientRect();
    const itemWidth = Number.isFinite(rect.width) ? rect.width : 0;

    return total + itemWidth;
  }, 0);

  return width + gap * originalItems.length;
}

function ensureCycles(track, originalItems, cycles) {
  const originalCount = originalItems.length;
  const safeCycles = Math.max(1, Math.trunc(cycles));

  if (originalCount === 0) return 0;

  for (let cycle = 1; cycle < safeCycles; cycle += 1) {
    originalItems.forEach((source, index) => {
      const clone = source.cloneNode(true);

      clone.dataset.valuationClientsClone = "true";
      clone.dataset.valuationClientsOriginalIndex = String(index);
      clone.setAttribute("aria-hidden", "true");
      clone.setAttribute("tabindex", "-1");

      clone.querySelectorAll("[id]").forEach((element) => {
        element.removeAttribute("id");
      });

      clone.querySelectorAll("[aria-describedby], [aria-labelledby]").forEach((element) => {
        element.removeAttribute("aria-describedby");
        element.removeAttribute("aria-labelledby");
      });

      clone
        .querySelectorAll("a, button, input, select, textarea, [tabindex]")
        .forEach((element) => {
          element.setAttribute("tabindex", "-1");
        });

      clone.querySelectorAll("img").forEach((image) => {
        image.setAttribute("alt", "");
      });

      track.appendChild(clone);
    });
  }

  return originalCount * safeCycles;
}

function hydrateMedia(scope) {
  hydrateImages(scope);
  hydrateBackgrounds(scope);
}

function hydrateImages(scope) {
  scope.querySelectorAll("img").forEach((image) => {
    const dataSrc = image.getAttribute("data-src");
    const dataSrcset = image.getAttribute("data-srcset");
    const dataSizes = image.getAttribute("data-sizes");

    if (dataSrc && image.getAttribute("src") !== dataSrc) {
      image.setAttribute("src", dataSrc);
    }

    if (dataSrcset && image.getAttribute("srcset") !== dataSrcset) {
      image.setAttribute("srcset", dataSrcset);
    }

    if (dataSizes && image.getAttribute("sizes") !== dataSizes) {
      image.setAttribute("sizes", dataSizes);
    }

    image.setAttribute("loading", "eager");
    image.setAttribute("decoding", "async");
    image.classList.remove("lazy-image", "lazy", "is-lazy");
  });
}

function hydrateBackgrounds(scope) {
  scope.querySelectorAll("[data-background-src]").forEach((element) => {
    const src = element.getAttribute("data-background-src");

    if (!src) return;

    element.style.backgroundImage = `url("${src}")`;
  });
}

function readNumber(value, fallback, min = -Infinity, max = Infinity) {
  const parsed = Number.parseFloat(String(value ?? ""));
  const number = Number.isFinite(parsed) ? parsed : fallback;

  return Math.min(max, Math.max(min, number));
}

function readInteger(value, fallback, min = -Infinity, max = Infinity) {
  const parsed = Number.parseInt(String(value ?? ""), 10);
  const number = Number.isFinite(parsed) ? parsed : fallback;

  return Math.min(max, Math.max(min, number));
}

function round(value) {
  return Math.round(value * 1000) / 1000;
}

function hasDOM() {
  return typeof window !== "undefined" && typeof document !== "undefined";
}

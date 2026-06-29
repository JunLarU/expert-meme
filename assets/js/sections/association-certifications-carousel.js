const DEFAULT_SELECTOR =
  "[data-association-carousel], .nosotros-carousel--home-logos, .nosotros-carousel--logos";

const ITEM_SELECTOR = ".nosotros-carousel__item";
const CLONE_SELECTOR = '[data-association-carousel-clone="true"]';

const DEFAULTS = {
  minItems: 9,
  secondsPerItem: 3,
  transitionRatio: 0.27,
};

let styleElement = null;
const generatedKeyframes = new Set();

export default function initAssociationCertificationsCarousel(options = {}) {
  if (!hasDOM()) return [];

  const config = {
    ...DEFAULTS,
    ...options,
  };

  const carousels = Array.from(
    document.querySelectorAll(options.selector || DEFAULT_SELECTOR),
  );

  const initialized = carousels
    .map((carousel, index) => prepareCarousel(carousel, config, index))
    .filter(Boolean);

  if (initialized.length > 0) {
    notifyLazyLoaders();
  }

  return initialized;
}

function prepareCarousel(carousel, config, carouselIndex = 0) {
  if (!(carousel instanceof HTMLElement)) return null;

  resetCarousel(carousel);

  const originalItems = getOriginalItems(carousel);
  const originalCount = originalItems.length;

  if (originalCount === 0) {
    carousel.dataset.associationCarouselState = "empty";
    return null;
  }

  originalItems.forEach((item, index) => {
    item.dataset.associationCarouselOriginal = "true";
    item.dataset.associationCarouselOriginalIndex = String(index);
    item.dataset.associationCarouselPosition = String(index);
    item.setAttribute("role", item.getAttribute("role") || "listitem");

    if (!item.hasAttribute("tabindex")) {
      item.setAttribute("tabindex", "0");
    }
  });

  const targetCount = resolveTargetCount(originalCount, config.minItems);
  const totalItems = ensureFullCycles(carousel, originalItems, targetCount);

  const secondsPerItem = readNumber(
    carousel.dataset.carouselSecondsPerItem,
    config.secondsPerItem,
  );

  const transitionRatio = clampNumber(config.transitionRatio, 0.08, 0.45);
  const duration = Math.max(totalItems * secondsPerItem, secondsPerItem);
  const centerStartSeconds = secondsPerItem * (1 + transitionRatio);
  const keyframesName = ensureKeyframes(totalItems, transitionRatio);

  const items = Array.from(carousel.querySelectorAll(ITEM_SELECTOR));

  items.forEach((item, index) => {
    /**
     * Importante:
     * - index 0 queda centrado desde el primer frame.
     * - index 1 entra después, luego index 2, etc.
     * - el total SIEMPRE es múltiplo de los originales, así el cierre es:
     *   último -> primero, sin repetir uno extra ni saltarse el último.
     */
    const delay = index * secondsPerItem - centerStartSeconds;

    item.style.animationName = keyframesName;
    item.style.animationDuration = `${duration}s`;
    item.style.animationTimingFunction = "linear";
    item.style.animationIterationCount = "infinite";
    item.style.animationDelay = `${round(delay)}s`;
    item.style.animationFillMode = "both";

    item.dataset.associationCarouselPosition = String(index);
  });

  carousel.style.setProperty("--carousel-duration", `${duration}s`);
  carousel.style.setProperty("--carousel-total-items", String(totalItems));
  carousel.style.setProperty("--carousel-original-items", String(originalCount));
  carousel.dataset.associationCarouselReady = "true";
  carousel.dataset.associationCarouselState = originalCount === 1 ? "single" : "multiple";

  return {
    carousel,
    originalItems: originalCount,
    totalItems,
    duration,
    keyframesName,
    carouselIndex,
  };
}

function resetCarousel(carousel) {
  carousel.querySelectorAll(CLONE_SELECTOR).forEach((clone) => clone.remove());

  carousel.querySelectorAll(ITEM_SELECTOR).forEach((item) => {
    item.style.animationName = "";
    item.style.animationDuration = "";
    item.style.animationTimingFunction = "";
    item.style.animationIterationCount = "";
    item.style.animationDelay = "";
    item.style.animationFillMode = "";

    delete item.dataset.associationCarouselPosition;
  });

  delete carousel.dataset.associationCarouselReady;
  delete carousel.dataset.associationCarouselState;
}

function getOriginalItems(carousel) {
  return Array.from(carousel.querySelectorAll(ITEM_SELECTOR)).filter(
    (item) => item.dataset.associationCarouselClone !== "true",
  );
}

function resolveTargetCount(originalCount, minItems) {
  if (originalCount <= 0) return 0;

  const minimum = Math.max(
    1,
    Number.isFinite(minItems) ? Math.trunc(minItems) : DEFAULTS.minItems,
  );

  /**
   * Si hay 4 y el mínimo visual es 9, NO hacemos 9:
   *   1,2,3,4,1,2,3,4,1  -> repite 1 al cerrar.
   * Hacemos 12:
   *   1,2,3,4,1,2,3,4,1,2,3,4 -> cierra 4 -> 1.
   */
  return Math.ceil(Math.max(originalCount, minimum) / originalCount) * originalCount;
}

function ensureFullCycles(carousel, originalItems, targetCount) {
  const originalCount = originalItems.length;

  if (originalCount === 0) return 0;

  for (let index = originalCount; index < targetCount; index += 1) {
    const originalIndex = index % originalCount;
    const source = originalItems[originalIndex];
    const clone = source.cloneNode(true);

    clone.dataset.associationCarouselClone = "true";
    clone.dataset.associationCarouselOriginalIndex = String(originalIndex);
    clone.dataset.associationCarouselPosition = String(index);
    clone.setAttribute("aria-hidden", "true");
    clone.setAttribute("tabindex", "-1");

    clone.querySelectorAll("[id]").forEach((element) => {
      element.removeAttribute("id");
    });

    clone.querySelectorAll("[aria-describedby], [aria-labelledby]").forEach((element) => {
      element.removeAttribute("aria-describedby");
      element.removeAttribute("aria-labelledby");
    });

    clone.querySelectorAll("a, button, input, select, textarea, [tabindex]").forEach((element) => {
      element.setAttribute("tabindex", "-1");
    });

    clone.querySelectorAll("img").forEach((image) => {
      image.setAttribute("alt", "");
    });

    carousel.appendChild(clone);
  }

  return targetCount;
}

function ensureKeyframes(totalItems, transitionRatio) {
  const safeTotal = Math.max(1, Math.trunc(totalItems));
  const safeTransitionRatio = clampNumber(transitionRatio, 0.08, 0.45);
  const name = `associationCertificationsVertical_${safeTotal}_${String(safeTransitionRatio).replace(".", "_")}`;

  if (generatedKeyframes.has(name)) {
    return name;
  }

  const slot = 100 / safeTotal;
  const transition = slot * safeTransitionRatio;

  const p0 = 0;
  const p1 = round(transition);
  const p2 = round(slot);
  const p3 = round(slot + transition);
  const p4 = round(slot * 2);
  const p5 = round(slot * 2 + transition);
  const p6 = round(slot * 3);
  const p7 = round(slot * 3 + transition);

  const css = `
@keyframes ${name} {
  ${p0}% {
    transform: translate(-50%, 65%) scale(0.62);
    opacity: 0;
    visibility: hidden;
  }

  ${p1}%,
  ${p2}% {
    transform: translate(-50%, 65%) scale(0.72);
    opacity: 0.45;
    visibility: visible;
  }

  ${p3}%,
  ${p4}% {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
    visibility: visible;
  }

  ${p5}%,
  ${p6}% {
    transform: translate(-50%, -165%) scale(0.72);
    opacity: 0.45;
    visibility: visible;
  }

  ${p7}% {
    transform: translate(-50%, -165%) scale(0.62);
    opacity: 0;
    visibility: visible;
  }

  100% {
    transform: translate(-50%, -165%) scale(0.62);
    opacity: 0;
    visibility: hidden;
  }
}
`;

  getStyleElement().appendChild(document.createTextNode(css));
  generatedKeyframes.add(name);

  return name;
}

function getStyleElement() {
  if (styleElement && document.head.contains(styleElement)) {
    return styleElement;
  }

  styleElement = document.getElementById("association-certifications-carousel-keyframes");

  if (!styleElement) {
    styleElement = document.createElement("style");
    styleElement.id = "association-certifications-carousel-keyframes";
    document.head.appendChild(styleElement);
  }

  return styleElement;
}

function notifyLazyLoaders() {
  document.dispatchEvent(
    new CustomEvent("whis:load", {
      bubbles: true,
      detail: {
        source: "association-certifications-carousel",
      },
    }),
  );
}

function readNumber(value, fallback) {
  const number = Number(value);

  return Number.isFinite(number) && number > 0 ? number : fallback;
}

function clampNumber(value, min, max) {
  const number = Number(value);

  if (!Number.isFinite(number)) return min;

  return Math.min(max, Math.max(min, number));
}

function round(value) {
  return Number.parseFloat(value.toFixed(4));
}

function hasDOM() {
  return typeof window !== "undefined" && typeof document !== "undefined";
}

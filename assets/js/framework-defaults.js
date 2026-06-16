import initSplide from "./sections/splide";
import typeJumbotron from "./sections/jumbotron";
import initNavbar from "./sections/navbar";

import {
  lazyLoadImages,
  lazyLoadBackgrounds,
  preloadCriticalImages,
} from "./helpers/lazyload";

import initAjaxForms from "./helpers/ajax-form";

import {
  lazyLoadVideos,
  lazyLoadBackgroundVideos,
  lazyLoadInteractiveVideos,
} from "./helpers/lazyloadvideos";

import initStats from "./sections/stats";

const isDev =
  typeof process !== "undefined" &&
  process.env &&
  process.env.NODE_ENV === "development";

const noop = () => {};

export const WHIS_DEFAULT_OPTIONS = {
  debug: isDev,
  exposeApiInDev: true,

  modules: {
    navbar: true,
    jumbotron: true,
    ajaxForms: true,

    splide: true,
    splideMediaControl: true,

    criticalImages: true,
    lazyImages: true,
    lazyBackgrounds: true,

    lazyVideos: true,
    lazyBackgroundVideos: true,
    lazyInteractiveVideos: true,

    stats: true,

    refreshOnContentLoaded: true,
    beforePrint: true,
  },

  selectors: {
    splide: ".splide",

    criticalImages: ".hero-image, .logo, .critical-image",
    lazyImages: ".lazy-image",
    lazyBackgrounds: ".lazy-bg, .hero-section[data-background-src]",

    lazyVideos: ".lazy-video",
    lazyBackgroundVideos: ".video-background, .lazy-video-bg",
    lazyInteractiveVideos: ".video-player, .lazy-video-interactive",
  },

  splide: {
    options: {},
    pageOptions: {},
  },

  ajaxForms: {},

  images: {
    threshold: 0.01,
    rootMargin: "100px",
    fadeInDuration: 400,
    enableRetry: true,
    maxRetries: 3,
    enableAutoQuality: true,
    placeholderBlur: true,
  },

  videos: {
    threshold: 0.15,
    rootMargin: "150px",
    fadeInDuration: 500,
    autoplay: false,
    pauseOnExit: true,
    unloadOnExit: false,
    preloadMetadata: true,
    enableRetry: true,
    maxRetries: 3,
  },

  callbacks: {
    beforeInit: noop,
    afterInit: noop,

    onImageLoad: null,
    onImageError: null,
    onImageComplete: null,

    onVideoLoad: null,
    onVideoError: null,
    onVideoPlay: null,
    onVideoPause: null,
    onVideoComplete: null,
  },
};

let currentDefaults = cloneDefaults(WHIS_DEFAULT_OPTIONS);

/**
 * Permite modificar los defaults globales del framework.
 *
 * Ejemplo:
 * defineWhisDefaults({
 *   modules: {
 *     stats: false,
 *   },
 * });
 */
export function defineWhisDefaults(defaults = {}) {
  currentDefaults = deepMerge(currentDefaults, defaults);
  return currentDefaults;
}

/**
 * Devuelve una copia de los defaults actuales.
 */
export function getWhisDefaults() {
  return cloneDefaults(currentDefaults);
}

/**
 * Inicializa los JS default del framework.
 *
 * Esta es la función principal que el usuario puede modificar
 * desde su proyecto sin tocar el core del framework.
 */
export function initWhisDefaults(options = {}) {
  const config = deepMerge(getWhisDefaults(), options);

  const api = {
    config,

    navbar: null,
    jumbotron: null,
    ajaxForms: [],

    splide: null,

    lazyLoader: null,
    bgLazyLoader: null,

    videoLoader: null,
    bgVideoLoader: null,
    interactiveVideoLoader: null,

    stats: null,

    refresh: noop,
    destroy: noop,
  };

  const log = (...args) => {
    if (config.debug) console.log(...args);
  };

  const errorLog = (...args) => {
    if (config.debug) console.error(...args);
  };

  runCallback(config.callbacks.beforeInit, api);

  if (config.modules.navbar) {
    api.navbar = initNavbar();
  }

  if (config.modules.jumbotron) {
    api.jumbotron = typeJumbotron();
  }

  if (config.modules.ajaxForms) {
    api.ajaxForms = initAjaxForms(config.ajaxForms);
  }

  if (config.modules.splide) {
    api.splide = initSplide(resolveSplideOptions(config));
  }

  if (config.modules.criticalImages) {
    preloadCriticalImages(config.selectors.criticalImages);
  }

  if (config.modules.lazyImages) {
    api.lazyLoader = lazyLoadImages(config.selectors.lazyImages, {
      ...config.images,

      onLoad: (...args) => {
        log(`📸 Imagen cargada: ${args[1]}/${args[2]}`);
        runCallback(config.callbacks.onImageLoad, ...args);
      },

      onError: (...args) => {
        errorLog(`❌ Error cargando imagen: ${args[1]}`);
        runCallback(config.callbacks.onImageError, ...args);
      },

      onComplete: (...args) => {
        log(`✅ Todas las ${args[0]} imágenes cargadas`);
        runCallback(config.callbacks.onImageComplete, ...args);
      },
    });
  }

  if (config.modules.lazyBackgrounds) {
    api.bgLazyLoader = lazyLoadBackgrounds(config.selectors.lazyBackgrounds);
  }

  if (config.modules.lazyVideos) {
    api.videoLoader = lazyLoadVideos(config.selectors.lazyVideos, {
      ...config.videos,

      onLoad: (...args) => {
        log(`📹 Video cargado: ${args[1]}/${args[2]}`);
        runCallback(config.callbacks.onVideoLoad, ...args);
      },

      onError: (...args) => {
        errorLog(`❌ Error cargando video: ${args[1]}`, args[2]);
        runCallback(config.callbacks.onVideoError, ...args);
      },

      onPlay: (...args) => {
        log("▶️ Video reproduciéndose");
        runCallback(config.callbacks.onVideoPlay, ...args);
      },

      onPause: (...args) => {
        log("⏸️ Video pausado");
        runCallback(config.callbacks.onVideoPause, ...args);
      },

      onComplete: (...args) => {
        log(`✅ Todos los ${args[0]} videos cargados`);
        runCallback(config.callbacks.onVideoComplete, ...args);
      },
    });
  }

  if (config.modules.splideMediaControl) {
    bindSplideMediaControl(api.splide, config);
  }

  if (config.modules.lazyBackgroundVideos) {
    api.bgVideoLoader = lazyLoadBackgroundVideos(
      config.selectors.lazyBackgroundVideos,
    );
  }

  if (config.modules.lazyInteractiveVideos) {
    api.interactiveVideoLoader = lazyLoadInteractiveVideos(
      config.selectors.lazyInteractiveVideos,
    );
  }

  if (config.modules.stats) {
    api.stats = initStats();
  }

  api.refresh = () => {
    api.lazyLoader?.refresh?.();
    api.bgLazyLoader?.refresh?.();

    api.videoLoader?.refresh?.();
    api.bgVideoLoader?.refresh?.();
    api.interactiveVideoLoader?.refresh?.();

    if (config.modules.splideMediaControl) {
      bindSplideMediaControl(api.splide, config);
    }

    log("🔄 Defaults del framework refrescados");
  };

  api.destroy = () => {
    api.lazyLoader?.disconnect?.();
    api.bgLazyLoader?.disconnect?.();

    api.videoLoader?.disconnect?.();
    api.bgVideoLoader?.disconnect?.();
    api.interactiveVideoLoader?.disconnect?.();

    api.ajaxForms?.forEach((formApi) => formApi?.destroy?.());
  };

  if (config.modules.refreshOnContentLoaded) {
    document.addEventListener("contentLoaded", api.refresh);
  }

  if (config.modules.beforePrint) {
    window.addEventListener("beforeprint", () => {
      api.lazyLoader?.loadAll?.();
      api.bgLazyLoader?.loadAll?.();
      api.videoLoader?.pauseAll?.();
    });
  }

  if (config.exposeApiInDev && config.debug) {
    window.whisDefaults = api;
    log("🔧 API de defaults disponible en window.whisDefaults");
  }

  runCallback(config.callbacks.afterInit, api);

  return api;
}

/**
 * Inicializa automáticamente cuando el DOM ya está listo.
 */
export function bootstrapWhisDefaults(options = {}) {
  const start = () => initWhisDefaults(options);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
    return null;
  }

  return start();
}

export default initWhisDefaults;

function resolveSplideOptions(config) {
  const splideElement = document.querySelector(config.selectors.splide);
  const pageName = splideElement?.dataset?.splidePage || "";

  const pageOptions = pageName
    ? config.splide.pageOptions?.[pageName] || {}
    : {};

  return {
    ...config.splide.options,
    ...pageOptions,
  };
}

function bindSplideMediaControl(splideInstance, config = {}) {
  if (!splideInstance) return;

  const splideRoot = document.querySelector(
    config.selectors?.splide || ".splide",
  );

  if (!splideRoot) return;

  const getActiveSlide = () =>
    splideRoot.querySelector(".splide__slide.is-active");

  const pauseAutoplay = () => {
    splideInstance?.Components?.Autoplay?.pause?.();
  };

  const resumeAutoplay = () => {
    splideInstance?.Components?.Autoplay?.play?.();
  };

  const pauseFromElement = (element) => {
    const slide = element.closest(".splide__slide");

    if (!slide || slide !== getActiveSlide()) return;

    slide.classList.add("media-interacting");
    pauseAutoplay();
  };

  const resumeFromElement = (element) => {
    const slide = element.closest(".splide__slide");

    slide?.classList.remove("media-interacting");
    slide?.classList.remove("video-playing");

    resumeAutoplay();
  };

  splideRoot.querySelectorAll("video").forEach((video) => {
    if (video.dataset.splideBound === "true") return;

    video.dataset.splideBound = "true";

    video.addEventListener("play", () => {
      const slide = video.closest(".splide__slide");

      if (!slide || slide !== getActiveSlide()) return;

      slide.classList.add("video-playing", "media-interacting");
      pauseAutoplay();
    });

    video.addEventListener("pause", () => {
      resumeFromElement(video);
    });

    video.addEventListener("ended", () => {
      resumeFromElement(video);
    });
  });

  splideRoot
    .querySelectorAll("iframe, embed, object, canvas")
    .forEach((element) => {
      if (element.dataset.splideInteractiveBound === "true") return;

      element.dataset.splideInteractiveBound = "true";

      element.addEventListener("pointerdown", () => pauseFromElement(element));
      element.addEventListener("mousedown", () => pauseFromElement(element));
      element.addEventListener("touchstart", () => pauseFromElement(element), {
        passive: true,
      });

      element.addEventListener("mouseenter", () => pauseFromElement(element));
      element.addEventListener("focus", () => pauseFromElement(element));
      element.addEventListener("focusin", () => pauseFromElement(element));

      element.addEventListener("mouseleave", () => resumeFromElement(element));
      element.addEventListener("blur", () => resumeFromElement(element));
      element.addEventListener("focusout", () => resumeFromElement(element));
    });

  if (splideInstance.__whisMediaControlBound) return;

  splideInstance.__whisMediaControlBound = true;

  splideInstance.on("move", () => {
    splideRoot.querySelectorAll("video").forEach((video) => {
      const slide = video.closest(".splide__slide");

      if (slide !== getActiveSlide() && !video.paused && !video.ended) {
        video.pause();
      }
    });

    splideRoot
      .querySelectorAll(
        ".splide__slide.media-interacting, .splide__slide.video-playing",
      )
      .forEach((slide) => {
        slide.classList.remove("media-interacting", "video-playing");
      });
  });

  splideInstance.on("moved", () => {
    const activeSlide = getActiveSlide();

    if (!activeSlide) return;

    const activeVideo = activeSlide.querySelector("video");

    const interactiveMedia = activeSlide.querySelector(
      "iframe, embed, object, canvas",
    );

    const videoPlaying =
      activeVideo && !activeVideo.paused && !activeVideo.ended;

    if (!videoPlaying && !interactiveMedia) {
      resumeAutoplay();
    }
  });
}

function runCallback(callback, ...args) {
  if (typeof callback === "function") {
    callback(...args);
  }
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === "[object Object]";
}

function deepMerge(target = {}, source = {}) {
  const output = { ...target };

  Object.entries(source || {}).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      output[key] = [...value];
      return;
    }

    if (isPlainObject(value) && isPlainObject(output[key])) {
      output[key] = deepMerge(output[key], value);
      return;
    }

    output[key] = value;
  });

  return output;
}

function cloneDefaults(value) {
  if (Array.isArray(value)) {
    return value.map(cloneDefaults);
  }

  if (isPlainObject(value)) {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [key, cloneDefaults(item)]),
    );
  }

  return value;
}
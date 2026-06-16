import {
  lazyLoadImages,
  lazyLoadBackgrounds,
  preloadCriticalImages,
} from "./lazyload";

import initAjaxForms from "./ajax-form";

import {
  lazyLoadVideos,
  lazyLoadBackgroundVideos,
  lazyLoadInteractiveVideos,
} from "./lazyloadvideos";

const isDev =
  typeof process !== "undefined" &&
  process.env &&
  process.env.NODE_ENV === "development";

const noop = () => {};

export const WHIS_DEFAULT_OPTIONS = {
  debug: isDev,
  exposeApiInDev: true,
  autoInit: true,

  modules: {
    ajaxForms: true,

    criticalImages: true,
    lazyImages: true,
    lazyBackgrounds: true,

    lazyVideos: true,
    lazyBackgroundVideos: true,
    lazyInteractiveVideos: true,

    refreshOnContentLoaded: true,
    refreshOnWhisLoad: true,
    beforePrint: true,
  },

  selectors: {
    forms: 'form[data-ajax-form], form[data-form-handler="ajax"]',

    criticalImages: ".hero-image, .logo, .critical-image",
    lazyImages: ".lazy-image",
    lazyBackgrounds: ".lazy-bg, [data-background-src]",

    lazyVideos: ".lazy-video",
    lazyBackgroundVideos: ".video-background, .lazy-video-bg",
    lazyInteractiveVideos: ".video-player, .lazy-video-interactive",
  },

  ajaxForms: {
    selector: 'form[data-ajax-form], form[data-form-handler="ajax"]',
    csrf: false,
    csrfField: "_token",
    sendAs: "auto",
    method: "POST",
    resetOnSuccess: true,
    credentials: "same-origin",
  },

  images: {
    threshold: 0.01,
    rootMargin: "100px",
    fadeInDuration: 400,
    enableRetry: true,
    maxRetries: 3,
    retryDelay: 1000,
    enableAutoQuality: true,
    placeholderBlur: true,
    priority: "auto",
    debug: isDev,
  },

  backgrounds: {
    useHelper: true,
    threshold: 0.1,
    rootMargin: "100px",
    fadeInDuration: 400,
    enableRetry: true,
    maxRetries: 3,
    retryDelay: 1000,
    enableAutoQuality: true,
    placeholderBlur: true,
    priority: "custom",
    debug: isDev,
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
    retryDelay: 1500,
    priority: "auto",
  },

  events: {
    ready: "whis:ready",
    refresh: "whis:refresh",
    destroy: "whis:destroy",
    error: "whis:error",
  },

  callbacks: {
    beforeInit: noop,
    afterInit: noop,
    beforeRefresh: noop,
    afterRefresh: noop,
    beforeDestroy: noop,
    afterDestroy: noop,

    onImageLoad: null,
    onImageError: null,
    onImageComplete: null,

    onVideoLoad: null,
    onVideoError: null,
    onVideoPlay: null,
    onVideoPause: null,
    onVideoComplete: null,

    onError: null,
  },
};

let currentDefaults = cloneDefaults(WHIS_DEFAULT_OPTIONS);
let currentInstance = null;

/**
 * Modifica los defaults globales del framework.
 *
 * Ejemplo:
 *
 * defineWhisDefaults({
 *   ajaxForms: {
 *     csrf: true,
 *     sendAs: "urlencoded",
 *   },
 * });
 */
export function defineWhisDefaults(defaults = {}) {
  currentDefaults = deepMerge(currentDefaults, defaults);

  return getWhisDefaults();
}

/**
 * Devuelve una copia de los defaults actuales.
 */
export function getWhisDefaults(path = "") {
  const defaults = cloneDefaults(currentDefaults);

  if (!path) {
    return defaults;
  }

  return getByPath(defaults, path);
}

/**
 * Regresa los defaults a su estado original.
 */
export function resetWhisDefaults() {
  currentDefaults = cloneDefaults(WHIS_DEFAULT_OPTIONS);

  return getWhisDefaults();
}

/**
 * Crea una configuración final sin inicializar nada.
 * Sirve para debug o para integrar Whis en otro sistema.
 */
export function createWhisConfig(options = {}) {
  return deepMerge(getWhisDefaults(), options);
}

/**
 * Devuelve la instancia actual, si ya fue inicializada.
 */
export function getWhisInstance() {
  return currentInstance;
}

/**
 * Inicializa los defaults del framework.
 *
 * Solo incluye:
 * - Ajax Forms
 * - Lazy Images
 * - Lazy Backgrounds
 * - Lazy Videos
 * - Lazy Background Videos
 * - Lazy Interactive Videos
 */
export function initWhisDefaults(options = {}, runtimeOptions = {}) {
  if (!hasDOM()) {
    return null;
  }

  const replace = runtimeOptions.replace ?? false;

  if (currentInstance && !currentInstance.destroyed && !replace) {
    return currentInstance;
  }

  if (currentInstance && !currentInstance.destroyed && replace) {
    currentInstance.destroy();
  }

  const config = createWhisConfig(options);

  const api = {
    config,
    destroyed: false,

    ajaxForms: [],

    lazyLoader: null,
    bgLazyLoader: null,

    videoLoader: null,
    bgVideoLoader: null,
    interactiveVideoLoader: null,

    refresh: noop,
    destroy: noop,
    reinit: noop,

    initAjaxForms: noop,
    initImages: noop,
    initBackgrounds: noop,
    initVideos: noop,

    getConfig: () => cloneDefaults(config),
  };

  const log = (...args) => {
    if (config.debug) console.log(...args);
  };

  const errorLog = (...args) => {
    if (config.debug) console.error(...args);
  };

  const safeRun = (label, callback) => {
    try {
      return callback();
    } catch (error) {
      errorLog(`❌ Error en ${label}:`, error);

      runCallback(config.callbacks.onError, error, {
        label,
        api,
        config,
      });

      emit(config.events.error, {
        label,
        error,
        api,
      });

      return null;
    }
  };

  currentInstance = api;

  runCallback(config.callbacks.beforeInit, api);

  api.initAjaxForms = (ajaxOptions = {}) => {
    if (!config.modules.ajaxForms) return [];

    api.ajaxForms = safeRun("ajaxForms", () =>
      initAjaxForms({
        ...config.ajaxForms,
        ...ajaxOptions,
        selector:
          ajaxOptions.selector ||
          config.ajaxForms.selector ||
          config.selectors.forms,
      }),
    ) || [];

    return api.ajaxForms;
  };

  api.initImages = (imageOptions = {}) => {
    if (config.modules.criticalImages) {
      safeRun("criticalImages", () => {
        preloadCriticalImages(
          imageOptions.criticalSelector || config.selectors.criticalImages,
        );
      });
    }

    if (!config.modules.lazyImages) return null;

    api.lazyLoader = safeRun("lazyImages", () =>
      lazyLoadImages(
        imageOptions.selector || config.selectors.lazyImages,
        {
          ...config.images,
          ...imageOptions,

          onLoad: (...args) => {
            log(`📸 Imagen cargada: ${args[1]}/${args[2]}`);
            runCallback(config.callbacks.onImageLoad, ...args);
            runCallback(imageOptions.onLoad, ...args);
          },

          onError: (...args) => {
            errorLog(`❌ Error cargando imagen: ${args[1]}`);
            runCallback(config.callbacks.onImageError, ...args);
            runCallback(imageOptions.onError, ...args);
          },

          onComplete: (...args) => {
            log(`✅ Todas las ${args[0]} imágenes cargadas`);
            runCallback(config.callbacks.onImageComplete, ...args);
            runCallback(imageOptions.onComplete, ...args);
          },
        },
      ),
    );

    return api.lazyLoader;
  };

  api.initBackgrounds = (backgroundOptions = {}) => {
    if (!config.modules.lazyBackgrounds) return null;

    const selector =
      backgroundOptions.selector || config.selectors.lazyBackgrounds;

    if (backgroundOptions.useHelper ?? config.backgrounds.useHelper) {
      api.bgLazyLoader = safeRun("lazyBackgrounds", () =>
        lazyLoadBackgrounds(selector),
      );

      return api.bgLazyLoader;
    }

    api.bgLazyLoader = safeRun("lazyBackgrounds", () =>
      lazyLoadImages(selector, {
        ...config.backgrounds,
        ...backgroundOptions,
      }),
    );

    return api.bgLazyLoader;
  };

  api.initVideos = (videoOptions = {}) => {
    if (config.modules.lazyVideos) {
      api.videoLoader = safeRun("lazyVideos", () =>
        lazyLoadVideos(
          videoOptions.selector || config.selectors.lazyVideos,
          {
            ...config.videos,
            ...videoOptions,

            onLoad: (...args) => {
              log(`📹 Video cargado: ${args[1]}/${args[2]}`);
              runCallback(config.callbacks.onVideoLoad, ...args);
              runCallback(videoOptions.onLoad, ...args);
            },

            onError: (...args) => {
              errorLog(`❌ Error cargando video: ${args[1]}`, args[2]);
              runCallback(config.callbacks.onVideoError, ...args);
              runCallback(videoOptions.onError, ...args);
            },

            onPlay: (...args) => {
              log("▶️ Video reproduciéndose");
              runCallback(config.callbacks.onVideoPlay, ...args);
              runCallback(videoOptions.onPlay, ...args);
            },

            onPause: (...args) => {
              log("⏸️ Video pausado");
              runCallback(config.callbacks.onVideoPause, ...args);
              runCallback(videoOptions.onPause, ...args);
            },

            onComplete: (...args) => {
              log(`✅ Todos los ${args[0]} videos cargados`);
              runCallback(config.callbacks.onVideoComplete, ...args);
              runCallback(videoOptions.onComplete, ...args);
            },
          },
        ),
      );
    }

    if (config.modules.lazyBackgroundVideos) {
      api.bgVideoLoader = safeRun("lazyBackgroundVideos", () =>
        lazyLoadBackgroundVideos(config.selectors.lazyBackgroundVideos),
      );
    }

    if (config.modules.lazyInteractiveVideos) {
      api.interactiveVideoLoader = safeRun("lazyInteractiveVideos", () =>
        lazyLoadInteractiveVideos(config.selectors.lazyInteractiveVideos),
      );
    }

    return {
      videoLoader: api.videoLoader,
      bgVideoLoader: api.bgVideoLoader,
      interactiveVideoLoader: api.interactiveVideoLoader,
    };
  };

  api.refresh = () => {
    if (api.destroyed) return api;

    runCallback(config.callbacks.beforeRefresh, api);

    api.lazyLoader?.refresh?.();
    api.bgLazyLoader?.refresh?.();

    api.videoLoader?.refresh?.();
    api.bgVideoLoader?.refresh?.();
    api.interactiveVideoLoader?.refresh?.();

    emit(config.events.refresh, { api });

    runCallback(config.callbacks.afterRefresh, api);

    log("🔄 Defaults del framework refrescados");

    return api;
  };

  api.destroy = () => {
    if (api.destroyed) return api;

    runCallback(config.callbacks.beforeDestroy, api);

    api.lazyLoader?.disconnect?.();
    api.bgLazyLoader?.disconnect?.();

    api.videoLoader?.disconnect?.();
    api.bgVideoLoader?.disconnect?.();
    api.interactiveVideoLoader?.disconnect?.();

    api.ajaxForms?.forEach((formApi) => formApi?.destroy?.());

    api.destroyed = true;

    if (currentInstance === api) {
      currentInstance = null;
    }

    emit(config.events.destroy, { api });

    runCallback(config.callbacks.afterDestroy, api);

    return api;
  };

  api.reinit = (nextOptions = {}) => {
    return initWhisDefaults(
      deepMerge(config, nextOptions),
      { replace: true },
    );
  };

  api.initAjaxForms();
  api.initImages();
  api.initBackgrounds();
  api.initVideos();

  if (config.modules.refreshOnContentLoaded) {
    document.addEventListener("contentLoaded", api.refresh);
  }

  if (config.modules.refreshOnWhisLoad) {
    document.addEventListener("whis:load", api.refresh);
  }

  if (config.modules.beforePrint) {
    window.addEventListener("beforeprint", () => {
      api.lazyLoader?.loadAll?.();
      api.bgLazyLoader?.loadAll?.();

      api.videoLoader?.pauseAll?.();
      api.bgVideoLoader?.pauseAll?.();
      api.interactiveVideoLoader?.pauseAll?.();
    });
  }

  if (config.exposeApiInDev && config.debug) {
    window.whisDefaults = api;
    log("🔧 API de defaults disponible en window.whisDefaults");
  }

  emit(config.events.ready, { api });

  runCallback(config.callbacks.afterInit, api);

  return api;
}

/**
 * Inicializa automáticamente cuando el DOM está listo.
 */
export function bootstrapWhisDefaults(options = {}) {
  if (!hasDOM()) return null;

  const start = () => initWhisDefaults(options);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });

    return null;
  }

  return start();
}

/**
 * Refresca la instancia activa.
 */
export function refreshWhisDefaults() {
  return currentInstance?.refresh?.() || null;
}

/**
 * Destruye la instancia activa.
 */
export function destroyWhisDefaults() {
  return currentInstance?.destroy?.() || null;
}

export default initWhisDefaults;

function hasDOM() {
  return typeof window !== "undefined" && typeof document !== "undefined";
}

function runCallback(callback, ...args) {
  if (typeof callback === "function") {
    return callback(...args);
  }

  return undefined;
}

function emit(eventName, detail = {}) {
  if (!hasDOM() || !eventName) return;

  document.dispatchEvent(
    new CustomEvent(eventName, {
      bubbles: true,
      detail,
    }),
  );
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

function getByPath(object, path) {
  return String(path)
    .split(".")
    .filter(Boolean)
    .reduce((current, key) => current?.[key], object);
}
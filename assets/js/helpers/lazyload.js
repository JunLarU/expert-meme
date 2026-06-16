/**
 * Lazy Loading Avanzado con todas las optimizaciones
 * @param {string} selector - Selector CSS para las imágenes
 * @param {Object} options - Opciones de configuración
 */
export function lazyLoadImages(selector = ".lazy-image", options = {}) {
  const config = {
    threshold: 0.01,
    rootMargin: "50px",
    enableAutoQuality: true,
    
    enableRetry: true,
    maxRetries: 3,
    retryDelay: 1000,
    fadeInDuration: 300,
    placeholderBlur: true,
    loadDelay: 0,
    onLoad: null,
    onError: null,
    onComplete: null,
    priority: "auto",
    debug: false,
    ...options,
  };

  const debugLog = (...args) => {
    if (config.debug) console.log(...args);
  };

  const debugError = (...args) => {
    if (config.debug) console.error(...args);
  };

  const supportsNativeLazyLoading = "loading" in HTMLImageElement.prototype;

  const connection =
    navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const isSlowConnection = connection
    ? connection.effectiveType === "slow-2g" ||
      connection.effectiveType === "2g" ||
      connection.saveData
    : false;

  if (config.enableAutoQuality && isSlowConnection) {
    config.rootMargin = "0px";
    config.threshold = 0.1;
  }

  const images = document.querySelectorAll(selector);

  if (!images.length) {
    if (config.debug) {
      console.warn(`⚠️ No se encontraron elementos con el selector: ${selector}`);
    }

    return {
      observer: null,
      refresh: () => {},
      loadAll: () => {},
      disconnect: () => {},
    };
  }

  let loadedCount = 0;
  const totalImages = images.length;

  injectStyles(config);

  const loadImage = (element, retryCount = 0) => {
    return new Promise((resolve, reject) => {
      const isBackgroundImage = element.dataset.backgroundSrc !== undefined;
      const imgSrc = element.dataset.src || element.dataset.backgroundSrc;
      const imgSrcset = element.dataset.srcset;

      if (!imgSrc) {
        reject(new Error("No data-src or data-background-src attribute found"));
        return;
      }

      debugLog(`🔄 Cargando ${isBackgroundImage ? "background" : "imagen"}: ${imgSrc}`);

      element.classList.add("lazy-loading");

      if (isBackgroundImage) {
        const tempImg = new Image();
        tempImg.onload = () => {
          element.style.backgroundImage = `url('${imgSrc}')`;
          handleLoadSuccess(element);
          debugLog(`✅ Background cargado: ${imgSrc}`);
          resolve(element);
        };
        tempImg.onerror = (e) => {
          debugError(`❌ Error cargando background: ${imgSrc}`, e);
          handleLoadError(element, imgSrc, retryCount, reject);
        };
        tempImg.src = imgSrc;
      } else {
        const img = element;

        const onLoad = async () => {
          try {
            if (img.decode) {
              await img.decode();
            }
          } catch (e) {}

          handleLoadSuccess(img);
          resolve(img);
        };

        const onError = () => handleLoadError(img, imgSrc, retryCount, reject);

        img.addEventListener("load", onLoad, { once: true });
        img.addEventListener("error", onError, { once: true });

        if (imgSrcset) {
          img.srcset = imgSrcset;
        }
        img.src = imgSrc;
      }
    });
  };

  const handleLoadSuccess = (element) => {
    element.classList.remove("lazy-loading");
    element.classList.add("lazy-loaded");

    delete element.dataset.src;
    delete element.dataset.srcset;
    delete element.dataset.backgroundSrc;

    loadedCount++;

    if (typeof config.onLoad === "function") {
      config.onLoad(element, loadedCount, totalImages);
    }

    if (loadedCount === totalImages && typeof config.onComplete === "function") {
      config.onComplete(totalImages);
    }
  };

  const handleLoadError = (element, imgSrc, retryCount, reject) => {
    debugError(`❌ Error al cargar: ${imgSrc}`);

    element.classList.remove("lazy-loading");
    element.classList.add("lazy-error");

    if (config.enableRetry && retryCount < config.maxRetries) {
      debugLog(`🔄 Reintentando (${retryCount + 1}/${config.maxRetries})...`);
      setTimeout(() => {
        element.classList.remove("lazy-error");
        loadImage(element, retryCount + 1);
      }, config.retryDelay * (retryCount + 1));
    } else {
      if (typeof config.onError === "function") {
        config.onError(element, imgSrc);
      }
      reject(new Error(`Failed to load after ${config.maxRetries} retries`));
    }
  };

  const processImage = (img, observer) => {
    const delay = img.dataset.loadDelay || config.loadDelay;

    setTimeout(() => {
      loadImage(img)
        .then(() => {
          observer.unobserve(img);
        })
        .catch((err) => {
          debugError("❌ Error definitivo:", err);
          observer.unobserve(img);
        });
    }, delay);
  };

  if (supportsNativeLazyLoading && config.priority === "auto") {
    let hasBackgrounds = false;

    images.forEach((img) => {
      const priority = img.dataset.priority || config.priority;

      if (img.dataset.src && img.tagName === "IMG" && !img.dataset.backgroundSrc) {
        if (priority === "high") {
          img.loading = "eager";
          img.fetchPriority = "high";
        } else {
          img.loading = "lazy";
        }

        img.src = img.dataset.src;
        if (img.dataset.srcset) {
          img.srcset = img.dataset.srcset;
        }
        handleLoadSuccess(img);
      } else {
        hasBackgrounds = true;
      }
    });

    if (!hasBackgrounds) {
      return {
        observer: null,
        refresh: () => {},
        loadAll: () => {},
        disconnect: () => {},
      };
    }
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          processImage(entry.target, obs);
        }
      });
    },
    {
      threshold: config.threshold,
      rootMargin: config.rootMargin,
    }
  );

  const imagesInViewport = [];
  const imagesOutOfViewport = [];

  images.forEach((img) => {
    img.classList.add("lazy-image-initial");

    const rect = img.getBoundingClientRect();
    const isInViewport =
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth);

    if (isInViewport) {
      imagesInViewport.push(img);
    } else {
      imagesOutOfViewport.push(img);
    }
  });

  debugLog(`📊 Lazy Loading Stats:
    - Total: ${totalImages}
    - En viewport: ${imagesInViewport.length}
    - Fuera de viewport: ${imagesOutOfViewport.length}`);

  imagesInViewport.forEach((img) => {
    const priority = img.dataset.priority || config.priority;
    const delay = priority === "high" ? 0 : priority === "low" ? 300 : 100;

    setTimeout(() => {
      loadImage(img).catch((err) => debugError("❌ Error cargando inicial:", err));
    }, delay);
  });

  imagesOutOfViewport.forEach((img) => {
    observer.observe(img);
  });

  return {
    observer,
    refresh: () => {
      const newImages = document.querySelectorAll(
        `${selector}[data-src], ${selector}[data-background-src]`
      );

      newImages.forEach((img) => {
        if (!img.classList.contains("lazy-loaded")) {
          observer.observe(img);
        }
      });

      debugLog(`🔄 Refrescado: ${newImages.length} nuevas imágenes`);
    },
    loadAll: () => {
      imagesOutOfViewport.forEach((img) => {
        if (!img.classList.contains("lazy-loaded")) {
          loadImage(img).catch((err) => debugError(err));
        }
      });
    },
    disconnect: () => {
      observer.disconnect();
    },
  };
}

function injectStyles(config) {
  const styleId = "lazy-load-styles";

  if (document.getElementById(styleId)) return;

  const style = document.createElement("style");
  style.id = styleId;
  style.textContent = `
    .lazy-image-initial {
      opacity: 0;
      transition: opacity ${config.fadeInDuration}ms ease-in-out;
    }
    
    .lazy-loading {
      opacity: 0.5;
      ${config.placeholderBlur ? "filter: blur(5px);" : ""}
    }
    
    .lazy-loaded {
      opacity: 1;
      filter: none;
      animation: lazyFadeIn ${config.fadeInDuration}ms ease-in-out;
    }
    
    .lazy-error {
      opacity: 0.3;
      border: 2px dashed #ff0000;
    }
    
    @keyframes lazyFadeIn {
      from {
        opacity: 0;
        ${config.placeholderBlur ? "filter: blur(5px);" : ""}
      }
      to {
        opacity: 1;
        filter: none;
      }
    }
    
    [data-background-src] {
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }
  `;

  document.head.appendChild(style);
}

export function lazyLoadBackgrounds(selector = ".lazy-bg") {
  return lazyLoadImages(selector, {
    threshold: 0.1,
    rootMargin: "100px",
    priority: "custom",
  });
}

export function preloadCriticalImages(selector = ".critical-image") {
  const images = document.querySelectorAll(selector);

  images.forEach((img) => {
    if (!img.dataset.src) return;

    const existingPreload = document.querySelector(
      `link[rel="preload"][as="image"][href="${img.dataset.src}"]`
    );

    if (existingPreload) return;

    const link = document.createElement("link");
    link.rel = "preload";
    link.as = "image";
    link.href = img.dataset.src;

    if ((img.dataset.priority || "").toLowerCase() === "high") {
      link.fetchPriority = "high";
    }

    document.head.appendChild(link);
  });
}
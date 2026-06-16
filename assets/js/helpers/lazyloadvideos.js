/**
 * Lazy Loading Avanzado para Videos
 * @param {string} selector - Selector CSS para los videos
 * @param {Object} options - Opciones de configuración
 */
export function lazyLoadVideos(selector = '.lazy-video', options = {}) {
  const config = {
    threshold: 0.01,
    rootMargin: '100px',
    enableAutoQuality: true,
    enableRetry: true,
    maxRetries: 3,
    retryDelay: 1500,
    fadeInDuration: 400,
    loadDelay: 0,
    autoplay: false, // Autoplay cuando entra en viewport
    pauseOnExit: true, // Pausar cuando sale del viewport
    unloadOnExit: false, // Descargar video cuando sale (ahorra memoria)
    preloadMetadata: true, // Precargar metadata (duración, dimensiones)
    onLoad: null,
    onError: null,
    onComplete: null,
    onPlay: null,
    onPause: null,
    priority: 'auto',
    ...options
  };

  // Detectar calidad de conexión
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const isSlowConnection = connection ? 
    (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g' || connection.saveData) : 
    false;

  // Ajustar configuración según conexión
  if (config.enableAutoQuality && isSlowConnection) {
    config.rootMargin = '0px';
    config.threshold = 0.15;
    config.preloadMetadata = false;
    config.autoplay = false; // Nunca autoplay en conexiones lentas
  }

  const videos = document.querySelectorAll(selector);
  
  if (!videos.length) {
    console.warn(`⚠️ No se encontraron videos con el selector: ${selector}`);
    return {
      observer: null,
      refresh: () => {},
      loadAll: () => {},
      playAll: () => {},
      pauseAll: () => {},
      disconnect: () => {}
    };
  }

  let loadedCount = 0;
  const totalVideos = videos.length;
  const loadedVideos = new Set();

  // Inyectar estilos
  injectVideoStyles(config);

  /**
   * Carga un video con manejo de errores y reintentos
   */
  const loadVideo = (videoElement, retryCount = 0) => {
    return new Promise((resolve, reject) => {
      // Evitar cargar el mismo video múltiples veces
      if (loadedVideos.has(videoElement)) {
        resolve(videoElement);
        return;
      }

      // Obtener fuentes del video
      const videoSrc = videoElement.dataset.src;
      const posterSrc = videoElement.dataset.poster;
      const sources = videoElement.querySelectorAll('source[data-src]');
      
      if (!videoSrc && sources.length === 0) {
        reject(new Error('No data-src attribute found'));
        return;
      }

      // Marcar como cargando
      videoElement.classList.add('lazy-loading');

      // Configurar poster si existe
      if (posterSrc) {
        videoElement.poster = posterSrc;
        delete videoElement.dataset.poster;
      }

      // Configurar preload
      if (config.preloadMetadata) {
        videoElement.preload = 'metadata';
      } else {
        videoElement.preload = 'none';
      }

      // Manejar éxito de carga
      const onLoadedData = () => {
        handleLoadSuccess(videoElement);
        loadedVideos.add(videoElement);
        resolve(videoElement);
      };

      // Manejar error de carga
      const onError = (e) => {
        handleLoadError(videoElement, videoSrc || 'multiple sources', retryCount, reject, e);
      };

      // Agregar event listeners
      videoElement.addEventListener('loadeddata', onLoadedData, { once: true });
      videoElement.addEventListener('error', onError, { once: true });

      // Cargar fuentes
      if (videoSrc) {
        // Video con src directo
        videoElement.src = videoSrc;
        delete videoElement.dataset.src;
      } else if (sources.length > 0) {
        // Video con múltiples sources
        sources.forEach(source => {
          if (source.dataset.src) {
            source.src = source.dataset.src;
            delete source.dataset.src;
          }
        });
      }

      // Iniciar carga
      videoElement.load();
    });
  };

  /**
   * Maneja la carga exitosa de un video
   */
  const handleLoadSuccess = (videoElement) => {
    videoElement.classList.remove('lazy-loading');
    videoElement.classList.add('lazy-loaded');

    loadedCount++;

    // Log de éxito
    console.log(`📹 Video cargado: ${loadedCount}/${totalVideos}`);

    // Callback personalizado
    if (typeof config.onLoad === 'function') {
      config.onLoad(videoElement, loadedCount, totalVideos);
    }

    // Verificar si todos los videos cargaron
    if (loadedCount === totalVideos && typeof config.onComplete === 'function') {
      config.onComplete(totalVideos);
    }
  };

  /**
   * Maneja errores de carga con sistema de reintentos
   */
  const handleLoadError = (videoElement, videoSrc, retryCount, reject, errorEvent) => {
    console.error(`❌ Error al cargar video: ${videoSrc}`, errorEvent);
    
    videoElement.classList.remove('lazy-loading');
    videoElement.classList.add('lazy-error');

    // Sistema de reintentos
    if (config.enableRetry && retryCount < config.maxRetries) {
      console.log(`🔄 Reintentando carga de video (${retryCount + 1}/${config.maxRetries})...`);
      setTimeout(() => {
        videoElement.classList.remove('lazy-error');
        loadVideo(videoElement, retryCount + 1);
      }, config.retryDelay * (retryCount + 1));
    } else {
      // Callback de error
      if (typeof config.onError === 'function') {
        config.onError(videoElement, videoSrc, errorEvent);
      }
      reject(new Error(`Failed to load video after ${config.maxRetries} retries`));
    }
  };

  /**
   * Procesa un video cuando entra/sale del viewport
   */
  const processVideo = (video, isIntersecting, observer) => {
    if (isIntersecting) {
      // Video entra en viewport
      const delay = video.dataset.loadDelay || config.loadDelay;
      
      setTimeout(() => {
        loadVideo(video)
          .then((loadedVideo) => {
            // Autoplay si está configurado
            if (config.autoplay || video.dataset.autoplay !== undefined) {
              playVideo(loadedVideo);
            }
          })
          .catch(err => {
            console.error('❌ Error definitivo cargando video:', err);
          });
      }, delay);
    } else {
      // Video sale del viewport
      if (config.pauseOnExit && !video.paused) {
        video.pause();
        
        if (typeof config.onPause === 'function') {
          config.onPause(video);
        }
      }

      if (config.unloadOnExit && loadedVideos.has(video)) {
        unloadVideo(video);
      }
    }
  };

  /**
   * Reproduce un video con manejo de errores
   */
  const playVideo = async (video) => {
    try {
      // Los navegadores requieren que los videos con autoplay estén silenciados
      if (video.autoplay || video.dataset.autoplay !== undefined) {
        video.muted = true;
      }

      await video.play();
      
      if (typeof config.onPlay === 'function') {
        config.onPlay(video);
      }
      
      console.log('▶️ Video reproduciéndose:', video.src || video.currentSrc);
    } catch (error) {
      console.warn('⚠️ No se pudo reproducir el video automáticamente:', error.message);
      
      // Autoplay falló (común en móviles sin interacción de usuario)
      // Mostrar controls para que el usuario pueda reproducir manualmente
      if (error.name === 'NotAllowedError') {
        video.controls = true;
      }
    }
  };

  /**
   * Descarga un video para liberar memoria
   */
  const unloadVideo = (video) => {
    video.pause();
    video.removeAttribute('src');
    video.load(); // Esto limpia el buffer
    loadedVideos.delete(video);
    video.classList.remove('lazy-loaded');
    video.classList.add('lazy-unloaded');
    
    console.log('🗑️ Video descargado de memoria');
  };

  // Crear IntersectionObserver
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      processVideo(entry.target, entry.isIntersecting, observer);
    });
  }, {
    threshold: config.threshold,
    rootMargin: config.rootMargin
  });

  // Separar videos en viewport y fuera de viewport
  const videosInViewport = [];
  const videosOutOfViewport = [];

  videos.forEach(video => {
    // Añadir clase inicial
    video.classList.add('lazy-video-initial');
    
    // Verificar si está en viewport
    const rect = video.getBoundingClientRect();
    const isInViewport = (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );

    if (isInViewport) {
      videosInViewport.push(video);
    } else {
      videosOutOfViewport.push(video);
    }

    // Agregar event listeners para callbacks
    video.addEventListener('play', () => {
      if (typeof config.onPlay === 'function') {
        config.onPlay(video);
      }
    });

    video.addEventListener('pause', () => {
      if (typeof config.onPause === 'function') {
        config.onPause(video);
      }
    });
  });

  // Cargar inmediatamente los videos que ya están en viewport
  videosInViewport.forEach(video => {
    const priority = video.dataset.priority || config.priority;
    const delay = priority === 'high' ? 0 : (priority === 'low' ? 500 : 200);
    
    setTimeout(() => {
      loadVideo(video)
        .then((loadedVideo) => {
          if (config.autoplay || video.dataset.autoplay !== undefined) {
            playVideo(loadedVideo);
          }
        })
        .catch(err => console.error('❌ Error cargando video inicial:', err));
    }, delay);
  });

  // Observar el resto
  videosOutOfViewport.forEach(video => {
    observer.observe(video);
  });

  // Pausar todos los videos cuando la página se oculta
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      videos.forEach(video => {
        if (!video.paused) {
          video.pause();
          video.dataset.wasPlaying = 'true';
        }
      });
    } else {
      videos.forEach(video => {
        if (video.dataset.wasPlaying === 'true' && config.autoplay) {
          playVideo(video);
          delete video.dataset.wasPlaying;
        }
      });
    }
  });

  // Retornar API para control externo
  return {
    observer,
    refresh: () => {
      const newVideos = document.querySelectorAll(`${selector}[data-src], ${selector}:has(source[data-src])`);
      newVideos.forEach(video => {
        if (!loadedVideos.has(video)) {
          observer.observe(video);
        }
      });
    },
    loadAll: () => {
      videosOutOfViewport.forEach(video => {
        loadVideo(video).catch(err => console.error(err));
      });
    },
    playAll: () => {
      videos.forEach(video => {
        if (loadedVideos.has(video)) {
          playVideo(video);
        }
      });
    },
    pauseAll: () => {
      videos.forEach(video => {
        if (!video.paused) {
          video.pause();
        }
      });
    },
    unloadAll: () => {
      videos.forEach(video => {
        if (loadedVideos.has(video)) {
          unloadVideo(video);
        }
      });
    },
    disconnect: () => {
      observer.disconnect();
    },
    getLoadedVideos: () => Array.from(loadedVideos)
  };
}

/**
 * Inyecta estilos CSS necesarios para videos lazy
 */
function injectVideoStyles(config) {
  const styleId = 'lazy-video-styles';
  
  if (document.getElementById(styleId)) return;

  const style = document.createElement('style');
  style.id = styleId;
  style.textContent = `
    .lazy-video-initial {
      opacity: 0;
      transition: opacity ${config.fadeInDuration}ms ease-in-out;
      background: rgba(0, 0, 0, 0.1);
    }
    
    .lazy-loading {
      opacity: 0.5;
      filter: blur(2px);
      position: relative;
    }
    
    .lazy-loading::after {
      content: '⏳';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 3rem;
      z-index: 10;
      animation: pulse 1.5s ease-in-out infinite;
    }
    
    .lazy-loaded {
      opacity: 1;
      filter: none;
      animation: videoFadeIn ${config.fadeInDuration}ms ease-in-out;
    }
    
    .lazy-error {
      opacity: 0.4;
      border: 3px dashed #ff0000;
      position: relative;
    }
    
    .lazy-error::after {
      content: '⚠️ Error al cargar video';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(255, 0, 0, 0.9);
      color: white;
      padding: 1rem 2rem;
      border-radius: 0.5rem;
      font-size: 1.4rem;
      white-space: nowrap;
    }
    
    .lazy-unloaded {
      opacity: 0.3;
    }
    
    @keyframes videoFadeIn {
      from {
        opacity: 0;
        filter: blur(2px);
      }
      to {
        opacity: 1;
        filter: none;
      }
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 0.4; transform: translate(-50%, -50%) scale(0.9); }
      50% { opacity: 1; transform: translate(-50%, -50%) scale(1.1); }
    }
    
    /* Estilos para videos de fondo */
    .video-background {
      position: absolute;
      top: 50%;
      left: 50%;
      min-width: 100%;
      min-height: 100%;
      width: auto;
      height: auto;
      transform: translate(-50%, -50%);
      object-fit: cover;
      z-index: -1;
    }
  `;
  
  document.head.appendChild(style);
}

/**
 * Helper para videos de fondo
 */
export function lazyLoadBackgroundVideos(selector = '.lazy-video-bg') {
  return lazyLoadVideos(selector, {
    threshold: 0.1,
    rootMargin: '200px',
    autoplay: true,
    pauseOnExit: true,
    unloadOnExit: true, // Importante para videos de fondo (ahorra mucha memoria)
    preloadMetadata: false
  });
}

/**
 * Helper para videos interactivos (con controles)
 */
export function lazyLoadInteractiveVideos(selector = '.lazy-video-interactive') {
  return lazyLoadVideos(selector, {
    threshold: 0.25,
    rootMargin: '100px',
    autoplay: false, // No autoplay para videos interactivos
    pauseOnExit: false, // El usuario controla la reproducción
    unloadOnExit: false,
    preloadMetadata: true
  });
}

/**
 * Precargar videos críticos
 */
export function preloadCriticalVideos(selector = '.critical-video') {
  const videos = document.querySelectorAll(selector);
  videos.forEach(video => {
    const videoSrc = video.dataset.src;
    const sources = video.querySelectorAll('source[data-src]');
    
    if (videoSrc) {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.as = 'video';
      link.href = videoSrc;
      document.head.appendChild(link);
    } else if (sources.length > 0) {
      // Precargar la primera fuente (generalmente la de mayor calidad)
      const firstSource = sources[0];
      if (firstSource.dataset.src) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'video';
        link.href = firstSource.dataset.src;
        document.head.appendChild(link);
      }
    }
  });
}
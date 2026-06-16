import "../sass/app.scss";
import { bootstrapWhisDefaults } from "./helpers/framework-defaults";

export {
  WHIS_DEFAULT_OPTIONS,
  defineWhisDefaults,
  getWhisDefaults,
  resetWhisDefaults,
  createWhisConfig,
  initWhisDefaults,
  bootstrapWhisDefaults,
  getWhisInstance,
  refreshWhisDefaults,
  destroyWhisDefaults,
} from "./helpers/framework-defaults";

/**
 * Inicialización default del framework.
 *
 * El usuario puede usar esto tal cual, o borrar esta línea
 * e inicializar manualmente con initWhisDefaults({...}).
 */
bootstrapWhisDefaults();

/**
Ejemplo sencillo:
import "../sass/app.scss";
import { initWhisDefaults } from "./framework-defaults";

initWhisDefaults({
  ajaxForms: {
    csrf: true,
    sendAs: "urlencoded",
  },

  images: {
    rootMargin: "200px",
    maxRetries: 5,
  },

  videos: {
    autoplay: false,
    pauseOnExit: true,
  },
});
Ejempllo avanzado:
import "../sass/app.scss";
import {
  defineWhisDefaults,
  initWhisDefaults,
} from "./framework-defaults";

defineWhisDefaults({
  ajaxForms: {
    csrf: true,
    csrfField: "_token",
    sendAs: "auto",
  },

  selectors: {
    lazyImages: ".lazy-image, [data-lazy-image]",
    lazyBackgrounds: ".lazy-bg, [data-background-src]",
    lazyVideos: ".lazy-video, [data-lazy-video]",
  },
});

const whis = initWhisDefaults({
  callbacks: {
    afterInit(api) {
      console.log("Whis listo:", api);
    },

    onImageComplete(total) {
      console.log(`Imágenes cargadas: ${total}`);
    },

    onVideoComplete(total) {
      console.log(`Videos cargados: ${total}`);
    },
  },
});

// Cuando el usuario inyecte HTML dinámico:
whis.refresh();

// Si necesita reiniciar todo:
whis.reinit({
  images: {
    rootMargin: "300px",
  },
});

*/
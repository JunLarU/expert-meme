import "../sass/app.scss";
import { bootstrapWhisDefaults } from "./helpers/framework-defaults";
import initNavbar from "./sections/navbar";
import initSplide from "./sections/splide";

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
document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  const splideElement = document.querySelector(".splide");

  let splideOptions = {};

  if (splideElement?.dataset.splidePage === "eventos") {
    splideOptions = {
      type: "loop",
      rewind: true,
      autoplay: false,
      interval: 5000,
      speed: 1000,
      arrows: true,
      pagination: true,
    };
  }

  const splide = initSplide(splideOptions);
});

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

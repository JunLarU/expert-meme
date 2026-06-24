import "../sass/app.scss";

import { bootstrapWhisDefaults } from "./helpers/framework-defaults";
import initAboutMvv from "./sections/about-mvv";
import initClientsHexGrid from "./sections/clients-hex-grid";
import initFooter from "./sections/footer";
import initLatestReveal from "./sections/latest-reveal";
import initNavbar from "./sections/navbar";
import initProjectEntryRelated from "./sections/project-entry-related";
import initProjectEntrySlider from "./sections/project-entry-slider";
import initProjectsMap from "./sections/projects-map";
import initSmoothScrollLinks from "./sections/smooth-scroll";
import initSplide from "./sections/splide";
import initStats from "./sections/stats";

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

bootstrapWhisDefaults();

document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  initFooter();

  const projectEntrySlider = document.querySelector(
    "[data-project-entry-slider]",
  );
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

  /*
    No inicialices el slider de proyectos-entry con initSplide(),
    porque ese init es el genérico del jumbotron/home/eventos.
  */
  if (!projectEntrySlider) {
    initSplide(splideOptions);
  }

  initProjectEntrySlider();
  initProjectEntryRelated();

  initStats();
  initClientsHexGrid();
  initAboutMvv();
  initSmoothScrollLinks();

  initProjectsMap();

  initLatestReveal();

  // initAdminApiTokens();
  //  try {
  //   const response = await siteApi("/projects", {
  //     query: {
  //       limit: 6,
  //     },
  //   });

  //   console.log(response.projects);
  // } catch (error) {
  //   console.error(error.message, error.data);
  // }
});

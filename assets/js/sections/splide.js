import "@splidejs/splide/css";
import "@splidejs/splide/css/skyblue";
import "@splidejs/splide/css/sea-green";
import "@splidejs/splide/css/core";
import Splide from "@splidejs/splide";

export default function initSplide(customOptions = {}) {
  const splideElement = document.querySelector(".splide");

  if (!splideElement) return null;

  const slides = splideElement.querySelectorAll(".splide__slide");
  const slideCount = slides.length;
  const hasMultipleSlides = slideCount > 1;

  const TEXT_VISIBLE_CLASS = "is-text-visible";

  const defaultOptions = {
    type: hasMultipleSlides ? "loop" : "slide",
    rewind: !hasMultipleSlides,
    autoplay: hasMultipleSlides,
    interval: 3500,
    speed: 700,
    pauseOnHover: true,
    pauseOnFocus: true,
    arrows: hasMultipleSlides,
    pagination: hasMultipleSlides,
    drag: hasMultipleSlides,
    keyboard: hasMultipleSlides ? "global" : false,
  };

  const splide = new Splide(splideElement, {
    ...defaultOptions,
    ...customOptions,
  });

  function restartJumbotronTextAnimation(activeSlide = null) {
    const currentSlide =
      activeSlide || splideElement.querySelector(".splide__slide.is-active");

    splideElement
      .querySelectorAll(`.splide__slide.${TEXT_VISIBLE_CLASS}`)
      .forEach((slide) => {
        slide.classList.remove(TEXT_VISIBLE_CLASS);
      });

    if (currentSlide) {
      currentSlide.classList.remove(TEXT_VISIBLE_CLASS);

      // Fuerza reflow para que la animación se reinicie aunque sea el mismo slide.
      void currentSlide.offsetWidth;

      requestAnimationFrame(() => {
        currentSlide.classList.add(TEXT_VISIBLE_CLASS);
      });
    }

    // Overlay global fuera de Splide, por si después le agregas texto.
    const jumbotron = splideElement.closest(".home__jumbotron");
    const globalOverlayText = jumbotron?.querySelector(
      ":scope > .home__jumbotron__overlay .home__jumbotron__overlay__text"
    );

    if (globalOverlayText && globalOverlayText.textContent.trim() !== "") {
      globalOverlayText.classList.remove(TEXT_VISIBLE_CLASS);
      void globalOverlayText.offsetWidth;

      requestAnimationFrame(() => {
        globalOverlayText.classList.add(TEXT_VISIBLE_CLASS);
      });
    }
  }

  if (hasMultipleSlides) {
    splide.on("pagination:mounted", (data) => {
      data.list.classList.add("splide__pagination--custom");
      data.items.forEach((item) => {
        item.button.textContent = String(item.page + 1);
      });
    });
  }

  splide.on("inactive", (slide) => {
    slide.slide.classList.remove(TEXT_VISIBLE_CLASS);
  });

  splide.on("active", (slide) => {
    restartJumbotronTextAnimation(slide.slide);
  });

  splide.on("mounted", () => {
    if (!hasMultipleSlides) {
      const arrows = splideElement.querySelector(".splide__arrows");
      const pagination = splideElement.querySelector(".splide__pagination");

      if (arrows) arrows.style.display = "none";
      if (pagination) pagination.style.display = "none";

      splideElement.classList.add("splide--single");
    }

    restartJumbotronTextAnimation();
  });

  splide.mount();

  return splide;
}
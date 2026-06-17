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

  if (hasMultipleSlides) {
    splide.on("pagination:mounted", (data) => {
      data.list.classList.add("splide__pagination--custom");

      data.items.forEach((item) => {
        item.button.textContent = String(item.page + 1);
      });
    });
  }

  splide.on("mounted", () => {
    if (!hasMultipleSlides) {
      const arrows = splideElement.querySelector(".splide__arrows");
      const pagination = splideElement.querySelector(".splide__pagination");

      if (arrows) arrows.style.display = "none";
      if (pagination) pagination.style.display = "none";

      splideElement.classList.add("splide--single");
    }
  });

  splide.mount();

  return splide;
}
import Splide from "@splidejs/splide";

const PROJECT_ENTRY_SLIDER_SELECTOR = "[data-project-entry-slider]";
const VIDEO_SELECTOR = "video";

function padNumber(value) {
  return String(value).padStart(2, "0");
}

function ensureVideoLoaded(video) {
  if (!video || video.dataset.projectVideoLoaded === "true") return;

  const directSrc = video.dataset.src;
  const sources = video.querySelectorAll("source[data-src]");

  if (directSrc) {
    video.src = directSrc;
    delete video.dataset.src;
  }

  sources.forEach((source) => {
    source.src = source.dataset.src;
    delete source.dataset.src;
  });

  video.dataset.projectVideoLoaded = "true";
  video.load();
}

function pauseVideos(sliderElement, exceptSlide = null) {
  sliderElement.querySelectorAll(VIDEO_SELECTOR).forEach((video) => {
    if (exceptSlide && exceptSlide.contains(video)) return;

    if (!video.paused) {
      video.pause();
    }
  });
}

function loadVideosInside(slide) {
  if (!slide) return;

  slide.querySelectorAll(VIDEO_SELECTOR).forEach((video) => {
    ensureVideoLoaded(video);
  });
}

function setupVideoEvents(sliderElement) {
  sliderElement.querySelectorAll(VIDEO_SELECTOR).forEach((video) => {
    video.addEventListener("play", () => {
      const currentSlide = video.closest(".splide__slide");
      pauseVideos(sliderElement, currentSlide);
    });
  });
}

export default function initProjectEntrySlider() {
  const sliders = document.querySelectorAll(PROJECT_ENTRY_SLIDER_SELECTOR);

  if (!sliders.length) return [];

  return Array.from(sliders).map((sliderElement) => {
    if (sliderElement.dataset.projectEntryInitialized === "true") {
      return null;
    }

    const slides = sliderElement.querySelectorAll(".splide__slide");
    const slideCount = slides.length;
    const hasMultipleSlides = slideCount > 1;

    const currentElement = sliderElement.querySelector("[data-project-entry-current]");
    const totalElement = sliderElement.querySelector("[data-project-entry-total]");
    const progressElement = sliderElement.querySelector("[data-project-entry-progress]");

    if (totalElement) {
      totalElement.textContent = padNumber(slideCount);
    }

    const splide = new Splide(sliderElement, {
      type: "slide",
      rewind: hasMultipleSlides,
      perPage: 1,
      perMove: 1,
      gap: "1.5rem",
      speed: 760,
      easing: "cubic-bezier(0.22, 1, 0.36, 1)",
      arrows: hasMultipleSlides,
      pagination: hasMultipleSlides,
      drag: hasMultipleSlides,
      keyboard: hasMultipleSlides ? "global" : false,
      waitForTransition: true,
      trimSpace: false,
      autoplay: false,
      pauseOnHover: true,
      pauseOnFocus: true,
      padding: {
        right: hasMultipleSlides ? "clamp(2rem, 16vw, 22rem)" : "0",
      },
      breakpoints: {
        1023: {
          padding: {
            right: "2rem",
          },
        },
        767: {
          padding: {
            right: "1.5rem",
          },
          gap: "1rem",
        },
      },
    });

    function updateUI(index = splide.index) {
      const current = index + 1;
      const progress = slideCount > 0 ? (current / slideCount) * 100 : 0;

      if (currentElement) {
        currentElement.textContent = padNumber(current);
      }

      if (progressElement) {
        progressElement.style.width = `${progress}%`;
      }
    }

    function getSlideByIndex(index) {
      return splide.Components.Slides.getAt(index)?.slide || null;
    }

    function prepareActiveSlide(index = splide.index) {
      const activeSlide = getSlideByIndex(index);
      const previousSlide = getSlideByIndex(index - 1);
      const nextSlide = getSlideByIndex(index + 1);

      loadVideosInside(activeSlide);
      loadVideosInside(previousSlide);
      loadVideosInside(nextSlide);
    }

    splide.on("mounted", () => {
      sliderElement.dataset.projectEntryInitialized = "true";

      if (!hasMultipleSlides) {
        sliderElement.classList.add("splide--single");
      }

      setupVideoEvents(sliderElement);
      updateUI(0);
      prepareActiveSlide(0);
    });

    splide.on("move", () => {
      pauseVideos(sliderElement);
    });

    splide.on("moved", (newIndex) => {
      updateUI(newIndex);
      prepareActiveSlide(newIndex);
    });

    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        pauseVideos(sliderElement);
      }
    });

    splide.mount();

    return splide;
  });
}
import "@splidejs/splide/css";
import "@splidejs/splide/css/skyblue";
import "@splidejs/splide/css/sea-green";
import "@splidejs/splide/css/core";
import Splide from "@splidejs/splide";

const TEXT_VISIBLE_CLASS = "is-text-visible";
const VIDEO_SELECTOR = "video";

function ensureVideoLoaded(video) {
  if (!video || video.dataset.splideVideoLoaded === "true") return;

  const directSrc = video.dataset.src;
  const mobileSrc = video.dataset.mobileSrc;
  const sources = video.querySelectorAll("source[data-src]");

  const shouldUseMobile =
    mobileSrc &&
    window.matchMedia &&
    window.matchMedia("(max-width: 767px)").matches;

  if (shouldUseMobile) {
    video.src = mobileSrc;
  } else if (directSrc) {
    video.src = directSrc;
  }

  sources.forEach((source) => {
    if (!source.dataset.src) return;

    source.src = source.dataset.src;
    delete source.dataset.src;
  });

  video.dataset.splideVideoLoaded = "true";

  video.muted = true;
  video.loop = true;
  video.playsInline = true;
  video.autoplay = true;

  video.setAttribute("muted", "");
  video.setAttribute("loop", "");
  video.setAttribute("playsinline", "");
  video.setAttribute("autoplay", "");

  video.load();
}

function playVideo(video) {
  if (!video) return;

  ensureVideoLoaded(video);

  video.muted = true;
  video.loop = true;
  video.playsInline = true;
  video.autoplay = true;

  const playPromise = video.play();

  if (playPromise && typeof playPromise.catch === "function") {
    playPromise.catch(() => {
      /*
       * Evita romper el slider si el navegador bloquea autoplay.
       * Al estar muted normalmente sí se permite.
       */
    });
  }
}

function pauseVideo(video, reset = true) {
  if (!video) return;

  if (!video.paused) {
    video.pause();
  }

  if (reset) {
    try {
      video.currentTime = 0;
    } catch (error) {}
  }
}

function getSlideVideos(slide) {
  if (!slide) return [];

  return Array.from(slide.querySelectorAll(VIDEO_SELECTOR));
}

function pauseAllVideos(slides, exceptSlide = null) {
  slides.forEach((slide) => {
    if (exceptSlide && slide === exceptSlide) return;

    getSlideVideos(slide).forEach((video) => {
      pauseVideo(video, true);
    });
  });
}

function playSlideVideos(slide) {
  getSlideVideos(slide).forEach((video) => {
    playVideo(video);
  });
}

export default function initSplide() {
  document.querySelectorAll(".home__jumbotron .splide").forEach((element) => {
    const slides = Array.from(element.querySelectorAll(".splide__slide"));
    const hasMultipleSlides = slides.length > 1;

    const splide = new Splide(element, {
      type: hasMultipleSlides ? "fade" : "slide",
      rewind: true,
      clones: 0,

      autoplay: hasMultipleSlides,
      interval: 3500,
      speed: 700,

      perPage: 1,
      perMove: 1,

      arrows: hasMultipleSlides,
      pagination: hasMultipleSlides,
      drag: hasMultipleSlides,

      pauseOnHover: true,
      pauseOnFocus: true,
      waitForTransition: true,
      resetProgress: false,
    });

    const resetText = () => {
      slides.forEach((slide) => {
        slide.classList.remove(TEXT_VISIBLE_CLASS);
      });
    };

    const syncActiveSlide = () => {
      window.requestAnimationFrame(() => {
        const currentSlide = splide.Components.Slides.getAt(
          splide.index,
        )?.slide;

        resetText();
        pauseAllVideos(slides, currentSlide);

        if (currentSlide) {
          currentSlide.classList.add(TEXT_VISIBLE_CLASS);
          playSlideVideos(currentSlide);
        }
      });
    };

    const pauseCurrentVideosBeforeMove = () => {
      const currentSlide = splide.Components.Slides.getAt(splide.index)?.slide;

      if (currentSlide) {
        getSlideVideos(currentSlide).forEach((video) => {
          pauseVideo(video, true);
        });
      }

      resetText();
    };

    if (hasMultipleSlides) {
      splide.on("pagination:mounted", (data) => {
        data.list.classList.add("splide__pagination--custom");

        data.items.forEach((item) => {
          item.button.textContent = String(item.page + 1);
        });
      });
    }

    splide.on("mounted", syncActiveSlide);
    splide.on("move", pauseCurrentVideosBeforeMove);
    splide.on("moved", syncActiveSlide);
    splide.on("active", syncActiveSlide);
    splide.on("inactive", (slide) => {
      getSlideVideos(slide.slide).forEach((video) => {
        pauseVideo(video, true);
      });
    });

    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        pauseAllVideos(slides);
        return;
      }

      syncActiveSlide();
    });

    splide.mount();
  });
}
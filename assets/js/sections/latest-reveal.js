export default function initLatestReveal() {
  const sections = Array.from(document.querySelectorAll(".latest"));
  if (!sections.length) return;

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  );

  const isSmallViewport = () => window.matchMedia("(max-width: 767px)").matches;

  const revealSection = (section) => {
    section.classList.add("is-visible");
  };

  const isRevealed = (section) => section.classList.contains("is-visible");

  const isNearViewport = (section) => {
    const rect = section.getBoundingClientRect();
    const viewportHeight =
      window.innerHeight || document.documentElement.clientHeight || 0;

    /*
      Más permisivo en mobile.
      El problema suele pasar porque la sección cambia de alto
      después de cargar mapa/imágenes.
    */
    const offset = isSmallViewport() ? 260 : 120;

    return rect.top <= viewportHeight + offset && rect.bottom >= -offset;
  };

  const revealVisibleSections = () => {
    sections.forEach((section) => {
      if (isRevealed(section)) return;
      if (!isNearViewport(section)) return;

      revealSection(section);
    });
  };

  if (prefersReducedMotion.matches) {
    sections.forEach(revealSection);
    return;
  }

  /*
    En mobile conviene no dejar las cards ocultas esperando
    un IntersectionObserver que a veces no dispara bien con
    layouts sticky + mapas + imágenes lazy.
  */
  if (isSmallViewport()) {
    revealVisibleSections();

    window.setTimeout(revealVisibleSections, 80);
    window.setTimeout(revealVisibleSections, 350);
    window.setTimeout(revealVisibleSections, 900);

    window.addEventListener("scroll", revealVisibleSections, {
      passive: true,
    });

    window.addEventListener("resize", revealVisibleSections);
    window.addEventListener("orientationchange", revealVisibleSections);
    window.addEventListener("load", revealVisibleSections);

    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", revealVisibleSections);
    }

    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        revealSection(entry.target);
        obs.unobserve(entry.target);
      });
    },
    {
      threshold: 0,
      rootMargin: "0px 0px 16% 0px",
    }
  );

  sections.forEach((section) => observer.observe(section));

  revealVisibleSections();

  window.setTimeout(revealVisibleSections, 80);
  window.setTimeout(revealVisibleSections, 350);
  window.setTimeout(revealVisibleSections, 900);
  window.addEventListener("load", revealVisibleSections);
}
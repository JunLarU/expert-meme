export default function initAboutMvv() {
  initAboutMvvPanels();
}

function initAboutMvvPanels() {
  const containers = document.querySelectorAll("[data-about-mvv]");
  if (!containers.length) return;

  const desktopQuery = window.matchMedia("(min-width: 768px)");

  containers.forEach((container) => {
    const panels = Array.from(
      container.querySelectorAll("[data-about-mvv-panel]"),
    );

    if (!panels.length) return;

    const setActive = (panel, active) => {
      panel.classList.toggle("is-active", active);
      panel.setAttribute("aria-expanded", active ? "true" : "false");
    };

    const clearPanels = () => {
      panels.forEach((panel) => setActive(panel, false));
    };

    const toggleMobilePanel = (panel) => {
      const wasActive = panel.classList.contains("is-active");

      clearPanels();

      if (!wasActive) {
        setActive(panel, true);
      }
    };

    panels.forEach((panel) => {
      if (!panel.hasAttribute("tabindex")) {
        panel.setAttribute("tabindex", "0");
      }

      panel.setAttribute("aria-expanded", "false");

      panel.addEventListener("pointerenter", () => {
        if (!desktopQuery.matches) return;

        clearPanels();
        setActive(panel, true);
      });

      panel.addEventListener("pointerleave", () => {
        if (!desktopQuery.matches) return;

        setActive(panel, false);
      });

      panel.addEventListener("focusin", () => {
        if (!desktopQuery.matches) return;

        clearPanels();
        setActive(panel, true);
      });

      panel.addEventListener("focusout", (event) => {
        if (!desktopQuery.matches) return;
        if (panel.contains(event.relatedTarget)) return;

        setActive(panel, false);
      });

      // Dentro de initAboutMvvPanels, en el listener de click
      panel.addEventListener("click", () => {
        // Solo ejecuta el acordeón si estamos en mobile (max-width: 767px)
        if (window.matchMedia("(max-width: 767px)").matches) {
          toggleMobilePanel(panel);
        }
        // En escritorio no hacemos nada en click, porque el hover ya lo gestiona todo.
      });

      panel.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();

        if (desktopQuery.matches) {
          clearPanels();
          setActive(panel, true);
          return;
        }

        toggleMobilePanel(panel);
      });
    });

    const handleViewportChange = () => {
      clearPanels();
    };

    if (desktopQuery.addEventListener) {
      desktopQuery.addEventListener("change", handleViewportChange);
    } else {
      desktopQuery.addListener(handleViewportChange);
    }
  });
}


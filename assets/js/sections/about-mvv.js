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

    const buildClosedColumns = () => {
      return panels.map(() => "minmax(0, 1fr)").join(" ");
    };

    const buildActiveColumns = (activePanel) => {
      return panels
        .map((panel) => {
          if (panel !== activePanel) return "minmax(0, 1fr)";

          const grow = Number.parseFloat(panel.dataset.mvvGrow || "3.35");
          return `minmax(0, ${Number.isFinite(grow) ? grow : 3.35}fr)`;
        })
        .join(" ");
    };

    const setActive = (panel, active) => {
      panel.classList.toggle("is-active", active);
      panel.setAttribute("aria-expanded", active ? "true" : "false");
    };

    const setClosedColumns = () => {
      container.style.setProperty("--mvv-columns", buildClosedColumns());
      container.removeAttribute("data-mvv-has-active");
    };

    const clearPanels = () => {
      panels.forEach((panel) => setActive(panel, false));
      setClosedColumns();
    };

    const activateDesktopPanel = (panel) => {
      if (!desktopQuery.matches) return;

      panels.forEach((currentPanel) => {
        setActive(currentPanel, currentPanel === panel);
      });

      container.style.setProperty("--mvv-columns", buildActiveColumns(panel));
      container.setAttribute("data-mvv-has-active", "true");
    };

    const toggleMobilePanel = (panel) => {
      const wasActive = panel.classList.contains("is-active");

      clearPanels();

      if (!wasActive) {
        setActive(panel, true);
      }
    };

    /*
      Muy importante:
      Deja las columnas cerradas ya escritas como lista explícita desde el inicio.
      Así, cuando entras desde otra sección, el navegador anima desde:
      1fr 1fr 1fr 1fr 1fr
      hacia:
      1fr 1fr 3.35fr 1fr 1fr
      y ya no desde repeat(...), que suele brincar.
    */
    setClosedColumns();

    panels.forEach((panel) => {
      if (!panel.hasAttribute("tabindex")) {
        panel.setAttribute("tabindex", "0");
      }

      panel.setAttribute("aria-expanded", "false");

      panel.addEventListener("pointerenter", () => {
        activateDesktopPanel(panel);
      });

      panel.addEventListener("focusin", () => {
        activateDesktopPanel(panel);
      });

      panel.addEventListener("click", () => {
        if (desktopQuery.matches) return;

        toggleMobilePanel(panel);
      });

      panel.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;

        event.preventDefault();

        if (desktopQuery.matches) {
          activateDesktopPanel(panel);
          return;
        }

        toggleMobilePanel(panel);
      });
    });

    container.addEventListener("pointerleave", () => {
      if (!desktopQuery.matches) return;

      clearPanels();
    });

    container.addEventListener("focusout", (event) => {
      if (!desktopQuery.matches) return;
      if (container.contains(event.relatedTarget)) return;

      clearPanels();
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
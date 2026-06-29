// resources/js/sections/projects-search.js

export default function initProjectsSearch() {
  const container = document.querySelector("[data-projects-search]");
  if (!container) return;

  const input = container.querySelector("[data-projects-search-input]");
  const resultsContainer = container.querySelector("[data-projects-search-results]");
  const resultsInner = resultsContainer?.querySelector(".projects-search__results-inner");
  const clearBtn = container.querySelector("[data-projects-search-clear]");
  const quickButtons = Array.from(container.querySelectorAll("[data-projects-search-chip]"));

  if (!input || !resultsContainer || !resultsInner) return;

  const endpoint =
    container.dataset.projectsSearchEndpoint ||
    input.dataset.projectsSearchEndpoint ||
    "@url/site-api/search/projects";

  const limit = Number(container.dataset.projectsSearchLimit || 10);

  let debounceTimer = null;
  let currentAbortController = null;
  let activeResultIndex = -1;

  const escapeHtml = (value = "") => {
    const div = document.createElement("div");
    div.textContent = String(value ?? "");
    return div.innerHTML;
  };

  const normalizeText = (value = "") =>
    String(value ?? "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();

  const showResults = () => {
    resultsContainer.hidden = false;
    requestAnimationFrame(() => {
      resultsContainer.classList.add("is-active");
    });
  };

  const hideResults = () => {
    resultsContainer.classList.remove("is-active");
    activeResultIndex = -1;

    setTimeout(() => {
      if (!resultsContainer.classList.contains("is-active")) {
        resultsContainer.hidden = true;
      }
    }, 180);
  };

  const resetResults = () => {
    activeResultIndex = -1;
    resultsInner.innerHTML = "";
    hideResults();
  };

  const toggleClear = () => {
    if (!clearBtn) return;

    clearBtn.hidden = input.value.trim().length === 0;
  };

  const abortCurrentRequest = () => {
    if (!currentAbortController) return;

    currentAbortController.abort();
    currentAbortController = null;
  };

  const clearSearch = () => {
    abortCurrentRequest();

    input.value = "";
    toggleClear();
    resetResults();
    input.focus();
  };

  const highlightText = (text = "", query = "") => {
    const rawText = String(text ?? "");
    const terms = String(query ?? "")
      .split(/\s+/)
      .map((term) => term.trim())
      .filter((term) => term.length >= 2);

    if (!rawText || terms.length === 0) {
      return escapeHtml(rawText);
    }

    let output = escapeHtml(rawText);

    terms.forEach((term) => {
      const safeTerm = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

      if (!safeTerm) return;

      output = output.replace(
        new RegExp(`(${safeTerm})`, "gi"),
        "<mark>$1</mark>",
      );
    });

    return output;
  };

  const buildMeta = (project = {}) => {
    return [
      project.location,
      project.year,
    ]
      .map((item) => String(item ?? "").trim())
      .filter(Boolean)
      .join(" · ");
  };

  const renderLoading = (query) => {
    showResults();

    resultsInner.innerHTML = `
      <div class="projects-search__state projects-search__state--loading">
        <span class="projects-search__spinner" aria-hidden="true"></span>

        <div>
          <strong>Buscando obras</strong>
          <p>Consultando coincidencias para “${escapeHtml(query)}”.</p>
        </div>
      </div>
    `;
  };

  const renderEmpty = (query) => {
    showResults();

    resultsInner.innerHTML = `
      <div class="projects-search__state projects-search__state--empty">
        <span class="projects-search__state-icon" aria-hidden="true">⌕</span>

        <div>
          <strong>No encontramos coincidencias</strong>
          <p>No hay resultados para <mark>“${escapeHtml(query)}”</mark>. Prueba con ubicación, servicio, material, cliente o año.</p>
        </div>
      </div>
    `;
  };

  const renderError = () => {
    showResults();

    resultsInner.innerHTML = `
      <div class="projects-search__state projects-search__state--error">
        <span class="projects-search__state-icon" aria-hidden="true">!</span>

        <div>
          <strong>No se pudo completar la búsqueda</strong>
          <p>Intenta nuevamente o revisa tu conexión.</p>
        </div>
      </div>
    `;
  };

  const renderResults = (projects = [], total = 0, query = "") => {
    const safeProjects = Array.isArray(projects) ? projects : [];

    if (Number(total) === 0 || safeProjects.length === 0) {
      renderEmpty(query);
      return;
    }

    showResults();

    const resultLabel = safeProjects.length === 1
      ? "1 resultado encontrado"
      : `${safeProjects.length} resultados encontrados`;

    const cards = safeProjects.map((project, index) => {
      const title = project.title || "Proyecto";
      const href = project.href || "#";
      const image = project.image || "/images/JTRON.jpg";
      const imageAlt = project.image_alt || title;
      const category = project.category || "Proyecto";
      const service = project.service || "";
      const meta = buildMeta(project);
      const brief = project.brief || "";
      const tags = Array.isArray(project.tags) ? project.tags : [];

      const visibleTags = tags
        .map((tag) => String(tag ?? "").trim())
        .filter(Boolean)
        .slice(0, 3);

      if (service && !visibleTags.includes(service)) {
        visibleTags.push(service);
      }

      const tagsHtml = visibleTags.length
        ? `
          <ul class="projects-search__card-tags" aria-label="Etiquetas del proyecto">
            ${visibleTags
              .slice(0, 4)
              .map((tag) => `<li>${highlightText(tag, query)}</li>`)
              .join("")}
          </ul>
        `
        : "";

      return `
        <a
          href="${escapeHtml(href)}"
          class="projects-search__card"
          data-projects-search-result
          data-result-index="${index}"
          aria-label="Ver proyecto ${escapeHtml(title)}"
        >
          <figure class="projects-search__card-img">
            <img
              src="${escapeHtml(image)}"
              alt="${escapeHtml(imageAlt)}"
              loading="lazy"
              decoding="async"
              width="260"
              height="180"
            >

            <span class="projects-search__card-badge">
              ${highlightText(category, query)}
            </span>
          </figure>

          <div class="projects-search__card-content">
            <div class="projects-search__card-top">
              <span>Obra L+E</span>
              <small>${String(index + 1).padStart(2, "0")}</small>
            </div>

            <h4 class="projects-search__card-title">
              ${highlightText(title, query)}
            </h4>

            ${
              meta
                ? `
                  <p class="projects-search__card-meta">
                    ${highlightText(meta, query)}
                  </p>
                `
                : ""
            }

            ${
              brief
                ? `
                  <p class="projects-search__card-brief">
                    ${highlightText(brief, query)}
                  </p>
                `
                : ""
            }

            ${tagsHtml}

            <span class="projects-search__card-link">
              Ver proyecto <span aria-hidden="true">→</span>
            </span>
          </div>
        </a>
      `;
    }).join("");

    resultsInner.innerHTML = `
      <div class="projects-search__summary">
        <span>${escapeHtml(resultLabel)}</span>
        <small>para “${escapeHtml(query)}”</small>
      </div>

      <div class="projects-search__list" role="listbox" aria-label="Resultados de búsqueda de proyectos">
        ${cards}
      </div>
    `;

    activeResultIndex = -1;
  };

  const performSearch = (query) => {
    const cleanQuery = String(query ?? "").trim();

    abortCurrentRequest();

    if (cleanQuery === "") {
      resetResults();
      toggleClear();
      return;
    }

    renderLoading(cleanQuery);

    currentAbortController = new AbortController();

    const url = `${endpoint}?q=${encodeURIComponent(cleanQuery)}&limit=${encodeURIComponent(limit)}`;

    fetch(url, {
      signal: currentAbortController.signal,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Error en la respuesta");
        }

        return response.json();
      })
      .then((data) => {
        if (currentAbortController?.signal.aborted) return;

        renderResults(data.projects || [], data.total || 0, cleanQuery);
        toggleClear();
      })
      .catch((error) => {
        if (error.name === "AbortError") return;

        renderError();
      })
      .finally(() => {
        currentAbortController = null;
      });
  };

  const scheduleSearch = () => {
    const query = input.value;

    toggleClear();

    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      performSearch(query);
    }, 250);
  };

  const getResultLinks = () =>
    Array.from(container.querySelectorAll("[data-projects-search-result]"));

  const setActiveResult = (nextIndex) => {
    const results = getResultLinks();

    if (results.length === 0) return;

    activeResultIndex = Math.max(0, Math.min(nextIndex, results.length - 1));

    results.forEach((item, index) => {
      const isActive = index === activeResultIndex;

      item.classList.toggle("is-active", isActive);

      if (isActive) {
        item.focus({ preventScroll: true });
        item.scrollIntoView({
          block: "nearest",
          behavior: "smooth",
        });
      }
    });
  };

  input.addEventListener("input", scheduleSearch);

  input.addEventListener("focus", () => {
    const query = input.value.trim();

    if (query !== "" && resultsInner.innerHTML.trim() !== "") {
      showResults();
    }
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      clearSearch();
      input.blur();
      return;
    }

    if (event.key === "ArrowDown") {
      const results = getResultLinks();

      if (results.length === 0) return;

      event.preventDefault();
      setActiveResult(activeResultIndex + 1);
      return;
    }
  });

  resultsInner.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      clearSearch();
      input.blur();
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActiveResult(activeResultIndex + 1);
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();

      if (activeResultIndex <= 0) {
        activeResultIndex = -1;
        input.focus();
        getResultLinks().forEach((item) => item.classList.remove("is-active"));
        return;
      }

      setActiveResult(activeResultIndex - 1);
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener("click", clearSearch);
  }

  quickButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const value =
        button.dataset.projectsSearchChip ||
        button.textContent ||
        "";

      input.value = value.trim();
      toggleClear();
      performSearch(input.value);
      input.focus();
    });
  });

  document.addEventListener("click", (event) => {
    if (!container.contains(event.target)) {
      hideResults();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !resultsContainer.hidden) {
      clearSearch();
      input.blur();
    }
  });

  toggleClear();
}
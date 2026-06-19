export default function initClientsHexGrid() {
  const viewports = document.querySelectorAll("[data-clients-hex-grid]");
  if (!viewports.length) return;

  viewports.forEach((viewport) => {
    const section = viewport.closest(".clients-hex");
    const track = viewport.querySelector(".clients-hex__track");

    if (!section || !track) return;

    const sourceItems = Array.from(
      section.querySelectorAll(".clients-hex__source li")
    );

    const clients = sourceItems.map((item) => {
      const img = item.querySelector("img");

      return {
        name: (item.dataset.name || img?.alt || "Cliente").trim(),
        url: (item.dataset.url || "").trim(),
        logo: (
          img?.getAttribute("data-src") ||
          img?.getAttribute("src") ||
          ""
        ).trim(),
      };
    });

    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    );

    const isTouchLike = window.matchMedia(
      "(pointer: coarse), (max-width: 1023px)"
    );

    const config = {
      desktopMaxSpeed: 230,
      mobileSpeed: -92,
      deadZoneRatio: 0.27,
      smoothing: 0.055,
      bufferColumns: 8,
      emptyRatio: 0.28,
      resizeDebounceMs: 90,
    };

    const seed = Math.floor(Math.random() * 2147483647);

    let hexWidth = 120;
    let hexHeight = 104;
    let gap = 6;
    let stepX = 90;
    let stepY = 104;
    let rows = 6;
    let visibleColumns = 12;
    let renderedStartCol = 0;
    let logicalCol = 0;
    let pixelOffset = 0;

    let velocity = 0;
    let targetVelocity = 0;
    let rafId = 0;
    let lastTime = performance.now();
    let resizeTimer = 0;

    function updateNavbarClosedHeight() {
      const navbar = document.querySelector(".navbar");
      if (!navbar) return;

      const navbarMobile = navbar.querySelector(".navbar__mobile");
      const styles = window.getComputedStyle(navbar);

      const paddingTop = parseFloat(styles.paddingTop) || 0;
      const paddingBottom = parseFloat(styles.paddingBottom) || 0;

      let height = 0;

      if (navbarMobile) {
        height =
          navbarMobile.getBoundingClientRect().height +
          paddingTop +
          paddingBottom;
      } else {
        height = navbar.getBoundingClientRect().height;
      }

      if (Number.isFinite(height) && height > 0) {
        section.style.setProperty(
          "--navbar-closed-height",
          `${Math.ceil(height)}px`
        );
      }
    }

    function resolveCssLengthPx(element, propertyName, fallback) {
      const probe = document.createElement("div");

      probe.style.position = "absolute";
      probe.style.visibility = "hidden";
      probe.style.pointerEvents = "none";
      probe.style.width = `var(${propertyName})`;
      probe.style.height = "0";

      element.appendChild(probe);

      const value = probe.getBoundingClientRect().width;

      probe.remove();

      return Number.isFinite(value) && value > 0 ? value : fallback;
    }

    function hashInt(a, b, c = 0) {
      let h = seed;

      h ^= Math.imul(a | 0, 374761393);
      h ^= Math.imul(b | 0, 668265263);
      h ^= Math.imul(c | 0, 1442695041);

      h = Math.imul(h ^ (h >>> 13), 1274126177);
      h = (h ^ (h >>> 16)) >>> 0;

      return h;
    }

    function random01(a, b, c = 0) {
      return hashInt(a, b, c) / 4294967295;
    }

    function randomIndex(length, a, b, c = 0) {
      if (!length) return 0;

      return Math.floor(random01(a, b, c) * length) % length;
    }

    function getInitials(name) {
      const words = String(name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

      if (!words.length) return "C";

      if (words.length === 1) {
        return words[0].slice(0, 2).toUpperCase();
      }

      return `${words[0][0]}${words[1][0]}`.toUpperCase();
    }

    function shouldRenderEmptyCell(globalCol, row) {
      /*
       * Aleatorio estable:
       * no se repite como tile y no cambia mientras navegas.
       */
      return random01(globalCol, row, 11) < config.emptyRatio;
    }

    function pickClient(globalCol, row) {
      if (!clients.length) return null;

      return clients[randomIndex(clients.length, globalCol, row, 37)];
    }

    function createClientContent(client) {
      const hex = document.createElement("span");
      hex.className = "clients-hex__hex";

      if (client?.logo) {
        const img = document.createElement("img");
        img.className = "clients-hex__logo";
        img.src = client.logo;
        img.alt = client.name;
        img.loading = "lazy";
        img.decoding = "async";
        hex.appendChild(img);
      } else {
        const initials = document.createElement("span");
        initials.className = "clients-hex__initials";
        initials.textContent = getInitials(client?.name);
        hex.appendChild(initials);
      }

      const name = document.createElement("span");
      name.className = "clients-hex__name";
      name.textContent = client?.name || "Cliente";
      hex.appendChild(name);

      return hex;
    }

    function createEmptyContent() {
      const hex = document.createElement("span");
      hex.className = "clients-hex__hex";
      return hex;
    }

    function createCell({ x, y, width, height, client }) {
      const hasClient = Boolean(client);
      const cell = document.createElement(hasClient && client.url ? "a" : "div");

      cell.className = hasClient
        ? "clients-hex__cell clients-hex__cell--filled"
        : "clients-hex__cell clients-hex__cell--empty";

      cell.style.width = `${width}px`;
      cell.style.height = `${height}px`;
      cell.style.transform = `translate3d(${x}px, ${y}px, 0)`;

      if (hasClient) {
        cell.setAttribute("aria-label", client.name);
        cell.title = client.name;

        if (client.url) {
          cell.href = client.url;
          cell.target = "_blank";
          cell.rel = "noopener noreferrer";
        } else {
          cell.tabIndex = 0;
        }

        cell.appendChild(createClientContent(client));
      } else {
        cell.setAttribute("aria-hidden", "true");
        cell.appendChild(createEmptyContent());
      }

      return cell;
    }

    function updateDimensions() {
      updateNavbarClosedHeight();

      const viewportRect = viewport.getBoundingClientRect();
      const viewportWidth = viewportRect.width || window.innerWidth;
      const viewportHeight = viewportRect.height || 380;

      hexWidth = resolveCssLengthPx(viewport, "--hex-width", 120);
      gap = resolveCssLengthPx(viewport, "--hex-gap", 6);

      /*
       * Hexágono flat-top.
       * El gap se suma al desplazamiento de columnas/filas,
       * no al tamaño del hexágono. Así queda separación sin deformar.
       */
      hexHeight = hexWidth * 0.8660254038;
      stepX = hexWidth * 0.75 + gap;
      stepY = hexHeight + gap;

      rows = Math.max(4, Math.ceil(viewportHeight / stepY) + 4);
      visibleColumns = Math.max(
        12,
        Math.ceil(viewportWidth / stepX) + config.bufferColumns * 2 + 4
      );
    }

    function renderGrid() {
      updateDimensions();

      const fragment = document.createDocumentFragment();

      renderedStartCol = logicalCol - config.bufferColumns;

      track.innerHTML = "";

      const viewportHeight =
        viewport.getBoundingClientRect().height || section.clientHeight || 380;

      for (let i = 0; i < visibleColumns; i += 1) {
        const globalCol = renderedStartCol + i;
        const x = i * stepX;

        for (let row = -3; row < rows + 3; row += 1) {
          const y = row * stepY + (globalCol % 2 === 0 ? 0 : stepY / 2);

          if (y < -hexHeight * 1.6 || y > viewportHeight + hexHeight * 1.6) {
            continue;
          }

          const empty = shouldRenderEmptyCell(globalCol, row);
          const client = empty ? null : pickClient(globalCol, row);

          fragment.appendChild(
            createCell({
              x,
              y,
              width: hexWidth,
              height: hexHeight,
              client,
            })
          );
        }
      }

      track.appendChild(fragment);

      track.style.width = `${visibleColumns * stepX}px`;
      track.style.transform = `translate3d(${
        pixelOffset - config.bufferColumns * stepX
      }px, 0, 0)`;

      bindTouchOverlay();
    }

    function shiftColumnsIfNeeded() {
      let shifted = false;

      while (pixelOffset <= -stepX) {
        logicalCol += 1;
        pixelOffset += stepX;
        shifted = true;
      }

      while (pixelOffset >= stepX) {
        logicalCol -= 1;
        pixelOffset -= stepX;
        shifted = true;
      }

      if (shifted) {
        renderGrid();
      }
    }

    function updateDesktopVelocity(event) {
      if (isTouchLike.matches) return;

      const rect = viewport.getBoundingClientRect();
      const x = event.clientX - rect.left;

      const center = rect.width / 2;
      const deadZone = rect.width * config.deadZoneRatio;
      const distance = x - center;
      const absDistance = Math.abs(distance);

      if (absDistance <= deadZone) {
        targetVelocity = 0;
        return;
      }

      const activeRange = Math.max(1, center - deadZone);
      const normalized = Math.min(1, (absDistance - deadZone) / activeRange);

      /*
       * Curva suave:
       * cerca del centro acelera lento,
       * cerca de los bordes acelera más.
       */
      const power = normalized * normalized;

      targetVelocity =
        distance < 0
          ? config.desktopMaxSpeed * power
          : -config.desktopMaxSpeed * power;
    }

    function updateModeVelocity() {
      if (prefersReducedMotion.matches) {
        targetVelocity = 0;
        velocity = 0;
        return;
      }

      if (isTouchLike.matches) {
        targetVelocity = config.mobileSpeed;
      } else {
        targetVelocity = 0;
      }
    }

    function tick(now) {
      const delta = Math.min(0.05, (now - lastTime) / 1000);
      lastTime = now;

      velocity += (targetVelocity - velocity) * config.smoothing;

      pixelOffset += velocity * delta;

      shiftColumnsIfNeeded();

      track.style.transform = `translate3d(${
        pixelOffset - config.bufferColumns * stepX
      }px, 0, 0)`;

      rafId = requestAnimationFrame(tick);
    }

    function bindTouchOverlay() {
      const cells = track.querySelectorAll(".clients-hex__cell--filled");

      cells.forEach((cell) => {
        cell.addEventListener(
          "pointerdown",
          () => {
            cell.classList.add("is-touch-active");
          },
          { passive: true }
        );

        cell.addEventListener(
          "pointerup",
          () => {
            setTimeout(() => {
              cell.classList.remove("is-touch-active");
            }, 420);
          },
          { passive: true }
        );

        cell.addEventListener(
          "pointercancel",
          () => {
            cell.classList.remove("is-touch-active");
          },
          { passive: true }
        );

        cell.addEventListener(
          "pointerleave",
          () => {
            if (!isTouchLike.matches) return;
            cell.classList.remove("is-touch-active");
          },
          { passive: true }
        );
      });
    }

    function scheduleRebuild() {
      window.clearTimeout(resizeTimer);

      resizeTimer = window.setTimeout(() => {
        renderGrid();
        updateModeVelocity();
      }, config.resizeDebounceMs);
    }

    const resizeObserver = new ResizeObserver(scheduleRebuild);

    viewport.addEventListener("mousemove", updateDesktopVelocity, {
      passive: true,
    });

    viewport.addEventListener(
      "mouseleave",
      () => {
        if (!isTouchLike.matches) {
          targetVelocity = 0;
        }
      },
      { passive: true }
    );

    window.addEventListener(
      "resize",
      () => {
        updateNavbarClosedHeight();
        scheduleRebuild();
      },
      { passive: true }
    );

    isTouchLike.addEventListener?.("change", () => {
      updateModeVelocity();
      scheduleRebuild();
    });

    prefersReducedMotion.addEventListener?.("change", () => {
      updateModeVelocity();
      scheduleRebuild();
    });

    resizeObserver.observe(viewport);

    updateNavbarClosedHeight();
    updateModeVelocity();
    renderGrid();

    lastTime = performance.now();

    if (!prefersReducedMotion.matches) {
      rafId = requestAnimationFrame(tick);
    }
  });
}
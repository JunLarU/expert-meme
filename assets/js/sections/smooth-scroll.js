export default function initSmoothScrollLinks(options = {}) {
  const {
    selector = "[data-smooth-scroll]",
    defaultOffset = 0,
    activeClass = "is-scrolling",
    storageKey = "whis:smooth-scroll",
    autoScrollOnHash = true,
    initialDelay = 120,
    scrollDuration = 700,
    cleanHashOnLoad = true,
  } = options;

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  );

  const normalizePathname = (pathname) => {
    return pathname.replace(/\/+$/, "") || "/";
  };

  const safeDecode = (value) => {
    try {
      return decodeURIComponent(value);
    } catch {
      return value;
    }
  };

  const cleanUrlFromHash = () => {
    if (!window.location.hash) return;

    const cleanUrl =
      window.location.origin +
      window.location.pathname +
      window.location.search;

    window.history.replaceState(null, "", cleanUrl);
  };

  const getElementFromHash = (hash) => {
    if (!hash || hash === "#") return null;

    const decodedHash = safeDecode(hash);
    const id = decodedHash.replace(/^#/, "");

    if (!id) return null;

    const byId = document.getElementById(id);
    if (byId) return byId;

    try {
      return document.querySelector(decodedHash);
    } catch {
      return null;
    }
  };

  const getElementFromSelector = (selectorValue) => {
    if (!selectorValue) return null;

    try {
      return document.querySelector(selectorValue);
    } catch {
      return null;
    }
  };

  const getLinkUrl = (link) => {
    const href = link.getAttribute("href");
    if (!href) return null;

    try {
      return new URL(href, window.location.href);
    } catch {
      return null;
    }
  };

  const isSamePageUrl = (url) => {
    if (!url) return false;

    return (
      url.origin === window.location.origin &&
      normalizePathname(url.pathname) ===
        normalizePathname(window.location.pathname)
    );
  };

  const getOffset = (source = null) => {
    const rawOffset = source?.dataset?.scrollOffset;
    const parsedOffset = Number.parseFloat(rawOffset);

    return Number.isFinite(parsedOffset) ? parsedOffset : defaultOffset;
  };

  const getTarget = ({ link = null, hash = "" } = {}) => {
    const explicitTarget = link?.dataset?.scrollTarget;

    if (explicitTarget) {
      return getElementFromSelector(explicitTarget);
    }

    return getElementFromHash(hash);
  };

  const setPendingScroll = (link, url) => {
    const explicitTarget = link.dataset.scrollTarget || "";
    const hash = url.hash || "";

    if (!explicitTarget && !hash) return false;

    const payload = {
      hash,
      target: explicitTarget,
      path: normalizePathname(url.pathname),
      origin: url.origin,
      offset: getOffset(link),
      timestamp: Date.now(),
    };

    try {
      sessionStorage.setItem(storageKey, JSON.stringify(payload));
      return true;
    } catch {
      return false;
    }
  };

  const getPendingScroll = () => {
    try {
      const raw = sessionStorage.getItem(storageKey);
      if (!raw) return null;

      const payload = JSON.parse(raw);

      const isValid =
        payload &&
        payload.origin === window.location.origin &&
        payload.path === normalizePathname(window.location.pathname);

      if (!isValid) {
        sessionStorage.removeItem(storageKey);
        return null;
      }

      return payload;
    } catch {
      return null;
    }
  };

  const clearPendingScroll = () => {
    try {
      sessionStorage.removeItem(storageKey);
    } catch {
      // No-op.
    }
  };

  const scrollToTarget = (target, offset = defaultOffset) => {
    if (!target) return false;

    const targetTop =
      target.getBoundingClientRect().top + window.scrollY - offset;

    document.documentElement.classList.add(activeClass);

    window.scrollTo({
      top: Math.max(0, targetTop),
      behavior: prefersReducedMotion.matches ? "auto" : "smooth",
    });

    window.setTimeout(() => {
      document.documentElement.classList.remove(activeClass);
    }, prefersReducedMotion.matches ? 0 : scrollDuration);

    return true;
  };

  const scrollFromCurrentHashOrPending = () => {
    const pendingScroll = getPendingScroll();

    const hash = pendingScroll?.hash || window.location.hash;
    const targetSelector = pendingScroll?.target || "";
    const offset = Number.isFinite(Number(pendingScroll?.offset))
      ? Number(pendingScroll.offset)
      : defaultOffset;

    if (!hash && !targetSelector) return;

    const target = targetSelector
      ? getElementFromSelector(targetSelector)
      : getElementFromHash(hash);

    if (cleanHashOnLoad) {
      cleanUrlFromHash();
    }

    if (!target) {
      clearPendingScroll();
      return;
    }

    window.setTimeout(() => {
      scrollToTarget(target, offset);
      clearPendingScroll();
    }, initialDelay);
  };

  const getCleanUrl = (url) => {
    return url.origin + url.pathname + url.search;
  };

  const links = document.querySelectorAll(selector);

  links.forEach((link) => {
    link.addEventListener("click", (event) => {
      const url = getLinkUrl(link);
      if (!url) return;

      const hasTarget = Boolean(link.dataset.scrollTarget || url.hash);

      if (!hasTarget) return;

      /*
        Otra página del mismo sitio:
        - intercepta el click
        - guarda el destino
        - navega a la URL limpia, sin #hash
      */
      if (!isSamePageUrl(url)) {
        if (url.origin !== window.location.origin) return;

        const saved = setPendingScroll(link, url);
        if (!saved) return;

        event.preventDefault();

        window.location.assign(getCleanUrl(url));
        return;
      }

      /*
        Misma página:
        - smooth scroll inmediato
        - no cambia la URL
      */
      const target = getTarget({
        link,
        hash: url.hash,
      });

      if (!target) return;

      event.preventDefault();

      scrollToTarget(target, getOffset(link));

      if (cleanHashOnLoad) {
        cleanUrlFromHash();
      }
    });
  });

  /*
    Si alguien entra directo con:
    /servicios#servicios

    Hace scroll, pero limpia la URL a:
    /servicios
  */
  if (autoScrollOnHash && window.location.hash) {
    scrollFromCurrentHashOrPending();
    return;
  }

  /*
    Si venimos desde otra página mediante sessionStorage:
    /servicios
    pero con destino guardado internamente.
  */
  if (getPendingScroll()) {
    scrollFromCurrentHashOrPending();
  }
}
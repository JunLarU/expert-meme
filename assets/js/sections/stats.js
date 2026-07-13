export default function initStats(customStats = null) {
  const stats = customStats ?? [
    { value: "+20 años", label: "de trayectoria en diseño, fabricación y montaje" },
    { value: "+2,500 proyectos", label: "diseño estructural" },
    { value: "+30 estados", label: "con proyectos desarrollados" },
    { value: "+1’000,000m²", label: "de estructura diseñada, fabricada y montada" },
  ];

  const section = document.querySelector(".stats");
  const statsContainer = document.querySelector(".stats__content");

  if (!section || !statsContainer || !Array.isArray(stats) || stats.length === 0) return;

  const animationDuration = 750;
  const changeEvery = 1500;

  const normalizeNumber = (numberText) => {
    let text = String(numberText ?? "").trim();

    /*
      Soporta:
      1,000,000
      1.000.000
      1’000,000
      1'000,000
      1 000 000
      1,000,000.50
      1.000.000,50
    */
    text = text.replace(/[\s\u00a0\u202f'’‘`´]/g, "");

    if (!text) {
      return {
        number: 0,
        decimals: 0,
      };
    }

    const dotCount = (text.match(/\./g) ?? []).length;
    const commaCount = (text.match(/,/g) ?? []).length;

    const hasDot = dotCount > 0;
    const hasComma = commaCount > 0;

    let decimals = 0;
    let decimalSeparator = null;

    if (hasDot && hasComma) {
      const lastDot = text.lastIndexOf(".");
      const lastComma = text.lastIndexOf(",");

      decimalSeparator = lastDot > lastComma ? "." : ",";
    } else if (hasComma) {
      const parts = text.split(",");
      const lastPart = parts[parts.length - 1];

      if (commaCount === 1) {
        decimalSeparator = lastPart.length === 3 ? null : ",";
      } else {
        decimalSeparator = lastPart.length === 3 ? null : ",";
      }
    } else if (hasDot) {
      const parts = text.split(".");
      const lastPart = parts[parts.length - 1];

      if (dotCount === 1) {
        decimalSeparator = lastPart.length === 3 ? null : ".";
      } else {
        decimalSeparator = lastPart.length === 3 ? null : ".";
      }
    }

    if (decimalSeparator) {
      const decimalPart = text.split(decimalSeparator).pop() ?? "";
      decimals = decimalPart.length;

      const thousandSeparator = decimalSeparator === "." ? "," : ".";

      text = text
        .replace(new RegExp(`\\${thousandSeparator}`, "g"), "")
        .replace(decimalSeparator, ".");
    } else {
      text = text.replace(/[.,]/g, "");
      decimals = 0;
    }

    const number = Number(text);

    return {
      number: Number.isFinite(number) ? number : 0,
      decimals,
    };
  };

  const shouldSeparateSuffix = (suffix) => {
    const cleanSuffix = String(suffix ?? "").trim();

    if (!cleanSuffix) return false;

    /*
      Separa texto/unidades:
      +500proyectos  => +500 proyectos
      +1,000,000m²   => +1,000,000 m²

      Pero no separa símbolos pegados:
      15%            => 15%
      3,500+         => 3,500+
    */
    return /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9]/.test(cleanSuffix);
  };

  const parseValue = (value) => {
    const raw = String(value ?? "").trim();

    /*
      Antes fallaba con millones tipo:
      +1’000,000m²

      Ahora toma como número completo:
      1’000,000
    */
    const match = raw.match(/([+-]?)(\d(?:[\d\s.,'’‘`´]*\d)?)/u);

    if (!match) {
      return {
        prefix: raw,
        number: 0,
        suffix: "",
        decimals: 0,
        separateSuffix: false,
      };
    }

    const fullMatch = match[0];
    const sign = match[1] ?? "";
    const numberText = match[2] ?? "";

    const start = match.index ?? 0;
    const end = start + fullMatch.length;

    const prefix = raw.slice(0, start) + sign;
    const suffix = raw.slice(end).trim();

    const normalized = normalizeNumber(numberText);

    return {
      prefix,
      number: normalized.number,
      suffix,
      decimals: normalized.decimals,
      separateSuffix: shouldSeparateSuffix(suffix),
    };
  };

  const getFormatter = (decimals = 0) => {
    return new Intl.NumberFormat("es-MX", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  };

  statsContainer.innerHTML = "";

  const nodes = stats.map((stat) => {
    const parsed = parseValue(stat.value);

    const el = document.createElement("div");
    el.className = "stat";

    const valueEl = document.createElement("h3");
    valueEl.className = "stat__value";
    valueEl.style.whiteSpace = "nowrap";

    const prefixEl = document.createElement("span");
    prefixEl.className = "stat__prefix";

    const numEl = document.createElement("span");
    numEl.className = "stat__num";

    const suffixEl = document.createElement("span");
    suffixEl.className = "stat__suffix";

    const labelEl = document.createElement("p");
    labelEl.className = "stat__label";

    prefixEl.textContent = parsed.prefix;
    numEl.textContent = getFormatter(parsed.decimals).format(0);
    suffixEl.textContent = parsed.suffix;
    labelEl.textContent = stat.label ?? "";

    if (parsed.separateSuffix) {
      suffixEl.style.marginLeft = "0.16em";
    }

    valueEl.appendChild(prefixEl);
    valueEl.appendChild(numEl);
    valueEl.appendChild(suffixEl);

    el.appendChild(valueEl);
    el.appendChild(labelEl);

    el._rafId = 0;
    el._current = 0;
    el._target = parsed.number;
    el._decimals = parsed.decimals;

    statsContainer.appendChild(el);

    return el;
  });

  const animateNumber = (node, toValue, duration = 750) => {
    const numEl = node.querySelector(".stat__num");
    const decimals = node._decimals ?? 0;
    const formatter = getFormatter(decimals);

    if (!numEl) return;

    if (node._rafId) {
      cancelAnimationFrame(node._rafId);
      node._rafId = 0;
    }

    const from = 0;
    const target = Number.isFinite(toValue) ? toValue : 0;
    const start = performance.now();

    const tick = (now) => {
      const t = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - t, 3);

      let current = from + (target - from) * eased;

      if (decimals === 0) {
        current = Math.round(current);
      } else {
        current = Number(current.toFixed(decimals));
      }

      node._current = current;
      numEl.textContent = formatter.format(current);

      if (t < 1) {
        node._rafId = requestAnimationFrame(tick);
      } else {
        node._rafId = 0;
        node._current = target;
        numEl.textContent = formatter.format(target);
      }
    };

    node._rafId = requestAnimationFrame(tick);
  };

  let index = 0;
  let interval = null;

  const activate = (i) => {
    nodes.forEach((node) => {
      node.classList.remove("active");
    });

    const node = nodes[i];

    if (!node) return;

    node.classList.add("active");
    animateNumber(node, node._target, animationDuration);
  };

  const startAnimation = () => {
    if (interval) return;

    activate(index);

    interval = setInterval(() => {
      index = (index + 1) % nodes.length;
      activate(index);
    }, changeEvery);
  };

  const stopAnimation = () => {
    if (interval) {
      clearInterval(interval);
      interval = null;
    }

    nodes.forEach((node) => {
      if (node._rafId) {
        cancelAnimationFrame(node._rafId);
        node._rafId = 0;
      }
    });
  };

  if (!("IntersectionObserver" in window)) {
    startAnimation();
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          startAnimation();
        } else {
          stopAnimation();
        }
      }
    },
    { threshold: 0.4 },
  );

  observer.observe(section);
}
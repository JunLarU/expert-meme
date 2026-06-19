export default function initStats(customStats = null) {
  const stats = customStats ?? [
    { value: "+100", label: "Edificios construidos" },
    { value: "+1,000", label: "Toneladas de acero semanales" },
    { value: "+15 Años", label: "de experiencia" },
    { value: "100+ Empleados", label: "equipo de trabajo" },
  ];

  const section = document.querySelector(".stats");
  const statsContainer = document.querySelector(".stats__content");

  if (!section || !statsContainer || !Array.isArray(stats) || stats.length === 0) return;

  const animationDuration = 750;
  const changeEvery = 1500;

  // Convierte textos como:
  // "+15 Años"          => prefix "+", number 15, suffix " Años"
  // "3,500+ Empleados" => prefix "", number 3500, suffix "+ Empleados"
  // "+17,000 Ton"      => prefix "+", number 17000, suffix " Ton"
  // "15.5%"            => prefix "", number 15.5, suffix "%"
  const parseValue = (value) => {
    const raw = String(value ?? "").trim();

    const match = raw.match(/([+-]?)(\d[\d.,]*)/);

    if (!match) {
      return {
        prefix: raw,
        number: 0,
        suffix: "",
        decimals: 0,
      };
    }

    const fullMatch = match[0];
    const sign = match[1] ?? "";
    const numberText = match[2] ?? "";

    const start = match.index;
    const end = start + fullMatch.length;

    const prefix = raw.slice(0, start) + sign;
    const suffix = raw.slice(end);

    const normalized = normalizeNumber(numberText);

    return {
      prefix,
      number: normalized.number,
      suffix,
      decimals: normalized.decimals,
    };
  };

  const normalizeNumber = (numberText) => {
    let text = String(numberText).trim();

    const hasDot = text.includes(".");
    const hasComma = text.includes(",");

    let decimals = 0;

    if (hasDot && hasComma) {
      const lastDot = text.lastIndexOf(".");
      const lastComma = text.lastIndexOf(",");

      const decimalSeparator = lastDot > lastComma ? "." : ",";
      const thousandSeparator = decimalSeparator === "." ? "," : ".";

      const decimalPart = text.split(decimalSeparator).pop();
      decimals = decimalPart.length;

      text = text
        .replaceAll(thousandSeparator, "")
        .replace(decimalSeparator, ".");
    } else if (hasComma) {
      const parts = text.split(",");
      const lastPart = parts[parts.length - 1];

      // "1,000" => miles
      // "15,5"  => decimal
      if (parts.length === 2 && lastPart.length === 3) {
        text = text.replaceAll(",", "");
        decimals = 0;
      } else {
        decimals = lastPart.length;
        text = text.replace(",", ".");
      }
    } else if (hasDot) {
      const parts = text.split(".");
      const lastPart = parts[parts.length - 1];

      // "1.000" => miles
      // "15.5"  => decimal
      if (parts.length === 2 && lastPart.length === 3) {
        text = text.replaceAll(".", "");
        decimals = 0;
      } else {
        decimals = lastPart.length;
      }
    }

    const number = Number(text);

    return {
      number: Number.isFinite(number) ? number : 0,
      decimals,
    };
  };

  const getFormatter = (decimals = 0) => {
    return new Intl.NumberFormat("es-MX", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  };

  // ---------- Render ----------
  statsContainer.innerHTML = "";

  const nodes = stats.map((stat) => {
    const parsed = parseValue(stat.value);

    const el = document.createElement("div");
    el.className = "stat";

    const valueEl = document.createElement("h3");
    valueEl.className = "stat__value";

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

  // ---------- Animación ----------
  const animateNumber = (node, toValue, duration = 750) => {
    const numEl = node.querySelector(".stat__num");
    const decimals = node._decimals ?? 0;
    const formatter = getFormatter(decimals);

    if (!numEl) return;

    if (node._rafId) {
      cancelAnimationFrame(node._rafId);
    }

    const from = 0;
    const start = performance.now();

    const tick = (now) => {
      const t = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - t, 3);

      let current = from + (toValue - from) * eased;

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
        numEl.textContent = formatter.format(toValue);
      }
    };

    node._rafId = requestAnimationFrame(tick);
  };

  // ---------- Ciclo ----------
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
    clearInterval(interval);
    interval = null;

    nodes.forEach((node) => {
      if (node._rafId) {
        cancelAnimationFrame(node._rafId);
        node._rafId = 0;
      }
    });
  };

  // ---------- IntersectionObserver ----------
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
    { threshold: 0.4 }
  );

  observer.observe(section);
}
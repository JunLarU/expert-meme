import { MX_EN_MAP } from "./projects-map-mx";
import imgJtron from "@images/JTRON.jpg";
import imgStats from "@images/STATS.jpg";
import imgMembership from "@images/membership.jpg";
const PROJECT_IMAGES = {
  jtron: imgJtron,
  stats: imgStats,
  membership: imgMembership,
};
/* ============================================================================
 * 1. CALIBRACIÓN GEOGRÁFICA  (lat/lng  ->  unidades del mapa mx_en)
 * ----------------------------------------------------------------------------
 * El mapa mx_en NO trae metadatos de proyección, así que jVectorMap no puede
 * convertir lat/lng por sí solo. Aquí definimos un sistema de coordenadas
 * propio a partir de PUNTOS DE CONTROL: lugares cuya posición real (lat/lng)
 * y cuya posición en las unidades internas del SVG conocemos.
 *
 * Con esos puntos se ajusta por mínimos cuadrados una transformación AFÍN:
 *
 *      x = a·lng + b·lat + c
 *      y = d·lng + e·lat + f
 *
 * Los términos cruzados (b y d) absorben la rotación/convergencia de los
 * meridianos de la proyección con la que se dibujó el mapa. El ajuste es
 * AUTOMÁTICO: si editas, agregas o quitas puntos de control, los coeficientes
 * se recalculan solos al cargar.
 *
 * Para recalibrar con más precisión: abre el mapa, identifica dos o tres
 * ciudades cuya posición real conozcas, observa en qué (x,y) caen y ajusta.
 * Bastan 3 puntos bien repartidos; aquí usamos 5 (las esquinas del país).
 * ========================================================================== */

/* ============================================================================
 * 1. CONVERSIÓN GEOGRÁFICA LOCAL  lat/lng -> coordenadas del mapa mx_en
 * ----------------------------------------------------------------------------
 * El mapa mx_en NO trae proyección real. En vez de usar una sola transformación
 * global para todo México, usamos puntos de control por zona y ajustamos una
 * transformación local con los puntos más cercanos.
 *
 * Esto mejora MUCHO en Querétaro / Guanajuato / CDMX y mantiene funcionando
 * el resto del país.
 * ========================================================================== */

const MAP_CONTROL_POINTS = [
  // Bajío / Centro
  { name: "Querétaro", lat: 20.5888, lng: -100.3899, x: 558.7, y: 445.4 },
  { name: "Corregidora", lat: 20.5389, lng: -100.433, x: 552.5, y: 452.5 },
  { name: "El Marqués", lat: 20.6122, lng: -100.2731, x: 567.0, y: 443.5 },
  { name: "Pedro Escobedo", lat: 20.5047, lng: -100.1447, x: 574.0, y: 456.0 },
  {
    name: "San Juan del Río",
    lat: 20.3886,
    lng: -100.0003,
    x: 581.0,
    y: 463.0,
  },

  { name: "Celaya", lat: 20.5222, lng: -100.8122, x: 531.0, y: 455.0 },
  {
    name: "Apaseo el Grande",
    lat: 20.5497,
    lng: -100.6792,
    x: 540.0,
    y: 450.0,
  },
  { name: "Irapuato", lat: 20.6767, lng: -101.3563, x: 505.0, y: 448.0 },
  { name: "Salamanca", lat: 20.5739, lng: -101.1957, x: 512.0, y: 459.0 },
  { name: "Silao", lat: 20.9431, lng: -101.4282, x: 504.0, y: 430.0 },
  { name: "León", lat: 21.1219, lng: -101.686, x: 495.0, y: 418.0 },

  { name: "CDMX", lat: 19.4326, lng: -99.1332, x: 581.6, y: 494.8 },
  { name: "Toluca", lat: 19.2826, lng: -99.6557, x: 557.0, y: 498.0 },
  { name: "Pachuca", lat: 20.1011, lng: -98.7591, x: 590.0, y: 456.0 },
  { name: "San Luis Potosí", lat: 22.1565, lng: -100.9855, x: 543.0, y: 379.0 },
  { name: "Aguascalientes", lat: 21.8853, lng: -102.2916, x: 481.3, y: 406.8 },
  { name: "Guadalajara", lat: 20.6597, lng: -103.3496, x: 442.0, y: 446.9 },
  { name: "Morelia", lat: 19.7059, lng: -101.1949, x: 494.4, y: 502.9 },

  // Norte / Occidente
  { name: "Tijuana", lat: 32.5149, lng: -117.0382, x: 64.0, y: 40.0 },
  { name: "Mexicali", lat: 32.6245, lng: -115.4523, x: 130.0, y: 60.0 },
  { name: "Hermosillo", lat: 29.0729, lng: -110.9559, x: 210.6, y: 151.8 },
  { name: "Chihuahua", lat: 28.632, lng: -106.0691, x: 368.0, y: 186.1 },
  { name: "Monterrey", lat: 25.6866, lng: -100.3161, x: 557.9, y: 293.7 },
  { name: "Saltillo", lat: 25.4267, lng: -100.9954, x: 494.6, y: 236.8 },
  { name: "Tampico", lat: 22.2331, lng: -97.8611, x: 592.3, y: 309.7 },
  { name: "Culiacán", lat: 24.8091, lng: -107.394, x: 327.5, y: 316.6 },
  { name: "Durango", lat: 24.0277, lng: -104.6532, x: 406.8, y: 320.0 },
  { name: "Tepic", lat: 21.5042, lng: -104.8946, x: 391.6, y: 412.9 },
  { name: "Zacatecas", lat: 22.7709, lng: -102.5832, x: 475.0, y: 372.8 },

  // Sur / Golfo / Península
  { name: "Veracruz", lat: 19.1738, lng: -96.1342, x: 677.0, y: 476.5 },
  { name: "Puebla", lat: 19.0414, lng: -98.2063, x: 621.6, y: 493.8 },
  { name: "Cuernavaca", lat: 18.9242, lng: -99.2216, x: 584.6, y: 514.5 },
  { name: "Chilpancingo", lat: 17.5515, lng: -99.5, x: 552.9, y: 551.8 },
  { name: "Oaxaca", lat: 17.0732, lng: -96.7266, x: 674.9, y: 564.0 },
  { name: "Tuxtla Gutiérrez", lat: 16.7516, lng: -93.1029, x: 803.6, y: 587.0 },
  { name: "Villahermosa", lat: 17.9895, lng: -92.9475, x: 790.9, y: 533.1 },
  { name: "Campeche", lat: 19.8301, lng: -90.5349, x: 842.1, y: 481.2 },
  { name: "Mérida", lat: 20.9674, lng: -89.5926, x: 897.6, y: 421.6 },
  { name: "Cancún", lat: 21.1619, lng: -86.8515, x: 927.2, y: 463.2 },
  { name: "Chetumal", lat: 18.5001, lng: -88.2961, x: 894.1, y: 530.0 },
];

function geoDistanceSq(a, b) {
  const midLat = (((a.lat + b.lat) / 2) * Math.PI) / 180;
  const dx = (a.lng - b.lng) * Math.cos(midLat);
  const dy = a.lat - b.lat;
  return dx * dx + dy * dy;
}

function solve3x3(M, v) {
  const a = [
    [M[0][0], M[0][1], M[0][2], v[0]],
    [M[1][0], M[1][1], M[1][2], v[1]],
    [M[2][0], M[2][1], M[2][2], v[2]],
  ];

  for (let col = 0; col < 3; col++) {
    let pivot = col;

    for (let row = col + 1; row < 3; row++) {
      if (Math.abs(a[row][col]) > Math.abs(a[pivot][col])) {
        pivot = row;
      }
    }

    [a[col], a[pivot]] = [a[pivot], a[col]];

    const div = a[col][col] || 1e-12;
    for (let c = col; c < 4; c++) a[col][c] /= div;

    for (let row = 0; row < 3; row++) {
      if (row === col) continue;

      const factor = a[row][col];
      for (let c = col; c < 4; c++) {
        a[row][c] -= factor * a[col][c];
      }
    }
  }

  return [a[0][3], a[1][3], a[2][3]];
}

function fitWeightedAffine(points, weights) {
  let Sll = 0;
  let Sla = 0;
  let Sl = 0;
  let Saa = 0;
  let Sa = 0;
  let Sw = 0;

  let Slx = 0;
  let Sax = 0;
  let Sx = 0;

  let Sly = 0;
  let Say = 0;
  let Sy = 0;

  points.forEach((p, i) => {
    const w = weights[i];
    const lng = p.lng;
    const lat = p.lat;

    Sll += w * lng * lng;
    Sla += w * lng * lat;
    Sl += w * lng;
    Saa += w * lat * lat;
    Sa += w * lat;
    Sw += w;

    Slx += w * lng * p.x;
    Sax += w * lat * p.x;
    Sx += w * p.x;

    Sly += w * lng * p.y;
    Say += w * lat * p.y;
    Sy += w * p.y;
  });

  const M = [
    [Sll, Sla, Sl],
    [Sla, Saa, Sa],
    [Sl, Sa, Sw],
  ];

  const [a, b, c] = solve3x3(M, [Slx, Sax, Sx]);
  const [d, e, f] = solve3x3(M, [Sly, Say, Sy]);

  return { a, b, c, d, e, f };
}

function latLngToMapPoint(lat, lng) {
  const target = { lat: Number(lat), lng: Number(lng) };

  if (!Number.isFinite(target.lat) || !Number.isFinite(target.lng)) {
    return [0, 0];
  }

  const nearest = MAP_CONTROL_POINTS.map((point) => ({
    ...point,
    distSq: geoDistanceSq(target, point),
  }))
    .sort((a, b) => a.distSq - b.distSq)
    .slice(0, 8);

  const exact = nearest.find((point) => point.distSq < 1e-10);
  if (exact) return [exact.x, exact.y];

  const weights = nearest.map((point) => 1 / Math.max(point.distSq, 0.000001));
  const affine = fitWeightedAffine(nearest, weights);

  return [
    affine.a * target.lng + affine.b * target.lat + affine.c,
    affine.d * target.lng + affine.e * target.lat + affine.f,
  ];
}

/* ============================================================================
 * 2. DATOS DE MARCADORES
 * ----------------------------------------------------------------------------
 * Ahora cada pin se define con `lat` y `lng` REALES. La posición en el mapa
 * se calcula automáticamente. Campos:
 *   - type:    "project" | "office" | "workshop"
 *   - state:   nombre del estado tal como lo reconoce el mapa (para agrupar
 *              las obras al hacer click en un estado). Acepta acentos/may/min.
 *   - href:    enlace destino al hacer click en la tarjeta. Para proyectos,
 image: PROJECT_IMAGES.jtron,
  imageAlt: "Plataforma metálica de producción",
 *              su entry; para oficinas/talleres, lo que quieras (contacto, etc.)
 * ========================================================================== */

const PROJECT_MARKERS = [
  {
    lat: 20.61,
    lng: -100.41,
    type: "project",
    state: "Querétaro",
    title: "Nave industrial Bajío",
    kind: "Proyecto destacado",
    location: "Querétaro, Qro.",
    year: "2026",
    summary:
      "Desarrollo estructural y acompañamiento técnico para nave industrial con soluciones en acero.",
    href: "/proyecto/nave-industrial-bajio",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.538898818101988,
    lng: -100.43297348902101,
    type: "project",
    state: "Guanajuato",
    title: "Plataforma metálica de producción",
    kind: "Proyecto industrial",
    location: "Celaya, Gto.",
    year: "2025",
    summary:
      "Diseño y revisión de plataforma metálica para operación industrial.",
    href: "/proyecto/plataforma-metalica-produccion",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.5497,
    lng: -100.6792,
    type: "project",
    state: "Guanajuato",
    title: "Refuerzo estructural de bodega",
    kind: "Refuerzo estructural",
    location: "Apaseo el Grande, Gto.",
    year: "2025",
    summary:
      "Evaluación de condiciones existentes y propuesta de refuerzo estructural.",
    href: "/proyecto/refuerzo-estructural-bodega",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.3886,
    lng: -100.0003,
    type: "project",
    state: "Querétaro",
    title: "Cubierta de acero para patio de maniobras",
    kind: "Cubierta metálica",
    location: "San Juan del Río, Qro.",
    year: "2025",
    summary:
      "Propuesta estructural para cubierta metálica con criterios de montaje y operación.",
    href: "/proyecto/cubierta-acero-patio-maniobras",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.5931,
    lng: -100.392,
    type: "project",
    state: "Querétaro",
    title: "Dictamen estructural comercial",
    kind: "Dictamen",
    location: "Querétaro, Qro.",
    year: "2025",
    summary:
      "Inspección técnica y elaboración de dictamen para inmueble comercial.",
    href: "/proyecto/dictamen-estructural-comercial",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.6767,
    lng: -101.3563,
    type: "project",
    state: "Guanajuato",
    title: "Estructura de soporte para equipo",
    kind: "Soporte industrial",
    location: "Irapuato, Gto.",
    year: "2024",
    summary:
      "Diseño de estructura secundaria para soporte de equipo industrial.",
    href: "/proyecto/estructura-soporte-equipo",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.5446,
    lng: -100.4458,
    type: "project",
    state: "Querétaro",
    title: "Valuación de inmueble industrial",
    kind: "Valuación",
    location: "Corregidora, Qro.",
    year: "2024",
    summary:
      "Análisis de valor para inmueble industrial considerando estado, ubicación y uso.",
    href: "/proyecto/valuacion-inmueble-industrial",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.6122,
    lng: -100.2731,
    type: "project",
    state: "Querétaro",
    title: "Mezzanine para área de almacén",
    kind: "Mezzanine",
    location: "El Marqués, Qro.",
    year: "2024",
    summary:
      "Desarrollo estructural de mezzanine metálico para ampliar capacidad operativa.",
    href: "/proyecto/mezzanine-almacen",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 21.1219,
    lng: -101.686,
    type: "project",
    state: "Guanajuato",
    title: "Supervisión de montaje estructural",
    kind: "Supervisión",
    location: "León, Gto.",
    year: "2024",
    summary:
      "Seguimiento técnico de montaje de estructura metálica y control documental.",
    href: "/proyecto/supervision-montaje-estructural",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.9431,
    lng: -101.4282,
    type: "project",
    state: "Guanajuato",
    title: "Ampliación de nave de producción",
    kind: "Ampliación",
    location: "Silao, Gto.",
    year: "2023",
    summary:
      "Análisis y propuesta estructural para ampliación de área productiva.",
    href: "/proyecto/ampliacion-nave-produccion",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.5739,
    lng: -101.1957,
    type: "project",
    state: "Guanajuato",
    title: "Revisión de cimentación para equipo",
    kind: "Cimentación",
    location: "Salamanca, Gto.",
    year: "2023",
    summary:
      "Revisión técnica de cimentación para equipo industrial y anclajes.",
    href: "/proyecto/revision-cimentacion-equipo",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.57,
    lng: -100.376,
    type: "project",
    state: "Querétaro",
    title: "Escaleras y pasarelas industriales",
    kind: "Accesos industriales",
    location: "Querétaro, Qro.",
    year: "2023",
    summary:
      "Diseño de elementos de acceso metálico para operación y mantenimiento.",
    href: "/proyecto/escaleras-pasarelas-industriales",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.5047,
    lng: -100.1447,
    type: "project",
    state: "Querétaro",
    title: "Diagnóstico de nave existente",
    kind: "Diagnóstico",
    location: "Pedro Escobedo, Qro.",
    year: "2022",
    summary:
      "Levantamiento visual, registro de condiciones y diagnóstico técnico.",
    href: "/proyecto/diagnostico-nave-existente",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.625,
    lng: -100.395,
    type: "project",
    state: "Querétaro",
    title: "Estructura para anuncios exteriores",
    kind: "Estructura exterior",
    location: "Querétaro, Qro.",
    year: "2022",
    summary:
      "Diseño estructural para soporte exterior considerando acciones de viento.",
    href: "/proyecto/estructura-anuncios",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.56,
    lng: -100.425,
    type: "project",
    state: "Querétaro",
    title: "Evaluación estructural de casa habitación",
    kind: "Residencial",
    location: "Querétaro, Qro.",
    year: "2022",
    summary:
      "Revisión técnica de vivienda existente y recomendaciones de intervención.",
    href: "/proyecto/evaluacion-casa-habitacion",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.585,
    lng: -100.389,
    type: "office",
    state: "Querétaro",
    title: "Oficina central L+E",
    kind: "Oficina / sucursal",
    location: "Querétaro, Qro.",
    year: "Operación",
    summary:
      "Atención técnica, coordinación de proyectos, diseño estructural y valuación.",
    href: "/contacto",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 20.546,
    lng: -100.685,
    type: "workshop",
    state: "Guanajuato",
    title: "Taller de fabricación Bajío",
    kind: "Taller",
    location: "Apaseo el Grande, Gto.",
    year: "Operación",
    summary:
      "Apoyo para fabricación, preparación, revisión y logística de elementos metálicos.",
    href: "/contacto",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
  {
    lat: 19.4326,
    lng: -99.1332,
    type: "office",
    state: "Ciudad de México",
    title: "Sucursal de atención centro",
    kind: "Oficina / sucursal",
    location: "CDMX",
    year: "Cobertura",
    summary: "Punto de atención y coordinación para proyectos en zona centro.",
    href: "/contacto",
    image: PROJECT_IMAGES.jtron,
    imageAlt: "Plataforma metálica de producción",
  },
];

/* Posición en el mapa calculada automáticamente desde lat/lng. */
function spreadCloseMarkers(markers, minDistance = 11, radius = 9) {
  const placed = [];

  markers.forEach((marker) => {
    const base = latLngToMapPoint(marker.lat, marker.lng);
    let closeCount = 0;

    for (const other of placed) {
      const dx = base[0] - other.base[0];
      const dy = base[1] - other.base[1];
      const distance = Math.hypot(dx, dy);

      if (distance < minDistance) closeCount++;
    }

    if (closeCount === 0) {
      marker.coords = base;
    } else {
      const angle = closeCount * 1.75;
      const offsetRadius = radius + closeCount * 2;

      marker.coords = [
        base[0] + Math.cos(angle) * offsetRadius,
        base[1] + Math.sin(angle) * offsetRadius,
      ];
    }

    marker.rawCoords = base;
    placed.push(marker);
  });

  return markers;
}

const MARKER_COLORS = {
  project: "#8b0000",
  office: "#1368a9",
  workshop: "#00a878",
};

const TYPE_LABEL = {
  project: "Proyecto",
  office: "Oficina",
  workshop: "Taller",
};

/* Normaliza un nombre de estado (quita acentos, espacios y mayúsculas) para
   poder cruzar el nombre que reporta el mapa con el `state` del marcador,
   aunque difieran en acentos o capitalización. */
function normState(s = "") {
  return String(s)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z]/g, "");
}

/* ============================================================================
 * 3. INICIALIZACIÓN
 * ========================================================================== */

export default function initProjectsMap() {
  const maps = document.querySelectorAll("[data-projects-map]");
  if (!maps.length) return;
  maps.forEach((map) => initSingleProjectsMap(map));
}

function initSingleProjectsMap(map) {
  const vectorMap = map.querySelector("[data-projects-map-vector]");
  if (!vectorMap) return;

  if (vectorMap.dataset.vectorReady === "true") return;

  map.classList.remove("projects-map--missing-vector");

  const card = buildCardElement(map);
  const panel = buildPanelElement(map);

  const cardState = {
    pinned: false,
    marker: null,
    markerEl: null,
    closeTimer: null,
  };

  const panelState = {
    pinned: false,
    stateName: null,
    anchor: null,
    lastPoint: null,
    closeTimer: null,
  };

  try {
    vectorMap.innerHTML = "";

    const svg = buildNativeMexicoSvg(
      vectorMap,
      map,
      card,
      panel,
      cardState,
      panelState,
    );

    const statePaths = getStatePaths(svg);

    const preparedMarkers = distributeMarkersInsideStates(
      PROJECT_MARKERS,
      svg,
      statePaths,
    );

    renderSvgMarkers(
      svg,
      map,
      card,
      panel,
      preparedMarkers,
      cardState,
      panelState,
    );

    setupSvgZoom(map, vectorMap, svg, card, cardState, panel, panelState);

    vectorMap.dataset.vectorReady = "true";

    map.classList.remove("projects-map--missing-vector");
    map.classList.add("projects-map--ready");

    document.addEventListener("click", (event) => {
      const clickedMarker = event.target.closest(".projects-map__svg-marker");
      const clickedCard = card.contains(event.target);
      const clickedPanel = panel.contains(event.target);
      const clickedRegion = event.target.closest(".projects-map__svg-region");
      const clickedControls = event.target.closest(
        ".projects-map__zoom-controls",
      );

      if (
        clickedMarker ||
        clickedCard ||
        clickedPanel ||
        clickedRegion ||
        clickedControls
      ) {
        return;
      }

      cardState.pinned = false;
      panelState.pinned = false;

      closeCard(card, cardState, true);
      closePanel(panel, panelState, true);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;

      cardState.pinned = false;
      panelState.pinned = false;

      closeCard(card, cardState, true);
      closePanel(panel, panelState, true);
    });
  } catch (error) {
    map.classList.add("projects-map--missing-vector");
    console.error('No se pudo inicializar el mapa SVG "mx_en":', error);
  }
}
function buildMarkersLayer(map) {
  let layer = map.querySelector(".projects-map__markers-layer");

  if (!layer) {
    layer = document.createElement("div");
    layer.className = "projects-map__markers-layer";
    layer.setAttribute(
      "aria-label",
      "Puntos de proyectos, oficinas y talleres",
    );
    map.appendChild(layer);
  }

  return layer;
}

function renderCustomMarkers(map, vectorMap, layer, markers, card, panel) {
  layer.innerHTML = "";

  markers.forEach((marker, index) => {
    const button = document.createElement("button");

    button.type = "button";
    button.className = `projects-map__marker projects-map__marker--${marker.type}`;
    button.dataset.markerIndex = String(index);
    button.style.setProperty(
      "--marker-color",
      MARKER_COLORS[marker.type] || MARKER_COLORS.project,
    );

    button.setAttribute("aria-label", marker.title);
    button.title = `${marker.title} — ${marker.location}`;

    button.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      closePanel(panel);
      openCard(map, card, marker, button);
    });

    layer.appendChild(button);
  });

  positionCustomMarkers(map, vectorMap, layer, markers);
}

function positionCustomMarkers(map, vectorMap, layer, markers) {
  const svg = vectorMap.querySelector("svg");
  if (!svg || typeof svg.createSVGPoint !== "function") return;

  /*
    Tomamos el grupo real donde están los estados, porque jVectorMap aplica
    ahí el transform de zoom/pan. Así los pines se mueven junto con el mapa.
  */
  const regionElement = svg.querySelector(".jvectormap-region");
  const transformElement = regionElement?.parentNode || svg;

  const matrix = transformElement.getScreenCTM?.();
  if (!matrix) return;

  const hostRect = map.getBoundingClientRect();

  markers.forEach((marker, index) => {
    const markerElement = layer.querySelector(
      `.projects-map__marker[data-marker-index="${index}"]`,
    );

    if (!markerElement || !Array.isArray(marker.coords)) return;

    const point = svg.createSVGPoint();
    point.x = marker.coords[0];
    point.y = marker.coords[1];

    const screenPoint = point.matrixTransform(matrix);

    const left = screenPoint.x - hostRect.left;
    const top = screenPoint.y - hostRect.top;

    markerElement.style.left = `${left}px`;
    markerElement.style.top = `${top}px`;

    const isVisible =
      left >= -40 &&
      top >= -40 &&
      left <= hostRect.width + 40 &&
      top <= hostRect.height + 40;

    markerElement.toggleAttribute("hidden", !isVisible);
  });
}

function openStateFromRegion(
  map,
  svg,
  card,
  panel,
  cardState,
  panelState,
  regionPath,
  event = null,
) {
  if (!regionPath) return;

  event?.preventDefault?.();
  event?.stopPropagation?.();

  svg
    .querySelectorAll(".projects-map__svg-region.is-selected")
    .forEach((path) => path.classList.remove("is-selected"));

  regionPath.classList.add("is-selected");

  cardState.pinned = false;
  closeCard(card, cardState, true);
  clearActiveMarker(svg);

  openStatePanel(
    map,
    panel,
    regionPath.dataset.name || "",
    regionPath,
    panelState,
    {
      pinned: true,
    },
  );
}
function buildNativeMexicoSvg(
  container,
  map,
  card,
  panel,
  cardState,
  panelState,
) {
  const svgNS = "http://www.w3.org/2000/svg";

  const svg = document.createElementNS(svgNS, "svg");
  svg.classList.add("projects-map__svg");
  svg.setAttribute("viewBox", `0 0 ${MX_EN_MAP.width} ${MX_EN_MAP.height}`);
  svg.setAttribute("preserveAspectRatio", "xMidYMid meet");
  svg.setAttribute("role", "img");
  svg.setAttribute("aria-label", "Mapa de México con proyectos por estado");

  const viewportGroup = document.createElementNS(svgNS, "g");
  viewportGroup.classList.add("projects-map__svg-viewport");

  const regionsGroup = document.createElementNS(svgNS, "g");
  regionsGroup.classList.add("projects-map__svg-regions");

  Object.entries(MX_EN_MAP.paths).forEach(([code, region]) => {
    const path = document.createElementNS(svgNS, "path");

    path.setAttribute("d", region.path);
    path.setAttribute("data-code", code);
    path.setAttribute("data-name", region.name || "");
    path.setAttribute("tabindex", "0");
    path.setAttribute("role", "button");
    path.setAttribute(
      "aria-label",
      `Ver registros en ${region.name || "estado"}`,
    );

    path.classList.add("projects-map__svg-region");

    const title = document.createElementNS(svgNS, "title");
    title.textContent = region.name || "";
    path.appendChild(title);

    /*
      Fallback:
      El flujo principal ahora lo maneja setupSvgZoom() con pointerup.
      Este click queda por seguridad para navegadores raros / teclado externo.
    */
    path.addEventListener("click", (event) => {
      if (svg.__statePointerHandled) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }

      openStateFromRegion(
        map,
        svg,
        card,
        panel,
        cardState,
        panelState,
        path,
        event,
      );
    });

    path.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;

      openStateFromRegion(
        map,
        svg,
        card,
        panel,
        cardState,
        panelState,
        path,
        event,
      );
    });

    regionsGroup.appendChild(path);
  });

  const markersGroup = document.createElementNS(svgNS, "g");
  markersGroup.classList.add("projects-map__svg-markers");

  viewportGroup.appendChild(regionsGroup);
  viewportGroup.appendChild(markersGroup);

  svg.appendChild(viewportGroup);
  container.appendChild(svg);

  return svg;
}
function getStatePaths(svg) {
  const statePaths = new Map();

  svg.querySelectorAll(".projects-map__svg-region").forEach((path) => {
    const name = path.dataset.name || "";
    const key = normState(name);

    if (key) {
      statePaths.set(key, path);
    }
  });

  return statePaths;
}

/* ============================================================================
 * DISTRIBUCIÓN SEGURA DE PINES DENTRO DE CADA ESTADO
 * ----------------------------------------------------------------------------
 * Objetivos:
 * 1. Agrupar pines por estado.
 * 2. Colocarlos cerca del centro visual REAL del estado, no del bbox.
 * 3. Asegurar que el centro del pin siempre quede dentro del path del estado.
 * 4. Intentar que además haya margen interno suficiente para que el pin no
 *    quede pegado al borde.
 * 5. Si hay varios pines en el mismo estado, distribuirlos cerca del centro,
 *    evitando encimarlos.
 * ========================================================================== */

function distributeMarkersInsideStates(markers, svg, statePaths) {
  const grouped = new Map();

  markers.forEach((marker) => {
    const key = normState(marker.state);

    if (!grouped.has(key)) {
      grouped.set(key, []);
    }

    grouped.get(key).push(marker);
  });

  grouped.forEach((stateMarkers, stateKey) => {
    const path = statePaths.get(stateKey);

    if (!path) {
      stateMarkers.forEach((marker, index) => {
        marker.coords = getFallbackStatePoint(index, stateMarkers.length);
      });

      return;
    }

    const layout = buildStateInteriorLayout(svg, path);
    const usedPoints = [];

    stateMarkers.forEach((marker, index) => {
      const coords = getPointInsidePath(
        svg,
        path,
        index,
        stateMarkers.length,
        layout,
        usedPoints,
      );

      const safeCoords = forcePointInsidePath(svg, path, coords, layout);

      marker.coords = safeCoords;
      usedPoints.push(safeCoords);
    });
  });

  return markers;
}

function buildStateInteriorLayout(svg, path) {
  const bbox = path.getBBox();

  const samples = [];
  const point = svg.createSVGPoint();

  /*
    Más resolución = más precisión.
    42x42 sigue siendo ligero porque solo se calcula al iniciar el mapa.
  */
  const cols = 42;
  const rows = 42;

  for (let row = 0; row < rows; row++) {
    for (let col = 0; col < cols; col++) {
      const x = bbox.x + bbox.width * ((col + 0.5) / cols);
      const y = bbox.y + bbox.height * ((row + 0.5) / rows);

      point.x = x;
      point.y = y;

      if (isPointInsidePath(path, point, x, y)) {
        samples.push({ x, y });
      }
    }
  }

  /*
    Centro aproximado del área real:
    no usamos bbox center directamente porque puede caer fuera en estados
    irregulares, largos o cóncavos.
  */
  let centroidX = bbox.x + bbox.width / 2;
  let centroidY = bbox.y + bbox.height / 2;

  if (samples.length) {
    centroidX =
      samples.reduce((sum, sample) => sum + sample.x, 0) / samples.length;

    centroidY =
      samples.reduce((sum, sample) => sum + sample.y, 0) / samples.length;
  }

  /*
    Buscamos el punto interno más cercano al centroide.
    Así el punto base siempre está dentro del estado.
  */
  const center = getBestCenterSample(svg, path, samples, {
    x: centroidX,
    y: centroidY,
  });

  /*
    Margen interno para evitar bordes.
    En estados pequeños baja automáticamente para no imposibilitar el cálculo.
  */
  const minSide = Math.max(1, Math.min(bbox.width, bbox.height));

  const markerClearance = clampNumber(minSide * 0.12, 2.5, 8);
  const relaxedClearance = clampNumber(minSide * 0.07, 1.25, 5);

  /*
    Separación entre pines del mismo estado.
    También baja en estados pequeños.
  */
  const minMarkerDistance = clampNumber(minSide * 0.18, 8, 18);
  const relaxedMarkerDistance = clampNumber(minSide * 0.1, 5, 11);

  return {
    bbox,
    samples,
    center,
    markerClearance,
    relaxedClearance,
    minMarkerDistance,
    relaxedMarkerDistance,
  };
}

function getBestCenterSample(svg, path, samples, desiredCenter) {
  const point = svg.createSVGPoint();

  if (!samples.length) {
    return {
      x: desiredCenter.x,
      y: desiredCenter.y,
    };
  }

  const sorted = [...samples].sort((a, b) => {
    const da = distanceSq(a.x, a.y, desiredCenter.x, desiredCenter.y);
    const db = distanceSq(b.x, b.y, desiredCenter.x, desiredCenter.y);

    return da - db;
  });

  /*
    Primero intentamos que el centro no solo esté dentro,
    sino que tenga un pequeño radio interno también dentro del estado.
  */
  for (const sample of sorted.slice(0, 80)) {
    if (isCircleSafelyInsidePath(svg, path, point, sample.x, sample.y, 5, 18)) {
      return sample;
    }
  }

  return sorted[0];
}

function getPointInsidePath(svg, path, index, total, layout, usedPoints = []) {
  const point = svg.createSVGPoint();

  const { bbox, center } = layout;

  const goldenAngle = Math.PI * (3 - Math.sqrt(5));

  /*
    Mientras menos pines haya, más cerca del centro quedan.
    Si hay muchos, abrimos un poco la distribución, pero sin mandarlos a bordes.
  */
  const spreadRatio =
    total <= 1 ? 0 : total <= 3 ? 0.16 : total <= 7 ? 0.23 : 0.3;

  const maxRadiusX = Math.max(2, bbox.width * spreadRatio);
  const maxRadiusY = Math.max(2, bbox.height * spreadRatio);

  /*
    Fase 1:
    Punto seguro, con margen interno y separación normal entre pines.
  */
  const strict = findCandidateInsidePath({
    svg,
    path,
    point,
    index,
    total,
    center,
    maxRadiusX,
    maxRadiusY,
    usedPoints,
    minDistance: layout.minMarkerDistance,
    clearance: layout.markerClearance,
    goldenAngle,
    attempts: 520,
  });

  if (strict) return strict;

  /*
    Fase 2:
    Mismo objetivo, pero con margen y separación relajados.
    Esto ayuda en CDMX, Tlaxcala, Colima o estados muy angostos.
  */
  const relaxed = findCandidateInsidePath({
    svg,
    path,
    point,
    index,
    total,
    center,
    maxRadiusX: Math.max(maxRadiusX, bbox.width * 0.34),
    maxRadiusY: Math.max(maxRadiusY, bbox.height * 0.34),
    usedPoints,
    minDistance: layout.relaxedMarkerDistance,
    clearance: layout.relaxedClearance,
    goldenAngle,
    attempts: 720,
  });

  if (relaxed) return relaxed;

  /*
    Fase 3:
    Recorremos muestras internas reales del estado.
    Todas estas muestras ya pasaron isPointInFill().
  */
  const sampleCandidate = findBestSampleCandidate(
    layout.samples,
    center,
    usedPoints,
    layout.relaxedMarkerDistance,
  );

  if (sampleCandidate) {
    return [sampleCandidate.x, sampleCandidate.y];
  }

  /*
    Fase 4:
    Último fallback dentro del estado.
  */
  return [center.x, center.y];
}

function findCandidateInsidePath(options) {
  const {
    path,
    point,
    index,
    total,
    center,
    maxRadiusX,
    maxRadiusY,
    usedPoints,
    minDistance,
    clearance,
    goldenAngle,
    attempts,
  } = options;

  /*
    Para un solo pin, primero intenta exactamente el centro interno.
  */
  if (total <= 1) {
    const x = center.x;
    const y = center.y;

    if (
      isPointInsidePath(path, point, x, y) &&
      isCircleSafelyInsidePath(options.svg, path, point, x, y, clearance, 20)
    ) {
      return [x, y];
    }
  }

  for (let attempt = 0; attempt < attempts; attempt++) {
    /*
      Secuencia tipo sunflower:
      - Los primeros intentos quedan muy cerca del centro.
      - Los siguientes se abren gradualmente.
      - Evita que todos los puntos caigan sobre una línea.
    */
    const n = attempt * Math.max(total, 1) + index;
    const radiusFactor = Math.min(
      1,
      Math.sqrt((n + 0.5) / Math.max(attempts * 0.34, 1)),
    );

    const angle = n * goldenAngle;

    const x = center.x + Math.cos(angle) * maxRadiusX * radiusFactor;
    const y = center.y + Math.sin(angle) * maxRadiusY * radiusFactor;

    if (!isPointInsidePath(path, point, x, y)) continue;

    if (
      clearance > 0 &&
      !isCircleSafelyInsidePath(options.svg, path, point, x, y, clearance, 18)
    ) {
      continue;
    }

    if (!hasEnoughDistanceFromUsedPoints(x, y, usedPoints, minDistance)) {
      continue;
    }

    return [x, y];
  }

  return null;
}

function findBestSampleCandidate(samples, center, usedPoints, minDistance) {
  if (!samples.length) return null;

  const sorted = [...samples].sort((a, b) => {
    const da = distanceSq(a.x, a.y, center.x, center.y);
    const db = distanceSq(b.x, b.y, center.x, center.y);

    return da - db;
  });

  for (const sample of sorted) {
    if (
      hasEnoughDistanceFromUsedPoints(
        sample.x,
        sample.y,
        usedPoints,
        minDistance,
      )
    ) {
      return sample;
    }
  }

  return sorted[0];
}

function forcePointInsidePath(svg, path, coords, layout) {
  const point = svg.createSVGPoint();
  const [x, y] = coords || [];

  if (Number.isFinite(x) && Number.isFinite(y)) {
    if (isPointInsidePath(path, point, x, y)) {
      return [x, y];
    }
  }

  if (
    layout?.center &&
    isPointInsidePath(path, point, layout.center.x, layout.center.y)
  ) {
    return [layout.center.x, layout.center.y];
  }

  if (layout?.samples?.length) {
    const sample = layout.samples[0];

    return [sample.x, sample.y];
  }

  /*
    Este fallback casi nunca debería usarse.
    Solo entra si el navegador no puede validar el path.
  */
  const bbox = path.getBBox();

  return [bbox.x + bbox.width / 2, bbox.y + bbox.height / 2];
}

function isPointInsidePath(path, point, x, y) {
  if (!path || typeof path.isPointInFill !== "function") {
    return true;
  }

  point.x = x;
  point.y = y;

  return path.isPointInFill(point);
}

function isCircleSafelyInsidePath(
  svg,
  path,
  point,
  x,
  y,
  radius,
  samples = 16,
) {
  if (!isPointInsidePath(path, point, x, y)) return false;

  /*
    Revisamos varios radios, no solo el borde exterior.
    Esto evita aceptar puntos donde solo el centro está dentro,
    pero el pin queda visualmente mordiendo el borde del estado.
  */
  const radii = [radius * 0.5, radius];

  for (const r of radii) {
    for (let i = 0; i < samples; i++) {
      const angle = (Math.PI * 2 * i) / samples;

      const px = x + Math.cos(angle) * r;
      const py = y + Math.sin(angle) * r;

      if (!isPointInsidePath(path, point, px, py)) {
        return false;
      }
    }
  }

  return true;
}

function hasEnoughDistanceFromUsedPoints(x, y, usedPoints, minDistance) {
  if (!usedPoints.length) return true;

  const minDistanceSq = minDistance * minDistance;

  return usedPoints.every(([usedX, usedY]) => {
    return distanceSq(x, y, usedX, usedY) >= minDistanceSq;
  });
}

function distanceSq(x1, y1, x2, y2) {
  const dx = x1 - x2;
  const dy = y1 - y2;

  return dx * dx + dy * dy;
}

function clampNumber(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function getFallbackStatePoint(index, total) {
  const angle = index * Math.PI * (3 - Math.sqrt(5));
  const radius = 18 + total * 2;

  return [
    MX_EN_MAP.width / 2 + Math.cos(angle) * radius,
    MX_EN_MAP.height / 2 + Math.sin(angle) * radius,
  ];
}

function renderSvgMarkers(
  svg,
  map,
  card,
  panel,
  markers,
  cardState,
  panelState,
) {
  const svgNS = "http://www.w3.org/2000/svg";
  const markersGroup = svg.querySelector(".projects-map__svg-markers");

  if (!markersGroup) return;

  markersGroup.innerHTML = "";

  markers.forEach((marker, index) => {
    if (!Array.isArray(marker.coords)) return;

    const [x, y] = marker.coords;

    const circle = document.createElementNS(svgNS, "circle");

    circle.classList.add(
      "projects-map__svg-marker",
      `projects-map__svg-marker--${marker.type}`,
    );

    circle.setAttribute("cx", String(x));
    circle.setAttribute("cy", String(y));
    circle.setAttribute("tabindex", "0");
    circle.setAttribute("role", "button");
    circle.setAttribute("aria-label", marker.title);
    circle.setAttribute("data-marker-index", String(index));

    /*
      NO pongas r fijo aquí.
      El radio se calcula en syncSvgMarkerSizes()
      según el zoom actual.
    */
    circle.dataset.baseRadius = "8";
    circle.dataset.activeRadius = "11";

    circle.style.setProperty(
      "--marker-color",
      MARKER_COLORS[marker.type] || MARKER_COLORS.project,
    );

    const title = document.createElementNS(svgNS, "title");
    title.textContent = `${marker.title} — ${marker.location}`;
    circle.appendChild(title);

    circle.addEventListener("mouseenter", () => {
      if (cardState.pinned) return;

      setActiveMarker(svg, circle);

      panelState.pinned = false;
      closePanel(panel, panelState, true);

      openCard(map, card, marker, circle, cardState, {
        pinned: false,
      });
    });

    circle.addEventListener("mouseleave", () => {
      if (cardState.pinned) return;

      circle.classList.remove("is-active");
      syncSvgMarkerSizes(svg);
      scheduleCardClose(card, cardState);
    });

    circle.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      panelState.pinned = false;
      closePanel(panel, panelState, true);

      setActiveMarker(svg, circle);

      openCard(map, card, marker, circle, cardState, {
        pinned: true,
      });
    });

    circle.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;

      event.preventDefault();

      panelState.pinned = false;
      closePanel(panel, panelState, true);

      setActiveMarker(svg, circle);

      openCard(map, card, marker, circle, cardState, {
        pinned: true,
      });
    });

    markersGroup.appendChild(circle);
  });

  syncSvgMarkerSizes(svg);
}
function setActiveMarker(svg, markerEl) {
  svg
    .querySelectorAll(".projects-map__svg-marker.is-active")
    .forEach((item) => {
      item.classList.remove("is-active");
    });

  markerEl.classList.add("is-active");
  syncSvgMarkerSizes(svg);
}

function clearActiveMarker(svg) {
  svg
    .querySelectorAll(".projects-map__svg-marker.is-active")
    .forEach((item) => {
      item.classList.remove("is-active");
    });

  syncSvgMarkerSizes(svg);
}

function syncSvgMarkerSizes(svg, zoomState = null) {
  const state = zoomState || svg.__projectsMapZoomState || null;
  const currentViewBoxWidth = Number(state?.width) || MX_EN_MAP.width;

  /*
    Si haces zoom in:
    - viewBox width baja.
    - El SVG visualmente crece.
    - Entonces el radio debe bajar proporcionalmente.
  */
  const zoomScale = currentViewBoxWidth / MX_EN_MAP.width;

  svg.querySelectorAll(".projects-map__svg-marker").forEach((marker) => {
    const baseRadius = Number(marker.dataset.baseRadius) || 8;
    const activeRadius = Number(marker.dataset.activeRadius) || 11;

    const isActive =
      marker.classList.contains("is-active") ||
      marker.matches(":hover") ||
      marker.matches(":focus");

    const radius = isActive ? activeRadius : baseRadius;

    marker.setAttribute("r", String(radius * zoomScale));
    marker.setAttribute("stroke-width", String(3 * zoomScale));
  });
}

function setupSvgZoom(map, container, svg, card, cardState, panel, panelState) {
  const defaultViewBox = {
    x: 0,
    y: 0,
    width: MX_EN_MAP.width,
    height: MX_EN_MAP.height,
  };

  const state = {
    ...defaultViewBox,

    minWidth: MX_EN_MAP.width / 7,
    minHeight: MX_EN_MAP.height / 7,
    maxWidth: MX_EN_MAP.width,
    maxHeight: MX_EN_MAP.height,

    dragging: false,
    didDrag: false,

    dragStartX: 0,
    dragStartY: 0,
    startBox: null,

    pointerDownRegion: null,
  };

  const DRAG_THRESHOLD = 6;

  cardState.zoomState = state;
  panelState.zoomState = state;
  svg.__projectsMapZoomState = state;

  const applyViewBox = () => {
    clampViewBox(state);

    svg.setAttribute(
      "viewBox",
      `${state.x} ${state.y} ${state.width} ${state.height}`,
    );

    syncSvgMarkerSizes(svg, state);

    requestAnimationFrame(() => {
      repositionOpenCard(map, card, cardState);
      repositionOpenPanel(map, panel, panelState);
    });
  };

  const zoomAt = (clientX, clientY, factor) => {
    const rect = svg.getBoundingClientRect();

    if (!rect.width || !rect.height) return;

    const px = (clientX - rect.left) / rect.width;
    const py = (clientY - rect.top) / rect.height;

    const focusX = state.x + state.width * px;
    const focusY = state.y + state.height * py;

    const nextWidth = clamp(
      state.width * factor,
      state.minWidth,
      state.maxWidth,
    );

    const nextHeight = clamp(
      state.height * factor,
      state.minHeight,
      state.maxHeight,
    );

    state.x = focusX - nextWidth * px;
    state.y = focusY - nextHeight * py;
    state.width = nextWidth;
    state.height = nextHeight;

    applyViewBox();
  };

  const zoomCenter = (factor) => {
    const rect = svg.getBoundingClientRect();

    zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor);
  };

  const resetZoom = () => {
    state.x = defaultViewBox.x;
    state.y = defaultViewBox.y;
    state.width = defaultViewBox.width;
    state.height = defaultViewBox.height;

    applyViewBox();
  };

  container.addEventListener(
    "wheel",
    (event) => {
      event.preventDefault();

      const factor = event.deltaY > 0 ? 1.14 : 0.86;
      zoomAt(event.clientX, event.clientY, factor);
    },
    { passive: false },
  );

  svg.addEventListener("pointerdown", (event) => {
    if (event.button != null && event.button !== 0) return;

    if (event.target.closest?.(".projects-map__svg-marker")) return;
    if (event.target.closest?.(".projects-map__zoom-controls")) return;
    if (event.target.closest?.(".projects-map__card")) return;
    if (event.target.closest?.(".projects-map__panel")) return;

    state.dragging = true;
    state.didDrag = false;

    state.dragStartX = event.clientX;
    state.dragStartY = event.clientY;

    state.pointerDownRegion =
      event.target.closest?.(".projects-map__svg-region") || null;

    state.startBox = {
      x: state.x,
      y: state.y,
      width: state.width,
      height: state.height,
    };

    svg.classList.add("is-dragging");

    try {
      svg.setPointerCapture?.(event.pointerId);
    } catch (_) {
      /*
        Algunos navegadores pueden lanzar error si el puntero ya no está activo.
      */
    }
  });

  svg.addEventListener("pointermove", (event) => {
    if (!state.dragging || !state.startBox) return;

    const movedX = event.clientX - state.dragStartX;
    const movedY = event.clientY - state.dragStartY;

    if (Math.hypot(movedX, movedY) > DRAG_THRESHOLD) {
      state.didDrag = true;
    }

    /*
      Si todavía no pasó el umbral, lo tratamos como tap/click.
      Esto evita que un micro-movimiento en desktop cancele el click.
    */
    if (!state.didDrag) return;

    event.preventDefault();

    const rect = svg.getBoundingClientRect();

    if (!rect.width || !rect.height) return;

    const dx = (movedX / rect.width) * state.startBox.width;
    const dy = (movedY / rect.height) * state.startBox.height;

    state.x = state.startBox.x - dx;
    state.y = state.startBox.y - dy;

    applyViewBox();
  });

  const endDrag = (event, shouldOpenOnTap = false) => {
    if (!state.dragging) return;

    const regionToOpen =
      shouldOpenOnTap && !state.didDrag ? state.pointerDownRegion : null;

    state.dragging = false;
    state.startBox = null;
    state.pointerDownRegion = null;

    svg.classList.remove("is-dragging");

    try {
      svg.releasePointerCapture?.(event.pointerId);
    } catch (_) {
      /*
        Seguro contra errores de releasePointerCapture.
      */
    }

    if (regionToOpen?.isConnected) {
      /*
        Evita que el click sintético posterior vuelva a disparar el fallback.
      */
      svg.__statePointerHandled = true;
      window.clearTimeout(svg.__statePointerHandledTimer);

      openStateFromRegion(
        map,
        svg,
        card,
        panel,
        cardState,
        panelState,
        regionToOpen,
        event,
      );

      svg.__statePointerHandledTimer = window.setTimeout(() => {
        svg.__statePointerHandled = false;
      }, 450);
    }
  };

  svg.addEventListener("pointerup", (event) => {
    endDrag(event, true);
  });

  svg.addEventListener("pointercancel", (event) => {
    endDrag(event, false);
  });

  svg.addEventListener("pointerleave", (event) => {
    endDrag(event, false);
  });

  buildZoomControls(container, {
    zoomIn: () => zoomCenter(0.82),
    zoomOut: () => zoomCenter(1.22),
    reset: resetZoom,
  });

  applyViewBox();
}
function buildZoomControls(container, actions) {
  const controls = document.createElement("div");

  controls.className = "projects-map__zoom-controls";
  controls.setAttribute("aria-label", "Controles de zoom del mapa");

  controls.innerHTML = `
    <button type="button" class="projects-map__zoom-button" data-zoom-in aria-label="Acercar">+</button>
    <button type="button" class="projects-map__zoom-button" data-zoom-out aria-label="Alejar">−</button>
    <button type="button" class="projects-map__zoom-button projects-map__zoom-button--reset" data-zoom-reset aria-label="Restablecer zoom">↺</button>
  `;

  controls
    .querySelector("[data-zoom-in]")
    .addEventListener("click", actions.zoomIn);

  controls
    .querySelector("[data-zoom-out]")
    .addEventListener("click", actions.zoomOut);

  controls
    .querySelector("[data-zoom-reset]")
    .addEventListener("click", actions.reset);

  container.appendChild(controls);
}

function clampViewBox(state) {
  state.width = clamp(state.width, state.minWidth, state.maxWidth);
  state.height = clamp(state.height, state.minHeight, state.maxHeight);

  state.x = clamp(state.x, 0, MX_EN_MAP.width - state.width);
  state.y = clamp(state.y, 0, MX_EN_MAP.height - state.height);
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

/* ============================================================================
 * 4. TARJETA DE INFORMACIÓN (pin -> click)
 * ========================================================================== */

function buildCardElement(map) {
  const card = document.createElement("div");

  card.className = "projects-map__card";
  card.setAttribute("hidden", "");

  card.innerHTML = `
    <button type="button" class="projects-map__card-close" aria-label="Cerrar">×</button>
    <div class="projects-map__card-body"></div>
  `;

  card.addEventListener("mouseenter", () => {
    if (card._cardState) {
      clearTimeout(card._cardState.closeTimer);
    }
  });

  card.addEventListener("mouseleave", () => {
    if (!card._cardState || card._cardState.pinned) return;
    scheduleCardClose(card, card._cardState);
  });

  card
    .querySelector(".projects-map__card-close")
    .addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (card._cardState) {
        card._cardState.pinned = false;
      }

      closeCard(card, card._cardState, true);
    });

  map.appendChild(card);

  return card;
}

function getMarkerImage(marker = {}) {
  return marker.image || marker.photo || marker.thumbnail || marker.cover || "";
}

function renderMarkerImage(marker, className = "projects-map__card-image") {
  const src = getMarkerImage(marker);
  if (!src) return "";

  const alt = marker.imageAlt || marker.title || "Foto de la obra";

  return `
    <figure class="${className}">
      <img
        src="${escapeAttr(src)}"
        alt="${escapeAttr(alt)}"
        loading="lazy"
        decoding="async"
      >
    </figure>
  `;
}

function openCard(map, card, marker, markerEl, cardState, options = {}) {
  const { pinned = false } = options;

  clearTimeout(cardState.closeTimer);

  cardState.pinned = pinned;
  cardState.marker = marker;
  cardState.markerEl = markerEl;

  card._cardState = cardState;

  const body = card.querySelector(".projects-map__card-body");

  const cta =
    marker.type === "project"
      ? "Ver proyecto"
      : marker.type === "workshop"
        ? "Ver taller"
        : "Ver oficina";

  const href = marker.href ? String(marker.href) : "";

  body.innerHTML = `
  ${renderMarkerImage(marker, "projects-map__card-image")}

  <span class="projects-map__card-type projects-map__card-type--${marker.type}">
    ${escapeHtml(TYPE_LABEL[marker.type] || "Punto")}
  </span>

  <h4>${escapeHtml(marker.title)}</h4>

  <div class="projects-map__card-meta">
    <span>${escapeHtml(marker.kind)}</span>
    <span>${escapeHtml(marker.location)}</span>
    <span>${escapeHtml(marker.year)}</span>
  </div>

  <p>${escapeHtml(marker.summary)}</p>

  ${
    href
      ? `<a class="projects-map__card-cta" href="${escapeAttr(href)}">
          ${escapeHtml(cta)} <span aria-hidden="true">→</span>
        </a>`
      : ""
  }
`;

  card.classList.toggle("is-pinned", pinned);
  card.classList.toggle("is-linkable", Boolean(href));

  /*
    Click en la tarjeta completa, excepto en cerrar o en links internos.
  */
  card.onclick = (event) => {
    if (!href) return;
    if (event.target.closest(".projects-map__card-close")) return;
    if (event.target.closest("a")) return;

    window.location.href = href;
  };

  positionCardNearMarker(map, card, markerEl);

  card.removeAttribute("hidden");

  requestAnimationFrame(() => {
    card.classList.add("is-visible");
  });
}

function positionCardNearMarker(map, card, markerEl) {
  if (!markerEl) return;

  const hostRect = map.getBoundingClientRect();
  const markerRect = markerEl.getBoundingClientRect();

  const x = markerRect.left + markerRect.width / 2 - hostRect.left;
  const y = markerRect.top + markerRect.height / 2 - hostRect.top;

  card.style.left = `${x}px`;
  card.style.top = `${y}px`;

  card.classList.remove("is-left", "is-below");

  const estimatedWidth = 360;
  const estimatedHeight = 210;

  if (x + estimatedWidth + 32 > hostRect.width) {
    card.classList.add("is-left");
  }

  if (y - estimatedHeight / 2 < 16) {
    card.classList.add("is-below");
  }
}

function repositionOpenCard(map, card, cardState) {
  if (!cardState?.markerEl) return;
  if (card.hasAttribute("hidden")) return;

  positionCardNearMarker(map, card, cardState.markerEl);
}

function scheduleCardClose(card, cardState) {
  clearTimeout(cardState.closeTimer);

  cardState.closeTimer = window.setTimeout(() => {
    if (cardState.pinned) return;
    closeCard(card, cardState, false);
  }, 160);
}

function closeCard(card, cardState, force = false) {
  if (cardState?.pinned && !force) return;

  if (cardState) {
    clearTimeout(cardState.closeTimer);

    const svg = card
      .closest("[data-projects-map]")
      ?.querySelector(".projects-map__svg");

    if (svg) {
      clearActiveMarker(svg);
    }

    cardState.marker = null;
    cardState.markerEl = null;
  }

  card.classList.remove("is-visible", "is-pinned", "is-left", "is-below");
  card.onclick = null;

  window.setTimeout(() => {
    if (!card.classList.contains("is-visible")) {
      card.setAttribute("hidden", "");
    }
  }, 180);
}

function escapeAttr(value = "") {
  return escapeHtml(value).replace(/"/g, "&quot;");
}

/* ============================================================================
 * 5. PANEL DE OBRAS POR ESTADO (estado -> click)
 * ========================================================================== */

/* ============================================================================
 * 5. TARJETA FLOTANTE DE OBRAS POR ESTADO
 * ========================================================================== */

/* ============================================================================
 * 5. TARJETA FLOTANTE DE OBRAS / OFICINAS / TALLERES POR ESTADO
 * ========================================================================== */

function buildPanelElement(map) {
  const panel = document.createElement("aside");
  const canvas = map.querySelector(".projects-map__canvas") || map;

  panel.className = "projects-map__panel";
  panel.setAttribute("hidden", "");
  panel.setAttribute("aria-live", "polite");

  panel.innerHTML = `
    <header class="projects-map__panel-head">
      <div>
        <p class="projects-map__panel-eyebrow">Presencia en el estado</p>
        <h3 class="projects-map__panel-title"></h3>
        <p class="projects-map__panel-count"></p>
      </div>

      <button type="button" class="projects-map__panel-close" aria-label="Cerrar">×</button>
    </header>

    <div class="projects-map__panel-scroll">
      <div class="projects-map__state-cards"></div>

      <p class="projects-map__panel-empty" hidden>
        Sin proyectos, oficinas o talleres registrados en este estado.
      </p>
    </div>
  `;

  panel
    .querySelector(".projects-map__panel-close")
    .addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (panel._panelState) {
        panel._panelState.pinned = false;
      }

      closePanel(panel, panel._panelState, true);
    });

  /*
    El panel debe vivir dentro del canvas.
    Así queda encima del mapa, pero lo posicionamos SIEMPRE dentro del área visible.
  */
  canvas.appendChild(panel);

  return panel;
}

function openStatePanel(
  map,
  panel,
  stateName,
  anchor,
  panelState,
  options = {},
) {
  const { pinned = true } = options;
  const key = normState(stateName);

  clearTimeout(panelState.closeTimer);

  panelState.pinned = pinned;
  panelState.stateName = stateName || "Estado";
  panelState.anchor = anchor || null;

  panel._panelState = panelState;

  const entries = PROJECT_MARKERS.filter((marker) => {
    return normState(marker.state) === key;
  }).sort((a, b) => {
    const order = {
      project: 1,
      office: 2,
      workshop: 3,
    };

    const typeA = order[a.type] || 99;
    const typeB = order[b.type] || 99;

    if (typeA !== typeB) return typeA - typeB;

    return String(b.year).localeCompare(String(a.year));
  });

  const title = panel.querySelector(".projects-map__panel-title");
  const count = panel.querySelector(".projects-map__panel-count");
  const cards = panel.querySelector(".projects-map__state-cards");
  const empty = panel.querySelector(".projects-map__panel-empty");

  title.textContent = stateName || "Estado";

  count.textContent = entries.length
    ? `${entries.length} ${
        entries.length === 1 ? "registro encontrado" : "registros encontrados"
      }`
    : "Sin registros";

  cards.innerHTML = "";

  if (!entries.length) {
    empty.removeAttribute("hidden");
  } else {
    empty.setAttribute("hidden", "");

    entries.forEach((marker) => {
      const href = marker.href ? String(marker.href) : "";
      const item = document.createElement(href ? "a" : "article");

      item.className = `projects-map__state-card projects-map__state-card--${marker.type}`;

      if (href) {
        item.href = href;
        item.setAttribute(
          "aria-label",
          `${marker.type === "project" ? "Ver proyecto" : "Ver información"} ${marker.title}`,
        );
      }

      const cta =
        marker.type === "project"
          ? "Ver proyecto"
          : marker.type === "office"
            ? "Ver oficina"
            : marker.type === "workshop"
              ? "Ver taller"
              : "Ver más";

      item.innerHTML = `
  ${renderMarkerImage(marker, "projects-map__state-card-image")}

  <div class="projects-map__state-card-content">
    <span class="projects-map__card-type projects-map__card-type--${marker.type}">
      ${escapeHtml(TYPE_LABEL[marker.type] || "Punto")}
    </span>

    <h4>${escapeHtml(marker.title)}</h4>

    <div class="projects-map__card-meta">
      <span>${escapeHtml(marker.kind)}</span>
      <span>${escapeHtml(marker.location)}</span>
      <span>${escapeHtml(marker.year)}</span>
    </div>

    <p>${escapeHtml(marker.summary)}</p>

    ${
      href
        ? `<span class="projects-map__card-cta">
            ${escapeHtml(cta)}
            <span aria-hidden="true">→</span>
          </span>`
        : ""
    }
  </div>
`;

      cards.appendChild(item);
    });
  }

  panel.classList.remove("is-visible", "is-left", "is-below");
  panel.classList.toggle("is-pinned", pinned);
  panel.removeAttribute("hidden");

  /*
    IMPORTANTE:
    Primero quitamos hidden para poder medir el tamaño real del panel.
    Luego calculamos una posición segura dentro del canvas.
  */
  positionPanelNearAnchor(map, panel, anchor);

  requestAnimationFrame(() => {
    panel.classList.add("is-visible");

    const scroll = panel.querySelector(".projects-map__panel-scroll");
    if (scroll) scroll.scrollTop = 0;
  });
}

function positionPanelNearAnchor(map, panel, anchor) {
  const canvas = map.querySelector(".projects-map__canvas") || map;
  const canvasRect = canvas.getBoundingClientRect();

  if (!canvasRect.width || !canvasRect.height) return;

  const safeGap = 14;
  const anchorGap = 18;

  /*
    Como el panel ya no usa translate(-100%, -50%) para posicionarse,
    aquí calculamos left/top reales y seguros.
  */
  const panelRect = panel.getBoundingClientRect();

  const panelWidth = Math.min(
    panelRect.width || 440,
    Math.max(260, canvasRect.width - safeGap * 2),
  );

  const panelHeight = Math.min(
    panelRect.height || 360,
    Math.max(220, canvasRect.height - safeGap * 2),
  );

  let x = canvasRect.width - panelWidth - safeGap;
  let y = safeGap;

  if (anchor && typeof anchor.getBoundingClientRect === "function") {
    const anchorRect = anchor.getBoundingClientRect();

    const anchorX = anchorRect.left + anchorRect.width / 2 - canvasRect.left;
    const anchorY = anchorRect.top + anchorRect.height / 2 - canvasRect.top;

    if (Number.isFinite(anchorX) && Number.isFinite(anchorY)) {
      /*
        1. Intenta abrir a la derecha del estado.
        2. Si no cabe, abre a la izquierda.
        3. Si tampoco cabe, lo centra con respecto al estado.
      */
      x = anchorX + anchorGap;

      if (x + panelWidth > canvasRect.width - safeGap) {
        x = anchorX - panelWidth - anchorGap;
        panel.classList.add("is-left");
      }

      if (x < safeGap) {
        x = anchorX - panelWidth / 2;
      }

      y = anchorY - panelHeight / 2;
    }
  }

  const maxX = Math.max(safeGap, canvasRect.width - panelWidth - safeGap);
  const maxY = Math.max(safeGap, canvasRect.height - panelHeight - safeGap);

  x = clampNumber(x, safeGap, maxX);
  y = clampNumber(y, safeGap, maxY);

  if (y <= safeGap + 4) {
    panel.classList.add("is-below");
  }

  panel.style.left = `${Math.round(x)}px`;
  panel.style.top = `${Math.round(y)}px`;
}

function repositionOpenPanel(map, panel, panelState) {
  if (!panelState?.anchor) return;
  if (panel.hasAttribute("hidden")) return;

  positionPanelNearAnchor(map, panel, panelState.anchor);
}

function schedulePanelClose(panel, panelState) {
  clearTimeout(panelState.closeTimer);

  panelState.closeTimer = window.setTimeout(() => {
    if (panelState.pinned) return;
    closePanel(panel, panelState, false);
  }, 180);
}

function closePanel(panel, panelState = panel._panelState, force = false) {
  if (panelState?.pinned && !force) return;

  if (panelState) {
    clearTimeout(panelState.closeTimer);
    panelState.stateName = null;
    panelState.anchor = null;
  }

  const svg = panel
    .closest("[data-projects-map]")
    ?.querySelector(".projects-map__svg");

  if (svg) {
    svg
      .querySelectorAll(".projects-map__svg-region.is-selected")
      .forEach((item) => item.classList.remove("is-selected"));
  }

  panel.classList.remove("is-visible", "is-pinned", "is-left", "is-below");

  window.setTimeout(() => {
    if (!panel.classList.contains("is-visible")) {
      panel.setAttribute("hidden", "");
    }
  }, 180);
}

/* ============================================================================
 * 6. UTILIDADES
 * ========================================================================== */

function buildMarkerTooltip(marker) {
  return `
    <div class="projects-map-tip">
      <h4>${escapeHtml(marker.title)}</h4>
      <div class="projects-map-tip__meta">
        <span>${escapeHtml(marker.kind)}</span>
        <span>${escapeHtml(marker.location)}</span>
        <span>${escapeHtml(marker.year)}</span>
      </div>
      <p>${escapeHtml(marker.summary)}</p>
      <p class="projects-map-tip__hint">Click para fijar y abrir</p>
    </div>
  `;
}

function escapeHtml(value = "") {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

/* Exporto la proyección por si la necesitas en otros módulos / pruebas. */
/* Exporto la conversión por si la necesitas en otros módulos / pruebas. */
export { latLngToMapPoint, MAP_CONTROL_POINTS };

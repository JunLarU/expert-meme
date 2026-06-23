import $ from "jquery";

window.$ = $;
window.jQuery = $;

require("jvectormap-next/jquery-jvectormap.css");

const jVectorMapFactory = require("jvectormap-next");
const factory = jVectorMapFactory.default || jVectorMapFactory;

if (typeof factory === "function") {
  factory($);
}

if (!$.fn.vectorMap) {
  console.error(
    "jVectorMap no se registró en $.fn.vectorMap. Revisa la carga de jvectormap-next."
  );
}

export default $;
export default function initFooter() {
  const yearNodes = document.querySelectorAll("[data-current-year]");
  if (!yearNodes.length) return;

  const currentYear = new Date().getFullYear();

  yearNodes.forEach((node) => {
    node.textContent = currentYear;
  });
}
const ROOT_SELECTOR = "[data-admin-api-tokens]";
const CREATE_FORM_SELECTOR = "[data-api-token-create-form]";
const RESULT_SELECTOR = "[data-api-token-result]";
const TOKEN_OUTPUT_SELECTOR = "[data-api-token-plain]";
const COPY_BUTTON_SELECTOR = "[data-api-token-copy]";

export default function initAdminApiTokens(root = document) {
  const page = root.querySelector(ROOT_SELECTOR);

  if (!page || page.dataset.adminApiTokensBound === "true") return;

  page.dataset.adminApiTokensBound = "true";

  bindConfirmations(page);
  bindAjaxSuccess(page);
  bindCopy(page);
}

function bindConfirmations(page) {
  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;

      if (!(form instanceof HTMLFormElement)) return;
      if (!page.contains(form)) return;

      const message = form.dataset.apiTokenConfirm;

      if (!message) return;

      const accepted = window.confirm(message);

      if (!accepted) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    },
    true,
  );
}

function bindAjaxSuccess(page) {
  page.addEventListener("ajax-form:success", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) return;

    if (form.matches(CREATE_FORM_SELECTOR)) {
      renderCreatedToken(form, event.detail?.data || {});
    }
  });
}

function renderCreatedToken(form, data) {
  const resultBox = form.querySelector(RESULT_SELECTOR);
  const tokenOutput = form.querySelector(TOKEN_OUTPUT_SELECTOR);

  const plainTextToken = String(data?.plainTextToken || "").trim();

  if (!resultBox || !tokenOutput || !plainTextToken) return;

  tokenOutput.textContent = plainTextToken;
  resultBox.hidden = false;
  resultBox.classList.add("is-visible");

  tokenOutput.focus?.();
}

function bindCopy(page) {
  page.addEventListener("click", async (event) => {
    const button = event.target.closest(COPY_BUTTON_SELECTOR);

    if (!button || !page.contains(button)) return;

    const resultBox = button.closest(RESULT_SELECTOR);
    const tokenOutput = resultBox?.querySelector(TOKEN_OUTPUT_SELECTOR);
    const token = tokenOutput?.textContent?.trim() || "";

    if (!token) return;

    const originalText = button.textContent;

    try {
      await copyToClipboard(token);
      button.textContent = "Copiado";
      button.classList.add("is-copied");
    } catch (error) {
      console.error("No se pudo copiar el token:", error);
      button.textContent = "Copia manualmente";
      button.classList.add("has-error");
    }

    window.setTimeout(() => {
      button.textContent = originalText;
      button.classList.remove("is-copied", "has-error");
    }, 1800);
  });
}

async function copyToClipboard(text) {
  if (navigator.clipboard?.writeText && window.isSecureContext) {
    await navigator.clipboard.writeText(text);
    return;
  }

  const textarea = document.createElement("textarea");
  textarea.value = text;
  textarea.setAttribute("readonly", "");
  textarea.style.position = "fixed";
  textarea.style.left = "-9999px";
  textarea.style.top = "0";

  document.body.appendChild(textarea);
  textarea.select();

  const ok = document.execCommand("copy");

  document.body.removeChild(textarea);

  if (!ok) {
    throw new Error("copy_failed");
  }
}
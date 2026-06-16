const DEFAULT_SELECTOR = 'form[data-ajax-form], form[data-form-handler="ajax"]';

const FIELD_SELECTOR = `
  input[name]:not([type="submit"]):not([type="button"]):not([type="reset"]),
  select[name],
  textarea[name]
`;

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
const DEFAULT_OPTIONS = {
  selector: DEFAULT_SELECTOR,
  validation: "html",
  validationProfiles: {},
  csrf: false,
  csrfField: "_token",
  sendAs: "auto", // auto | json | formdata | urlencoded
  method: "POST",
  resetOnSuccess: true,
  credentials: "same-origin",
  loadingText: "Enviando...",
  defaultSubmitText: "Enviar",

  messages: {
    loading: "Enviando información...",
    invalidForm: "Revisa los campos marcados en rojo.",
    invalidServerResponse: "La respuesta del servidor no fue válida.",
    genericError: "Ocurrió un error al enviar el formulario.",
    genericSuccess: "Formulario enviado correctamente.",
    missingCsrf: "No se encontró el token CSRF del formulario.",

    required: "Completa {label}.",
    selectRequired: "Selecciona {label}.",
    checkboxRequired: "Selecciona {label}.",
    email: "Escribe un {label} válido.",
    url: "Escribe una URL válida.",
    typeMismatch: "{Label} no tiene un formato válido.",
    minLength: "{Label} debe tener al menos {minLength} caracteres.",
    maxLength: "{Label} no debe exceder {maxLength} caracteres.",
    pattern: "{Label} no tiene el formato correcto.",
    rangeUnderflow: "{Label} debe ser mayor o igual a {min}.",
    rangeOverflow: "{Label} debe ser menor o igual a {max}.",
    match: "{Label} no coincide.",
    invalid: "{Label} no es válido.",
  },
};

const DEFAULT_VALIDATION_PROFILES = {
  html: {
    messages: {},
    fields: {},
  },

  none: {
    skip: true,
    messages: {},
    fields: {},
  },

  contact: {
    messages: {
      loading: "Enviando mensaje...",
      invalidForm: "Revisa los datos de contacto.",
      genericSuccess: "Tu mensaje fue enviado correctamente.",
      genericError: "No se pudo enviar tu mensaje. Intenta nuevamente.",
      missingCsrf:
        "La sesión del formulario de contacto expiró. Recarga la página e intenta otra vez.",

      required: "Completa {label}.",
      selectRequired: "Selecciona {label}.",
      checkboxRequired: "Selecciona {label}.",
      email: "Escribe un {label} válido.",
      url: "Escribe una URL válida.",
      typeMismatch: "{Label} no tiene un formato válido.",
      minLength: "{Label} debe tener al menos {minLength} caracteres.",
      maxLength: "{Label} no debe exceder {maxLength} caracteres.",
      pattern: "{Label} no tiene el formato correcto.",
      rangeUnderflow: "{Label} debe ser mayor o igual a {min}.",
      rangeOverflow: "{Label} debe ser menor o igual a {max}.",
      match: "{Label} no coincide.",
      invalid: "{Label} no es válido.",
    },

    fields: {
      name: {
        required: true,
        minLength: 3,
        maxLength: 120,
        label: "nombre o empresa",
        normalize: "spaces",
      },
      email: {
        required: true,
        type: "email",
        maxLength: 150,
        label: "correo electrónico",
        normalize: "trim",
      },
      message: {
        required: true,
        minLength: 10,
        maxLength: 3000,
        label: "mensaje",
        normalize: "trim",
      },
    },
  },

  eventRegistration: {
    messages: {
      loading: "Enviando registro...",
      invalidForm: "Revisa los datos de tu registro.",
      genericSuccess: "Tu registro fue enviado correctamente.",
      genericError: "No se pudo enviar tu registro. Intenta nuevamente.",
      missingCsrf:
        "La sesión del registro expiró. Recarga la página e intenta otra vez.",

      required: "Completa {label}.",
      selectRequired: "Selecciona {label}.",
      checkboxRequired: "Selecciona {label}.",
      email: "Escribe un {label} válido.",
      url: "Escribe una URL válida.",
      typeMismatch: "{Label} no tiene un formato válido.",
      minLength: "{Label} debe tener al menos {minLength} caracteres.",
      maxLength: "{Label} no debe exceder {maxLength} caracteres.",
      pattern: "{Label} no tiene el formato correcto.",
      rangeUnderflow: "{Label} debe ser mayor o igual a {min}.",
      rangeOverflow: "{Label} debe ser menor o igual a {max}.",
      match: "{Label} no coincide.",
      invalid: "{Label} no es válido.",
    },

    fields: {
      name: {
        required: true,
        minLength: 3,
        maxLength: 120,
        label: "nombre o empresa",
        normalize: "spaces",
      },
      select: {
        required: true,
        label: "opción",
      },
      email: {
        required: true,
        type: "email",
        maxLength: 150,
        label: "correo electrónico",
        normalize: "trim",
      },
    },
  },

  login: {
    messages: {
      loading: "Iniciando sesión...",
      invalidForm: "Revisa tu correo y contraseña.",
      genericSuccess: "Sesión iniciada correctamente.",
      genericError: "No se pudo iniciar sesión.",
      missingCsrf:
        "La sesión del login expiró. Recarga la página e intenta otra vez.",

      required: "Completa {label}.",
      email: "Escribe un {label} válido.",
      invalid: "{Label} no es válido.",
    },

    fields: {
      email: {
        required: true,
        type: "email",
        label: "correo electrónico",
        normalize: "trim",
      },
      password: {
        required: true,
        label: "contraseña",
        normalize: "none",
      },
    },
  },
};

export function initAjaxForms(options = {}) {
  const config = createConfig(options);
  const selector = options.selector || config.selector;

  const forms = resolveForms(options.forms || selector);

  return forms.map((form) => initAjaxForm(form, config)).filter(Boolean);
}

export function initAjaxForm(formOrSelector, options = {}) {
  const form =
    typeof formOrSelector === "string"
      ? document.querySelector(formOrSelector)
      : formOrSelector;

  if (!form || !(form instanceof HTMLFormElement)) return null;

  if (form.dataset.ajaxFormBound === "true") return null;
  form.dataset.ajaxFormBound = "true";

  const config = createConfig(options);
  const state = createState(form, config);

  bindFieldEvents(state);

  form.addEventListener("submit", (event) => {
    handleSubmit(event, state);
  });

  return {
    form,
    validate: () => validateForm(state),
    reset: () => resetFormState(state),
    destroy: () => {
      form.dataset.ajaxFormBound = "false";
    },
  };
}

export default initAjaxForms;

function createConfig(options = {}) {
  return {
    ...DEFAULT_OPTIONS,
    ...options,

    messages: {
      ...DEFAULT_OPTIONS.messages,
      ...(options.messages || {}),
    },

    validationProfiles: mergeValidationProfiles(
      DEFAULT_VALIDATION_PROFILES,
      options.validationProfiles || {},
    ),
  };
}
function mergeValidationProfiles(defaultProfiles = {}, customProfiles = {}) {
  const merged = { ...defaultProfiles };

  Object.entries(customProfiles).forEach(([profileName, customProfile]) => {
    const defaultProfile = defaultProfiles[profileName] || {};

    merged[profileName] = {
      ...defaultProfile,
      ...customProfile,

      messages: {
        ...(defaultProfile.messages || {}),
        ...(customProfile.messages || {}),
      },

      fields: {
        ...(defaultProfile.fields || {}),
        ...(customProfile.fields || {}),
      },
    };
  });

  return merged;
}

function createState(form, config) {
  const formOptions = getFormOptions(form, config);
  const submitButton = form.querySelector('[type="submit"], .button__submit');
  const submitText =
    submitButton?.querySelector(".button__submit__text") || submitButton;

  return {
    form,
    config,
    options: formOptions,
    fields: getFields(form),
    statusBox: findStatusBox(form),
    submitButton,
    submitText,
    originalSubmitText:
      submitText?.textContent?.trim() ||
      formOptions.submitText ||
      config.defaultSubmitText,
    isSending: false,
  };
}

function getFormOptions(form, config) {
  const dataset = form.dataset;

  return {
    endpoint:
      dataset.action ||
      dataset.url ||
      dataset.endpoint ||
      form.getAttribute("action") ||
      window.location.pathname,

    method:
      dataset.method || form.getAttribute("method") || config.method || "POST",

    validation: dataset.validation || config.validation || "html",

    csrf: readBoolean(dataset.csrf, config.csrf),

    csrfField: dataset.csrfField || config.csrfField || "_token",

    sendAs: dataset.sendAs || dataset.format || config.sendAs || "auto",

    resetOnSuccess: readBoolean(dataset.resetOnSuccess, config.resetOnSuccess),

    loadingText: dataset.loadingText || "",

    successMessage: dataset.successMessage || "",

    errorMessage: dataset.errorMessage || "",
  };
}

function resolveForms(formsOrSelector) {
  if (typeof formsOrSelector === "string") {
    return Array.from(document.querySelectorAll(formsOrSelector));
  }

  if (formsOrSelector instanceof HTMLFormElement) {
    return [formsOrSelector];
  }

  if (formsOrSelector instanceof NodeList || Array.isArray(formsOrSelector)) {
    return Array.from(formsOrSelector).filter(
      (form) => form instanceof HTMLFormElement,
    );
  }

  return [];
}

function getFields(form) {
  return Array.from(form.querySelectorAll(FIELD_SELECTOR));
}

function bindFieldEvents(state) {
  const alreadyBoundGroups = new Set();

  state.fields.forEach((field) => {
    if (shouldIgnoreField(field, state)) return;

    const groupKey = getFieldGroupKey(field);

    if (alreadyBoundGroups.has(groupKey)) return;
    alreadyBoundGroups.add(groupKey);

    const validateWhenTouched = () => {
      if (
        field.dataset.touched === "true" ||
        state.form.dataset.submitted === "true"
      ) {
        validateField(field, state);
      }
    };

    field.addEventListener("blur", () => {
      field.dataset.touched = "true";
      validateField(field, state);
    });

    field.addEventListener("input", validateWhenTouched);
    field.addEventListener("change", validateWhenTouched);
  });
}

async function handleSubmit(event, state) {
  event.preventDefault();

  if (state.isSending) return;

  state.form.dataset.submitted = "true";
  clearStatus(state);

  const isValid = validateForm(state);

  if (!isValid) {
    showStatus(state, getMessage(state, "invalidForm"), "error");
    focusFirstInvalidField(state);
    return;
  }

  try {
    setLoadingState(state, true);

    const request = buildRequest(state);

    const response = await fetch(state.options.endpoint, request);

    const parsedResponse = await parseResponse(response);

    if (response.redirected && !parsedResponse.isJson) {
      window.location.href = response.url;
      return;
    }

    const data = parsedResponse.data;

    updateCsrfTokenIfPresent(state, data, response);

    if (!response.ok || data?.ok === false) {
      applyServerErrors(state, data?.errors);

      const message =
        data?.error || data?.message || getMessage(state, "genericError");

      throw new Error(message);
    }

    if (data?.redirect) {
      window.location.href = data.redirect;
      return;
    }

    showStatus(
      state,
      data?.message || getMessage(state, "genericSuccess"),
      "success",
    );

    state.form.dispatchEvent(
      new CustomEvent("ajax-form:success", {
        bubbles: true,
        detail: {
          response,
          data,
          form: state.form,
        },
      }),
    );

    if (state.options.resetOnSuccess) {
      state.form.reset();
      resetValidationStyles(state);
    }
  } catch (error) {
    console.error("Error al enviar formulario:", error);

    showStatus(
      state,
      error?.message || getMessage(state, "genericError"),
      "error",
    );

    state.form.dispatchEvent(
      new CustomEvent("ajax-form:error", {
        bubbles: true,
        detail: {
          error,
          form: state.form,
        },
      }),
    );
  } finally {
    setLoadingState(state, false);
  }
}

function validateForm(state) {
  const profile = getValidationProfile(state);

  if (profile.skip) return true;

  let isValid = true;
  const validatedGroups = new Set();

  state.fields.forEach((field) => {
    if (shouldIgnoreField(field, state)) return;

    const groupKey = getFieldGroupKey(field);
    if (validatedGroups.has(groupKey)) return;

    validatedGroups.add(groupKey);

    if (!validateField(field, state)) {
      isValid = false;
    }
  });

  return isValid;
}

function validateField(field, state) {
  const profile = getValidationProfile(state);

  if (profile.skip || shouldIgnoreField(field, state)) {
    return true;
  }

  const rules = getFieldRules(field, state);
  const label = getFieldLabel(field, state, rules);
  const value = getFieldValue(field, state.form);
  const normalizedValue = normalizeValue(value, rules);
  const context = getMessageContext(label);

  clearFieldState(field, state);

  const nativeMessage = getNativeValidationMessage(field, label, state);

  if (nativeMessage) {
    setFieldState(field, state, false, nativeMessage);
    return false;
  }

  if (rules.required && isEmptyValue(normalizedValue)) {
    const message =
      field.dataset.messageRequired ||
      rules.messageRequired ||
      getRequiredMessage(field, label, state);

    setFieldState(field, state, false, message);
    return false;
  }

  if (
    rules.type === "email" &&
    !isEmptyValue(normalizedValue) &&
    !EMAIL_REGEX.test(String(normalizedValue))
  ) {
    setFieldState(
      field,
      state,
      false,
      field.dataset.messageEmail ||
        rules.messageEmail ||
        getMessage(state, "email", `Escribe un ${label} válido.`, context),
    );

    return false;
  }

  if (
    typeof normalizedValue === "string" &&
    rules.minLength &&
    normalizedValue.length < Number(rules.minLength)
  ) {
    setFieldState(
      field,
      state,
      false,
      field.dataset.messageMinLength ||
        rules.messageMinLength ||
        getMessage(
          state,
          "minLength",
          `${capitalize(label)} debe tener al menos ${rules.minLength} caracteres.`,
          getMessageContext(label, {
            minLength: rules.minLength,
          }),
        ),
    );

    return false;
  }

  if (
    typeof normalizedValue === "string" &&
    rules.maxLength &&
    normalizedValue.length > Number(rules.maxLength)
  ) {
    setFieldState(
      field,
      state,
      false,
      field.dataset.messageMaxLength ||
        rules.messageMaxLength ||
        getMessage(
          state,
          "maxLength",
          `${capitalize(label)} no debe exceder ${rules.maxLength} caracteres.`,
          getMessageContext(label, {
            maxLength: rules.maxLength,
          }),
        ),
    );

    return false;
  }

  if (
    rules.pattern &&
    typeof normalizedValue === "string" &&
    normalizedValue !== ""
  ) {
    const regex =
      rules.pattern instanceof RegExp
        ? rules.pattern
        : new RegExp(rules.pattern);

    if (!regex.test(normalizedValue)) {
      setFieldState(
        field,
        state,
        false,
        field.dataset.messagePattern ||
          rules.messagePattern ||
          getMessage(
            state,
            "pattern",
            `${capitalize(label)} no tiene el formato correcto.`,
            context,
          ),
      );

      return false;
    }
  }

  if (rules.match) {
    const otherField = state.form.querySelector(
      `[name="${escapeAttribute(rules.match)}"]`,
    );

    const otherValue = otherField
      ? normalizeValue(getFieldValue(otherField, state.form), rules)
      : "";

    if (normalizedValue !== otherValue) {
      setFieldState(
        field,
        state,
        false,
        rules.messageMatch ||
          field.dataset.messageMatch ||
          getMessage(
            state,
            "match",
            `${capitalize(label)} no coincide.`,
            context,
          ),
      );

      return false;
    }
  }

  setFieldState(field, state, true);
  return true;
}

function getValidationProfile(state) {
  const profileName = state.options.validation || "html";

  return (
    state.config.validationProfiles[profileName] ||
    state.config.validationProfiles.html
  );
}

function getFieldRules(field, state) {
  const profile = getValidationProfile(state);
  const profileRules = profile.fields?.[field.name] || {};

  return {
    ...profileRules,
    ...getRulesFromDataset(field),
  };
}

function getRulesFromDataset(field) {
  const dataset = field.dataset;
  const rules = {};

  if (dataset.required !== undefined) {
    rules.required = readBoolean(dataset.required, false);
  }

  if (dataset.type) {
    rules.type = dataset.type;
  }

  if (dataset.minLength) {
    rules.minLength = Number(dataset.minLength);
  }

  if (dataset.maxLength) {
    rules.maxLength = Number(dataset.maxLength);
  }

  if (dataset.pattern) {
    rules.pattern = dataset.pattern;
  }

  if (dataset.match) {
    rules.match = dataset.match;
  }

  if (dataset.label) {
    rules.label = dataset.label;
  }

  if (dataset.normalize) {
    rules.normalize = dataset.normalize;
  }

  return rules;
}

function getNativeValidationMessage(field, label, state) {
  if (field.checkValidity()) return "";

  const validity = field.validity;
  const context = getMessageContext(label);

  if (validity.valueMissing) {
    return getRequiredMessage(field, label, state);
  }

  if (validity.typeMismatch) {
    if (field.type === "email") {
      return getMessage(state, "email", `Escribe un ${label} válido.`, context);
    }

    if (field.type === "url") {
      return getMessage(state, "url", "Escribe una URL válida.", context);
    }

    return getMessage(
      state,
      "typeMismatch",
      `${capitalize(label)} no tiene un formato válido.`,
      context,
    );
  }

  if (validity.tooShort) {
    return getMessage(
      state,
      "minLength",
      `${capitalize(label)} debe tener al menos ${field.minLength} caracteres.`,
      getMessageContext(label, {
        minLength: field.minLength,
      }),
    );
  }

  if (validity.tooLong) {
    return getMessage(
      state,
      "maxLength",
      `${capitalize(label)} no debe exceder ${field.maxLength} caracteres.`,
      getMessageContext(label, {
        maxLength: field.maxLength,
      }),
    );
  }

  if (validity.patternMismatch) {
    return getMessage(
      state,
      "pattern",
      `${capitalize(label)} no tiene el formato correcto.`,
      context,
    );
  }

  if (validity.rangeUnderflow) {
    return getMessage(
      state,
      "rangeUnderflow",
      `${capitalize(label)} debe ser mayor o igual a ${field.min}.`,
      getMessageContext(label, {
        min: field.min,
      }),
    );
  }

  if (validity.rangeOverflow) {
    return getMessage(
      state,
      "rangeOverflow",
      `${capitalize(label)} debe ser menor o igual a ${field.max}.`,
      getMessageContext(label, {
        max: field.max,
      }),
    );
  }

  return getMessage(
    state,
    "invalid",
    `${capitalize(label)} no es válido.`,
    context,
  );
}

function getRequiredMessage(field, label, state) {
  const context = getMessageContext(label);

  if (field.tagName === "SELECT") {
    return getMessage(state, "selectRequired", `Selecciona ${label}.`, context);
  }

  if (field.type === "checkbox" || field.type === "radio") {
    return getMessage(
      state,
      "checkboxRequired",
      `Selecciona ${label}.`,
      context,
    );
  }

  return getMessage(state, "required", `Completa ${label}.`, context);
}

function getFieldValue(field, form) {
  if (field.type === "file") {
    return field.files;
  }

  if (field.type === "radio") {
    return (
      form.querySelector(
        `input[type="radio"][name="${escapeAttribute(field.name)}"]:checked`,
      )?.value || ""
    );
  }

  if (field.type === "checkbox") {
    const checkboxes = Array.from(
      form.querySelectorAll(
        `input[type="checkbox"][name="${escapeAttribute(field.name)}"]`,
      ),
    );

    if (checkboxes.length > 1) {
      return checkboxes
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);
    }

    return field.checked ? field.value || "on" : "";
  }

  if (field.tagName === "SELECT" && field.multiple) {
    return Array.from(field.selectedOptions).map((option) => option.value);
  }

  return field.value || "";
}

function normalizeValue(value, rules = {}) {
  if (value instanceof FileList) return value;
  if (Array.isArray(value)) return value;

  let normalized = String(value ?? "");

  if (rules.normalize === "none") {
    return normalized;
  }

  if (rules.normalize === "spaces") {
    return normalized.replace(/\s+/g, " ").trim();
  }

  return normalized.trim();
}

function isEmptyValue(value) {
  if (value instanceof FileList) return value.length === 0;
  if (Array.isArray(value)) return value.length === 0;
  return String(value ?? "").trim() === "";
}

function buildRequest(state) {
  const formData = new FormData(state.form);

  normalizeTextFieldsInFormData(formData, state);
  appendCsrfIfNeeded(formData, state);

  const sendAs = resolveSendAs(state);
  const method = String(state.options.method || "POST").toUpperCase();

  const headers = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  const csrfToken = getCsrfToken(state);
  const csrfKey = getCsrfKey(state);

  if (csrfToken) {
    headers["X-CSRF-Token"] = csrfToken;
  }

  if (csrfKey) {
    headers["X-CSRF-Key"] = csrfKey;
  }

  if (sendAs === "json") {
    headers["Content-Type"] = "application/json";

    return {
      method,
      headers,
      credentials: state.config.credentials,
      body: JSON.stringify(formDataToObject(formData)),
    };
  }

  if (sendAs === "urlencoded") {
    headers["Content-Type"] =
      "application/x-www-form-urlencoded; charset=UTF-8";

    return {
      method,
      headers,
      credentials: state.config.credentials,
      body: formDataToUrlSearchParams(formData),
    };
  }

  return {
    method,
    headers,
    credentials: state.config.credentials,
    body: formData,
  };
}

function resolveSendAs(state) {
  const requested = String(state.options.sendAs || "auto").toLowerCase();

  if (requested !== "auto") {
    return requested;
  }

  if (formHasFiles(state.form)) {
    return "formdata";
  }

  if (state.options.csrf) {
    return "urlencoded";
  }

  return "json";
}

function normalizeTextFieldsInFormData(formData, state) {
  state.fields.forEach((field) => {
    if (!field.name || field.disabled) return;
    if (!isTextLikeField(field)) return;

    const rules = getFieldRules(field, state);
    const normalized = normalizeValue(field.value, rules);

    formData.set(field.name, normalized);
  });
}

function isTextLikeField(field) {
  if (field.tagName === "TEXTAREA") return true;
  if (field.tagName === "SELECT") return false;

  const type = String(field.type || "text").toLowerCase();

  return [
    "text",
    "email",
    "password",
    "search",
    "tel",
    "url",
    "number",
    "date",
    "datetime-local",
    "month",
    "time",
    "week",
  ].includes(type);
}

function appendCsrfIfNeeded(formData, state) {
  if (!state.options.csrf) return;

  const csrfField = state.options.csrfField || "_token";
  const token = getCsrfToken(state);
  const key = getCsrfKey(state);

  if (!token) {
    throw new Error(getMessage(state, "missingCsrf"));
  }

  if (!formData.has(csrfField)) {
    formData.append(csrfField, token);
  }

  if (key && !formData.has("_csrf_key")) {
    formData.append("_csrf_key", key);
  }
}

function getCsrfKey(state) {
  const fieldKey = state.form.querySelector('[name="_csrf_key"]')?.value;

  const metaKey = document
    .querySelector('meta[name="csrf-key"]')
    ?.getAttribute("content");

  return fieldKey || metaKey || "";
}

function getCsrfToken(state) {
  const csrfField = state.options.csrfField;

  const fieldToken = state.form.querySelector(
    `[name="${escapeAttribute(csrfField)}"]`,
  )?.value;

  const metaToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");

  return fieldToken || metaToken || "";
}

function updateCsrfTokenIfPresent(state, data, response = null) {
  if (!state.options.csrf) return;

  const token =
    data?.csrfToken ||
    data?._token ||
    response?.headers?.get("x-csrf-token") ||
    "";

  const key =
    data?.csrfKey ||
    data?._csrf_key ||
    response?.headers?.get("x-csrf-key") ||
    "";

  if (token) {
    upsertHiddenInput(state.form, state.options.csrfField || "_token", token);
  }

  if (key) {
    upsertHiddenInput(state.form, "_csrf_key", key);
  }
}

function upsertHiddenInput(form, name, value) {
  let input = form.querySelector(`[name="${cssEscapeAttribute(name)}"]`);

  if (!input) {
    input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    form.appendChild(input);
  }

  input.value = value;
}

function cssEscapeAttribute(value) {
  return String(value).replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}

function formDataToObject(formData) {
  const data = {};

  formData.forEach((value, key) => {
    if (value instanceof File && value.name === "" && value.size === 0) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(data, key)) {
      if (!Array.isArray(data[key])) {
        data[key] = [data[key]];
      }

      data[key].push(value);
      return;
    }

    data[key] = value;
  });

  return data;
}

function formDataToUrlSearchParams(formData) {
  const params = new URLSearchParams();

  formData.forEach((value, key) => {
    if (value instanceof File) return;
    params.append(key, value);
  });

  return params;
}

async function parseResponse(response) {
  const contentType = response.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    return {
      isJson: true,
      data: await response.json(),
    };
  }

  const text = await response.text();

  return {
    isJson: false,
    data: {
      ok: response.ok,
      message: text,
    },
  };
}

function applyServerErrors(state, errors) {
  if (!errors || typeof errors !== "object") return;

  const normalizedErrors = normalizeServerErrors(errors);

  Object.entries(normalizedErrors).forEach(([fieldName, message]) => {
    const field = findFieldByName(state.form, fieldName);

    if (!field) return;

    setFieldState(field, state, false, message);
  });

  focusFirstInvalidField(state);
}

function normalizeServerErrors(errors) {
  const normalized = {};

  Object.entries(errors).forEach(([field, value]) => {
    if (Array.isArray(value)) {
      normalized[field] = String(value[0] || "");
      return;
    }

    if (value && typeof value === "object") {
      normalized[field] = String(Object.values(value)[0] || "");
      return;
    }

    normalized[field] = String(value || "");
  });

  return normalized;
}

function setFieldState(field, state, isValid, message = "") {
  const group = findFieldGroup(field, state.form);
  const errorBox = findErrorBox(field, state.form, group);

  group?.classList.remove("has-error", "has-success");

  field.removeAttribute("aria-invalid");

  if (errorBox) {
    errorBox.textContent = "";
  }

  if (isValid) {
    if (!isEmptyValue(getFieldValue(field, state.form))) {
      group?.classList.add("has-success");
    }

    return;
  }

  group?.classList.add("has-error");
  field.setAttribute("aria-invalid", "true");

  if (errorBox) {
    errorBox.textContent = message;

    if (errorBox.id) {
      field.setAttribute("aria-describedby", errorBox.id);
    }
  }
}

function clearFieldState(field, state) {
  const group = findFieldGroup(field, state.form);
  const errorBox = findErrorBox(field, state.form, group);

  group?.classList.remove("has-error", "has-success");
  field.removeAttribute("aria-invalid");

  if (errorBox) {
    errorBox.textContent = "";
  }
}

function resetValidationStyles(state) {
  state.fields.forEach((field) => {
    clearFieldState(field, state);
    delete field.dataset.touched;
  });

  delete state.form.dataset.submitted;
}

function resetFormState(state) {
  state.form.reset();
  clearStatus(state);
  resetValidationStyles(state);
  setLoadingState(state, false);
}

function findFieldGroup(field, form) {
  return (
    field.closest(".form-group") ||
    field.closest(".form-check") ||
    field.closest(".form-check-group") ||
    field.closest(`[data-field="${escapeAttribute(field.name)}"]`) ||
    field.closest("[data-field-group]") ||
    form
  );
}

function findErrorBox(field, form, group) {
  return (
    group?.querySelector(`[data-error-for="${escapeAttribute(field.name)}"]`) ||
    group?.querySelector(`#error-${cssEscape(field.name)}`) ||
    group?.querySelector("[data-field-error]") ||
    group?.querySelector(".form-group__error") ||
    form.querySelector(`[data-error-for="${escapeAttribute(field.name)}"]`) ||
    form.querySelector(`#error-${cssEscape(field.name)}`)
  );
}

function findFieldByName(form, fieldName) {
  return form.querySelector(`[name="${escapeAttribute(fieldName)}"]`);
}

function findStatusBox(form) {
  return (
    form.querySelector("[data-form-status]") ||
    form.querySelector(".form__status") ||
    form.querySelector(".contact-form__status") ||
    form.querySelector('[id$="FormStatus"]')
  );
}

function showStatus(state, message, type = "error") {
  if (!state.statusBox) return;

  state.statusBox.textContent = message;
  state.statusBox.classList.remove("is-error", "is-success", "is-loading");
  state.statusBox.classList.add("is-visible", `is-${type}`);
}

function clearStatus(state) {
  if (!state.statusBox) return;

  state.statusBox.textContent = "";
  state.statusBox.classList.remove(
    "is-visible",
    "is-error",
    "is-success",
    "is-loading",
  );
}

function setLoadingState(state, loading) {
  state.isSending = loading;

  if (state.submitButton) {
    state.submitButton.disabled = loading;
    state.submitButton.classList.toggle("is-loading", loading);
  }

  if (state.submitText) {
    state.submitText.textContent = loading
      ? getMessage(state, "loading")
      : state.originalSubmitText;
  }

  if (loading) {
    showStatus(state, getMessage(state, "loading"), "loading");
  }
}

function focusFirstInvalidField(state) {
  const firstInvalid = state.form.querySelector(
    `
      .form-group.has-error input:not([type="hidden"]),
      .form-group.has-error select,
      .form-group.has-error textarea,
      .form-check.has-error input:not([type="hidden"]),
      .form-check-group.has-error input:not([type="hidden"]),
      [aria-invalid="true"]:not([type="hidden"])
    `,
  );

  firstInvalid?.focus();
}
function getFieldLabel(field, state, rules = {}) {
  if (rules.label) return rules.label;

  if (field.dataset.label) return field.dataset.label;

  const group = findFieldGroup(field, state.form);

  const explicitLabel =
    field.id &&
    (group?.querySelector(`label[for="${escapeAttribute(field.id)}"]`) ||
      state.form.querySelector(`label[for="${escapeAttribute(field.id)}"]`));

  const wrappingLabel = field.closest("label");

  const labelText =
    explicitLabel?.textContent?.trim() || wrappingLabel?.textContent?.trim();

  if (labelText) {
    return cleanLabelText(labelText);
  }

  return field.name || "campo";
}

function cleanLabelText(text) {
  return text
    .replace(/\*/g, "")
    .replace(/^selecciona\s+/i, "")
    .replace(/^escribe\s+/i, "")
    .trim()
    .toLowerCase();
}

function shouldIgnoreField(field, state) {
  if (!field.name || field.disabled) return true;

  const type = String(field.type || "").toLowerCase();

  if (["submit", "button", "reset"].includes(type)) return true;

  if (type === "hidden" && field.name === state.options.csrfField) {
    return true;
  }

  return false;
}

function getFieldGroupKey(field) {
  const type = String(field.type || "").toLowerCase();

  if (type === "radio" || type === "checkbox") {
    return `${type}:${field.name}`;
  }

  return field.name;
}

function formHasFiles(form) {
  return Boolean(form.querySelector('input[type="file"]'));
}

function readBoolean(value, fallback = false) {
  if (value === undefined || value === null || value === "") {
    return fallback;
  }

  return ["true", "1", "yes", "si", "sí"].includes(String(value).toLowerCase());
}

function capitalize(value) {
  const text = String(value || "");
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function cssEscape(value) {
  if (window.CSS?.escape) return CSS.escape(value);

  return String(value).replace(/[^a-zA-Z0-9_-]/g, "\\$&");
}

function escapeAttribute(value) {
  return String(value).replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}
function getProfileMessages(state) {
  const profile = getValidationProfile(state);

  return {
    ...state.config.messages,
    ...(profile.messages || {}),
  };
}

function getMessage(state, key, fallback = "", context = {}) {
  const messages = getProfileMessages(state);

  const optionMessageMap = {
    loading: "loadingText",
    genericSuccess: "successMessage",
    genericError: "errorMessage",
  };

  const optionKey = optionMessageMap[key];

  const value =
    (optionKey && state.options[optionKey]) || messages[key] || fallback || "";

  return formatMessage(value, context);
}

function formatMessage(message, context = {}) {
  return String(message || "").replace(/\{([a-zA-Z0-9_]+)\}/g, (_, key) => {
    return context[key] ?? "";
  });
}

function getMessageContext(label, extra = {}) {
  return {
    label,
    Label: capitalize(label),
    ...extra,
  };
}

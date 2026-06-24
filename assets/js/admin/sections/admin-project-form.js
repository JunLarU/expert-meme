const PROJECT_FORM_SELECTOR = 'form.admin-project-form[data-ajax-form]';
const GALLERY_ROOT_SELECTOR = '[data-project-gallery-admin]';
const GALLERY_LIST_SELECTOR = '[data-project-gallery-list]';
const GALLERY_ITEM_SELECTOR = '[data-project-gallery-item]';
const GALLERY_ADD_SELECTOR = '[data-project-gallery-add]';
const GALLERY_REMOVE_SELECTOR = '[data-project-gallery-remove]';

const FIELD_SELECTOR = `
  input[name]:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="hidden"]),
  select[name],
  textarea[name]
`;

const FILE_SIZE_UNITS = {
  b: 1,
  kb: 1024,
  k: 1024,
  mb: 1024 * 1024,
  m: 1024 * 1024,
  gb: 1024 * 1024 * 1024,
  g: 1024 * 1024 * 1024,
};

export default function initAdminProjectForm(root = document) {
  const forms = Array.from(root.querySelectorAll(PROJECT_FORM_SELECTOR));

  forms.forEach((form) => {
    if (form.dataset.projectFormCompatBound === 'true') return;

    form.dataset.projectFormCompatBound = 'true';

    prepareAjaxFormCompatibility(form);
    initProjectGallery(form);
    bindSubmitGuard(form);
  });
}

function prepareAjaxFormCompatibility(form) {
  /*
   * AJAX-FORM.js queda intacto.
   * Este formulario es muy dinámico por la galería, así que aquí se valida antes
   * y AJAX-FORM.js solo se encarga de enviar el FormData.
   */
  form.dataset.validation = 'none';
  form.dataset.sendAs = form.dataset.sendAs || 'formdata';
  form.dataset.csrf = form.dataset.csrf || 'true';
  form.dataset.csrfField = form.dataset.csrfField || '_token';
  form.dataset.resetOnSuccess = form.dataset.resetOnSuccess || 'false';
  form.enctype = 'multipart/form-data';

  const status = getStatusBox(form);

  if (status) {
    status.setAttribute('role', status.getAttribute('role') || 'status');
    status.setAttribute('aria-live', status.getAttribute('aria-live') || 'polite');
  }

  ensureFileValidationDataset(form);
}

function ensureFileValidationDataset(form) {
  setFileDefaults(form, 'cover_image_file', {
    maxFileSize: '8mb',
    fileTypes: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
    message: 'Solo se permiten imágenes PNG, JPG, JPEG o WEBP de máximo 8 MB.',
  });

  setFileDefaults(form, 'cover_mobile_file', {
    maxFileSize: '8mb',
    fileTypes: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
    message: 'La portada móvil debe ser PNG, JPG, JPEG o WEBP de máximo 8 MB.',
  });

  setFileDefaults(form, 'hero_background_file', {
    maxFileSize: '8mb',
    fileTypes: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
    message: 'El fondo del hero debe ser PNG, JPG, JPEG o WEBP de máximo 8 MB.',
  });

  setFileDefaults(form, 'map_image_file', {
    maxFileSize: '4mb',
    fileTypes: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
    message: 'La imagen del pin debe ser PNG, JPG, JPEG o WEBP de máximo 4 MB.',
  });

  form.querySelectorAll('input[type="file"][name^="gallery_media_files["]').forEach((field) => {
    setFileDataset(field, {
      maxFileSize: '35mb',
      fileTypes: '.png,.jpg,.jpeg,.webp,.mp4,.webm,image/png,image/jpeg,image/webp,video/mp4,video/webm',
      message: 'El archivo de galería debe ser imagen PNG/JPG/WEBP o video MP4/WEBM de máximo 35 MB.',
    });
  });

  form.querySelectorAll('input[type="file"][name^="gallery_poster_files["]').forEach((field) => {
    setFileDataset(field, {
      maxFileSize: '4mb',
      fileTypes: '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
      message: 'La miniatura del video debe ser PNG, JPG, JPEG o WEBP de máximo 4 MB.',
    });
  });
}

function setFileDefaults(form, name, options) {
  const field = form.querySelector(`input[type="file"][name="${cssEscape(name)}"]`);
  if (!field) return;

  setFileDataset(field, options);
}

function setFileDataset(field, options) {
  if (!field.dataset.maxFileSize && options.maxFileSize) {
    field.dataset.maxFileSize = options.maxFileSize;
  }

  if (!field.dataset.fileTypes && options.fileTypes) {
    field.dataset.fileTypes = options.fileTypes;
  }

  if (!field.dataset.messageFileType && options.message) {
    field.dataset.messageFileType = options.message;
  }

  if (!field.dataset.messageFileSize && options.message) {
    field.dataset.messageFileSize = options.message;
  }
}

function initProjectGallery(form) {
  const root = form.querySelector(GALLERY_ROOT_SELECTOR);
  if (!root || root.dataset.projectGalleryBound === 'true') return;

  root.dataset.projectGalleryBound = 'true';

  const list = root.querySelector(GALLERY_LIST_SELECTOR);
  const addButton = root.querySelector(GALLERY_ADD_SELECTOR);

  if (!list || !addButton) return;

  let nextIndex = getNextGalleryIndex(list);

  addButton.addEventListener('click', () => {
    const template = list.querySelector(GALLERY_ITEM_SELECTOR);
    if (!template) return;

    const clone = template.cloneNode(true);

    resetGalleryItem(clone, nextIndex++);

    list.appendChild(clone);

    ensureFileValidationDataset(form);
    refreshRemoveButtons(list);
  });

  list.addEventListener('click', (event) => {
    const removeButton = event.target.closest(GALLERY_REMOVE_SELECTOR);
    if (!removeButton) return;

    const item = removeButton.closest(GALLERY_ITEM_SELECTOR);
    if (!item) return;

    const items = list.querySelectorAll(GALLERY_ITEM_SELECTOR);
    if (items.length <= 1) return;

    item.remove();
    refreshRemoveButtons(list);
  });

  refreshRemoveButtons(list);
}

function getNextGalleryIndex(list) {
  let max = -1;

  list.querySelectorAll('[name]').forEach((field) => {
    const match = String(field.name).match(/\[(\d+)\]/);
    if (!match) return;

    max = Math.max(max, Number(match[1]));
  });

  return max + 1;
}

function resetGalleryItem(item, index) {
  item.querySelectorAll('input, textarea, select').forEach((field) => {
    if (field.name) {
      field.name = field.name.replace(/\[\d+\]/g, `[${index}]`);
    }

    field.removeAttribute('aria-invalid');

    if (field.type === 'file') {
      field.value = '';
      return;
    }

    if (field.type === 'checkbox' || field.type === 'radio') {
      field.checked = false;
      return;
    }

    if (field.type === 'hidden') {
      field.value = '0';
      return;
    }

    if (field.tagName === 'SELECT') {
      field.selectedIndex = 0;
      return;
    }

    field.value = '';
  });

  item.querySelectorAll('.has-error, .has-success').forEach((element) => {
    element.classList.remove('has-error', 'has-success');
  });

  item.querySelectorAll('[data-field-error], .form-group__error').forEach((errorBox) => {
    errorBox.textContent = '';
  });
}

function refreshRemoveButtons(list) {
  const items = Array.from(list.querySelectorAll(GALLERY_ITEM_SELECTOR));
  const canRemove = items.length > 1;

  items.forEach((item) => {
    const removeButton = item.querySelector(GALLERY_REMOVE_SELECTOR);
    if (!removeButton) return;

    removeButton.style.display = canRemove ? 'inline-flex' : 'none';
    removeButton.disabled = !canRemove;
  });
}

function bindSubmitGuard(form) {
  form.addEventListener(
    'submit',
    (event) => {
      clearFormState(form);
      ensureFileValidationDataset(form);

      const result = validateProjectForm(form);

      if (result.valid) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      showStatus(form, result.message || 'Revisa los campos marcados en rojo.', 'error');

      const firstInvalid = form.querySelector('[aria-invalid="true"]');

      if (firstInvalid) {
        firstInvalid.focus({ preventScroll: false });
      }
    },
    true,
  );
}

function validateProjectForm(form) {
  const fields = Array.from(form.querySelectorAll(FIELD_SELECTOR));
  let valid = true;
  const messages = [];

  fields.forEach((field) => {
    const message = validateField(field);

    if (!message) {
      markField(field, true);
      return;
    }

    valid = false;
    messages.push(message);
    markField(field, false, message);
  });

  return {
    valid,
    message: unique(messages).slice(0, 5).join('\n') || 'Revisa los campos marcados en rojo.',
  };
}

function validateField(field) {
  if (!field.name || field.disabled) return '';

  if (field.type === 'file') {
    return validateFileField(field);
  }

  const label = getFieldLabel(field);
  const value = getFieldValue(field);

  if (field.required && isEmpty(value)) {
    return field.tagName === 'SELECT' ? `Selecciona ${label}.` : `Completa ${label}.`;
  }

  if (isEmpty(value)) return '';

  if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(value)) {
    return `Escribe un ${label} válido.`;
  }

  if (field.type === 'url' && !isValidUrl(value)) {
    return `Escribe una URL válida en ${label}.`;
  }

  if (field.type === 'number') {
    const number = Number(value);

    if (!Number.isFinite(number)) {
      return `${capitalize(label)} debe ser un número válido.`;
    }

    if (field.min !== '' && number < Number(field.min)) {
      return `${capitalize(label)} debe ser mayor o igual a ${field.min}.`;
    }

    if (field.max !== '' && number > Number(field.max)) {
      return `${capitalize(label)} debe ser menor o igual a ${field.max}.`;
    }
  }

  const minLength = Number(field.getAttribute('minlength') || 0);
  const maxLength = Number(field.getAttribute('maxlength') || 0);

  if (minLength && value.length < minLength) {
    return `${capitalize(label)} debe tener al menos ${minLength} caracteres.`;
  }

  if (maxLength && value.length > maxLength) {
    return `${capitalize(label)} no debe exceder ${maxLength} caracteres.`;
  }

  return '';
}

function validateFileField(field) {
  const files = Array.from(field.files || []);
  const label = getFieldLabel(field);

  if (field.required && files.length === 0) {
    return `Selecciona ${label}.`;
  }

  if (files.length === 0) return '';

  const maxFiles = Number(field.dataset.maxFiles || 0);

  if (maxFiles && files.length > maxFiles) {
    return `${capitalize(label)} permite máximo ${maxFiles} archivo(s).`;
  }

  const maxFileSize = parseFileSize(field.dataset.maxFileSize || '');
  const allowedTypes = getAllowedTypes(field);

  for (const file of files) {
    if (maxFileSize && file.size > maxFileSize) {
      return field.dataset.messageFileSize || `${file.name} excede el tamaño máximo permitido.`;
    }

    if (allowedTypes.length && !isAllowedFile(file, allowedTypes)) {
      return field.dataset.messageFileType || `${file.name} tiene un tipo de archivo no permitido.`;
    }
  }

  return '';
}

function getAllowedTypes(field) {
  const raw = field.dataset.fileTypes || field.getAttribute('accept') || '';

  return raw
    .split(',')
    .map((item) => item.trim().toLowerCase())
    .filter(Boolean);
}

function isAllowedFile(file, allowedTypes) {
  const mime = String(file.type || '').toLowerCase();
  const name = String(file.name || '').toLowerCase();

  return allowedTypes.some((rule) => {
    if (rule.endsWith('/*')) {
      return mime.startsWith(rule.slice(0, -1));
    }

    if (rule.startsWith('.')) {
      return name.endsWith(rule);
    }

    return mime === rule;
  });
}

function markField(field, isValid, message = '') {
  const group = findFieldGroup(field);
  const errorBox = findErrorBox(field, group);

  if (group) {
    group.classList.remove('has-error', 'has-success');
  }

  field.removeAttribute('aria-invalid');

  if (errorBox) {
    errorBox.textContent = '';
  }

  if (isValid) {
    if (field.dataset.touched === 'true' && group) {
      group.classList.add('has-success');
    }

    return;
  }

  if (group) {
    group.classList.add('has-error');
  }

  field.setAttribute('aria-invalid', 'true');

  if (errorBox) {
    errorBox.textContent = message;

    if (!errorBox.id) {
      errorBox.id = `error-${safeId(field.name)}`;
    }

    field.setAttribute('aria-describedby', errorBox.id);
  }
}

function clearFormState(form) {
  const status = getStatusBox(form);

  if (status) {
    status.classList.remove('is-visible', 'is-error', 'is-success', 'is-loading');
    status.textContent = '';
  }

  form.querySelectorAll('.has-error, .has-success').forEach((element) => {
    element.classList.remove('has-error', 'has-success');
  });

  form.querySelectorAll('[aria-invalid="true"]').forEach((field) => {
    field.removeAttribute('aria-invalid');
  });

  form.querySelectorAll('[data-field-error], .form-group__error').forEach((errorBox) => {
    errorBox.textContent = '';
  });
}

function findFieldGroup(field) {
  return (
    field.closest('.form-group') ||
    field.closest('.admin-check') ||
    field.closest('[data-field-group]') ||
    field.closest('[data-field]') ||
    null
  );
}

function findErrorBox(field, group) {
  if (!group) return null;

  const names = getFieldNameCandidates(field.name);

  for (const name of names) {
    const errorBox = group.querySelector(`[data-error-for="${escapeAttribute(name)}"]`);

    if (errorBox) {
      return errorBox;
    }
  }

  return group.querySelector('[data-field-error], .form-group__error');
}

function getFieldNameCandidates(name) {
  const value = String(name || '');
  const base = value.split('[')[0];
  const noIndexes = value.replace(/\[\d+\]/g, '[]');

  return unique([value, base, noIndexes, `${base}[]`].filter(Boolean));
}

function getFieldLabel(field) {
  if (field.dataset.label) {
    return cleanLabel(field.dataset.label);
  }

  const group = findFieldGroup(field);
  const explicit = field.id
    ? group?.querySelector(`label[for="${escapeAttribute(field.id)}"]`) ||
      field.form?.querySelector(`label[for="${escapeAttribute(field.id)}"]`)
    : null;

  const wrapping = field.closest('label');
  const text = explicit?.textContent || wrapping?.textContent || field.name || 'campo';

  return cleanLabel(text);
}

function cleanLabel(text) {
  return String(text || '')
    .replace(/\*/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

function getFieldValue(field) {
  if (field.type === 'checkbox') {
    return field.checked ? field.value || 'on' : '';
  }

  if (field.tagName === 'SELECT' && field.multiple) {
    return Array.from(field.selectedOptions)
      .map((option) => option.value)
      .join(',');
  }

  return String(field.value || '').trim();
}

function showStatus(form, message, type = 'error') {
  const status = getStatusBox(form);
  if (!status) return;

  status.textContent = message;
  status.classList.remove('is-error', 'is-success', 'is-loading');
  status.classList.add('is-visible', `is-${type}`);
}

function getStatusBox(form) {
  return form.querySelector('[data-form-status], .form__status');
}

function isEmpty(value) {
  return String(value ?? '').trim() === '';
}

function isValidUrl(value) {
  try {
    new URL(value, window.location.origin);
    return true;
  } catch (_) {
    return false;
  }
}

function parseFileSize(value) {
  const text = String(value || '').trim().toLowerCase();

  if (!text) return 0;

  const match = text.match(/^(\d+(?:\.\d+)?)\s*(b|kb|k|mb|m|gb|g)?$/i);

  if (!match) return 0;

  const amount = Number(match[1]);
  const unit = match[2] || 'b';

  return Math.round(amount * (FILE_SIZE_UNITS[unit] || 1));
}

function cssEscape(value) {
  if (window.CSS?.escape) return window.CSS.escape(value);

  return String(value).replace(/"/g, '\\"');
}

function escapeAttribute(value) {
  return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function safeId(value) {
  return (
    String(value || 'field')
      .replace(/[^a-zA-Z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'field'
  );
}

function unique(values) {
  return Array.from(new Set(values.filter(Boolean)));
}

function capitalize(value) {
  const text = String(value || '');

  return text.charAt(0).toUpperCase() + text.slice(1);
}
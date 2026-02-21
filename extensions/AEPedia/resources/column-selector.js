/**
 * AEPedia CSV column selector and client-side email extractor.
 *
 * On file selection:
 *   - Reads the file, detects headers, populates the <select multiple>
 *     with options whose value is always the 0-based column index.
 *     The label is the header name when available, or "Column N" otherwise.
 *
 * On form submit:
 *   - Parses the full CSV client-side, extracts valid emails into a hidden
 *     field, then re-submits. The raw file is never sent to the server.
 *
 * mw.config values set by PHP:
 *   - aepedia.columnNumberedLabel  translated "Column $1" string
 *   - aepedia.noEmailsError        translated error when no emails found
 *   - aepedia.confirmAllowlist     confirmation message for allowlist import
 *   - aepedia.confirmGroups        confirmation message for group import
 */

const COLUMN_NUMBERED_LABEL =
  mw.config.get("aepedia.columnNumberedLabel") ?? "Column $1";
const NO_EMAILS_ERROR =
  mw.config.get("aepedia.noEmailsError") ?? "No valid emails found.";
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// -------------------------------------------------------------------------
// Initialisation — wire up event listeners once the DOM is ready
// -------------------------------------------------------------------------

mw.hook("wikipage.content").add(() => {
  registerForm(
    "aepedia-form-allowlist",
    mw.config.get("aepedia.confirmAllowlist"),
  );
  registerForm("aepedia-form-groups", mw.config.get("aepedia.confirmGroups"));
});

/**
 * Wire up file input and form submit listeners for one form.
 * Relies on stable element names within the form: csv_file, emails, csv_cols.
 *
 * @param {string} formId
 * @param {string} confirmMsg
 */
const registerForm = (formId, confirmMsg) => {
  const form = document.getElementById(formId);
  if (!form) return;

  const fileInput = form.elements["csv_file"];
  const colSelect = form.elements["csv_cols"];
  const emailsField = form.elements["emails"];
  if (!fileInput || !colSelect || !emailsField) return;

  fileInput.addEventListener("change", async () => {
    const file = fileInput.files?.[0];
    if (!file) {
      resetSelect(colSelect);
      return;
    }
    const text = await readFile(file);
    const headers = findHeaders(text);
    headers ? populateSelect(colSelect, headers) : resetSelect(colSelect);
  });

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const file = fileInput.files?.[0];
    if (!file) {
      showClientError(form, NO_EMAILS_ERROR);
      return;
    }

    // Values are always 0-based column indexes
    const colIndexes = Array.from(colSelect.selectedOptions, ({ value }) =>
      parseInt(value, 10),
    );

    (async () => {
      const text = await readFile(file);
      const emails = extractEmails(text, colIndexes.length ? colIndexes : [0]);

      if (emails.length === 0) {
        showClientError(form, NO_EMAILS_ERROR);
        return;
      }

      if (!confirm(confirmMsg)) return;

      emailsField.value = emails.join("\n");
      fileInput.disabled = true;
      form.submit();
    })();
  });
};

// -------------------------------------------------------------------------
// Internal helpers
// -------------------------------------------------------------------------

/**
 * Read a File as text.
 *
 * @param  {File}            file
 * @return {Promise<string>}
 */
const readFile = (file) =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve(e.target.result);
    reader.onerror = reject;
    reader.readAsText(file);
  });

/**
 * Return the first non-empty line of a text string.
 *
 * @param  {string} text
 * @return {string[]}
 */
const findHeaders = (text) => {
  const lines = text
    .split("\n")
    .map((l) => l.trim())
    .filter((l) => l != "");
  let headers = null;
  for (const line of lines) {
    const cols = parseCsvLine(line);
    const containsEmail = cols.some((c) => EMAIL_RE.test(c.trim()));
    if (containsEmail) break;
    headers = cols;
  }

  return headers;
};

/**
 * Detect whether the first line is a header and populate the <select>.
 * Option values are always 0-based column indexes.
 *
 * @param {HTMLSelectElement} select
 * @param {string}            firstLine
 */
const populateSelect = (select, cols) => {
  const looksLikeHeader = cols.every((c) => !EMAIL_RE.test(c.trim()));
  const prevSelected = new Set(
    Array.from(select.selectedOptions, (o) => o.value),
  );

  select.innerHTML = "";

  for (let idx = 0; idx < cols.length; idx++) {
    const col = cols[idx];
    const label = looksLikeHeader
      ? col.trim()
      : COLUMN_NUMBERED_LABEL.replace("$1", idx + 1);
    const opt = document.createElement("option");
    opt.value = String(idx);
    opt.textContent = label;
    opt.selected =
      prevSelected.has(String(idx)) || (prevSelected.size === 0 && idx === 0);
    select.appendChild(opt);
  }

  if (select.selectedOptions.length === 0 && select.options.length > 0) {
    select.options[0].selected = true;
  }
};

/**
 * Reset the <select> to a single default "Column 1" option (index 0).
 *
 * @param {HTMLSelectElement} select
 */
const resetSelect = (select) => {
  select.innerHTML = `<option value="0" selected>${COLUMN_NUMBERED_LABEL.replace("$1", "1")}</option>`;
};

/**
 * Parse the full CSV text and return a deduplicated array of valid emails.
 *
 * @param  {string}    text
 * @param  {number[]}  colIndexes  0-based column indexes to try, in order
 * @return {string[]}
 */
const extractEmails = (text, colIndexes) => {
  const emails = [];
  const seen = new Set();
  let isFirstLine = true;

  for (const rawLine of text.split("\n")) {
    const line = rawLine.trim();
    if (line === "") continue;

    const cols = parseCsvLine(line);

    // Skip the first line if it looks like a header
    if (isFirstLine) {
      isFirstLine = false;
      if (cols.every((c) => !EMAIL_RE.test(c.trim()))) continue;
    }

    for (const idx of colIndexes) {
      if (idx >= cols.length) continue;

      const email = cols[idx].trim().toLowerCase();
      if (EMAIL_RE.test(email) && !seen.has(email)) {
        seen.add(email);
        emails.push(email);
        break;
      }
    }
  }

  return emails;
};

/**
 * Minimal CSV line parser that handles double-quoted fields.
 *
 * @param  {string}   line
 * @return {string[]}
 */
const parseCsvLine = (line) => {
  const result = [];
  let cur = "",
    inQuote = false;

  for (let i = 0; i < line.length; i++) {
    const ch = line[i];
    if (ch === '"') {
      if (inQuote && line[i + 1] === '"') {
        cur += '"';
        i++;
      } else {
        inQuote = !inQuote;
      }
    } else if (ch === "," && !inQuote) {
      result.push(cur);
      cur = "";
    } else {
      cur += ch;
    }
  }

  result.push(cur);
  return result;
};

/**
 * Display an inline error inside a form, replacing any previous one.
 *
 * @param {HTMLFormElement} form
 * @param {string}          message
 */
const showClientError = (form, message) => {
  form.querySelector(".aepedia-client-error")?.remove();
  const err = document.createElement("p");
  err.className = "aepedia-client-error error";
  err.style.color = "red";
  err.textContent = message;
  form.querySelector("button[type=submit]").before(err);
};

/* A form posted to the server holds the button it was sent with until the next
   page, so a second press while a slow answer is on its way cannot send it
   twice: the first run, a snapshot, a restore. A form a script sends itself has
   prevented the default by then and is left alone, and so is a download
   (`data-download`), after which the page stays. The button is disabled only
   once the form's entries are taken, which would otherwise leave out its own
   name and value, and is given back when the browser shows the page again from
   its cache, as Back does. */

document.addEventListener("submit", (event) => {
  const button = event.submitter;
  if (event.defaultPrevented || event.target.method !== "post" || !button || "download" in button.dataset) return;
  window.setTimeout(() => {
    button.disabled = true;
    button.dataset.held = "";
  });
});

window.addEventListener("pageshow", (event) => {
  if (!event.persisted) return;
  document.querySelectorAll("[data-held]").forEach((button) => {
    button.disabled = false;
    delete button.dataset.held;
  });
});

/* A settings form's save pill (`view/pages/general.php`) is the records' save bar
   without its island: closed until a field differs from what the page loaded,
   counted as the bar counts, and again once every field is back or Discard reset
   them, while what the server said, saved or refused, keeps it open. Its
   sentence is announced through the polite region once it changes, as the bar's
   is. Ctrl or ⌘ S saves while something is pending or was refused. */
document.querySelectorAll("[data-save-bar]").forEach((bar) => {
  const form = bar.closest("form");
  const sentence = bar.querySelector("[data-sentence]");
  const dot = bar.querySelector(".save-bar-dot");
  const said = sentence.textContent;
  const done = bar.classList.contains("is-done");
  const kept = "kept" in bar.dataset;
  const differs = (field) => (field.tagName === "SELECT"
    ? [...field.options].some((option) => option.selected !== option.defaultSelected)
    : field.type !== "hidden" && field.value !== field.defaultValue);
  const count = () => [...form.elements].filter((field) => field.name && differs(field)).length;
  const show = () => {
    const n = count();
    const text = n > 0 ? fill(bar.dataset.pending, { n }) : said;
    bar.classList.toggle("is-open", n > 0 || kept);
    bar.classList.toggle("is-done", n === 0 && done);
    dot.hidden = n === 0 && !kept;
    form.querySelector("button[type=reset]").hidden = n === 0;
    if (text === sentence.textContent) return;
    sentence.textContent = text;
    sentence.parentElement.removeAttribute("role");
    announce(text);
  };
  form.addEventListener("input", show);
  form.addEventListener("reset", () => window.setTimeout(show));
  document.addEventListener("keydown", (event) => {
    if (event.key.toLowerCase() !== "s" || !(event.ctrlKey || event.metaKey) || event.altKey) return;
    event.preventDefault();
    if (count() > 0 || (kept && !done)) form.requestSubmit(form.querySelector("button[type=submit]"));
  });
});

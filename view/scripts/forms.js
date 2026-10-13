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

/* Fields and a field's popover (`partials/fields`, `partials/field-popover`),
   on the shared popover of `view/scripts/app.js`, whose grant
   panel `view/scripts/grants.js` wires as for the collection's badge. Adding and removing are plain
   forms; this narrows the list as somebody types, sends a field's switch as it
   is flipped and its move as one `PATCH`, and shows the removal's confirmation
   only once it was asked for. A change redraws the page, since the columns are
   the server's drawing of the collection, where it was scrolled. */

/* A field opened from Fields hangs where Fields does, since opening it closes the list it was in. */
function openFieldPopover(trigger) {
  const anchor = trigger.closest(".popover") ? document.querySelector('[data-field-popover="fields"]') : trigger;
  const { element } = popover(anchor, [...document.getElementById(trigger.dataset.fieldPopover).content
    .cloneNode(true).childNodes], true);
  narrowFields(element);
  mountGrant(element);
  foldGrants(element);
  const form = element.querySelector("[data-field-edit]");
  const send = async (move) => {
    const feed = form.elements.feed;
    const body = new URLSearchParams({ ...(move ? { move } : {}),
      ...(feed ? { feed: feed.checked ? "1" : "0" } : {}) });
    const response = await fetch(form.dataset.fieldEdit, { method: "PATCH", body,
      headers: { "X-CSRF-Token": form.dataset.csrf, Accept: "application/json" } });
    if (response.ok) return (keepScroll(), location.reload());
    form.querySelector("[data-field-error]").textContent = (await response.json()).error ?? "";
  };
  form?.addEventListener("change", () => send());
  element.querySelectorAll("[data-field-move]").forEach((button) =>
    button.addEventListener("click", () => send(button.dataset.fieldMove)));
  element.querySelector("[data-field-confirm]")?.addEventListener("click", (event) => {
    event.target.hidden = true;
    element.querySelector("[data-field-confirmed]").hidden = false;
    element.querySelector("[data-field-confirmed] button").focus();
  });
  const shown = (selector) => [...element.querySelectorAll(selector)].find((each) => !each.closest("[hidden]"));
  focusField(shown("input:not([type=hidden])") ?? shown("button:not(:disabled)"), element);
  return element;
}

/* A field's grants open under their one line, and close again under it. */
function foldGrants(element) {
  const [opener, part] = [element.querySelector("[data-grant-open]"), element.querySelector("[data-grant-part]")];
  opener?.addEventListener("click", () => {
    part.hidden = !part.hidden;
    opener.setAttribute("aria-expanded", String(!part.hidden));
    if (!part.hidden) focusField(part.querySelector("input:not([type=hidden]), button"));
  });
}

/* Enter takes the first line still shown, not the form's first button, which
   may be one the search has hidden. */
function narrowFields(element) {
  const search = element.querySelector("[data-field-search]");
  const lines = [...element.querySelectorAll("[data-field-carried], [data-field-offer]")];
  search?.addEventListener("input", () => {
    const typed = search.value.trim().toLowerCase();
    lines.forEach((line) => { line.hidden = !line.textContent.toLowerCase().includes(typed); });
    element.querySelector("[data-field-none]").hidden = lines.some((line) => !line.hidden);
  });
  search?.addEventListener("keydown", (event) => {
    if (event.key !== "Enter") return;
    event.preventDefault();
    lines.find((line) => !line.hidden)?.click();
  });
}

/* The name that opened a popover closes it again, as the view menu's button does. */
let fieldOpened = null;

document.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-field-popover]");
  if (!trigger) return;
  if (fieldOpened?.trigger === trigger && fieldOpened.element.isConnected) {
    fieldOpened.element.dispatchEvent(new Event("close"));
    return;
  }
  fieldOpened = { trigger, element: openFieldPopover(trigger) };
});

/* Island mounting, and the behaviour every screen shares; no framework logic.
   Every word an island shows arrives in its JSON, never as a literal here. Escape
   closes one thing per press, innermost first. An undo is never a toast: a button
   that leaves after two seconds is one a keyboard or a screen reader cannot reach
   in time. A toast is never the only place a failure is reported, since a message
   that disappears on its own is no error report, and toasts and the save bar's
   sentence are announced through the one polite live region. */

const fill = (text, values) => text.replace(/\{(\w+)\}/g, (match, name) => values[name] ?? match);

/* An answer as its JSON, which every admin endpoint answers in, success too, or as the page's sentence where it
   has none: an update's or a restore's 503 page, a host's own 413 or 502, a connection lost (`status` 0). */
async function answered(sent) {
  const response = await sent.catch(() => null);
  const body = await response?.json().catch(() => null);
  return body ? { ok: response.ok, status: response.status, body }
    : { ok: false, status: response?.status ?? 0, body: { error: document.body.dataset.unanswered } };
}

/* Through the open modal dialog's region, since an open one makes the frame's inert (`partials/live-region`). */
function announce(text) {
  const region = (document.querySelector("dialog:modal") ?? document.body).querySelector(":scope > [data-live-region]");
  if (region) {
    region.textContent = "";
    window.setTimeout(() => { region.textContent = text; }, 60);
  }
}

/* On what it answers, since a word at the foot of a wide screen is missed by the eye that is on
   the key it copied, and one beside it covers what is still to read or do, the key's sentence or
   its Done. Placed when called, so a button a re-render takes along, the save bar's, still holds it.
   Inside an open dialog it hangs in the dialog, since a modal one stands over everything in the body. */
function toast(text, near) {
  document.querySelector(".toast")?.remove();
  const element = Object.assign(document.createElement("div"), { className: "toast" });
  element.textContent = text;
  const holder = near.closest("dialog[open]") ?? document.body;
  holder.append(element);
  Object.assign(element.style, centred(near, element, holder));
  announce(text);
  window.setTimeout(() => element.remove(), 2000);
}

/* A glyph in a chip or an option, drawn from the page's sprite by its name (`partials/glyphs`). */
function glyphMark(name) {
  const [drawing, use] = ["svg", "use"].map((tag) => document.createElementNS("http://www.w3.org/2000/svg", tag));
  drawing.setAttribute("class", "glyph");
  drawing.setAttribute("aria-hidden", "true");
  use.setAttribute("href", "#glyph-" + name);
  drawing.append(use);
  const mark = Object.assign(document.createElement("span"), { className: "chip-mark" });
  mark.append(drawing);
  return mark;
}

/* Read is the record screen's default and editing is entered on intent. Back
   from editing after a save, the page is read afresh, since the read view is the
   server's drawing of what is now stored. A copy just made arrives at `#edit`,
   opening in editing once, so a reload reads it. A language chosen loads its
   address, as a query bar's choice does, and at `#edit` where editing was open,
   since the words of the other language are what was being edited. */
function mountRecord(screen, mounted) {
  const read = screen.querySelector("[data-record-read]");
  const edit = screen.querySelector("[data-record-edit]");
  const modes = screen.querySelectorAll("[data-record-mode]");
  const show = (mode) => {
    const sheet = mounted.current?.sheet;
    if (mode === "read" && sheet?.writes > 0 && sheet.count === 0) {
      return location.replace(location.pathname + location.search);
    }
    read.hidden = mode !== "read";
    edit.hidden = mode === "read";
    modes.forEach((button) => button.setAttribute("aria-pressed", String(button.dataset.recordMode === mode)));
    edit.querySelectorAll("[data-prose]").forEach(grow);
  };
  modes.forEach((button) => button.addEventListener("click", () => show(button.dataset.recordMode)));
  if (edit && location.hash === "#edit") {
    history.replaceState(null, "", location.pathname + location.search);
    show("edit");
  }
  const words = screen.querySelector("[data-words-select]");
  if (words) {
    chosen(words, "[data-words-select]", (select) => location.assign(select.value + (edit?.hidden === false
      ? "#edit" : "")));
  }
  edit?.addEventListener("input", (event) => event.target.matches("[data-prose]") && grow(event.target));
}

function grow(area) {
  area.style.height = "auto";
  area.style.height = area.scrollHeight + 2 + "px";
}

/* A finger raises no keyboard it did not ask for: after a touch, and on a page
   first seen on a touch screen, a text field waits until it is itself tapped, and
   the surface it opened on takes focus instead, so a screen reader still lands
   inside. A key pressed says a keyboard is at hand again. A cell's pickers focus
   their field themselves (`pickers.js`). */
let touching = matchMedia("(pointer: coarse)").matches;
document.addEventListener("pointerdown", (event) => { touching = event.pointerType !== "mouse"; }, true);
document.addEventListener("keydown", () => { touching = false; }, true);

function focusField(target, surface) {
  const typed = target?.matches("textarea, input:not([type=checkbox], [type=radio], [type=file])");
  (touching && typed ? surface : target)?.focus();
}

document.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-toast], [data-dialog], [data-dialog-close], [data-grant],"
    + " [data-view-menu]");
  if (trigger?.dataset.viewMenu !== undefined) openViewMenu(trigger);
  if (trigger?.dataset.grant !== undefined) openGrant(trigger);
  if (trigger?.dataset.toast !== undefined) toast(trigger.dataset.toast, trigger);
  if (trigger?.dataset.dialog !== undefined) document.getElementById(trigger.dataset.dialog)?.showModal();
  if (trigger?.dataset.dialogClose !== undefined) trigger.closest("dialog").close();
});

/* A narrow paper's view switch: the five views on the shared popover, the current
   one focused, and the button that opened it closes it again. */
let viewMenu = null;

function openViewMenu(button) {
  if (viewMenu?.element.isConnected) return viewMenu.close();
  viewMenu = popover(button, [...document.getElementById("view-menu").content.cloneNode(true).childNodes]);
  viewMenu.element.querySelector("[aria-current]")?.focus();
}

/* The account menu and New language are a `<details>`, which only its own summary
   closes: an outside press and Escape close one as they close a popover. */
function mountDisclosure(details) {
  dismissing((node) => !details.open || details.contains(node), () => { details.open = false; });
  details.addEventListener("keydown", (event) => event.key === "Escape" && details.open
    && (event.preventDefault(), details.open = false, details.querySelector("summary").focus()));
}

document.querySelectorAll("details.account, details.locale-add").forEach(mountDisclosure);

/* A container query takes hold in the first layout, which a browser may count as a
   change, so the spacing it moves eases only from the second frame on; the floating
   feed panel, drawn open from a wide window's cookie, stays out of view until then,
   by which time `feed-panel.js` has closed it without a slide. */
requestAnimationFrame(() => requestAnimationFrame(() => document.body.classList.add("is-settled")));

/* New language hides as its form opens, so focus moves into the form rather
   than falling back to the page with the button gone. */
document.querySelectorAll("details.locale-add").forEach((details) => details.addEventListener("toggle",
  () => details.open && details.querySelector("input:not([type=hidden])").focus()));

/* A dialog closes on a press outside it, as a popover does, but not while it
   shows a secret it can show only once. The press has to begin outside
   too, or a text selection dragged past the edge would throw away a form. */
function mountOutsideClose(dialog) {
  const outside = (event) => {
    const box = dialog.getBoundingClientRect();
    return event.target === dialog && (event.clientX < box.left || event.clientX > box.right
      || event.clientY < box.top || event.clientY > box.bottom);
  };
  let began = false;
  dialog.addEventListener("pointerdown", (event) => { began = outside(event); });
  dialog.addEventListener("click", (event) => began && outside(event) && dialog.dataset.locked === undefined
    && dialog.close());
}

document.querySelectorAll("dialog.dialog").forEach(mountOutsideClose);

function mountIslands() {
  const root = document.querySelector('[data-island="sheet"]');
  return root && window.Vue ? mountSheet(root) : null;
}

/* A page waiting out an update or a restore asks the lightest address again and
   opens its own, or the one it names, once that is answered: a halt that needs a hand carries no
   Retry-After and is then shown as it is, and a form sent meanwhile was never
   carried out, so its address is opened and not sent again. */
function waitOut(address) {
  window.setTimeout(async () => {
    const answer = await fetch("/robots.txt", { cache: "no-store" }).catch(() => null);
    const waiting = answer === null || (answer.status === 503 && answer.headers.has("Retry-After"));
    waiting ? waitOut(address) : location.replace(address);
  }, 3000);
}

const waitingPage = document.querySelector("[data-waiting]");
waitingPage && waitOut(waitingPage.dataset.waiting || location.href);
const mounted = { current: mountIslands() };
document.querySelectorAll("[data-query-bar]").forEach((form) => mountQueryBar(form, mounted));
document.querySelectorAll("[data-record-screen]").forEach((screen) => mountRecord(screen, mounted));
focusField(document.querySelector("[data-autofocus]"));
refocus();
restoreScroll();

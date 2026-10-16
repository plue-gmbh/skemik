/* Island mounting, and the behaviour every screen shares; no framework logic.
   Every word an island shows arrives in its JSON, never as a literal here. Escape
   closes one thing per press, innermost first. An undo is never a toast: a button
   that leaves after two seconds is one a keyboard or a screen reader cannot reach
   in time. A toast is never the only place a failure is reported, since a message
   that disappears on its own is no error report, and toasts and the save bar's
   sentence are announced through the one polite live region. */

const fill = (text, values) => text.replace(/\{(\w+)\}/g, (match, name) => values[name] ?? match);

function announce(text) {
  const region = document.querySelector("[data-live-region]");
  if (region) {
    region.textContent = "";
    window.setTimeout(() => { region.textContent = text; }, 60);
  }
}

function toast(text) {
  document.querySelector(".toast")?.remove();
  const element = Object.assign(document.createElement("div"), { className: "toast" });
  element.textContent = text;
  document.body.append(element);
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

/* A select that loads an address moves on a choice and not on a step: a closed
   select walked with the arrow keys reports each step as a change in some
   browsers, which would load a page per step, so a choice made by keyboard waits
   for Enter or for focus to leave. The page it loads gives focus back to it. */
function chosen(select, selector, act) {
  let [stepped, held] = [false, select.value];
  const take = () => {
    stepped = false;
    if (select.value === held) return;
    held = select.value;
    try { sessionStorage.setItem("refocus", selector); } catch { /* private window */ }
    act(select);
  };
  select.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && stepped) return (event.preventDefault(), take());
    stepped ||= event.key.length === 1 || /^(Arrow|Page|Home|End)/.test(event.key);
  });
  select.addEventListener("change", () => stepped || take());
  select.addEventListener("blur", () => stepped && take());
}

function refocus() {
  let kept = null;
  try {
    kept = sessionStorage.getItem("refocus");
    sessionStorage.removeItem("refocus");
  } catch { kept = null; }
  if (kept) document.querySelector(kept)?.focus();
}

/* Read is the record screen's default and editing is entered on intent. Back
   from editing after a save, the page is read afresh, since the read view is the
   server's drawing of what is now stored. A language chosen loads its address,
   as a query bar's choice does. */
function mountRecord(screen, mounted) {
  const [read, edit] = [screen.querySelector("[data-record-read]"), screen.querySelector("[data-record-edit]")];
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
  const words = screen.querySelector("[data-words-select]");
  if (words) chosen(words, "[data-words-select]", (select) => location.assign(select.value));
  edit?.addEventListener("input", (event) => event.target.matches("[data-prose]") && grow(event.target));
}

function grow(area) {
  area.style.height = "auto";
  area.style.height = area.scrollHeight + 2 + "px";
}

/* A finger raises no keyboard it did not ask for: after a touch, and on a page
   first seen on a touch screen, a text field waits until it is itself tapped, and
   the surface it opened on takes focus instead, so a screen reader still lands
   inside. A key pressed says a keyboard is at hand again. */
let touching = matchMedia("(pointer: coarse)").matches;
document.addEventListener("pointerdown", (event) => { touching = event.pointerType !== "mouse"; }, true);
document.addEventListener("keydown", () => { touching = false; }, true);

function focusField(target, surface) {
  const typed = target?.matches("textarea, input:not([type=checkbox], [type=radio], [type=file])");
  (touching && typed ? surface : target)?.focus();
}

/* Popovers: one at a time, under the element that opened it or over it where
   more room is, never covering what it is about; left-aligned to it, pushed back
   inside the viewport sideways, and capped at that room, since a search's hits
   arrive after it is placed. An outside press and Escape close it; a press on the
   element that opened it is left to that element, whose click opens or closes.
   It hangs at the end of the page, where Tab out of it would land past
   everything, so Tab goes round inside it (`cycle`) and Escape is the keyboard's way out. */
function popover(anchor, content, wide = false) {
  document.querySelector(".popover[data-shared]")?.dispatchEvent(new Event("close"));
  const element = Object.assign(document.createElement("div"), { className: "popover" + (wide ? " is-wide" : "") });
  element.dataset.shared = "";
  element.tabIndex = -1;
  element.setAttribute("role", "dialog");
  element.append(...content);
  document.body.append(element);
  Object.assign(element.style, placement(anchor, element.getBoundingClientRect()));
  const unfollow = following(anchor, element, (at) => Object.assign(element.style, at));
  const locked = () => element.dataset.locked !== undefined;
  const outside = (event) => element.contains(event.target) || anchor.contains(event.target) || locked() || close();
  const escape = (event) => event.key === "Escape" && !locked() && (event.preventDefault(), close(), anchor.focus());
  const close = () => {
    element.remove();
    unfollow();
    document.removeEventListener("pointerdown", outside, true);
    document.removeEventListener("keydown", escape, true);
  };
  element.addEventListener("close", close);
  element.addEventListener("keydown", (event) => cycle(event, element));
  document.addEventListener("pointerdown", outside, true);
  document.addEventListener("keydown", escape, true);
  return { element, close };
}

function cycle(event, element) {
  if (event.key !== "Tab") return;
  const stops = [...element.querySelectorAll("a[href], button, input, select, textarea, [tabindex='0']")]
    .filter((each) => !each.disabled && each.type !== "hidden" && each.getClientRects().length > 0);
  const [first, last] = [stops[0], stops.at(-1)];
  const at = document.activeElement;
  if (!first || (at !== element && at !== (event.shiftKey ? first : last))) return;
  event.preventDefault();
  (event.shiftKey ? last : first).focus();
}

/* In the page's coordinates and not the screen's, so a phone's keyboard coming up
   or the page scrolling leaves it at its field. Over the field it hangs by its
   own bottom edge, so hits arriving later grow it away from the field. The width
   is the document's, since the window's counts the scrollbar and a popover
   pushed under it widens the page. */
function placement(anchor, box = { width: 0, height: 0 }, below = null) {
  const at = anchor.getBoundingClientRect();
  const [under, over] = [window.innerHeight - at.bottom - 12, at.top - 12];
  below ??= box.height <= under || under >= over;
  const left = Math.max(8, Math.min(at.left, document.documentElement.clientWidth - box.width - 8));
  return { left: window.scrollX + left + "px",
    top: window.scrollY + (below ? at.bottom + 4 : at.top - 4) + "px",
    maxHeight: (below ? under : over) + "px", transform: below ? "none" : "translateY(-100%)" };
}

/* The paper scrolls under the page, not the page, so a popover goes along with its
   anchor as any container scrolls, on the side it opened and as tall as it was
   placed, since only its place changes; `apply` takes the place, as the element's
   own style or as an island's binding. Returns what stops the following. */
function following(anchor, element, apply) {
  const below = element.style.transform === "none";
  const move = () => {
    const { left, top } = placement(anchor, element.getBoundingClientRect(), below);
    apply({ left, top });
  };
  document.addEventListener("scroll", move, true);
  return () => document.removeEventListener("scroll", move, true);
}

document.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-toast], [data-dialog], [data-dialog-close], [data-grant],"
    + " [data-view-menu]");
  if (trigger?.dataset.viewMenu !== undefined) openViewMenu(trigger);
  if (trigger?.dataset.grant !== undefined) openGrant(trigger);
  if (trigger?.dataset.toast !== undefined) toast(trigger.dataset.toast);
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
  document.addEventListener("pointerdown", (event) => details.open && !details.contains(event.target)
    && (details.open = false), true);
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
   opens its own once that is answered: a halt that needs a hand carries no
   Retry-After and is then shown as it is, and a form sent meanwhile was never
   carried out, so its address is opened and not sent again. */
function waitOut() {
  window.setTimeout(async () => {
    const answer = await fetch("/robots.txt", { cache: "no-store" }).catch(() => null);
    const waiting = answer === null || (answer.status === 503 && answer.headers.has("Retry-After"));
    waiting ? waitOut() : location.replace(location.href);
  }, 3000);
}

document.querySelector("[data-waiting]") && waitOut();
const mounted = { current: mountIslands() };
document.querySelectorAll("[data-query-bar]").forEach((form) => mountQueryBar(form, mounted));
document.querySelectorAll("[data-record-screen]").forEach((screen) => mountRecord(screen, mounted));
focusField(document.querySelector("[data-autofocus]"));
refocus();

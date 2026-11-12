/* The popover every screen opens, under or over what it is about, and what keeps
   it there: the place it takes, how it follows a scroll and a change of room, and
   how a press outside closes it. Loaded before `app.js` and the islands, which call
   these as they mount. */

/* Popovers: one at a time, under the element that opened it, or over it where it
   does not fit under and more room is, never covering what it is about; left-aligned to it, pushed back
   inside the viewport sideways, and capped at that room, since a search's hits
   arrive after it is placed. An outside press (`dismissing`) and Escape close it; a press on the
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
  const escape = (event) => event.key === "Escape" && !locked() && (event.preventDefault(), close(), anchor.focus());
  const close = () => {
    element.remove();
    unfollow();
    undismiss();
    document.removeEventListener("keydown", escape, true);
  };
  const undismiss = dismissing((node) => element.contains(node) || anchor.contains(node) || locked(), close);
  element.addEventListener("close", close);
  element.addEventListener("keydown", (event) => cycle(event, element));
  document.addEventListener("keydown", escape, true);
  return { element, close };
}

function cycle(event, element) {
  if (event.key !== "Tab") return;
  const stops = [...element.querySelectorAll("a[href], button, input, select, textarea, [tabindex='0']")]
    .filter((each) => !each.disabled && each.type !== "hidden" && each.getClientRects().length > 0);
  const first = stops[0];
  const last = stops.at(-1);
  const at = document.activeElement;
  if (!first || (at !== element && at !== (event.shiftKey ? first : last))) return;
  event.preventDefault();
  (event.shiftKey ? last : first).focus();
}

/* An outside press closes what is open. A mouse closes it as it presses and its click goes on, so the next
   cell opens at once. A finger closes it as it lifts and its click stops there, since a table on a phone holds
   nothing to tap that opens nothing; a finger that scrolls lifts with no tap. Returns what stops listening. */
function dismissing(inside, close) {
  const swallow = (event) => (event.preventDefault(), event.stopPropagation(), disarm());
  const disarm = () => ["click", "pointerdown", "keydown"].forEach((type) =>
    document.removeEventListener(type, type === "click" ? swallow : disarm, true));
  const press = (event) => event.pointerType === "mouse" && !inside(event.target) && close();
  const lift = (event) => event.pointerType !== "mouse" && !inside(event.target)
    && (["click", "pointerdown", "keydown"].forEach((type) =>
      document.addEventListener(type, type === "click" ? swallow : disarm, true)), close());
  document.addEventListener("pointerdown", press, true);
  document.addEventListener("pointerup", lift, true);
  return () => {
    document.removeEventListener("pointerdown", press, true);
    document.removeEventListener("pointerup", lift, true);
  };
}

/* In the page's coordinates and not the screen's, so the page scrolling leaves it
   at its field. The room is what the visual viewport shows, since a phone's
   keyboard covers the window's foot without shrinking it. Over the field it hangs
   by its own bottom edge, so hits arriving later grow it away from the field. The
   width is the document's, since the window's counts the scrollbar and a popover
   pushed under it widens the page. */
function placement(anchor, box = { width: 0, height: 0 }, below = null) {
  const at = anchor.getBoundingClientRect();
  const seen = window.visualViewport;
  const under = seen.offsetTop + seen.height - at.bottom - 12;
  const over = at.top - seen.offsetTop - 12;
  below ??= box.height <= under || under >= over;
  const left = Math.max(8, Math.min(at.left, document.documentElement.clientWidth - box.width - 8));
  return { left: window.scrollX + left + "px",
    top: window.scrollY + (below ? at.bottom + 4 : at.top - 4) + "px",
    maxHeight: (below ? under : over) + "px", transform: below ? "none" : "translateY(-100%)" };
}

/* Over the middle of the anchor and inside the window's width, in the coordinates of what holds it: the
   page's, as a popover is, or a dialog's, which is fixed and scrolls its own content. */
function centred(anchor, element, holder) {
  const at = anchor.getBoundingClientRect();
  const box = element.getBoundingClientRect();
  const left = Math.max(8, Math.min(at.left + (at.width - box.width) / 2,
    document.documentElement.clientWidth - box.width - 8));
  const frame = holder === document.body ? null : holder.getBoundingClientRect();
  const [x, y] = frame === null ? [window.scrollX, window.scrollY]
    : [holder.scrollLeft - frame.left - holder.clientLeft, holder.scrollTop - frame.top - holder.clientTop];
  return { left: x + left + "px", top: y + at.top + (at.height - box.height) / 2 + "px" };
}

/* The paper scrolls under the page, not the page, so a popover goes along with its
   anchor as any container scrolls, on the side it stands and as tall as it was
   placed, since only its place changes. A keyboard coming up or going changes the
   room, so it is placed anew, side and height, by all it holds. So does a phone's
   bar sliding away under a scroll, which not every browser reports as a resize.
   `apply` takes the place, as the element's own style or as an island's binding.
   Returns what stops the following. */
function following(anchor, element, apply) {
  let room = window.visualViewport.height;
  const resize = () => {
    room = window.visualViewport.height;
    apply(placement(anchor, { width: element.offsetWidth,
      height: Math.max(element.offsetHeight, element.scrollHeight) }));
  };
  const move = () => {
    if (window.visualViewport.height !== room) return resize();
    const below = element.style.transform === "none";
    const { left, top } = placement(anchor, element.getBoundingClientRect(), below);
    apply({ left, top });
  };
  document.addEventListener("scroll", move, true);
  window.visualViewport.addEventListener("resize", resize);
  return () => {
    document.removeEventListener("scroll", move, true);
    window.visualViewport.removeEventListener("resize", resize);
  };
}

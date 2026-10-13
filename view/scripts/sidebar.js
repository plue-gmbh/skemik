/* The sidebar's behaviour on every admin page (`partials/sidebar`, and the menu
   button of `partials/header` that opens it as a drawer on narrow screens). */

/* Every sidebar link loads a page, which draws the list from its top again. The
   list's scroll is the viewer's and this tab's, so it is kept here and not by
   the server; with nothing kept, the open collection is at least brought into view. */
function keepSidebarScroll(list) {
  let kept = null;
  try { kept = sessionStorage.getItem("sidebar-scroll"); } catch { kept = null; }
  if (kept !== null) list.scrollTop = Number(kept);
  list.querySelector('[aria-current="page"]')?.scrollIntoView({ block: "nearest" });
  window.addEventListener("pagehide", () => {
    try { sessionStorage.setItem("sidebar-scroll", String(list.scrollTop)); } catch { /* private window */ }
  });
}

/* On narrow screens the sidebar is a drawer, open while focus is in it or on its
   button. A tap outside only closes it, so the press that dismisses it never also
   follows a link beneath; widening the window closes it too, or the listener
   would keep eating the first click on the page. */
function mountDrawer(button) {
  const sidebar = document.getElementById(button.getAttribute("aria-controls"));
  const inside = (node) => sidebar.contains(node) || button.contains(node);
  const outside = (event) => inside(event.target)
    || (event.preventDefault(), event.stopPropagation(), toggle(false));
  const toggle = (open) => {
    sidebar.classList.toggle("is-open", open);
    button.setAttribute("aria-expanded", String(open));
    document[open ? "addEventListener" : "removeEventListener"]("click", outside, true);
    if (open) (sidebar.querySelector('[aria-current="page"]') ?? sidebar.querySelector("a"))?.focus();
  };
  const escape = (event) => event.key === "Escape" && sidebar.classList.contains("is-open")
    && (event.preventDefault(), toggle(false), button.focus());
  [sidebar, button].forEach((each) => each.addEventListener("keydown", escape));
  sidebar.addEventListener("focusout", (event) => event.relatedTarget
    && !inside(event.relatedTarget) && toggle(false));
  button.addEventListener("click", () => toggle(!sidebar.classList.contains("is-open")));
  window.matchMedia("(max-width: 820px)").addEventListener("change", () => toggle(false));
}

document.querySelectorAll(".sidebar-list").forEach(keepSidebarScroll);
document.querySelectorAll(".header-menu").forEach(mountDrawer);

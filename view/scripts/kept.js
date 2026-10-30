/* What this tab keeps for the page it loads next, since the server draws every
   page from its top: the focus a select that loads an address had, so the page it
   loads gives it back, and where the paper and the table were scrolled when a
   screen is redrawn at its own address, after a field moved or a save. Each is
   kept in the tab's session storage and read once, as `view/scripts/app.js` ends. */

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

/* The paper scrolls on every screen size, and the table inside itself. */
const scrollers = () => [document.querySelector(".paper"), document.querySelector(".sheet-scroll")];

function keepScroll() {
  const [paper, sheet] = scrollers();
  const kept = { at: location.pathname, paper: paper?.scrollTop ?? 0, sheet: sheet?.scrollLeft ?? 0 };
  try { sessionStorage.setItem("scroll", JSON.stringify(kept)); } catch { /* private window */ }
}

function restoreScroll() {
  let kept = null;
  try {
    kept = JSON.parse(sessionStorage.getItem("scroll"));
    sessionStorage.removeItem("scroll");
  } catch { kept = null; }
  if (kept?.at !== location.pathname) return;
  const [paper, sheet] = scrollers();
  if (paper) paper.scrollTop = kept.paper;
  if (sheet) sheet.scrollLeft = kept.sheet;
}

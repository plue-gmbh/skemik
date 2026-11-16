/* What sticks at the paper's top (`data-sticks`): a collection's tools and a record's. A group draws its edge
   only while it is stuck over what scrolls under it, which a stylesheet cannot tell in every browser. The
   paper scrolls on a wide screen and the window on a narrow one. */
const stickingGroups = [...document.querySelectorAll("[data-sticks]")];

function stuck(group) {
  const paper = group.closest(".paper");
  const port = getComputedStyle(paper).overflowY === "visible" ? 0 : paper.getBoundingClientRect().top;
  return group.getBoundingClientRect().top <= port + parseFloat(getComputedStyle(group).top) + 0.5;
}

function settleGroups() {
  stickingGroups.forEach((group) => group.classList.toggle("is-stuck", stuck(group)));
}

settleGroups();
document.addEventListener("scroll", settleGroups, { capture: true, passive: true });
window.addEventListener("resize", settleGroups);

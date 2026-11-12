/* What sticks at the paper's top (`data-sticks`): the record's head or its tools, and the collection's query
   row. Its height pads the scrolling, so a field Tab reaches is never left under it. It draws its edge only
   while it is stuck over what scrolls under it, which a stylesheet cannot tell in every browser. The paper
   scrolls on a wide screen and the window on a narrow one. */
const stickingBars = [...document.querySelectorAll("[data-sticks]")];

/* A bar that does not stick has `top: auto`, which is no number, so it is never stuck. */
function stuck(bar) {
  const paper = bar.closest(".paper");
  const port = getComputedStyle(paper).overflowY === "visible" ? 0 : paper.getBoundingClientRect().top;
  return bar.getBoundingClientRect().top <= port + parseFloat(getComputedStyle(bar).top) + 0.5;
}

function settleBars() {
  stickingBars.forEach((bar) => bar.classList.toggle("is-stuck", stuck(bar)));
}

const barSizes = new ResizeObserver(() => {
  document.documentElement.style.setProperty("--sticking",
    Math.max(0, ...stickingBars.map((bar) => bar.offsetHeight)) + "px");
  settleBars();
});
stickingBars.forEach((bar) => barSizes.observe(bar));
document.addEventListener("scroll", settleBars, { capture: true, passive: true });

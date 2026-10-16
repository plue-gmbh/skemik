/* The trash's ticks and the bar over them (`pages/trash`), no island: the
   screen is drawn whole by the server and only the lot changes. The ticked ids
   go into the restore form and the purge dialog, whose sentences count over the
   lot as it changes, from the counts each row carries, so the dialog is right
   the moment it opens and asks the server nothing. */

function mountTrash(area) {
  const [bar, all, dialog] = ["[data-ticked]", "[data-tick-all]", "#purge-ticked"]
    .map((each) => document.querySelector(each));
  const boxes = () => [...area.querySelectorAll("[data-tick]")];
  const tick = (box) => {
    if (box.matches("[data-tick-all]")) boxes().forEach((each) => { each.checked = box.checked; });
    const rows = boxes().filter((each) => each.checked).map((each) => each.closest("[data-record]"));
    all.checked = rows.length > 0 && rows.length === boxes().length;
    all.indeterminate = rows.length > 0 && !all.checked;
    bar.hidden = rows.length === 0;
    document.querySelectorAll("[data-ticked-ids]").forEach((input) => {
      input.value = rows.map((row) => row.dataset.record).join(",");
    });
    announce(counted(bar.querySelector("[data-ticked-count]"), rows.length));
    if (dialog) lot(dialog, rows);
  };
  area.addEventListener("change", (event) => event.target.matches("[data-tick], [data-tick-all]")
    && tick(event.target));
  bar.querySelector("[data-ticked-clear]").addEventListener("click", () => {
    all.checked = false;
    tick(all);
    all.focus();
  });
}

function counted(count, n) {
  count.title = fill(count.dataset.long, { n });
  count.querySelector(".ticked-short").textContent = fill(count.dataset.short, { n });
  count.querySelector(".ticked-long").textContent = count.title;
  return count.title;
}

/* Over the lot: the references pointing at any of it, and the log's name
   redaction only where a ticked person changed records, its box cleared when
   it hides, since a hidden box still sends. */
function lot(dialog, rows) {
  const title = dialog.querySelector("h2");
  title.textContent = fill(title.dataset.text, { n: rows.length });
  const pointing = rows.reduce((sum, row) => sum + Number(row.dataset.pointing), 0);
  const references = dialog.querySelector("[data-pointing]");
  references.textContent = pointing === 0 ? references.dataset.none : fill(references.dataset.text, { n: pointing });
  const acted = dialog.querySelector("[data-acted]");
  acted.hidden = !rows.some((row) => row.dataset.acted !== undefined);
  if (acted.hidden) acted.querySelector("input").checked = false;
}

document.querySelectorAll("[data-trash]:has([data-tick])").forEach(mountTrash);

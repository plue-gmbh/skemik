/* The query bar (`view/partials/query-bar.php`), whose fields are the address. A
   choice loads it, spelled with what its field is narrowed to already, so it takes
   that field's place in the address and no other's; typing redraws the view in place so focus stays in the field,
   and with it what else the server drew of the address (`drawn`) — unless edits
   are pending, which only a deliberate Enter may leave, through the save bar's
   leaving guard, and typing says so. An answer with no view to take, an update's
   waiting page or a login, is loaded as the address, so it shows; a question
   saved takes the address as it then stands; a connection lost leaves the view
   as it stood. The field is busy while a redraw is out, and typing on stops it
   (`busy`). Loaded before `view/scripts/app.js`, which mounts it and whose
   `chosen`, `busy`, `mountIslands` and `mountOutsideClose` it calls. */
function mountQueryBar(form, mounted) {
  const search = form.querySelector("[data-query-search]");
  const sortValue = form.querySelector("[data-query-sort-value]");
  const address = () => {
    const params = new URLSearchParams(new FormData(form));
    [...params.keys()].forEach((key) => params.get(key) === "" && params.delete(key));
    return location.pathname + (params.toString() === "" ? "" : "?" + params);
  };
  const go = () => location.assign(address());
  /* A question saved takes the address as it then stands, since typing moves it without drawing its dialog anew. */
  const keeping = () => {
    const saved = document.querySelector("[data-question-query]");
    saved?.form.addEventListener("submit", () => { saved.value = address().split("?")[1] ?? ""; });
  };
  /* Beside the view: the view switch's links and the feed panel's address, which carry the query, each chip,
     whose link takes its value away and keeps the search typed since, and the mark before them that saves or
     deletes the question the address now is, with the dialog or the form after the bar it works through. */
  const drawn = (page) => {
    const links = page.querySelectorAll("[data-view-links]");
    document.querySelectorAll("[data-view-links]").forEach((each, index) => each.replaceWith(links[index]));
    const feed = page.querySelector("[data-feed-address]");
    if (feed) {
      document.querySelector("[data-feed-address]")?.replaceWith(feed);
      document.dispatchEvent(new Event("feed-address"));
    }
    document.querySelectorAll(".query-narrowed, #question-save, #question-remove").forEach((each) => each.remove());
    form.append(...page.querySelectorAll(".query-narrowed"));
    form.parentElement.after(...page.querySelectorAll("#question-save, #question-remove"));
    document.querySelectorAll("#question-save").forEach(mountOutsideClose);
    keeping();
  };
  let typing = 0;
  const redraw = async () => {
    if (mounted.current?.sheet.count > 0) return toast(mounted.current.sheet.words["sheet.search_held"], search);
    const url = address();
    const text = await busy(search, (signal) => fetch(url, { signal }).then((response) => response.text())
      .catch(() => null));
    if (text === null || url !== address()) return;
    const page = new DOMParser().parseFromString(text, "text/html");
    const view = page.querySelector("[data-view]");
    const count = page.querySelector("[data-query-count]");
    if (!view) return go();
    mounted.current?.app.unmount();
    document.querySelector("[data-view]").replaceWith(view);
    form.querySelector("[data-query-count]").textContent = count?.textContent ?? "";
    drawn(page);
    history.replaceState(null, "", url);
    mounted.current = mountIslands();
  };
  form.addEventListener("submit", (event) => { event.preventDefault(); go(); });
  const choose = form.querySelector("[data-query-choose]");
  if (choose) chosen(choose, "[data-query-choose]", (select) => {
    new URLSearchParams(select.value).forEach((value, name) => {
      [...form.querySelectorAll("[data-query-filter]")].find((input) => input.name === name)?.remove();
      form.append(Object.assign(document.createElement("input"), { type: "hidden", name, value }));
    });
    go();
  });
  chosen(form.querySelector("[data-query-sort]"), "[data-query-sort]", (select) => {
    const { value, dataset } = select;
    sortValue.value = value === "" || dataset.whole !== undefined ? value : "-" + value;
    go();
  });
  form.querySelector("[data-query-direction]")?.addEventListener("click", () => {
    sortValue.value = sortValue.value.startsWith("-") ? sortValue.value.slice(1) : "-" + sortValue.value;
    go();
  });
  keeping();
  search.addEventListener("input", () => {
    window.clearTimeout(typing);
    typing = window.setTimeout(redraw, 300);
  });
  const clear = () => {
    search.value = "";
    redraw();
  };
  search.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || search.value === "") return;
    event.preventDefault();
    clear();
  });
  form.querySelector("[data-query-clear]").addEventListener("click", () => {
    clear();
    search.focus();
  });
}

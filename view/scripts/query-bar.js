/* The query bar (`view/partials/query-bar.php`), whose fields are the address. A
   choice loads it; typing redraws the view in place so focus stays in the field,
   and the view switch's links and the feed panel's address, which carry the
   query — unless edits are pending, which only a deliberate Enter may leave,
   through the save bar's leaving guard. Loaded before `view/scripts/app.js`,
   which mounts it and whose `chosen` and `mountIslands` it calls. */
function mountQueryBar(form, mounted) {
  const search = form.querySelector("[data-query-search]");
  const sortValue = form.querySelector("[data-query-sort-value]");
  const address = () => {
    const params = new URLSearchParams(new FormData(form));
    [...params.keys()].forEach((key) => params.get(key) === "" && params.delete(key));
    return location.pathname + (params.toString() === "" ? "" : "?" + params);
  };
  const go = () => location.assign(address());
  let typing = 0;
  const redraw = async () => {
    if (mounted.current?.sheet.count > 0) return;
    const url = address();
    const page = new DOMParser().parseFromString(await (await fetch(url)).text(), "text/html");
    const [view, count] = [page.querySelector("[data-view]"), page.querySelector("[data-query-count]")];
    if (!view || url !== address()) return;
    mounted.current?.app.unmount();
    document.querySelector("[data-view]").replaceWith(view);
    const links = page.querySelectorAll("[data-view-links]");
    document.querySelectorAll("[data-view-links]").forEach((each, index) => each.replaceWith(links[index]));
    form.querySelector("[data-query-count]").textContent = count?.textContent ?? "";
    const feed = page.querySelector("[data-feed-address]");
    if (feed) {
      document.querySelector("[data-feed-address]")?.replaceWith(feed);
      document.dispatchEvent(new Event("feed-address"));
    }
    history.replaceState(null, "", url);
    mounted.current = mountIslands();
  };
  form.addEventListener("submit", (event) => { event.preventDefault(); go(); });
  chosen(form.querySelector("[data-query-choose]"), "[data-query-choose]", (select) => {
    form.querySelectorAll("[data-query-filter]").forEach((input) => input.remove());
    new URLSearchParams(select.value).forEach((value, name) => {
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
    sortValue.value = sortValue.value.startsWith("-") ? sortValue.value.slice(1) : "";
    go();
  });
  search.addEventListener("input", () => {
    window.clearTimeout(typing);
    typing = window.setTimeout(redraw, 250);
  });
  search.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || search.value === "") return;
    event.preventDefault();
    search.value = "";
    redraw();
  });
}

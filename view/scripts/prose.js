/* A Markdown field's help on the record screen (`view/pages/record.php`): the
   marks put in or taken out at the cursor, a web link, Insert link, and a preview
   beside the field. The preview is drawn by the server, since the renderer the
   `.html` uses is PHP's and a second one here would disagree with it some day.
   Each tool puts its text in through an `input` event, so the save bar and the
   preview see it as typing. `view/scripts/app.js` owns what every screen shares
   — `popover`, `focusField`, `toast`, `fill`. */

const previews = new WeakMap();

/* The preview of what the textarea holds, or of the authored words it falls back
   to when empty; a reply to an earlier text than the field's is dropped. */
function preview(area) {
  const shown = area.closest(".record-prose-edit")?.querySelector("[data-preview]");
  if (!shown) return;
  const text = () => area.value || (area.dataset.prose === "authored" ? area.placeholder : "");
  window.clearTimeout(previews.get(area));
  previews.set(area, window.setTimeout(async () => {
    const sent = text();
    const csrf = JSON.parse(document.getElementById("sheet-data").textContent).csrf;
    const answer = await fetch("/preview", { method: "POST", body: sent,
      headers: { "Content-Type": "text/markdown; charset=utf-8", "X-CSRF-Token": csrf } }).catch(() => null);
    if (answer?.ok && sent === text()) shown.innerHTML = await answer.text();
  }, 300));
}

const MARKS = { heading: ["## ", /^#{1,6} /], quote: ["> ", /^> ?/], bold: ["**"], italic: ["*"], code: ["`"] };

/* A line's mark on every line the selection touches, off again where each
   carries it; the heading is `##`, since the record's name is the page's `<h1>`.
   A cursor keeps its place in its line, a selection takes the lines. At the
   text's start no newline is looked for, since `lastIndexOf` reads -1 as 0. */
function prefix(area, mark, pattern) {
  const [value, from, to] = [area.value, area.selectionStart, area.selectionEnd];
  const start = from === 0 ? 0 : value.lastIndexOf("\n", from - 1) + 1;
  const stop = value.indexOf("\n", Math.max(start, to > from ? to - 1 : to));
  const end = stop === -1 ? value.length : stop;
  const lines = value.slice(start, end).split("\n");
  const off = lines.every((line) => pattern.test(line));
  const marked = lines.map((line) => (off ? line.replace(pattern, "") : pattern.test(line) ? line : mark + line));
  area.setRangeText(marked.join("\n"), start, end, "select");
  if (from === to) {
    const at = Math.max(start, from + marked[0].length - lines[0].length);
    area.setSelectionRange(at, at);
  }
}

/* Marks around the selection, inside its blank edges since an asterisk beside
   a space is no emphasis; around nothing, a pair the cursor stands between; off
   again where the selection is already marked or stands inside the marks. A
   bold text is no italic one, but the second asterisk of an empty pair is its
   closing one. */
function wrap(area, marks) {
  const [value, from, to, n] = [area.value, area.selectionStart, area.selectionEnd, marks.length];
  const chosen = value.slice(from, to);
  const [lead, trail] = [chosen.length - chosen.trimStart().length, chosen.length - chosen.trimEnd().length];
  const [inner, at, until] = [chosen.slice(lead, chosen.length - trail), from + lead, to - trail];
  const marked = (text, before) => text.length >= 2 * n && text.startsWith(marks) && text.endsWith(marks)
    && !(marks === "*" && ((text.length > 2 && text[1] === "*") || before === "*"));
  if (marked(inner, value[at - 1])) return area.setRangeText(inner.slice(n, -n), at, until, "select");
  if (marked(value.slice(at - n, until + n), value[at - n - 1])) {
    return area.setRangeText(inner, at - n, until + n, "select");
  }
  area.setRangeText(marks + inner + marks, at, until, "select");
  area.setSelectionRange(at + n, until + n);
}

function mark(button) {
  const area = button.closest("[data-field]").querySelector("[data-prose]");
  const [marks, pattern] = MARKS[button.dataset.mark];
  pattern ? prefix(area, marks, pattern) : wrap(area, marks);
  area.dispatchEvent(new Event("input", { bubbles: true }));
  focusField(area, button);
}

/* A web link around the selection, its address asked for; the address stands as
   the words where nothing is selected. The renderer allows the scheme, so one it
   refuses shows as plain words in the preview. */
function linkWeb(button, words) {
  const area = button.closest("[data-field]").querySelector("[data-prose]");
  const [from, to] = [area.selectionStart, area.selectionEnd];
  const input = Object.assign(document.createElement("input"), { type: "url", className: "popover-input",
    placeholder: "https://", autocomplete: "off" });
  input.setAttribute("aria-label", words["record.link_address"]);
  const label = Object.assign(document.createElement("p"), { className: "popover-label",
    textContent: words["record.link"] });
  const { element, close } = popover(button, [label, input]);
  input.addEventListener("keydown", (event) => {
    const address = input.value.trim();
    if (event.key !== "Enter" || address === "") return;
    event.preventDefault();
    area.setRangeText(`[${area.value.slice(from, to) || address}](${address})`, from, to, "end");
    area.dispatchEvent(new Event("input", { bubbles: true }));
    close();
    focusField(area, button);
  });
  focusField(input, element);
}

/* ⇢ Insert link: a record search across what this account may read, in
   the record's locale so a work is offered once, inserting `[selection or
   label](skemik:id)` at the cursor — a link that survives a slug change because
   it names the id. */
function insertLink(button, words) {
  const area = button.closest("[data-field]").querySelector("[data-prose]");
  const id = button.closest("[data-record]").dataset.record;
  const locale = JSON.parse(document.getElementById("sheet-data").textContent).rows
    .find((row) => row.id === id)?.locale ?? "";
  const [from, to] = [area.selectionStart, area.selectionEnd];
  const search = Object.assign(document.createElement("input"), { type: "search", className: "popover-input",
    placeholder: words["record.link_search"], autocomplete: "off" });
  search.setAttribute("aria-label", words["record.link_search"]);
  const list = Object.assign(document.createElement("div"), { className: "options" });
  const label = Object.assign(document.createElement("p"), { className: "popover-label",
    textContent: words["record.link_insert"] });
  const { element, close } = popover(button, [label, search, list]);
  const choose = (hit) => {
    const text = `[${area.value.slice(from, to) || hit.label}](skemik:${hit.id})`;
    area.setRangeText(text, from, to, "end");
    area.dispatchEvent(new Event("input", { bubbles: true }));
    close();
    focusField(area, button);
    toast(fill(words["record.link_done"], { label: hit.label }), button);
  };
  const paint = async () => {
    const typed = search.value;
    const address = "/search?" + new URLSearchParams({ q: typed, in: locale });
    const hits = typed.trim() === "" ? []
      : (await (await fetch(address, { headers: { Accept: "application/json" } })).json()).hits ?? [];
    if (typed !== search.value) return;
    list.replaceChildren(...hits.map((hit) => {
      const option = Object.assign(document.createElement("button"), { type: "button", className: "option" });
      option.append(glyphMark(hit.glyph), Object.assign(document.createElement("span"), { textContent: hit.label }),
        Object.assign(document.createElement("span"), { className: "option-term", textContent: hit.collection }));
      option.addEventListener("click", () => choose(hit));
      return option;
    }));
    if (hits.length === 0 && typed.trim() !== "") {
      list.append(Object.assign(document.createElement("p"), { className: "popover-hint",
        textContent: words["record.link_none"] }));
    }
  };
  let typing = 0;
  search.addEventListener("input", () => { window.clearTimeout(typing); typing = window.setTimeout(paint, 200); });
  search.addEventListener("keydown", (event) => {
    if (event.key === "Enter") list.querySelector("button")?.click();
  });
  focusField(search, element);
}

document.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-mark], [data-link], [data-link-insert]");
  if (!trigger) return;
  const words = JSON.parse(document.getElementById("sheet-data").textContent).words;
  if (trigger.dataset.mark !== undefined) mark(trigger);
  if (trigger.dataset.link !== undefined) linkWeb(trigger, words);
  if (trigger.dataset.linkInsert !== undefined) insertLink(trigger, words);
});

document.addEventListener("input", (event) => event.target.matches("[data-prose]") && preview(event.target));

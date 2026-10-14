/* The save bar's island (`view/partials/save-bar.php` holds its contract): a
   view's records, found by `[data-record]` and their cells by `[data-field]`
   however the view lays them out, or the record screen's one record, edited
   through the cell editor, the record's textareas and the spine dialog, and sent
   by `PATCH` when saved; the sending is `view/scripts/sheet-sending.js` and ticked
   records are `view/scripts/ticks.js`, both mixed in. `view/scripts/app.js` mounts
   it and owns what every screen shares — `fill`, `announce`, `toast`. */

function mountSheet(root) {
  const data = JSON.parse(document.getElementById("sheet-data").textContent);
  const table = document.querySelector("[data-sheet]");
  if (!table) return null;
  const [wordsIn, dated] = [table.dataset.words, table.dataset.dated];
  const spineData = JSON.parse(document.getElementById("spine-data")?.textContent ?? "null");
  const rows = new Map(data.rows.map((row) => [row.id, row]));
  const isNew = (id) => id.startsWith("new-");
  const filtered = new URLSearchParams(location.search).get("locale");
  let made = 0;
  let last = null;
  const loaded = new Map();
  const cellOf = (id, key) => table.querySelector(`[data-record="${id}"] [data-field="${key}"]`);
  const fieldOf = (key) => data.fields.find((field) => field.key === key);
  const plain = (value) => (Array.isArray(value) && value.length === 0 ? ""
    : value !== null && typeof value === "object" ? JSON.stringify(value)
    : String(value ?? "").replace(/^(\d{4}-\d\d-\d\dT\d\d:\d\d):00$/, "$1"));
  const same = (a, b) => plain(a) === plain(b);
  const isSpine = (key) => key.startsWith("spine.");
  const original = (id, key) => (isSpine(key) ? spineData.rows[id][key.slice(6)] : rows.get(id).values[key]);
  const chipOf = (id) => document.querySelector(`[data-spine="${id}"]`);
  const sheet = { table, data, spineData, rows, loaded, wordsIn, dated, filtered, isNew, cellOf, fieldOf, same, isSpine,
    chipOf };

  const app = Vue.createApp({
    template: "#save-bar-template",
    mixins: [ticked(table, data, spineData), sending(sheet)],
    data: () => ({ pending: {}, editing: null, spine: null, moved: null, conflicts: [], refusals: [],
      saving: false, done: false, writes: 0, words: data.words, language: data.language }),
    computed: {
      count() {
        return Object.values(this.pending).reduce((sum, values) => sum + Object.keys(values).length, 0);
      },
      conflict() { return this.conflicts[0] ?? null; },
      saved() { return this.done && this.count === 0; },
      open() { return this.count > 0 || this.done || this.conflict !== null || this.refusals.length > 0; },
      sentence() {
        const [words, conflict] = [this.words, this.conflict];
        if (conflict) {
          return fill(words["sheet.conflict"], { label: rows.get(conflict.id).label, name: conflict.body.by });
        }
        if (this.saving || this.refusals.length > 0) {
          return this.saving ? words["sheet.saving"] : this.refusals.join(" · ");
        }
        if (this.count > 0) return fill(words["sheet.pending"], { n: this.count });
        return this.done ? words["sheet.saved"] : "";
      },
    },
    watch: { sentence(text) { if (text) announce(text); } },
    mounted() {
      table.addEventListener("click", (event) => this.clicked(event));
      this.adding = (event) => event.target.closest("[data-add-row]") && this.add();
      document.addEventListener("click", this.adding);
      table.querySelectorAll("[data-prose]").forEach((area) => {
        const [id, key] = [area.closest("[data-record]").dataset.record, area.closest("[data-field]").dataset.field];
        area.addEventListener("input", () => this.change(id, key, area.value === "" ? null : area.value));
      });
      this.opening = (event) => {
        const chip = event.target.closest("[data-spine]");
        if (chip && spineData?.rows[chip.dataset.spine]) this.openSpine(chip);
      };
      document.addEventListener("click", this.opening);
      window.addEventListener("beforeunload", (event) => this.count > 0 && event.preventDefault());
      document.addEventListener("keydown", (event) => this.shortcut(event));
    },
    unmounted() {
      document.removeEventListener("click", this.opening);
      document.removeEventListener("click", this.adding);
    },
    methods: {
      clicked(event) {
        const button = event.target.closest("[data-edit]");
        if (!button) return;
        const id = button.closest("[data-record]").dataset.record;
        const key = button.closest("[data-field]").dataset.field;
        fieldOf(key).kind === "boolean" ? this.change(id, key, this.current(id, key) !== true) : this.edit(id, key);
      },
      current(id, key) {
        return key in (this.pending[id] ?? {}) ? this.pending[id][key] : original(id, key);
      },
      edit(id, key, all = false) {
        const [field, cell] = [fieldOf(key), cellOf(id, key)];
        if (field.kind === "boolean") return cell.querySelector("[data-edit]").focus();
        cell.dataset.editing = "";
        const terms = data.terms[rows.get(id).locale] ?? {};
        this.editing = { id, field, cell, terms, value: all ? null : this.current(id, key), collection: data.collection,
          locale: rows.get(id).locale, hint: rows.get(id).hints?.[key], zone: data.zone, words: wordsIn, all };
      },
      close() {
        const { id, field, cell, all } = this.editing;
        delete cell.dataset.editing;
        this.editing = null;
        last = cell;
        return { id, key: field.key, cell, all };
      },
      commit(value, move) {
        const { id, key, cell, all } = this.close();
        (all ? this.takers(key) : [id]).forEach((each) => this.change(each, key, value));
        if (all) return this.$refs.setter?.focus();
        move ? this.move(id, key, move) : cell.querySelector("[data-edit]").focus();
      },
      cancel() {
        this.close().cell.querySelector("[data-edit]").focus();
      },
      move(id, key, [down, across]) {
        const ids = [...table.querySelectorAll("[data-record]")].map((row) => row.dataset.record);
        const on = (field) => cellOf(id, field.key)?.querySelector(":scope:not([data-off]) [data-edit]");
        const keys = data.fields.filter(on).map((field) => field.key);
        const next = [ids[ids.indexOf(id) + down], keys[keys.indexOf(key) + across]];
        const target = next[0] && next[1] ? cellOf(...next)?.querySelector(":scope:not([data-off]) [data-edit]") : null;
        target ? this.edit(...next) : cellOf(id, key).querySelector("[data-edit]").focus();
      },
      change(id, key, value) {
        const values = { ...this.pending[id] };
        same(original(id, key), value) ? delete values[key] : (values[key] = value);
        Object.keys(values).length > 0 ? (this.pending[id] = values) : delete this.pending[id];
        this.done = false;
        this.draw(id, key);
      },
      /* A prose field is its own textarea: only its mark follows, and a discard puts
         its text back, said as an `input` so its height and its preview follow; only
         where the text differs, since the event comes back here as a change. */
      draw(id, key) {
        const cell = cellOf(id, key);
        if (key === "additional_type") showKinds(table.querySelector(`[data-record="${id}"]`), this.current(id, key));
        if (isSpine(key) || !cell) return isSpine(key) && this.drawSpine(id);
        const shown = cell.querySelector(".cell-shown");
        const area = cell.querySelector("[data-prose]");
        if (area) {
          const pending = key in (this.pending[id] ?? {});
          pending ? (cell.dataset.pending = "") : delete cell.dataset.pending;
          const stored = rows.get(id).values[key] ?? "";
          if (!pending && area.value !== stored) {
            area.value = stored;
            area.dispatchEvent(new Event("input", { bubbles: true }));
          }
          return;
        }
        loaded.has(id + key) || loaded.set(id + key, shown.innerHTML);
        if (!(key in (this.pending[id] ?? {}))) {
          shown.innerHTML = loaded.get(id + key);
          return delete cell.dataset.pending;
        }
        cell.dataset.pending = "";
        shown.replaceChildren(cellShown(fieldOf(key), this.pending[id][key], data.terms[rows.get(id).locale] ?? {},
          this.language, this.words));
      },
      /* The chip marks a pending spine edit and shows a pending slug. */
      drawSpine(id) {
        const [chip, values] = [chipOf(id), this.pending[id] ?? {}];
        if (!chip) return;
        Object.keys(values).some(isSpine) ? (chip.dataset.pending = "") : delete chip.dataset.pending;
        chip.querySelector("[data-spine-slug]").textContent = values["spine.slug"] ?? spineData.rows[id].slug;
      },
      openSpine(chip) {
        const id = chip.dataset.spine;
        this.spine = { id, chip, record: spineData.rows[id], common: spineData, csrf: data.csrf };
      },
      closeSpine() {
        const id = this.spine.id;
        this.spine = null;
        chipOf(id)?.focus();
      },
      /* A new record is pending like any value, and made by a `POST` when saved,
         ahead of a grid's or the cards' last tile, which adds or uploads. */
      add() {
        const row = document.querySelector("[data-new-row]").content.firstElementChild.cloneNode(true);
        const id = "new-" + (made += 1);
        const holder = table.querySelector("[data-rows]");
        row.dataset.record = id;
        holder.insertBefore(row, holder.querySelector(":scope > [data-rows-end]"));
        document.querySelector("[data-sheet-empty]")?.remove();
        rows.set(id, { id, version: 0, locale: filtered ?? data.locale, label: this.words["sheet.new"], values: {} });
        const first = data.fields.find((field) => cellOf(id, field.key)?.querySelector("[data-edit]"));
        first && this.edit(id, first.key);
      },
      discard() {
        const pending = this.pending;
        this.pending = {};
        [...rows.keys()].filter(isNew).forEach((id) => {
          table.querySelector(`[data-record="${id}"]`).remove();
          rows.delete(id);
          delete pending[id];
        });
        Object.entries(pending).forEach(([id, values]) => {
          Object.keys(values).forEach((key) => this.draw(id, key));
        });
        this.unmarkAll();
        this.refusals = [];
        toast(this.words["sheet.discarded"]);
        this.keepFocus();
      },
      /* Ctrl or ⌘ S saves from anywhere on the screen, since the bar is the last stop of a
         long page. A field still open is left first, which commits it; the browser's own
         Save page is never what is meant here. */
      shortcut(event) {
        if (event.key.toLowerCase() !== "s" || !(event.ctrlKey || event.metaKey) || event.altKey) return;
        event.preventDefault();
        if (this.editing) document.activeElement?.blur();
        this.$nextTick(() => this.count > 0 && !this.saving && this.conflicts.length === 0 && this.save());
      },
      /* A save or a discard takes the bar's buttons, and the focus on them, along:
         it goes to what the bar still offers, else back to the cell last edited. */
      keepFocus() {
        this.$nextTick(() => {
          if (document.activeElement !== document.body) return;
          (document.querySelector(".save-bar button:not(:disabled)")
            ?? (last?.isConnected ? last : table).querySelector("[data-edit]"))?.focus();
        });
      },
    },
  });
  app.component("cell-editor", cellEditor);
  app.component("spine-dialog", spineDialog);
  return { app, sheet: app.mount(root) };
}

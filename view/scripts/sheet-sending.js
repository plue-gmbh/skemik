/* The save bar's sending half, mixed into the sheet island (`view/scripts/sheet.js`),
   which hands it what both halves read. Each pending record goes by itself, so
   each is checked, versioned and logged as a single edit is, and a refusal or a
   conflict names its record without holding back the others. */

/* On a labelled thing's record screen in another locale, what is edited is that locale's words. */
function withWords(body, locale) {
  const { spine, ...words } = body;
  return locale ? { ...(spine ? { spine } : {}), l10n: { [locale]: words } } : body;
}

function sending(sheet) {
  const { table, data, spineData, rows, loaded, wordsIn, dated, filtered, isNew, cellOf, fieldOf, same, isSpine,
    chipOf } = sheet;
  return {
    methods: {
      /* A moved record screen is read afresh, and a timeline a date refiled. */
      async save() {
        this.saving = true;
        this.refusals = [];
        this.unmarkAll();
        for (const id of Object.keys(this.pending)) {
          await this.send(id);
        }
        this.saving = false;
        const clean = this.count === 0 && this.conflicts.length === 0;
        if (clean && this.moved) return location.replace(this.moved);
        this.done = clean;
        this.keepFocus();
        window.setTimeout(() => { this.done = false; }, 1800);
      },
      async send(id) {
        const row = rows.get(id);
        const sent = { ...this.pending[id] };
        const body = withWords(withSpine(sent), wordsIn);
        const query = new URLSearchParams(wordsIn ? { words: wordsIn }
          : isNew(id) && filtered ? { locale: filtered } : {});
        if (isNew(id) && table.dataset.sheet) query.set("view", table.dataset.sheet);
        const address = `/c/${encodeURIComponent(data.collection)}` + (isNew(id) ? "" : `/${id}`)
          + (query.toString() === "" ? "" : "?" + query);
        try {
          const headers = { "Content-Type": "application/json", Accept: "application/json",
            "X-CSRF-Token": data.csrf, ...(isNew(id) ? {} : { "If-Match": `"${row.version}"` }) };
          const response = await fetch(address,
            { method: isNew(id) ? "POST" : "PATCH", headers, body: JSON.stringify(body) });
          const answer = await response.json();
          if (response.ok && (dated in sent || (dated === "" && isNew(id)))) this.moved = location.href;
          if (response.ok && isNew(id)) return this.made(id, answer);
          if (response.ok) return this.stored(id, answer, sent);
          if (response.status === 412) return this.conflicts.push({ id, body: answer });
          (answer.violations ?? [{ field: "", reason: answer.error }]).forEach((each) => this.refuse(id, each));
        } catch (failure) {
          this.refuse(id, { field: "", reason: String(failure.message ?? failure) });
        }
      },
      /* What was typed while the save was on its way stays pending, drawn over the new value. */
      stored(id, body, sent) {
        Object.assign(rows.get(id), { version: body.version, values: body.values });
        const before = spineData.rows[id].address;
        spineData.rows[id] = body.spine;
        this.writes += 1;
        if (table.closest("[data-record-screen]") && Object.keys(sent ?? {}).some(isSpine)) {
          this.moved = body.spine.address;
        } else if (before !== body.spine.address) {
          relink(table.querySelector(`[data-record="${id}"]`), before, body.spine.address);
        }
        const values = { ...this.pending[id] };
        Object.keys(values).forEach((key) => {
          if (!sent || same(values[key], sent[key])) delete values[key];
        });
        Object.keys(values).length > 0 ? (this.pending[id] = values) : delete this.pending[id];
        Object.entries(body.cells).forEach(([key, html]) => {
          const cell = cellOf(id, key);
          const shown = cell?.querySelector(".cell-shown") ?? cell?.querySelector(".cell");
          loaded.set(id + key, html);
          if (!cell || key in values) return;
          delete cell.dataset.pending;
          shown ? (shown.innerHTML = html) : this.draw(id, key);
        });
        const chip = table.querySelector(`[data-spine="${id}"]`);
        chip && !Object.keys(values).some(isSpine) ? (chip.outerHTML = body.chip) : this.drawSpine(id);
      },
      /* What the server drew replaces what the island made, keeping its place. */
      made(id, body) {
        const drawn = table.querySelector(`[data-record="${id}"]`);
        drawn.insertAdjacentHTML("afterend", body.html);
        drawn.remove();
        rows.delete(id);
        rows.set(body.id, { id: body.id, version: body.version, locale: filtered ?? data.locale, label: body.label,
          values: body.values });
        spineData.rows[body.id] = body.spine;
        delete this.pending[id];
        this.writes += 1;
      },
      refuse(id, violation) {
        const key = violation.field.replace(/^l10n\.[^.]+\./, "");
        const [field, label, reason] = [fieldOf(key), rows.get(id).label, violation.reason];
        const name = field?.label ?? spineData?.words[violation.field];
        const button = isSpine(violation.field) ? chipOf(id)
          : field ? cellOf(id, field.key)?.querySelector("[data-edit], [data-prose]") : null;
        button?.setAttribute("aria-invalid", "true");
        isSpine(violation.field) || button?.setAttribute("title", reason);
        this.refusals.push(name ? fill(this.words["sheet.refused"], { label, field: name, reason })
          : fill(this.words["sheet.failed"], { label, reason }));
      },
      unmarkAll() {
        table.querySelectorAll("[aria-invalid]:not([data-spine])").forEach((button) => {
          button.removeAttribute("aria-invalid");
          button.removeAttribute("title");
        });
        document.querySelectorAll("[data-spine][aria-invalid]").forEach((chip) => chip.removeAttribute("aria-invalid"));
      },
      takeTheirs() {
        const { id, body } = this.conflicts.shift();
        this.stored(id, body, null);
      },
      async overwrite() {
        const { id, body } = this.conflicts.shift();
        rows.get(id).version = body.version;
        await this.save();
      },
    },
  };
}

/* A view's ticks and the bar over the view for what is ticked (each view draws
   its ticks, `partials/save-bar` the bar), mixed into the sheet island over its
   `change` and `edit`. A status or one field's value across the ticked records
   lands as pending edits the save bar saves record by record, so each is
   checked, versioned and logged as a single edit is; the trash is a form,
   confirmed, since deleting is never pending. A field is set through its own
   editor, opened empty on the first ticked record that has it, so a reference
   picked is picked for every record and never one record's own edge; a record
   whose cell has no editor, off for its kind or a file's, is left. */

function ticked(table, data, spineData) {
  const boxes = () => [...table.querySelectorAll("[data-tick]")];
  return {
    data: () => ({ ticked: [], statuses: spineData?.statuses ?? [], collection: data.collection, csrf: data.csrf }),
    computed: {
      /* A field the view shows no cell of is not offered, and a boolean, which has
         no editor to open, is offered as its two values. */
      settable() {
        const shown = data.fields.filter((field) => table.querySelector(`[data-field="${field.key}"] [data-edit]`));
        return shown.flatMap((field) => (field.kind !== "boolean" ? [{ value: field.key, label: field.label }]
          : [true, false].map((value) => ({ value: field.key + ":" + Number(value),
            label: fill(this.words["sheet.set_to"], { field: field.label,
              value: this.words[value ? "cell.yes" : "cell.no"] }) }))));
      },
      /* The status every ticked record would be saved with, so a status set stays shown until the ticks or
         the pending edits make it untrue, and a mixed selection shows the placeholder. */
      status() {
        const held = new Set(this.ticked.map((id) => this.current(id, "spine.status")));
        return held.size === 1 ? [...held][0] : "";
      },
    },
    mounted() {
      table.addEventListener("change", (event) => this.tick(event.target));
    },
    methods: {
      fill: (text, values) => fill(text, values),
      tick(box) {
        if (box.matches("[data-tick-all]")) boxes().forEach((each) => { each.checked = box.checked; });
        else if (!box.matches("[data-tick]")) return;
        this.ticked = boxes().filter((each) => each.checked)
          .map((each) => each.closest("[data-record]").dataset.record);
        const all = table.querySelector("[data-tick-all]");
        all.checked = this.ticked.length > 0 && this.ticked.length === boxes().length;
        all.indeterminate = this.ticked.length > 0 && !all.checked;
        announce(fill(this.words["sheet.ticked"], { n: this.ticked.length }));
      },
      takers(key) {
        return this.ticked.filter((id) => table.querySelector(
          `[data-record="${id}"] [data-field="${key}"]:not([data-off]) [data-edit]`));
      },
      untick() {
        const all = table.querySelector("[data-tick-all]");
        all.checked = false;
        this.tick(all);
        all.focus();
      },
      setStatus(event) {
        const value = event.target.value;
        if (value) this.ticked.forEach((id) => this.change(id, "spine.status", value));
      },
      setField(event) {
        const [key, flag] = event.target.value.split(":");
        event.target.value = "";
        const takers = key ? this.takers(key) : [];
        if (key && takers.length === 0) return toast(this.words["sheet.set_none"]);
        if (flag !== undefined) return takers.forEach((id) => this.change(id, key, flag === "1"));
        if (!key) return;
        /* The cell the editor opens on may be scrolled out of the table's view. */
        table.querySelector(`[data-record="${takers[0]}"] [data-field="${key}"]`)
          .scrollIntoView({ block: "nearest", inline: "nearest" });
        this.edit(takers[0], key, true);
      },
      back: () => location.search.slice(1),
      /* The dialog fills the page's top layer, so a press on its backdrop lands on the element itself. */
      outside(event) {
        if (event.target === this.$refs.trash) this.$refs.trash.close();
      },
    },
  };
}

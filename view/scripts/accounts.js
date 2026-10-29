/* The Accounts card's dialogs (`view/partials/account-dialog.php` holds their
   contract). A role shows the scopes it has, and `write ⊆ read` is kept by the
   ticks themselves: a tick that moves the other list says so under them before
   anything is saved, since a list changing silently is a scope nobody chose. A
   person is picked from `/search`, as an edge's target is, or named new. A save
   or a delete reloads the page, the rows being the server's drawing of the
   accounts, and a dialog left without saving opens again as it was. An address
   naming one, as a person's record screen does, opens it. */

function mountAccount(form) {
  const shown = () => {
    const role = form.elements.role.value;
    form.querySelector('[data-scope="write"]').hidden = role !== "editor";
    form.querySelector('[data-scope="read"]').hidden = role === "owner";
    form.querySelectorAll("[data-scope-narrow]").forEach((box) => {
      box.closest("[data-scope]").querySelector("[data-scope-types]").hidden = !box.checked;
    });
  };
  form.addEventListener("change", (event) => {
    if (event.target.matches("[data-scope-types] input")) keepWithin(form, event.target);
    shown();
  });
  form.addEventListener("submit", (event) => { event.preventDefault(); saveAccount(form); });
  form.addEventListener("reset", () => window.setTimeout(shown));
  form.closest("dialog").addEventListener("close", () => form.reset());
  mountRemove(form);
  const person = form.querySelector("[data-person]");
  if (person) mountPerson(person);
}

/* An editor's read tick taken away takes the write tick with it, and a write
   tick given brings its read tick; a viewer's write list is its read list. */
function keepWithin(form, box) {
  const said = form.querySelector("[data-scope-said]");
  said.textContent = "";
  if (form.elements.role.value !== "editor") return;
  const [write, read] = ["write", "read"].map((scope) => form.querySelector(`[data-scope="${scope}"]`));
  const other = box.closest("[data-scope]") === read ? write : read;
  const twin = other.querySelector(`[data-scope-types] input[value="${box.value}"]`);
  const narrowed = other.querySelector("[data-scope-narrow]").checked;
  const word = box.closest("label").querySelector("[data-type-word]").textContent;
  if (other === write && !box.checked && !narrowed) {
    narrowWrite(write, read);
    said.textContent = form.dataset.saidNarrowed;
  } else if (other === write && !box.checked && twin.checked) {
    twin.checked = false;
    said.textContent = fill(form.dataset.saidGoes, { types: word });
  } else if (other === read && box.checked && narrowed && !twin.checked) {
    twin.checked = true;
    said.textContent = fill(form.dataset.saidComes, { types: word });
  }
}

/* Writing every type, once reading is narrowed, is writing what is read. */
function narrowWrite(write, read) {
  write.querySelector("[data-scope-narrow]").checked = true;
  write.querySelectorAll("[data-scope-types] input").forEach((each) => {
    each.checked = read.querySelector(`[data-scope-types] input[value="${each.value}"]`).checked;
  });
}

/* A scope is sent only where it stands and is narrowed; absent, it is every type. */
function saveAccount(form) {
  const body = new URLSearchParams({ email: form.elements.email.value, role: form.elements.role.value });
  form.querySelectorAll("[data-scope]").forEach((section) => {
    if (section.hidden || !section.querySelector("[data-scope-narrow]").checked) return;
    body.set(section.dataset.scope, [...section.querySelectorAll("[data-scope-types] input:checked")]
      .map((each) => each.value).join(" "));
  });
  ["person", "name"].forEach((name) => form.elements.namedItem(name) && body.set(name,
    form.elements.namedItem(name).value));
  const disabled = form.elements.namedItem("disabled");
  if (disabled) body.set("disabled", disabled.checked ? "1" : "0");
  answerAccount(form, fetch(form.dataset.accountForm, { method: form.dataset.method, body,
    headers: accountHeaders(form) }));
}

const accountHeaders = (form) => ({ "X-CSRF-Token": form.dataset.csrf, Accept: "application/json" });

async function answerAccount(form, sent) {
  const response = await sent;
  if (response.ok) return location.reload();
  form.querySelector("[data-account-error]").textContent = (await response.json()).error ?? "";
}

/* Deleting asks once more, beside the sentence saying the person stays. */
function mountRemove(form) {
  const ask = form.querySelector("[data-account-remove-ask]");
  const removing = form.querySelector("[data-account-removing]");
  if (!ask) return;
  const remove = removing.querySelector("[data-account-remove]");
  ask.addEventListener("click", () => {
    [ask.hidden, removing.hidden] = [true, false];
    remove.focus();
  });
  remove.addEventListener("click", () =>
    answerAccount(form, fetch(remove.dataset.accountRemove, { method: "POST", headers: accountHeaders(form) })));
  form.addEventListener("reset", () => {
    [ask.hidden, removing.hidden] = [false, true];
    form.querySelectorAll("[data-scope-said], [data-account-error]").forEach((line) => { line.textContent = ""; });
  });
}

/* The newest people first, before anything is typed; a person who holds an
   account already is not offered, and a name typed may be a new person. */
function mountPerson(person) {
  const form = person.closest("form");
  const search = person.querySelector("[data-person-search]");
  const options = person.querySelector("[data-person-options]");
  const [chosen, find] = [person.querySelector("[data-person-chosen]"), person.querySelector("[data-person-find]")];
  const choose = (id, name, label) => {
    [form.elements.namedItem("person").value, form.elements.namedItem("name").value] = [id, name];
    person.querySelector("[data-person-label]").textContent = label;
    [chosen.hidden, find.hidden] = [label === "", label !== ""];
    label === "" ? search.focus() : form.elements.email.focus();
  };
  let typing = 0;
  search.addEventListener("focus", () => options.childElementCount === 0 && paintPeople(person, choose));
  search.addEventListener("input", () => {
    window.clearTimeout(typing);
    typing = window.setTimeout(() => paintPeople(person, choose), 200);
  });
  search.addEventListener("keydown", (event) => event.key === "Enter"
    && (event.preventDefault(), options.querySelector("button")?.click()));
  person.querySelector("[data-person-change]").addEventListener("click", () => choose("", "", ""));
  form.addEventListener("reset", () => {
    [form.elements.namedItem("person").value, form.elements.namedItem("name").value] = ["", ""];
    [chosen.hidden, find.hidden] = [true, false];
    options.replaceChildren();
  });
}

async function paintPeople(person, choose) {
  const search = person.querySelector("[data-person-search]");
  const options = person.querySelector("[data-person-options]");
  const typed = search.value;
  const answer = await (await fetch("/search?" + new URLSearchParams({ q: typed, type: "Person" }),
    { headers: { Accept: "application/json" } })).json();
  if (typed !== search.value) return;
  const held = new Set(JSON.parse(person.dataset.held));
  const named = fill(person.dataset.newWord, { name: typed.trim() });
  options.replaceChildren(...(answer.hits ?? []).filter((hit) => !held.has(hit.id))
    .map((hit) => personOption(hit.glyph, hit.label, () => choose(hit.id, "", hit.label))),
    ...(typed.trim() === "" ? [] : [personOption("new", named, () => choose("", typed.trim(), named))]));
}

function personOption(glyph, label, take) {
  const option = Object.assign(document.createElement("button"), { type: "button", className: "option" });
  option.append(glyphMark(glyph), Object.assign(document.createElement("span"), { textContent: label }));
  option.addEventListener("click", take);
  return option;
}

/* The dialog an address names, as a person's record screen links it. */
function openNamedAccount() {
  const dialog = location.hash.startsWith("#account-") ? document.getElementById(location.hash.slice(1)) : null;
  if (!dialog?.matches("dialog.account-dialog")) return;
  history.replaceState(null, "", location.pathname + location.search);
  dialog.showModal();
}

document.querySelectorAll("[data-account-form]").forEach(mountAccount);
openNamedAccount();

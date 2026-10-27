/* The grant popover (`partials/grant-panel` holds its contract), on the shared
   popover of `view/scripts/app.js`: opened from a collection's badge (`partials/grant-badge`),
   and carried by a column's field popover, which wires it here too (`view/scripts/fields.js`),
   and the home screen's re-issue of a whole key, which shows its secret the same way. */
function openGrant(badge) {
  const { element } = popover(badge, [...document.getElementById(badge.dataset.grant).content.cloneNode(true)
    .childNodes], true);
  focusField(mountGrant(element) ?? element.querySelector("button, a"), element);
}

/* The panel where the reader may issue, on the badge's popover and a field's
   (`view/scripts/fields.js`): each take-back, then the issue form, whose name
   field it answers, or null. */
function mountGrant(element) {
  mountRevoke(element);
  const form = element.querySelector("[data-grant-issue]");
  if (!form) return null;
  const [name, submit] = [form.querySelector("[data-grant-label]"), form.querySelector("[type=submit]")];
  const [fallback, issue] = [form.querySelector("[data-grant-name]")?.textContent ?? "", submit.textContent];
  name.addEventListener("input", () => {
    form.querySelectorAll("[data-grant-name]").forEach((each) => { each.textContent = name.value.trim() || fallback; });
    form.querySelector("[name=adds]")?.remove();
    const replace = form.querySelector("[data-grant-replace]");
    [replace.hidden, replace.querySelector("input").checked] = [true, false];
    submit.textContent = issue;
  });
  form.addEventListener("change", () => issuable(form, submit));
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const sent = new URLSearchParams(new FormData(form));
    sent.set("capability", sent.getAll("capability").join(","));
    const response = await fetch("/settings/tokens", { method: "POST", body: sent,
      headers: { Accept: "application/json" } });
    const body = await response.json();
    if (response.status === 409) confirmAdding(form, submit);
    if (!response.ok) return (form.querySelector("[data-grant-error]").textContent = body.error);
    showSecret(element, body);
  });
  return name;
}

/* Nothing ticked is nothing to issue, and the draft box stands under a ticked
   read; a form whose one capability is fixed has no box to tick. */
function issuable(form, submit) {
  const [unpublished, ticks] = [form.querySelector("[data-grant-unpublished]"),
    [...form.querySelectorAll("input[type=checkbox][name=capability]")]];
  submit.disabled = ticks.length > 0 && !ticks.some((tick) => tick.checked);
  if (!unpublished) return;
  unpublished.hidden = !ticks.some((tick) => tick.checked && tick.value === "read");
  unpublished.querySelector("input").checked &&= !unpublished.hidden;
}

/* The secret stands alone and locks the popover: only its done button closes
   it, and the page is read afresh so the marks show what was issued. A press on
   the secret copies it; the stylesheet selects it whole, which is the way to
   copy where no clipboard is there, HTTP on a LAN. */
function showSecret(element, body) {
  const secret = element.querySelector("[data-grant-secret]");
  for (let node = secret; node !== element; node = node.parentElement) {
    [...node.parentElement.children].forEach((sibling) => { sibling.hidden ||= sibling !== node; });
  }
  secret.querySelectorAll("[data-grant-name]").forEach((each) => { each.textContent = body.label; });
  const value = secret.querySelector("[data-grant-secret-value]");
  value.textContent = body.secret;
  value.addEventListener("click", () => navigator.clipboard?.writeText(body.secret).then(() =>
    toast(value.dataset.copied)));
  element.dataset.locked = "";
  secret.hidden = false;
  announce(secret.querySelector("p").textContent);
  secret.querySelector("[data-grant-done]").addEventListener("click", () => location.reload());
  secret.querySelector("[data-grant-done]").focus();
}

/* A live name was typed: the next press adds to that key, until the name changes,
   and the box to stop the name's older secrets stands until then. */
function confirmAdding(form, submit) {
  const adds = Object.assign(document.createElement("input"), { type: "hidden", name: "adds", value: "1" });
  form.append(adds);
  form.querySelector("[data-grant-replace]").hidden = false;
  submit.textContent = form.dataset.grantAdd;
}

/* A key's re-issue on the Grants card (`partials/grant-dialog` holds its
   contract): its ticked rules on a new secret, shown as the badge shows one, and
   the dialog then held until it is closed, which reads the page afresh. */
function mountReissue(form) {
  const dialog = form.closest("dialog");
  const [submit, error] = [dialog.querySelector(`[form="${form.id}"]`), form.querySelector("[data-grant-error]")];
  form.addEventListener("change", () => { submit.disabled = !form.querySelector("[name=rule]:checked"); });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const sent = new URLSearchParams(new FormData(form));
    sent.set("rules", sent.getAll("rule").join(","));
    sent.delete("rule");
    sent.set("from", form.dataset.grantReissue);
    const response = await fetch("/settings/tokens", { method: "POST", body: sent,
      headers: { Accept: "application/json" } });
    const body = await response.json();
    if (!response.ok) return (error.textContent = body.error);
    dialog.addEventListener("cancel", (closing) => closing.preventDefault());
    dialog.addEventListener("close", () => location.reload());
    showSecret(dialog, body);
  });
  dialog.addEventListener("close", () => {
    form.reset();
    [error.textContent, submit.disabled] = ["", false];
  });
}

/* Taking a key back asks once more, beside when the key was last used: once on
   the Grants card's dialog, once per key the badge's popover reviews. */
function mountRevoke(scope) {
  scope.querySelectorAll("[data-grant-revoke-ask]").forEach((ask) => {
    const revoking = ask.closest(".grant, .grant-dialog").querySelector("[data-grant-revoking]");
    ask.addEventListener("click", () => {
      [ask.hidden, revoking.hidden] = [true, false];
      revoking.querySelector("[type=submit]").focus();
    });
    scope.addEventListener("close", () => { [ask.hidden, revoking.hidden] = [false, true]; });
  });
}

/* The set-up key's dialog on the Grants card (`partials/home-grants` holds its
   contract): the badge's issue form, and once its secret shows, closed by its
   done button alone. */
function mountSetup(dialog) {
  mountGrant(dialog);
  dialog.addEventListener("cancel", (closing) => dialog.dataset.locked !== undefined && closing.preventDefault());
}

document.querySelectorAll("[data-grant-reissue]").forEach(mountReissue);
document.querySelectorAll(".grant-dialog:not([data-grant-setup])").forEach(mountRevoke);
document.querySelectorAll("[data-grant-setup]").forEach(mountSetup);

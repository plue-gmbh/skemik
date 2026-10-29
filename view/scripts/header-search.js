/* The header's search (`view/partials/header.php`) opens into its field and closes once left
   empty or by its ×, which a phone's field covering the header needs; an empty field asks
   nothing. On its results, `/find`, the question stands in the field, which the page does not
   repeat. */
function mountHeaderSearch(form) {
  const [button, field] = [form.querySelector("button"), form.querySelector("input")];
  const close = () => (field.value = "", open(false), button.focus());
  const open = (opened) => {
    form.classList.toggle("is-open", opened);
    button.setAttribute("aria-expanded", String(opened));
  };
  if (location.pathname === form.getAttribute("action")) {
    field.value = new URLSearchParams(location.search).get("q") ?? "";
    open(field.value !== "");
  }
  button.addEventListener("click", () => {
    if (form.classList.contains("is-open") && field.value.trim() !== "") return form.requestSubmit();
    open(true);
    field.focus();
  });
  form.addEventListener("submit", (event) => field.value.trim() === "" && event.preventDefault());
  form.querySelector("[data-header-search-close]").addEventListener("click", close);
  field.addEventListener("keydown", (event) => event.key === "Escape" && (event.preventDefault(), close()));
  field.addEventListener("blur", (event) => !form.contains(event.relatedTarget) && field.value.trim() === ""
    && open(false));
}

document.querySelectorAll("[data-header-search]").forEach(mountHeaderSearch);

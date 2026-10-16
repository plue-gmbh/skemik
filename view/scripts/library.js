/* The media library's uploads (`partials/view-grid`), each file its own request,
   so one refused refuses only itself and the size cap stays per file; the screen
   is then read afresh, since what the uploads left is the server's to draw. A
   request the web server stops before PHP has no page to explain it, so that one
   sentence stands here. The bar counts bytes, since `fetch` cannot. Files dropped
   anywhere on the library's screen are uploaded, one dropped on a file's preview
   on its own screen replaces it, and nowhere else: a file dropped on another
   screen is no asset anybody asked for, and the browser opening it in place of
   the page would lose what was typed there. */

function sent(form, file, moved) {
  const body = new FormData(form);
  body.set("file", file);
  const request = new XMLHttpRequest();
  request.open("POST", form.action);
  request.setRequestHeader("Accept", "application/json");
  request.setRequestHeader("X-CSRF-Token", body.get("_csrf"));
  request.responseType = "json";
  request.upload.addEventListener("progress", (event) => moved(event.loaded / event.total));
  return new Promise((resolve) => {
    request.addEventListener("loadend", () =>
      resolve(request.response ?? {}));
    request.send(body);
  });
}

function showSent(bar, share) {
  if (share < 1) bar.value = share;
  else bar.removeAttribute("value");
}

async function sentAll(form, files, bar) {
  const total = files.reduce((sum, file) => sum + file.size, 0) || 1;
  const answers = [];
  let before = 0;
  for (const file of files) {
    const moved = (share) => showSent(bar, (before + share * file.size) / total);
    answers.push(await sent(form, file, moved));
    before += file.size;
  }
  return answers;
}

function mountUpload(form) {
  const input = form.elements.file;
  const note = document.querySelector("[data-upload-progress]");
  const alert = document.querySelector("[data-upload-failed]");
  input.addEventListener("change", async () => {
    if (input.files.length === 0) return;
    const files = [...input.files];
    const bar = note.querySelector("progress");
    [input.disabled, alert.hidden, note.hidden, bar.value] = [true, true, false, 0];
    announce(note.textContent);
    const answers = await sentAll(form, files, bar);
    if (answers.every((answer) => answer.address)) return location.assign(answers[0].address);
    const refused = answers.find((answer) => answer.error)?.error;
    [alert.textContent, alert.hidden] = [refused ?? form.dataset.failed, false];
    [note.hidden, input.disabled] = [true, false];
  });
}

/* An input for one file takes the first file dropped, as it takes the one picked. */
function first(files) {
  const carrier = new DataTransfer();
  carrier.items.add(files[0]);
  return carrier.files;
}

/* Entering and leaving fire for every element crossed, so the outline counts them. It is drawn on what takes
   the drop: inside the scrolling paper on the library, since outside the page it is clipped and a long library
   carries its bottom out of view, and around the preview alone on a file's screen, since a file meant for the
   library and dropped anywhere there would replace the one shown, its bytes gone. */
function mountDrop(form) {
  const [input, preview] = [form.elements.file, document.querySelector("[data-drop]")];
  const zone = preview ?? form.closest(".paper");
  const carried = (event) => event.dataTransfer?.types.includes("Files");
  let entered = 0;
  const mark = (count) => zone.classList.toggle("is-dropping", (entered = count) > 0);
  document.addEventListener("dragenter", (event) => carried(event) && mark(entered + 1));
  document.addEventListener("dragleave", (event) => carried(event) && mark(entered - 1));
  document.addEventListener("dragover", (event) => carried(event) && event.preventDefault());
  document.addEventListener("drop", (event) => {
    if (!carried(event)) return;
    event.preventDefault();
    mark(0);
    if (input.disabled || preview?.contains(event.target) === false || event.dataTransfer.files.length === 0) return;
    input.files = input.multiple ? event.dataTransfer.files : first(event.dataTransfer.files);
    input.dispatchEvent(new Event("change"));
  });
}

document.querySelectorAll("[data-upload]").forEach((form) => {
  mountUpload(form);
  mountDrop(form);
});

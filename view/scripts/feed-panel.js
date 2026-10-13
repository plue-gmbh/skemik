/* The feed panel (`partials/feed-panel`): opened and closed by the header's Feed
   button, open or closed kept in a cookie so the server draws the next page the
   same way; on a narrow screen it floats over the paper and starts closed. The
   document is fetched from the address shown in the spelling chosen, as any
   consumer fetches it, or for the keyed reader from the same route behind the
   session, `/feed-panel/…`; JSON is printed in two-space indents and lightly
   coloured, HTML with its tags and attribute values coloured, every other
   format as its bytes. Escape inside the panel closes it, after whatever a
   popover's Escape closed first. The query bar replaces the address
   as it filters and says so (`query-bar.js`). */

function mountFeedPanel(panel, button) {
  const part = (name) => panel.querySelector(`[data-feed-${name}]`);
  const chosen = { format: "", reader: "anonymous" };
  /* The server writes the base address; the spelling is applied to it and never to itself. */
  const base = () => {
    const link = part("address");
    return new URL(link.dataset.base ??= link.href);
  };
  const shown = () => {
    const url = base();
    return url.origin + url.pathname + chosen.format + url.search;
  };
  const fetched = () => {
    const url = base();
    const door = chosen.reader === "keyed" ? "/feed-panel" : "/feed";
    return url.origin + url.pathname.replace(/^\/feed(?=\/|$)/, door) + chosen.format + url.search;
  };
  const show = (code, text) => {
    part("status").textContent = code;
    part("status").classList.toggle("is-refused", code !== "" && code !== "200");
    const json = chosen.format === "" || code !== "200";
    const nodes = json ? coloured(pretty(text)) : chosen.format === ".html" ? markedUp(text) : [text];
    part("document").replaceChildren(...nodes);
  };
  const load = async () => {
    const asked = fetched();
    try {
      const accept = chosen.format === "" ? "application/ld+json" : "*/*";
      const answer = await fetch(asked, { headers: { Accept: accept } });
      if (asked === fetched()) show(String(answer.status), await answer.text());
    } catch {
      show("", panel.dataset.failed);
    }
  };
  const relabel = () => {
    part("address").href = shown();
    part("address").textContent = shown();
    part("address").title = shown();
  };
  const choose = (option) => {
    option.parentElement.querySelectorAll("button")
      .forEach((each) => each.setAttribute("aria-pressed", String(each === option)));
    ["format", "reader"].forEach((name) => { chosen[name] = option.dataset[name] ?? chosen[name]; });
    relabel();
    load();
  };
  const narrow = matchMedia("(max-width: 820px)");
  const display = (open) => {
    panel.hidden = !open;
    button.setAttribute("aria-expanded", String(open));
  };
  const toggle = (open) => {
    display(open);
    document.cookie = `feed=${open ? "open" : ""}; Path=/; Max-Age=${open ? 31536000 : 0}; SameSite=Lax`;
    if (open) { load(); part("address").focus(); }
  };
  panel.addEventListener("click", (event) => {
    const option = event.target.closest("[data-format], [data-reader], [data-feed-copy]");
    if (option?.dataset.feedCopy !== undefined) copyAddress(shown(), part("address"), panel.dataset.copied);
    else if (option) choose(option);
  });
  panel.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || event.defaultPrevented) return;
    event.preventDefault();
    toggle(false);
    button.focus();
  });
  button.addEventListener("click", () => toggle(panel.hidden));
  document.addEventListener("feed-address", () => { relabel(); if (!panel.hidden) load(); });
  /* Floating, the panel covers where the drawer and the account menu open, so either
     closes it, as its own button would. */
  document.querySelectorAll(".header-menu, details.account > summary").forEach((other) =>
    other.addEventListener("click", () => narrow.matches && !panel.hidden && toggle(false)));
  display(!panel.hidden && !narrow.matches);
  if (!panel.hidden) load();
}

/* The clipboard is there on HTTPS and localhost; elsewhere the address is selected to copy by hand. */
async function copyAddress(text, link, said) {
  if (!navigator.clipboard) return getSelection().selectAllChildren(link);
  await navigator.clipboard.writeText(text);
  toast(said);
}

function pretty(text) {
  try { return JSON.stringify(JSON.parse(text), null, 2); } catch { return text; }
}

/* Keys, strings and bare values each a span; what is none of them stays text. */
function coloured(text) {
  return spans(text,
    /(?<key>"(?:[^"\\]|\\.)*")(?=\s*:)|(?<string>"(?:[^"\\]|\\.)*")|(?<value>-?\d[\d.eE+-]*|true|false|null)/g);
}

/* A tag a key, a quoted attribute value inside it a string, the text between tags text. */
function markedUp(text) {
  return spans(text, /(?<key><\/?[!a-zA-Z][^>"\s]*|\s[a-zA-Z-]+=|\/?>)|(?<string>"[^"]*")/g);
}

function spans(text, token) {
  const nodes = [];
  let last = 0;
  for (const match of text.matchAll(token)) {
    const kind = Object.keys(match.groups).find((name) => match.groups[name] !== undefined);
    nodes.push(text.slice(last, match.index),
      Object.assign(document.createElement("span"), { className: "feed-" + kind, textContent: match[0] }));
    last = match.index + match[0].length;
  }
  return [...nodes, text.slice(last)];
}

const feedPanel = document.querySelector("[data-feed-panel]");
const feedButton = document.querySelector("[data-feed-button]");
if (feedPanel && feedButton) mountFeedPanel(feedPanel, feedButton);

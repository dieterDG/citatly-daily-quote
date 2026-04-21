(function () {
  "use strict";

  function setPlain(el, text) {
    if (!el) return;
    el.textContent = text || "";
  }

  async function loadQuote(container) {
    const endpoint = (window.QOTD && window.QOTD.endpoint) ? window.QOTD.endpoint : null;
    if (!endpoint) return;

    // Dynamische min-height Berechnung
    const lineHeight = parseFloat(getComputedStyle(container).lineHeight) || 24;
    const estimatedHeight = lineHeight * 2; // 2 Zeilen
    container.style.minHeight = estimatedHeight + 'px';
    container.setAttribute("data-qotd-loading", "1");

    try {
      const res = await fetch(endpoint, { credentials: "same-origin" });
      if (!res.ok) {
        container.removeAttribute("data-qotd-loading");
        container.style.minHeight = ''; // ✅ OK - Fehlerfall
        return;
      }

      const data = await res.json();
      const textEl = container.querySelector(".qotd__text");
      const authorEl = container.querySelector(".qotd__author");
      const sourceEl = container.querySelector(".qotd__source");

      if (!data || !data.has_quote) {
        setPlain(textEl, "");
        setPlain(authorEl, "");
        setPlain(sourceEl, "");
        container.removeAttribute("data-qotd-loading");
        container.style.minHeight = ''; // ✅ OK - Kein Zitat vorhanden
        return;
      }

      setPlain(textEl, data.text || "");

      const author = (data.author || "").trim();
      const extra = (data.extra || "").trim();

      setPlain(authorEl, author ? "— " + author : "");
      setPlain(sourceEl, extra ? (author ? " · " : "— ") + extra : "");

      container.removeAttribute("data-qotd-loading");
      // ✅ ZEILE 49 GELÖSCHT - min-height bleibt permanent!
    } catch (e) {
      container.removeAttribute("data-qotd-loading");
      container.style.minHeight = ''; // ✅ OK - Fehlerfall
    }
  }

  function initAll() {
    const nodes = document.querySelectorAll('[data-qotd="1"]');
    nodes.forEach(loadQuote);
  }

  window.addEventListener("load", initAll);
})();
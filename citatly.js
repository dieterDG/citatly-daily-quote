(function () {
  "use strict";

  function setPlain(el, text) {
    if (!el) return;
    el.textContent = text || "";
  }

  function createSeparator(className, text) {
    var span = document.createElement("span");
    span.className = className;
    span.textContent = text;
    return span;
  }

  async function loadQuote(container) {
    const endpoint = (window.Citatly && window.Citatly.endpoint) ? window.Citatly.endpoint : null;
    if (!endpoint) return;

    // Initiale min-height: geschätzte 2 Zeilen
    const lineHeight = parseFloat(getComputedStyle(container).lineHeight) || 24;
    container.style.minHeight = (lineHeight * 2) + 'px';
    container.setAttribute("data-citatly-loading", "1");

    try {
      const res = await fetch(endpoint, { credentials: "same-origin" });
      if (!res.ok) {
        container.removeAttribute("data-citatly-loading");
        container.style.minHeight = '';
        return;
      }

      const data = await res.json();
      const textEl = container.querySelector(".citatly__text");
      const metaEl = container.querySelector(".citatly__meta");

      if (!data || !data.has_quote) {
        setPlain(textEl, "");
        if (metaEl) metaEl.innerHTML = "";
        container.removeAttribute("data-citatly-loading");
        container.style.minHeight = '';
        return;
      }

      setPlain(textEl, data.text || "");

      const author = (data.author || "").trim();
      const extra = (data.extra || "").trim();

      // Meta-Bereich dynamisch aufbauen mit eigenen Elementen für Trennzeichen
      if (metaEl) {
        metaEl.innerHTML = "";

        if (author || extra) {
          metaEl.appendChild(createSeparator("citatly__separator", "— "));
        }

        if (author) {
          var authorEl = document.createElement("span");
          authorEl.className = "citatly__author";
          authorEl.textContent = author;
          metaEl.appendChild(authorEl);
        }

        if (author && extra) {
          metaEl.appendChild(createSeparator("citatly__divider", " · "));
        }

        if (extra) {
          var sourceEl = document.createElement("span");
          sourceEl.className = "citatly__source";
          sourceEl.textContent = extra;
          metaEl.appendChild(sourceEl);
        }
      }

      container.removeAttribute("data-citatly-loading");

      // WICHTIG: Min-height auf AKTUELLE Höhe setzen (nach dem Text geladen ist)
      requestAnimationFrame(() => {
        const actualHeight = container.getBoundingClientRect().height;
        container.style.minHeight = actualHeight + 'px';
      });

    } catch (e) {
      container.removeAttribute("data-citatly-loading");
      container.style.minHeight = '';
    }
  }

  function initAll() {
    const nodes = document.querySelectorAll('[data-citatly="1"]');
    nodes.forEach(loadQuote);
  }

  window.addEventListener("load", initAll);
})();

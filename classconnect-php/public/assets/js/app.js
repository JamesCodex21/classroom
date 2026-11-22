/* ClassConnect front-end behaviour. Plain JavaScript, no dependencies. */
(function () {
  "use strict";

  var body = document.body;

  /* ---- Mobile navigation ---- */
  var menuToggle = document.getElementById("menu-toggle");

  function setNavOpen(open) {
    body.classList.toggle("nav-open", open);
    if (menuToggle) {
      menuToggle.setAttribute("aria-expanded", String(open));
      menuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    }
  }

  if (menuToggle) {
    menuToggle.addEventListener("click", function () {
      setNavOpen(!body.classList.contains("nav-open"));
    });
  }

  document.addEventListener("click", function (event) {
    if (event.target.closest("[data-close-sidebar]") || event.target.closest(".sidebar .nav-link")) {
      setNavOpen(false);
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setNavOpen(false);
  });

  /* ---- Flash messages ---- */
  document.addEventListener("click", function (event) {
    var dismiss = event.target.closest("[data-dismiss]");
    if (dismiss) {
      var alertBox = dismiss.closest("[data-alert]");
      if (alertBox) alertBox.remove();
    }
  });

  document.querySelectorAll("[data-alert].alert-success, [data-alert].alert-info").forEach(function (el) {
    setTimeout(function () { el.remove(); }, 6000);
  });

  /* ---- Confirmation prompts and busy states on forms ---- */
  document.addEventListener("submit", function (event) {
    var form = event.target;
    var message = form.getAttribute("data-confirm");
    if (message && !window.confirm(message)) {
      event.preventDefault();
      return;
    }
    if (form.hasAttribute("data-busy")) {
      var submit = form.querySelector('button[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.classList.add("is-loading");
      }
    }
  });

  /* ---- Copy class code ---- */
  function fallbackCopy(text) {
    var field = document.createElement("textarea");
    field.value = text;
    field.setAttribute("readonly", "");
    field.style.position = "absolute";
    field.style.left = "-9999px";
    document.body.appendChild(field);
    field.select();
    try { document.execCommand("copy"); } catch (err) { /* ignore */ }
    field.remove();
  }

  function showCopied(button) {
    button.classList.add("is-success");
    button.setAttribute("title", "Copied!");
    setTimeout(function () {
      button.classList.remove("is-success");
      button.setAttribute("title", "Copy class code");
    }, 1600);
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-copy]");
    if (!button) return;
    var text = button.getAttribute("data-copy");
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(
        function () { showCopied(button); },
        function () { fallbackCopy(text); showCopied(button); }
      );
    } else {
      fallbackCopy(text);
      showCopied(button);
    }
  });

  /* ---- Auto-submit filter dropdowns ---- */
  document.querySelectorAll("select[data-auto-submit]").forEach(function (select) {
    select.addEventListener("change", function () { select.form.submit(); });
  });

  /* ---- File validation and drag-and-drop for uploads ---- */
  function formatSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  document.querySelectorAll('input[type="file"][data-accept-exts]').forEach(function (input) {
    var exts = input.getAttribute("data-accept-exts").split(",");
    var maxMb = Number(input.getAttribute("data-max-mb") || 25);
    var form = input.form;
    var info = form ? form.querySelector("[data-file-info]") : null;
    var label = form ? form.querySelector("[data-file-label]") : null;
    var dropzone = form ? form.querySelector(".dropzone") : null;

    function setInfo(text, isError) {
      if (!info) return;
      info.textContent = text;
      info.classList.toggle("field-error", !!isError);
      info.classList.toggle("hint", !isError);
    }

    function resetLabel() {
      if (label) label.textContent = "Choose a file or drop it here";
      if (dropzone) dropzone.classList.remove("has-file");
    }

    input.addEventListener("change", function () {
      var file = input.files && input.files[0];
      if (!file) {
        resetLabel();
        setInfo("", false);
        return;
      }
      var dot = file.name.lastIndexOf(".");
      var ext = dot === -1 ? "" : file.name.slice(dot + 1).toLowerCase();
      var problem = "";
      if (!ext || exts.indexOf(ext) === -1) {
        problem = "That file type isn't supported. Choose one of: " + exts.join(", ").toUpperCase() + ".";
      } else if (file.size === 0) {
        problem = "That file is empty.";
      } else if (file.size > maxMb * 1024 * 1024) {
        problem = "That file is larger than " + maxMb + " MB.";
      }
      if (problem) {
        input.value = "";
        resetLabel();
        setInfo(problem, true);
        return;
      }
      if (label) label.textContent = file.name;
      if (dropzone) dropzone.classList.add("has-file");
      setInfo(formatSize(file.size) + " selected. Ready to upload.", false);
    });

    if (dropzone) {
      ["dragenter", "dragover"].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
          event.preventDefault();
          dropzone.classList.add("is-dragover");
        });
      });
      ["dragleave", "drop"].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
          event.preventDefault();
          dropzone.classList.remove("is-dragover");
        });
      });
      dropzone.addEventListener("drop", function (event) {
        if (event.dataTransfer && event.dataTransfer.files.length) {
          input.files = event.dataTransfer.files;
          input.dispatchEvent(new Event("change"));
        }
      });
    }
  });
})();

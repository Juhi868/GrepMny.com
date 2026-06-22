(function () {
  const root = document.documentElement;
  const storedTheme = localStorage.getItem("GrepMny-theme");
  const preferredDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const initialTheme = storedTheme || (preferredDark ? "dark" : "light");
  root.dataset.theme = initialTheme;

  const updateThemeButton = (button) => {
    button.setAttribute("aria-pressed", root.dataset.theme === "dark" ? "true" : "false");
  };

  document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
    updateThemeButton(button);
    button.addEventListener("click", () => {
      root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
      localStorage.setItem("GrepMny-theme", root.dataset.theme);
      document.querySelectorAll("[data-theme-toggle]").forEach(updateThemeButton);
    });
  });

  const menuToggle = document.querySelector("[data-menu-toggle]");
  const menu = document.querySelector("[data-menu]");
  if (menuToggle && menu) {
    menuToggle.addEventListener("click", () => {
      const isOpen = menu.classList.toggle("is-open");
      menuToggle.setAttribute("aria-expanded", String(isOpen));
    });

    menu.addEventListener("click", (event) => {
      if (event.target.closest("a")) {
        menu.classList.remove("is-open");
        menuToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  const siteSearch = document.querySelector("[data-site-search]");
  const searchItems = Array.from(document.querySelectorAll("[data-search-item]"));
  if (siteSearch && searchItems.length) {
    siteSearch.addEventListener("input", () => {
      const query = siteSearch.value.trim().toLowerCase();
      searchItems.forEach((item) => {
        item.classList.toggle("is-hidden", query !== "" && !item.textContent.toLowerCase().includes(query));
      });
    });
  }

  const showAlert = (form, message, isError) => {
    const alert = form.querySelector("[data-alert]");
    if (!alert) return;
    alert.textContent = message;
    alert.classList.add("is-visible");
    alert.classList.toggle("is-error", isError);
  };

  const setFieldError = (input, message) => {
    const field = input.closest(".field");
    if (!field) return;
    const help = field.querySelector("small");
    field.classList.toggle("is-invalid", Boolean(message));
    if (help) help.textContent = message || help.dataset.defaultText || "";
  };

  document.querySelectorAll(".field small").forEach((small) => {
    small.dataset.defaultText = small.textContent;
  });

  const statusParams = new URLSearchParams(window.location.search);
  const statusMessage = statusParams.get("message");
  if (statusMessage) {
    document.querySelectorAll("[data-alert]").forEach((alert) => {
      alert.textContent = statusMessage;
      alert.classList.add("is-visible");
      alert.classList.toggle("is-error", statusParams.get("status") === "error");
    });
  }

  document.querySelectorAll("form[data-validate]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      let valid = true;
      const inputs = Array.from(form.querySelectorAll("input[required], input[min], input[max], input[minlength], input[pattern]"));

      inputs.forEach((input) => {
        setFieldError(input, "");
        if (!input.checkValidity()) {
          valid = false;
          setFieldError(input, input.validationMessage);
        }
      });

      if (form.hasAttribute("data-password-match")) {
        const password = form.querySelector("[name='password1']");
        const confirm = form.querySelector("[name='password2']");
        if (password && confirm && password.value !== confirm.value) {
          valid = false;
          setFieldError(confirm, "Passwords must match.");
        }
      }

      if (form.hasAttribute("data-date-range")) {
        const start = form.querySelector("[name='start_date']");
        const end = form.querySelector("[name='end_date']");
        if (start && end && start.value && end.value && new Date(end.value) < new Date(start.value)) {
          valid = false;
          setFieldError(end, "End date must be after the start date.");
        }
      }

      if (!valid) {
        event.preventDefault();
        showAlert(form, "Please fix the highlighted fields before submitting.", true);
      }
    });
  });

  const patternInput = document.querySelector("[data-pattern-input]");
  const patternResults = document.querySelector("[data-pattern-results]");
  const sampleRecords = [
    { name: "Riya Sharma", course: "Data Analytics", duration: "12 weeks", fee: "12000" },
    { name: "Arjun Mehta", course: "Python Foundations", duration: "8 weeks", fee: "9000" },
    { name: "Maya Rao", course: "UX Research", duration: "6 weeks", fee: "7500" },
    { name: "Dev Patel", course: "Cloud Security", duration: "10 weeks", fee: "14500" },
    { name: "Sara Khan", course: "Frontend Design Systems", duration: "9 weeks", fee: "11000" }
  ];

  const renderPatternResults = () => {
    if (!patternInput || !patternResults) return;
    const query = patternInput.value.trim();
    let matcher = (text) => text.toLowerCase().includes(query.toLowerCase());

    if (query) {
      try {
        const regex = new RegExp(query, "i");
        matcher = (text) => regex.test(text);
      } catch (error) {
        matcher = (text) => text.toLowerCase().includes(query.toLowerCase());
      }
    }

    const matches = sampleRecords.filter((record) => matcher(Object.values(record).join(" ")));
    patternResults.innerHTML = matches.length
      ? matches.map((record) => `
          <article>
            <h3>${record.name}</h3>
            <p>${record.course} · ${record.duration} · ₹${record.fee}</p>
          </article>
        `).join("")
      : "<article><h3>No matches</h3><p>Try a broader search term or a different pattern.</p></article>";
  };

  if (patternInput && patternResults) {
    patternInput.addEventListener("input", renderPatternResults);
    renderPatternResults();
  }
}());

(() => {
  const selects = Array.from(document.querySelectorAll("select.perform-select"));
  if (!selects.length) return;

  const closeAll = (except = null) => {
    document.querySelectorAll(".perform-select-host.is-open, .employee-select.is-open, .filter-select.is-open").forEach((container) => {
      if (container === except) return;
      const trigger = container.querySelector(".perform-select-trigger");
      const menu = container.querySelector(".perform-select-menu");
      container.classList.remove("is-open");
      trigger?.setAttribute("aria-expanded", "false");
      menu?.remove();
    });
  };

  const positionMenu = (trigger, menu) => {
    const rect = trigger.getBoundingClientRect();
    const viewportPadding = 12;
    const menuHeight = Math.min(menu.scrollHeight, 280);
    const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
    const spaceAbove = rect.top - viewportPadding;
    const openAbove = spaceBelow < Math.min(menuHeight, 220) && spaceAbove > spaceBelow;
    const maxHeight = Math.max(160, Math.min(280, openAbove ? spaceAbove : spaceBelow));
    const top = openAbove ? Math.max(viewportPadding, rect.top - maxHeight - 6) : rect.bottom + 6;
    const width = Math.max(rect.width, 220);
    const left = Math.min(Math.max(viewportPadding, rect.left), window.innerWidth - width - viewportPadding);

    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
    menu.style.width = `${Math.min(width, window.innerWidth - viewportPadding * 2)}px`;
    menu.style.maxHeight = `${maxHeight}px`;
    menu.dataset.placement = openAbove ? "above" : "below";
  };

  const setValue = (select, value) => {
    if (select.value === value) return;
    select.value = value;
    select.dispatchEvent(new Event("change", { bubbles: true }));
  };

  const openSelect = (select, container, trigger) => {
    closeAll(container);
    const menu = document.createElement("div");
    const options = Array.from(select.options);
    const selectedIndex = Math.max(0, select.selectedIndex);

    menu.className = "perform-select-menu";
    menu.setAttribute("role", "listbox");
    menu.tabIndex = -1;

    options.forEach((option, index) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "perform-select-option";
      item.setAttribute("role", "option");
      item.dataset.value = option.value;
      item.dataset.index = String(index);
      item.textContent = option.textContent.trim();
      item.disabled = option.disabled;
      item.setAttribute("aria-selected", String(index === selectedIndex));
      if (index === selectedIndex) item.classList.add("is-selected");
      if (option.disabled) item.classList.add("is-disabled");
      item.addEventListener("click", () => {
        if (option.disabled) return;
        setValue(select, option.value);
        syncTrigger(select, trigger);
        closeAll();
        trigger.focus();
      });
      menu.append(item);
    });

    container.append(menu);
    container.classList.add("is-open");
    trigger.setAttribute("aria-expanded", "true");
    positionMenu(trigger, menu);
    requestAnimationFrame(() => menu.classList.add("is-visible"));
    menu.querySelector(".is-selected")?.scrollIntoView({ block: "nearest" });
  };

  const syncTrigger = (select, trigger) => {
    const selected = select.options[select.selectedIndex];
    trigger.querySelector(".perform-select-value").textContent = selected?.textContent.trim() || "Select an option";
  };

  const setup = (select) => {
    const parent = select.parentElement;
    const container = parent?.matches(".employee-select, .filter-select") ? parent : (() => {
      const host = document.createElement("div");
      host.className = "perform-select-host";
      select.parentElement.insertBefore(host, select);
      host.append(select);
      return host;
    })();

    select.classList.add("perform-select-native");
    select.tabIndex = -1;

    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "perform-select-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("aria-label", select.getAttribute("aria-label") || select.name || select.id || "Select an option");

    const value = document.createElement("span");
    value.className = "perform-select-value";
    const chevron = document.createElement("span");
    chevron.className = "perform-select-trigger-chevron";
    chevron.setAttribute("aria-hidden", "true");
    trigger.append(value, chevron);
    container.append(trigger);
    syncTrigger(select, trigger);

    trigger.addEventListener("click", () => {
      if (select.disabled) return;
      if (container.classList.contains("is-open")) closeAll();
      else openSelect(select, container, trigger);
    });

    trigger.addEventListener("keydown", (event) => {
      const enabled = Array.from(select.options).map((option, index) => ({ option, index })).filter(({ option }) => !option.disabled);
      if (event.key === "Escape") {
        closeAll();
        return;
      }
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        trigger.click();
        return;
      }
      if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) return;
      event.preventDefault();
      const current = enabled.findIndex(({ index }) => index === select.selectedIndex);
      const next = event.key === "Home" ? 0 : event.key === "End" ? enabled.length - 1 : Math.max(0, Math.min(enabled.length - 1, current + (event.key === "ArrowDown" ? 1 : -1)));
      if (enabled[next]) {
        setValue(select, enabled[next].option.value);
        syncTrigger(select, trigger);
      }
    });

    select.addEventListener("change", () => syncTrigger(select, trigger));
  };

  selects.forEach(setup);
  document.addEventListener("click", (event) => {
    if (!event.target.closest(".perform-select-host, .employee-select, .filter-select")) closeAll();
  });
  window.addEventListener("resize", () => closeAll());
  window.addEventListener("scroll", () => closeAll(), true);
})();

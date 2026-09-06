(() => {
  const selects = Array.from(document.querySelectorAll("select.perform-select"));
  if (!selects.length) return;

  const openMenus = new WeakMap();

  const getContainer = (select) => {
    const parent = select.parentElement;

    if (
      parent?.matches(
        ".employee-select, .filter-select, .perform-select-host"
      )
    ) {
      return parent;
    }

    const host = document.createElement("div");
    host.className = "perform-select-host";

    if (select.parentElement) {
      select.parentElement.insertBefore(host, select);
    }

    host.append(select);
    return host;
  };

  const closeAll = (except = null) => {
    document
      .querySelectorAll(
        ".perform-select-host.is-open, .employee-select.is-open, .filter-select.is-open"
      )
      .forEach((container) => {
        if (container === except) return;

        const trigger = container.querySelector(
          ":scope > .perform-select-trigger"
        );

        const menu = openMenus.get(container);

        container.classList.remove("is-open");
        trigger?.setAttribute("aria-expanded", "false");

        if (menu) {
          menu.classList.remove("is-visible");
          menu.remove();
        }

        openMenus.delete(container);
      });
  };

  const positionMenu = (trigger, menu) => {
    if (!trigger || !menu) return;

    const rect = trigger.getBoundingClientRect();

    const viewportPadding = 12;
    const gap = 6;
    const maxMenuHeight = 280;

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    /*
     * Match the trigger width by default.
     * Never allow the menu to exceed the viewport.
     */
    const availableWidth =
      viewportWidth - viewportPadding * 2;

    const width = Math.min(
      Math.max(rect.width, 180),
      availableWidth
    );

    /*
     * Calculate available space from the ACTUAL trigger position
     * in the viewport.
     */
    const spaceBelow =
      viewportHeight -
      rect.bottom -
      viewportPadding -
      gap;

    const spaceAbove =
      rect.top -
      viewportPadding -
      gap;

    const minimumUsableHeight = 120;

    let openAbove = false;

    if (
      spaceBelow < minimumUsableHeight &&
      spaceAbove > spaceBelow
    ) {
      openAbove = true;
    }

    const availableSpace = openAbove
      ? spaceAbove
      : spaceBelow;

    const maxHeight = Math.max(
      1,
      Math.min(maxMenuHeight, availableSpace)
    );

    let top;

    if (openAbove) {
      /*
       * Temporarily use the desired menu height.
       * max-height will constrain it when necessary.
       */
      top = rect.top - maxHeight - gap;
    } else {
      top = rect.bottom + gap;
    }

    let left = rect.left;

    /*
     * Keep the menu inside the viewport.
     */
    if (
      left + width >
      viewportWidth - viewportPadding
    ) {
      left =
        viewportWidth -
        viewportPadding -
        width;
    }

    left = Math.max(viewportPadding, left);

    /*
     * Final vertical clamp.
     */
    top = Math.max(
      viewportPadding,
      Math.min(
        top,
        viewportHeight -
          viewportPadding -
          Math.min(maxHeight, maxMenuHeight)
      )
    );

    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.width = `${Math.round(width)}px`;
    menu.style.maxHeight = `${Math.round(maxHeight)}px`;

    menu.dataset.placement = openAbove
      ? "above"
      : "below";
  };

  const setValue = (select, value) => {
    if (select.value === value) return;

    select.value = value;

    select.dispatchEvent(
      new Event("change", {
        bubbles: true,
      })
    );
  };

  const syncTrigger = (select, trigger) => {
    const selected =
      select.options[select.selectedIndex];

    const value =
      trigger.querySelector(
        ".perform-select-value"
      );

    if (value) {
      value.textContent =
        selected?.textContent.trim() ||
        "Select an option";
    }
  };

  const openSelect = (
    select,
    container,
    trigger
  ) => {
    closeAll(container);

    const menu =
      document.createElement("div");

    const options =
      Array.from(select.options);

    const selectedIndex =
      Math.max(0, select.selectedIndex);

    menu.className =
      "perform-select-menu";

    menu.setAttribute(
      "role",
      "listbox"
    );

    menu.setAttribute(
      "aria-label",
      select.getAttribute("aria-label") ||
        select.name ||
        select.id ||
        "Select an option"
    );

    options.forEach((option, index) => {
      const item =
        document.createElement("button");

      item.type = "button";

      item.className =
        "perform-select-option";

      item.setAttribute(
        "role",
        "option"
      );

      item.dataset.value =
        option.value;

      item.dataset.index =
        String(index);

      item.textContent =
        option.textContent.trim();

      item.disabled =
        option.disabled;

      item.setAttribute(
        "aria-selected",
        String(
          index === selectedIndex
        )
      );

      if (index === selectedIndex) {
        item.classList.add(
          "is-selected"
        );
      }

      if (option.disabled) {
        item.classList.add(
          "is-disabled"
        );
      }

      item.addEventListener(
        "click",
        () => {
          if (option.disabled) return;

          setValue(
            select,
            option.value
          );

          syncTrigger(
            select,
            trigger
          );

          closeAll();

          trigger.focus();
        }
      );

      menu.append(item);
    });

    /*
     * IMPORTANT:
     * Render the menu directly under <body>.
     *
     * This prevents parent grid/flex/overflow/
     * transform/height rules from stretching
     * or repositioning the dropdown.
     */
    document.body.append(menu);

    openMenus.set(
      container,
      menu
    );

    container.classList.add(
      "is-open"
    );

    trigger.setAttribute(
      "aria-expanded",
      "true"
    );

    /*
     * Position using viewport coordinates.
     * Because the menu is position: fixed,
     * getBoundingClientRect() is exactly what
     * we want here.
     */
    positionMenu(
      trigger,
      menu
    );

    requestAnimationFrame(() => {
      menu.classList.add(
        "is-visible"
      );
    });

    const selectedOption =
      menu.querySelector(
        ".perform-select-option.is-selected"
      );

    selectedOption?.scrollIntoView({
      block: "nearest",
    });
  };

  const setup = (select) => {
    const container =
      getContainer(select);

    /*
     * Keep the actual <select> as the real
     * form/data source while visually replacing
     * its browser UI with our custom control.
     */
    select.classList.add(
      "perform-select-native"
    );

    select.tabIndex = -1;

    const trigger =
      document.createElement("button");

    trigger.type = "button";

    trigger.className =
      "perform-select-trigger";

    trigger.setAttribute(
      "aria-haspopup",
      "listbox"
    );

    trigger.setAttribute(
      "aria-expanded",
      "false"
    );

    trigger.setAttribute(
      "aria-label",
      select.getAttribute("aria-label") ||
        select.name ||
        select.id ||
        "Select an option"
    );

    const value =
      document.createElement("span");

    value.className =
      "perform-select-value";

    const chevron =
      document.createElement("span");

    chevron.className =
      "perform-select-trigger-chevron";

    chevron.setAttribute(
      "aria-hidden",
      "true"
    );

    trigger.append(
      value,
      chevron
    );

    container.append(trigger);

    syncTrigger(
      select,
      trigger
    );

    trigger.addEventListener(
      "click",
      () => {
        if (select.disabled) return;

        if (
          container.classList.contains(
            "is-open"
          )
        ) {
          closeAll();
        } else {
          openSelect(
            select,
            container,
            trigger
          );
        }
      }
    );

    trigger.addEventListener(
      "keydown",
      (event) => {
        const enabled =
          Array.from(select.options)
            .map((option, index) => ({
              option,
              index,
            }))
            .filter(
              ({ option }) =>
                !option.disabled
            );

        if (event.key === "Escape") {
          event.preventDefault();

          closeAll();

          return;
        }

        if (
          event.key === "Enter" ||
          event.key === " "
        ) {
          event.preventDefault();

          trigger.click();

          return;
        }

        if (
          ![
            "ArrowDown",
            "ArrowUp",
            "Home",
            "End",
          ].includes(event.key)
        ) {
          return;
        }

        event.preventDefault();

        if (!enabled.length) return;

        const current =
          enabled.findIndex(
            ({ index }) =>
              index ===
              select.selectedIndex
          );

        const next =
          event.key === "Home"
            ? 0
            : event.key === "End"
              ? enabled.length - 1
              : Math.max(
                  0,
                  Math.min(
                    enabled.length - 1,
                    current +
                      (event.key ===
                      "ArrowDown"
                        ? 1
                        : -1)
                  )
                );

        if (enabled[next]) {
          setValue(
            select,
            enabled[next].option.value
          );

          syncTrigger(
            select,
            trigger
          );
        }
      }
    );

    select.addEventListener(
      "change",
      () => {
        syncTrigger(
          select,
          trigger
        );
      }
    );
  };

  selects.forEach(setup);

  /*
   * Close when clicking outside
   */
  document.addEventListener(
    "click",
    (event) => {
      const target =
        event.target;

      if (
        target.closest(
          ".perform-select-host, .employee-select, .filter-select, .perform-select-menu"
        )
      ) {
        return;
      }

      closeAll();
    }
  );

  /*
   * If the viewport changes or the page scrolls,
   * close the menu rather than leaving a stale
   * floating menu behind.
   */
  window.addEventListener(
    "resize",
    () => closeAll()
  );

  window.addEventListener(
    "scroll",
    () => closeAll(),
    true
  );
})();
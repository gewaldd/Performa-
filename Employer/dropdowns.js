(() => {
  const selects = Array.from(
    document.querySelectorAll("select.perform-select")
  );

  if (!selects.length) return;

  const state = new WeakMap();

  const isManaged = (select) => Boolean(state.get(select));

  function getContainer(select) {
    const parent = select.parentElement;

    if (
      parent &&
      parent.matches(
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

    host.appendChild(select);

    return host;
  }

  function closeSelect(select) {
    const itemState = state.get(select);

    if (!itemState) return;

    const {
      container,
      trigger,
      menu
    } = itemState;

    container.classList.remove("is-open");

    trigger?.setAttribute(
      "aria-expanded",
      "false"
    );

    if (menu) {
      menu.classList.remove("is-visible");
      menu.remove();
    }

    itemState.menu = null;
  }

  function closeAll(except = null) {
    selects.forEach((select) => {
      if (select !== except) {
        closeSelect(select);
      }
    });
  }

  function syncTrigger(select) {
    const itemState = state.get(select);

    if (!itemState) return;

    const {
      trigger
    } = itemState;

    const selected =
      select.options[
        select.selectedIndex
      ];

    const value =
      trigger.querySelector(
        ".perform-select-value"
      );

    if (value) {
      value.textContent =
        selected?.textContent?.trim() ||
        "Select an option";
    }
  }

  function positionMenu(trigger, menu) {
    if (!trigger || !menu) return;

    const rect =
      trigger.getBoundingClientRect();

    const viewportPadding = 10;
    const gap = 6;
    const maximumHeight = 280;

    const viewportWidth =
      window.innerWidth;

    const viewportHeight =
      window.innerHeight;

    const maximumWidth =
      viewportWidth -
      viewportPadding * 2;

    const width = Math.min(
      Math.max(rect.width, 1),
      maximumWidth
    );

    /*
     * Because the menu is already attached to <body>,
     * scrollHeight now represents its natural content height.
     */
    const naturalHeight = Math.min(
      Math.max(
        menu.scrollHeight || 1,
        1
      ),
      maximumHeight
    );

    const spaceBelow =
      viewportHeight -
      rect.bottom -
      viewportPadding -
      gap;

    const spaceAbove =
      rect.top -
      viewportPadding -
      gap;

    const opensAbove =
      spaceBelow < naturalHeight &&
      spaceAbove > spaceBelow;

    const availableSpace =
      opensAbove
        ? spaceAbove
        : spaceBelow;

    const height = Math.max(
      1,
      Math.min(
        naturalHeight,
        availableSpace
      )
    );

    let top = opensAbove
      ? rect.top - height - gap
      : rect.bottom + gap;

    let left = rect.left;

    if (
      left + width >
      viewportWidth - viewportPadding
    ) {
      left =
        viewportWidth -
        viewportPadding -
        width;
    }

    left = Math.max(
      viewportPadding,
      left
    );

    top = Math.max(
      viewportPadding,
      Math.min(
        top,
        viewportHeight -
          viewportPadding -
          height
      )
    );

    menu.style.top =
      `${Math.round(top)}px`;

    menu.style.left =
      `${Math.round(left)}px`;

    menu.style.width =
      `${Math.round(width)}px`;

    menu.style.maxHeight =
      `${Math.round(height)}px`;

    menu.dataset.placement =
      opensAbove
        ? "above"
        : "below";
  }

  function buildMenu(select) {
    const itemState =
      state.get(select);

    if (!itemState) return null;

    const menu =
      document.createElement("div");

    menu.className =
      "perform-select-menu";

    menu.setAttribute(
      "role",
      "listbox"
    );

    menu.setAttribute(
      "aria-label",
      select.getAttribute(
        "aria-label"
      ) ||
        select.name ||
        select.id ||
        "Select an option"
    );

    Array.from(
      select.options
    ).forEach(
      (option, index) => {
        const button =
          document.createElement(
            "button"
          );

        button.type = "button";

        button.className =
          "perform-select-option";

        button.setAttribute(
          "role",
          "option"
        );

        button.setAttribute(
          "aria-selected",
          String(
            index ===
              select.selectedIndex
          )
        );

        button.textContent =
          option.textContent.trim();

        button.disabled =
          option.disabled;

        if (
          index ===
          select.selectedIndex
        ) {
          button.classList.add(
            "is-selected"
          );
        }

        if (option.disabled) {
          button.classList.add(
            "is-disabled"
          );
        }

        button.addEventListener(
          "click",
          (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (option.disabled) {
              return;
            }

            select.value =
              option.value;

            select.dispatchEvent(
              new Event(
                "change",
                {
                  bubbles: true
                }
              )
            );

            closeSelect(select);

            itemState.trigger.focus({
              preventScroll: true
            });
          }
        );

        menu.appendChild(button);
      }
    );

    return menu;
  }

  function openSelect(select) {
    const itemState =
      state.get(select);

    if (
      !itemState ||
      select.disabled
    ) {
      return;
    }

    closeAll(select);

    const menu =
      buildMenu(select);

    if (!menu) return;

    document.body.appendChild(
      menu
    );

    itemState.menu = menu;

    itemState.container.classList.add(
      "is-open"
    );

    itemState.trigger.setAttribute(
      "aria-expanded",
      "true"
    );

    /*
     * Wait one frame so the browser has calculated
     * the menu's natural dimensions before positioning it.
     */
    requestAnimationFrame(() => {
      if (
        state.get(select)?.menu !==
        menu
      ) {
        return;
      }

      positionMenu(
        itemState.trigger,
        menu
      );

      menu.classList.add(
        "is-visible"
      );
    });
  }

  function moveSelection(
    select,
    direction
  ) {
    const enabled =
      Array.from(
        select.options
      )
        .map(
          (
            option,
            index
          ) => ({
            option,
            index
          })
        )
        .filter(
          ({ option }) =>
            !option.disabled
        );

    if (!enabled.length) {
      return;
    }

    let current =
      enabled.findIndex(
        ({ index }) =>
          index ===
          select.selectedIndex
      );

    if (current < 0) {
      current =
        direction > 0
          ? -1
          : enabled.length;
    }

    let next;

    if (direction === -999999) {
      next = 0;
    } else if (
      direction === 999999
    ) {
      next =
        enabled.length - 1;
    } else {
      next =
        Math.max(
          0,
          Math.min(
            enabled.length - 1,
            current + direction
          )
        );
    }

    if (!enabled[next]) return;

    select.value =
      enabled[next]
        .option
        .value;

    select.dispatchEvent(
      new Event(
        "change",
        {
          bubbles: true
        }
      )
    );
  }

  function setup(select) {
    if (isManaged(select)) {
      return;
    }

    const container =
      getContainer(select);

    /*
     * Keep the native select available to the application
     * while removing its visible browser UI.
     */
    select.classList.add(
      "perform-select-native"
    );

    select.tabIndex = -1;

    /*
     * Prevent duplicate custom triggers.
     */
    if (
      container.querySelector(
        ":scope > .perform-select-trigger"
      )
    ) {
      return;
    }

    const trigger =
      document.createElement(
        "button"
      );

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
      select.getAttribute(
        "aria-label"
      ) ||
        select.name ||
        select.id ||
        "Select an option"
    );

    const value =
      document.createElement(
        "span"
      );

    value.className =
      "perform-select-value";

    const chevron =
      document.createElement(
        "span"
      );

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

    container.appendChild(
      trigger
    );

    state.set(
      select,
      {
        container,
        trigger,
        menu: null
      }
    );

    syncTrigger(select);

    /*
     * VERY IMPORTANT:
     * These controls are sometimes nested inside <label>.
     * Prevent the label's default activation from interfering
     * with the custom dropdown.
     */
    trigger.addEventListener(
      "mousedown",
      (event) => {
        event.preventDefault();
      }
    );

    trigger.addEventListener(
      "touchstart",
      (event) => {
        event.preventDefault();
      },
      {
        passive: false
      }
    );

    trigger.addEventListener(
      "click",
      (event) => {
        event.preventDefault();
        event.stopPropagation();

        const itemState =
          state.get(select);

        if (!itemState) return;

        if (itemState.menu) {
          closeSelect(select);
        } else {
          openSelect(select);
        }
      }
    );

    trigger.addEventListener(
      "keydown",
      (event) => {
        switch (event.key) {
          case "ArrowDown":
            event.preventDefault();
            moveSelection(
              select,
              1
            );
            break;

          case "ArrowUp":
            event.preventDefault();
            moveSelection(
              select,
              -1
            );
            break;

          case "Home":
            event.preventDefault();
            moveSelection(
              select,
              -999999
            );
            break;

          case "End":
            event.preventDefault();
            moveSelection(
              select,
              999999
            );
            break;

          case "Enter":
          case " ":
            event.preventDefault();

            trigger.click();
            break;

          case "Escape":
            event.preventDefault();

            closeSelect(select);
            break;

          default:
            break;
        }
      }
    );

    select.addEventListener(
      "change",
      () => {
        syncTrigger(select);
      }
    );
  }

  selects.forEach(setup);

  /*
   * Clicking outside closes every custom dropdown.
   */
  document.addEventListener(
    "click",
    (event) => {
      const target =
        event.target;

      if (
        target.closest(
          ".perform-select-trigger, .perform-select-menu"
        )
      ) {
        return;
      }

      closeAll();
    }
  );

  /*
   * Close menus when the viewport/layout changes.
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
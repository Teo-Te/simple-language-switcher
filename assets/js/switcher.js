jQuery(document).ready(function ($) {
  "use strict";

  // Language Switcher Frontend Functionality
  const SLS_Switcher = {
    // Initialize the language switcher
    init: function () {
      this.bindEvents();
      this.setupDropdown();
      this.handleMobileResponsive();
    },

    // Bind all event listeners
    bindEvents: function () {
      // Toggle dropdown on click
      $(document).on("click", ".sls-current-language", this.toggleDropdown);

      // Close dropdown when clicking outside
      $(document).on("click", this.closeDropdownOutside);

      // Handle keyboard navigation
      $(document).on("keydown", ".sls-language-switcher", this.handleKeyboard);

      // Handle language option clicks
      $(document).on("click", ".sls-language-option", this.handleLanguageClick);

      // Handle hover for desktop
      $(document).on("mouseenter", ".sls-language-switcher", this.showDropdown);
      $(document).on("mouseleave", ".sls-language-switcher", this.hideDropdown);
    },

    // Setup dropdown functionality
    setupDropdown: function () {
      $(".sls-language-switcher").each(function () {
        const $switcher = $(this);
        const $dropdown = $switcher.find(".sls-dropdown");

        // Add accessibility attributes
        $switcher.find(".sls-current-language").attr({
          role: "button",
          "aria-haspopup": "listbox",
          "aria-expanded": "false",
          tabindex: "0",
        });

        $dropdown.attr({
          role: "listbox",
          "aria-hidden": "true",
        });

        $dropdown.find(".sls-language-option").attr({
          role: "option",
          tabindex: "-1",
        });
      });
    },

    // Toggle dropdown visibility
    toggleDropdown: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $switcher = $(this).closest(".sls-language-switcher");
      const $dropdown = $switcher.find(".sls-dropdown");
      const isOpen = $dropdown.is(":visible");

      // Close all other dropdowns first
      $(".sls-dropdown").hide();
      $(".sls-current-language").attr("aria-expanded", "false");
      $(".sls-dropdown").attr("aria-hidden", "true");

      if (!isOpen) {
        $dropdown.show();
        $(this).attr("aria-expanded", "true");
        $dropdown.attr("aria-hidden", "false");

        // Focus first option for keyboard users
        $dropdown.find(".sls-language-option:first").focus();
      }
    },

    // Show dropdown on hover (desktop)
    showDropdown: function () {
      if (window.innerWidth > 768) {
        // Only on desktop
        const $dropdown = $(this).find(".sls-dropdown");
        $dropdown.stop(true, true).fadeIn(200);
      }
    },

    // Hide dropdown on mouse leave (desktop)
    hideDropdown: function () {
      if (window.innerWidth > 768) {
        // Only on desktop
        const $dropdown = $(this).find(".sls-dropdown");
        $dropdown.stop(true, true).fadeOut(200);
      }
    },

    // Close dropdown when clicking outside
    closeDropdownOutside: function (e) {
      if (!$(e.target).closest(".sls-language-switcher").length) {
        $(".sls-dropdown").hide();
        $(".sls-current-language").attr("aria-expanded", "false");
        $(".sls-dropdown").attr("aria-hidden", "true");
      }
    },

    // Handle keyboard navigation
    handleKeyboard: function (e) {
      const $switcher = $(this);
      const $dropdown = $switcher.find(".sls-dropdown");
      const $options = $dropdown.find(".sls-language-option");
      const $current = $switcher.find(".sls-current-language");

      switch (e.key) {
        case "Enter":
        case " ": // Space
          if ($(e.target).hasClass("sls-current-language")) {
            e.preventDefault();
            SLS_Switcher.toggleDropdown.call(e.target, e);
          }
          break;

        case "Escape":
          $dropdown.hide();
          $current.attr("aria-expanded", "false");
          $dropdown.attr("aria-hidden", "true");
          $current.focus();
          break;

        case "ArrowDown":
          e.preventDefault();
          if ($dropdown.is(":visible")) {
            const $focused = $options.filter(":focus");
            const $next = $focused.length
              ? $focused.next(".sls-language-option")
              : $options.first();
            if ($next.length) {
              $next.focus();
            }
          } else {
            SLS_Switcher.toggleDropdown.call($current[0], e);
          }
          break;

        case "ArrowUp":
          e.preventDefault();
          if ($dropdown.is(":visible")) {
            const $focused = $options.filter(":focus");
            const $prev = $focused.length
              ? $focused.prev(".sls-language-option")
              : $options.last();
            if ($prev.length) {
              $prev.focus();
            }
          }
          break;
      }
    },

    // Handle language option clicks
    handleLanguageClick: function (e) {
      e.preventDefault();

      const $option = $(this);
      const locale = $option.data("locale");
      const originalText = $option.find(".sls-name").text();

      // Show loading state
      $option.addClass("loading");
      $option.find(".sls-name").text("Switching...");

      // AJAX language switch
      $.post(sls_ajax.ajax_url, {
        action: "sls_set_language",
        locale: locale,
        nonce: sls_ajax.nonce,
      })
        .done(function (response) {
          if (response.success && response.data.redirect_url) {
            // Always redirect to the home page of the selected language
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = response.data.redirect_url;
          } else {
            // Fallback: just reload the page
            window.location.reload();
          }
        })
        .fail(function () {
          alert("Network error");
          $option.removeClass("loading");
          $option.find(".sls-name").text(originalText);
        });
    },

    // Handle mobile responsive behavior
    handleMobileResponsive: function () {
      $(window).on("resize", function () {
        if (window.innerWidth <= 768) {
          // On mobile, ensure dropdowns are hidden
          $(".sls-dropdown").hide();
        }
      });
    },

    // Add smooth animations
    addAnimations: function () {
      // Add CSS for smooth transitions if not already present
      if (!$("#sls-animations").length) {
        $('<style id="sls-animations">')
          .text(
            `
                        .sls-dropdown {
                            transition: opacity 0.2s ease, transform 0.2s ease;
                            transform-origin: top;
                        }
                        .sls-language-option {
                            transition: background-color 0.15s ease;
                        }
                        .sls-language-option.loading {
                            opacity: 0.6;
                            pointer-events: none;
                        }
                        .sls-current-language {
                            transition: background-color 0.15s ease;
                        }
                        .sls-current-language:hover {
                            background-color: #e9ecef;
                        }
                    `
          )
          .appendTo("head");
      }
    },

    // Utility function to get current language from URL
    getCurrentLanguageFromURL: function () {
      const path = window.location.pathname;
      const match = path.match(/^\/([a-z]{2})\//);
      return match ? match[1] : "en";
    },

    // Update switcher if language changes
    updateSwitcher: function () {
      const currentLang = this.getCurrentLanguageFromURL();
      $(".sls-language-switcher").each(function () {
        const $switcher = $(this);
        // Update current language display if needed
        // This would be useful for AJAX page loads
      });
    },
  };

  // Initialize the language switcher
  SLS_Switcher.init();
  SLS_Switcher.addAnimations();

  // Make available globally for debugging
  window.SLS_Switcher = SLS_Switcher;

  // Handle page visibility changes (for AJAX sites)
  $(document).on("visibilitychange", function () {
    if (document.hidden) {
      $(".sls-dropdown").hide();
    }
  });
});

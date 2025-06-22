jQuery(document).ready(function ($) {
  "use strict";

  const SLS_Admin = {
    flagOptions: "",

    init: function () {
      this.populateFlagOptions();
      this.bindEvents();
    },

    populateFlagOptions: function () {
      if (
        typeof sls_admin_data !== "undefined" &&
        sls_admin_data.common_flags
      ) {
        let options = "";
        for (let code in sls_admin_data.common_flags) {
          options += `<option value="${sls_admin_data.common_flags[code]}">${
            sls_admin_data.common_flags[code]
          } ${code.toUpperCase()}</option>`;
        }
        this.flagOptions = options;
      }
    },

    bindEvents: function () {
      // Flag selection handling
      $(document).on("change", ".lang-flag", this.handleFlagSelection);

      // Custom flag input handling
      $(document).on("input", ".custom-flag", this.handleCustomFlagInput);

      // Add language button
      $("#add-language").on("click", this.addLanguageRow);

      // Remove language button
      $(document).on("click", ".remove-lang", this.removeLanguageRow);

      // Form submission
      $("#language-form").on("submit", this.saveLanguages);

      // Validation with delays - REMOVED duplicate checking
      $(document).on("input", ".lang-code", function () {
        const $this = $(this);
        clearTimeout($this.data("timeout"));
        $this.data(
          "timeout",
          setTimeout(function () {
            SLS_Admin.validateLanguageCode.call($this[0]);
          }, 300)
        );
      });

      // Check for duplicate LOCALE instead
      $(document).on("blur", ".lang-locale", function () {
        clearTimeout($(this).data("timeout"));
        SLS_Admin.checkDuplicateLocale.call(this);
      });

      $(document).on("input", ".lang-locale", function () {
        const $this = $(this);
        clearTimeout($this.data("timeout"));
        $this.data(
          "timeout",
          setTimeout(function () {
            SLS_Admin.validateLocale.call($this[0]);
            SLS_Admin.checkDuplicateLocale.call($this[0]);
          }, 300)
        );
      });
    },

    handleFlagSelection: function () {
      const $this = $(this);
      const $customInput = $this.siblings(".custom-flag");
      const $preview = $this.closest("tr").find(".flag-preview");

      if ($this.val() === "custom") {
        $customInput.show().focus();
        $preview.html($customInput.val());
      } else {
        $customInput.hide().val("");
        $preview.html($this.val());
      }
    },

    handleCustomFlagInput: function () {
      const $this = $(this);
      const $preview = $this.closest("tr").find(".flag-preview");
      $preview.html($this.val());
    },

    addLanguageRow: function (e) {
      e.preventDefault();

      const row = `
                <tr>
                    <td><input type="text" class="lang-code" placeholder="es" maxlength="10"></td>
                    <td><input type="text" class="lang-name" placeholder="Spanish"></td>
                    <td><input type="text" class="lang-locale" placeholder="es_ES"></td>
                    <td>
                        <select class="lang-flag">
                            <option value="">Select Flag</option>
                            ${SLS_Admin.flagOptions}
                            <option value="custom">Custom...</option>
                        </select>
                        <input type="text" class="custom-flag" style="display:none;" placeholder="&#127466;&#127480;">
                    </td>
                    <td class="flag-preview" style="font-size: 20px;"></td>
                    <td><input type="checkbox" class="lang-active" checked></td>
                    <td><button type="button" class="button remove-lang">Remove</button></td>
                </tr>
            `;

      $("#languages-table").append(row);
      $("#languages-table tr:last-child .lang-code").focus();
    },

    removeLanguageRow: function (e) {
      e.preventDefault();

      const totalRows = $("#languages-table tr").length;

      if (totalRows <= 1) {
        alert("You must have at least one language");
        return;
      }

      if (confirm("Are you sure you want to remove this language?")) {
        const $row = $(this).closest("tr");
        $row.remove();

        // Clear validation errors and revalidate LOCALES
        setTimeout(function () {
          $(".lang-locale").each(function () {
            SLS_Admin.clearValidation($(this));
          });
        }, 100);

        SLS_Admin.showMessage("Language removed", "success");
      }
    },

    validateLanguageCode: function () {
      const $this = $(this);
      const value = $this.val().toLowerCase().trim();

      SLS_Admin.clearValidation($this);

      if (!value) return true;

      const pattern = /^[a-z]{2}(-[a-z]{2,4})?$/;

      if (!pattern.test(value)) {
        $this.addClass("invalid");
        $this.after(
          '<span class="error-message">Examples: en, en-gb, pt-br</span>'
        );
        return false;
      }

      $this.val(value);
      return true;
    },

    validateLocale: function () {
      const $this = $(this);
      const value = $this.val().trim();

      SLS_Admin.clearValidation($this);

      if (!value) return true;

      const pattern = /^[a-z]{2}(_[A-Z]{2})?(_[a-zA-Z]+)?$/;

      if (!pattern.test(value)) {
        $this.addClass("invalid");
        $this.after(
          '<span class="error-message">Examples: sq, sq_AL, de_DE_formal</span>'
        );
        return false;
      }

      return true;
    },

    checkDuplicateLocale: function () {
      const $this = $(this);
      const value = $this.val().trim();

      // Clear previous duplicate errors
      $this.removeClass("invalid");
      $this.siblings(".duplicate-locale-error").remove();

      if (!value) return true;

      let isDuplicate = false;
      const $currentRow = $this.closest("tr");

      $(".lang-locale")
        .not($this)
        .each(function () {
          const $otherRow = $(this).closest("tr");
          const otherValue = $(this).val().trim();

          if (otherValue === value && $currentRow[0] !== $otherRow[0]) {
            isDuplicate = true;
            return false;
          }
        });

      if (isDuplicate) {
        $this.addClass("invalid");
        $this.after(
          '<span class="error-message duplicate-locale-error">Duplicate locale</span>'
        );
        return false;
      }

      return true;
    },

    clearValidation: function ($element) {
      $element.removeClass("invalid");
      $element.siblings(".error-message").remove();
    },

    saveLanguages: function (e) {
      e.preventDefault();

      if (!SLS_Admin.validateForm()) {
        SLS_Admin.showMessage("Please fix validation errors", "error");
        return false;
      }

      const $submitBtn = $(this).find('button[type="submit"]');
      const originalText = $submitBtn.text();
      $submitBtn
        .prop("disabled", true)
        .text("Saving & Installing Language Packs...");

      const languages = {};
      let hasLanguages = false;

      $("#languages-table tr").each(function () {
        const code = $(this).find(".lang-code").val().trim();
        const locale = $(this).find(".lang-locale").val().trim();

        if (code && locale) {
          hasLanguages = true;
          const $flagSelect = $(this).find(".lang-flag");
          let flagValue = $flagSelect.val();

          if (flagValue === "custom") {
            flagValue = $(this).find(".custom-flag").val().trim();
          }

          languages[locale] = {
            code: code,
            name: $(this).find(".lang-name").val().trim(),
            locale: locale,
            flag: flagValue,
            active: $(this).find(".lang-active").is(":checked"),
          };
        }
      });

      if (!hasLanguages) {
        SLS_Admin.showMessage("Please add at least one language", "error");
        $submitBtn.prop("disabled", false).text(originalText);
        return false;
      }

      $.post(ajaxurl, {
        action: "sls_save_languages",
        languages: JSON.stringify(languages),
        nonce: sls_admin_data.nonce,
      })
        .done(function (response) {
          if (response.success) {
            SLS_Admin.showMessage(response.data, "success");

            // Add language status check after save
            setTimeout(function () {
              SLS_Admin.checkLanguageStatuses();
              location.reload();
            }, 2000);
          } else {
            SLS_Admin.showMessage("Error: " + response.data, "error");
          }
        })
        .fail(function () {
          SLS_Admin.showMessage("Network error. Please try again.", "error");
        })
        .always(function () {
          $submitBtn.prop("disabled", false).text(originalText);
        });
    },

    checkLanguageStatuses: function () {
      $.post(ajaxurl, {
        action: "sls_check_language_status",
        nonce: sls_admin_data.nonce,
      }).done(function (response) {
        if (response.success) {
          const statuses = response.data;

          // Update UI to show installation status
          $("#languages-table tr").each(function () {
            const $row = $(this);
            const locale = $row.find(".lang-locale").val();

            if (statuses[locale]) {
              const status = statuses[locale];
              let statusHtml = "";

              if (status.installed) {
                statusHtml = '<span style="color: green;">✓ Installed</span>';
              } else if (status.available) {
                statusHtml =
                  '<span style="color: orange;">⚠ Available but not installed</span>';
              } else {
                statusHtml = '<span style="color: red;">✗ Not available</span>';
              }

              // Add status column if it doesn't exist
              if ($row.find(".status-cell").length === 0) {
                $row.append('<td class="status-cell">' + statusHtml + "</td>");
              } else {
                $row.find(".status-cell").html(statusHtml);
              }
            }
          });
        }
      });
    },

    validateForm: function () {
      let isValid = true;

      $(".lang-code, .lang-locale").each(function () {
        SLS_Admin.clearValidation($(this));
      });

      $("#languages-table tr").each(function () {
        const $row = $(this);
        const code = $row.find(".lang-code").val().trim();
        const name = $row.find(".lang-name").val().trim();
        const locale = $row.find(".lang-locale").val().trim();

        if (code || name || locale) {
          if (!code) {
            $row
              .find(".lang-code")
              .addClass("invalid")
              .after('<span class="error-message">Required</span>');
            isValid = false;
          } else {
            if (
              !SLS_Admin.validateLanguageCode.call($row.find(".lang-code")[0])
            )
              isValid = false;
            // REMOVED duplicate code check
          }

          if (!name) {
            $row
              .find(".lang-name")
              .addClass("invalid")
              .after('<span class="error-message">Required</span>');
            isValid = false;
          }

          if (!locale) {
            $row
              .find(".lang-locale")
              .addClass("invalid")
              .after('<span class="error-message">Required</span>');
            isValid = false;
          } else {
            if (!SLS_Admin.validateLocale.call($row.find(".lang-locale")[0]))
              isValid = false;
            if (
              !SLS_Admin.checkDuplicateLocale.call($row.find(".lang-locale")[0])
            )
              isValid = false;
          }
        }
      });

      return isValid;
    },

    showMessage: function (message, type = "info") {
      $(".sls-message").remove();
      const messageHtml = `<div class="sls-message ${type}">${message}</div>`;
      $(".wrap h1").after(messageHtml);

      if (type === "success") {
        setTimeout(() => $(".sls-message").fadeOut(), 3000);
      }
    },
  };

  SLS_Admin.init();
  window.SLS_Admin = SLS_Admin;
});

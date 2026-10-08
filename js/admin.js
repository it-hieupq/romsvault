jQuery(function ($) {
  /**
   * Grabber
   */
  var page = 1;
  var game = 0;
  var stt = 0;
  $(".toplevel_page_grabber #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Grabber is running. Please wait it.</p>" +
      "</div>";
    $(".toplevel_page_grabber .acf-admin-notice").remove();
    $(".toplevel_page_grabber h1").after(message);

    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data:
        $form.serialize() +
        "&page=" +
        page +
        "&game=" +
        game +
        "&stt=" +
        stt +
        "&action=k_grabber",
      cache: false,
      timeout: 60000,
      success: function (data, textStatus, jqXHR) {
        if (data.status == true) {
          page = data.page;
          game = data.game;
          stt = data.stt;
          $form.after(data.message);
          $(".toplevel_page_grabber #publish").trigger("click");
        } else {
          $form.find(".spinner").removeClass("is-active");
          $(".toplevel_page_grabber .acf-admin-notice p").html(data.message);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        $form.find(".spinner").removeClass("is-active");
        $(".toplevel_page_grabber .acf-admin-notice p").html(
          jqXHR.responseText
        );
      },
    });
  });

  /**
   * Grabber Multi
   */
  $(".rom_page_grabber_multi #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Grabber is running. Please wait it.</p>" +
      "</div>";
    $(".rom_page_grabber_multi").find(".acf-admin-notice").remove();
    $(".rom_page_grabber_multi h1").after(message);
    var urls = $("#acf-grabber_multi_urls").val();
    var urlArr = urls.split("\n");
	console.log(urlArr);
    function processUrlsRecursively(urlArr, index = 0) {
      // Base case: Stop when the index exceeds the array length
      if (index >= urlArr.length) {
        console.log("All URLs processed");
        document.querySelector(
          "#wpbody-content > div.wrap.acf-settings-wrap > div"
        ).innerHTML = "<p>Done.</p>";
        return;
      }

      // Get the current URL
      const url = urlArr[index];

      // Perform the AJAX request
      $.ajax({
        type: "POST",
        url: ajax.ajax_url,
        data:
          $form.serialize() +
          "&action=k_grabber_multi" +
          `&acf[request_url]=${url}` +
          `&acf[index]=${index}`,
        success: function (data, textStatus, jqXHR) {
          try {
            data = JSON.parse(data);
          } catch (e) {
            document.querySelector(
              "#wpbody-content > div.wrap.acf-settings-wrap > div"
            ).innerHTML = "<p>Error response.</p>";
            return;
          }
          $form.after(data.message);
          // Process the next URL recursively
          processUrlsRecursively(urlArr, index + 1);
        },
        error: function (jqXHR, textStatus, errorThrown) {
          console.error("Error processing URL:", url, errorThrown);

          // Optionally process the next URL even if the current one fails
          processUrlsRecursively(urlArr, index + 1);
        },
      });
    }
    // Usage
    processUrlsRecursively(urlArr);
    $form.find(".spinner").removeClass("is-active");
  });

  /**
   * Grabber Hack
   */
  $(".hack_page_grabber_hack #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Grabber Hack is running. Please wait it.</p>" +
      "</div>";
    $(".hack_page_grabber_hack").find(".acf-admin-notice").remove();
    $(".hack_page_grabber_hack h1").after(message);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize() + "&action=k_grabber_hack",
      success: function (data, textStatus, jqXHR) {
        $form.find(".spinner").removeClass("is-active");
        $(".hack_page_grabber_hack").find(".acf-admin-notice p").html(data);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        //$form.find('.spinner').removeClass('is-active');
        //alert(jqXHR.responseText);
      },
    });
  });

  /**
   * Insert SEO
   */
  $(".toplevel_page_insert_seo #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Insert SEO is running. Please wait it.</p>" +
      "</div>";
    $(".toplevel_page_insert_seo").find(".acf-admin-notice").remove();
    $(".toplevel_page_insert_seo h1").after(message);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize() + "&action=k_insert_seo",
      timeout: 60000,
      success: function (data, textStatus, jqXHR) {
        $form.find(".spinner").removeClass("is-active");
        alert(data);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        //$form.find('.spinner').removeClass('is-active');
        //alert(jqXHR.responseText);
      },
    });
  });

  /**
   * Insert Link
   */
  var offset = 0;
  $(".toplevel_page_insert_link #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Insert Link is running. Please wait it.</p>" +
      "</div>";
    $(".toplevel_page_insert_link").find(".acf-admin-notice").remove();
    $(".toplevel_page_insert_link h1").after(message);

    if (offset == 0) $form.append("<ol></ol>");

    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize() + "&action=k_insert_link&offset=" + offset,
      cache: false,
      timeout: 60000,
      success: function (data, textStatus, jqXHR) {
        if (data.status == true) {
          offset = data.offset;
          $form.find("ol").append(data.message);
          $(".toplevel_page_insert_link #publish").trigger("click");
        } else {
          $form.find(".spinner").removeClass("is-active");
          $(".toplevel_page_insert_link .acf-admin-notice p").html(
            data.message
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        $form.find(".spinner").removeClass("is-active");
        $(".toplevel_page_insert_link .acf-admin-notice p").html(
          jqXHR.responseText
        );
      },
    });
  });

  /**
   * Insert Link S3
   */
  var offset = 0;
  $(".toplevel_page_insert_link_s3 #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Insert Link is running. Please wait it.</p>" +
      "</div>";
    $(".toplevel_page_insert_link_s3").find(".acf-admin-notice").remove();
    $(".toplevel_page_insert_link_s3 h1").after(message);

    if (offset == 0) $form.append("<ol reversed></ol>");

    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize() + "&action=k_insert_link_s3&offset=" + offset,
      cache: false,
      timeout: 300000,
      success: function (data, textStatus, jqXHR) {
        if (data.status == true) {
          offset = data.offset;
          $form.find("ol").prepend(data.message);
          $(".toplevel_page_insert_link_s3 #publish").trigger("click");
        } else {
          $form.find(".spinner").removeClass("is-active");
          $(".toplevel_page_insert_link_s3 .acf-admin-notice p").html(
            data.message
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        $form.find(".spinner").removeClass("is-active");
        $(".toplevel_page_insert_link_s3 .acf-admin-notice p").html(
          jqXHR.responseText
        );
      },
    });
  });

  $(".toplevel_page_insert_link_aff #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Insert Link Aff is running. Please wait it.</p>" +
      "</div>";
    $(".toplevel_page_insert_link_aff").find(".acf-admin-notice").remove();
    $(".toplevel_page_insert_link_aff h1").after(message);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize() + "&action=k_insert_link_aff",
      cache: false,
      timeout: 300000,
      success: function (data, textStatus, jqXHR) {
        $form.find(".spinner").removeClass("is-active");
        alert(data);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        // $form.find('.spinner').removeClass('is-active');
        // alert(jqXHR.responseText);
      },
    });
  });

  /**
   * Insert External Link Aff
   */
  var offset = 0;
  $(".toplevel_page_insert_external_link_aff #publish").on(
    "click",
    function (e) {
      e.preventDefault();
      var $form = $(this).parents("#post");
      $form.find(".spinner").addClass("is-active");

      var message =
        '<div class="acf-admin-notice notice notice-success">' +
        "<p>Insert Link is running. Please wait it.</p>" +
        "</div>";
      $(".toplevel_page_insert_external_link_aff")
        .find(".acf-admin-notice")
        .remove();
      $(".toplevel_page_insert_external_link_aff h1").after(message);

      if (offset == 0) $form.append("<ol reversed></ol>");

      $.ajax({
        type: "POST",
        url: ajax.ajax_url,
        data:
          $form.serialize() +
          "&action=k_insert_external_link_aff&offset=" +
          offset,
        success: function (data, textStatus, jqXHR) {
          if (data.status == true) {
            offset = data.offset;
            $form.find("ol").prepend(data.message);
            $(".toplevel_page_insert_external_link_aff #publish").trigger(
              "click"
            );
          } else {
            $form.find(".spinner").removeClass("is-active");
            $(
              ".toplevel_page_insert_external_link_aff .acf-admin-notice p"
            ).html(data.message);
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          $form.find(".spinner").removeClass("is-active");
          $(".toplevel_page_insert_external_link_aff .acf-admin-notice p").html(
            jqXHR.responseText
          );
        },
      });
    }
  );

  /**
   * Edit repeater field
   */
  $(".k-edit-download-links").on("click", function (e) {
    e.preventDefault();
    var $this = $(this);
    $(".k-popover-download-links").remove();
    $(".k-popover-text-field").remove();
    $.ajax({
      type: "GET",
      url: ajax.ajax_url,
      data: {
        action: "k_edit_download_links",
        post_id: $this.data("post_id"),
        field: $this.data("field"),
      },
      success: function (data, textStatus, jqXHR) {
        $this.after(data);
      },
    });
  });

  $("body").on("click", ".k-close-download-links", function (e) {
    e.preventDefault();
    $(".k-popover-download-links").remove();
  });

  $("body").on("click", ".k-add-download-link", function (e) {
    e.preventDefault();
    $.ajax({
      type: "GET",
      url: ajax.ajax_url,
      data: {
        action: "k_add_download_link",
      },
      success: function (data, textStatus, jqXHR) {
        $(".k-popover-download-links table").append(data);
      },
    });
  });

  $("body").on("click", ".k-remove-download-link", function (e) {
    e.preventDefault();
    $(this).parent("td").parent("tr").remove();
  });

  $("body").on("click", ".k-save-download-links", function (e) {
    e.preventDefault();
    var $form = $(this).parents(".k-popover-download-links");
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize(),
      success: function (data, textStatus, jqXHR) {
        $form.prevAll(".k-download-links").html(data);
        $(".k-popover-download-links").remove();
      },
    });
  });

  /**
   * Change post status
   */
  $("body").on("click", ".k-change-post-status", function (e) {
    e.preventDefault();
    var $this = $(this);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "k_change_post_status",
        post_id: $this.data("post_id"),
      },
      success: function (data, textStatus, jqXHR) {
        $this.prevAll(".k-post-status").html(data);
      },
    });
  });

  /**
   * Change hide status
   */
  $("body").on("click", ".k-change-hide-status", function (e) {
    e.preventDefault();
    var $this = $(this);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "k_change_hide_status",
        post_id: $this.data("post_id"),
      },
      success: function (data, textStatus, jqXHR) {
        $this.parent().html(data);
      },
    });
  });

  /**
   * Edit text field
   */
  $("body").on("click", ".k-edit-text-field", function (e) {
    e.preventDefault();
    var $this = $(this);
    $(".k-popover-download-links").remove();
    $(".k-popover-text-field").remove();
    $.ajax({
      type: "GET",
      url: ajax.ajax_url,
      data: {
        action: "k_edit_text_field",
        post_id: $this.data("post_id"),
        field: $this.data("field"),
      },
      success: function (data, textStatus, jqXHR) {
        $this.after(data);
      },
    });
  });

  $("body").on("click", ".k-close-text-field", function (e) {
    e.preventDefault();
    $(".k-popover-text-field").remove();
  });

  $("body").on("click", ".k-save-text-field", function (e) {
    e.preventDefault();
    var $form = $(this).parents(".k-popover-text-field");
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: $form.serialize(),
      success: function (data, textStatus, jqXHR) {
        $form.parent().html(data);
      },
    });
  });

  /**
   * Update Roms
   */
  $(".rom_page_update_roms #publish").on("click", function (e) {
    e.preventDefault();
    var $form = $(this).parents("#post");
    $form.find(".spinner").addClass("is-active");

    var message =
      '<div class="acf-admin-notice notice notice-success">' +
      "<p>Insert Link is running. Please wait it.</p>" +
      "</div>";
    $(".rom_page_update_roms").find(".acf-admin-notice").remove();
    $(".rom_page_update_roms h1").after(message);
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: "action=k_update_roms",
      timeout: 60000,
      success: function (data, textStatus, jqXHR) {
        $form.find(".spinner").removeClass("is-active");
        alert(data);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        // $form.find('.spinner').removeClass('is-active');
        // alert(jqXHR.responseText);
      },
    });
  });

  /**
   * Get-file.php
   */
  $(".posts_page_get_file .get-file").on("click", function (e) {
    e.preventDefault();
    var $this = $(this);
    var postId = $this.data("post_id");
    var url = $('input[name="url"]').val();
    var username = $('input[name="username"]').val();
    var password = $('input[name="password"]').val();
    var ip = $('input[name="ip"]').val();
    $this.text("Getting...");
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "willgroup_get_file",
        post_id: postId,
        url: url,
        username: username,
        password: password,
        ip: ip,
      },
      timeout: 60000,
      success: function (data, textStatus, jqXHR) {
        $this.text("Get");
        alert(data);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        alert(jqXHR.responseText);
      },
    });
  });

  /**
   *  Report
   */
  $(document).on("click", ".toplevel_page_report .delete-rows", function (e) {
    e.preventDefault();
    
    var $checked = $('[name="ids"]:checked');
    var ids = $checked.map(function () {
      return $(this).val();
    }).get();
    
    if (ids.length === 0) {
      alert("Please select row(s) to delete");
      return;
    }
    
    if (!confirm("Are you sure you want to delete " + ids.length + " selected row(s)?")) {
      return;
    }
    
    var $button = $(this);
    var $wrapper = $('.report-table-wrapper');
    var nonce = $wrapper.data('nonce');
    var originalText = $button.text();
    
    // Disable button and show loading
    $button.prop('disabled', true).text('Deleting...');
    
    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "app_report_delete_rows",
        ids: ids,
        nonce: nonce
      },
      timeout: 10000,
      success: function (response) {
        if (response && response.success) {
          // Remove rows from table
          $checked.parents("tr").fadeOut(300, function() {
            $(this).remove();
          });
          
          // Show success message
          if (response.data && response.data.message) {
            alert(response.data.message);
          }
          
          // Reload page after a short delay to refresh pagination
          setTimeout(function() {
            window.location.reload();
          }, 500);
        } else {
          var errorMsg = (response && response.data && response.data.message) 
            ? response.data.message 
            : 'An error occurred while deleting.';
          alert(errorMsg);
          $button.prop('disabled', false).text(originalText);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        var errorMsg = 'An error occurred. ';
        if (textStatus === 'timeout') {
          errorMsg += 'Request timed out.';
        } else if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
          errorMsg = jqXHR.responseJSON.data.message;
        } else {
          errorMsg += 'Please try again.';
        }
        alert(errorMsg);
        $button.prop('disabled', false).text(originalText);
      },
    });
  });

  var isChecked = false;
  $("#checkAll").click(function () {
    isChecked = !isChecked;
    $(".check-item").prop("checked", isChecked);
  });

  // custom xxx
  // $("#acf-grabber_gallery-grabber_button").css({
  // 	'text-align': 'center',
  // 	'cursor': 'pointer',
  // 	'background': '#2271b1',
  // 	'border-color': '#2271b1',
  // 	'color': '#fff',
  // 	'text-decoration': 'none',
  // 	'text-shadow': 'none'
  // });
  $("#acf-grabber_gallery-grabber_button").addClass("button");
  $("#acf-grabber_gallery-grabber_button").addClass("button-primary");
  $("#acf-grabber_gallery-grabber_button").addClass("button-large");

  $(document).on("click", "#acf-grabber_gallery-grabber_button", function (e) {
    e.preventDefault();
    if ($("#acf-grabber_gallery-grabber_button").hasClass("disabled")) {
      return;
    }
    $("#acf-grabber_gallery-grabber_button").addClass("disabled");

    const url = $("#acf-grabber_gallery-grabber_input").val();

    // Regular expression to validate URL
    const urlPattern = new RegExp(
      "^(https?:\\/\\/)?" + // Protocol (http or https)
        "((([a-zA-Z\\d]([a-zA-Z\\d-]*[a-zA-Z\\d])*)\\.)+[a-zA-Z]{2,}|" + // Domain name
        "((\\d{1,3}\\.){3}\\d{1,3}))" + // OR IP (v4) address
        "(\\:\\d+)?(\\/[-a-zA-Z\\d%_.~+]*)*" + // Port and path
        "(\\?[;&a-zA-Z\\d%_.~+=-]*)?" + // Query string
        "(\\#[-a-zA-Z\\d_]*)?$",
      "i"
    ); // Fragment locator

    if (!urlPattern.test(url)) {
      $("#acf-grabber_gallery-grabber_button").removeClass("disabled");
      alert("Value must be a valid URL");
      return;
    }

    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "k_grabber_gallery_multi",
        "acf[url]": url,
        "acf[postId]": $("#post_ID").val(),
        "acf[title]": $("#title").val(),
      },
      timeout: 6000,
      success: function (data, textStatus, jqXHR) {
        $("#acf-grabber_gallery-grabber_button").removeClass("disabled");
        location.reload(true);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        location.reload(true);
      },
    });
  });

  // grabber file
  /**
   * Export ROM Links (Site Options page → ROM Links tab)
   */
  $(document).on("click", "#rom-links-export-btn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $status = $("#rom-links-export-status");
    var consoleId = $('[data-name="rom_links_console"] select').val();

    if (!consoleId) {
      $status.text("Vui lòng chọn console.");
      return;
    }

    $btn.prop("disabled", true);
    $status.text("Đang export...");

    $.ajax({
      type: "POST",
      url: ajax.ajax_url,
      data: {
        action: "rom_export_rom_links",
        console_id: consoleId,
      },
      timeout: 60000,
      success: function (response) {
        if (!response || !response.success) {
          var msg =
            response && response.data && response.data.message
              ? response.data.message
              : "Export failed";
          $status.text("Lỗi: " + msg);
          $btn.prop("disabled", false);
          return;
        }

        var blob = new Blob([response.data.content], {
          type: "text/markdown;charset=utf-8",
        });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = response.data.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        $status.text("Đã export " + response.data.count + " link.");
        $btn.prop("disabled", false);
      },
      error: function (jqXHR, textStatus) {
        $status.text("Lỗi: " + (textStatus || "request failed"));
        $btn.prop("disabled", false);
      },
    });
  });

  $("#acf-grabber_file-grabber_file_button").addClass("button");
  $("#acf-grabber_file-grabber_file_button").addClass("button-primary");
  $("#acf-grabber_file-grabber_file_button").addClass("button-large");

  $(document).on(
    "click",
    "#acf-grabber_file-grabber_file_button",
    function (e) {
      e.preventDefault();
      if ($("#acf-grabber_file-grabber_file_button").hasClass("disabled")) {
        return;
      }
      $("#acf-grabber_file-grabber_file_button").addClass("disabled");

      const url = $("#acf-grabber_file-grb_file_url").val();
      const file_name = $("#acf-grabber_file-grb_file_name").val();

      // Regular expression to validate URL
      // const urlPattern = new RegExp('^(https?:\\/\\/)?' + // Protocol (http or https)
      // 	'((([a-zA-Z\\d]([a-zA-Z\\d-]*[a-zA-Z\\d])*)\\.)+[a-zA-Z]{2,}|' + // Domain name
      // 	'((\\d{1,3}\\.){3}\\d{1,3}))' + // OR IP (v4) address
      // 	'(\\:\\d+)?' + // Optional port
      // 	'(\\/[-a-zA-Z\\d%_.~+&=()]*' + // Path (allowing special characters and parentheses)
      // 	'(\\?[;&a-zA-Z\\d%_.~+=-]*)?)?' + // Optional query string
      // 	'(\\#[-a-zA-Z\\d_]*)?$', 'i'); // Optional fragment locator

      // if (!urlPattern.test(url)) {
      // 	$("#acf-grabber_file-grabber_file_button").removeClass('disabled');
      // 	alert("Value must be a valid URLzzzzz");
      // 	return;
      // }

      $.ajax({
        type: "POST",
        url: ajax.ajax_url,
        data: {
          action: "k_grab_file",
          "acf[url]": url,
          "acf[file_name]": file_name,
          "acf[postId]": $("#post_ID").val(),
          "acf[title]": $("#title").val(),
        },
        timeout: 6000,
        success: function (data, textStatus, jqXHR) {
          $("#acf-grabber_gallery-grabber_button").removeClass("disabled");
          location.reload(true);
        },
        error: function (jqXHR, textStatus, errorThrown) {
          $("#acf-grabber_gallery-grabber_button").removeClass("disabled");
          alert("Failed to save the file");
          location.reload(true);
        },
      });
    }
  );
});

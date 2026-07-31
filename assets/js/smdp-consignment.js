jQuery(document).ready(function ($) {
  const orderId = $("#consignment_city").data("order-id");
  const provider = $("#consignment_city")
    .closest(".form-field")
    .data("provider");

  /**
   * Show / hide loading spinners
   */
  function showLoading(level) {
    $("#" + level + "-loading").show();
  }

  function hideLoading(level) {
    $("#" + level + "-loading").hide();
  }

  /**
   * Show error in console
   */
  function showError(level, message) {
    console.error("Error [" + level + "]:", message);
  }

  /**
   * Populate a <select> with options
   */
  function populateSelect($select, items, currentValue, valueKey, labelKey) {
    $select.html(
      '<option value="">Select ' +
        $select.data("level").charAt(0).toUpperCase() +
        $select.data("level").slice(1) +
        "</option>"
    );

    if (items && items.length) {
      items.forEach((item) => {
        const isSelected = currentValue && currentValue == item[valueKey];
        $select.append(
          new Option(item[labelKey], item[valueKey], false, isSelected)
        );
      });
    } else {
      $select.append(
        new Option("No " + $select.data("level") + " found", "", true, true)
      );
    }
    $select.prop("disabled", false);
  }

  /**
   * Fetch locations via AJAX
   */
  function fetchLocations(level, parentId, $select, currentValue) {
    if (!parentId && level !== "city") return;

    showLoading(level);
    $select.prop("disabled", true);

    const data = {
      action: "get_locations",
      order_id: orderId,
      find: level,
      provider: provider,
      nonce: smdp_admin.nonce_pathao_locations
    };

    if (parentId) {
      data.parent = parentId;
    }

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: data,
      success: function (response) {
        hideLoading(level);
        if (response.success && response.data.length) {
          populateSelect(
            $select,
            response.data,
            currentValue,
            "sys_id",
            "label"
          );
        } else {
          showError(level, "No locations found");
          populateSelect($select, [], currentValue, "sys_id", "label");
        }
      },
      error: function (err) {
        hideLoading(level);
        showError(level, err);
        populateSelect($select, [], currentValue, "sys_id", "label");
      }
    });
  }

  /**
   * Fetch cities if needed
   */
  const $citySelect = $("#consignment_city");
  const cityLoaded = parseInt($citySelect.data("loaded"));
  const currentCity = $citySelect.data("current");

  if (cityLoaded <= 0) {
    console.log(cityLoaded);
    fetchLocations("city", null, $citySelect, currentCity);
  }

  /**
   * Fetch zones on city change
   */
  $("#consignment_city").on("change", function () {
    const cityId = $(this).val();
    const $zoneSelect = $("#consignment_zone");
    const currentZone = $zoneSelect.data("current");

    // Clear dependent dropdowns
    $("#consignment_area").html('<option value="">Select Area</option>');

    if (!cityId) {
      $zoneSelect.html('<option value="">Select Zone</option>');
      return;
    }

    fetchLocations("zone", cityId, $zoneSelect, currentZone);
  });

  /**
   * Fetch areas on zone change
   */
  $("#consignment_zone").on("change", function () {
    const zoneId = $(this).val();
    const $areaSelect = $("#consignment_area");
    const currentArea = $areaSelect.data("current");

    if (!zoneId) {
      $areaSelect.html('<option value="">Select Area</option>');
      return;
    }

    fetchLocations("area", zoneId, $areaSelect, currentArea);
  });
});

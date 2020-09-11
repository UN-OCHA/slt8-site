(function () {
  'use strict';

  // Feature detection.
  if (typeof MutationObserver === 'undefined') {
    return;
  }

  // Add the data-content attribute with the column label to all the cells.
  function updateTable(table) {
    if (table.classList.contains('cd-table--responsive-processed')) {
      return;
    }
    table.classList.add('cd-table--responsive-processed');

    var headers = table.getElementsByTagName('th');
    if (headers.length === 0) {
      return;
    }

    var cells = table.querySelectorAll('td');
    if (cells.length === 0) {
      return;
    }

    // Extract the column labels.
    var labels = [];
    var length = headers.length;
    for (var i = 0; i < length; i++) {
      labels.push(headers[i].textContent.trim());
    }

    // Add the data-content attribute with the corresponding column label
    // to each cell.
    for (var i = 0, l = cells.length; i < l; i++) {
      cells[i].setAttribute('data-content', labels[i % length]);
    }
  }

  // Find all non processed `cd-table--reponsive` tables and update them.
  function updateTables() {
    var tables = document.querySelectorAll('.cd-table--responsive:not(.cd-table--responsive-processed)');
    for (var i = 0, l = tables.length; i < l; i++) {
      updateTable(tables[i]);
    }
  }

  // Register a mutation observer on the document to detect the addition
  // of `cd-table` tables.
  var observer = new MutationObserver(function (mutations) {
    for (var i = 0, l = mutations.length; i < l; i++) {
      var mutation = mutations[i];
      if (mutation.type === 'childList' && mutation.addedNodes) {
        updateTables();
        // No need to continue further.
        return;
      }
    }
  });

  // Observe the entire DOM for the addition of the `cd-table` tables.
  observer.observe(document, {
    childList: true,
    subtree: true
  });

  // Process existing `cd-table--responsive` tables.
  updateTables();
})();

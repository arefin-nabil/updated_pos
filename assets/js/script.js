// assets/js/script.js

/**
 * Global Helper Functions
 */

$(document).ready(function() {
    // Enable Bootstrap tooltips/popovers everywhere
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow');
    }, 5000);
});

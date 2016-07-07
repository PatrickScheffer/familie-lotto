$(document).ready(function() {
  $('.toggle_results').click(function(e) {
    $('.more_results').slideToggle();
    $('.more_results').toggleClass('open');
    if ($('.more_results').hasClass('open')) {
      $(this).find('.state_label').html('Verberg de');
    }
    else {
      $(this).find('.state_label').html('Toon alle');
    }
    e.preventDefault();
  });

  $('.delete').click(function(e) {
    if (!confirm('Are you sure?')) {
      e.preventDefault();
    }
  });
});
(function ($) {
  'use strict';

  // Test connection button on settings page.
  $(document).on('click', '#rebelboost-test-connection', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $result = $('#rebelboost-test-result');

    $btn.prop('disabled', true);
    $result.text(rebelboost.i18n.testing).css('color', '');

    $.post(rebelboost.ajax_url, {
      action: 'rebelboost_test_connection',
      nonce: rebelboost.nonce,
    })
      .done(function (response) {
        if (response.success) {
          $result.text(response.data.message).css('color', '#00a32a');
        } else {
          $result.text(response.data.message).css('color', '#d63638');
        }
      })
      .fail(function () {
        $result.text(rebelboost.i18n.test_error).css('color', '#d63638');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  // Purge All from admin bar.
  $(document).on('click', '.rebelboost-purge-all a, #wp-admin-bar-rebelboost-purge-all a', function (e) {
    e.preventDefault();

    if (!confirm(rebelboost.i18n.confirm_purge)) {
      return;
    }

    var $link = $(this);
    var originalText = $link.text();
    $link.text(rebelboost.i18n.purging);

    $.post(rebelboost.ajax_url, {
      action: 'rebelboost_purge_all',
      nonce: rebelboost.nonce,
    })
      .done(function (response) {
        if (response.success) {
          $link.text(rebelboost.i18n.purge_success);
        } else {
          $link.text(rebelboost.i18n.purge_error);
        }
      })
      .fail(function () {
        $link.text(rebelboost.i18n.purge_error);
      })
      .always(function () {
        setTimeout(function () {
          $link.text(originalText);
        }, 2000);
      });
  });

  // Purge This Page from admin bar.
  $(document).on('click', '.rebelboost-purge-page a, #wp-admin-bar-rebelboost-purge-page a', function (e) {
    e.preventDefault();

    var $link = $(this);
    var $node = $link.closest('[data-path]').length
      ? $link.closest('[data-path]')
      : $link.parent();
    var path = $node.attr('data-path') || $link.closest('li').find('[data-path]').attr('data-path');

    if (!path) {
      return;
    }

    var originalText = $link.text();
    $link.text(rebelboost.i18n.purging);

    $.post(rebelboost.ajax_url, {
      action: 'rebelboost_purge_page',
      nonce: rebelboost.nonce,
      path: path,
    })
      .done(function (response) {
        if (response.success) {
          $link.text(rebelboost.i18n.purge_success);
        } else {
          $link.text(rebelboost.i18n.purge_error);
        }
      })
      .fail(function () {
        $link.text(rebelboost.i18n.purge_error);
      })
      .always(function () {
        setTimeout(function () {
          $link.text(originalText);
        }, 2000);
      });
  });

  // Purge from post editor meta box.
  $(document).on('click', '.rebelboost-purge-post-btn', function (e) {
    e.preventDefault();

    var $btn = $(this);
    var path = $btn.data('path');
    var postId = $btn.data('post-id');
    var $result = $btn.closest('.rebelboost-meta-box').find('.rebelboost-purge-result');

    $btn.prop('disabled', true);
    $result.text(rebelboost.i18n.purging).css('color', '');

    $.post(rebelboost.ajax_url, {
      action: 'rebelboost_purge_page',
      nonce: rebelboost.nonce,
      path: path,
      post_id: postId,
    })
      .done(function (response) {
        if (response.success) {
          $result.text(response.data.message).css('color', '#00a32a');
        } else {
          $result.text(response.data.message).css('color', '#d63638');
        }
      })
      .fail(function () {
        $result.text(rebelboost.i18n.purge_error).css('color', '#d63638');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });
})(jQuery);

/**
 * Context Fusion Admin JavaScript
 *
 * Handles AJAX interactions for context extraction and display.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Extract context for an image
	 */
	$(document).on('click', '.msh-extract-context', function (e) {
		e.preventDefault();

		var $button = $(this);
		var mediaId = $button.data('media-id');
		var originalText = $button.text();

		if ($button.prop('disabled')) {
			return;
		}

		$button.prop('disabled', true).text(mshContextAdmin.strings.extracting);

		$.ajax({
			url: mshContextAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'msh_extract_image_context',
				nonce: mshContextAdmin.nonce,
				media_id: mediaId
			},
			success: function (response) {
				if (response.success) {
					// Show success message
					var message = response.data.message || 'Context extraction queued';
					alert(message);

					// Reload page to show context
					setTimeout(function () {
						location.reload();
					}, 2000);
				} else {
					alert(mshContextAdmin.strings.error + ' ' + (response.data.message || 'Unknown error'));
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function (xhr, status, error) {
				alert(mshContextAdmin.strings.error + ' ' + error);
				$button.prop('disabled', false).text(originalText);
			}
		});
	});

	/**
	 * Refresh context for an image
	 */
	$(document).on('click', '.msh-refresh-context', function (e) {
		e.preventDefault();

		var $button = $(this);
		var mediaId = $button.data('media-id');
		var originalText = $button.text();

		if ($button.prop('disabled')) {
			return;
		}

		$button.prop('disabled', true).text(mshContextAdmin.strings.refreshing);

		$.ajax({
			url: mshContextAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'msh_extract_image_context',
				nonce: mshContextAdmin.nonce,
				media_id: mediaId
			},
			success: function (response) {
				if (response.success) {
					// Reload page to show updated context
					location.reload();
				} else {
					alert(mshContextAdmin.strings.error + ' ' + (response.data.message || 'Unknown error'));
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function (xhr, status, error) {
				alert(mshContextAdmin.strings.error + ' ' + error);
				$button.prop('disabled', false).text(originalText);
			}
		});
	});

	/**
	 * Find similar images
	 */
	$(document).on('click', '.msh-find-similar', function (e) {
		e.preventDefault();

		var $button = $(this);
		var mediaId = $button.data('media-id');
		var originalText = $button.text();
		var $resultsContainer = $button.closest('.msh-context-section').find('.msh-similar-results');

		if ($button.prop('disabled')) {
			return;
		}

		$button.prop('disabled', true).text(mshContextAdmin.strings.finding || 'Finding similar images...');
		$resultsContainer.hide().empty();

		$.ajax({
			url: mshContextAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'msh_find_similar_images',
				nonce: mshContextAdmin.nonce,
				media_id: mediaId,
				limit: 5
			},
			success: function (response) {
				if (response.success) {
					var similar = response.data.similar;
					var count = response.data.count;

					if (count === 0) {
						$resultsContainer.html('<p style="color: #666; font-style: italic;">No similar images found.</p>').show();
					} else {
						var html = '<div class="msh-similar-images-list" style="margin-top: 10px;">';
						html += '<p><strong>' + count + ' similar image(s) found:</strong></p>';

						similar.forEach(function (item) {
							var similarityPercent = Math.round(item.similarity * 100);
							var badgeColor = similarityPercent >= 80 ? '#46b450' : (similarityPercent >= 60 ? '#ffb900' : '#999');

							html += '<div class="msh-similar-item" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 3px; background: #f9f9f9;">';
							html += '<div style="display: flex; gap: 10px; align-items: start;">';

							// Thumbnail
							if (item.thumbnail) {
								html += '<div style="flex-shrink: 0;">';
								html += '<img src="' + item.thumbnail + '" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 2px;" />';
								html += '</div>';
							}

							// Details
							html += '<div style="flex: 1;">';
							html += '<div style="margin-bottom: 5px;">';
							html += '<a href="' + item.edit_url + '" target="_blank" style="font-weight: 600; text-decoration: none;">' + item.title + '</a>';
							html += '</div>';

							html += '<div style="font-size: 12px; color: #666; margin-bottom: 5px;">';
							html += '<span style="background: ' + badgeColor + '; color: white; padding: 2px 6px; border-radius: 3px; margin-right: 8px;">';
							html += similarityPercent + '% similar';
							html += '</span>';
							html += '<span>Score: ' + item.avg_context_score + '</span>';
							html += '</div>';

							if (item.top_keywords && item.top_keywords.length > 0) {
								html += '<div style="font-size: 11px; color: #999;">';
								html += 'Keywords: ' + item.top_keywords.slice(0, 5).join(', ');
								html += '</div>';
							}

							html += '</div>';
							html += '</div>';
							html += '</div>';
						});

						html += '</div>';
						$resultsContainer.html(html).slideDown();
					}

					$button.prop('disabled', false).text(originalText);
				} else {
					alert(mshContextAdmin.strings.error + ' ' + (response.data.message || 'Unknown error'));
					$button.prop('disabled', false).text(originalText);
				}
			},
			error: function (xhr, status, error) {
				alert(mshContextAdmin.strings.error + ' ' + error);
				$button.prop('disabled', false).text(originalText);
			}
		});
	});

})(jQuery);

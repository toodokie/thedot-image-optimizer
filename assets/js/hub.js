/**
 * Optimizer Hub JavaScript
 *
 * Provides AJAX filtering for the Cache tab plus scaffolding for
 * upcoming tabs (Queue, Events, History, Sync).
 */

(function($) {
	'use strict';

	const MSH_Hub = {
		queueRefreshInterval: null,
		eventsRefreshInterval: null,
		eventsPaused: false,
		modalBackdrop: null,
		modalDialog: null,
		modalTitle: null,
		modalBody: null,
		modalFooter: null,
		toast: null,
		toastTimeout: null,
		globalHandlersBound: false,

		/**
		 * Bootstrap handlers once DOM ready.
		 */
		init: function() {
			if (!window.mshHubData) {
				console.warn('mshHubData missing – Hub scripts skipped.');
				return;
			}

			this.bindCacheFilters();
			this.bindCachePagination();
			this.bindRegenerateButtons();

			this.bindQueueActions();
			this.bindEventsFeed();

			// Future features keep their bindings so we do not lose earlier scaffolding.
			this.bindAdditionalPlaceholders();
		},

		/**
		 * Enhance cache filter form with AJAX.
		 */
		bindCacheFilters: function() {
		const $form = $('#msh-cache-filter-form');
		if (!$form.length) {
			return;
		}

		const $clear = $('#msh-clear-filters');

		$form.on('submit', (event) => {
			event.preventDefault();
			this.loadCacheEntries(1);
		});

		$form.find('select').on('change', () => {
			$form.trigger('submit');
		});

		$form.find('[name="search"]').on('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				$form.trigger('submit');
			}
		});

		if ($clear.length) {
			$clear.on('click', (event) => {
				event.preventDefault();
				this.resetFilters();
				this.loadCacheEntries(1);
			});
		}

		this.toggleClearLink();
	},
		},

		/**
		 * Handle pagination button clicks via delegation.
		 */
		bindCachePagination: function() {
			$(document).on('click', '.msh-page-btn', (event) => {
				event.preventDefault();
				const page = parseInt($(event.currentTarget).data('page'), 10) || 1;
				this.loadCacheEntries(page);
			});
		},

		/**
		 * Handle "Regenerate" action from cache rows.
		 */
		bindRegenerateButtons: function() {
			$(document).on('click', '.msh-regenerate-btn', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const attachmentId = $button.data('attachment-id');
				const locale = $button.data('locale');
				const field = $button.data('field');

				this.regenerateEntry(attachmentId, locale, field, $button);
			});
		},

		/**
		 * Placeholder bindings retained from earlier scaffold.
		 */
		bindAdditionalPlaceholders: function() {
		$(document).on('click', '#msh-bulk-regenerate', (event) => {
			event.preventDefault();
			this.bulkRegenerate();
		});

		$(document).on('click', '#msh-export-csv', (event) => {
			event.preventDefault();
			this.exportCSV();
		});

		$(document).on('click', '.msh-action-preview', (event) => {
			event.preventDefault();
			const $button = $(event.currentTarget);
			console.log('Preview metadata placeholder', $button.data());
		});

		$(document).on('click', '.msh-action-copy', (event) => {
			event.preventDefault();
			const value = $(event.currentTarget).data('value') || '';
			navigator.clipboard?.writeText(value).then(() => {
				console.log('Copied metadata value');
			}).catch(() => console.warn('Copy not supported.'));
		});

		$(document).on('click', '.msh-action-edit', (event) => {
			event.preventDefault();
			console.log('Edit metadata placeholder', $(event.currentTarget).data());
		});

		$(document).on('click', '.msh-action-regenerate', (event) => {
			event.preventDefault();
			console.log('Regenerate metadata placeholder', $(event.currentTarget).data());
		});

		$(document).on('click', '.msh-action-toggle-lock', (event) => {
			event.preventDefault();
			console.log('Toggle lock placeholder', $(event.currentTarget).data());
		});
	},
		},

		/**
		 * Issue AJAX request to fetch filtered metadata entries.
		 *
		 * @param {number} page Page number to load.
		 */
		loadCacheEntries: function(page) {
			const $form = $('#msh-cache-filter-form');
			const locale = $form.find('[name="locale"]').val();
			const field = $form.find('[name="field"]').val();
			const source = $form.find('[name="source"]').val();
			const status = $form.find('[name="status"]').val();
			const search = $form.find('[name="search"]').val();

			this.toggleClearLink();
			this.showCacheLoading();

			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'msh_get_metadata_entries',
					nonce: window.mshHubData.ajaxNonce,
					locale,
					field,
					source,
					status,
					search,
					paged: page
				}
			})
				.done((response) => {
					if (!response || !response.success) {
						const message = response && response.data && response.data.message
							? response.data.message
							: 'Unknown error occurred.';
						alert(message);
						return;
					}

					$('#msh-cache-table-body').html(response.data.table_html);
					$('#msh-cache-pagination').html(response.data.pagination_html);
					this.updateResultsCount(response.data.total || 0);
				})
				.fail((xhr, status, error) => {
					console.error('Metadata AJAX error:', status, error);
					alert('Failed to load metadata entries. Check console for details.');
				})
				.always(() => {
					this.hideCacheLoading();
				});
		},

		/**
		 * Update the summary line with a new total count.
		 *
		 * @param {number} total Total entries returned.
		 */
		updateResultsCount: function(total) {
			$('.msh-results-count').text(
				'Showing ' + (parseInt(total, 10) || 0) + ' metadata entries'
			);
		},

		/**
		 * Reset filters to default state.
		 */
		resetFilters: function() {
			const $form = $('#msh-cache-filter-form');
			if (!$form.length) {
				return;
			}

			$form[0].reset();
			$form.find('select').val('');
			$form.find('[name=\"search\"]').val('');
			this.toggleClearLink();
		},

		/**
		 * Enable/disable clear link depending on active filters.
		 *
		 * @param {string} locale
		 * @param {string} staleness
		 * @param {string} source
		 */
		toggleClearLink: function() {
			const $clear = $('#msh-clear-filters');
			const $form = $('#msh-cache-filter-form');
			if (!$clear.length || !$form.length) {
				return;
			}

			const hasFilters = Boolean(
				$form.find('[name="locale"]').val() ||
				$form.find('[name="field"]').val() ||
				$form.find('[name="source"]').val() ||
				$form.find('[name="status"]').val() ||
				$form.find('[name="search"]').val()
			);
			$clear.toggleClass('is-disabled', !hasFilters);
		},

		/**
		 * Show loading spinner / dim table while fetching.
		 */
		showCacheLoading: function() {
			$('#msh-loading-spinner').show();
			$('#msh-cache-table-body').css('opacity', '0.5');
		},

		/**
		 * Restore table once loading complete.
		 */
		hideCacheLoading: function() {
			$('#msh-loading-spinner').hide();
			$('#msh-cache-table-body').css('opacity', '1');
		},

		/**
		 * Enqueue regeneration job for a single cache entry.
		 *
		 * @param {number} attachmentId Attachment ID.
		 * @param {string} locale Locale code.
		 * @param {string} field Field slug.
		 * @param {jQuery} $button Triggering button.
		 */
		regenerateEntry: function(attachmentId, locale, field, $button) {
			if (!attachmentId || !field) {
				alert('Missing attachment or field information.');
				return;
			}

			const originalText = $button.text();
			$button.prop('disabled', true).text('Processing...');

			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'msh_regenerate_entry',
					nonce: window.mshHubData.ajaxNonce,
					attachment_id: attachmentId,
					locale,
					field
				}
			})
				.done((response) => {
					if (!response || !response.success) {
						const message = response && response.data && response.data.message
							? response.data.message
							: 'Unable to enqueue job.';
						alert(message);
						return;
					}

					$button.text('✓ Queued').addClass('button-primary');
					setTimeout(() => {
						$button
							.text(originalText)
							.removeClass('button-primary')
							.prop('disabled', false);
					}, 2000);
				})
				.fail((xhr, status, error) => {
					console.error('Regenerate AJAX error:', status, error);
					alert('Failed to enqueue regeneration. Check console for details.');
					$button.text(originalText).prop('disabled', false);
				})
				.always(() => {
					if (!$button.hasClass('button-primary')) {
						$button.text(originalText).prop('disabled', false);
					}
				});
		},

		/**
		 * Queue tab bindings and auto-refresh.
		 */
		bindQueueActions: function() {
			if (!$('.msh-queue-tab').length) {
				return;
			}

			$(document).on('click', '#msh-process-now', (event) => {
				event.preventDefault();
				this.processQueue();
			});

			$(document).on('change', '#msh-auto-refresh', (event) => {
				const enabled = $(event.currentTarget).is(':checked');
				if (enabled) {
					this.startQueueAutoRefresh();
					this.refreshQueueStats();
				} else if (this.queueRefreshInterval) {
					clearInterval(this.queueRefreshInterval);
					this.queueRefreshInterval = null;
				}
			});

			this.refreshQueueStats();
			this.startQueueAutoRefresh();
		},

		/**
		 * Refresh queue statistics via AJAX.
		 */
		refreshQueueStats: function() {
			if (!$('.msh-queue-tab').length) {
				return;
			}

			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'msh_refresh_queue_stats',
					nonce: window.mshHubData.ajaxNonce
				}
			})
				.done((response) => {
					if (!response || !response.success) {
						console.warn('Failed to refresh queue stats.');
						return;
					}

					this.updateQueueStats(response.data.stats || {});
				})
				.fail((xhr, status, error) => {
					console.error('Queue stats AJAX error:', status, error);
				});
		},

		/**
		 * Update stat cards and priority breakdown.
		 *
		 * @param {Object} stats
		 */
		updateQueueStats: function(stats) {
			const pending = parseInt(stats.pending || 0, 10);
			const processing = parseInt(stats.processing || 0, 10);
			const complete = parseInt(stats.complete || 0, 10);
			const failed = parseInt(stats.failed || 0, 10);

			const high = parseInt(stats.high_priority || stats.priority_high || 0, 10);
			const medium = parseInt(stats.medium_priority || stats.priority_medium || 0, 10);
			const normal = parseInt(stats.normal_priority || stats.priority_normal || 0, 10);

			const queueTotal = Math.max(0, pending + processing);

			$('#msh-stat-pending').text(this.formatNumber(pending));
			$('#msh-stat-processing').text(this.formatNumber(processing));
			$('#msh-stat-complete').text(this.formatNumber(complete));
			$('#msh-stat-failed').text(this.formatNumber(failed));

			$('#msh-priority-high-count').text(this.formatNumber(high));
			$('#msh-priority-medium-count').text(this.formatNumber(medium));
			$('#msh-priority-normal-count').text(this.formatNumber(normal));

			const highPercent = queueTotal ? Math.min(100, (high / queueTotal) * 100) : 0;
			const mediumPercent = queueTotal ? Math.min(100, (medium / queueTotal) * 100) : 0;
			const normalPercent = queueTotal ? Math.min(100, (normal / queueTotal) * 100) : 0;

			$('#msh-progress-high').css('width', highPercent + '%');
			$('#msh-progress-medium').css('width', mediumPercent + '%');
			$('#msh-progress-normal').css('width', normalPercent + '%');

			if (failed > 0) {
				$('.msh-stat-failed').addClass('has-alert');
			} else {
				$('.msh-stat-failed').removeClass('has-alert');
			}

			const $note = $('#msh-priority-note');
			if ($note.length) {
				const strings = (window.mshHubData && window.mshHubData.i18n) || {};
				if (queueTotal > 0) {
					const template = queueTotal === 1
						? strings.queueJobsWaitingSingular
						: strings.queueJobsWaitingPlural;
					const fallback = queueTotal === 1
						? '%s job waiting for processing.'
						: '%s jobs waiting for processing.';
					$note.text((template || fallback).replace('%s', this.formatNumber(queueTotal)));
				} else {
					$note.text(strings.queueNoJobs || 'No jobs waiting in the queue.');
				}
			}

			console.log('Queue stats refreshed:', stats);
		},

		/**
		 * Process queue manually.
		 */
		processQueue: function() {
			const $button = $('#msh-process-now');
			if (!$button.length) {
				return;
			}

			const originalText = $button.text();
			const strings = (window.mshHubData && window.mshHubData.i18n) || {};
			const processingLabel = strings.queueProcessing || 'Processing...';
			const doneLabel = strings.queueProcessingComplete || 'Processing Complete';

			$button.prop('disabled', true).text(processingLabel);

			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'msh_process_queue',
					nonce: window.mshHubData.ajaxNonce
				}
			})
				.done((response) => {
					if (!response || !response.success) {
						const message = response && response.data && response.data.message
							? response.data.message
							: 'Unable to process queue.';
						alert(message);
						$button.text(originalText).prop('disabled', false);
						return;
					}

					$button.text('✓ ' + doneLabel).addClass('button-primary');
					this.refreshQueueStats();

					setTimeout(() => {
						$button.text(originalText).removeClass('button-primary').prop('disabled', false);
					}, 2000);
				})
				.fail((xhr, status, error) => {
					console.error('Process queue AJAX error:', status, error);
					alert('Failed to process queue. Check console for details.');
					$button.text(originalText).prop('disabled', false);
				});
		},

		/**
		 * Start auto-refresh interval.
		 */
		startQueueAutoRefresh: function() {
			if (this.queueRefreshInterval) {
				clearInterval(this.queueRefreshInterval);
			}

			this.queueRefreshInterval = setInterval(() => {
				if ($('#msh-auto-refresh').is(':checked')) {
					this.refreshQueueStats();
				}
			}, 5000);
		},

		/**
		 * Format numbers with locale separators.
		 *
		 * @param {number} num
		 * @return {string}
		 */
		formatNumber: function(num) {
			return Number(num || 0).toLocaleString();
		},

		/**
		 * Wire up events tab behaviour.
		 */
		bindEventsFeed: function() {
			if (!$('.msh-events-tab').length) {
				return;
			}

			$(document).on('click', '#msh-toggle-events', (event) => {
				event.preventDefault();
				this.toggleEventsFeed();
			});

			this.eventsPaused = false;
			this.refreshEventsFeed();
			this.startEventsAutoRefresh();
			this.updateEventsStatus();
		},

		/**
		 * Toggle event feed polling.
		 */
		toggleEventsFeed: function() {
			this.eventsPaused = !this.eventsPaused;
			if (this.eventsPaused) {
				if (this.eventsRefreshInterval) {
					clearInterval(this.eventsRefreshInterval);
					this.eventsRefreshInterval = null;
				}
			} else {
				this.refreshEventsFeed();
				this.startEventsAutoRefresh();
			}

			this.updateEventsStatus();
		},

		/**
		 * Update status label and button text.
		 */
		updateEventsStatus: function() {
			const strings = (window.mshHubData && window.mshHubData.i18n) || {};
			const $status = $('#msh-events-status');
			const $button = $('#msh-toggle-events');

			if (!$status.length || !$button.length) {
				return;
			}

			if (this.eventsPaused) {
				$status.text(strings.eventsPaused || 'Live feed paused.');
				$button.text(strings.eventsResume || 'Resume Live Feed');
			} else {
				$status.text(strings.eventsLiveFeed || 'Live event feed running – updates every 5 seconds.');
				$button.text(strings.eventsPause || 'Pause Live Feed');
			}
		},

		/**
		 * Pull recent events list.
		 */
		refreshEventsFeed: function() {
			if (this.eventsPaused || !$('.msh-events-tab').length) {
				return;
			}

			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'msh_get_recent_events',
					nonce: window.mshHubData.ajaxNonce
				}
			})
				.done((response) => {
					const strings = (window.mshHubData && window.mshHubData.i18n) || {};
					if (!response || !response.success) {
						$('#msh-events-stream').html('<p class="msh-placeholder">' + (strings.eventsError || 'Unable to load recent events. Please try again.') + '</p>');
						return;
					}

					if (response.data && response.data.html) {
						$('#msh-events-stream').html(response.data.html);
					} else {
						$('#msh-events-stream').html('<p class="msh-placeholder">' + (strings.eventsNoData || 'No recent events yet. The feed will populate as activity occurs.') + '</p>');
					}
				})
				.fail((xhr, status, error) => {
					console.error('Events feed AJAX error:', status, error);
					const strings = (window.mshHubData && window.mshHubData.i18n) || {};
					$('#msh-events-stream').html('<p class="msh-placeholder">' + (strings.eventsError || 'Unable to load recent events. Please try again.') + '</p>');
				});
		},

		/**
		 * Start polling for events.
		 */
		startEventsAutoRefresh: function() {
			if (this.eventsRefreshInterval) {
				clearInterval(this.eventsRefreshInterval);
			}

			this.eventsRefreshInterval = setInterval(() => {
				if (!this.eventsPaused) {
					this.refreshEventsFeed();
				}
			}, 5000);
		},

		/* ---------------------------------------------------------------------
		 * Placeholder methods retained for future feature work.
		 * ------------------------------------------------------------------ */

		bulkRegenerate: function() {
			console.log('Bulk regeneration placeholder triggered.');
		},

		exportCSV: function() {
			console.log('CSV export placeholder triggered.');
			alert('CSV export coming soon!');
		},

		viewBoth: function(element) {
			const $row = $(element).closest('tr');
			console.log('View both placeholder for attachment:', $row.data('attachment-id'));
		},

		switchSource: function(element) {
			const $row = $(element).closest('tr');
			console.log('Switch source placeholder for attachment:', $row.data('attachment-id'));
		}
	};

	$(document).ready(() => {
		MSH_Hub.init();
	});

	window.MSH_Hub = MSH_Hub;

})(jQuery);

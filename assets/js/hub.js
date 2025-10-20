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
		metadataHandlersBound: false,

		/**
		 * Bootstrap handlers once DOM ready.
		 */
		init: function() {
			if (!window.mshHubData) {
				console.warn('mshHubData missing – Hub scripts skipped.');
				return;
			}

			this.bindGlobalUiHandlers();
		this.bindCacheFilters();
		this.bindCachePagination();
		this.bindRegenerateButtons();
		this.bindMetadataActions();

		this.bindQueueActions();
		this.bindEventsFeed();
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
				const entryId = parseInt($button.data('entryId'), 10);

				this.regenerateEntry(attachmentId, locale, field, $button, Number.isFinite(entryId) ? entryId : null);
			});
		},

		/**
		 * Metadata row actions and supporting UI helpers.
		 */
		
		bindGlobalUiHandlers: function() {
			if (this.globalHandlersBound) {
				return;
			}

			$(document).on('click.mshHubModal', '[data-msh-modal-close]', (event) => {
				event.preventDefault();
				this.closeModal();
			});

			this.globalHandlersBound = true;
		},

		bindMetadataActions: function() {
			if (this.metadataHandlersBound) {
				return;
			}

			$(document).on('click.mshMetadata', '#msh-bulk-regenerate', (event) => {
				event.preventDefault();
				this.bulkRegenerate();
			});

			$(document).on('click.mshMetadata', '#msh-export-csv', (event) => {
				event.preventDefault();
				this.exportCSV();
			});

			$(document).on('click.mshMetadata', '.msh-action-preview', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const entryId = this.getEntryIdFromButton($button);
				if (!entryId) {
					this.showToast(this.getString('metadataMissingIdentifier', 'Unable to locate metadata record.'), 'error');
					return;
				}
				this.handlePreviewClick(entryId, $button);
			});

			$(document).on('click.mshMetadata', '.msh-action-copy', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const entryId = this.getEntryIdFromButton($button);
				if (!entryId) {
					this.handleCopyFallback($button);
					return;
				}
				this.handleCopyClick(entryId, $button);
			});

			$(document).on('click.mshMetadata', '.msh-action-edit', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const entryId = this.getEntryIdFromButton($button);
				if (!entryId) {
					this.showToast(this.getString('metadataMissingIdentifier', 'Unable to locate metadata record.'), 'error');
					return;
				}
				this.handleEditClick(entryId, $button);
			});

			$(document).on('click.mshMetadata', '.msh-action-regenerate', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const entryId = this.getEntryIdFromButton($button);
				const attachmentId = parseInt($button.data('mediaId'), 10);
				const locale = $button.data('locale') || '';
				const field = $button.data('field') || '';
				this.regenerateEntry(attachmentId, locale, field, $button, entryId);
			});

			$(document).on('click.mshMetadata', '.msh-action-toggle-lock', (event) => {
				event.preventDefault();
				const $button = $(event.currentTarget);
				const entryId = this.getEntryIdFromButton($button);
				if (!entryId) {
					this.showToast(this.getString('metadataMissingIdentifier', 'Unable to locate metadata record.'), 'error');
					return;
				}
				this.handleToggleLockClick(entryId, $button);
			});

			this.metadataHandlersBound = true;
		},

		getEntryIdFromButton: function($button) {
			if (!$button || !$button.length) {
				return null;
			}

			const entryId = parseInt($button.data('entryId'), 10);
			return Number.isFinite(entryId) && entryId > 0 ? entryId : null;
		},

		handlePreviewClick: function(entryId, $button) {
			this.toggleButtonBusy($button, true, this.getString('metadataLoadingPreview', 'Loading preview...'));

			this.postAction('msh_preview_metadata', {
				entry_id: entryId
			})
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.handleAjaxError(response, 'Unable to load metadata preview.');
						return;
					}

					const entry = response.data.entry || {};
					const attachmentUrl = response.data.attachment_url || '';
					this.showPreviewModal(entry, attachmentUrl);
				})
				.fail((xhr, status, error) => {
					console.error('Preview AJAX error:', status, error);
					this.showToast(this.getString('metadataPreviewError', 'Unable to load metadata preview.'), 'error');
				})
				.always(() => {
					this.toggleButtonBusy($button, false);
				});
		},

		showPreviewModal: function(entry, attachmentUrl) {
			const title = this.getString('metadataPreviewTitle', 'Metadata Preview');
			const $content = $('<div>', { class: 'msh-metadata-preview' });

			if (attachmentUrl) {
				const $imageWrapper = $('<div>', { class: 'msh-metadata-preview__image' });
				const $image = $('<img>', { src: attachmentUrl, alt: this.getString('metadataPreviewImageAlt', 'Preview image'), loading: 'lazy' });
				$imageWrapper.append($image);
				$content.append($imageWrapper);
			}

			const $summary = $('<dl>', { class: 'msh-metadata-preview__summary' });
			const addSummaryItem = (label, value) => {
				if (value === undefined || value === null || value === '') {
					return;
				}
				const $dt = $('<dt>').text(label);
				const $dd = $('<dd>').text(value);
				$summary.append($dt, $dd);
			};

			addSummaryItem(this.getString('metadataPreviewEntryId', 'Entry ID'), entry.id);
			addSummaryItem(this.getString('metadataPreviewAttachment', 'Attachment ID'), entry.media_id || entry.attachment_id);
			addSummaryItem(this.getString('metadataPreviewLocale', 'Locale'), entry.locale);
			addSummaryItem(this.getString('metadataPreviewSource', 'Source'), entry.source_label || entry.source);
			addSummaryItem(this.getString('metadataPreviewStatus', 'Status'), entry.status_label || entry.metadata_status);
			addSummaryItem(this.getString('metadataPreviewUpdated', 'Updated'), entry.updated_at_display || entry.updated_at);

			$content.append($summary);

			const values = this.buildFieldValues(entry);
			const $fields = $('<div>', { class: 'msh-metadata-preview__fields' });
			Object.keys(values).forEach((key) => {
				const $section = $('<section>', { class: 'msh-metadata-preview__field' });
				$section.append($('<h3>').text(this.getFieldLabel(key)));
				$section.append($('<pre>').text(values[key] || ''));
				$fields.append($section);
			});

			$content.append($fields);

			const $footer = $('<div>');
			const $closeButton = $('<button>', {
				class: 'button button-primary',
				text: this.getString('close', 'Close'),
				'data-msh-modal-close': 'true'
			});
			$footer.append($closeButton);

			this.openModal(title, $content, $footer);
		},

		buildFieldValues: function(entry) {
			const values = {};

			if (!entry) {
				return values;
			}

			if (entry.fields && typeof entry.fields === 'object') {
				Object.keys(entry.fields).forEach((key) => {
					values[key] = entry.fields[key];
				});
				return values;
			}

			['title', 'alt_text', 'alt', 'caption', 'description'].forEach((key) => {
				if (entry[key] !== undefined && entry[key] !== null && entry[key] !== '') {
					values[key] = entry[key];
				}
			});

			if (entry.field && entry.value !== undefined) {
				values[entry.field] = entry.value;
			} else if (entry.value !== undefined && Object.keys(values).length === 0) {
				values.value = entry.value;
			}

			return values;
		},

		getFieldLabel: function(key) {
			const map = {
				title: this.getString('fieldTitle', 'Title'),
				alt_text: this.getString('fieldAltText', 'Alt Text'),
				alt: this.getString('fieldAltText', 'Alt Text'),
				caption: this.getString('fieldCaption', 'Caption'),
				description: this.getString('fieldDescription', 'Description'),
				value: this.getString('fieldValue', 'Value')
			};

			if (map[key]) {
				return map[key];
			}

			return key.replace(/_/g, ' ').replace(/\w/g, (char) => char.toUpperCase());
		},

		getString: function(key, fallback) {
			const strings = (window.mshHubData && window.mshHubData.i18n) || {};
			return strings[key] || fallback;
		},

		handleCopyClick: function(entryId, $button) {
			this.toggleButtonBusy($button, true, this.getString('metadataCopying', 'Copying...'));

			this.postAction('msh_copy_metadata', { entry_id: entryId })
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.handleAjaxError(response, 'Unable to copy metadata.');
						return;
					}

					const text = response.data.text || '';
					this.copyTextToClipboard(text)
						.then(() => {
							const message = (response.data && response.data.message) || this.getString('metadataCopied', 'Copied to clipboard!');
							this.showToast(message, 'success');
						})
						.catch(() => {
							this.showToast(this.getString('metadataCopyUnsupported', 'Copy to clipboard is not supported in this browser.'), 'error');
						});
				})
				.fail((xhr, status, error) => {
					console.error('Copy AJAX error:', status, error);
					this.showToast(this.getString('metadataCopyError', 'Unable to copy metadata.'), 'error');
				})
				.always(() => {
					this.toggleButtonBusy($button, false);
				});
		},

		handleCopyFallback: function($button) {
			const value = $button.data('value');
			if (value === undefined || value === null || value === '') {
				this.showToast(this.getString('metadataCopyUnavailable', 'Nothing to copy for this row.'), 'error');
				return;
			}

			this.copyTextToClipboard(String(value))
				.then(() => {
					this.showToast(this.getString('metadataCopied', 'Copied to clipboard!'), 'success');
				})
				.catch(() => {
					this.showToast(this.getString('metadataCopyUnsupported', 'Copy to clipboard is not supported in this browser.'), 'error');
				});
		},

		copyTextToClipboard: function(text) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				return navigator.clipboard.writeText(text);
			}

			const deferred = $.Deferred();
			const $temp = $('<textarea>', { class: 'msh-clipboard-temp' }).css({ position: 'absolute', left: '-9999px', top: '0', height: '1px', width: '1px', opacity: 0 });
			$('body').append($temp);
			$temp.val(text).trigger('focus');
			$temp[0].select();

			try {
				const successful = document.execCommand('copy');
				if (successful) {
					deferred.resolve();
				} else {
					deferred.reject();
				}
			} catch (error) {
				deferred.reject(error);
			} finally {
				$temp.remove();
			}

			return deferred.promise();
		},

		handleEditClick: function(entryId, $button) {
			this.toggleButtonBusy($button, true, this.getString('metadataLoadingEditForm', 'Preparing editor...'));

			this.loadEntryDetails(entryId)
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.handleAjaxError(response, 'Unable to load metadata details.');
						return;
					}

					const entry = response.data.entry || {};
					this.openEditModal(entryId, entry);
				})
				.fail((xhr, status, error) => {
					console.error('Edit metadata AJAX error:', status, error);
					this.showToast(this.getString('metadataEditError', 'Unable to load metadata details.'), 'error');
				})
				.always(() => {
					this.toggleButtonBusy($button, false);
				});
		},

		openEditModal: function(entryId, entry) {
			const title = this.getString('metadataEditTitle', 'Edit Metadata');
			const values = this.buildFieldValues(entry);
			const supportedFields = ['title', 'alt_text', 'caption', 'description'];
			const $form = $('<form>', { class: 'msh-metadata-edit-form' });

			$form.append($('<input>', { type: 'hidden', name: 'entry_id', value: entryId }));
			if (entry.locale) {
				$form.append($('<input>', { type: 'hidden', name: 'locale', value: entry.locale }));
			}
			if (entry.field) {
				$form.append($('<input>', { type: 'hidden', name: 'field', value: entry.field }));
			}

			supportedFields.forEach((fieldKey) => {
				const label = this.getFieldLabel(fieldKey);
				const $wrapper = $('<div>', { class: 'msh-metadata-edit-form__control' });
				$wrapper.append($('<label>', { for: 'msh-edit-' + fieldKey, text: label }));
				$wrapper.append($('<textarea>', { id: 'msh-edit-' + fieldKey, name: fieldKey, rows: 3 }).val(values[fieldKey] || ''));
				$form.append($wrapper);
			});

			const $footer = $('<div>', { class: 'msh-modal__actions' });
			const $cancel = $('<button>', { type: 'button', class: 'button', text: this.getString('cancel', 'Cancel'), 'data-msh-modal-close': 'true' });
			const $save = $('<button>', { type: 'submit', class: 'button button-primary msh-modal-save', text: this.getString('saveChanges', 'Save Changes') });
			$footer.append($cancel, $save);

			$form.on('submit', (event) => {
				event.preventDefault();
				this.submitEditForm(entryId, $form);
			});

			this.openModal(title, $form, $footer);
		},

		submitEditForm: function(entryId, $form) {
			const $saveButton = $form.find('.msh-modal-save');
			this.toggleButtonBusy($saveButton, true, this.getString('saving', 'Saving...'));

			const payload = {
				entry_id: entryId,
				title: $form.find('[name="title"]').val() || '',
				alt_text: $form.find('[name="alt_text"]').val() || '',
				caption: $form.find('[name="caption"]').val() || '',
				description: $form.find('[name="description"]').val() || ''
			};

			this.postAction('msh_update_metadata', payload)
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.handleAjaxError(response, 'Unable to update metadata.');
						return;
					}

					const message = (response.data && response.data.message) || this.getString('metadataUpdateSuccess', 'Metadata updated successfully.');
					this.showToast(message, 'success');
					this.closeModal();
					this.reloadMetadataTable();
				})
				.fail((xhr, status, error) => {
					console.error('Update metadata AJAX error:', status, error);
					this.showToast(this.getString('metadataUpdateError', 'Unable to update metadata.'), 'error');
				})
				.always(() => {
					this.toggleButtonBusy($saveButton, false);
				});
		},

		handleToggleLockClick: function(entryId, $button) {
			this.toggleButtonBusy($button, true, this.getString('metadataLocking', 'Updating...'));

			this.postAction('msh_toggle_lock', { entry_id: entryId })
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.handleAjaxError(response, 'Unable to toggle lock state.');
						return;
					}

					const protectedFlag = response.data.protected === undefined ? null : response.data.protected;
					const message = (response.data && response.data.message) || (protectedFlag ? this.getString('metadataLockEnabled', 'Entry locked.') : this.getString('metadataLockDisabled', 'Entry unlocked.'));
					this.showToast(message, 'success');
					this.reloadMetadataTable();
				})
				.fail((xhr, status, error) => {
					console.error('Toggle lock AJAX error:', status, error);
					this.showToast(this.getString('metadataLockError', 'Unable to toggle lock state.'), 'error');
				})
				.always(() => {
					this.toggleButtonBusy($button, false);
				});
		},

		reloadMetadataTable: function() {
			this.loadCacheEntries(this.getCurrentPage());
		},

		getCurrentPage: function() {
			const $current = $('.msh-page-btn.current');
			if ($current.length) {
				const page = parseInt($current.data('page'), 10);
				if (Number.isFinite(page) && page > 0) {
					return page;
				}
			}

			return 1;
		},

		loadEntryDetails: function(entryId) {
			return this.postAction('msh_preview_metadata', { entry_id: entryId });
		},

		postAction: function(action, data) {
			const ajaxUrl = (window.mshHubData && window.mshHubData.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : null);
			if (!ajaxUrl) {
				return $.Deferred().reject('missing_ajax_url').promise();
			}

			const payload = $.extend({
				action,
				nonce: (window.mshHubData && window.mshHubData.ajaxNonce) || ''
			}, data || {});

			return $.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: payload
			});
		},

		handleAjaxError: function(response, fallback) {
			const message = response && response.data && response.data.message ? response.data.message : fallback;
			if (message) {
				this.showToast(message, 'error');
			}
		},

		ensureModal: function() {
			if (this.modalBackdrop && this.modalDialog) {
				return;
			}

			const $backdrop = $('<div>', { class: 'msh-modal-backdrop' }).hide();
			const $dialog = $('<div>', { class: 'msh-modal', role: 'dialog', 'aria-modal': 'true' }).hide();
			const $content = $('<div>', { class: 'msh-modal__content' });
			const $header = $('<div>', { class: 'msh-modal__header' });
			const $title = $('<h2>', { class: 'msh-modal__title', id: 'msh-hub-modal-title' });
			const $close = $('<button>', { type: 'button', class: 'msh-modal__close button-link', 'aria-label': this.getString('close', 'Close'), 'data-msh-modal-close': 'true' });
			const $body = $('<div>', { class: 'msh-modal__body' });
			const $footer = $('<div>', { class: 'msh-modal__footer' });

			$header.append($title, $close);
			$content.append($header, $body, $footer);
			$dialog.append($content);

			$('body').append($backdrop, $dialog);

			$backdrop.on('click', () => this.closeModal());
			$(document).on('keydown.mshHubModal', (event) => {
				if (event.key === 'Escape') {
					this.closeModal();
				}
			});

			this.modalBackdrop = $backdrop;
			this.modalDialog = $dialog;
			this.modalTitle = $title;
			this.modalBody = $body;
			this.modalFooter = $footer;
		},

		openModal: function(title, content, footer) {
			this.ensureModal();

			this.modalTitle.text(title || '');
			this.modalBody.empty().append(content);
			this.modalFooter.empty();
			if (footer) {
				this.modalFooter.append(footer);
			}

			$('body').addClass('msh-modal-open');
			this.modalBackdrop.fadeIn(120);
			this.modalDialog.fadeIn(120).attr('aria-labelledby', 'msh-hub-modal-title');
		},

		closeModal: function() {
			if (this.modalDialog) {
				this.modalDialog.fadeOut(120, () => {
					this.modalBody.empty();
					this.modalFooter.empty();
				});
			}

			if (this.modalBackdrop) {
				this.modalBackdrop.fadeOut(120);
			}

			$('body').removeClass('msh-modal-open');
		},

		ensureToast: function() {
			if (this.toast) {
				return;
			}

			this.toast = $('<div>', { class: 'msh-toast', role: 'status', 'aria-live': 'polite' }).hide();
			$('body').append(this.toast);
		},

		showToast: function(message, type) {
			this.ensureToast();
			const toastType = type || 'info';

			if (this.toastTimeout) {
				clearTimeout(this.toastTimeout);
			}

			this.toast.removeClass('is-success is-error is-info').addClass('is-' + toastType).text(message || '');
			this.toast.stop(true, true).fadeIn(120);

			this.toastTimeout = setTimeout(() => {
				this.toast.fadeOut(180);
			}, 4000);
		},

		toggleButtonBusy: function($button, enable, busyText) {
			if (!$button || !$button.length) {
				return;
			}

			if (enable) {
				if (typeof $button.data('originalHtml') === 'undefined') {
					$button.data('originalHtml', $button.html());
				}
				$button.prop('disabled', true).addClass('is-busy');
				if (busyText) {
					$button.text(busyText);
				}
			} else {
				if (typeof $button.data('originalHtml') !== 'undefined') {
					$button.html($button.data('originalHtml'));
					$button.removeData('originalHtml');
				}
				$button.prop('disabled', false).removeClass('is-busy');
			}
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
		regenerateEntry: function(attachmentId, locale, field, $button, entryId) {
			if (!attachmentId || !field) {
				this.showToast(this.getString('metadataRegenerateMissing', 'Missing attachment or field information.'), 'error');
				return;
			}

			const originalHtml = $button.html();
			const queuedLabel = this.getString('queued', 'Queued');

			this.toggleButtonBusy($button, true, this.getString('metadataRegenerating', 'Queuing...'));

			this.postAction('msh_regenerate_entry', {
				attachment_id: attachmentId,
				locale,
				field,
				entry_id: entryId || ''
			})
				.done((response) => {
					if (!response || !response.success) {
						this.handleAjaxError(response, 'Unable to enqueue regeneration job.');
						this.toggleButtonBusy($button, false);
						return;
					}

					const message = (response.data && response.data.message) || this.getString('metadataRegenerateQueued', 'Regeneration queued.');
					this.showToast(message, 'success');
					this.toggleButtonBusy($button, false);
					$button.html('✓ ' + queuedLabel).addClass('button-primary');

					setTimeout(() => {
						$button.html(originalHtml).removeClass('button-primary');
					}, 2000);

					this.reloadMetadataTable();
				})
				.fail((xhr, status, error) => {
					console.error('Regenerate AJAX error:', status, error);
					this.toggleButtonBusy($button, false);
					this.showToast(this.getString('metadataRegenerateError', 'Failed to enqueue regeneration.'), 'error');
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

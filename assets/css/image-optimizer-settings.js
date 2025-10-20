(function($) {
    'use strict';

    const SettingsPage = {
        init() {
            this.$container = $('#msh-context-profiles');
            this.$addButton = $('.msh-add-profile');
            this.template = document.getElementById('msh-profile-template');
            this.strings = (window.mshSettings && window.mshSettings.strings) || {};

            this.bindEvents();
            this.initializeProfiles();
        },

        bindEvents() {
            if (this.$addButton.length) {
                this.$addButton.on('click', (event) => {
                    event.preventDefault();
                    this.addProfile();
                });
            }

            if (!this.$container.length) {
                return;
            }

            this.$container.on('click', '.msh-remove-profile', (event) => {
                event.preventDefault();
                this.removeProfile($(event.currentTarget).closest('.msh-profile'));
            });

            this.$container.on('input', '.msh-profile input[name$="[label]"]', (event) => {
                const $input = $(event.currentTarget);
                const $fieldset = $input.closest('.msh-profile');
                const newValue = $input.val();
                this.autoFillSlug($fieldset, newValue);
            });

            this.$container.on('input', '.msh-profile input[name$="[id]"]', (event) => {
                const $input = $(event.currentTarget);
                $input.data('manual', $input.val().length > 0);
            });
        },

        addProfile() {
            if (!this.template) {
                return;
            }

            const raw = this.template.innerHTML;
            if (!raw) {
                return;
            }

            const nextIndex = this.getNextIndex();
            const html = raw.replace(/__index__/g, String(nextIndex));
            const $fragment = $(html);

            $fragment.attr('data-index', nextIndex);
            $fragment.find('details').attr('open', true);
            $fragment.find('input[name$="[id]"]').each(function() {
                $(this).data('manual', false);
            });

            this.$container.append($fragment);
            this.setNextIndex(nextIndex + 1);
        },

        removeProfile($fieldset) {
            if (!$fieldset.length) {
                return;
            }

            const confirmMessage = this.strings.deleteProfileConfirm || 'Remove this context profile?';
            if (window.confirm(confirmMessage)) {
                $fieldset.remove();
            }
        },

        autoFillSlug($fieldset, label) {
            if (!$fieldset.length) {
                return;
            }

            const $slugInput = $fieldset.find('input[name$="[id]"]').first();
            if (!$slugInput.length) {
                return;
            }

            if ($slugInput.data('manual')) {
                return;
            }

            const slug = this.slugify(label);
            $slugInput.val(slug);
        },

        slugify(value) {
            if (!value) {
                return '';
            }
            return value
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .replace(/-{2,}/g, '-')
                .substring(0, 60);
        },

        getNextIndex() {
            const attr = this.$container.attr('data-next-index');
            const current = parseInt(attr, 10);
            if (Number.isFinite(current) && current >= 0) {
                return current;
            }
            return this.$container.find('.msh-profile').length;
        },

        setNextIndex(index) {
            this.$container.attr('data-next-index', index);
        },

        initializeProfiles() {
            this.$container.find('.msh-profile').each((_, element) => {
                const $fieldset = $(element);
                const $slugInput = $fieldset.find('input[name$="[id]"]').first();
                if ($slugInput.length) {
                    $slugInput.data('manual', $slugInput.val().length > 0);
                }
            });
        }
    };

    $(document).ready(() => {
        SettingsPage.init();
    });
})(jQuery);

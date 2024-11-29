'use strict';

import Alpine from 'alpinejs';
import api from './api';
import { listResources } from './helpers';
import { toast } from '../base/toast';
import { CustomFormData } from '../formdata';

export function settingsView() {
    Alpine.data('settings', (path) => ({
        required: [],
        isProcessing: false,
        plans: [],
        plansFetched: false,

        init() {
            listResources('/plans')
                .then(plans => {
                    this.plans = plans;
                    this.plansFetched = true;
                });
        },

        submit() {
            if (this.isProcessing) {
                return;
            }

            this.isProcessing = true;

            let data = new CustomFormData(this.$refs.form);

            api.post(`/options${this.$refs.form.dataset.path || ''}`, data)
                .then(response => {
                    this.isProcessing = false;

                    toast.show(
                        'Changes saved successfully!',
                        'ti ti-square-rounded-check-filled'
                    );
                })
                .catch(error => this.isProcessing = false);
        },

        clearCache() {
            if (this.isProcessing) {
                return;
            }

            this.isProcessing = true;

            api.delete(`/cache`)
                .then(() => {
                    this.isProcessing = false;

                    toast.show(
                        'Cache cleared successfully!',
                        'ti ti-square-rounded-check-filled'
                    );
                })
                .catch(error => this.isProcessing = false);
        }
    }));

    Alpine.data('colorSchemes', (light, dark, def) => ({
        light: light,
        dark: dark,
        def: def,

        init() {
            ['light', 'dark'].forEach((scheme) => {
                this.$watch(scheme, (val) => {
                    if (!val) {
                        scheme == 'light' ? this.dark = true : this.light = true;

                        if (this.def == scheme) {
                            this.def = 'system';
                        }
                    }
                });
            });
        },
    }))
}
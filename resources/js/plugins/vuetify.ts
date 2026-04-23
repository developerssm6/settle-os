import '../../styles/layers.css';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';

import { createVuetify, type ThemeDefinition } from 'vuetify';

const light: ThemeDefinition = {
    dark: false,
    colors: {
        background:        '#F4F6FA',
        surface:           '#FFFFFF',
        'surface-variant': '#F4F6FA',
        primary:           '#1E63E4',
        secondary:         '#5A6678',
        success:           '#12855D',
        warning:           '#C6820A',
        error:             '#C0392B',
        info:              '#1E63E4',
    },
};

const dark: ThemeDefinition = {
    dark: true,
    colors: {
        background:        '#111419',
        surface:           '#111419',
        'surface-variant': '#1A1F27',
        primary:           '#7FA9F5',
        secondary:         '#9CA6B7',
        success:           '#4BC28E',
        warning:           '#F0B350',
        error:             '#F0685A',
        info:              '#7FA9F5',
    },
};

export default createVuetify({
    theme: {
        defaultTheme: 'light',
        themes: { light, dark },
    },
    display: {
        mobileBreakpoint: 'md',
        thresholds: {
            xs: 0, sm: 600, md: 960, lg: 1280, xl: 1920, xxl: 2560,
        },
    },
    defaults: {
        VCard:         { rounded: 'lg', elevation: 1 },
        VBtn:          { rounded: 'lg' },
        VTextField:    { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
        VSelect:       { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
        VAutocomplete: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
        VDataTable:    { density: 'compact', hover: true },
        VChip:         { rounded: 'pill' },
    },
});

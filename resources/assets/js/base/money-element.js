'use strict';

/**
 * Usage: <x-money data-value="1000" currency="USD" minor="2" fraction="true"></x-money>
 */
export class MoneyElement extends HTMLElement {
    static observedAttributes = [
        'data-value',
        'lang',

        'data-currency',
        'currency',

        'data-minor-units',
        'minor-units',

        'data-fraction',
        'fraction'
    ];

    constructor() {
        super();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        this.render();
    }

    render() {
        let lang = this.lang || document.documentElement.lang || 'en';
        let currency = this.getAttribute('currency') || this.dataset.currency || 'USD';
        let minorUnits = this.getAttribute('minor-units') || this.dataset.minorUnits || 2;
        let value = this.dataset.value || this.textContent;
        let amount = parseInt(value, 10) / Math.pow(10, minorUnits);

        let showFraction = this.getAttribute('fraction') || this.dataset.fraction;

        let options = {
            style: 'currency',
            currency: currency,
        };

        if (showFraction === 'false' || showFraction === '0' || showFraction === 'no' || showFraction === 'off') {
            // Explictly hide fraction
            options.minimumFractionDigits = 0;
            options.maximumFractionDigits = 0;
        } else if (showFraction === 'auto') {
            // Strip trailing zero if integer
            options.trailingZeroDisplay = 'stripIfInteger';
        } else {
            // Based on currency
            options.trailingZeroDisplay = 'auto';
        }

        let formatter = new Intl.NumberFormat(lang, options);
        this.textContent = formatter.format(amount);
    }

}
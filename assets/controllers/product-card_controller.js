import { Controller } from '@hotwired/stimulus';
import Routing from 'fos-routing';

export default class extends Controller {
    static values = {
        productId: Number
    }

    connect() {
        console.log(`Product card controller connected for product ID: ${this.productIdValue}`);
    }

    async showDetails(event) {
        event.preventDefault();

        const url = Routing.generate('produit_show', { id: this.productIdValue });
        console.log(`Navigating to: ${url}`);

        window.location.href = url;
    }
}

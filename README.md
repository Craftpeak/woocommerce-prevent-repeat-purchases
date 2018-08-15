# WooCommerce Prevent Repeat Purchases

A WooCommerce plugin to prevent customers from purchasing products more than once.

**This only works with Simple products!!**

## Requirements
 - WooCommerce
 - PHP 7+ (not actually, but don't be _that_ person)
 
## Installation
`composer require craftpeak/woocommerce-prevent-repeat-purchases`

(or do it the old fashioned way)

## Setup
For Simple products, there is a "Prevent Repeat Purchases?" checkbox in the "General" product metabox. If checked,
customers will only be able to purchase the product once (per account). This works via the `wc_customer_bought_product`
function, and is set on a product-by-product basis.

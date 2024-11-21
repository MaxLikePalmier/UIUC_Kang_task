=== Barcode Scanner and Inventory manager. POS (Point of Sale) - scan barcodes & create orders with barcode reader.
Contributors: UkrSolution <https://profiles.wordpress.org/ukrsolution>
Tags: barcode, scanner, inventory, POS, Point Of Sale
Requires at least: 4.0.1 
Tested up to: 6.5
Stable tag: trunk
Requires PHP: 5.6
License: GPLv2 or later 
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Fast POS to manage inventory. It reads barcodes & finds woocommerce products/orders. Create orders right from the wp-admin.

== Description ==

**✔ Find products/orders by scanning barcodes or by name/SKU/Id**
**✔ Manage product stock, prices and other product data**
**✔ Point of Sale (POS) - create orders by scanning barcodes**

https://youtu.be/MkGuoVlDgIs

This plugin for WooCommerce allows you to scan barcodes and manage your inventory/orders more efficiently. After barcode is scanned, plugin starts to search item automatically and displays product/order you were looking for. Also this plugin provides Point Of Sale (POS) functionality, so you can create WooCommerce orders right from the work place or checkout counter.

You can also switch to the "Enter" mode and search products/orders manually by typing ID/SKU/NAME/etc - and product/order will be found. 

We pay a lot of attention to search speed optimization, so even shops with thousands of items can count on fast responsiveness and comfortable work.

Plugin is fully compatible with WooCommerce. 

== Main features of barcode scanner:==

* Supports all types of barcode scanners
* Search of products by scanning barcodes
* Review & edit basic product information
* Manage stock quantity, price, etc.
* Search of orders
* Review order information
* Creation of new orders (POS functionality)
* Order fulfilment (item picking) functionality
* Auto actions*

Auto actions allows you to increase or decrease product quantity automatically simply by scanning products, so you don't have to change quantity value manually. It might be very useful for restocking items in your stock.

By default plugin allows to do search by Product ID, SKU, Name, however you can specify any other custom field (like UPC, EAN, etc) by which search should be done.

*Please note: This is a free trial version, so it comes with some limitations.*

**Possible use cases:**

1. Quick search of item:
If you have to search for products/orders regularly and you type id/sku/name manually - you may increase your productivity by using barcode scanner.
You will need to scan product/order barcode and it will be found as fast as possible without any additional interaction with keyboard.

2. Point of Sale (POS):
You can use barcode scanner as a Point of Sale (POS) system to create orders in offline stores.
You simply scan products which your customer wants to buy, and you will see prices for each item along with total order price.
POS functionality also includes proce adjustment for added products to the order and possibility to edit the end price of the order.
POS also allows to specify order status, final order price, apply coupons, specify woocommerce user and have some other features.

3. Restocking items:
If you received a new products from your vendor, you can use "auto actions" to increase products quantity in WooCommerce simply by scanning items.
Product quantity will be increased each time you scan a barcode and the good or error sound will be played. So, you even don't have to look at the screen to understand if it worked or not.

Your use case may be different, however barcode scanning & quick search is always helpful tool and can be adopted to stock management process.


More information about Barcode Scanner plugin here:

* [Barcode Scanner Plugin](https://www.ukrsolution.com/Wordpress/WooCommerce-Barcode-QRCode-Scanner-Reader)
* [FAQ](https://www.ukrsolution.com/Wordpress/WooCommerce-Barcode-QRCode-Scanner-Reader#faq)

== Installation ==

== Frequently Asked Questions == 

= Which scanning devices plugin supports ? =

Plugin supports any barcode scanners. There are no specific requirements to scaner devices.

= Which barcode types plugin supports ? =

Plugin supports any types of barcodes which are supported by your scanning device.

= How to configure plugin to search product by custom field ? =

You need to specify custom field name in "search fields" settings, which are located below search field. Make sure you entered a correct custom field from a database. Please ask your developer to get a proper custom field name from a database.

= How can I report security bugs?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/barcode-scanner-lite-pos-to-manage-products-inventory-and-orders)


== Screenshots ==

1. Ready to scan screen
2. Barcode scanned and product is found
3. Barcode scanned and order is found
4. Order creation by scanning products

== Changelog =

= 1.1.4 - 7 February 2022 =
* Added support of Schedule sale price.
* Improvements for Logs.
* Search by ID.

= 1.1.3 - 20 January 2022 =
* Added support out/in stock for parent products.
* Fixed issue with stock status.

= 1.1.2 - 17 January 2022 =
* Added possibility to use the plugin in offline mode.
* Added possibility to select any page on the front for scanner window.
* Added scanner to the top barcode.
* Fixed conflict with my-account page.
* Fixed issue with indexation.

= 1.1.1 - 30 December 2021 =
* UI Improvements.

= 1.1.0 - 24 December 2021 =
* Added integration with ATUM Inventory plugin.
* Added option to display custom product price.
* Added the "Note/Comment" field for Create Order dialog window.
* Added support of "Germanized für WooCommerce" plugin.
* Added support of the Woocommerce currency.
* Search by product name.
* Added support of the "MPN" field.
* Added "SKU " text field.
* Added option to link order to user.
* UI improvements.

= 1.0.6 - 23 November 2021 =
* Added logging of actions (business and premium plans).
* Added integration with We POS.
* Added option to edit product SKU.


= 1.0.5 - 18th November 2021 =
* Display the Barcode Scanner dialog window on a website frontend.
* Added support of the Adminimize plugin.
* Search fields redesign.

= 1.0.4 - 28th October 2021 =
* Display scanner window on website frontend.
* Added possibility to edit price and total order amount during creating an order.
* Supporting woocommerce currency.
* UI improvements.
* Fixed issue in Firefox.

= 1.0.3.1 - 7th September 2021 =
* Fixed issue with product images icons.

= 1.0.3 - 1st September 2021 =
* Added the "Support" page to the plugin menu.
* Added possibility edit title.
* Create new products.
* Turn on stock automatically.
* Mobile friendly.

= 1.0.2 - 3rd August 2021 =
* Added support of WPML
* User Interface improvements
* Fixes of minor bugs

= 1.0.1 - 15th July 2021 =
* First version

== Upgrade Notice ==
* Added new features

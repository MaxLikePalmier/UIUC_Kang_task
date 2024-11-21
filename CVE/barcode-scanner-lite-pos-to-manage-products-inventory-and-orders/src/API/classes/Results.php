<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\API\actions\CartScannerActions;
use UkrSolution\BarcodeScanner\features\interfaceData\InterfaceData;
use UkrSolution\BarcodeScanner\features\locations\LocationsData;
use UkrSolution\BarcodeScanner\features\settings\Settings;
use UkrSolution\BarcodeScanner\features\settings\SettingsHelper;

class Results
{
    private $settings = null;
    private $autoFill = false;
    public $filter_get_after = "barcode_scanner_%field_get_after";

    public function postsPrepare($posts, $withVariation)
    {
        $result = array();

        if (!$posts) return $result;

        if (count($posts) > 1) {
            foreach ($posts as $post) {
                if (in_array($post->post_type, ["product", "product_variation"])) {
                    $post = $this->formatProduct($post, array(), false, true);
                } else {
                    $post = $this->formatPostToRedirect($post, $withVariation);
                }

                if ($post) $result[] = $post;
            }
        } elseif (count($posts)) {
            $post = $posts[0];

            if (in_array($post->post_type, ["product", "product_variation"])) {
                $post = $this->formatProduct($post);
            } else {
                $post = $this->formatPostToRedirect($post, $withVariation);
            }

            if ($post) $result[] = $post;
        }

        return $result;
    }

    public function productsPrepare($posts, $additionalFields = array(), $autoFill = false)
    {
        $this->autoFill = $autoFill;
        $products = array();

        if (!$posts) return $products;

        if (count($posts) > 1) {
            foreach ($posts as $post) {
                $product = $this->formatProduct($post, $additionalFields, false, true);

                if ($product) $products[] = $product;
            }
        } elseif (count($posts)) {
            $product = $this->formatProduct($posts[0], $additionalFields);

            if ($product) $products[] = $product;
        }

        return $products;
    }

    public function ordersPrepare($posts, $additionalFields = array(), $autoFill = false, $page = '')
    {
        $this->autoFill = $autoFill;
        $orders = array();

        if (!$posts) return $orders;

        if (count($posts) > 1) {
            foreach ($posts as $post) {
                $order = $this->formatOrder($post, $additionalFields, $page);

                if ($order) $orders[] = $order;
            }
        } elseif (count($posts)) {
            $order = $this->formatOrder($posts[0], $additionalFields, $page);

            if ($order) $orders[] = $order;
        }

        return $orders;
    }

    private function formatPostToRedirect($post, $withVariation)
    {
        if ($post) {
            switch ($post->post_type) {
                case 'product':
                case 'shop_order':
                    $post->postEditUrl = admin_url('post.php?post=' . $post->ID) . '&action=edit';

                    return $post;
                case 'product_variation':
                    if ($withVariation === 0) {
                        $postParent = get_post($post->post_parent);

                        $postParent->ID = $post->ID;
                        $postParent->post_parent = $post->post_parent;
                        $postParent->post_title = strip_tags($post->post_title);
                        $postParent->post_type = $post->post_type;
                        $postParent->variation_id = $post->ID;
                        $post = $postParent;
                    }

                    $post->postEditUrl = isset($_POST["bsInstanceFrontendStatus"]) && $_POST["bsInstanceFrontendStatus"] ? get_permalink($post) : admin_url('post.php?post=' . $post->ID) . '&action=edit';

                    return $post;
            }
        }

        return null;
    }

    public function formatProduct($post, $additionalFields = array(), $isAddChild = true, $isGeneralProps = false)
    {
        $product = \wc_get_product($post->ID);

        if ($isGeneralProps && $product) {
            return $this->assignGeneralProps($post, $product, $additionalFields, $isAddChild);
        }

        if ($product) {
            return $this->assignProps($post, $product, $additionalFields, $isAddChild);
        }

        return null;
    }

    public function formatOrder($post, $additionalFields = array(), $page = '')
    {
        $order = wc_get_order($post->ID);

        if ($order) {
            $reflect = new \ReflectionClass($order);

            if ($reflect->getShortName() === "OrderRefund") {
                return null;
            }

            if ($page == 'orders_list') {
                return self::assignOrderListProps($post, $order, $additionalFields, $page);
            } else {
                return self::assignOrderProps($post, $order, $additionalFields, $page);
            }
        }

        return null;
    }

    public function formatCartScannerItem($item, $post, $priceField = "", $request = null)
    {
        if (!$this->settings) {
            $this->settings = new Settings();
        }

        $cartActions = new CartScannerActions();

        $customerUserId = $request ? $request->get_param("orderUserId") : null;

        $product = \wc_get_product($post->ID);

        $customPrice = $item->custom_price || $item->custom_price == "0" ? $item->custom_price : null;
        $customPrice = $cartActions->formatPriceForUpdate($customPrice);

        if (!$customPrice && $priceField) {
            $productData = $this->formatProduct(get_post($post->ID));

            if ($productData && isset($productData[$priceField]) && $productData[$priceField]) {
                $customPrice = $productData[$priceField];
                $customPrice = apply_filters("scanner_new_order_item_price", $customPrice, $item->quantity, $post->ID, $customerUserId);
            }
        }

        $linePrice = (float)$item->quantity ? (float)$item->price / $item->quantity : 0;

        $priceField = (new Results())->getFieldPrice($customerUserId);

        if ($customPrice != null || $customPrice == "0") {
            $linePrice = $customPrice;
        } else {
            $linePrice = $product->get_price();
            $linePrice = apply_filters("scanner_new_order_item_price", $linePrice, $item->quantity, $post->ID, $customerUserId);
        }

        if (!$customPrice) {
            if ($priceField) {
                $_id = $item->variation_id ? $item->variation_id : $item->product_id;
                $linePrice = (new Results())->getProductPrice(null, $priceField, $_id, $customerUserId);
            }
        }

        $linePrice = strip_tags(wc_price($linePrice, array("currency" => " ", "price_format" => '%2$s')));
        $linePrice = trim(str_replace("&nbsp;", "", $linePrice));
        $linePriceC = strip_tags(wc_price($linePrice));

        $_price = $customPrice || $customPrice == "0" ? $customPrice : (float)$item->price;

        $lineSubtotal = ($_price * $item->quantity);
        if (in_array(mb_substr($lineSubtotal, -1), array(".", ""))) $lineSubtotal = mb_substr($lineSubtotal, 0, -1);

        $lineSubtotalC = strip_tags(wc_price($_price * $item->quantity));
        if (in_array(mb_substr($lineSubtotalC, -1), array(".", ""))) $lineSubtotalC = mb_substr($lineSubtotalC, 0, -1);


        $lineTotal = ($_price * $item->quantity);
        if (in_array(mb_substr($lineTotal, -1), array(".", ""))) $lineTotal = mb_substr($lineTotal, 0, -1);

        $lineTotalC = strip_tags(wc_price($_price * $item->quantity));
        if (in_array(mb_substr($lineTotalC, -1), array(".", ""))) $lineTotalC = mb_substr($lineTotalC, 0, -1);

        $attributes = @json_decode($item->attributes, false);
        $attributes = $attributes ? $attributes : array();

        $additionalFields = array(
            "quantity" => $item->quantity,
            "quantity_step" => $item->quantity_step,
            "line_subtotal" => $lineSubtotal,
            "line_subtotal_c" => $lineSubtotalC,
            "line_total" => $lineTotal,
            "line_total_c" => $lineTotalC,
            "line_price" => $linePrice,
            "line_price_c" => $linePriceC,
            "variation" => $attributes,
            "variationForPreview" => $this->prepareVariationPreview($product, $attributes),
        );

        if ($product) {
            return $this->assignProps($post, $product, $additionalFields, false);
        }

        return null;
    }

    private function prepareVariationPreview($product, $variations)
    {
        $list = array();

        if (!$product) {
            return $list;
        }

        try {
            foreach ($variations as $key => $value) {
                $taxonomy = str_replace("attribute_", "", $key);
                $attributes = wc_get_product_terms($product->get_id(), $taxonomy, array('fields' => 'all'));
                $attributeValue = "";

                if ($attributes) {
                    foreach ($attributes as $term) {
                        if ($term->slug === $value) {
                            $attributeValue = $term->name;
                            break;
                        }
                    }
                } else {
                    $attributeValue = $value;
                }

                $list[] = array(
                    "label" => wc_attribute_label($taxonomy),
                    "value" => $attributeValue,
                );
            }

            return $list;
        } catch (\Throwable $th) {
            return $list;
        }
    }

    private function assignProps($post, $product, $additionalFields = array(), $isAddChild = true)
    {
        $parentProduct = $post->post_parent ? \wc_get_product($post->post_parent) : null;
        $postUrlId = ($post->post_parent) ? $post->post_parent : $post->ID;
        $postSuffix = "";

        if ($product->get_type() == "simple") {
            $postUrlId = $post->ID;
        }

        $translation = array();
        $translationProductsIds = array();

        $product_thumbnail_url = $this->getThumbnailUrl($post->ID);
        $product_large_thumbnail_url = $this->getThumbnailUrl($post->ID, 'large');
        $product_parent_thumbnail_url = "";
        $product_parent_large_thumbnail_url = "";
        $product_gallery = $this->getGallery($post->ID, $product);

        if ($post->post_parent) {
            $product_parent_thumbnail_url = $this->getThumbnailUrl($post->post_parent);
            $product_parent_large_thumbnail_url = $this->getThumbnailUrl($post->post_parent, 'large');
            $product_gallery = $this->getGallery($post->post_parent, $parentProduct);
        }

        if (isset($post->translation)) {
            $translation = $post->translation;

            if (isset($post->translation->language_code)) {
                $postSuffix = "&lang=" . $post->translation->language_code;
            }
        }

        if (isset($post->translationProductsIds)) {
            $translationProductsIds = $post->translationProductsIds;
        }

        $product_regular_price = strip_tags(wc_price($product->get_regular_price(), array("currency" => " ",)));
        $product_regular_price = trim(str_replace("&nbsp;", "", $product_regular_price));

        $product_sale_price = strip_tags(wc_price($product->get_sale_price(), array("currency" => " ",)));
        $product_sale_price = trim(str_replace("&nbsp;", "", $product_sale_price));

        $product_price = strip_tags(wc_price($product->get_price(), array("currency" => " ",)));
        $product_price = trim(str_replace("&nbsp;", "", $product_price));
        $product_price_c = html_entity_decode(strip_tags(wc_price($product_price)), ENT_COMPAT | ENT_HTML5, 'UTF-8');

        $attributes = $this->autoFill == false ? $product->get_attributes() : array();

        $post_title = $product->get_name();
        $post_title = @htmlspecialchars_decode($post_title);

        $_stock = get_post_meta($post->ID, "_stock", true);

        $categories = $post->post_parent ? get_the_terms($post->post_parent, 'product_cat') : get_the_terms($post->ID, 'product_cat');

        $tags = $post->post_parent ? get_the_terms($post->post_parent, 'product_tag') : get_the_terms($post->ID, 'product_tag');

        $variationAttributes = $parentProduct ? $parentProduct->get_variation_attributes() : null;

        $locationsTree = LocationsData::getLocations();

        $taxClasses = \wc_get_product_tax_class_options();

        if ($product->get_type() != "simple") {
            $taxClasses = array_merge(array("parent" => __("Same as parent", "woocommerce")), $taxClasses);
        }

        $shippingClass = $this->getShippingClass($post->ID);
        $shippingClasses = $this->getAllShippingClasses();

        $props = array(
            "ID" => $post->ID,
            "post_parent" => $product->get_parent_id(),
            "post_title" => $post_title ? $post_title : $product->get_name(),
            "post_type" => $post->post_type,
            "post_type_tooltip" => $product ? $product->get_type() : 'product',
            "post_status" => $post->post_status,
            "post_author" => $post->post_author,
            "product_desc" => urlencode($product->get_description()),
            "product_type" => $product->get_type(),
            "product_quantity" => $product->get_stock_quantity(),
            "product_manage_stock" => get_post_meta($post->ID, "_manage_stock", true) == "yes",
            "_tax_class" => get_post_meta($post->ID, "_tax_class", true),
            "_stock_status" => get_post_meta($post->ID, "_stock_status", true),
            "_stock" => $_stock ? sprintf('%g', $_stock) : $_stock,
            "product_sku" => $product->get_sku(),
            "product_regular_price" => $product_regular_price, 
            "product_sale_price" => $product_sale_price, 
            "product_price" => $product_price, 
            "product_price_c" => $product_price_c,
            "product_thumbnail_url" => $product_thumbnail_url,
            "product_large_thumbnail_url" => $product_large_thumbnail_url,
            "product_parent_thumbnail_url" => $product_parent_thumbnail_url,
            "product_parent_large_thumbnail_url" => $product_parent_large_thumbnail_url,
            "product_gallery" => $product_gallery,
            "variation_id" => $product->get_id(),
            "postEditUrl" => isset($_POST["bsInstanceFrontendStatus"]) && $_POST["bsInstanceFrontendStatus"] ? get_permalink($post) : admin_url('post.php?post=' . $postUrlId) . '&action=edit' . $postSuffix,
            "updated" => time(),
            "children" => $isAddChild && $this->autoFill == false ? $this->getChildren($product) : array(),
            "translation" => $translation,
            "translationProductsIds" => $translationProductsIds,
            "foundCounter" => $this->autoFill == false ? \get_post_meta($post->ID, "usbs_found_counter", true) : "",
            "locations" => $this->autoFill == false ? $this->getLocations($post->ID) : array(),
            "categories" => $categories,
            "tags" => $tags,
            "locationsTree" => $locationsTree,
            "taxClasses" => $taxClasses,
            "shippingClass" => $shippingClass,
            "shippingClasses" => $shippingClasses,
            "attributes" => $attributes,
            "variationAttributes" => $variationAttributes,
            "attributesLabels" => $this->autoFill == false ? $this->getAttributesLabels($attributes, $product->get_type()) : array(),
            "requiredAttributes" => $this->autoFill == false ? $this->getRequiredProductAttributes($product) : array(),
            "linkedAttributes" => $product->get_parent_id() ? $this->getLinkedAttributes($product->get_parent_id()) : $this->getLinkedAttributes($post->ID),
        );

        if (!$this->settings) {
            $this->settings = new Settings();
        }

        if ($this->autoFill == false && BatchNumbers::status()) {
            BatchNumbers::addProductProps($props);
        }

        if ($this->autoFill == false && BatchNumbersWebis::status()) {
            BatchNumbersWebis::addProductProps($props);
        }

        if ($this->autoFill == false) {
            try {
                foreach (InterfaceData::getFields(true, "", false, Users::userRole()) as $value) {
                    if (!$value['field_name']) continue;
                    $filterName = str_replace("%field", $value['field_name'], $this->filter_get_after);
                    $defaultValue = \get_post_meta($post->ID, $value['field_name'], true);
                    $filteredValue = apply_filters($filterName, $defaultValue, $value['field_name'], $props["ID"]);
                    $filteredValue = $value['field_name'] == "_stock" && $filteredValue ? sprintf('%g', $filteredValue) :  $filteredValue;
                    $props[$value['field_name']] = $filteredValue;
                }
            } catch (\Throwable $th) {
            }

            $oldPrice1 = $this->settings->getField("prices", "show_regular_price", "");
            if (($this->settings->getField("prices", "show_price_1", "on") === "on" || $oldPrice1 === "on") && $oldPrice1 !== "off") {
                $price1Field = $this->settings->getField("prices", "price_1_field", "_regular_price");

                if ($price1Field && isset($post->$price1Field)) {
                    $props[$price1Field] = $post->$price1Field;
                }
            }

            $oldPrice2 = $this->settings->getField("prices", "show_sale_price", "");
            if (($this->settings->getField("prices", "show_price_2", "on") === "on" || $oldPrice2 === "on") && $oldPrice2 !== "off") {
                $price2Field = $this->settings->getField("prices", "price_2_field", "_sale_price");

                if ($price2Field && isset($post->$price2Field)) {
                    $props[$price2Field] = $post->$price2Field;
                }
            }

            $oldPrice3 = $this->settings->getField("prices", "show_other_price", "");
            if (($this->settings->getField("prices", "show_price_3", "on") === "on" || $oldPrice3 === "on") && $oldPrice3 !== "off") {
                $price3Field = $this->settings->getField("prices", "other_price_field", "");
                if (!$price3Field) {
                    $price3Field = $this->settings->getField("prices", "price_3_field", "_purchase_price");
                }

                if ($price3Field && isset($post->$price3Field)) {
                    $props[$price3Field] = $post->$price3Field;
                }
            }
        }

        foreach ($additionalFields as $key => $value) {
            $props[$key] = $value;
        }





        return $props;
    }

    private function assignGeneralProps($post, $product, $additionalFields = array(), $isAddChild = true)
    {
        $postUrlId = ($post->post_parent) ? $post->post_parent : $post->ID;
        $postSuffix = "";

        if ($product->get_type() == "simple") {
            $postUrlId = $post->ID;
        }

        $translation = array();
        $translationProductsIds = array();

        $product_thumbnail_url = $this->getThumbnailUrl($post->ID);
        $product_large_thumbnail_url = $this->getThumbnailUrl($post->ID, 'large');
        $product_parent_thumbnail_url = "";
        $product_parent_large_thumbnail_url = "";

        if ($post->post_parent) {
            $product_parent_thumbnail_url = $this->getThumbnailUrl($post->post_parent);
            $product_parent_large_thumbnail_url = $this->getThumbnailUrl($post->post_parent, 'large');
        }

        if (isset($post->translation)) {
            $translation = $post->translation;

            if (isset($post->translation->language_code)) {
                $postSuffix = "&lang=" . $post->translation->language_code;
            }
        }

        if (isset($post->translationProductsIds)) {
            $translationProductsIds = $post->translationProductsIds;
        }

        $product_regular_price = strip_tags(wc_price($product->get_regular_price(), array("currency" => " ",)));
        $product_regular_price = trim(str_replace("&nbsp;", "", $product_regular_price));

        $product_sale_price = strip_tags(wc_price($product->get_sale_price(), array("currency" => " ",)));
        $product_sale_price = trim(str_replace("&nbsp;", "", $product_sale_price));

        $product_price = strip_tags(wc_price($product->get_price(), array("currency" => " ",)));
        $product_price = trim(str_replace("&nbsp;", "", $product_price));
        $product_price_c = html_entity_decode(strip_tags(wc_price($product_price)), ENT_COMPAT | ENT_HTML5, 'UTF-8');

        $attributes = $this->autoFill == false ? $product->get_attributes() : array();

        $post_title = $product->get_name();
        $post_title = @htmlspecialchars_decode($post_title);

        $_stock = get_post_meta($post->ID, "_stock", true);

        $props = array(
            "ID" => $post->ID,
            "post_parent" => $product->get_parent_id(),
            "post_title" => $post_title ? $post_title : $product->get_name(),
            "post_type" => $post->post_type,
            "post_type_tooltip" => $product ? $product->get_type() : 'product',
            "post_status" => $post->post_status,
            "post_author" => $post->post_author,
            "product_desc" => urlencode($product->get_description()),
            "product_type" => $product->get_type(),
            "product_quantity" => $product->get_stock_quantity(),
            "product_manage_stock" => get_post_meta($post->ID, "_manage_stock", true) == "yes",
            "_tax_class" => get_post_meta($post->ID, "_tax_class", true),
            "_stock_status" => get_post_meta($post->ID, "_stock_status", true),
            "_stock" => $_stock ? sprintf('%g', $_stock) : $_stock,
            "product_sku" => $product->get_sku(),
            "product_regular_price" => $product_regular_price,
            "product_sale_price" => $product_sale_price,
            "product_price" => $product_price,
            "product_price_c" => $product_price_c,
            "product_thumbnail_url" => $product_thumbnail_url,
            "product_large_thumbnail_url" => $product_large_thumbnail_url,
            "product_parent_thumbnail_url" => $product_parent_thumbnail_url,
            "product_parent_large_thumbnail_url" => $product_parent_large_thumbnail_url,
            "variation_id" => $product->get_id(),
            "postEditUrl" => isset($_POST["bsInstanceFrontendStatus"]) && $_POST["bsInstanceFrontendStatus"] ? get_permalink($post) : admin_url('post.php?post=' . $postUrlId) . '&action=edit' . $postSuffix,
            "updated" => time(),
            "attributes" => $attributes,
            "attributesLabels" =>  array(),
            "requiredAttributes" =>  array(),
            "children" =>  array(),
            "translation" => $translation,
            "translationProductsIds" => $translationProductsIds,
            "foundCounter" =>  "",
            "locations" => array(),
            "categories" => array(),
            "tags" => array(),
            "locationsTree" => array(),
            "taxClasses" => array(),
            "shippingClass" => "",
            "shippingClasses" => array(),
        );

        if (!$this->settings) {
            $this->settings = new Settings();
        }

        foreach ($additionalFields as $key => $value) {
            $props[$key] = $value;
        }

        return $props;
    }

    public function getLocations($productId)
    {
        try {
            $locationsList = ResultsHelper::getLocationsList();
            $result = array();

            foreach ($locationsList as $value) {
                $result[$value->slug] = get_post_meta($productId, $value->slug, true);
            }

            return $result;
        } catch (\Throwable $th) {
        }

        return array();
    }

    private function getShippingClass($productId)
    {
        global $wpdb;

        try {
            $classes = $wpdb->get_results("SELECT T.* 
                FROM {$wpdb->prefix}term_relationships AS R, {$wpdb->prefix}term_taxonomy AS TT, {$wpdb->prefix}terms AS T
                WHERE R.object_id = '{$productId}' 
                    AND TT.term_taxonomy_id = R.term_taxonomy_id AND TT.taxonomy = 'product_shipping_class'
                    AND T.term_id = TT.term_id;");

            if ($classes && count($classes) > 0) {
                return $classes[0]->slug;
            }
        } catch (\Throwable $th) {
        }

        return "";
    }

    private function getAllShippingClasses()
    {
        try {
            return get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        } catch (\Throwable $th) {
        }

        return array();
    }

    public function getChildren($product)
    {
        $result = array();

        if ($product->get_type() == "variable") {
            $post = get_post($product->get_id());
            $data = $this->formatProduct($post, array(), false);

            if ($data) $result[] = $data;
        } else if ($product->get_type() == "variation" && $product->get_parent_id()) {
            $post = get_post($product->get_parent_id());
            $data = $this->formatProduct($post, array(), false);
            $product = \wc_get_product($post->ID);

            if ($data) $result[] = $data;
        }

        $children = $product ? $product->get_children() : array();

        foreach ($children as $id) {
            $post = get_post($id);
            $product = $this->formatProduct($post, array(), false);

            if ($product) {
                $result[] = $product;
            }
        }

        return $result;
    }

    private function getAttributesLabels($attributes, $type)
    {
        $result = array();

        try {
            if ($type === "variable") {
                foreach ($attributes as $key => $value) {
                    if ($value->get_id() == 0) {
                        $result[$key] = ucfirst(strtolower($value->get_name()));
                    } else {
                        $result[$key] = \wc_attribute_label($value->get_name());
                    }
                }
            }

            if ($type === "variation") {
                foreach ($attributes as $key => $value) {
                    $name = \wc_attribute_label($key);

                    if ($name !== $key) {
                        $result[$key] =  $name;
                    } else {
                        $name =  str_replace("-", " ", $key);
                        $result[$key] =  ucfirst($name);
                    }
                }
            }
        } catch (\Throwable $th) {
        }

        return $result;
    }

    private function getLinkedAttributes($productId)
    {
        $product = \wc_get_product($productId);

        if (!$product) {
            return (object)array();
        }

        $attributes = $product->get_attributes();

        if (empty($attributes)) {
            return (object)array();
        }

        $attributesData = array("global" => array(), "custom" => array());

        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $data = $attribute->get_data();
                $data["label"] = wc_attribute_label($attribute->get_name());
                $data["terms"] = wc_get_product_terms($productId, $attribute->get_name(), ['fields' => 'names']);
                $attributesData["global"][] = $data;
            } else {
                $data = $attribute->get_data();
                $data["label"] = $data['name'];
                $attributesData["custom"][] = $data;
            }
        }

        return $attributesData;
    }

    private function getRequiredProductAttributes($product)
    {
        $result = array();

        if ($product->get_type() !== "variation") {
            return $result;
        }

        $attributes = $product->get_attributes();

        foreach ($attributes as $taxonomy => $value) {
            if (empty($value)) {
                $productTermId = $product->get_parent_id();
                $productTermId = $productTermId ? $productTermId : $product->get_id();

                $attrValues = wc_get_product_terms($productTermId, $taxonomy, array('fields' => 'all'));

                if (empty($attrValues)) {
                    $attrValues = $this->findLocalAttributeValues($product->get_parent_id(), $taxonomy);
                }

                $result[$taxonomy] = array(
                    'label' => wc_attribute_label($taxonomy),
                    'values' => $attrValues
                );
            }
        }

        return $result;
    }

    private function findLocalAttributeValues($productId, $attribute)
    {
        $result = array();
        $attributes = get_post_meta($productId, '_product_attributes', true);

        if (!$attributes) {
            return $result;
        }

        foreach ($attributes as $key => $value) {
            if ($key === $attribute && $value["value"]) {
                $values = explode("|", $value["value"]);

                foreach ($values as $attrValue) {
                    $result[] = array(
                        'slug' => trim($attrValue),
                        'name' => trim($attrValue),
                    );
                }
            }
        }

        return $result;
    }

    private function assignOrderProps($post, $order, $additionalFields = array(), $page = '')
    {
        global $wpdb;

        $products = array();
        $items = $order->get_items("line_item");
        $currencySymbol = get_woocommerce_currency_symbol(get_woocommerce_currency());

        $order_subtotal_taxes = array();
        $order_subtotal_tax = 0;

        $shipping_class_names = \WC()->shipping->get_shipping_method_class_names();

        $order_shipping = 0;
        $order_shipping_tax = 0;
        $order_shipping_name = "";
        $order_shipping_title = "";

        foreach ($order->get_items("shipping") as $value) {
            if ($value->get_total()) {
                $order_shipping += $value->get_total();
            }

            if ($value->get_total_tax()) {
                $order_shipping_tax += $value->get_total_tax();
            }

            $order_shipping_name = $value->get_name();
            $order_shipping_title = $value->get_method_title();
            $method_id = $value->get_method_id();
            $instance_id = $value->get_instance_id();

            try {
                if ($shipping_class_names && isset($shipping_class_names[$method_id])) {
                    $method_instance = new $shipping_class_names[$method_id]($instance_id);

                    if ($method_instance) {
                        $order_shipping_title = $method_instance->method_title;
                    }
                }
            } catch (\Throwable $th) {
            }
        }

        $order_payment = $order->get_payment_method();
        $order_payment_title = $order->get_payment_method_title();

        $additionalTaxes = array();

        foreach ($order->get_items("fee") as $value) {
            $additionalTaxes[] = array(
                "label" => $value->get_name(),
                "value" => $value->get_total(),
                "value_c" => strip_tags(wc_price($value->get_total())),
                "tax" => $value->get_total_tax(),
                "tax_c" => strip_tags(wc_price($value->get_total_tax())),
                "plugin" => "",
            );
        }

        foreach ($items as $item) {
            $variationId = $item->get_variation_id();
            $id = $variationId;

            if (!$id) {
                $id = $item->get_product_id();
            }
            $_post = get_post($id);


            if (!$_post) {
                $_post = (object)array("ID" => "", "post_parent" => "", "post_type" => "");
            }

            $product_thumbnail_url = $this->getThumbnailUrl($_post->ID);
            $product_large_thumbnail_url = $this->getThumbnailUrl($_post->ID, 'large');
            $product_parent_thumbnail_url = "";
            $product_parent_large_thumbnail_url = "";

            if ($_post->post_parent) {
                $product_parent_thumbnail_url = $this->getThumbnailUrl($_post->post_parent);
                $product_parent_large_thumbnail_url = $this->getThumbnailUrl($_post->post_parent, 'large');
            }

            $editId = $variationId && $_post->post_parent ? $_post->post_parent : $_post->ID;

            $args = array("currency" => " ", "thousand_separator" => "", "decimal_separator" => ".");

            $usbs_check_product_scanned = \wc_get_order_item_meta($item->get_id(), 'usbs_check_product_scanned', true);
            $usbs_check_product_scanned = $usbs_check_product_scanned == "" ? 0 : $usbs_check_product_scanned;

            $logRecord = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}barcode_scanner_logs AS L WHERE L.post_id = '{$item->get_id()}' AND L.field = 'usbs_check_product' AND L.action = 'update_order_item_meta' ORDER BY L.id DESC LIMIT 1");
            $fulfillment_user_name = "";
            $fulfillment_user_email = "";

            if ($logRecord && $logRecord->user_id) {
                $user = get_user_by("ID", $logRecord->user_id);

                if ($user) {
                    $fulfillment_user_name = $user->display_name ? $user->display_name : $user->user_nicename;
                    $fulfillment_user_email = $user->user_email;
                }
            }

            $quantity = \wc_get_order_item_meta($item->get_id(), '_qty', true);

            $_productData = array(
                "ID" => $_post->ID,
                "variation_id" => $variationId,
                "post_type" => $_post->post_type,
                "name" => strip_tags($item->get_name()),
                "quantity" => (float)$quantity,
                "price_c" => strip_tags(wc_price($item->get_total() / $quantity)),
                "subtotal" => $this->clearPrice($item->get_subtotal(), $args),
                "subtotal_c" => strip_tags(wc_price($item->get_subtotal())),
                "total" => $this->clearPrice($item->get_total(), $args),
                "total_c" => strip_tags(wc_price($item->get_total())),
                "subtotal_tax" => $this->clearPrice($item->get_subtotal_tax(), $args),
                "subtotal_tax_c" => strip_tags(wc_price($item->get_subtotal_tax())),
                "total_tax" => $this->clearPrice($item->get_total_tax(), $args),
                "total_tax_c" => strip_tags(wc_price($item->get_total_tax())),
                "item_price_tax" => $this->clearPrice(($item->get_subtotal() / $quantity) + $item->get_total_tax(), $args),
                "item_price_tax_c" => strip_tags(wc_price(($item->get_subtotal() / $quantity) + $item->get_total_tax())),
                "item_price_tax_total" => $this->clearPrice($item->get_subtotal() + $item->get_total_tax(), $args),
                "item_price_tax_total_c" => strip_tags(wc_price($item->get_total() + $item->get_total_tax())),
                "item_regular_price" => $this->clearPrice(get_post_meta($id, "_regular_price", true)), 
                "item_regular_price_c" => strip_tags(wc_price(get_post_meta($id, "_regular_price", true))), 
                "taxes" => strip_tags(wc_price($item->get_taxes())),
                "product_thumbnail_url" => $product_thumbnail_url,
                "product_large_thumbnail_url" => $product_large_thumbnail_url,
                "product_parent_thumbnail_url" => $product_parent_thumbnail_url,
                "product_parent_large_thumbnail_url" => $product_parent_large_thumbnail_url,
                "postEditUrl" => admin_url('post.php?post=' . $editId) . '&action=edit',
                "locations" => $this->getLocations($_post->ID),
                "item_id" => $item->get_id(),
                "usbs_check_product" => \wc_get_order_item_meta($item->get_id(), 'usbs_check_product', true),
                "usbs_check_product_scanned" => $usbs_check_product_scanned,
                "fulfillment_user_name" => $fulfillment_user_name,
                "fulfillment_user_email" => $fulfillment_user_email,
                "product_categories" => wp_get_post_terms($item->get_product_id(), 'product_cat'),
            );

            foreach (InterfaceData::getFields(true, "", false, Users::userRole()) as $value) {
                if (!isset($value['field_name']) || !$value['field_name']) continue;
                $filterName = str_replace("%field", $value['field_name'], $this->filter_get_after);
                $defaultValue = \get_post_meta($_productData["ID"], $value['field_name'], true);
                $filteredValue = apply_filters($filterName, $defaultValue, $value['field_name'], $_productData["ID"]);
                $filteredValue = $filteredValue;
                $_productData[$value['field_name']] = $filteredValue;
            }

            $products[] = $_productData;

            $_taxes = $item->get_taxes();

            if ($_taxes && isset($_taxes["total"]) && is_array($_taxes["total"])) {
                foreach ($_taxes["total"] as $tax_rate_id => $tax_amount) {
                    if ($tax_amount) {
                        $order_subtotal_tax += $tax_amount;

                        if (isset($order_subtotal_taxes[$tax_rate_id])) {
                            $order_subtotal_taxes[$tax_rate_id]['cost'] += $tax_amount;
                            $order_subtotal_taxes[$tax_rate_id]['cost_c'] = strip_tags(wc_price($order_subtotal_taxes[$tax_rate_id]['cost']));
                        } else {
                            $order_subtotal_taxes[$tax_rate_id] = array(
                                'label' => \WC_Tax::get_rate_label($tax_rate_id),
                                'cost' => $tax_amount,
                                'cost_c' => strip_tags(wc_price($tax_amount)),
                                'rate_id' => $tax_rate_id,
                            );
                        }
                    }
                }
            }
        }

        $customerId = $order->get_customer_id();
        $user = $order->get_user();
        $userData = null;
        $customerName = '';

        if ($customerId && $user) {
            $userData = $user->data;
            $userData->phone = get_user_meta($user->ID, 'billing_phone', true);
            $userData->avatar = @get_avatar_url($customerId);

            if ($user->display_name) {
                $customerName = $user->display_name;
            } else {
                $customerName = get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true);
            }
        }

        $wpFormat = get_option("date_format", "F j, Y") . " " . get_option("time_format", "g:i a");
        $orderDate = new \DateTime($order->get_date_created());
        $date_format = $orderDate->format($wpFormat);
        $date_format = SettingsHelper::dateTranslate($date_format);

        if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
            $customerName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        }

        $customerCountry = $order->get_billing_country();
        $previewDateFormat = $orderDate->format("M j, Y");
        $previewDateFormat = SettingsHelper::dateTranslate($previewDateFormat);

        $authorName = get_user_meta($customerId, "first_name", true);
        $authorName .= " " . get_user_meta($customerId, "last_name", true);
        $authorName = trim($authorName);

        try {
            if (empty($authorName)) {
                $user = $customerId ? new \WP_User($customerId) : null;
                $authorName = $user ? $user->display_name : "";
            }
        } catch (\Throwable $th) {
        }


        $logRecord = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}barcode_scanner_logs AS L WHERE L.post_id = '{$order->get_id()}' AND L.action = 'update_order_fulfillment' AND L.value = '1' ORDER BY L.id DESC LIMIT 1");
        $fulfillment_user_name = "";
        $fulfillment_user_email = "";

        if ($logRecord && $logRecord->user_id) {
            $_user = get_user_by("ID", $logRecord->user_id);

            if ($_user) {
                $fulfillment_user_name = $_user->display_name ? $_user->display_name : $_user->user_nicename;
                $fulfillment_user_email = $_user->user_email;
            }
        }

        $settings = new Settings();

        $fulfillmentField = $settings->getSettings("orderFulFillmentField");
        $fulfillmentField = $fulfillmentField === null ? "" : $fulfillmentField->value;

        $orderStatusesAreStillNotCompleted = $settings->getSettings("orderStatusesAreStillNotCompleted");
        $orderStatusesAreStillNotCompleted = $orderStatusesAreStillNotCompleted === null ? "wc-pending,wc-processing,wc-on-hold" : $orderStatusesAreStillNotCompleted->value;

        $sortOrderItemsByCategories = $settings->getSettings("sortOrderItemsByCategories");
        $sortOrderItemsByCategories = $sortOrderItemsByCategories === null ? "" : $sortOrderItemsByCategories->value;

        $bStates = WC()->countries->get_states($order->get_billing_country());
        $bState  = !empty($bStates[$order->get_billing_state()]) ? $bStates[$order->get_billing_state()] : '';

        $sStates = WC()->countries->get_states($order->get_shipping_country());
        $sState  = !empty($sStates[$order->get_shipping_state()]) ? $sStates[$order->get_shipping_state()] : '';

        $receiptShortcodes = ResultsHelper::getReceiptShortcodes($settings, $order->get_id());

        $props = array(
            "ID" => $order->get_id(),
            "post_type" => $post->post_type,
            "post_author" => $post->post_author,
            "data" => array(
                "billing" => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'postcode' => $order->get_billing_postcode(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'state_name' => $bState,
                    'country' => $order->get_billing_country(),
                    'country_name' => $order->get_billing_country() ? WC()->countries->countries[$order->get_billing_country()] : "",
                ),
                "shipping" => array(
                    'first_name' => $order->get_shipping_first_name(),
                    'last_name' => $order->get_shipping_last_name(),
                    'company' => $order->get_shipping_company(),
                    'address_1' => $order->get_shipping_address_1(),
                    'address_2' => $order->get_shipping_address_2(),
                    'postcode' => $order->get_shipping_postcode(),
                    'city' => $order->get_shipping_city(),
                    'state' => $order->get_shipping_state(),
                    'state_name' => $sState,
                    'country' => $order->get_shipping_country(),
                    'country_name' => $order->get_shipping_country() ? WC()->countries->countries[$order->get_shipping_country()] : "",
                ),
                "customer_note" => $this->getNotes($order),
                "total_tax" => $order->get_total_tax(),
                "status" => $order->get_status(),
                "customFields" => $this->getOrderCustomFields($order->get_id()),
            ),
            "order_date" => $order->get_date_created(),
            "date_format" => $date_format,
            "preview_date_format" => $previewDateFormat,
            "usbs_fulfillment_objects" => get_post_meta($order->get_id(), "usbs_fulfillment_objects", true),
            "usbs_order_fulfillment_data" => get_post_meta($order->get_id(), "usbs_order_fulfillment_data", true),
            "user" => $userData,
            "order_tax" => $order->get_total_tax(),
            "order_tax_c" => strip_tags(wc_price($order->get_total_tax())),
            "order_subtotal" => $order->get_subtotal(),
            "order_subtotal_c" => strip_tags(wc_price($order->get_subtotal())),
            "order_subtotal_taxes" => array_values($order_subtotal_taxes),
            "order_subtotal_tax" => $order_subtotal_tax,
            "order_subtotal_tax_c" => strip_tags(wc_price($order_subtotal_tax)),
            "order_shipping" => $order_shipping,
            "order_shipping_c" => strip_tags(wc_price($order_shipping)),
            "order_shipping_tax" => $order_shipping_tax,
            "order_shipping_tax_c" => strip_tags(wc_price($order_shipping_tax)),
            "order_shipping_name" => $order_shipping_name,
            "order_shipping_title" => $order_shipping_title,
            "order_payment" => $order_payment,
            "order_payment_title" => $order_payment_title,
            "additionalTaxes" => $additionalTaxes,
            "order_total" => $order->get_total(),
            "order_total_c" => strip_tags(wc_price($order->get_total())),
            "customer_id" => $customerId,
            "customer_name" => trim($customerName),
            "author_name" => trim($authorName),
            "customer_country" => $customerCountry,
            "products" => $sortOrderItemsByCategories == "on" && !in_array($page, array('history', 'orders_list')) ? ProductsHelper::sortProductsByCategories($products) : $products,
            "currencySymbol" => $currencySymbol,
            "statuses" => wc_get_order_statuses(),
            "postEditUrl" => admin_url('post.php?post=' . $post->ID) . '&action=edit',
            "postPayUrl" => $order->get_checkout_payment_url(),
            "updated" => time(),
            "foundCounter" => \get_post_meta($post->ID, "usbs_found_counter", true),
            "fulfillment_user_name" => $fulfillment_user_name,
            "fulfillment_user_email" => $fulfillment_user_email,
            "discount" => $order->get_discount_total() ?  strip_tags($order->get_discount_to_display()) : "",
            "coupons" => $order->get_coupon_codes(),
            "shop" => ResultsHelper::getStoreData(),
            "receiptShortcodes" => $receiptShortcodes,
            "pickListTemplate" => !in_array($page, array('history', 'orders_list')) ? PickList::getTemplate($post, $order, $settings) : '',
            "customer_orders_count" => 0,
            "user_pending_orders_count" => ResultsHelper::get_user_pending_orders_count($customerId, $post->ID, $orderStatusesAreStillNotCompleted),
        );

        if ($customerId) {
            $customerOrders = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(P.ID) AS 'count' FROM {$wpdb->prefix}posts AS P, {$wpdb->prefix}postmeta AS PM WHERE P.ID = PM.post_id AND P.post_type = 'shop_order' AND PM.meta_key = '_customer_user' AND PM.meta_value = %d;",
                $customerId
            ));

            $props["customer_orders_count"] = $customerOrders ? $customerOrders->count : 0;

        }

        if ($fulfillmentField) {
            $props[$fulfillmentField] = get_post_meta($post->ID, $fulfillmentField, true);
            $props[$fulfillmentField . "-filled"] = get_post_meta($post->ID, $fulfillmentField . "-filled", true);
        }

        $props["_order_number"] = get_post_meta($post->ID, "_order_number", true);
        $props["_billing_address_index"] = get_post_meta($post->ID, "_billing_address_index", true);
        $props["_shipping_address_index"] = get_post_meta($post->ID, "_shipping_address_index", true);
        $props["ywot_tracking_code"] = get_post_meta($post->ID, "ywot_tracking_code", true);

        $props["_wc_shipment_tracking_items_list"] = array();
        $wcShipmentTrackingItems = get_post_meta($post->ID, "_wc_shipment_tracking_items", true);
        $_wc_shipment_tracking_items = "";
        if ($wcShipmentTrackingItems && is_array($wcShipmentTrackingItems)) {
            foreach ($wcShipmentTrackingItems as $value) {
                if (isset($value["tracking_number"])) {
                    $_wc_shipment_tracking_items .= " " . $value["tracking_number"];
                    $props["_wc_shipment_tracking_items_list"][] = $value;
                }
            }
        }
        $props["_wc_shipment_tracking_items"] = trim($_wc_shipment_tracking_items);

        $aftershipTrackingItems = get_post_meta($post->ID, "_aftership_tracking_items", true);
        $_aftership_tracking_items = "";
        if ($aftershipTrackingItems && is_array($aftershipTrackingItems)) {
            foreach ($aftershipTrackingItems as $value) {
                if (isset($value["tracking_number"])) $_aftership_tracking_items .= " " . $value["tracking_number"];
            }
        }
        $props["_aftership_tracking_items"] = trim($_aftership_tracking_items);

        foreach ($additionalFields as $key => $value) {
            $props[$key] = $value;
        }

        return $props;
    }

    private function assignOrderListProps($post, $order, $additionalFields = array(), $page = '')
    {
        $customerId = $order->get_customer_id();
        $user = $order->get_user();
        $customerName = '';

        if ($customerId && $user) {
            $user = $user->data;

            if ($user->display_name) {
                $customerName = $user->display_name;
            } else {
                $customerName = get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true);
            }
        }

        $wpFormat = get_option("date_format", "F j, Y") . " " . get_option("time_format", "g:i a");
        $orderDate = new \DateTime($order->get_date_created());
        $date_format = $orderDate->format($wpFormat);
        $date_format = SettingsHelper::dateTranslate($date_format);

        if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
            $customerName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        }

        $previewDateFormat = $orderDate->format("M j, Y");
        $previewDateFormat = SettingsHelper::dateTranslate($previewDateFormat);

        $settings = new Settings();

        $fulfillmentField = $settings->getSettings("orderFulFillmentField");
        $fulfillmentField = $fulfillmentField === null ? "" : $fulfillmentField->value;

        $props = array(
            "ID" => $order->get_id(),
            "post_type" => $post->post_type,
            "post_author" => $post->post_author,
            "data" => array(
                "status" => $order->get_status(),
            ),
            "order_date" => $order->get_date_created(),
            "date_format" => $date_format,
            "preview_date_format" => $previewDateFormat,
            "usbs_fulfillment_objects" => get_post_meta($order->get_id(), "usbs_fulfillment_objects", true),
            "usbs_order_fulfillment_data" => get_post_meta($order->get_id(), "usbs_order_fulfillment_data", true),
            "order_total" => $order->get_total(),
            "order_total_c" => strip_tags(wc_price($order->get_total())),
            "customer_name" => trim($customerName),
            "products" => array(),
            "total_products" => count($order->get_items("line_item")),
            "updated" => time(),
            "customer_orders_count" => 0,
        );

        if ($fulfillmentField) {
            $props[$fulfillmentField] = get_post_meta($post->ID, $fulfillmentField, true);
            $props[$fulfillmentField . "-filled"] = get_post_meta($post->ID, $fulfillmentField . "-filled", true);
        }

        $props["_order_number"] = get_post_meta($post->ID, "_order_number", true);

        return $props;
    }

    private function clearPrice($price, $args = array())
    {
        $price = trim(strip_tags(wc_price($price, $args)));
        $price = str_replace("&nbsp;", "", $price);

        return $price;
    }

    public function getNotes($order)
    {
        $result = "-";

        try {
            $result = $order->get_customer_note();
        } catch (\Throwable $th) {
        }

        return $result;
    }

    public function getOrderCustomFields($orderId)
    {
        $fields = InterfaceData::getOrderFields();
        $result = array();

        if (!$fields || !$orderId) return $result;

        foreach ($fields as $key => $value) {
            if (isset($value['name'])) {
                $value['value'] = get_post_meta($orderId, $value['name'], true);
                $result[] = $value;
            }
        }

        return $result;
    }

    public function getThumbnailUrl($postID, $size = "medium")
    {
        return get_the_post_thumbnail_url($postID, $size);
    }

    public function getGallery($postID, $product)
    {
        $gallery = array();

        if ($product) {
            $gallery_attachment_ids = $product->get_gallery_image_ids();


            foreach ($gallery_attachment_ids as $attachment_id) {
                $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
                $large_image_url = wp_get_attachment_image_url($attachment_id, 'large');

                if ($image_url) {
                    $gallery[] = array(
                        'id' => $attachment_id,
                        'url' => $image_url,
                        'large_url' => $large_image_url,
                    );
                }
            }
        }

        return $gallery;
    }

    public function getUserProductTax($price, $productTaxClass, $taxAddress, $returnSum = true)
    {
        $tax = 0;

        try {
            $isPricesIncludeTax = \wc_prices_include_tax();

            $tax_rates_data = $this->getUserProductTaxRates($productTaxClass, $taxAddress);

            $tax_amounts = \WC_Tax::calc_tax($price, $tax_rates_data, $isPricesIncludeTax);

            if ($returnSum) $tax = array_sum($tax_amounts);
            else $tax = $tax_amounts;
        } catch (\Throwable $th) {
        }

        return $tax;
    }


    public function getUserProductTaxRates($productTaxClass, $taxAddress = array())
    {
        try {

            $tax_obj = new \WC_Tax();

            $country = isset($taxAddress["country"]) ? $taxAddress["country"] : "";
            $state = isset($taxAddress["state"]) ? $taxAddress["state"] : "";
            $city = isset($taxAddress["city"]) ? $taxAddress["city"] : "";
            $postcode = isset($taxAddress["postcode"]) ? $taxAddress["postcode"] : "";

            $tax_rates_data = $tax_obj->find_rates(array(
                'country' => $country ? $country : "*",
                'state' =>  $state ? $state : "*",
                'city' => $city ? $city : "*",
                'postcode' =>  $postcode ? $postcode : "*",
                'tax_class' =>  $productTaxClass
            ));

            return $tax_rates_data;
        } catch (\Throwable $th) {
        }

        return array();
    }



    public function getAddressShippingPriceTax($price, $taxAddress)
    {
        $tax = 0;

        try {
            $tax_rate = $this->getAddressTaxClass($taxAddress);


            if ($tax_rate) {
                $taxes = \WC_Tax::calc_tax($price, $tax_rate);

                if ($taxes) {
                    foreach ($taxes as $value) {
                        $tax += $value;
                    }
                }
            }
        } catch (\Throwable $th) {
        }

        return $tax;
    }

    public function getShopTaxRate($taxClass = "")
    {
        $tax_obj = new \WC_Tax();

        $address = get_option('woocommerce_store_address', '');
        $city = get_option('woocommerce_store_city', '');
        $state = get_option('woocommerce_store_state', '');
        $postcode = get_option('woocommerce_store_postcode', '');
        $country = get_option('woocommerce_default_country', '');
        $countryParts = explode(':', $country);
        $country = $countryParts[0];

        if (function_exists("WC")) {
            $city = WC()->countries->get_base_city();
            $state = WC()->countries->get_base_state();
            $postcode = WC()->countries->get_base_postcode();
            $country = WC()->countries->get_base_country();
        }


        $tax_rates_data = $tax_obj->find_rates(array(
            'country' => $country ? $country : "*",
            'state' => $state ? $state : "*",
            'city' => $city ? $city : "*",
            'postcode' => $postcode ? $postcode : "*",
        ));

        return apply_filters('woocommerce_matched_rates', $tax_rates_data, $taxClass);
    }

    public function getAddressTaxClass($taxAddress, $taxClass = "")
    {
        try {
            $tax_obj = new \WC_Tax();

            $country = isset($taxAddress["country"]) ? $taxAddress["country"] : "";
            $state = isset($taxAddress["state"]) ? $taxAddress["state"] : "";
            $city = isset($taxAddress["city"]) ? $taxAddress["city"] : "";
            $postcode = isset($taxAddress["postcode"]) ? $taxAddress["postcode"] : "";

            $tax_rates_data = $tax_obj->find_rates(array(
                'country' => $country ? $country : "*",
                'state' => $state ? $state : "*",
                'city' => $city ? $city : "*",
                'postcode' => $postcode ? $postcode : "*",
            ));

            return apply_filters('woocommerce_matched_rates', $tax_rates_data, $taxClass);
        } catch (\Throwable $th) {
        }

        return array();
    }

    public function getFieldPrice($customerId)
    {
        $settings = new Settings();

        $field = $settings->getSettings("defaultPriceField");
        $field = $field === null ? $settings->getField("prices", "defaultPriceField", "wc_default") : $field->value;

        if ($field === "_price_1" || $field === "_regular_price") {
            $field = $settings->getField("prices", "price_1_field", "_regular_price");
        } else if ($field === "_price_2" || $field === "_sale_price") {
            $field = $settings->getField("prices", "price_2_field", "_sale_price");
        } else if ($field === "_price_3" || $field === "custom_price") {
            $oldField = $settings->getField("prices", "other_price_field", "");
            $field = $oldField ? $oldField : $settings->getField("prices", "price_3_field", "_purchase_price");
        } else if ($field === "wc_default") {
            $field = "";
        }

        return apply_filters("scanner_new_order_item_price_field_filter", $field, $customerId);
    }

    public function getProductPrice($product, $field, $productId = null, $customerId = null, $quantity = 1)
    {
        if (!$product && $productId) {
            $product = \wc_get_product($productId);
        }

        if ($product) {
            if ($field) {
                $price = get_post_meta($product->get_id(), $field, true);
            } else {
                $price = $product->get_price();
            }

            return apply_filters("scanner_new_order_item_price", $price, $quantity, $product->get_id(), $customerId);
        } else {
            return "";
        }
    }

    public function jsonResponse($data)
    {
        @header('Content-type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}

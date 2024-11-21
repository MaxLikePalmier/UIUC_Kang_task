<?php

namespace UkrSolution\BarcodeScanner\API\classes;

class ProductsHelper
{
    public static function sortProductsByCategories($products)
    {
        $allCategories = get_terms('product_cat', array('hide_empty' => true));

        $categoryOrder = array();
        $index = 0;

        foreach ($allCategories as $category) {

            if ($category->parent == 0) {
                $categoryOrder[$category->term_id] = $index++;

                foreach ($allCategories as $categoryS) {
                    if ($categoryS->parent == $category->term_id) {
                        $categoryOrder[$categoryS->term_id] = $index++;
                    }
                }
            }
        }

        usort($products, function ($a, $b) use ($categoryOrder) {
            $minOrderA = PHP_INT_MAX;
            $minOrderB = PHP_INT_MAX;

            if (isset($a['product_categories'])) {
                foreach ($a['product_categories'] as $catA) {
                    if (isset($categoryOrder[$catA->term_id])) {
                        $minOrderA = min($minOrderA, $categoryOrder[$catA->term_id]);
                    }
                }
            }

            if (isset($b['product_categories'])) {
                foreach ($b['product_categories'] as $catB) {
                    if (isset($categoryOrder[$catB->term_id])) {
                        $minOrderB = min($minOrderB, $categoryOrder[$catB->term_id]);
                    }
                }
            }

            if ($minOrderA == $minOrderB && isset($a['product_categories']) && isset($b['product_categories'])) {
                foreach ($a['product_categories'] as $index => $catA) {
                    if (isset($b['product_categories'][$index])) {
                        $catB = $b['product_categories'][$index];

                        $orderA = isset($categoryOrder[$catA->term_id]) ? $categoryOrder[$catA->term_id] : null;
                        $orderB = isset($categoryOrder[$catB->term_id]) ? $categoryOrder[$catB->term_id] : null;

                        if ($orderA != $orderB) {
                            return ($orderA < $orderB) ? -1 : 1;
                        }
                    }
                }
                return 0;
            }

            return ($minOrderA < $minOrderB) ? -1 : 1;
        });

        return $products;

        $sortedProductIndexes = array();

        foreach ($allCategories as $cat) {
            foreach ($products as $index => $product) {
                if (in_array($index, $sortedProductIndexes)) {
                    continue;
                }

                if (isset($product["product_categories"]) && $product["product_categories"]) {
                    foreach ($product["product_categories"] as $productCategory) {
                        if ($productCategory->term_id == $cat->term_id) {
                            $sortedProductIndexes[] = $index;
                            break 2;
                        }
                    }
                }
            }
        }

        $sortedProducts = array();

        foreach ($sortedProductIndexes as $index) {
            $sortedProducts[] = $products[$index];
        }

        foreach ($products as $index => $product) {
            if (!in_array($index, $sortedProductIndexes)) {
                $sortedProducts[] = $products[$index];
            }
        }

        return $sortedProducts;
    }
}

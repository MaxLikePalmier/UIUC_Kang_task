<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\features\Debug\Debug;

class WPML
{

    static public function status()
    {

        if (function_exists('icl_object_id')) {
            return true;
        }

        return false;
    }

    static public function getTranslations()
    {
        $translations = array();

        try {
            $translations = icl_get_languages('skip_missing=0&orderby=KEY&order=DIR&link_empty_to=str');
        } catch (\Throwable $th) {
        }

        return $translations;
    }

    static public function getAdminLang()
    {
        try {
            return ICL_LANGUAGE_CODE;
        } catch (\Throwable $th) {
        }

        return "";
    }

    static public function getProductTranslations($productId)
    {
        try {
            if (Polylang::status()) {
                return Polylang::getProductTranslations($productId);
            }
        } catch (\Throwable $th) {
            Debug::addPoint("Throwable.getProductTranslations Polylang = " . $productId . ", " . $th->getMessage());
        }

        $result = array(
            "trid" => null,
            "translations" => array()
        );

        try {
            global $sitepress;

            if (!isset($sitepress)) return $result;

            $trid = $sitepress->get_element_trid($productId, 'post_product');

            $translations = $sitepress->get_element_translations($trid, 'product');

            $result["trid"] = $trid;
            $result["translations"] = $translations;
        } catch (\Throwable $th) {
            Debug::addPoint("Throwable.getProductTranslations = " . $productId . ", " . $th->getMessage());
        }

        return $result;
    }

    static public function postsFilter($posts, $filter)
    {
        $actualPosts = array();
        $languages = array();

        if (Polylang::status()) {
            return Polylang::postsFilter($posts, $filter);
        }

        if (isset($filter["wpml"])) $languages = $filter["wpml"];

        try {
            Debug::addPoint("languages = " . json_encode($languages));

            foreach ($posts as &$post) {
                $translations = self::getProductTranslations($post->ID);
                Debug::addPoint("translations = " . json_encode($translations));

                $activeTranslate = null;

                foreach ($translations["translations"] as $lang => $translation) {
                    if (key_exists($lang, $languages) && $languages[$lang] && $translation->element_id == $post->ID) {
                        $activeTranslate = $translation;
                    }
                }

                Debug::addPoint("activeTranslate = " . json_encode($activeTranslate));

                if (count($translations) > 0 && $activeTranslate) {
                    $post->translationProductsIds = array_column($translations["translations"], 'element_id');
                    $post->translation = $activeTranslate;
                    $actualPosts[] = $post;
                } else if (!count($translations["translations"])) {
                    $actualPosts[] = $post;
                } else if ($post->post_type === "shop_order") {
                    $actualPosts[] = $post;
                }
            }
        } catch (\Throwable $th) {
            Debug::addPoint("Throwable.postsFilter = " . $th->getMessage());
        }

        return $actualPosts;
    }

    static public function addTranslations(&$posts)
    {
        $actualPosts = array();
        $languages = array();

        try {
            foreach ($posts as &$post) {
                $translations = self::getProductTranslations($post["ID"]);
                $activeTranslate = null;

                foreach ($translations["translations"] as $lang => $translation) {
                    if ($translation->element_id == $post["ID"]) {
                        $activeTranslate = $translation;
                    }
                }

                if (count($translations) > 0 && $activeTranslate) {
                    $post["translationProductsIds"] = array_column($translations["translations"], 'element_id');
                    $post["translation"] = $activeTranslate;
                }
            }
        } catch (\Throwable $th) {
        }

        return $actualPosts;
    }
}

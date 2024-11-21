<?php

namespace UkrSolution\BarcodeScanner\API\classes;

use UkrSolution\BarcodeScanner\API\PluginsHelper;
use UkrSolution\BarcodeScanner\features\Debug\Debug;

class Polylang
{

    static public function status()
    {
        if (class_exists("Polylang_Woocommerce") || PluginsHelper::is_plugin_active('polylang/polylang.php') || PluginsHelper::is_plugin_active('polylang-pro/polylang.php')) {
            return true;
        }

        return false;
    }

    static public function getProductTranslations($productId)
    {
        $result = array(
            "trid" => null,
            "translations" => array()
        );

        try {
            $postTranslations = get_the_terms($productId, 'post_translations');

            if (!$postTranslations || !count($postTranslations)) {
                return $result;
            }

            $ids = @unserialize($postTranslations[0]->description);

            foreach ($ids as $lang => $id) {
                $result["translations"][$lang] = (object)array("element_id" => $id, "language_code" => $lang);
            }
        } catch (\Throwable $th) {
            Debug::addPoint("Polylang Throwable.getProductTranslations = " . $productId . ", " . $th->getMessage());
        }

        return $result;
    }

    static public function postsFilter($posts, $filter)
    {
        $actualPosts = array();
        $languages = array();

        if (isset($filter["wpml"])) $languages = $filter["wpml"];

        try {
            Debug::addPoint("Polylang languages = " . json_encode($languages));

            foreach ($posts as $post) {
                $translations = self::getProductTranslations($post->ID);
                Debug::addPoint("Polylang translations = " . json_encode($translations));

                $activeTranslate = null;

                foreach ($translations["translations"] as $lang => $translation) {
                    if (key_exists($lang, $languages) && $languages[$lang] && $translation->element_id == $post->ID) {
                        $activeTranslate = $translation;
                    }
                }

                Debug::addPoint("Polylang activeTranslate = " . json_encode($activeTranslate));

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
            Debug::addPoint("Polylang Throwable.postsFilter = " . $th->getMessage());
        }

        return $actualPosts;
    }

    static public function addTranslations(&$posts)
    {
        $actualPosts = array();
        $languages = array();
        echo '<pre>';
        print_r("addTranslations");
        exit();

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

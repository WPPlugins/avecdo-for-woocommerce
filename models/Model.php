<?php

namespace Avecdo;

use AvecdoSDK\POPO\Category;
use AvecdoSDK\POPO\Product;
use AvecdoSDK\POPO\Product\StockStatus;
use AvecdoSDK\POPO\Product\Combination;

if (!defined('ABSPATH')) {
    exit;
}

class Model extends WooQueries
{
    private static $weight_unit    = null;
    private static $dimension_unit = null;

    /**
     * @todo quick fix  remove when avecdo supports, variations/combinations
     */
    private $lastIssetPrice = 0;

    /**
     * @return Model
     */
    public static function make()
    {
        return new static();
    }

    /**
     * Class constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // @todo check if wee need this for products thats set to allow back orders
    private function assignAvecdoProductStock($productId, $avecdoProduct)
    {
        // 
        // Works fine but we do not set default stock value
        // so no need for extra sql calls
        //
        //$productId = (int)$productId;
        //global $wpdb;
        //$meta_query   = "SELECT
        //                     t1.post_id,
        //                     t1.meta_value as stock, 
        //                     t2.meta_value as backorders, 
        //                     t3.meta_value as manage_stock 
        //                FROM ". $wpdb->prefix ."postmeta AS t1
        //                INNER JOIN  ". $wpdb->prefix ."postmeta AS t2 
        //                ON          t2.post_id=t1.post_id 
        //                AND         t2.meta_key='_backorders'
        //                
        //                INNER JOIN  ". $wpdb->prefix ."postmeta AS t3 
        //                ON          t3.post_id=t1.post_id 
        //                AND         t3.meta_key='_manage_stock'
        //                
        //                WHERE t1.post_id = {$productId}
        //                AND   t1.meta_key='_stock'";
        //if($query_result = $wpdb->get_results($meta_query, OBJECT)) {
        //    $query_result = $query_result[0];
        //    
        //    $avecdoProduct->setStockQuantity($query_result->stock);
        //    if((int)$query_result->stock<=0 && $query_result->manage_stock == "no") {
        //        /* if the shop do not manage stock then set default of 20. */
        //        $avecdoProduct->setStockQuantity(20);
        //    } else if((int)$query_result->stock<=0 && $query_result->backorders != "no") {
        //        /* if the shop manage stock but allow backorders then set default of 20. */
        //        $avecdoProduct->setStockQuantity(20);
        //    }
        //}
    }

    /**
     * Assign categories and tags to avecdo product object
     * @param int $productId
     * @param Product $avecdoProduct
     * @param int[] $categoryIds added for better performance when fetching related products
     * @param int[] $tagIds added for better performance when fetching related products
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function assignAvecdoProductCategoriesAndTags($productId, Product $avecdoProduct, array &$categoryIds, array &$tagIds)
    {
        $cats = array();

        foreach ($this->getCategoriesTagsAndShippingClasses($productId) as $postmeta) {
            if ($postmeta->taxonomy == "product_tag") {
                $tagIds[] = $postmeta->term_id;
                $avecdoProduct->addToTags($postmeta->name);
            } else if ($postmeta->taxonomy == "product_cat") {
                $categoryIds[]            = $postmeta->term_id;
                $cats[$postmeta->term_id] = array(
                    'categoryId' => $postmeta->term_id,
                    'name'       => $postmeta->name,
                    'parent'     => $postmeta->parent
                );
            }
        }

        if (!empty($cats)) {
            foreach ($cats as $cat) {
                $fullName                     = array();
                $fullName[$cat['categoryId']] = $cat['name'];
                $parent                       = (int) $cat['parent'];
                while ($parent != 0) {
                    if (isset($cats[$parent])) {
                        $fullName[$cats[$parent]['categoryId']] = $cats[$parent]['name'];
                        $parent                                 = (int) $cats[$parent]['parent'];
                    } else {
                        $parent = 0;
                    }
                }
                ksort($fullName);
                $avecdoProduct->addToCategories($cat['categoryId'], $cat['parent'], implode(" > ", $fullName));
            }
        }
    }

    /**
     * Assign all metadata to avecdo product object
     * @param int $productId
     * @param Product|Combination $avecdoProduct
     * @param int[] $imagesIds output all image ids for this product.
     * @param boolen $combination
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function assignAvecdoProductMetaData($productId, $avecdoProduct, array &$imagesIds, $combination = false)
    {
        $methodInFix = $combination ? 'Combination' : '';

        $method = "noneExistingMethod";
        foreach ($this->getMetaData($productId) as $metaRow) {
            switch ($metaRow->meta_key) {
                case "_tax_class":
                case "_tax_status":
                case "_purchase_note":
                case "_price":
                case "_sale_price_dates_from":
                case "_sale_price_dates_to":
                case "_wp_attached_file":
                case "_wp_attachment_metadata":
                case "_sold_individually":
                case "total_sales":
                case "_crosssell_ids":
                case "_upsell_ids":
                case "_wp_old_slug":
                case "_wp_page_template":
                /* @todo see 'assignAvecdoProductStock' */
                case "_backorders":
                case "_manage_stock":
                    /* we set these at the top so we don't loop for values that's never going to be used. */
                    break;
                case "_regular_price":
                    $method               = "set{$methodInFix}Price";
                    $metaValue            = (float) $metaRow->meta_value;
                    /**
                     * @todo quick fix  remove when avecdo supports, variations/combinations
                     */
                    $this->lastIssetPrice = $metaValue;
                    break;
                case "_sku":
                    $method               = "set{$methodInFix}Sku";
                    $metaValue            = $metaRow->meta_value;
                    break;
                case "_product_image_gallery":
                    if (!empty($metaRow->meta_value)) {
                        $_metaValue = array_map('intval', explode(',', $metaRow->meta_value));
                        $imagesIds  = array_merge($imagesIds, $_metaValue);
                    }
                    break;
                case "_thumbnail_id":
                    $imagesIds[] = (int) $metaRow->meta_value;
                    break;
                case "_sale_price":
                    $method      = "set{$methodInFix}PriceSale";
                    $metaValue   = (float) $this->getSalePrice($productId);
                    break;
                case "_weight":
                    $method      = "set{$methodInFix}Weight";
                    $metaValue   = (float) $metaRow->meta_value;
                    break;
                case "_height":
                    $method      = "set{$methodInFix}DimensionHeight";
                    $metaValue   = (float) $metaRow->meta_value;
                    break;
                case "_length":
                    $method      = "set{$methodInFix}DimensionDepth";
                    $metaValue   = (float) $metaRow->meta_value;
                    break;
                case "_width":
                    $method      = "set{$methodInFix}DimensionWidth";
                    $metaValue   = (float) $metaRow->meta_value;
                    break;
                case "_stock":
                    $metaValue   = (float) $metaRow->meta_value;
                    $method      = "set{$methodInFix}StockQuantity";
                    if (!method_exists($avecdoProduct, $method) && $combination) {
                        $method = "setCombinationQuantity";
                    }
                    break;
                case "_stock_status":
                    $method    = "set{$methodInFix}StockStatus";
                    $metaValue = ($metaRow->meta_value == 'instock' ? StockStatus::IN_STOCK : StockStatus::OUT_OF_STOCK);
                    break;
                case "_default_attributes":
                    /* default attribute for product with combinations */
                    //$_metaValue = unserialize($metaRow->meta_value);
                    // eg: pa_color=>black
                    break;
                case "_product_attributes":
                    /* indicates that the product has attributes */
                    //$_metaValue = unserialize($metaRow->meta_value);
                    break;
                default:
                    /*
                      _max_variation_price
                      _max_variation_regular_price
                      _max_variation_sale_price
                      _min_variation_price
                      _min_variation_regular_price
                      _min_variation_sale_price
                     */
                    // starts with 'attribute_'
                    $this->assignAvecdoProductMetaData_Attribute($metaRow, $avecdoProduct, $combination);
                    break;
            }
            if (method_exists($avecdoProduct, $method)) {
                $avecdoProduct->{$method}($metaValue);
                // @todo see 'assignAvecdoProductStock'
                if (strpos($method, 'Quantity') !== false) {
                    $this->assignAvecdoProductStock($productId, $avecdoProduct);
                }
            }
        }
    }

    public function getProducts($page, $limit, $lastRun)
    {
        $offset   = ((((int) $page == 0 ? 1 : (int) $page) - 1) * (int) $limit);
        $products = array();

        $currency = get_woocommerce_currency();

        // plain products
        foreach ($this->getSimpleProductList((int) $offset, (int) $limit) as $row) {
            /*
             * @todo quick fix  remove when avecdo supports, variations/combinations
             */
            $this->lastIssetPrice = 0;
            $productId            = intval($row->productId);
            $avecdoProduct        = new Product();
            $avecdoProduct
                ->setInternalId($productId)
                ->setProductId($productId)
                ->setParentId($row->parentId)
                ->setName($row->name)
                ->setDescription($row->description)
                ->setWeightUnit($this->getWeightUnit())
                ->setDimensionUnit($this->getDimensionUnit())
                ->setCurrency($currency)
                /* default: all products are in stock, and we later look for stock status key. */
                ->setStockStatus(StockStatus::IN_STOCK)
                ->setUrl(get_permalink($productId))
                ->setShippingCost(null);


            $imagesIds = array();
            // set product metadata and output image ids.
            $this->assignAvecdoProductMetaData($productId, $avecdoProduct, $imagesIds);

            // set product images
            $this->assignAvecdoProductImages($imagesIds, $avecdoProduct);

            // set categories and tags.
            $categoryIds = array();
            $tagIds      = array();
            $this->assignAvecdoProductCategoriesAndTags($productId, $avecdoProduct, $categoryIds, $tagIds);

            // related products
            foreach ($this->getRelatedProductsByCategoriesAndTags($categoryIds, $tagIds) as $relatedProduct) {
                if ($relatedProduct->productId == $productId) {
                    continue;
                }
                $avecdoProduct->addToRelatedProducts($relatedProduct->productId, $relatedProduct->name);
            }

            /**
             * quick fix variation  price
             * @todo remove when avecdo supports, variations/combinations
             */
            $this->setPriceOnProductWithZeroPriceAndHasChildren($productId, $avecdoProduct);
            // on product of type variation set combinations.
//            avecdo dos not currently support product variations
//            if ($this->hasChilden($productId)) {
//                foreach ($this->getProductVairationsByProductId((int) $productId) as $child) {
//                    $this->setAvecdoProductCombination($avecdoProduct, $child);
//                }
//            }

            $products[] = $avecdoProduct->getAll();
        }

        return $products;
    }

    /**
     * quick fix variation  price
     * @todo remove when avecdo supports, variations/combinations
     */
    private function setPriceOnProductWithZeroPriceAndHasChildren($productId, & $avecdoProduct)
    {
        if ($this->hasChildren($productId) && $this->lastIssetPrice == 0) {
            $largestPrice     = 0;
            $largestSalePrice = 0;
            foreach ($this->getProductVairationsByProductId((int) $productId) as $child) {
                $metaData = $this->getMetaData((int) $child->productId);
                foreach ($metaData as $metaRow) {
                    switch ($metaRow->meta_key) {
                        case "_price":
                            $metaValue = (float) $metaRow->meta_value;
                            if ($largestPrice >= $metaValue) {
                                $largestPrice = $metaValue;
                            }
                            break;
                        case "_regular_price":
                            $metaValue = (float) $metaRow->meta_value;
                            if ($largestPrice == 0 || $largestPrice > $metaValue) {
                                $largestPrice = $metaValue;
                            }
                            break;
                        case "_sale_price":
                            $largestSalePrice = (float) $metaRow->meta_value;
                            break;
                    }
                }
            }
            if ($largestPrice > 0) {
                $avecdoProduct->setPrice($largestPrice);
            }
            if ($largestSalePrice > 0) {
                $avecdoProduct->setPrice($largestSalePrice);
            }
        }
    }

    /**
     * Set combinations for product
     * @param Product $avecdoProduct
     * @param object $product must contain [productId, parentId, name, description]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function setAvecdoProductCombination(Product $avecdoProduct, $product)
    {
//        $productId   = (int) $product->productId;
//        $parentId    = (int) $product->parentId;
//        $name        = $product->name;
//        $description = $product->description;
//
//        $avecdoCombination = new Combination($avecdoProduct);
//        $avecdoCombination
//            ->setParentProductId($parentId)
//            ->setCombinationId($productId)
//            ->setCombinationWeightUnit($this->getWeightUnit())
//            ->setCombinationDimensionUnit($this->getDimensionUnit())
//            ->setCombinationUrl(get_permalink($productId))
//            ->setCombinationName($name);
//
//        $imagesIds = array();
//        $this->assignAvecdoProductMetaData($productId, $avecdoCombination, $imagesIds, true);
//
//        $this->assignAvecdoProductImages($imagesIds, $avecdoCombination, true);
//
//        $avecdoProduct->addToCombinations($avecdoCombination);
    }

    /**
     * Assign product images to avecdo product object
     * @param int[] $imagesIds
     * @param Product|Combination $avecdoProduct
     * @param boolean $combination
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function assignAvecdoProductImages($imagesIds, $avecdoProduct, $combination = false)
    {
        $methodInFix = $combination ? 'Combination' : '';
        if (!is_array($imagesIds) || empty($imagesIds)) {
            return;
        }
        $imgsAdded = array();
        foreach ($this->getImagesData($imagesIds) as $postmeta) {
            if (in_array($postmeta->post_id, $imgsAdded)) {
                continue;
            }
            $imgsAdded[] = $postmeta->post_id;
            $url         = avecdoBuildFullMediaUrl($postmeta->file, $postmeta->post_id);
            $meta        = unserialize($postmeta->meta);
            $text        = avecdoGetImageTitleFromMeta($meta, $postmeta->image_alt);
            $avecdoProduct->{"addTo{$methodInFix}Images"}($url, $text);
        }
    }

    /**
     * Assign attribute to avecdo product
     * @param object $metaRow must contain [meta_key, meta_value]
     * @param Product|Combination $avecdoProduct
     * @param boolean $combination
     * @return void
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function assignAvecdoProductMetaData_Attribute($metaRow, $avecdoProduct, $combination)
    {
        $methodInFix = $combination ? 'Combination' : '';
        if (strpos($metaRow->meta_key, 'attribute_') !== false) {
            // Taxonomy-based attributes are prefixed with pa_, otherwise simply attribute_.
            $taxonomyBase = (0 === strpos($metaRow->meta_key, 'attribute_pa_'));
            if ($taxonomyBase) {
                $name       = str_replace('attribute_pa_', '', $metaRow->meta_key);
                $attrObject = $this->getAttributeTaxonomyByName($name);
                $id         = is_array($attrObject) && isset($attrObject[0]->id) ? $attrObject[0]->id : 0;
                $avecdoProduct->{"addTo{$methodInFix}Attributes"}($id, $name, $metaRow->meta_value);
            } else {
                $avecdoProduct->{"addTo{$methodInFix}Attributes"}(0, str_replace('attribute_', '', $metaRow->meta_key), $metaRow->meta_value);
            }
        }
    }

    /**
     * create an avecdo category from $categpry object.
     *
     * -- Christian M. Jensen <christian@modified.dk>
     * Added category description, image and url.
     *
     * @param array $category
     * @return array
     */
    public function createAvecdoCategory($category)
    {
        if (!is_array($category)) {
            return;
        }
        $avecdoCategory = new Category();
        $avecdoCategory
            ->setCategoryId($category['categoryId'])
            ->setParent($category['parent'])
            ->setFullName($category['fullName'])
            ->setDepth($category['depth'])
            ->setName($category['name'])
            ->setDescription($category['description'])
            ->setImage($category['image'])
            ->setUrl($category['url']);
        return $avecdoCategory->getAll();
    }

    /**
     * Get value to use for units in weight
     * @return string
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function getWeightUnit()
    {
        if (is_null(static::$weight_unit)) {
            return static::$weight_unit = get_option('woocommerce_weight_unit', '');
        }
        return static::$weight_unit;
    }

    /**
     * Get value to use for units in dimension
     * @return string
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function getDimensionUnit()
    {
        if (is_null(static::$dimension_unit)) {
            return static::$dimension_unit = get_option('woocommerce_dimension_unit', '');
        }
        return static::$dimension_unit;
    }

    /**
     * Get all product categories
     * @param int $number Maximum number of terms to return. Accepts 0 (all) or any positive number. Default 0 (all).
     * @param int $offset The number by which to offset the terms query.
     * @param bool|int $hide_empty Whether to hide terms not assigned to any posts. Accepts 1|true or 0|false. Default 1|true.
     * @param bool $hierarchical Whether to include terms that have non-empty descendants (even if $hide_empty is set to true). Default true.
     * @param bool $pad_counts Whether to pad the quantity of a term's children in the quantity of each term's "count" object variable. Default false.
     * @param string $orderby Field(s) to order terms by. Accepts term fields ('name', 'slug', 'term_group', 'term_id', 'id', 'description'), 'count' for term taxonomy count, 'include' to match the 'order' of the $include param, 'meta_value', 'meta_value_num', the value of $meta_key, the array keys of $meta_query, or 'none' to omit the ORDER BY clause. Defaults to 'term_id'.
     * @param string $order Whether to order terms in ascending or descending order. Accepts 'ASC' (ascending) or 'DESC' (descending). Default 'ASC'.
     * @return array
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    private function loadCategories($number = 0, $offset = 0, $hide_empty = false, $hierarchical = true, $pad_counts = false, $orderby = 'term_id', $order = 'ASC')
    {
        $results        = array();
        $taxonomy       = 'product_cat';
        $args           = array(
            'taxonomy'     => $taxonomy,
            'orderby'      => $orderby,
            'order'        => $order,
            'parent'       => 0,
            'pad_counts'   => $pad_counts,
            'hierarchical' => $hierarchical,
            'hide_empty'   => $hide_empty,
            'number'       => $number,
            'offset'       => $offset,
        );
        $all_categories = get_categories($args);
        if (is_wp_error($all_categories)) {
            return array();
        }
        foreach ($all_categories as $cat) {
            if (is_wp_error($cat)) {
                continue;
            }
            $results += $this->getCategoryLoopItem($cat, '', $args, 1);
        }
        return $results;
    }
    /*
     * Load categories that has '$category_id' as parent
     * @param int $category_id parent category id
     * @param string $fullname the full name of the parrent categories
     * @param array $args ans array of arguments to use with function get_categories
     * @param int $depth current category depth
     * @return array
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */

    private function loadSubCategories($category_id, $fullname, $args, $depth = 1)
    {
        $results         = array();
        $subQuery        = array('parent' => $category_id);
        $args2           = array_merge($args, $subQuery);
        $args2['number'] = 0;
        $args2['offset'] = 0;
        $sub_cats        = get_categories($args2);
        if (is_wp_error($sub_cats)) {
            return array();
        }
        foreach ($sub_cats as $cat) {
            if (is_wp_error($cat)) {
                continue;
            }
            $results += $this->getCategoryLoopItem($cat, $fullname, $args, $depth);
        }
        return $results;
    }

    /**
     * get category item from category object or array
     * @param array|object $cat
     * @param string $fullname
     * @param array $args
     * @param int $depth
     * @return array
     */
    private function getCategoryLoopItem($cat, $fullname, $args, $depth = 1)
    {
        $results  = array();
        $fullname = $this->getCategoryFullName($fullname, $cat);
        if (is_object($cat)) {
            $term_link = get_term_link($cat->term_id, 'product_cat');
            if (is_wp_error($term_link)) {
                $term_link = "";
            }
            $thumbnail_id = get_woocommerce_term_meta($cat->term_id, 'thumbnail_id', true);
            $image        = !is_null($thumbnail_id) ? wp_get_attachment_url($thumbnail_id) : null;

            $results[$cat->term_id] = $this->createAvecdoCategory(array(
                'categoryId'  => $cat->term_id,
                'name'        => !empty($cat->name) ? $cat->name : $cat->cat_name,
                'fullName'    => $fullname,
                'description' => !empty($cat->description) ? $cat->description : $cat->category_description,
                'parent'      => !empty($cat->parent) ? $cat->parent : $cat->category_parent,
                'url'         => $term_link,
                'depth'       => $depth,
                'image'       => $image
            ));
            // loop over any nested categories
            $results                += $this->loadSubCategories($results[$cat->term_id]['categoryId'], $results[$cat->term_id]['fullName'], $args, ++$depth);
        } else if (is_array($cat)) {
            $term_id   = (int) $cat['term_id'];
            $term_link = get_term_link($term_id, 'product_cat');
            if (is_wp_error($term_link)) {
                $term_link = "";
            }
            $thumbnail_id      = get_woocommerce_term_meta($term_id, 'thumbnail_id', true);
            $image             = !is_null($thumbnail_id) ? wp_get_attachment_url($thumbnail_id) : null;
            $results[$term_id] = $this->createAvecdoCategory(array(
                'categoryId'  => $term_id,
                'name'        => !empty($cat['name']) ? $cat['name'] : $cat['cat_name'],
                'fullName'    => $fullname,
                'description' => !empty($cat['description']) ? $cat['description'] : $cat['category_description'],
                'parent'      => !empty($cat['parent']) ? $cat['parent'] : $cat['category_parent'],
                'url'         => $term_link,
                'depth'       => $depth,
                'image'       => $image
            ));
            // loop over any nested categories
            $results           += $this->loadSubCategories($results[$term_id]['categoryId'], $results[$term_id]['fullName'], $args, ++$depth);
        }
        return $results;
    }

    /**
     * get the full name of the category
     * @param string $fullname
     * @param array|object $cat
     * @return string
     */
    private function getCategoryFullName($fullname, $cat)
    {
        if (!empty($fullname) && is_object($cat)) {
            return $fullname.' > '.(!empty($cat->name) ? $cat->name : $cat->cat_name);
        } else if (empty($fullname) && is_object($cat)) {
            return (!empty($cat->name) ? $cat->name : $cat->cat_name);
        } else if (!empty($fullname) && is_array($cat)) {
            return $fullname.' > '.(!empty($cat['name']) ? $cat['name'] : $cat['cat_name']);
        } else if (empty($fullname) && is_array($cat)) {
            return (!empty($cat['name']) ? $cat['name'] : $cat['cat_name']);
        }
        return $fullname;
    }

    /**
     * Get all categories in the shop.
     * @return array
     */
    public function getCategories()
    {
        return $this->loadCategories();
    }
}
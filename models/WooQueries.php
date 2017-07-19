<?php

namespace Avecdo;

if (!defined('ABSPATH')) {
    exit;
}

class WooQueries
{
    private static $_sttributeTaxonomyCache = array();
    protected $wpdb;
    protected $wpdb_prefix;

    /**
     * Class constructor.
     * @global \wpdb $wpdb
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->wpdb_prefix = $wpdb->prefix;
    }

    /**
     * Gets a boolean value indicating if the product has any children
     * @param int $productId
     * @return boolean
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.2.3
     */
    protected function hasChildren($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return false;
        }
        $query        = "SELECT ID FROM ".$this->wpdb_prefix."posts WHERE post_parent={$productId} LIMIT 1";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? true : false;
    }
    /**
     * Alias of 'hasChildren'
     * @deprecated since v1.2.3, miss spelled. Use: 'hasChildren'
     * @param int $productId
     * @return boolean
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function hasChilden($productId)
    {
        return $this->hasChildren($productId);
    }

    /**
     * Get product variations for a product by product id
     * @param int $productId
     * @return \stdClass[] [productId, parentId, name, description]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getProductVairationsByProductId($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return array();
        }
        $query        = "SELECT IFNULL(post_content, post_excerpt) AS description,
                    post_title AS name, ID AS productId,
                    post_parent AS parentId FROM ".$this->wpdb_prefix."posts
                    WHERE ".$this->wpdb_prefix."posts.post_parent = {$productId}
                    AND ".$this->wpdb_prefix."posts.post_type = 'product_variation'
                    AND ".$this->wpdb_prefix."posts.post_status = 'publish'
                    ORDER BY ".$this->wpdb_prefix."posts.ID";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get attribute id and label by attribute name
     * @param string $name
     * @return \stdClass[] [id, name, label]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getAttributeTaxonomyByName($name)
    {
        if (empty($name)) {
            return array();
        }
        if (isset(self::$_sttributeTaxonomyCache[$name])) {
            return self::$_sttributeTaxonomyCache[$name];
        }
        $query                                = $this->wpdb->prepare("
            SELECT
                attribute_id as id,
                attribute_name as name,
                attribute_label as label
            FROM ".$this->wpdb_prefix."woocommerce_attribute_taxonomies
            WHERE attribute_name='%s'", array($name)
        );
        $query_result                         = $this->wpdb->get_results($query, OBJECT);
        self::$_sttributeTaxonomyCache[$name] = $query_result ? $query_result : array();
        return self::$_sttributeTaxonomyCache[$name];
    }

    /**
     * Get related products by tags
     * @param int[] $tagIds
     * @param int $limit
     * @param int $offset
     * @return \stdClass[] [productId, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getRelatedProductsByTags(array $tagIds, $limit = 5, $offset = 0)
    {
        if (empty($tagIds)) {
            return array();
        }
        $tags = implode(',', array_map('intval', $tagIds));
        $tags = trim($tags);
        if (empty($tags)) {
            return array();
        }
        $query        = "SELECT ".$this->wpdb_prefix."posts.ID AS productId, ".$this->wpdb_prefix."posts.post_title AS name FROM ".$this->wpdb_prefix."posts
		INNER JOIN ".$this->wpdb_prefix."term_relationships AS tt1 ON ".$this->wpdb_prefix."posts.ID = tt1.object_id
		INNER JOIN ".$this->wpdb_prefix."postmeta ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."postmeta.post_id
		WHERE tt1.term_taxonomy_id IN ({$tags})
		AND ".$this->wpdb_prefix."posts.post_type = 'product'
		AND ".$this->wpdb_prefix."posts.post_status = 'publish'
		AND ".$this->wpdb_prefix."postmeta.meta_key = '_visibility'
		AND CAST(".$this->wpdb_prefix."postmeta.meta_value AS CHAR) IN ('visible','catalog')
		GROUP BY ".$this->wpdb_prefix."posts.ID ORDER BY ".$this->wpdb_prefix."posts.ID DESC LIMIT {$offset}, {$limit}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get related products by categories
     * @param int[] $categoryIds
     * @param int $limit
     * @param int $offset
     * @return \stdClass[] [productId, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getRelatedProductsByCategories(array $categoryIds, $limit = 6, $offset = 0)
    {
        if (empty($categoryIds)) {
            return array();
        }
        $categoris = implode(',', array_map('intval', $categoryIds));
        $categoris = trim($categoris);
        if (empty($categoris)) {
            return array();
        }
        $query        = "SELECT ".$this->wpdb_prefix."posts.ID AS productId, ".$this->wpdb_prefix."posts.post_title AS name FROM ".$this->wpdb_prefix."posts
		INNER JOIN ".$this->wpdb_prefix."term_relationships AS tt1 ON ".$this->wpdb_prefix."posts.ID = tt1.object_id
		INNER JOIN ".$this->wpdb_prefix."postmeta ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."postmeta.post_id
		WHERE tt1.term_taxonomy_id IN ({$categoris})
		AND ".$this->wpdb_prefix."posts.post_type = 'product'
		AND ".$this->wpdb_prefix."posts.post_status = 'publish'
		AND ".$this->wpdb_prefix."postmeta.meta_key = '_visibility'
		AND CAST(".$this->wpdb_prefix."postmeta.meta_value AS CHAR) IN ('visible','catalog')
		GROUP BY ".$this->wpdb_prefix."posts.ID ORDER BY ".$this->wpdb_prefix."posts.ID DESC LIMIT {$offset}, {$limit}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get related products by categories and tags
     * @param int[] $categoryIds
     * @param int[] $tagIds
     * @param int $limit
     * @param int $offset
     * @return \stdClass[] [productId, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getRelatedProductsByCategoriesAndTags(array $categoryIds, array $tagIds, $limit = 6, $offset = 0)
    {
        $categoryQuery = "";
        if (!empty($categoryIds)) {
            $categoryQuery .= "".$this->wpdb_prefix."term_relationships.term_taxonomy_id IN (".implode(',', array_map('intval', $categoryIds)).")";
        }
        $tagQuery = "";
        if (!empty($tagIds)) {
            $tagQuery .= "tt1.term_taxonomy_id IN (".implode(',', array_map('intval', $tagIds)).")";
        }
        $exQuery = "";
        if (!empty($tagQuery) && !empty($categoryQuery)) {
            $exQuery = "({$categoryQuery} OR {$tagQuery}) AND";
        } else if (empty($tagQuery) && !empty($categoryQuery)) {
            $exQuery = "({$categoryQuery}) AND";
        } else if (!empty($tagQuery) && empty($categoryQuery)) {
            $exQuery = "({$tagQuery}) AND";
        }

        $query        = "SELECT ".$this->wpdb_prefix."posts.ID AS productId, ".$this->wpdb_prefix."posts.post_title AS name FROM ".$this->wpdb_prefix."posts
		INNER JOIN ".$this->wpdb_prefix."term_relationships ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."term_relationships.object_id
		INNER JOIN ".$this->wpdb_prefix."term_relationships AS tt1 ON ".$this->wpdb_prefix."posts.ID = tt1.object_id
		INNER JOIN ".$this->wpdb_prefix."postmeta ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."postmeta.post_id
		WHERE ".$exQuery." ".$this->wpdb_prefix."posts.post_type = 'product'
		AND ".$this->wpdb_prefix."posts.post_status = 'publish'
		AND ".$this->wpdb_prefix."postmeta.meta_key = '_visibility'
		AND CAST(".$this->wpdb_prefix."postmeta.meta_value AS CHAR) IN ('visible','catalog')
		GROUP BY ".$this->wpdb_prefix."posts.ID ORDER BY ".$this->wpdb_prefix."posts.ID DESC LIMIT {$offset}, {$limit}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get related products by product id(s)
     * @param int[] $productIds
     * @param int $limit
     * @param int $offset
     * @return \stdClass[] [productId, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getRelatedProductsByProductIds(array $productIds, $limit = 6, $offset = 0)
    {
        $query        = "SELECT ".$this->wpdb_prefix."posts.ID AS productId, ".$this->wpdb_prefix."posts.post_title AS name FROM ".$this->wpdb_prefix."posts
		INNER JOIN ".$this->wpdb_prefix."term_relationships ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."term_relationships.object_id
		INNER JOIN ".$this->wpdb_prefix."term_relationships AS tt1 ON ".$this->wpdb_prefix."posts.ID = tt1.object_id
		INNER JOIN ".$this->wpdb_prefix."postmeta ON ".$this->wpdb_prefix."posts.ID = ".$this->wpdb_prefix."postmeta.post_id
		WHERE (
			".$this->wpdb_prefix."term_relationships.term_taxonomy_id IN (
				SELECT t.term_id FROM ".$this->wpdb_prefix."terms AS t
				INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON tt.term_id = t.term_id
				INNER JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy IN ('product_cat') AND tr.object_id IN
                                (".implode(',', array_map('intval', $productIds)).") ORDER BY t.term_id ASC
			) OR tt1.term_taxonomy_id IN (
				SELECT t.term_id FROM ".$this->wpdb_prefix."terms AS t
				INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON tt.term_id = t.term_id
                                INNER JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                WHERE tt.taxonomy IN ('product_tag') AND tr.object_id IN
                                (".implode(',', array_map('intval', $productIds)).") ORDER BY t.term_id ASC
			)
		)
		AND ".$this->wpdb_prefix."posts.post_type = 'product'
		AND ".$this->wpdb_prefix."posts.post_status = 'publish'
		AND ".$this->wpdb_prefix."postmeta.meta_key = '_visibility'
		AND CAST(".$this->wpdb_prefix."postmeta.meta_value AS CHAR) IN ('visible','catalog')
		GROUP BY ".$this->wpdb_prefix."posts.ID ORDER BY ".$this->wpdb_prefix."posts.ID DESC LIMIT {$offset}, {$limit}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get all categories for products by product id.
     * @param int[] $productIds
     * @return \stdClass[] [term_id, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getProductCategories(array $productIds)
    {
        $query        = "SELECT t.*, tt.* FROM ".$this->wpdb_prefix."terms AS t
            INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON tt.term_id = t.term_id
            INNER JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy IN ('product_cat') AND tr.object_id IN (".implode(',', array_map('intval', $productIds)).") ORDER BY t.name ASC";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get all tags for products by product id.
     * @param int[] $productIds
     * @return \stdClass[] [term_id, name]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getProductTags(array $productIds)
    {
        $query        = "SELECT t.term_id, t.name FROM ".$this->wpdb_prefix."terms AS t
          INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON tt.term_id = t.term_id INNER
          JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
          WHERE tt.taxonomy IN ('product_tag') AND tr.object_id IN (".implode(',', array_map('intval', $productIds)).") ORDER BY t.name ASC";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get meta data for $postId
     * @param int $postId
     * @return \stdClass[] [meta_key, meta_value]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getMetaData($postId)
    {
        $query        = "SELECT meta_key, meta_value FROM ".$this->wpdb_prefix."postmeta WHERE post_id={$postId}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get product list from simple db query
     * @param int $offset
     * @param int $limit
     * @param array $type
     * @return \stdClass[]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getSimpleProductList($offset, $limit, $type = array('product'))
    {
        $query        = "SELECT
            IFNULL(post_content, post_excerpt) AS description,
            post_title AS name, ID AS productId,
            post_parent AS parentId FROM ".$this->wpdb_prefix."posts
            WHERE post_type IN ('".implode("','", $type)."')
            AND post_status = 'publish' ORDER BY ID ASC
            LIMIT {$offset}, {$limit}";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * get Categories, Tags and ShippingClasses data [term_id, name, parent, taxonomy]
     * @param int $productId
     * @return \stdClass[]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getCategoriesTagsAndShippingClasses($productId)
    {
        /* ShippingClasses: is added for future use as it is here it will be fetched when finding the best way to use it. */
        if (in_array('termmeta', $this->wpdb->tables)) {
            $query = "SELECT t.term_id, t.name, tt.parent, tt.taxonomy FROM ".$this->wpdb_prefix."terms AS t
                       INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON t.term_id = tt.term_id
                       INNER JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                       LEFT JOIN ".$this->wpdb_prefix."termmeta AS tm ON t.term_id = tm.term_id AND tm.meta_key = 'order'
                       WHERE tt.taxonomy IN ('product_cat', 'product_tag', 'product_shipping_class') AND tr.object_id = {$productId}";
        } else {
            $query = "SELECT t.term_id, t.name, tt.parent, tt.taxonomy FROM ".$this->wpdb_prefix."terms AS t
                       INNER JOIN ".$this->wpdb_prefix."term_taxonomy AS tt ON t.term_id = tt.term_id
                       INNER JOIN ".$this->wpdb_prefix."term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                       LEFT JOIN ".$this->wpdb_prefix."woocommerce_termmeta AS tm ON t.term_id = tm.woocommerce_term_id AND tm.meta_key = 'order'
                       WHERE tt.taxonomy IN ('product_cat', 'product_tag', 'product_shipping_class') AND tr.object_id = {$productId}";
        }
        $query_result = $this->wpdb->get_results($query, OBJECT);
        return $query_result ? $query_result : array();
    }

    /**
     * Get currently active sale price for a product.
     * @param int $productId
     * @return string|null
     */
    protected function getSalePrice($productId)
    {
        $query        = "SELECT t1.post_id, t1.meta_value as sale_price, t2.meta_value as sale_from, t3.meta_value as sale_to
                       FROM ".$this->wpdb_prefix."postmeta AS t1 INNER JOIN ".$this->wpdb_prefix."postmeta AS t2 ON t2.post_id=t1.post_id AND
                       t2.meta_key='_sale_price_dates_from' INNER JOIN ".$this->wpdb_prefix."postmeta AS t3 ON t3.post_id=t1.post_id
                       AND t3.meta_key='_sale_price_dates_to' WHERE t1.post_id = {$productId} AND t1.meta_key='_sale_price'";
        $query_result = $this->wpdb->get_results($query, OBJECT);
        if ($query_result) {
            $query_result    = $query_result[0];
            $saleFrom        = (((int) $query_result->sale_from == 0) || ((int) $query_result->sale_from != 0 && (int) $query_result->sale_from <= time()));
            $saleTo          = ((int) $query_result->sale_to != 0 && (int) $query_result->sale_to >= time());
            $inSaleTimeFrame = (($saleFrom && $saleTo) || ($saleFrom && !$saleTo && $query_result->sale_to == 0));
            return $inSaleTimeFrame ? $query_result->sale_price : null;
        }
        return null;
    }

    /**
     * get images meta data [post_id, file, meta, image_alt]
     * @param int[] $imagesIds
     * @return \stdClass[]
     * @author Christian M. Jensen <christian@modified.dk>
     * @since 1.1.2
     */
    protected function getImagesData($imagesIds)
    {
        $query  = "SELECT t1.post_id, t1.meta_value as file, t2.meta_value as meta, t3.post_title as image_alt
                       FROM ".$this->wpdb_prefix."postmeta AS t1
                       INNER JOIN ".$this->wpdb_prefix."postmeta AS t2
                       ON t2.post_id=t1.post_id
                       AND t2.meta_key='_wp_attachment_metadata'
                       LEFT JOIN ".$this->wpdb_prefix."posts AS t3
                       ON t3.ID=t1.post_id AND t3.post_type='attachment'
                       WHERE t1.post_id IN (".implode(',', array_map('intval', $imagesIds)).")
                       AND t1.meta_key='_wp_attached_file'";
        $result = $this->wpdb->get_results($query, OBJECT);
        return $result ? $result : array();
    }

    /**
     * Get WooCommerce instance
     * @global array $GLOBALS
     * @return \WooCommerce|\Woocommerce
     */
    public function getWooCommerceInstance()
    {
        global $GLOBALS;
        return $GLOBALS['woocommerce'];
    }
}
<?php

/**
 * Image grouping engine.
 *
 * Groups uploaded images by filename base pattern so related images
 * become a single product with a gallery.
 *
 * @package WooCommerce_Uploady
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Uploady_Grouping_Engine
 *
 * @since 1.0.0
 */
class WC_Uploady_Grouping_Engine
{

    /**
     * Group attachment IDs by filename base.
     *
     * Files like shoe-1.jpg, shoe-2.jpg → group "shoe" with both IDs.
     * Files like hat.jpg → standalone group "hat".
     *
     * @param array $attachment_ids Array of WordPress attachment IDs.
     * @return array Grouped results: [ [ 'title' => string, 'featured' => int, 'gallery' => int[] ], ... ]
     */
    public function group(array $attachment_ids)
    {
        $buckets = array();

        foreach ($attachment_ids as $attachment_id) {
            $filename  = get_attached_file($attachment_id);
            $basename  = pathinfo($filename, PATHINFO_FILENAME);
            $base_name = $this->extract_base_name($basename);

            if (! isset($buckets[$base_name])) {
                $buckets[$base_name] = array();
            }

            $buckets[$base_name][] = $attachment_id;
        }

        /**
         * Filter the grouped image buckets before product creation.
         *
         * Allows Pro or third-party add-ons to modify grouping logic.
         *
         * @since 1.0.0
         * @param array $buckets        Associative array of base_name => attachment IDs.
         * @param array $attachment_ids Original flat list of attachment IDs.
         */
        $buckets = apply_filters('wc_uploady_group_images', $buckets, $attachment_ids);

        $groups = array();

        foreach ($buckets as $base_name => $ids) {
            $title    = $this->humanize_title($base_name);
            $featured = $ids[0];
            $gallery  = array_slice($ids, 1);

            $groups[] = array(
                'title'    => $title,
                'featured' => $featured,
                'gallery'  => $gallery,
            );
        }

        return $groups;
    }

    /**
     * Extract the base name from a filename, stripping trailing numeric suffixes.
     *
     * Examples:
     *   shoe-1    → shoe
     *   shoe-2    → shoe
     *   shoe_03   → shoe
     *   hat       → hat
     *   red-bag   → red-bag (not purely numeric suffix)
     *
     * @param string $basename Filename without extension.
     * @return string Base name.
     */
    private function extract_base_name($basename)
    {
        // Match trailing separator + digits: shoe-1, shoe_02, product-03.
        $base = preg_replace('/[\-_]\d+$/', '', $basename);
        return $base;
    }

    /**
     * Convert a filename base into a human-readable product title.
     *
     * Replaces hyphens/underscores with spaces and capitalizes words.
     *
     * @param string $base_name Filename base.
     * @return string Humanized title.
     */
    private function humanize_title($base_name)
    {
        $title = str_replace(array('-', '_'), ' ', $base_name);
        return ucwords($title);
    }
}

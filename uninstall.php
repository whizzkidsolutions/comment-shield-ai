<?php
/**
 * Uninstall script for Comment Shield AI – Perspective Spam Guard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin settings.
delete_option( 'pcg_settings' );

// Remove toxicity scores from comment meta.
global $wpdb;
$meta_key = '_pcg_toxicity_score';

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->commentmeta} WHERE meta_key = %s",
        $meta_key
    )
);

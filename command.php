<?php
/**
 * Plugin Name: GOOD ONE
 * Plugin URI:
 * Description: A tool that takes old postmeta data (older than 90 days past event) and archives
 * that data into the order_archive table
 * Version: 1.0.0
 * Author: Sara Pearce
 * Author URI: http://sarapearce.net
 * License: GPL2

 * @package eCommerce Order Archive Tool
 */

if (! class_exists('ArchiveOrdersClass')) {
    /**
     * This class builds an Intercom object that allows us to CRUD on the Intercom server,
     * then it removes duplicate leads on the Intercom remote server.
     */
    class ArchiveOrdersClass
    {

        /**
         * Check for our archive tables, and create them if missing.
         * Set wpdb for the class
         * Call archive_orders function which will do the cleanup process.
         */
        public function __construct()
        {
            // set the wordpress db object globally for the class
            global $wpdb;

						// cleanup after testing
            echo 'HERE IN THE CONSTRUCTOR';

            // Check for our tables, if they are not present, create them
            $this->check_for_tables();

            // Clean up orders from last year and put into archive table
            $this->archive_orders();
        }


        /**
         * Checks for the existance of the wp_kzrfjd5mm6_tribe_order_archive_posts and
         * wp_kzrfjd5mm6_tribe_order_archive_postmeta table for archiving. If table is missing,
         * we create the table.
         */
        public function check_for_tables()
        {

            //Check for existance of the wp_kzrfjd5mm6_tribe_order_archive_posts table
            $result = $wpdb->query("
						SELECT COUNT(*) from wp_kzrfjd5mm6_tribe_order_archive_posts");
            if ($result == 0) {
                $query = $wpdb->query("
								CREATE TABLE `wp_kzrfjd5mm6_tribe_order_archive_posts` (
									`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
									`post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
									`post_date` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
									`post_date_gmt` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
									`post_content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
									`post_title` text COLLATE utf8mb4_unicode_ci NOT NULL,
									`post_excerpt` text COLLATE utf8mb4_unicode_ci NOT NULL,
									`post_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publish',
									`comment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
									`ping_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
									`post_password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
									`post_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
									`to_ping` text COLLATE utf8mb4_unicode_ci NOT NULL,
									`pinged` text COLLATE utf8mb4_unicode_ci NOT NULL,
									`post_modified` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
									`post_modified_gmt` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
									`post_content_filtered` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
									`post_parent` bigint(20) unsigned NOT NULL,
									`guid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
									`menu_order` int(11) NOT NULL DEFAULT '0',
									`post_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post',
									`post_mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
									`comment_count` bigint(20) NOT NULL DEFAULT '0',
									PRIMARY KEY (`ID`),
									KEY `post_name` (`post_name`(191)),
									KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
									KEY `post_parent` (`post_parent`),
									KEY `post_author` (`post_author`)
								) ENGINE=InnoDB AUTO_INCREMENT=136265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
								");
            }


            //Check for existance of the wp_kzrfjd5mm6_tribe_order_archive_posts table
            $result = $wpdb->query("SELECT COUNT(*) from wp_kzrfjd5mm6_tribe_order_archive_postmeta");
            if ($result == 0) {
                $query = $wpdb->query("
								CREATE TABLE `wp_kzrfjd5mm6_tribe_order_archive_postmeta` (
								  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
								  `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
								  `meta_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
								  `meta_value` longtext COLLATE utf8mb4_unicode_ci,
								  PRIMARY KEY (`meta_id`),
								  KEY `post_id` (`post_id`),
								  KEY `meta_key` (`meta_key`(191))
								) ENGINE=InnoDB AUTO_INCREMENT=2970643 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
								");
            }
        }

        /**
         *
         *
         *
         */
        public function archive_orders()
        {
						$this->duplicate_rows();
						$this->delete_rows();
            $this->send_email_confirmation();
        }

				/**
         *
         *
         *
         */

        public function duplicate_rows()
        {
            // Copy posts to archive table
            try {
                $duplicate_postmeta = $wpdb->query("
								INSERT INTO wp_kzrfjd5mm6_tribe_order_archive_postmeta
								SELECT
				 				*
								FROM
								wp_kzrfjd5mm6_postmeta
								WHERE
								post_id IN (
								SELECT
								id
								FROM
								wp_kzrfjd5mm6_posts
								WHERE
								post_type LIKE '%tribe%'
								AND post_date < DATE_ADD(NOW(), INTERVAL -13 MONTH)
								)");
            } catch (Exception $e) {
                error_log($e->getMessage());
            }

            // Copy tribe posts (event ticket orders) to archive table
            try {
                $duplicate_posts = $wpdb->query("
								INSERT INTO
								wp_kzrfjd5mm6_tribe_order_archive_posts
								SELECT
								*
								FROM
								wp_kzrfjd5mm6_posts
								WHERE
								post_type LIKE '%tribe%'
								AND post_date < DATE_ADD(NOW(), INTERVAL -13 MONTH)
								)");
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

				/**
         *
         *
         *
         */
        public function delete_rows()
        {
					// Delete posts added to archive table
					try {
							$delete_postmeta = $wpdb->query("
							DELETE
							FROM
							wp_kzrfjd5mm6_postmeta
							WHERE
							post_id IN (
							SELECT
							id
							FROM
							wp_kzrfjd5mm6_posts
							WHERE
							post_type LIKE '%tribe%'
							AND post_date < DATE_ADD(NOW(), INTERVAL -13 MONTH)
							)");
					} catch (Exception $e) {
							error_log($e->getMessage());
					}

					// Delete tribe posts (event ticket orders) to archive table
					try {
							$delete_postmeta = $wpdb->query("
							INSERT INTO
							wp_kzrfjd5mm6_tribe_order_archive_posts
							SELECT
							*
							FROM
							wp_kzrfjd5mm6_posts
							WHERE
							post_type LIKE '%tribe%'
							AND post_date < DATE_ADD(NOW(), INTERVAL -13 MONTH)
							)");
					} catch (Exception $e) {
							error_log($e->getMessage());
					}
        }

				/**
         *
         *
         *
         */
        public function send_email_confirmation()
        {
					echo 'gonna send an email someday';
        }
    }

    /**
        * Add the function to the command object
        */
    if (class_exists('WP_CLI')) {
        WP_CLI::add_command('archive-orders', 'ArchiveOrdersClass');
    }
}

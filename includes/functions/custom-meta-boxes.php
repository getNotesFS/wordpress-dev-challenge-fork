<?php

if (!defined('ABSPATH')) {
	die('Direct access not permitted.');
}

if (!class_exists('Meta_Boxes')) :
	class Meta_Boxes
	{


		function __construct()
		{
			$this->setup_hooks();
		}

		/**
		 * Setup Hooks and add custom meta boxes.
		 *
		 * @return void
		 */
		function setup_hooks()
		{
			/**
			 * Actions.
			 *
			 */
			add_action('add_meta_boxes', [$this, 'add_post_citation_meta_box']);

			add_action('save_post', [$this, 'post_citation_meta_box_save']);
		}

		/**
		 * Set up and add the citation meta box.
		 */
		public static function add_post_citation_meta_box()
		{
			$screens = ['post'];
			foreach ($screens as $screen) {
				add_meta_box(
					'_post_citation_key', // Unique ID
					'Citation', // Box title
					[self::class, 'post_citation_key_meta_box_html'],   // Content callback, must be of type callable
					$screen  // Post type
				);
			}
		}


		public function post_citation_key_meta_box_html($post)
		{
			// Get saved meta data
			$post_citation_content = get_post_meta($post->ID, '_post_citation_key', TRUE);
			if (!$post_citation_content) $post_citation_content = '';
			wp_nonce_field('citation_content' . $post->ID, 'post_citation_nonce');
			// Render editor meta box
			wp_editor($post_citation_content, 'citation_content', array('textarea_rows' => '5'));
		}


		/**
		 * Save post citation content.
		 *
		 * @param [type] $post_id
		 * @return void
		 */
		public function post_citation_meta_box_save($post_id)
		{
			// Bail if we're doing an auto save
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

			// if our nonce isn't there, or we can't verify it, bail
			if (!isset($_POST['post_citation_nonce']) || !wp_verify_nonce($_POST['post_citation_nonce'], 'citation_content' . $post_id)) return;

			// if our current user can't edit this post, bail
			if (!current_user_can('edit_post')) return;


			// Make sure our data is set before trying to save it
			if (isset($_POST['citation_content']))
				$result = update_post_meta($post_id, '_post_citation_key', $_POST['citation_content']);
		}
	}

	$meta_box = new Meta_Boxes();

endif;

<?php 
/*
Plugin Name: CF Tags in Search
Plugin URI: 
Description: Alters the content of a post (just in the DB, not on display) by adding the post's tags so search can grab those posts.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

/* The code uses "pcf" in place of "post_content_filtered" in several places */

class CF_Tax_In_Search {
	
	static $instance;
	protected $taxonomies = array();

	private function __construct() {}

	public function factory() {
		if (!isset(self::$instance)) {
			$class = get_class($this);
			self::$instance = new $class;
		}
		return self::$instance;
	}
	
	public function add_taxonomy($taxonomy) {
		$this->taxonomies[] = $taxonomy;
	}
	
	public function add_actions() {
		add_filter(
			'wp_insert_post_data', 
			array($this, 'fill_pcf'),
			10, 2
		);
		
		add_filter(
			'posts_search',
			array($this, 'add_pcf_field_to_search'),
			10, 2
		);
	}

	/**
	 * Populate the post_content_filtered post args immediately prior to DB insert.
	 *
	 * @param array $new_data SLASHED DATA
	 * @param array $orig_post_arr data that's been passed to wp_insert_post (it's been sanitized, but not filtered)
	 * @return array - SLASHED new post data
	 */
	public function fill_pcf($new_data, $orig_post_arr) {
		if ($new_data['post_type'] != 'post') { return $new_data; }
		
		$new_output = '';
		foreach ($this->taxonomies as $taxonomy) {
			if ($taxonomy == 'category' && isset($orig_post_arr['post_category']) && is_array($orig_post_arr['post_category'])) {
				$new_output .= $this->get_string_for_term_array($orig_post_arr['post_category'], 'category');					
			}
			// Not an elseif just in case category changes
			if (
				isset($orig_post_arr['tax_input']) 
				&& is_array($orig_post_arr['tax_input']) 
				&& isset($orig_post_arr['tax_input'][$taxonomy])
			) {
				$terms = $orig_post_arr['tax_input'][$taxonomy];
				$new_output .= is_array($terms) ? $this->get_string_for_term_array($terms, $taxonomy) : $terms.',';
			}
		}

		$new_data['post_content_filtered'] = addslashes($new_output);
		return $new_data;
	}
	
	private function get_string_for_term_array($term_ids, $taxonomy) {
		$out = '';
		if (is_array($term_ids)) {
			foreach ($term_ids as $term_id) {
				$term_obj = get_term_by('id', (int)$term_id, $taxonomy);
				if ($term_obj) {
					$out .= $term_obj->name.',';
				}
			}
		}
		
		return $out;
	}
	
	public function add_pcf_field_to_search($search_string, $query) {
		if (!empty($query)
			&& is_a($query, 'WP_Query')
			&& $query->is_search()) {
			global $wpdb;

			foreach( (array) $query->query_vars['search_terms'] as $term ) {
				$term = esc_sql( like_escape( $term ) );
				$term_search_string= " OR ($wpdb->posts.post_content_filtered LIKE '%{$term}%')";
				$find = "post_title LIKE '%{$term}%')";
				$replace = $find.$term_search_string;
				$search_string = str_replace(
					$find, 
					$replace,
					$search_string
				);
			}
		}
		return $search_string;
	}
}

CF_Tax_In_Search::factory()->add_actions();
<?php

/*
  Plugin Name: Multisite Language Switcher Comments by Eyga.net
  Plugin URI: http://wordpress.org/plugins/msls-comments/
  Description: All comments posted on translation-joined pages are displayed on all translation-joined posts. Visit the plugin site for installation instructions.
  Version: 0.8.2
  Author: DSmidgy
  Author URI: http://blog.slo-host.com/
 */

// Options are stored in database, see intallation instructions
$msls_comments = array();

// Only container for static functions
class MslsComments {

	// Output for debugging
	static function debug($name, $value) {
		// Use: MslsComments::debug('$name', $name);
		print "<pre><b>$name</b><br>";
		print_r($value);
		print "</pre>";
	}

	// Store/retrieve options in/from WordPress database
	static function getOptions() {
		// Default option values
		$default_options = array(
			// -1 : Store comment on originating blog; >= 1 : Store comments in primary blog (number represents blog_id)
			'primary_blog_id' => -1,
			'comments_sort' => 'desc',
			'comments_per_blog' => 100000000,
			'common_upload_dir' => false,
		);
		// Get options from wp_siteoptions table
		$options = get_site_option('msls_comments');
		// If options aren't found in database
		if ($options == false) {
			// If data isn't present, do add
			add_site_option('msls_comments', $default_options);
			$options = $default_options;
		} else {
			// If some keys are missing, do update
			$update_options_execute = false;
			$update_options = array();
			foreach ($default_options as $def_opt_key => $def_opt_value) {
				if (array_key_exists($def_opt_key, $options) == true) {
					$update_options[$def_opt_key] = $options[$def_opt_key];
				} else {
					$update_options_execute = true;
					$update_options[$def_opt_key] = $def_opt_value;
				}
			}
			if ($update_options_execute == true) {
				update_site_option('msls_comments', $update_options);
				$options = $update_options;
			}
		}
		// Return
		#MslsComments::debug('$options', $options);
		return $options;
	}

	// Get all comments from this and translated posts
	static function getAllComments($current_post_id, $type) {
		// Were results already calculated?
		global $msls_comments;
		$comments_array = null;
		if (array_key_exists('array', $msls_comments) == true) {
			$comments_array = $msls_comments['array'];
		}
		$comments_count = null;
		if (array_key_exists('count', $msls_comments) == true) {
			$comments_count = $msls_comments['count'];
		}
		$comments_update = "";
		if (array_key_exists('update', $msls_comments) == true) {
			$comments_update = $msls_comments['update'];
		}
		// Refresh the empty comments list and counter
		if ($comments_array == null || $comments_count == null) {
			// Get options (set by user)
			$options = MslsComments::getOptions();
			$comments_per_blog = $options['comments_per_blog'];
			$primary_blog_id = $options['primary_blog_id'];
			$current_blog_id = get_current_blog_id();
			// Get comments and data from joined posts
			$comments_array = array();
			$joined_comments = MslsComments::getJoinedComments($primary_blog_id, $current_blog_id, $current_post_id, $comments_per_blog);
			$primary_post_id = $joined_comments['primary_post_id'];
			$comments_array = MslsComments::mergeComments($comments_array, $joined_comments['array']);
			$comments_update = $joined_comments['update'];
			// Is primary blog relevant?
			if ($primary_blog_id != -1 && $primary_blog_id != $current_blog_id && $primary_post_id == null) {
				$primary_blog_id = -1;
			}
			// Get comments form this posts
			$current_comments = MslsComments::expandComments(get_comments(array('post_id' => $current_post_id, 'status' => 'approve')), $primary_blog_id, $current_blog_id, $current_blog_id, $comments_per_blog);
			$comments_array = MslsComments::mergeComments($comments_array, $current_comments);
			$comments_count = count($comments_array);
			$comments_update .= MslsComments::mergeUpdates($comments_update, $current_post_id);
			// Sort comments
			if ($options['comments_sort'] == 'asc') {
				usort($comments_array, array('MslsComments', 'sortCommentsAsc'));
			} else {
				usort($comments_array, array('MslsComments', 'sortCommentsDesc'));
			}
			// Globals
			$msls_comments['primary_blog_id'] = $primary_blog_id;
			$msls_comments['primary_post_id'] = $primary_post_id;
			$msls_comments['current_blog_id'] = $current_blog_id;
			$msls_comments['current_post_id'] = $current_post_id;
			$msls_comments['current_post_link'] = get_permalink($current_post_id);
			$msls_comments['comments_per_blog'] = $comments_per_blog;
			$msls_comments['array'] = $comments_array;
			$msls_comments['count'] = $comments_count;
			$msls_comments['update'] = $comments_update;
		}
		// Return
		if ($type == 'array') {
			return $comments_array;
		}
		if ($type == 'count') {
			return $comments_count;
		}
		if ($type == 'update') {
			return $comments_update;
		}
		return null;
	}

	// Comments on joined/translated posts
	static function getJoinedComments($primary_blog_id, $current_blog_id, $current_post_id, $comments_per_blog) {
		// Return these variables
		$comments_array = array();
		$comments_update = "";
		$primary_post_id = null;
		$msls_post_links = get_blog_option($current_blog_id, "msls_$current_post_id");
		// Get comments and data from joined posts
		$sites = get_sites();
		foreach ($sites as $site) {
			$linked_blog_id = $site->blog_id;
			if ($linked_blog_id == $current_blog_id) {
				continue;
			}
			$linked_blog_language = get_blog_option($linked_blog_id, 'WPLANG');
			if (is_array($msls_post_links) && array_key_exists($linked_blog_language, $msls_post_links)) {
				$linked_post_id = $msls_post_links[$linked_blog_language];
			} else {
				continue;
			}
			if ($linked_blog_id == $primary_blog_id) {
				$primary_post_id = $linked_post_id;
			}
			switch_to_blog($linked_blog_id);
			$linked_comments = MslsComments::expandComments(get_comments(array('post_id' => $linked_post_id, 'status' => 'approve')), $primary_blog_id, $current_blog_id, $linked_blog_id, $comments_per_blog);
			$comments_array = MslsComments::mergeComments($comments_array, $linked_comments);
			$comments_update .= MslsComments::mergeUpdates($comments_update, $linked_post_id);
			restore_current_blog();
		}
		// Return
		return array(
			"primary_post_id" => $primary_post_id,
			"array" => $comments_array,
			"update" => $comments_update,
		);
	}

	// Add blog_id to comment object array element
	static function expandComments($comments, $primary_blog_id, $current_blog_id, $blog_id, $comments_per_blog) {
		// Add/correct comment properties
		for ($i = 0, $ic = count($comments); $i < $ic; $i++) {
			$comments[$i]->comment_blog_id = $blog_id;
			// Primary blog enabled: comments on non-primary blogs gets corrected
			// Primary blog disabled: comments on non-current blogs gets corrected
			if ($primary_blog_id != -1 && $primary_blog_id != $blog_id || $primary_blog_id == -1 && $current_blog_id != $blog_id) {
				$comments[$i]->comment_ID += $blog_id * $comments_per_blog;
				if ($comments[$i]->comment_parent > 0) {
					$comments[$i]->comment_parent += $blog_id * $comments_per_blog;
				}
			}
		}
		return $comments;
	}

	// Merge comments into existing array
	static function mergeComments($comments_array, $comments) {
		if (count($comments_array) == 0) {
			$comments_array = $comments;
		} else {
			// Could be slow with large arrays
			$comments_array = array_merge($comments_array, $comments);
		}
		return $comments_array;
	}

	// Merge update strings
	static function mergeUpdates($comments_update, $linked_post_id) {
		global $wpdb;
		$update = "";
		if (strlen($comments_update) > 0) {
			$update = "|";
		}
		$update .= "$wpdb->posts,$linked_post_id";
		return $update;
	}

	// Sort comments
	static function sortCommentsAsc($a, $b) {
		return MslsComments::sortComments($a, $b, 'asc');
	}

	static function sortCommentsDesc($a, $b) {
		return MslsComments::sortComments($a, $b, 'desc');
	}

	static function sortComments($a, $b, $sort_order) {
		$time_a = strtotime($a->comment_date);
		$time_b = strtotime($b->comment_date);
		if ($sort_order == 'asc') {
			if ($time_a > $time_b) {
				return 1;
			} else {
				return -1;
			}
		} else {
			if ($time_a > $time_b) {
				return -1;
			} else {
				return 1;
			}
		}
	}

	// Functions for comment form
	static function switchBlog() {
		MslsComments::getAllComments(get_the_ID(), null);
		global $msls_comments;
		if ($msls_comments['primary_blog_id'] != -1) {
			switch_to_blog($msls_comments['primary_blog_id']);
		}
	}

	static function restoreBlog() {
		global $msls_comments;
		if ($msls_comments['primary_blog_id'] != -1) {
			restore_current_blog();
		}
	}

	static function postId() {
		global $msls_comments;
		if ($msls_comments['primary_blog_id'] != -1) {
			return $msls_comments['primary_post_id'];
		} else {
			return get_the_ID();
		}
	}

	// Get array of comments form this and translated posts
	static function commentsArray($comments, $post_id) {
		return MslsComments::getAllComments($post_id, 'array');
	}

	// Get number of comments from this and translated posts
	static function commentsCount($count, $post_id) {
		if (is_single()) {
			return MslsComments::getAllComments($post_id, 'count');
		} else {
			return $count;
		}
	}

	// Redirect to original blog and post, after comment is stored into primary blog (depends on primaryBlog settings)
	static function commentFormRedirect($post_id) {
		global $msls_comments;
		if ($msls_comments['primary_blog_id'] != -1) {
			echo '<input type="hidden" name="redirect_to" value="' . $msls_comments['current_post_link'] . '">' . "\n\t\t\t\t\t\t";
			echo '<input type="hidden" name="comments_count_update" value="' . $msls_comments['count'] . "#" . $msls_comments['update'] . '">' . "\n";
		}
	}

	// Hide "reply" links on comments form non-primary/non-current blogs (depends if primary blog is enabled)
	static function commentReplyLink($link, $args, $comment, $post) {
		global $msls_comments;
		if ($msls_comments['primary_blog_id'] != -1 && $comment->comment_blog_id != $msls_comments['primary_blog_id'] || $msls_comments['primary_blog_id'] == -1 && $comment->comment_blog_id != $msls_comments['current_blog_id']) {
			return "";
		} else {
			return $link;
		}
	}

	// Hide "edit" links on comments from non-current blogs
	static function commentEditLink($link, $comment_id) {
		global $msls_comments;
		$comment_blog_id = floor($comment_id / $msls_comments['comments_per_blog']);
		if ($comment_blog_id == 0) {
			if ($msls_comments['primary_blog_id'] != -1) {
				$comment_blog_id = $msls_comments['primary_blog_id'];
			} else {
				$comment_blog_id = $msls_comments['current_blog_id'];
			}
		}
		if ($comment_blog_id != $msls_comments['current_blog_id']) {
			return "";
		} else {
			if ($msls_comments['primary_blog_id'] != -1 && $comment_blog_id == $msls_comments['primary_blog_id'] || $msls_comments['primary_blog_id'] == -1) {
				return $link;
			} else {
				return preg_replace('/c=' . $comment_blog_id . '0*/', 'c=', $link);
			}
		}
	}

	// Corrects comment count on all joined posts; called from primary blog
	static function commentPost($comment_id, $comment_status) {
		// TODO: Read blog_id and post_id from POST, get actual number of comments with getAllComments, write this result to all tables
		// TODO: Update should be done on deleting comments or changing it's status
		global $wpdb;
		// Get data form post
		$comments_count_update = filter_input(INPUT_POST, 'comments_count_update');
		list($comments_count, $comments_update) = explode('#', $comments_count_update);
		if ($comment_status == 1) {
			$comments_count++;
		}
		$table_and_post = explode('|', $comments_update);
		// Update post comment counters
		for ($i = 0, $ic = count($table_and_post); $i < $ic; $i++) {
			list($table_name, $post_id) = explode(',', $table_and_post[$i]);
			#error_log("table={$table_name}, id={$post_id}, cnt=".$comments_count);
			if (strlen($table_name) > 0 && strlen($post_id) > 0) {
				$wpdb->update(
						$table_name, array('comment_count' => $comments_count), array('ID' => $post_id), array('%d'), array('%d')
				);
			}
		}
	}

	// Use common upload directory
	static function uploadDir($param) {
		$options = MslsComments::getOptions();
		if ($options['common_upload_dir'] == true) {
			$blog_id = get_current_blog_id();
			$blog_path = get_blog_details($blog_id)->path;
			$param['path'] = MslsComments::uploadDirReplace($param['path'], $blog_id, $blog_path);
			$param['url'] = MslsComments::uploadDirReplace($param['url'], $blog_id, $blog_path);
			$param['basedir'] = MslsComments::uploadDirReplace($param['basedir'], $blog_id, $blog_path);
			$param['baseurl'] = MslsComments::uploadDirReplace($param['baseurl'], $blog_id, $blog_path);
			#error_log("path={$param['path']}, url={$param['url']}, subdir={$param['subdir']}, basedir={$param['basedir']}, baseurl={$param['baseurl']}");
		}
		return $param;
	}

	static function uploadDirReplace($string, $blog_id, $blog_path) {
		return str_replace("$blog_path", "/", str_replace("/sites/$blog_id", "", $string));
	}

}

add_filter('comments_array', array('MslsComments', 'commentsArray'), 10, 2);
add_filter('get_comments_number', array('MslsComments', 'commentsCount'), 10, 2);
add_action('comment_form', array('MslsComments', 'commentFormRedirect'), 10, 1);
add_filter('comment_reply_link', array('MslsComments', 'commentReplyLink'), 10, 4);
add_filter('edit_comment_link', array('MslsComments', 'commentEditLink'), 10, 2);
add_filter('comment_post', array('MslsComments', 'commentPost'), 10, 2);
add_filter('upload_dir', array('MslsComments', 'uploadDir'), 10, 1);

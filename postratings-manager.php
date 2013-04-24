<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress Plugin: WP-PostRatings								|
|	Copyright (c) 2012 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Manage Post Ratings Logs													|
|	- wp-content/plugins/wp-postratings/postratings-manager.php		|
|																							|
+----------------------------------------------------------------+
*/


### Check Whether User Can Manage Ratings
if(!current_user_can('manage_ratings')) {
	die('Access Denied');
}


### Ratings Variables
$base_name = plugin_basename('wp-postratings/postratings-manager.php');
$base_page = 'admin.php?page='.$base_name;
$mode = trim($_GET['mode']);
$postratings_page = intval($_GET['ratingpage']);
$postratings_filterid = trim(addslashes($_GET['id']));
$postratings_filteruser = trim(addslashes($_GET['user']));
$postratings_filterrating = trim(addslashes($_GET['rating']));
$postratings_sortby = trim($_GET['by']);
$postratings_sortby_text = '';
$postratings_sortorder = trim($_GET['order']);
$postratings_sortorder_text = '';
$postratings_log_perpage = intval($_GET['perpage']);
$postratings_sort_url = '';
$ratings_image = get_option('postratings_image');
$ratings_max = intval(get_option('postratings_max'));


### Form Processing 
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		case __('Delete Data/Logs', 'wp-postratings'):
			check_admin_referer('wp-postratings_logs');
			$post_ids = trim($_POST['delete_postid']);
			$delete_datalog = intval($_POST['delete_datalog']);
			$ratings_postmeta = array('ratings_users', 'ratings_score', 'ratings_average');
			if(!empty($post_ids)) {
				switch($delete_datalog) {
						case 1:
							if($post_ids == 'all') {
								$delete_logs = $wpdb->query("DELETE FROM $wpdb->ratings");
								if($delete_logs) {
									$text = '<font color="green">'.__('All Post Ratings Logs Have Been Deleted.', 'wp-postratings').'</font>';
								} else {
									$text = '<font color="red">'.__('An Error Has Occured While Deleting All Post Ratings Logs.', 'wp-postratings').'</font>';
								}
							} else {
								$delete_logs = $wpdb->query("DELETE FROM $wpdb->ratings WHERE rating_postid IN($post_ids)");
								if($delete_logs) {
									$text = '<font color="green">'.sprintf(__('All Post Ratings Logs For Post ID(s) %s Have Been Deleted.', 'wp-postratings'), $post_ids).'</font>';
								} else {
									$text = '<font color="red">'.sprintf(__('An Error Has Occured While Deleting All Post Ratings Logs For Post ID(s) %s.', 'wp-postratings'), $post_ids).'</font>';
								}
							}
							break;
						case 2:
							if($post_ids == 'all') {
								foreach($ratings_postmeta as $postmeta) {
									$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '$postmeta'");
									$text .= '<font color="green">'.sprintf(__('Rating Data "%s" Has Been Deleted.', 'wp-postratings'), "<strong><em>$postmeta</em></strong>").'</font><br />';
								}	
							} else {
								foreach($ratings_postmeta as $postmeta) {
									$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '$postmeta' AND post_id IN($post_ids)");
									$text .= '<font color="green">'.sprintf(__('Rating Data "%s" For Post ID(s) %s Has Been Deleted.', 'wp-postratings'), "<strong><em>$postmeta</em></strong>", $post_ids).'</font><br />';
								}	
							}
							break;
						case 3:
							if($post_ids == 'all') {
								$delete_logs = $wpdb->query("DELETE FROM $wpdb->ratings");
								if($delete_logs) {
									$text = '<font color="green">'.__('All Post Ratings Logs Have Been Deleted.', 'wp-postratings').'</font><br />';
								} else {
									$text = '<font color="red">'.__('An Error Has Occured While Deleting All Post Ratings Logs.', 'wp-postratings').'</font><br />';
								}
								foreach($ratings_postmeta as $postmeta) {
									$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '$postmeta'");
									$text .= '<font color="green">'.sprintf(__('Rating Data "%s" Has Been Deleted.', 'wp-postratings'), "<strong><em>$postmeta</em></strong>").'</font><br />';
								}	
							} else {
								$delete_logs = $wpdb->query("DELETE FROM $wpdb->ratings WHERE rating_postid IN($post_ids)");
								if($delete_logs) {
									$text = '<font color="green">'.sprintf(__('All Post Ratings Logs For Post ID(s) %s Have Been Deleted.', 'wp-postratings'), $post_ids).'</font><br />';
								} else {
									$text = '<font color="red">'.sprintf(__('An Error Has Occured While Deleting All Post Ratings Logs For Post ID(s) %s.', 'wp-postratings'), $post_ids).'</font><br />';
								}
								foreach($ratings_postmeta as $postmeta) {
									$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '$postmeta' AND post_id IN($post_ids)");
									$text .= '<font color="green">'.sprintf(__('Rating Data "%s" For Post ID(s) %s Has Been Deleted.', 'wp-postratings'), "<strong><em>$postmeta</em></strong>", $post_ids).'</font><br />';
								}	
							}
							break;
				}
			}
			break;
	}
}


### Form Sorting URL
if(!empty($postratings_filterid)) {
	$postratings_filterid = intval($postratings_filterid);
	$postratings_sort_url .= '&amp;id='.$postratings_filterid;
}
if(!empty($postratings_filteruser)) {
	$postratings_sort_url .= '&amp;user='.$postratings_filteruser;
}
if($_GET['rating'] != '') {
	$postratings_filterrating = intval($postratings_filterrating);
	$postratings_sort_url .= '&amp;rating='.$postratings_filterrating;
}
if(!empty($postratings_sortby)) {
	$postratings_sort_url .= '&amp;by='.$postratings_sortby;
}
if(!empty($postratings_sortorder)) {
	$postratings_sort_url .= '&amp;order='.$postratings_sortorder;
}
if(!empty($postratings_log_perpage)) {
	$postratings_log_perpage = intval($postratings_log_perpage);
	$postratings_sort_url .= '&amp;perpage='.$postratings_log_perpage;
}


### Get Order By
switch($postratings_sortby) {
	case 'id':
		$postratings_sortby = 'rating_id';
		$postratings_sortby_text = __('ID', 'wp-postratings');
		break;
	case 'username':
		$postratings_sortby = 'rating_username';
		$postratings_sortby_text = __('Username', 'wp-postratings');
		break;
	case 'rating':
		$postratings_sortby = 'rating_rating';
		$postratings_sortby_text = __('Rating', 'wp-postratings');
		break;
	case 'postid':
		$postratings_sortby = 'rating_postid';
		$postratings_sortby_text = __('Post ID', 'wp-postratings');
		break;
	case 'posttitle':
		$postratings_sortby = 'rating_posttitle';
		$postratings_sortby_text = __('Post Title', 'wp-postratings');
		break;
	case 'ip':
		$postratings_sortby = 'rating_ip';
		$postratings_sortby_text = __('IP', 'wp-postratings');
		break;
	case 'host':
		$postratings_sortby = 'rating_host';
		$postratings_sortby_text = __('Host', 'wp-postratings');
		break;
	case 'date':
	default:
		$postratings_sortby = 'rating_timestamp';
		$postratings_sortby_text = __('Date', 'wp-postratings');
}


### Get Sort Order
switch($postratings_sortorder) {
	case 'asc':
		$postratings_sortorder = 'ASC';
		$postratings_sortorder_text = __('Ascending', 'wp-postratings');
		break;
	case 'desc':
	default:
		$postratings_sortorder = 'DESC';
		$postratings_sortorder_text = __('Descending', 'wp-postratings');
}


// Where
$postratings_where = '';
if(!empty($postratings_filterid)) {
	$postratings_where = "AND rating_postid =$postratings_filterid";
}
if(!empty($postratings_filteruser)) {
	$postratings_where .= " AND rating_username = '$postratings_filteruser'";
}
if($_GET['rating'] != '') {
	$postratings_where .= " AND rating_rating = '$postratings_filterrating'";
}
// Get Post Ratings Logs Data
$total_ratings = $wpdb->get_var("SELECT COUNT(rating_id) FROM $wpdb->ratings WHERE 1=1 $postratings_where");
$total_users = $wpdb->get_var("SELECT SUM(meta_value) FROM $wpdb->postmeta WHERE meta_key = 'ratings_users'");
$total_score = $wpdb->get_var("SELECT SUM((meta_value+0.00)) FROM $wpdb->postmeta WHERE meta_key = 'ratings_score'");
$ratings_custom = intval(get_option('postratings_customrating'));
if($total_users == 0) { 
	$total_average = 0;
} else {
	$total_average = $total_score/$total_users;
}
// Checking $postratings_page and $offset
if(empty($postratings_page) || $postratings_page == 0) { $postratings_page = 1; }
if(empty($offset)) { $offset = 0; }
if(empty($postratings_log_perpage) || $postratings_log_perpage == 0) { $postratings_log_perpage = 20; }
// Determin $offset
$offset = ($postratings_page-1) * $postratings_log_perpage;
// Determine Max Number Of Ratings To Display On Page
if(($offset + $postratings_log_perpage) > $total_ratings) { 
	$max_on_page = $total_ratings; 
} else { 
	$max_on_page = ($offset + $postratings_log_perpage); 
}
// Determine Number Of Ratings To Display On Page
if (($offset + 1) > ($total_ratings)) { 
	$display_on_page = $total_ratings; 
} else { 
	$display_on_page = ($offset + 1); 
}
// Determing Total Amount Of Pages
$total_pages = ceil($total_ratings / $postratings_log_perpage);

// Get The Logs
$postratings_logs = $wpdb->get_results("SELECT * FROM $wpdb->ratings WHERE 1=1 $postratings_where ORDER BY $postratings_sortby $postratings_sortorder LIMIT $offset, $postratings_log_perpage");
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<!-- Manage Post Ratings -->
<div class="wrap">
	<div id="icon-wp-postratings" class="icon32"><br /></div>
	<h2><?php _e('Manage Ratings', 'wp-postratings'); ?></h2>
	<h3><?php _e('Post Ratings Logs', 'wp-postratings'); ?></h3>
	<p><?php printf(__('Displaying <strong>%s</strong> to <strong>%s</strong> of <strong>%s</strong> Post Ratings log entries.', 'wp-postratings'), number_format_i18n($display_on_page), number_format_i18n($max_on_page), number_format_i18n($total_ratings)); ?></p>
	<p><?php printf(__('Sorted by <strong>%s</strong> in <strong>%s</strong> order.', 'wp-postratings'), $postratings_sortby_text, $postratings_sortorder_text); ?></p>
	<table class="widefat">
		<thead>
			<tr>
				<th width="2%"><?php _e('ID', 'wp-postratings'); ?></th>
				<th width="10%"><?php _e('Username', 'wp-postratings'); ?></th>
				<th width="10%"><?php _e('Rating', 'wp-postratings'); ?></th>
				<th width="8%"><?php _e('Post ID', 'wp-postratings'); ?></th>
				<th width="25%"><?php _e('Post Title', 'wp-postratings'); ?></th>	
				<th width="20%"><?php _e('Date / Time', 'wp-postratings'); ?></th>
				<th width="25%"><?php _e('IP / Host', 'wp-postratings'); ?></th>			
			</tr>
		</thead>
		<tbody>
	<?php
		if($postratings_logs) {
			$i = 0;
			foreach($postratings_logs as $postratings_log) {
				if($i%2 == 0) {
					$style = 'class="alternate"';
				}  else {
					$style = '';
				}
				$postratings_id = intval($postratings_log->rating_id);
				$postratings_username = stripslashes($postratings_log->rating_username);
				$postratings_rating = intval($postratings_log->rating_rating);
				$postratings_postid = intval($postratings_log->rating_postid);
				$postratings_posttitle = stripslashes($postratings_log->rating_posttitle);
				$postratings_date = mysql2date(sprintf(__('%s @ %s', 'wp-postratings'), get_option('date_format'), get_option('time_format')), gmdate('Y-m-d H:i:s', $postratings_log->rating_timestamp));
				$postratings_ip = $postratings_log->rating_ip;
				$postratings_host = $postratings_log->rating_host;				
				echo "<tr $style>\n";
				echo '<td>'.$postratings_id.'</td>'."\n";
				echo "<td>$postratings_username</td>\n";
				echo '<td nowrap="nowrap">';
				if($ratings_custom && $ratings_max == 2) {
					if($postratings_rating > 0) {
						$postratings_rating = '+'.$postratings_rating;
					}
					echo $postratings_rating;
				} else {
					if('rtl' == $text_direction && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
						echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
					} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
						echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
					}
					if($ratings_custom) {
						for($j=1; $j <= $ratings_max; $j++) {
							if($j <= $postratings_rating) {
								echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_'.$j.'_on.'.RATINGS_IMG_EXT).'" alt="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" title="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" class="post-ratings-image" />';
							} else {
								echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_'.$j.'_off.'.RATINGS_IMG_EXT).'" alt="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" title="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" class="post-ratings-image" />';
							}
						}
					} else {
						for($j=1; $j <= $ratings_max; $j++) {
							if($j <= $postratings_rating) {
								echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_on.'.RATINGS_IMG_EXT).'" alt="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" title="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" class="post-ratings-image" />';
							} else {
								echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_off.'.RATINGS_IMG_EXT).'" alt="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" title="'.sprintf(_n('User Rate This Post %s Star', 'User Rate This Post %s Stars', $postratings_rating, 'wp-postratings'), $postratings_rating).__(' Out Of ', 'wp-postratings').$ratings_max.'" class="post-ratings-image" />';
							}
						}
					}
					if('rtl' == $text_direction && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
						echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
					} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
						echo '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
					}
				}
				echo '</td>'."\n";
				echo '<td>'.number_format_i18n($postratings_postid).'</td>'."\n";
				echo "<td>$postratings_posttitle</td>\n";
				echo "<td>$postratings_date</td>\n";
				echo "<td>$postratings_ip / $postratings_host</td>\n";
				echo '</tr>';
				$i++;
			}
		} else {
			echo '<tr><td colspan="7" align="center"><strong>'.__('No Post Ratings Logs Found', 'wp-postratings').'</strong></td></tr>';
		}
	?>
		</tbody>
	</table>
		<!-- <Paging> -->
		<?php
			if($total_pages > 1) {
		?>
		<br />
		<table class="widefat">
			<tr>
				<td align="<?php echo ('rtl' == $text_direction) ? 'right' : 'left'; ?>" width="50%">
					<?php
						if($postratings_page > 1 && ((($postratings_page*$postratings_log_perpage)-($postratings_log_perpage-1)) <= $total_ratings)) {
							echo '<strong>&laquo;</strong> <a href="'.$base_page.'&amp;ratingpage='.($postratings_page-1).$postratings_sort_url.'" title="&laquo; '.__('Previous Page', 'wp-postratings').'">'.__('Previous Page', 'wp-postratings').'</a>';
						} else {
							echo '&nbsp;';
						}
					?>
				</td>
				<td align="<?php echo ('rtl' == $text_direction) ? 'left' : 'right'; ?>" width="50%">
					<?php
						if($postratings_page >= 1 && ((($postratings_page*$postratings_log_perpage)+1) <=  $total_ratings)) {
							echo '<a href="'.$base_page.'&amp;ratingpage='.($postratings_page+1).$postratings_sort_url.'" title="'.__('Next Page', 'wp-postratings').' &raquo;">'.__('Next Page', 'wp-postratings').'</a> <strong>&raquo;</strong>';
						} else {
							echo '&nbsp;';
						}
					?>
				</td>
			</tr>
			<tr class="alternate">
				<td colspan="2" align="center">
					<?php printf(__('Pages (%s): ', 'wp-postratings'), number_format_i18n($total_pages)); ?>
					<?php
						if ($postratings_page >= 4) {
							echo '<strong><a href="'.$base_page.'&amp;ratingpage=1'.$postratings_sort_url.$postratings_sort_url.'" title="'.__('Go to First Page', 'wp-postratings').'">&laquo; '.__('First', 'wp-postratings').'</a></strong> ... ';
						}
						if($postratings_page > 1) {
							echo ' <strong><a href="'.$base_page.'&amp;ratingpage='.($postratings_page-1).$postratings_sort_url.'" title="&laquo; '.__('Go to Page', 'wp-postratings').' '.number_format_i18n($postratings_page-1).'">&laquo;</a></strong> ';
						}
						for($i = $postratings_page - 2 ; $i  <= $postratings_page +2; $i++) {
							if ($i >= 1 && $i <= $total_pages) {
								if($i == $postratings_page) {
									echo '<strong>['.number_format_i18n($i).']</strong> ';
								} else {
									echo '<a href="'.$base_page.'&amp;ratingpage='.($i).$postratings_sort_url.'" title="'.__('Page', 'wp-postratings').' '.number_format_i18n($i).'">'.number_format_i18n($i).'</a> ';
								}
							}
						}
						if($postratings_page < $total_pages) {
							echo ' <strong><a href="'.$base_page.'&amp;ratingpage='.($postratings_page+1).$postratings_sort_url.'" title="'.__('Go to Page', 'wp-postratings').' '.number_format_i18n($postratings_page+1).' &raquo;">&raquo;</a></strong> ';
						}
						if (($postratings_page+2) < $total_pages) {
							echo ' ... <strong><a href="'.$base_page.'&amp;ratingpage='.($total_pages).$postratings_sort_url.'" title="'.__('Go to Last Page', 'wp-postratings').'">'.__('Last', 'wp-postratings').' &raquo;</a></strong>';
						}
					?>
				</td>
			</tr>
		</table>	
		<!-- </Paging> -->
		<?php
			}
		?>
	<br />
	<form action="<?php echo admin_url('admin.php'); ?>" method="get">
		<input type="hidden" name="page" value="<?php echo $base_name; ?>" />
		<table class="widefat">
			<tr>
				<th><?php _e('Filter Options:', 'wp-postratings'); ?></th>
				<td>
					<?php _e('Post ID:', 'wp-postratings'); ?>&nbsp;<input type="text" name="id" value="<?php echo $postratings_filterid; ?>" size="5" maxlength="5" />
					&nbsp;&nbsp;&nbsp;
					<select name="user" size="1">
						<option value=""></option>
						<?php
							$filter_users = $wpdb->get_results("SELECT DISTINCT rating_username, rating_userid FROM $wpdb->ratings WHERE rating_username != '".__('Guest', 'wp-postratings')."' ORDER BY rating_userid ASC, rating_username ASC");
							if($filter_users) {
								foreach($filter_users as $filter_user) {
									$rating_username = stripslashes($filter_user->rating_username);
									$rating_userid = intval($filter_user->rating_userid);
									if($rating_userid > 0) {
										$prefix = __('Registered User: ', 'wp-postratings');
									} else {
										$prefix = __('Comment Author: ', 'wp-postratings');
									}
									if($rating_username == $postratings_filteruser) {
										echo '<option value="'.htmlspecialchars($rating_username).'" selected="selected">'.$prefix.' '.$rating_username.'</option>'."\n";
									} else {
										echo '<option value="'.htmlspecialchars($rating_username).'">'.$prefix.' '.$rating_username.'</option>'."\n";
									}
								}
							}
						?>
					</select>
					&nbsp;&nbsp;&nbsp;
					<select name="rating" size="1">
						<option value=""></option>
						<?php
							$filter_ratings = $wpdb->get_results("SELECT DISTINCT rating_rating FROM $wpdb->ratings ORDER BY rating_rating ASC");
							if($filter_ratings) {
								foreach($filter_ratings as $filter_rating) {
									$rating_rating = $filter_rating->rating_rating;
									$prefix = __('Rating: ', 'wp-postratings');
									if($rating_rating == $postratings_filterrating) {
										echo '<option value="'.$rating_rating.'" selected="selected">'.$prefix.' '.number_format_i18n($rating_rating).'</option>'."\n";
									} else {
										echo '<option value="'.$rating_rating.'">'.$prefix.' '.number_format_i18n($rating_rating).'</option>'."\n";
									}
								}
							}
						?>
					</select>
				</td>
			</tr>
			<tr class="alternate">
				<th><?php _e('Sort Options:', 'wp-postratings'); ?></th>
				<td>
					<select name="by" size="1">
						<option value="id"<?php if($postratings_sortby == 'rating_id') { echo ' selected="selected"'; }?>><?php _e('ID', 'wp-postratings'); ?></option>
						<option value="username"<?php if($postratings_sortby == 'rating_username') { echo ' selected="selected"'; }?>><?php _e('UserName', 'wp-postratings'); ?></option>
						<option value="rating"<?php if($postratings_sortby == 'rating_rating') { echo ' selected="selected"'; }?>><?php _e('Rating', 'wp-postratings'); ?></option>
						<option value="postid"<?php if($postratings_sortby == 'rating_postid') { echo ' selected="selected"'; }?>><?php _e('Post ID', 'wp-postratings'); ?></option>
						<option value="posttitle"<?php if($postratings_sortby == 'rating_posttitle') { echo ' selected="selected"'; }?>><?php _e('Post Title', 'wp-postratings'); ?></option>
						<option value="date"<?php if($postratings_sortby == 'rating_timestamp') { echo ' selected="selected"'; }?>><?php _e('Date', 'wp-postratings'); ?></option>
						<option value="ip"<?php if($postratings_sortby == 'rating_ip') { echo ' selected="selected"'; }?>><?php _e('IP', 'wp-postratings'); ?></option>
						<option value="host"<?php if($postratings_sortby == 'rating_host') { echo ' selected="selected"'; }?>><?php _e('Host', 'wp-postratings'); ?></option>
					</select>
					&nbsp;&nbsp;&nbsp;
					<select name="order" size="1">
						<option value="asc"<?php if($postratings_sortorder == 'ASC') { echo ' selected="selected"'; }?>><?php _e('Ascending', 'wp-postratings'); ?></option>
						<option value="desc"<?php if($postratings_sortorder == 'DESC') { echo ' selected="selected"'; } ?>><?php _e('Descending', 'wp-postratings'); ?></option>
					</select>
					&nbsp;&nbsp;&nbsp;
					<select name="perpage" size="1">
					<?php
						for($i=10; $i <= 100; $i+=10) {
							if($postratings_log_perpage == $i) {
								echo "<option value=\"$i\" selected=\"selected\">".__('Per Page', 'wp-postratings').": ".number_format_i18n($i)."</option>\n";
							} else {
								echo "<option value=\"$i\">".__('Per Page', 'wp-postratings').": ".number_format_i18n($i)."</option>\n";
							}
						}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center"><input type="submit" value="<?php _e('Go', 'wp-postratings'); ?>" class="button" /></td>
			</tr>
		</table>
	</form>
</div>
<p>&nbsp;</p>

<!-- Post Ratings Stats -->
<div class="wrap">
	<h3><?php _e('Post Ratings Logs Stats', 'wp-postratings'); ?></h3>
	<br style="clear" />
	<table class="widefat">
		<tr>
			<th><?php _e('Total Users Voted:', 'wp-postratings'); ?></th>
			<td><?php echo number_format_i18n($total_users); ?></td>
		</tr>
		<tr class="alternate">
			<th><?php _e('Total Score:', 'wp-postratings'); ?></th>
			<td><?php echo number_format_i18n($total_score); ?></td>
		</tr>
		<tr>
			<th><?php _e('Total Average:', 'wp-postratings'); ?></th>
			<td><?php echo number_format_i18n($total_average, 2); ?></td>
		</tr>
	</table>
</div>
<p>&nbsp;</p>

<!-- Delete Post Ratings Logs -->
<div class="wrap">
	<h3><?php _e('Delete Post Ratings Data/Logs', 'wp-postratings'); ?></h3>
	<br style="clear" />
	<div align="center">
		<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
		<?php wp_nonce_field('wp-postratings_logs'); ?>
		<table class="widefat">
			<tr>
				<td valign="top"><b><?php _e('Delete Type: ', 'wp-postratings'); ?></b></td>
				<td valign="top">
					<select size="1" name="delete_datalog">
						<option value="1"><?php _e('Logs Only', 'wp-postratings'); ?></option>
						<option value="2"><?php _e('Data Only', 'wp-postratings'); ?></option>
						<option value="3"><?php _e('Logs And Data', 'wp-postratings'); ?></option>
					</select>				
				</td>
			</tr>
			<tr>
				<td valign="top"><b><?php _e('Post ID(s):', 'wp-postratings'); ?></b></td>
				<td valign="top">
					<input type="text" name="delete_postid" size="20" dir="ltr" />
					<p><?php _e('Seperate each Post ID with a comma.', 'wp-postratings'); ?></p>
					<p><?php _e('To delete ratings data/logs from Post ID 2, 3 and 4. Just type in: <b>2,3,4</b>', 'wp-postratings'); ?></p>
					<p><?php _e('To delete ratings data/logs for all posts. Just type in: <b>all</b>', 'wp-postratings'); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center">
					<input type="submit" name="do" value="<?php _e('Delete Data/Logs', 'wp-postratings'); ?>" class="button" onclick="return confirm('<?php _e('You Are About To Delete Post Ratings Data/Logs.\nThis Action Is Not Reversible.\n\n Choose \\\'Cancel\\\' to stop, \\\'OK\\\' to delete.', 'wp-postratings'); ?>')" />
				</td>
			</tr>
		</table>
		</form>	
	</div>
	<h3><?php _e('Note:', 'wp-postratings'); ?></h3>
	<ul>
		<li><?php _e('\'Logs Only\' means the logs generated when a user rates a post.', 'wp-postratings'); ?></li>
		<li><?php _e('\'Data Only\' means the rating data for the post.', 'wp-postratings'); ?></li>
		<li><?php _e('\'Logs And Data\' means both the logs generated and the rating data for the post.', 'wp-postratings'); ?></li>
		<li><?php _e('If your logging method is by IP and Cookie or by Cookie, users may still be unable to rate if they have voted before as the cookie is still stored in their computer.', 'wp-postratings'); ?></li>
	</ul>
</div>
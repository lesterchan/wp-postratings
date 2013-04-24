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
|	- Uninstall WP-PostRatings														|
|	- wp-content/plugins/wp-postratings/postratings-uninstall.php		|
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
$ratings_tables = array($wpdb->ratings);
$ratings_settings = array('postratings_image', 'postratings_max', 'postratings_template_vote', 'postratings_template_text', 'postratings_template_none', 'postratings_logging_method', 'postratings_allowtorate', 'postratings_ratingstext', 'postratings_template_highestrated', 'postratings_ajax_style', 'widget_ratings_highest_rated', 'widget_ratings_most_rated', 'postratings_customrating', 'postratings_ratingsvalue', 'postratings_template_permission', 'postratings_template_mostrated', 'widget_ratings', 'widget_ratings-widget');
$ratings_postmetas = array('ratings_users', 'ratings_score', 'ratings_average');


### Form Processing 
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		//  Uninstall WP-PostRatings
		case __('UNINSTALL WP-PostRatings', 'wp-postratings') :
			check_admin_referer('wp-postratings_uninstall');
			if(trim($_POST['uninstall_rating_yes']) == 'yes') {
				echo '<div id="message" class="updated fade">';
				echo '<p>';
				foreach($ratings_tables as $table) {
					$wpdb->query("DROP TABLE {$table}");
					echo '<font style="color: green;">';
					printf(__('Table \'%s\' has been deleted.', 'wp-postratings'), "<strong><em>{$table}</em></strong>");
					echo '</font><br />';
				}
				echo '</p>';
				echo '<p>';
				foreach($ratings_settings as $setting) {
					$delete_setting = delete_option($setting);
					if($delete_setting) {
						echo '<font color="green">';
						printf(__('Setting Key \'%s\' has been deleted.', 'wp-postratings'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Setting Key \'%s\' or Setting Key \'%s\' does not exist.', 'wp-postratings'), "<strong><em>{$setting}</em></strong>", "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '<p>';
				foreach($ratings_postmetas as $postmeta) {
					$remove_postmeta = $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '$postmeta'");
					if($remove_postmeta) {
						echo '<font color="green">';
						printf(__('Post Meta Key \'%s\' has been deleted.', 'wp-postratings'), "<strong><em>{$postmeta}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Post Meta Key \'%s\' or Post Meta Key \'%s\' does not exist.', 'wp-postratings'), "<strong><em>{$postmeta}</em></strong>", "<strong><em>{$postmeta}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '</div>'; 
				$mode = 'end-UNINSTALL';
			}
			break;
	}
}


### Determines Which Mode It Is
switch($mode) {
		//  Deactivating WP-PostRatings
		case 'end-UNINSTALL':
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=wp-postratings/wp-postratings.php';
			if(function_exists('wp_nonce_url')) { 
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_wp-postratings/wp-postratings.php');
			}
			echo '<div class="wrap">';
			echo '<div id="icon-wp-postratings" class="icon32"><br /></div>';
			echo '<h2>'.__('Uninstall WP-PostRatings', 'wp-postratings').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-PostRatings Will Be Deactivated Automatically.', 'wp-postratings'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
?>
<!-- Uninstall WP-PostRatings -->
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<?php wp_nonce_field('wp-postratings_uninstall'); ?>
<div class="wrap">
	<div id="icon-wp-postratings" class="icon32"><br /></div>
	<h2><?php _e('Uninstall WP-PostRatings', 'wp-postratings'); ?></h2>
	<p>
		<?php _e('Deactivating WP-PostRatings plugin does not remove any data that may have been created, such as the ratings data and the ratings\'s logs. To completely remove this plugin, you can uninstall it here.', 'wp-postratings'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('WARNING:', 'wp-postratings'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-postratings'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('The following WordPress Options/Tables/PostMetas will be DELETED:', 'wp-postratings'); ?></strong><br />
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('WordPress Options', 'wp-postratings'); ?></th>
				<th><?php _e('WordPress Tables', 'wp-postratings'); ?></th>
				<th><?php _e('WordPress PostMetas', 'wp-postratings'); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td valign="top">
					<ol>
					<?php
						foreach($ratings_settings as $settings) {
							echo '<li>'.$settings.'</li>'."\n";
						}
					?>
					</ol>
				</td>
				<td valign="top" class="alternate">
					<ol>
					<?php
						foreach($ratings_tables as $tables) {
							echo '<li>'.$tables.'</li>'."\n";
						}
					?>
					</ol>
				</td>
				<td valign="top">
					<ol>
					<?php
						foreach($ratings_postmetas as $postmeta) {
							echo '<li>'.$postmeta.'</li>'."\n";
						}
					?>
					</ol>
				</td>
			</tr>
		</tbody>
	</table>
	<p>&nbsp;</p>
	<p style="text-align: center;">
		<input type="checkbox" name="uninstall_rating_yes" value="yes" />&nbsp;<?php _e('Yes', 'wp-postratings'); ?><br /><br />
		<input type="submit" name="do" value="<?php _e('UNINSTALL WP-PostRatings', 'wp-postratings'); ?>" class="button-primary" onclick="return confirm('<?php _e('You Are About To Uninstall WP-PostRatings From WordPress.\nThis Action Is Not Reversible.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.', 'wp-postratings'); ?>')" />
	</p>
</div>
</form>
<?php
} // End switch($mode)
?>
<?php
/**
 * WP-PostRatings Options.
 *
 * @package WordPress
 * @subpackage WP-PostRatings Plugin
 */


/**
 * Security check
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Permission check
 * Check whether the user can manage ratings
 */
if ( ! current_user_can( 'manage_ratings' ) ) {
    wp_die( esc_html__( 'Access Denied', 'wp-postratings' ) );
}


### Ratings Variables
$base_name = plugin_basename('wp-postratings/postratings-manager.php');
$base_page = 'admin.php?page='.$base_name;


### If Form Is Submitted
if ( isset( $_POST['Submit'] ) ) {
    check_admin_referer('wp-postratings_options');
    $postratings_customrating = intval($_POST['postratings_customrating']);
    $postratings_template_vote = wp_kses_post(trim($_POST['postratings_template_vote']));
    $postratings_template_text = wp_kses_post(trim($_POST['postratings_template_text']));
    $postratings_template_permission = wp_kses_post(trim($_POST['postratings_template_permission']));
    $postratings_template_none = wp_kses_post(trim($_POST['postratings_template_none']));
    $postratings_template_highestrated = wp_kses_post(trim($_POST['postratings_template_highestrated']));
    $postratings_template_mostrated = wp_kses_post(trim($_POST['postratings_template_mostrated']));
    $postratings_image = sanitize_text_field( trim( $_POST['postratings_image'] ) );
    $postratings_max = intval($_POST['postratings_max']);
    $postratings_richsnippet = intval($_POST['postratings_richsnippet']);
    $postratings_ratingstext_array = $_POST['postratings_ratingstext'];
    $postratings_ratingstext = array();
    if( ! empty( $postratings_ratingstext_array ) && is_array( $postratings_ratingstext_array ) ) {
        foreach( $postratings_ratingstext_array as $ratingstext ) {
            $postratings_ratingstext[] = wp_kses_post(trim( $ratingstext ));
        }
    }
    $postratings_ratingsvalue_array = $_POST['postratings_ratingsvalue'];
    $postratings_ratingsvalue = array();
    if( ! empty( $postratings_ratingsvalue_array )  && is_array( $postratings_ratingsvalue_array ) ) {
        foreach($postratings_ratingsvalue_array as $ratingsvalue) {
            $postratings_ratingsvalue[] =intval( $ratingsvalue );
        }
    }

    $postratings_ajax_style = array('loading' => intval($_POST['postratings_ajax_style_loading']), 'fading' => intval($_POST['postratings_ajax_style_fading']));
    $postratings_logging_method = intval($_POST['postratings_logging_method']);
    $postratings_allowtorate = intval($_POST['postratings_allowtorate']);
    $update_ratings_queries = array();
    $update_ratings_text = array();
    $postratings_options = array('richsnippet' => $postratings_richsnippet);
    $update_ratings_queries[] = update_option('postratings_customrating', $postratings_customrating);
    $update_ratings_queries[] = update_option('postratings_template_vote', $postratings_template_vote);
    $update_ratings_queries[] = update_option('postratings_template_text', $postratings_template_text);
    $update_ratings_queries[] = update_option('postratings_template_permission', $postratings_template_permission);
    $update_ratings_queries[] = update_option('postratings_template_none', $postratings_template_none);
    $update_ratings_queries[] = update_option('postratings_template_highestrated', $postratings_template_highestrated);
    $update_ratings_queries[] = update_option('postratings_template_mostrated', $postratings_template_mostrated);
    $update_ratings_queries[] = update_option('postratings_image', $postratings_image);
    $update_ratings_queries[] = update_option('postratings_max', $postratings_max);
    $update_ratings_queries[] = update_option('postratings_ratingstext', $postratings_ratingstext);
    $update_ratings_queries[] = update_option('postratings_ratingsvalue', $postratings_ratingsvalue);
    $update_ratings_queries[] = update_option('postratings_ajax_style', $postratings_ajax_style);
    $update_ratings_queries[] = update_option('postratings_logging_method', $postratings_logging_method);
    $update_ratings_queries[] = update_option('postratings_allowtorate', $postratings_allowtorate);
    $update_ratings_queries[] = update_option('postratings_options', $postratings_options);
    $update_ratings_text[] = __('Custom Rating', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template Vote', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template Voted', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template No Permission', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template For No Ratings', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template For Highest Rated', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Template For Most Rated', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Image', 'wp-postratings');
    $update_ratings_text[] = __('Max Ratings', 'wp-postratings');
    $update_ratings_text[] = __('Individual Rating Text', 'wp-postratings');
    $update_ratings_text[] = __('Individual Rating Value', 'wp-postratings');
    $update_ratings_text[] = __('Ratings AJAX Style', 'wp-postratings');
    $update_ratings_text[] = __('Logging Method', 'wp-postratings');
    $update_ratings_text[] = __('Allow To Vote Option', 'wp-postratings');
    $update_ratings_text[] = __('Ratings Settings', 'wp-postratings');
    $i = 0;
    $text = '';
    foreach($update_ratings_queries as $update_ratings_query) {
        if($update_ratings_query) {
            $text .= '<p style="color: green;">'.$update_ratings_text[$i].' '.__('Updated', 'wp-postratings').'</p>';
        }
        $i++;
    }
    if(empty($text)) {
        $text = '<p style="color: red;">'.__('No Ratings Option Updated', 'wp-postratings').'</p>';
    }
}


### Needed Variables
$postratings_max = intval(get_option('postratings_max'));
$postratings_options = get_option('postratings_options');
$postratings_customrating = intval(get_option('postratings_customrating'));
$postratings_url = plugins_url('wp-postratings/images');
$postratings_path = WP_PLUGIN_DIR.'/wp-postratings/images';
$postratings_ratingstext = get_option('postratings_ratingstext');
$postratings_ratingsvalue = get_option('postratings_ratingsvalue');
$postratings_image = get_option('postratings_image');
?>
<script type="text/javascript">
/* <![CDATA[*/
    function ratings_updown_templates(template, print) {
        var default_template;
        switch(template) {
            case "vote":
                default_template = "%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_SCORE%</strong> <?php esc_html_e('rating', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?>)<br />%RATINGS_TEXT%";
                break;
            case "text":
                default_template = "%RATINGS_IMAGES% (<em><strong>%RATINGS_SCORE%</strong> <?php esc_html_e('rating', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <strong><?php esc_html_e('rated', 'wp-postratings'); ?></strong></em>)";
                break;
            case "permission":
                default_template = "%RATINGS_IMAGES% (<em><strong>%RATINGS_SCORE%</strong> <?php esc_html_e('rating', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <strong><?php esc_html_e('rated', 'wp-postratings'); ?></strong></em>)<br /><em><?php esc_html_e('You need to be a registered member to rate this.', 'wp-postratings'); ?></em>";
                break;
            case "none":
                default_template = "%RATINGS_IMAGES_VOTE% (<?php esc_html_e('No Ratings Yet', 'wp-postratings'); ?>)<br />%RATINGS_TEXT%";
                break;
            case "highestrated":
                default_template = "<li><a href=\"%POST_URL%\" title=\"%POST_TITLE%\">%POST_TITLE%</a> (%RATINGS_SCORE% <?php esc_html_e('rating', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> %RATINGS_USERS% <?php esc_html_e('votes', 'wp-postratings'); ?>)</li>";
                break;
            case "mostrated":
                default_template = "<li><a href=\"%POST_URL%\"  title=\"%POST_TITLE%\">%POST_TITLE%</a> - %RATINGS_USERS% <?php esc_html_e('votes', 'wp-postratings'); ?></li>";
                break;
        }
        if(print) {
            jQuery("#postratings_template_" + template).val(default_template);
        } else {
            return default_template;
        }
    }
    function ratings_default_templates(template, print) {
        var default_template;
        switch(template) {
            case "vote":
                default_template = "%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <?php esc_html_e('average', 'wp-postratings'); ?>: <strong>%RATINGS_AVERAGE%</strong> <?php esc_html_e('out of', 'wp-postratings'); ?> %RATINGS_MAX%)<br />%RATINGS_TEXT%";
                break;
            case "text":
                default_template = "%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <?php esc_html_e('average', 'wp-postratings'); ?>: <strong>%RATINGS_AVERAGE%</strong> <?php esc_html_e('out of', 'wp-postratings'); ?> %RATINGS_MAX%<?php esc_html_e(',', 'wp-postratings'); ?> <strong><?php esc_html_e('rated', 'wp-postratings'); ?></strong></em>)";
                break;
            case "permission":
                default_template = "%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> <?php esc_html_e('votes', 'wp-postratings'); ?><?php esc_html_e(',', 'wp-postratings'); ?> <?php esc_html_e('average', 'wp-postratings'); ?>: <strong>%RATINGS_AVERAGE%</strong> <?php esc_html_e('out of', 'wp-postratings'); ?> %RATINGS_MAX%</em>)<br /><em><?php esc_html_e('You need to be a registered member to rate this.', 'wp-postratings'); ?></em>";
                break;
            case "none":
                default_template = "%RATINGS_IMAGES_VOTE% (<?php esc_html_e('No Ratings Yet', 'wp-postratings'); ?>)<br />%RATINGS_TEXT%";
                break;
            case "highestrated":
                default_template = "<li><a href=\"%POST_URL%\" title=\"%POST_TITLE%\">%POST_TITLE%</a> %RATINGS_IMAGES% (%RATINGS_AVERAGE% <?php esc_html_e('out of', 'wp-postratings'); ?> %RATINGS_MAX%)</li>";
                break;
            case "mostrated":
                default_template = "<li><a href=\"%POST_URL%\"  title=\"%POST_TITLE%\">%POST_TITLE%</a> - %RATINGS_USERS% <?php esc_html_e('votes', 'wp-postratings'); ?></li>";
                break;
        }
        if(print) {
            jQuery("#postratings_template_" + template).val(default_template);
        } else {
            return default_template;
        }
    }
    function set_custom(custom, max) {
        if(custom == 1) {
            jQuery("#postratings_max").val(max);
            jQuery("#postratings_max").attr("readonly", true);
            if(max == 2) {
                jQuery("#postratings_template_vote").val(ratings_updown_templates("vote", false));
                jQuery("#postratings_template_text").val(ratings_updown_templates("text", false));
                jQuery("#postratings_template_permission").val(ratings_updown_templates("permission", false));
                jQuery("#postratings_template_none").val(ratings_updown_templates("none", false));
                jQuery("#postratings_template_highestrated").val(ratings_updown_templates("highestrated", false));
                jQuery("#postratings_template_mostrated").val(ratings_updown_templates("mostrated", false));
            } else {
                jQuery("#postratings_template_vote").val(ratings_default_templates("vote", false));
                jQuery("#postratings_template_text").val(ratings_default_templates("text", false));
                jQuery("#postratings_template_none").val(ratings_default_templates("none", false));
                jQuery("#postratings_template_highestrated").val(ratings_default_templates("highestrated", false));
                jQuery("#postratings_template_mostrated").val(ratings_default_templates("mostrated", false));
            }
        } else {
            jQuery("#postratings_max").val(<?php echo $postratings_max; ?>);
            jQuery("#postratings_max").attr("readonly", false);
            jQuery("#postratings_template_vote").val(ratings_default_templates("vote", false));
            jQuery("#postratings_template_text").val(ratings_default_templates("text", false));
            jQuery("#postratings_template_permission").val(ratings_default_templates("permission", false));
            jQuery("#postratings_template_none").val(ratings_default_templates("none", false));
            jQuery("#postratings_template_highestrated").val(ratings_default_templates("highestrated", false));
            jQuery("#postratings_template_mostrated").val(ratings_default_templates("mostrated", false));
        }
        jQuery("#postratings_customrating").val(custom);
    }
/* ]]> */
</script>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<div class="wrap">
    <h1><?php esc_html_e('Post Ratings Options', 'wp-postratings'); ?></h1>
    <form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
        <?php wp_nonce_field('wp-postratings_options'); ?>
        <input type="hidden" id="postratings_customrating" name="postratings_customrating" value="<?php echo $postratings_customrating; ?>" />
        <input type="hidden" id="postratings_template_vote" name="postratings_template_vote" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_vote'))); ?>" />
        <input type="hidden" id="postratings_template_text" name="postratings_template_text" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_text'))); ?>" />
        <input type="hidden" id="postratings_template_permission" name="postratings_template_permission" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_permission'))); ?>" />
        <input type="hidden" id="postratings_template_none" name="postratings_template_none" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_none'))); ?>" />
        <input type="hidden" id="postratings_template_highestrated" name="postratings_template_highestrated" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_highestrated'))); ?>" />
        <input type="hidden" id="postratings_template_mostrated" name="postratings_template_mostrated" value="<?php echo esc_attr(stripslashes(get_option('postratings_template_mostrated'))); ?>" />
        <h2><?php esc_html_e('Ratings Settings', 'wp-postratings'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row" valign="top"><?php esc_html_e('Ratings Image:', 'wp-postratings'); ?></th>
                <td>
                    <?php
                        $postratings_images_array = array();
                        if($handle = @opendir($postratings_path)) {
                            while (false !== ($filename = readdir($handle))) {
                                if ($filename != '.' && $filename != '..' && strpos($filename, '.') !== 0) {
                                    if(is_dir($postratings_path.'/'.$filename)) {
                                        $postratings_images_array[$filename] = ratings_images_folder($filename);
                                    }
                                }
                            }
                            closedir($handle);
                        }
                        foreach($postratings_images_array as $key => $value) {
                            if(strpos($value['images'][0], '.'.RATINGS_IMG_EXT) === false) {
                                continue;
                            }
                            echo '<p>';
                            if($value['custom'] == 0) {
                                if($postratings_image == $key) {
                                    echo '<input type="radio" name="postratings_image" onclick="set_custom('.$value['custom'].', '.$value['max'].');" value="'.$key.'" checked="checked" />';
                                } else {
                                    echo '<input type="radio" name="postratings_image" onclick="set_custom('.$value['custom'].', '.$value['max'].');" value="'.$key.'" />';
                                }
                                echo '&nbsp;&nbsp;&nbsp;';
                                if(is_rtl() && file_exists($postratings_path.'/'.$key.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_start-rtl.'.RATINGS_IMG_EXT.'" alt="rating_start-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                } else if(file_exists($postratings_path.'/'.$key.'/rating_start.'.RATINGS_IMG_EXT)) {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_start.'.RATINGS_IMG_EXT.'" alt="rating_start.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                }
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_over.'.RATINGS_IMG_EXT.'" alt="rating_over.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                if(is_rtl() && file_exists($postratings_path.'/'.$key.'/rating_half-rtl.'.RATINGS_IMG_EXT)) {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_half-rtl.'.RATINGS_IMG_EXT.'" alt="rating_half-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                } else {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_half.'.RATINGS_IMG_EXT.'" alt="rating_half.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                }
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_off.'.RATINGS_IMG_EXT.'" alt="rating_off.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            } else {
                                if($postratings_image == $key) {
                                    echo '<input type="radio" name="postratings_image" onclick="set_custom('.$value['custom'].', '.$value['max'].');" value="'.$key.'" checked="checked" />';
                                } else {
                                    echo '<input type="radio" name="postratings_image" onclick="set_custom('.$value['custom'].', '.$value['max'].');" value="'.$key.'" />';
                                }
                                echo '&nbsp;&nbsp;&nbsp;';
                                if(is_rtl() && file_exists($postratings_path.'/'.$key.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_start-rtl.'.RATINGS_IMG_EXT.'" alt="rating_start-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                } elseif(file_exists($postratings_path.'/'.$key.'/rating_start.'.RATINGS_IMG_EXT)) {
                                    echo '<img src="'.$postratings_url.'/'.$key.'/rating_start.'.RATINGS_IMG_EXT.'" alt="rating_start.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                }
                                for($i = 1; $i <= $value['max']; $i++) {
                                        if(file_exists($postratings_path.'/'.$key.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT)) {
                                            echo '<img src="'.$postratings_url.'/'.$key.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT.'" alt="rating_'.$i.'_off.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                        }
                                }
                            }
                            if(is_rtl() && file_exists($postratings_path.'/'.$key.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_end-rtl.'.RATINGS_IMG_EXT.'" alt="rating_end-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            } elseif(file_exists($postratings_path.'/'.$key.'/rating_end.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$key.'/rating_end.'.RATINGS_IMG_EXT.'" alt="rating_end.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            }
                            echo '&nbsp;&nbsp;&nbsp;('.$key.')';
                            echo '</p>'."\n";
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php esc_html_e('Max Ratings:', 'wp-postratings'); ?></th>
                <td><input type="text" id="postratings_max" name="postratings_max" value="<?php echo $postratings_max; ?>" size="3" <?php if($postratings_customrating) { echo 'readonly="readonly"'; } ?> /></td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php esc_html_e('Enable Google Rich Snippets?', 'wp-postratings'); ?></th>
                <td>
                    <input type="radio" id="postratings_richsnippet_on" name="postratings_richsnippet" value="1" <?php if($postratings_options['richsnippet']) { echo 'checked="checked"'; } ?> />&nbsp;<?php esc_html_e('Yes', 'wp-postratings'); ?>
                    &nbsp;&nbsp;
                    <input type="radio" id="postratings_richsnippet_off" name="postratings_richsnippet" value="0" <?php if(!$postratings_options['richsnippet']) { echo 'checked="checked"'; } ?> />&nbsp;<?php esc_html_e('No', 'wp-postratings'); ?>
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center"><input type="button" name="update" value="<?php esc_attr_e('Update \'Individual Rating Text/Value\' Display', 'wp-postratings'); ?>" onclick="update_rating_text_value('<?php echo wp_create_nonce('wp-postratings_option_update_individual_rating')?>');" class="button" /><br /><img id="postratings_loading" src="<?php echo $postratings_url; ?>/loading.gif" alt="" style="display: none;" /></td>
            </tr>
        </table>
        <h2><?php esc_html_e('Individual Rating Text/Value', 'wp-postratings'); ?></h2>
        <div id="rating_text_value">
            <table class="form-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Rating Image', 'wp-postratings'); ?></th>
                        <th><?php esc_html_e('Rating Text', 'wp-postratings'); ?></th>
                        <th><?php esc_html_e('Rating Value', 'wp-postratings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        for($i = 1; $i <= $postratings_max; $i++) {
                            echo '<tr>'."\n";
                            echo '<td>'."\n";
                            if(is_rtl() && file_exists($postratings_path.'/'.$postratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT.'" alt="rating_start-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            } elseif(file_exists($postratings_path.'/'.$postratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_start.'.RATINGS_IMG_EXT.'" alt="rating_start.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            }
                            if($postratings_customrating) {
                                if($postratings_max == 2) {
                                    echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT.'" alt="rating_'.$i.'_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                } else {
                                    for($j = 1; $j < ($i+1); $j++) {
                                        echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_'.$j.'_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                    }
                                }
                            } else {
                                for($j = 1; $j < ($i+1); $j++) {
                                    echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                                }
                            }
                            if(is_rtl() && file_exists($postratings_path.'/'.$postratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT.'" alt="rating_end-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            } elseif(file_exists($postratings_path.'/'.$postratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
                                echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_end.'.RATINGS_IMG_EXT.'" alt="rating_end.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
                            }
                            echo '</td>'."\n";
                            echo '<td>'."\n";
                            echo '<input type="text" id="postratings_ratingstext_'.$i.'" name="postratings_ratingstext[]" value="'.esc_attr(stripslashes($postratings_ratingstext[$i-1])).'" size="20" maxlength="50" />'."\n";
                            echo '</td>'."\n";
                            echo '<td>'."\n";
                            echo '<input type="text" id="postratings_ratingsvalue_'.$i.'" name="postratings_ratingsvalue[]" value="';
                            if($postratings_ratingsvalue[$i-1] > 0 && $postratings_customrating) {
                                echo '+';
                            }
                            echo intval($postratings_ratingsvalue[$i-1]).'" size="3" maxlength="5" />'."\n";
                            echo '</td>'."\n";
                            echo '</tr>'."\n";
                        }
                    ?>
                </tbody>
            </table>
        </div>
        <?php $postratings_ajax_style = get_option('postratings_ajax_style'); ?>
        <h2><?php esc_html_e('Ratings AJAX Style', 'wp-postratings'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row" valign="top"><?php esc_html_e('Show Loading Image With Text', 'wp-postratings'); ?></th>
                <td>
                    <select name="postratings_ajax_style_loading" size="1">
                        <option value="0"<?php selected('0', $postratings_ajax_style['loading']); ?>><?php esc_html_e('No', 'wp-postratings'); ?></option>
                        <option value="1"<?php selected('1', $postratings_ajax_style['loading']); ?>><?php esc_html_e('Yes', 'wp-postratings'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><?php esc_html_e('Show Fading In And Fading Out Of Ratings', 'wp-postratings'); ?></th>
                <td>
                    <select name="postratings_ajax_style_fading" size="1">
                        <option value="0"<?php selected('0', $postratings_ajax_style['fading']); ?>><?php esc_html_e('No', 'wp-postratings'); ?></option>
                        <option value="1"<?php selected('1', $postratings_ajax_style['fading']); ?>><?php esc_html_e('Yes', 'wp-postratings'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <h2><?php esc_html_e('Allow To Rate', 'wp-postratings'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row" valign="top"><?php esc_html_e('Who Is Allowed To Rate?', 'wp-postratings'); ?></th>
                <td>
                    <select name="postratings_allowtorate" size="1">
                        <option value="0"<?php selected('0', get_option('postratings_allowtorate')); ?>><?php esc_html_e('Guests Only', 'wp-postratings'); ?></option>
                        <option value="1"<?php selected('1', get_option('postratings_allowtorate')); ?>><?php esc_html_e('Logged-in Users Only', 'wp-postratings'); ?></option>
                        <?php if ( is_multisite() ) : ?>
                            <option value="3"<?php selected('3', get_option('postratings_allowtorate')); ?>><?php esc_html_e('Users Registered On Blog Only', 'wp-postratings'); ?></option>
                        <?php endif; ?>
                        <option value="2"<?php selected('2', get_option('postratings_allowtorate')); ?>><?php esc_html_e('Logged-in Users And Guests', 'wp-postratings'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <h2><?php esc_html_e('Logging Method', 'wp-postratings'); ?></h2>
        <table class="form-table">
             <tr>
                <th scope="row" valign="top"><?php esc_html_e('Ratings Logging Method:', 'wp-postratings'); ?></th>
                <td>
                    <select name="postratings_logging_method" size="1">
                        <option value="0"<?php selected('0', get_option('postratings_logging_method')); ?>><?php esc_html_e('Do Not Log', 'wp-postratings'); ?></option>
                        <option value="1"<?php selected('1', get_option('postratings_logging_method')); ?>><?php esc_html_e('Logged By Cookie', 'wp-postratings'); ?></option>
                        <option value="2"<?php selected('2', get_option('postratings_logging_method')); ?>><?php esc_html_e('Logged By IP', 'wp-postratings'); ?></option>
                        <option value="3"<?php selected('3', get_option('postratings_logging_method')); ?>><?php esc_html_e('Logged By Cookie And IP', 'wp-postratings'); ?></option>
                        <option value="4"<?php selected('4', get_option('postratings_logging_method')); ?>><?php esc_html_e('Logged By Username', 'wp-postratings'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_html_e('Save Changes', 'wp-postratings'); ?>" />
        </p>
    </form>
</div>
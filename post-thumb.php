<?php
/*
Plugin Name: Post Thumb
Plugin URI: http://github.com/ryomatsu/post-thumb
Description: Thumbnail an image from own server from your post. Useful for listing popular posts, post list, etc.
Version: 1.0
Author: Ryo Matsufuji
Author URI: http://loumo.jp/

	Copyright (c) 2006 Victor Chang (http://theblemish.com)
	Post Thumbs is released under the GNU General Public License (GPL)
	http://www.gnu.org/licenses/gpl.txt

    forked by Ryo Matsufuji at 08-31-2012
	Copyright (c) 2012 Ryo Matsufuji (http://loumo.jp)
	Post Thumb is released under the GNU General Public License (GPL)
	http://www.gnu.org/licenses/gpl.txt

	This is a WordPress 3 plugin (http://wordpress.org).

*/

require_once('post-thumb-image-editor.php');

$data = array(	'domain_name' => '',
				'default_image' => '',
				'full_domain_name' => '',
				'base_path' => '',
				'folder_name' => '.pthumbs',
				'append' => 'false',
				'append_text' => '.',
				'resize_width' => '60',
				'resize_height' => '60',
				'keep_ratio' => 'true',
				'crop_exact' => 'true',
				'video_default' => '',
				'video_regex' => ''
				);
				
				
add_option('post_thumbnail_settings',$data,'Post Thumbnail Options');

function tb_post_thumb($generate=false, $alt_text='', $resize_width = 0, $resize_height = 0, $crop_x = 0, $crop_y = 0, $entry=null) {
	
	global $post;
    if ($entry) {
        $post = $entry;
    }
	$settings = get_option('post_thumbnail_settings');
    if ($resize_width) {
        $settings['resize_width'] = $resize_width;
    }
    if ($resize_height) {
        $settings['resize_height'] = $resize_height;
    }
	
	// find an image from your domain
	if (preg_match('/<img (.*?)src="https?:\/\/(www\.)?'.str_replace('/','\/',$settings['domain_name']).'\/(.*?)"/i',$post->post_content,$matches)) {
		// put matches into recognizable vars
		// fix later, assumes document root will match url structure
		$the_image = $settings['base_path'] . '/' . $matches[3];
		
		// check if image exists on server
		// if doesn't exist, can't do anything so return default image
		if (!file_exists($the_image)) {
			return tb_post_thumb_gen_image	(
												str_replace($settings['full_domain_name'],$settings['base_path'],$settings['default_image']),
												$settings['default_image'],
												$alt_text,
												$generate
											);
		}
		$dest_path = pathinfo($the_image);
		
		// dir to save thumbnail to
		$save_dir = $dest_path['dirname']."/{$settings['folder_name']}";

		// name to save to
        $filename_suffix = $resize_width .'x'.$resize_height.'-'.$crop_x.'-'.$crop_y;
        $filename = substr($dest_path['basename'], 0, strrpos($dest_path['basename'], "."));
		if ($settings['append'] == 'true') {
			$rename_to = $filename.$settings['append_text'].'-'.$filename_suffix.'.'.$dest_path['extension'];
		}
		else $rename_to = $settings['append_text'].$filename.'-'.$filename_suffix.'.'.$dest_path['extension'];

		// check if file already exists
		// return location if does
		if (file_exists($save_dir.'/'.$rename_to)) {
			$imagelocation = str_replace($settings['base_path'],$settings['full_domain_name'],$save_dir.'/'.$rename_to);
			return tb_post_thumb_gen_image	(
												$save_dir.'/'.$rename_to,
												$imagelocation,
												$alt_text,
												$generate
											);
		}
		
		// sticky bit?
		if (!is_dir($save_dir)) mkdir($save_dir,0777);
		// manipulate thumbnails
		$thumb = new ImageEditor($dest_path['basename'],$dest_path['dirname'].'/');
		$thumb->resize($settings['resize_width'], $settings['resize_height'], $settings['keep_ratio']);

		if ($settings['crop_exact'] == 'true' || ($crop_x != 0 && $crop_y != 0)) {
			if ($crop_x != 0 && $crop_y != 0) {
				$settings['resize_width'] = $crop_x;
				$settings['resize_height'] = $crop_y;
			}
			if ($thumb->x > $settings['resize_width'] || $thumb->y > $settings['resize_height']) {
				$thumb->crop(	(int)(($thumb->x - $settings['resize_width']) / 2),
								0,
								$settings['resize_width'],
								$settings['resize_height']
							);
			}
		}
		$thumb->outputFile($save_dir."/".$rename_to, "");
		chmod($save_dir."/".$rename_to,0666);
		$imagelocation = str_replace($settings['base_path'],$settings['full_domain_name'],$save_dir.'/'.$rename_to);
		return tb_post_thumb_gen_image	(
												$save_dir.'/'.$rename_to,
												$imagelocation,
												$alt_text,
												$generate
											);
	} else {
		if (!empty($settings['video_regex']) && tb_post_thumb_check_video($settings['video_regex'])) {
				$settings['default_image'] = $settings['video_default'];
		}
		return tb_post_thumb_gen_image	(
											str_replace($settings['full_domain_name'],$settings['base_path'],$settings['default_image']),
											$settings['default_image'],
											$alt_text,
											$generate
										);
	}
}

function tb_post_thumb_gen_image($server_image,$site_image,$alt='',$fullhtml) {
	if ($fullhtml) {
		list($width, $height, $type, $attr) = getimagesize($server_image);
		return '<img src="'.$site_image.'" '.$attr.' alt="'.$alt.'" />';
	} else {
		return $site_image;
	}
}

function tb_post_thumb_check_video($regex) {
	global $post;
	if (preg_match('/'.$regex.'/i',$post->post_content,$matches)) return true;
	else return false;
}

function tb_post_thumb_options() {
    if (function_exists('add_options_page')) {
		add_options_page('Post Thumbnails', 'Post Thumbs', 8, basename(__FILE__), 'tb_post_thumb_options_subpanel');
    }
}

function tb_post_thumb_options_subpanel() {
  	if (isset($_POST['info_update']) == 'Update') {
	  	if ($_POST['crop_exact'] == 'on') $_POST['crop_exact'] = 'true';
	  	else $_POST['crop_exact'] = 'false';
	  	if ($_POST['append'] == 'on') $_POST['append'] = 'true';
	  	else $_POST['append'] = 'false';
	  	if ($_POST['keep_ratio'] == 'on') $_POST['keep_ratio'] = 'true';
	  	else $_POST['keep_ratio'] = 'false';
	  	if (get_magic_quotes_gpc()) $_POST['video_regex'] = stripslashes($_POST['video_regex']);
	  	$new_options = array(	'domain_name' => $_POST['domain_name'],
								'default_image' => $_POST['default_image'],
								'full_domain_name' => $_POST['full_domain_name'],
								'base_path' => $_POST['base_path'],
								'folder_name' => $_POST['folder_name'],
								'append' => $_POST['append'],
								'append_text' => $_POST['append_text'],
								'resize_width' => $_POST['width'],
								'resize_height' => $_POST['height'],
								'keep_ratio' => $_POST['keep_ratio'],
								'crop_exact' => $_POST['crop_exact'],
								'video_regex' => $_POST['video_regex'],
								'video_default' => $_POST['video_default'],
							);
		if (!is_numeric($new_options['resize_width'])) {
			$update_error = "Resize width must be a number";
			$new_options['resize_width'] = '60';
		} else if (!is_numeric($new_options['resize_height'])) {
			$update_error = "Resize height must be a number";
			$new_options['resize_height'] = '60';
		}
		update_option('post_thumbnail_settings',$new_options);
	    ?><div class="updated">
	    	<?php if (!empty($update_error)) : ?>
	    	<strong>Update error:</strong> <?php echo $update_error; ?>
	    	<?php else : ?>
	    	<strong>Settings saved</strong>
	    	<?php endif; ?>
	    </div><?php
	} 
	$post_thumbnail_settings = get_option('post_thumbnail_settings');
	?>
<div class=wrap>
<form method="post">
		<h2>Post Thumbnail Options</h2>
		<fieldset name="options">
			<table cellpadding="3" cellspacing="0" width="100%">
				<tr>
					<td colspan="2" bgcolor="#dddddd">
						<strong>Location settings</strong>
					</td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#f6f6f6">
					<p><strong>Domain name:</strong> Type in yourdomain.com without the http:// for the domain name. It is used to find the image in the post that is from your own site.</p>
					<p><strong>Default image:</strong> The location of the default image to use if no picture can be found. Enter in the exact url, eg. http://example.com/default.jpg</p>
					<p><strong>Full domain name:</strong> Full domain name. Includes the http://. Used for the url to image.</p>
					<p><strong>Base path:</strong> Absolute path to website. For example, /httpdocs or /yourdomain.com. Used to find location of image on server. http://yourdomain.com/images/picture.jpg would actually be /yourdomain.com/images/picture.jpg.</p>
					</td>
				</tr>
				<tr>
					<td><strong>Domain name</strong></td>
					<td><input type="text" name="domain_name" value="<?php echo $post_thumbnail_settings['domain_name']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Default image</strong></td>
					<td><input type="text" name="default_image" value="<?php echo $post_thumbnail_settings['default_image']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Full domain name</strong></td>
					<td><input type="text" name="full_domain_name" value="<?php echo $post_thumbnail_settings['full_domain_name']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Base path</strong></td>
					<td><input type="text" name="base_path" value="<?php echo $post_thumbnail_settings['base_path']; ?>" /></td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#dddddd">
						<strong>Video image settings</strong>
					</td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#f6f6f6">
					<p>If you want to scan a post for a video and use a default image. Uses regex to scan for video.</p>
					</td>
				</tr>
				<tr>
					<td><strong>Video regex</strong></td>
					<td><input type="text" name="video_regex" value="<?php echo htmlentities($post_thumbnail_settings['video_regex']); ?>" /></td>
				</tr>
				<tr>
					<td><strong>Video default image:</strong></td>
					<td><input type="text" name="video_default" value="<?php echo $post_thumbnail_settings['video_default']; ?>" /></td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#dddddd">
						<strong>Filename settings</strong>
					</td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#f6f6f6">
					<p>Set the name of the folder. Folder will always be in the directory the image is in. Make sure directory is writable.</p>
					<p>Choose to put text before image name or after. Unchecking will put text before.</p>
					<p>Choose text to append or prepend image with. Example: pthumb.yourimage.jpg</p>
					</td>
				</tr>
				<tr>
					<td><strong>Folder name</strong></td>
					<td><input type="text" name="folder_name" value="<?php echo $post_thumbnail_settings['folder_name']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Append</strong></td>
					<td><input type="checkbox" name="append" <?php if ($post_thumbnail_settings['append'] == 'true') echo 'checked'; ?> /></td>
				</tr>
				<tr>
					<td><strong>Append / Prepend text</strong></td>
					<td><input type="text" name="append_text" value="<?php echo $post_thumbnail_settings['append_text']; ?>" /></td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#dddddd">
						<strong>Image settings</strong>
					</td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#f6f6f6">
					<p>Choose your resize width and height. Will resize in proportion to original width and height. If you don't care about proportions, uncheck keep ratio.</p>
					</td>
				</tr>
				<tr>
					<td><strong>Resize width</strong></td>
					<td><input type="text" name="width" value="<?php echo $post_thumbnail_settings['resize_width']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Resize height</strong></td>
					<td><input type="text" name="height" value="<?php echo $post_thumbnail_settings['resize_height']; ?>" /></td>
				</tr>
				<tr>
					<td><strong>Keep ratio?</strong></td>
					<td><input type="checkbox" name="keep_ratio" <?php if ($post_thumbnail_settings['keep_ratio'] == 'true') echo 'checked'; ?> /></td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#dddddd">
						<strong>Crop settings</strong>
					</td>
				</tr>
				<tr>
					<td colspan="2" bgcolor="#f6f6f6">
					<p>Decide if you want to crop the image to the exact widh and height.</p>
					</td>
				</tr>
				<tr>
					<td><strong>Crop exact?</strong></td>
					<td><input type="checkbox" name="crop_exact" <?php if ($post_thumbnail_settings['crop_exact'] == 'true') echo 'checked'; ?> /></td>
				</tr>
			</table>
		</fieldset>
<div class="submit">
  <input type="submit" name="info_update" value="Update" /></div>
  </form>
 </div><?php
}

add_action('admin_menu', 'tb_post_thumb_options');

?>

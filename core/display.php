<?php
/**
*
* @package Reputation System
* @copyright (c) 2013 Pico88
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace pico88\reputation\core;

class display
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**  @ reputation helper */
	protected $reputation_helper;

	/**
	* Constants for avatar dimensions (width and height)
	* small = 40px
	* medium = 60 px
	*/
	const SMALL = 40;
	const MEDIUM = 60;

	/**
	* Constructor
	* NOTE: The parameters of this method must match in order and type with
	* the dependencies defined in the services.yml file for this service.
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\driver\driver_interface $cache, \phpbb\config\config $config, \phpbb\db\driver\driver $db, \phpbb\template\template $template, \phpbb\user $user, $phpbb_root_path, $php_ext, $reputation_helper)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->reputation_helper = $reputation_helper;
	}

	/**
	* @param $points Rating points
	* @return string String value of CSS class for voting placeholder
	*/
	static function vote_class($points)
	{
		if ($points > 0) 
		{
			return 'positive';
		}
		else if ($points < 0) 
		{
			return 'negative';
		}
		else
		{
			return 'neutral';
		}
	}

	/**
	*
	*/
	static private function resize_avatar($is_ajax)
	{
		if ($is_ajax)
		{
			return self::SMALL;
		}
		else
		{
			return self::MEDIUM;
		}
	}

	/**
	*
	*/
	public function row($row, $is_ajax = false)
	{
		// Path to images folder
		$images_path = 'ext/pico88/reputation/images/';

		$row['bbcode_options'] = OPTION_FLAG_BBCODE + OPTION_FLAG_SMILIES + OPTION_FLAG_LINKS;
		$comment = (!empty($row['comment'])) ? generate_text_for_display($row['comment'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']) : false;

		// Display avatar if it is enabled
		$avatar_dimensions = $this->resize_avatar($is_ajax);
		$avatar_img = $row['user_avatar'] ? get_user_avatar($row['user_avatar'], $row['user_avatar_type'], ($row['user_avatar_width'] > $row['user_avatar_height']) ? $avatar_dimensions : ($avatar_dimensions / $row['user_avatar_height']) * $row['user_avatar_width'], ($row['user_avatar_height'] > $row['user_avatar_width']) ? $avatar_dimensions : ($avatar_dimensions / $row['user_avatar_width']) * $row['user_avatar_height']) : '<img src="' . $this->reputation_helper->path($images_path, $is_ajax) . 'no_avatar.gif" width="' . $avatar_dimensions . 'px;" height="' . $avatar_dimensions . 'px;" alt="" />';

		// Generate post url if it is needed
		$post_link = (isset($row['real_post_id'])) ? $this->generate_post_link($row['real_post_id'], $row['forum_id'], $row['post_subject']) : '';
		$go_to_post = '';
		if ($row['action'] == 1)
		{
			$action = $this->user->lang['RS_POST_RATING'];
			$go_to_post = $post_link;
		}
		else if ($row['action'] == 2)
		{
			$action = $this->user->lang['RS_USER_RATING'];
		}
		else if ($row['action'] == 3)
		{
			$action = $this->user->lang['RS_ONLYPOST_RATING'];
			$go_to_post = $post_link;
		}

		if ($row['point'] < 0)
		{
			$point_img = '<img src="' . $this->reputation_helper->path($images_path, $is_ajax) . 'neg.png" alt="" title="' . $this->user->lang('RS_POINTS_TITLE', $row['point']) . '" />';
			$point_class = 'negative';
		}

		if ($row['point'] > 0)
		{
			$point_img = '<img src="' . $this->reputation_helper->path($images_path, $is_ajax) . 'pos.png" alt="" title="' . $this->user->lang('RS_POINTS_TITLE', $row['point']) . '" />';
			$point_class = 'positive';
		}

		$can_delete = ($this->auth->acl_get('m_rs_moderate') || ($row['rep_from'] == $this->user->data['user_id'] && $this->auth->acl_get('u_rs_delete'))) ? true : false;

		$this->template->assign_block_vars('reputation', array(
			'REP_ID'			=> $row['rep_id'],
			'USERNAME'			=> get_username_string('full', $row['rep_from'], $row['username'], $row['user_colour']),
			'ACTION'			=> $action,
			'AVATAR_IMG'		=> $avatar_img,
			'TIME'				=> $this->user->format_date($row['time']),
			'COMMENT'			=> $comment,
			'POINT_VALUE'		=> $this->config['rs_point_type'] ? $point_img : $row['point'],
			'POINT_CLASS'		=> $this->config['rs_point_type'] ? '' : $point_class,

			'U_GO_TO_POST'		=> $go_to_post,
			'U_DELETE'			=> $this->reputation_helper->generate_url('reputation/delete/' . $row['rep_id'], $is_ajax),

			'S_DELETE'			=> $can_delete,
		));
	}

	/**
	*
	*/
	public function table_row($row, $module = 'ucp')
	{
		$row['bbcode_options'] = OPTION_FLAG_BBCODE + OPTION_FLAG_SMILIES + OPTION_FLAG_LINKS;
		$comment = (!empty($row['comment'])) ? generate_text_for_display($row['comment'], $row['bbcode_uid'], $row['bbcode_bitfield'], $row['bbcode_options']) : $this->user->lang['RS_NA'];

		// Generate post url if it is needed
		$post_link = $this->generate_post_link($row['real_post_id'], $row['forum_id'], $row['post_subject']);

		$go_to_post = '';
		if ($row['action'] == 1)
		{
			$action = $this->user->lang['RS_POST_RATING'];
			$go_to_post = $post_link;
		}
		else if ($row['action'] == 2)
		{
			$action = $this->user->lang['RS_USER_RATING'];
		}
		else if ($row['action'] == 3)
		{
			$action = $this->user->lang['RS_ONLYPOST_RATING'];
			$go_to_post = $post_link;
		}

		if ($row['point'] < 0)
		{
			$point_img = '<img src="' . $this->phpbb_root_path . 'ext/pico88/reputation/images/neg.png" alt="" title="' . $this->user->lang('RS_POINTS', $row['point']) . '" />';
			$point_class = 'negative';
		}

		if ($row['point'] > 0)
		{
			$point_img = '<img src="' . $this->phpbb_root_path . 'ext/pico88/reputation/images/pos.png" alt="" title="' . $this->user->lang('RS_POINTS', $row['point']) . '" />';
			$point_class = 'positive';
		}

		if ($module == 'ucp')
		{
			$template_block_vars = array(
				'USERNAME'		=> get_username_string('full', $row['rep_to'], $row['username'], $row['user_colour']),
			);
		}
		else
		{
			$template_block_vars = array(
				'USERNAME_FROM'		=> get_username_string('full', $row['rep_from'], $row['username_rep_from'], $row['user_colour_rep_from']),
				'USERNAME_TO'		=> get_username_string('full', $row['rep_to'], $row['username_rep_to'], $row['user_colour_rep_to']),
			);
		}

		$template_block_vars = array_merge($template_block_vars, array(
			'REP_ID'			=> $row['rep_id'],
			'ACTION'			=> $action,
			'TIME'				=> $this->user->format_date($row['time']),
			'COMMENT' 			=> $comment,
			'POINT_VALUE'		=> $this->config['rs_point_type'] ? $point_img : $row['point'],
			'POINT_CLASS'		=> $this->config['rs_point_type'] ? 'neutral' : $point_class,

			'U_POST'			=> $go_to_post,
		));

		$this->template->assign_block_vars('reputation', $template_block_vars);
	}

	/**
	*
	*/
	private function generate_post_link($post_id, $forum_id, $post_subject)
	{
		// Post was deleted
		if (!isset($post_subject) && !isset($post_id))
		{
			return '<strong>' . $this->user->lang['RS_POST_DELETE'] . '</strong>';
		}

		// Post exists
		if (isset($post_id))
		{
			// Check forum read permission
			if (!$this->auth->acl_get('f_read', $forum_id))
			{
				return;
			}

			// Prepare post url
			$post_url = append_sid("{$this->phpbb_root_path}viewtopic.$this->php_ext", 'f=' . $forum_id . '&amp;p=' . $post_id . '#p' . $post_id);
			$post_link = '<a href="' . $post_url . '">' . $post_subject . ' [#p' . $post_id . ']</a>';

			return $post_link;
		}
	}
}
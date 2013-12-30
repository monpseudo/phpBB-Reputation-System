<?php
/**
*
* @package Reputation System
* @copyright (c) 2013 Pico88
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace pico88\reputation\controller;

class delete
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $controller_helper;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @ var string */
	protected $reputation_display;

	/** @ reputation manager */
	protected $reputation_manager;

	/** @var string */
	protected $reputations_table;

	/** @var bool*/
	private $is_ajax;

	/**
	* Constructor
	* 
	* @param \phpbb\auth\auth $auth
	* @param \phpbb\config\config $config
	* @param \phpbb\db\driver\driver $db
	* @param \phpbb\request\request $request
	* @param \phpbb\user $user
	* @param string $reputation_table Name of the table uses to store reputations
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver $db, \phpbb\request\request $request, \phpbb\user $user, $reputation_display, $reputation_manager, $reputations_table)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->user = $user;
		$this->reputation_display = $reputation_display;
		$this->reputation_manager = $reputation_manager;
		$this->reputations_table = $reputations_table;

		$this->is_ajax = $this->request->is_ajax();

		$this->user->add_lang_ext('pico88/reputation', 'reputation_system');

		if (!$this->config['rs_enable'])
		{
			$json_data = array(
				'error_msg' => $this->user->lang['RS_DISABLED']
			);
			$this->reputation_manager->reputation_response($json_data, $this->is_ajax);
		}
	}

	/**
	* Delete controller to be accessed with the URL /reputation/delete/{{rep_id}
	* (where {rep_id} is the placeholder for a value)
	*
	* @param int    $rep_id    Reputation ID taken from the URL
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function delete($rep_id)
	{
		$sql_array = array(
			'SELECT'	=> 'r.rep_from, r.rep_to, r.post_id, u.username, u.user_colour, p.post_username',
			'FROM'		=> array(
				$this->reputations_table => 'r',
				USERS_TABLE => 'u'
			),
			'LEFT_JOIN' => array(
				array(
					'FROM'	=> array(POSTS_TABLE => 'p'),
					'ON'	=> 'r.post_id = p.post_id',
				),
			),
			'WHERE'		=> 'r.rep_id = ' . $rep_id . '
				AND r.rep_to = u.user_id',
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		//We couldn't find this reputation. May be it was deleted meanwhile?
		if (!$row)
		{
			$json_data = array(
				'error_msg' => $this->user->lang['RS_NO_REPUTATION']
			);
			$this->reputation_manager->reputation_response($json_data, $this->is_ajax);
		}

		if ($this->request->is_set_post('cancel'))
		{
			exit;
		}

		if ($this->auth->acl_gets('m_rs_moderate') || ($row['rep_from'] == $this->user->data['user_id'] && $this->auth->acl_get('u_rs_delete')))
		{
			if ($this->is_ajax)
			{
				$this->reputation_manager->delete($rep_id);

				$user_reputation = $this->reputation_manager->get_user_reputation($row['rep_to']);
				$post_reputation = $this->reputation_manager->get_post_reputation($row['post_id']);

				$json_data = array(
					'post_id'				=> $row['post_id'],
					'poster_id'				=> $row['rep_to'],
					'rep_id'				=> $rep_id,
					'user_reputation'		=> '<strong>' . $user_reputation . '</strong>',
					'post_reputation'		=> $post_reputation,
					'own_vote'				=> ($row['rep_from'] == $this->user->data['user_id']) ? true : false,
					'reputation_class'		=> $this->reputation_display->vote_class($post_reputation),
				);
			}
			else
			{
				$s_hidden_fields = build_hidden_fields(array(
					'u'		=> $row['rep_to'],
				));

				if (confirm_box(true))
				{
					$this->reputation_manager->delete($rep_id);

					$json_data = array(
						'error_msg' => $this->user->lang('RS_POINTS_DELETED')
					);
				}
				else
				{
					confirm_box(false, $this->user->lang('RS_DELETE_POINTS_CONFIRM'), $s_hidden_fields);
				}
			}
		}
		else
		{
			$json_data = array(
				'error_msg' => $this->user->lang['RS_USER_CANNOT_DELETE']
			);
		}
		$this->reputation_manager->reputation_response($json_data, $this->is_ajax);
	}

	/**
	* clear controller to be accessed with the URL /reputation/clear/{mode}/{item_id}
	* (where {item_id} is the placeholder for a value)
	*
	* @param strng  $mode      Mode taken from the URL
	* @param int    $item_id   Post or User ID taken from the URL
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function clear($mode, $item_id)
	{
		if (!$this->auth->acl_gets('m_rs_moderate'))
		{
			$json_data = array(
				'error_msg' => $this->user->lang['RS_USER_CANNOT_DELETE']
			);
			$this->reputation_manager->reputation_response($json_data, $this->is_ajax);
		}

		switch ($mode)
		{
			case 'post':
				if ($this->request->is_set_post('cancel'))
				{
					exit;
				}

				if ($this->is_ajax)
				{
					$sql = 'SELECT rep_to, post_id
						FROM ' . $this->reputations_table . "
						WHERE post_id = $item_id";
					$result = $this->db->sql_query($sql);
					$row = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					$this->reputation_manager->clear_reputation('post', $item_id);

					$user_reputation = $this->reputation_manager->get_user_reputation($row['rep_to']);

					$json_data = array(
						'clear_post'			=> true,
						'post_id'				=> $item_id,
						'poster_id'				=> $row['rep_to'],
						'user_reputation'		=> $user_reputation,
						'post_reputation'		=> 0,
						'reputation_class'		=> 'neutral',
					);
				}
				else
				{
					$s_hidden_fields = build_hidden_fields(array(
						'p'		=> $item_id,
					));

					if (confirm_box(true))
					{
						$this->reputation_manager->clear_reputation('post', $item_id);

						$json_data = array(
							'error_msg' => $this->user->lang['RS_CLEARED_POST']
						);
					}
					else
					{
						confirm_box(false, $this->user->lang('RS_CLEAR_POST_CONFIRM'), $s_hidden_fields);
					}
				}
			break;

			case 'user':
				if ($this->request->is_set_post('cancel'))
				{
					exit;
				}

				$post_ids = array();

				$sql = 'SELECT post_id
					FROM ' . $this->reputations_table . "
					WHERE rep_to = $item_id
					GROUP BY post_id";
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					$post_ids[] = $row['post_id'];
				}
				$this->db->sql_freeresult($result);

				if ($this->is_ajax)
				{
					$this->reputation_manager->clear_reputation('user', $item_id, $post_ids);

					$json_data = array(
						'clear_user'			=> true,
						'post_ids'				=> $post_ids,
						'poster_id'				=> $item_id,
						'user_reputation'		=> 0,
						'post_reputation'		=> 0,
						'reputation_class'		=> 'neutral',
					);
				}
				else
				{
					$s_hidden_fields = build_hidden_fields(array(
						'u'		=> $item_id,
					));

					if (confirm_box(true))
					{
						$this->reputation_manager->clear_reputation('post', $item_id);

						$json_data = array(
							'error_msg' => $this->user->lang['RS_CLEARED_USER']
						);
					}
					else
					{
						confirm_box(false, $this->user->lang('RS_CLEAR_USER_CONFIRM'), $s_hidden_fields);
					}
				}
			break;
		}

		$this->reputation_manager->reputation_response($json_data, $this->is_ajax);
	}
}
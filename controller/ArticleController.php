<?php
class ArticleController extends Controller
{
	public function listAction($query_params)
	{
		if (empty($query_params) || !is_array($query_params))
		{
			header("Location: /index/notfound");
			return;
		}

		$params = $this->getArticle(intval($query_params[0]));
		if (!TechlogTools::isMobile())
			$this->display(__METHOD__, $params);
		else
			$this->display(__CLASS__.'::mobileAction', $params);
	}

	public function testAction($query_params)
	{
		if (!$this->is_root)
		{
			header("Location: /index/notfound");
			return;
		}

		$draft_id = (isset($query_params[0]) && intval($query_params[0]) > 0)
			? intval($query_params[0]) : '';
		$contents = file_exists(DRAFT_PATH.'/draft'.$draft_id.'.tpl')
			? TechlogTools::pre_treat_article(
				file_get_contents(DRAFT_PATH.'/draft'.$draft_id.'.tpl')
			) : '';

		if (StringOpt::spider_string(
			$contents, '"page-header"', '</div>') === null)
		{
			$contents = '<div class="page-header"><h1>草稿'
				.$draft_id.'</h1></div>'.$contents;
		}

		$index = TechlogTools::get_index($contents);

		$params = array(
			'tags'	=> array(),
			'title'	=> '测试页面',
			'contents'	=> $contents,
			'inserttime'	=> '',
			'title_desc'	=> '仅供测试',
			'article_category_id'	=> 3,
		);
		if (count($index) > 0)
			$params['indexs'] = $index;

		if (!TechlogTools::isMobile())
			$this->display(__CLASS__.'::listAction', $params);
		else
			$this->display(__CLASS__.'::mobileAction', $params);
	}

	public function commentActionAjax()
	{
		$input = $_POST;
		if (!isset($input['article_id'])
			|| intval($input['article_id']) == 0
			|| !isset($input['qq'])
			|| !isset($input['nickname'])
			|| !isset($input['replyfloor'])
			|| !isset($input['reply'])
			|| !isset($input['email'])
			|| !isset($input['content']))
		{
			$result = array('code' => '-1',
				'msg' => '请勿攻击接口，否则封禁 IP，谢谢');
			return $result;
		}

		if (!$this->is_root
			&& ($input['email'] == 'zeyu203@qq.com'
				|| $input['nickname'] == '博主'))
		{
			$result = array('code' => '-1',
				'msg' => '请勿冒充博主');
			return $result;
		}

		$params = array('eq' => array('article_id' => $input['article_id']));
		$article = Repository::findOneFromArticle($params);
		if ($article == false)
		{
			$result = array('code' => '-1',
				'msg' => '评论失败，请刷新后重新评论，谢谢');
			return $result;
		}
		$infos = $input;
		$infos['ip'] = $_SERVER["REMOTE_ADDR"];
		$infos['floor'] = $article->get_comment_count() + 1;
		$infos['inserttime'] = date('Y-m-d H:i:s', time());
		if ($input['reply'])
			$infos['reply'] = $input['replyfloor'];
		$comment = new CommentModel($infos);
		Repository::persist($comment);
		$article->set_comment_count($article->get_comment_count() + 1);
		Repository::persist($article);

		$result = array('code' => 0, 'msg' => '评论成功');
		return $result;
	}

	private function getArticle($article_id)
	{
		$params = array();

		$params = array('eq' => array('article_id' => $article_id));
		if (!$this->is_root)
			$params['lt'] = array('category_id' => 5);
		$article = Repository::findOneFromArticle($params);

		if ($article == false)
		{
			header("Location: /index/notfound");
			return;
		}

		$params['tags']		= SqlRepository::getTags($article_id);
		$params['title']	= $article->get_title();
		$params['indexs'] = json_decode($article->get_indexs());
		$params['contents'] =
			TechlogTools::pre_treat_article($article->get_draft());
		$params['title_desc']	= $article->get_title_desc();
		$params['article_category_id']	= $article->get_category_id();
		$params['comment_count'] = intval($article->get_comment_count());
		$params['comments'] = ($params['comment_count'] > 0 ?
			SqlRepository::getComments($article_id) : array());
		$params['article_id'] = $article->get_article_id();

		if (
			StringOpt::spider_string(
				$params['contents'],
				'"page-header"',
				'</div>'
			) === null
			&& !TechlogTools::isMobile()
		)
		{
			$params['contents'] = '<div class="page-header"><h1>'
				.$article->get_title()
				.'</h1></div>'.$params['contents'];
		}

		$article->set_access_count($article->get_access_count() + 1);
		Repository::persist($article);

		$params['inserttime'] = $article->get_inserttime()
			.'&nbsp;&nbsp;&nbsp;最后更新: '
			.$article->get_updatetime()
			.'&nbsp;&nbsp;&nbsp;访问数量：'
			.($article->get_access_count()+1);

		return $params;
	}
}
?>

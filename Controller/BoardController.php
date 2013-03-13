<?php

/*
 * This file is part of the CCDNForum ForumBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNForum\ForumBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 *
 * @author Reece Fowell <reece@codeconsortium.com>
 * @version 1.0
 */
class BoardController extends BaseController
{
    /**
     *
     * @access public
     * @param int $boardId, int $page
     * @return RedirectResponse|RenderResponse
     */
    public function showAction($boardId, $page)
    {
        $board = $this->container->get('ccdn_forum_forum.repository.board')->findOneByIdWithCategory($boardId);

        // check board exists.
		$this->isFound($board);

        if ($this->isGranted('ROLE_MODERATOR')) {
            $topicsPager = $this->container->get('ccdn_forum_forum.repository.topic')->findTopicsForBoardById($boardId, true);
            $stickyTopics = $this->container->get('ccdn_forum_forum.repository.topic')->findStickyTopicsForBoardById($boardId, true);
        } else {
            $topicsPager = $this->container->get('ccdn_forum_forum.repository.topic')->findTopicsForBoardById($boardId, false);
            $stickyTopics = $this->container->get('ccdn_forum_forum.repository.topic')->findStickyTopicsForBoardById($boardId, false);
        }

        // deal with pagination.
        $topicsPerPage = $this->container->getParameter('ccdn_forum_forum.board.show.topics_per_page');
        $topicsPager->setMaxPerPage($topicsPerPage);
        $topicsPager->setCurrentPage($page, false, true);

        // this is necessary for working out the last page for each topic.
        $postsPerPage = $this->container->getParameter('ccdn_forum_forum.topic.show.posts_per_page');

        // setup bread crumbs.
        $category = $board->getCategory();

        $crumbs = $this->getCrumbs()
            ->add($this->trans('ccdn_forum_forum.crumbs.forum_index'), $this->path('ccdn_forum_forum_category_index'), "home")
            ->add($category->getName(), $this->path('ccdn_forum_forum_category_show', array('categoryId' => $category->getId())), "category")
            ->add($board->getName(), $this->path('ccdn_forum_forum_board_show', array('boardId' => $boardId)), "board");

        return $this->renderResponse('CCDNForumForumBundle:Board:show.html.', array(
            'crumbs' => $crumbs,
            'board' => $board,
            'pager' => $topicsPager,
            'posts_per_page' => $postsPerPage,
            'sticky_topics' => $stickyTopics,
        ));
    }
}

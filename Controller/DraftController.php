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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use CCDNForum\ForumBundle\Entity\Topic;
use CCDNForum\ForumBundle\Entity\Draft;

/**
 *
 * @author Reece Fowell <reece@codeconsortium.com>
 * @version 1.0
 */
class DraftController extends BaseController
{

    /**
     *
     * @access public
     * @param int $page
     * @return RenderResponse
     */
    public function listAction($page)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $draftsPaginated = $this->container->get('ccdn_forum_forum.repository.draft')->findDraftsPaginated($user->getId());

        // deal with pagination.
        $draftsPerPage = $this->container->getParameter('ccdn_forum_forum.draft.list.drafts_per_page');
        $draftsPaginated->setMaxPerPage($draftsPerPage);
        $draftsPaginated->setCurrentPage($page, false, true);

        $crumbs = $this->getCrumbs()
            ->add($this->trans('ccdn_forum_forum.crumbs.drafts_index'), $this->path('ccdn_forum_forum_draft_list'), "home");

        return $this->renderResponse('CCDNForumForumBundle:Draft:list.html.', array(
            'crumbs' => $crumbs,
            'pager' => $draftsPaginated,
        ));
    }

    /**
     *
     * @access public
     * @param int $draftId
     * @return RedirectResponse
     */
    public function deleteAction($draftId)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $draft = $this->container->get('ccdn_forum_forum.repository.draft')->findOneByIdForUserById($draftId, $user->getId());

		$this->isFound($draft);

        $this->getDraftManager()->remove($draft)->flush();

        return new RedirectResponse($this->path('ccdn_forum_forum_draft_list'));
    }

    /**
     *
     * @access public
     * @param int $draftId
     * @return RedirectResponse
     */
    public function publishAction($draftId)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $draft = $this->container->get('ccdn_forum_forum.repository.draft')->findOneByIdForUserById($draftId, $user->getId());

		$this->isFound($draft);
		
        // is this a topic?
        if (is_object($draft->getTopic()) && $draft->getTopic() instanceof Topic) {
            if ($draft->getTopic()->getId()) {
                if ($draft->getBoard()) {
                    return new RedirectResponse($this->path('ccdn_forum_forum_topic_reply_from_draft', array('topicId' => $draft->getTopic()->getId(), 'draftId' => $draft->getId()) ));
                } else {
                    $this->setFlash('error', $this->trans('ccdn_forum_forum.flash.draft.topic_does_not_exist'));
                }
            } else {
                if ($draft->getBoard()) {
                    return new RedirectResponse($this->path('ccdn_forum_forum_topic_create_from_draft', array('boardId' => $draft->getBoard()->getId(), 'draftId' => $draft->getId()) ));
                } else {
                    $this->setFlash('error', $this->trans('ccdn_forum_forum.flash.draft.board_does_not_exist'));
                }
            }
        } else {
            if ($draft->getBoard()) {
                return new RedirectResponse($this->path('ccdn_forum_forum_topic_create_from_draft', array('boardId' => $draft->getBoard()->getId(), 'draftId' => $draft->getId()) ));
            } else {
                $this->setFlash('error', $this->trans('ccdn_forum_forum.flash.draft.board_does_not_exist'));
            }
        }

        return new RedirectResponse($this->path('ccdn_forum_forum_draft_list'));
    }
}

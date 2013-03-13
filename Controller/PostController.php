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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 *
 * @author Reece Fowell <reece@codeconsortium.com>
 * @version 1.0
 */
class PostController extends BaseController
{
    /**
     *
     * @access public
     * @param int $postId
     * @return RenderResponse
     */
    public function showAction($postId)
    {
        $user = $this->getUser();

        $post = $this->container->get('ccdn_forum_forum.repository.post')->find($postId);

		$this->isFound($post);

        // If this topics first post is deleted, and no other posts exist then throw an NotFoundHttpException!
        if (($post->getIsDeleted() || $post->getTopic()->getIsDeleted()) && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new NotFoundHttpException('No such post exists!');
        }

        // Get post counts for users.
        if ($post->getCreatedBy()) {
            $registryUserIds = array($post->getCreatedBy()->getId());
        } else {
            $registryUserIds = array();
        }

        $registries = $this->getRegistryManager()->getRegistriesForUsersAsArray($registryUserIds);

        // Get the topic subscriptions.
        if ($this->isGranted('ROLE_USER') && $post->getTopic()) {
            $subscription = $this->container->get('ccdn_forum_forum.repository.subscription')->findTopicSubscriptionByTopicAndUserId($post->getTopic()->getId(), $user->getId());
        } else {
            $subscription = null;
        }

        $subscriberCount = $this->container->get('ccdn_forum_forum.repository.subscription')->getSubscriberCountForTopicById($post->getTopic()->getId());

        // setup crumb trail.
        $topic = $post->getTopic();
        $board = $topic->getBoard();
        $category = $board->getCategory();

        $crumbs = $this->getCrumbs()
            ->add($this->trans('ccdn_forum_forum.crumbs.forum_index'), $this->path('ccdn_forum_forum_category_index'), "home")
            ->add($category->getName(), $this->path('ccdn_forum_forum_category_show', array('categoryId' => $category->getId())), "category")
            ->add($board->getName(), $this->path('ccdn_forum_forum_board_show', array('boardId' => $board->getId())), "board")
            ->add($topic->getTitle(), $this->path('ccdn_forum_forum_topic_show', array('topicId' => $topic->getId())), "communication")
            ->add('#' . $post->getId(), $this->path('ccdn_forum_forum_post_show', array('postId' => $post->getId())), "comment");

        return $this->renderResponse('CCDNForumForumBundle:Post:show.html.', array(
            'user'	=> $user,
            'crumbs' => $crumbs,
            'topic' => $topic,
            'post' => $post,
            'registries' => $registries,
            'subscription' => $subscription,
            'subscription_count' => $subscriberCount,
        ));
    }

    /**
     *
     * @access public
     * @param int $postId
     * @return RedirectResponse|RenderResponse
     */
    public function editAction($postId)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $post = $this->container->get('ccdn_forum_forum.repository.post')->findPostForEditing($postId);

        $this->isFound($post);

        // if this topics first post is deleted, and no other posts exist then throw an NotFoundHttpException!
        if (($post->getIsDeleted() || $post->getTopic()->getIsDeleted()) && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new NotFoundHttpException('No such post exists!');
        }

        // you cannot reply/edit/delete a post if it is locked
        if ($post->getIsLocked() && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException('This post has been locked and cannot be edited or deleted!');
        }
		
        // you cannot reply/edit/delete a post if the topic is closed
        if ($post->getTopic()->getIsClosed() && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException('This topic has been closed!');
        }

        // Invalidate this action / redirect if user should not have access to it
        if ( ! $this->isGranted('ROLE_MODERATOR')) {
            if ($post->getCreatedBy()) {
                // if user does not own post, or is not a mod
                if ($post->getCreatedBy()->getId() != $user->getId()) {
                    throw new AccessDeniedException('You do not have permission to edit this post!');                	
                }
            } else {
                throw new AccessDeniedException('You do not have permission to edit this post!');
            }
        }

        if ($post->getTopic()->getFirstPost()->getId() == $post->getId()) {
			// if post is the very first post of the topic then use a topic handler so user can change topic title
            $formHandler = $this->container->get('ccdn_forum_forum.form.handler.topic_update')->setDefaultValues(array('post' => $post, 'user' => $user));

            if ($this->isGranted('ROLE_MODERATOR')) {
                $formHandler->setDefaultValues(array('board' => $post->getTopic()->getBoard()));
            }
        } else {
            $formHandler = $this->container->get('ccdn_forum_forum.form.handler.post_update')->setDefaultValues(array('post' => $post, 'user' => $user));
        }

        if (isset($_POST['submit_post'])) {
            if ($formHandler->process()) {	// get posts for determining the page of the edited post
                $topic = $post->getTopic();

                foreach ($topic->getPosts() as $index => $postTest) {
                    if ($post->getId() == $postTest->getId()) {
                        $postsPerPage = $this->container->getParameter('ccdn_forum_forum.topic.show.posts_per_page');
                        $page = ceil($index / $postsPerPage);
                        break;
                    }
                }

                $this->setFlash('success', $this->trans('ccdn_forum_forum.flash.post.edit.success', array('%post_id%' => $postId, '%topic_title%' => $post->getTopic()->getTitle())));

                // redirect user on successful edit.
                return new RedirectResponse($this->path('ccdn_forum_forum_topic_show_paginated_anchored', array('topicId' => $topic->getId(), 'page' => $page, 'postId' => $post->getId() ) ));
            }
        }

        // setup crumb trail.
        $topic = $post->getTopic();
        $board = $topic->getBoard();
        $category = $board->getCategory();

        $crumbs = $this->getCrumbs()
            ->add($this->trans('ccdn_forum_forum.crumbs.forum_index'), $this->path('ccdn_forum_forum_category_index'), "home")
            ->add($category->getName(),	$this->path('ccdn_forum_forum_category_show', array('categoryId' => $category->getId())), "category")
            ->add($board->getName(), $this->path('ccdn_forum_forum_board_show', array('boardId' => $board->getId())), "board")
            ->add($topic->getTitle(), $this->path('ccdn_forum_forum_topic_show', array('topicId' => $topic->getId())), "communication")
            ->add($this->trans('ccdn_forum_forum.crumbs.post.edit') . $post->getId(), $this->path('ccdn_forum_forum_topic_reply', array('topicId' => $topic->getId())), "edit");

        if ($post->getTopic()->getFirstPost()->getId() == $post->getId()) {
			// render edit_topic if first post
            $template = 'CCDNForumForumBundle:Post:edit_topic.html.';
        } else {
            // render edit_post if not first post
            $template = 'CCDNForumForumBundle:Post:edit_post.html.';
        }

        return $this->renderResponse($template, array(
            'user' => $user,
            'board' => $board,
            'topic' => $topic,
            'post' => $post,
            'crumbs' => $crumbs,
            'preview' => $formHandler->getForm()->getData(),
            'form' => $formHandler->getForm()->createView(),
        ));
    }

    /**
     *
     * @access public
     * @param int $postId
     * @return RedirectResponse|RenderResponse
     */
    public function deleteAction($postId)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $post = $this->container->get('ccdn_forum_forum.repository.post')->findPostForEditing($postId);

        $this->isFound($post);

        // if this topics first post is deleted, and no other posts exist then throw an NotFoundHttpException!
        if (($post->getIsDeleted() || $post->getTopic()->getIsDeleted()) && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new NotFoundHttpException('No such post exists!');
        }

		// you cannot reply/edit/delete a post if it is locked		
        if ($post->getIsLocked() && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException('This post has been locked and cannot be edited or deleted!');
        }
		
		// you cannot reply/edit/delete a post if the topic is closed
        if ($post->getTopic()->getIsClosed() && ! $this->isGranted('ROLE_MODERATOR')) {	
            throw new AccessDeniedException('This topic has been closed!');
        }

        // Invalidate this action / redirect if user should not have access to it
        if ( ! $this->isGranted('ROLE_MODERATOR')) {
            // if user does not own post, or is not a mod
            if ($post->getCreatedBy()) {
                if ($post->getCreatedBy()->getId() != $user->getId()) {
                    throw new AccessDeniedException('You do not have permission to use this resource!');
                }
            } else {
                throw new AccessDeniedException('You do not have permission to use this resource!');
            }
        }

        $topic = $post->getTopic();
        $board = $topic->getBoard();
        $category = $board->getCategory();

        if ($post->getTopic()->getFirstPost()->getId() == $post->getId() && $post->getTopic()->getCachedReplyCount() == 0) {
			// if post is the very first post of the topic then use a topic handler so user can change topic title
            $confirmationMessage = 'ccdn_forum_forum.topic.delete_topic_question';
            $crumbDelete = $this->trans('ccdn_forum_forum.crumbs.topic.delete');
            $pageTitle = $this->trans('ccdn_forum_forum.title.topic.delete', array('%topic_title%' => $topic->getTitle()));
        } else {
            $confirmationMessage = 'ccdn_forum_forum.post.delete_post_question';
            $crumbDelete = $this->trans('ccdn_forum_forum.crumbs.post.delete') . $post->getId();
            $pageTitle = $this->trans('ccdn_forum_forum.title.post.delete', array('%post_id%' => $post->getId(), '%topic_title%' => $topic->getTitle()));
        }

        // setup crumb trail.
        $crumbs = $this->getCrumbs()
            ->add($this->trans('ccdn_forum_forum.crumbs.forum_index'), $this->path('ccdn_forum_forum_category_index'), "home")
            ->add($category->getName(),	$this->path('ccdn_forum_forum_category_show', array('categoryId' => $category->getId())), "category")
            ->add($board->getName(), $this->path('ccdn_forum_forum_board_show', array('boardId' => $board->getId())), "board")
            ->add($topic->getTitle(), $this->path('ccdn_forum_forum_topic_show', array('topicId' => $topic->getId())), "communication")
            ->add($crumbDelete, $this->path('ccdn_forum_forum_topic_reply', array('topicId' => $topic->getId())), "trash");

        return $this->renderResponse('CCDNForumForumBundle:Post:delete_post.html.', array(
            'page_title' => $pageTitle,
            'confirmation_message' => $confirmationMessage,
            'topic' => $topic,
            'post' => $post,
            'crumbs' => $crumbs,
        ));
    }

    /**
     *
     * @access public
     * @param int $postId
     * @return RedirectResponse
     */
    public function deleteConfirmedAction($postId)
    {
		$this->isAuthorised('ROLE_USER');

        $user = $this->getUser();

        $post = $this->container->get('ccdn_forum_forum.repository.post')->findPostForEditing($postId);

        $this->isFound($post);

        // if this topics first post is deleted, and no other posts exist then throw an NotFoundHttpException!
        if (($post->getIsDeleted() || $post->getTopic()->getIsDeleted()) && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new NotFoundHttpException('No such post exists!');
        }

        // you cannot reply/edit/delete a post if the topic is closed
        if ($post->getTopic()->getIsClosed() && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException('This topic has been closed!');
        }

        // you cannot reply/edit/delete a post if it is locked
        if ($post->getIsLocked() && ! $this->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException('This post has been locked and cannot be edited or deleted!');
        }

        // Invalidate this action / redirect if user should not have access to it
        if ( ! $this->isGranted('ROLE_MODERATOR')) {
            // if user does not own post, or is not a mod
            if ($post->getCreatedBy()) {
                if ($post->getCreatedBy()->getId() != $user->getId()) {
                    throw new AccessDeniedException('You do not have permission to use this resource!');
                }
            } else {
                throw new AccessDeniedException('You do not have permission to use this resource!');
            }
        }

        $this->getPostManager()->softDelete($post, $user)->flush();

        // set flash message
        $this->setFlash('notice', $this->trans('ccdn_forum_forum.flash.post.delete.success', array('%post_id%' => $postId)));

        // forward user
        return new RedirectResponse($this->path('ccdn_forum_forum_topic_show', array('topicId' => $post->getTopic()->getId()) ));
    }
}

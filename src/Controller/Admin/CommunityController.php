<?php

namespace App\Controller\Admin;

use App\Entity\Community\Post;
use App\Repository\Community\PostRepository;
use App\Repository\Community\CommentaireRepository;
use App\Repository\Community\ReactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/back/community', name: 'admin_community_')]
#[IsGranted('ROLE_ADMIN')]
class CommunityController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PostRepository $postRepo): Response
    {
        $search = (string) $request->query->get('search', '');
        $category = (string) $request->query->get('category', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = 20;

        $posts = $postRepo->searchPosts($search, $category, $page);
        $totalPosts = $postRepo->countPosts($search, $category);
        $totalPages = (int) ceil($totalPosts / $pageSize);

        // Batch fetch comment counts
        $postIds = array_map(static fn ($p) => (int) $p['post_id'], $posts);
        $commentCounts = $postRepo->getCommentCountsByPostIds($postIds);

        return $this->render('back/community/index.html.twig', [
            'posts' => $posts,
            'comment_counts' => $commentCounts,
            'search' => $search,
            'category' => $category,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_posts' => $totalPosts,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, PostRepository $postRepo, CommentaireRepository $commentRepo): Response
    {
        $post = $postRepo->findOneWithAuthor($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }

        $comments = $commentRepo->findByPostIdWithAuthor($id);

        return $this->render('back/community/show.html.twig', [
            'post' => $post,
            'comments' => $comments,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, PostRepository $postRepo, EntityManagerInterface $em): Response
    {
        $post = $postRepo->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $content = $request->request->get('content');
            $category = $request->request->get('category');

            if (empty($title) || empty($content)) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
                return $this->redirectToRoute('admin_community_edit', ['id' => $id]);
            }

            $post->setTitle($title);
            $post->setContent($content);
            $post->setCategory($category ?: 'General');

            $em->flush();
            $this->addFlash('success', 'Post modifié avec succès.');
            return $this->redirectToRoute('admin_community_index');
        }

        return $this->render('back/community/edit.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, PostRepository $postRepo, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $post = $postRepo->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }

        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete_post_' . $id, $token))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_community_index');
        }

        // Delete associated comments
        $commentRepo = $em->getRepository(\App\Entity\Community\Commentaire::class);
        $comments = $commentRepo->findBy(['post' => $post]);
        foreach ($comments as $comment) {
            $em->remove($comment);
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post supprimé avec succès.');
        return $this->redirectToRoute('admin_community_index');
    }
}

<?php

namespace App\Controller\Community;

use App\Entity\Community\Post;
use App\Entity\Community\Commentaire;
use App\Repository\Community\PostRepository;
use App\Repository\Community\CommentaireRepository;
use App\Service\Community\ReactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/community')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PostController extends AbstractController
{
    #[Route('/', name: 'community_index', methods: ['GET'])]
    public function index(Request $request, PostRepository $postRepo, CommentaireRepository $commentRepo, ReactionManager $reactionManager): Response
    {
        $search = (string) $request->query->get('search', '');
        $category = (string) $request->query->get('category', '');
        $posts = $postRepo->searchPosts($search, $category);

        $postIds = array_map(static fn ($p) => (int) $p['post_id'], $posts);
        $countsByPost = $reactionManager->getCountsForPosts($postIds)['counts'];
        $userReactions = $reactionManager->getUserReactionsForPosts($postIds, $this->getUser()->getUserId());

        $reactionEmojis = [
            'LIKE' => '👍',
            'LOVE' => '❤️',
            'CELEBRATE' => '🎉',
            'SUPPORT' => '🤝',
            'INSIGHTFUL' => '💡',
            'CURIOUS' => '🔥',
        ];
        $reactionLabels = [
            'LIKE' => 'Like',
            'LOVE' => 'Love',
            'CELEBRATE' => 'Celebrate',
            'SUPPORT' => 'Support',
            'INSIGHTFUL' => 'Insightful',
            'CURIOUS' => 'Curious',
        ];

        // Pour chaque post, ajouter le nombre de commentaires
        foreach ($posts as &$post) {
            $post['comment_count'] = $commentRepo->countByPostId($post['post_id']);

            $counts = $countsByPost[(int) $post['post_id']] ?? [];
            $filledCounts = array_fill_keys(ReactionManager::TYPES, 0);
            foreach ($counts as $t => $c) {
                $filledCounts[(string) $t] = (int) $c;
            }
            $post['reaction_counts'] = $filledCounts;
            $post['reaction_total'] = array_sum($filledCounts);

            $ur = $userReactions[(int) $post['post_id']] ?? null;
            $post['user_reaction'] = $ur;
            $post['user_reaction_emoji'] = $ur ? ($reactionEmojis[$ur] ?? '👍') : null;
            $post['user_reaction_label'] = $ur ? ($reactionLabels[$ur] ?? $ur) : null;

            $post['reaction_emojis'] = $reactionEmojis;
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('community/_posts.html.twig', [
                'posts' => $posts,
            ]);
        }

        return $this->render('community/index.html.twig', [
            'posts' => $posts,
            'search' => $search,
            'category' => $category,
        ]);
    }

    #[Route('/new', name: 'community_new', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $category = $request->request->get('category');
        $location = $request->request->get('location');
        $locationLat = $request->request->get('location_lat');
        $locationLon = $request->request->get('location_lon');

        if (empty($title) || empty($content)) {
            $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
            return $this->redirectToRoute('community_index');
        }

        $post = new Post();
        $post->setUserId($this->getUser()->getUserId());
        $post->setTitle($title);
        $post->setContent($content);
        $post->setCategory($category ?: 'General');
        $post->setCreatedAt(new \DateTime());
        $post->setLocation($location ? trim((string) $location) : null);
        $post->setLocationLat($locationLat !== null && $locationLat !== '' ? (float) $locationLat : null);
        $post->setLocationLon($locationLon !== null && $locationLon !== '' ? (float) $locationLon : null);

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Post créé avec succès.');
        return $this->redirectToRoute('community_index');
    }

    #[Route('/{id}/edit', name: 'community_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(int $id, Request $request, PostRepository $postRepo, EntityManagerInterface $em): Response
    {
        $post = $postRepo->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }

        // Vérification ownership
        $user = $this->getUser();
        if ($post->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier ce post.');
            return $this->redirectToRoute('community_index');
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $content = $request->request->get('content');
            $category = $request->request->get('category');
            $location = $request->request->get('location');
            $locationLat = $request->request->get('location_lat');
            $locationLon = $request->request->get('location_lon');

            if (empty($title) || empty($content)) {
                $this->addFlash('error', 'Le titre et le contenu sont obligatoires.');
                return $this->redirectToRoute('community_edit', ['id' => $id]);
            }

            $post->setTitle($title);
            $post->setContent($content);
            $post->setCategory($category ?: 'General');
            $post->setLocation($location ? trim((string) $location) : null);
            $post->setLocationLat($locationLat !== null && $locationLat !== '' ? (float) $locationLat : null);
            $post->setLocationLon($locationLon !== null && $locationLon !== '' ? (float) $locationLon : null);
            $em->flush();

            $this->addFlash('success', 'Post modifié avec succès.');
            return $this->redirectToRoute('community_index');
        }

        return $this->render('community/edit.html.twig', [
            'post' => $post,
        ]);
    }



    #[Route('/{id}/delete', name: 'community_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, PostRepository $postRepo, EntityManagerInterface $em): Response
    {
        $post = $postRepo->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }

        $user = $this->getUser();
        if ($post->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer ce post.');
            return $this->redirectToRoute('community_index');
        }

        // Supprimer les commentaires associés manuellement (ou par cascade si configuré)
        $commentRepo = $em->getRepository(Commentaire::class);
        $comments = $commentRepo->findBy(['postId' => $id]);
        foreach ($comments as $comment) {
            $em->remove($comment);
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post supprimé avec succès.');
        return $this->redirectToRoute('community_index');
    }

    #[Route('/{id}', name: 'community_show', methods: ['GET'])]
    public function show(int $id, PostRepository $postRepo, CommentaireRepository $commentRepo, ReactionManager $reactionManager): Response
    {
        $post = $postRepo->findOneWithAuthor($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }
        $comments = $commentRepo->findByPostIdWithAuthor($id);

        $reactionEmojis = [
            'LIKE' => '👍',
            'LOVE' => '❤️',
            'CELEBRATE' => '🎉',
            'SUPPORT' => '🤝',
            'INSIGHTFUL' => '💡',
            'CURIOUS' => '🔥',
        ];
        $reactionLabels = [
            'LIKE' => 'Like',
            'LOVE' => 'Love',
            'CELEBRATE' => 'Celebrate',
            'SUPPORT' => 'Support',
            'INSIGHTFUL' => 'Insightful',
            'CURIOUS' => 'Curious',
        ];

        $counts = $reactionManager->getCountsForPost($id);
        $userReaction = $reactionManager->getUserReactionsForPosts([$id], $this->getUser()->getUserId())[$id] ?? null;

        $post['reaction_counts'] = $counts;
        $post['reaction_total'] = array_sum($counts);
        $post['user_reaction'] = $userReaction;
        $post['user_reaction_emoji'] = $userReaction ? ($reactionEmojis[$userReaction] ?? '👍') : null;
        $post['user_reaction_label'] = $userReaction ? ($reactionLabels[$userReaction] ?? $userReaction) : null;
        $post['reaction_emojis'] = $reactionEmojis;

        return $this->render('community/show.html.twig', [
            'post' => $post,
            'comments' => $comments,
        ]);
    }

    #[Route('/comment/{postId}/new', name: 'community_comment_new', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentNew(int $postId, Request $request, EntityManagerInterface $em): Response
    {
        $content = $request->request->get('content');
        if (empty($content)) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('community_show', ['id' => $postId]);
        }

        $comment = new Commentaire();
        $comment->setPostId($postId);
        $comment->setUserId($this->getUser()->getUserId());
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTime());

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajouté.');
        return $this->redirectToRoute('community_show', ['id' => $postId]);
    }

    #[Route('/comment/{id}/edit', name: 'community_comment_edit', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentEdit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $comment = $em->getRepository(Commentaire::class)->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        $user = $this->getUser();
        if ($comment->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier ce commentaire.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPostId()]);
        }

        $newContent = $request->request->get('content');
        if (empty($newContent)) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPostId()]);
        }

        $comment->setContent($newContent);
        $em->flush();

        $this->addFlash('success', 'Commentaire modifié.');
        return $this->redirectToRoute('community_show', ['id' => $comment->getPostId()]);
    }

    #[Route('/comment/{id}/delete', name: 'community_comment_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentDelete(int $id, EntityManagerInterface $em): Response
    {
        $comment = $em->getRepository(Commentaire::class)->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        $user = $this->getUser();
        if ($comment->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer ce commentaire.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPostId()]);
        }

        $postId = $comment->getPostId();
        $em->remove($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire supprimé.');
        return $this->redirectToRoute('community_show', ['id' => $postId]);
    }

    #[Route('/{id}/react', name: 'community_react', methods: ['POST'])]
    public function react(
        int $id,
        Request $request,
        ReactionManager $reactionManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?: [];
        $type = (string) ($payload['type'] ?? '');
        $tokenValue = (string) ($payload['_token'] ?? '');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('community_react_' . $id, $tokenValue))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        try {
            $result = $reactionManager->toggleReaction($id, $this->getUser()->getUserId(), $type);
            return new JsonResponse($result);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Invalid reaction type'], 400);
        }
    }

    #[Route('/location-search', name: 'community_location_search', methods: ['GET'])]
    public function locationSearch(Request $request, HttpClientInterface $http): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 3) {
            return new JsonResponse([]);
        }

        $url = 'https://nominatim.openstreetmap.org/search';
        $resp = $http->request('GET', $url, [
            'query' => [
                'q' => $q,
                'format' => 'json',
                'limit' => 5,
            ],
            'headers' => [
                // Nominatim policy: must send a valid User-Agent / Referer
                'User-Agent' => 'BizHub-Community/1.0 (Symfony)',
                'Accept' => 'application/json',
            ],
        ]);

        return new JsonResponse($resp->toArray(false));
    }
}

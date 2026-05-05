<?php

namespace App\Controller\Community;

use App\Entity\Community\Post;
use App\Entity\Community\Commentaire;
use App\Entity\UsersAvis\User;
use App\Repository\Community\PostRepository;
use App\Repository\Community\CommentaireRepository;
use App\Service\Community\ReactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/community')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PostController extends AbstractController
{
    private const COMMUNITY_UPLOAD_DIR = 'uploads/community';

    private function requireAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }

    #[Route('/', name: 'community_index', methods: ['GET'])]
    public function index(Request $request, PostRepository $postRepo, CommentaireRepository $commentRepo, ReactionManager $reactionManager): Response
    {
        $user = $this->requireAppUser();

        $search = (string) $request->query->get('search', '');
        $category = (string) $request->query->get('category', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $posts = $postRepo->searchPosts($search, $category, $page);

        $postIds = array_map(static fn ($p) => (int) $p['post_id'], $posts);

        // Batch fetch comment counts (fix N+1)
        $commentCountsByPost = $postRepo->getCommentCountsByPostIds($postIds);

        $countsByPost = $reactionManager->getCountsForPosts($postIds)['counts'];
        $userReactions = $reactionManager->getUserReactionsForPosts($postIds, $user->getUserId());

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

        foreach ($posts as &$post) {
            $post['comment_count'] = $commentCountsByPost[(int) $post['post_id']] ?? 0;

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

        $totalPosts = $postRepo->countPosts($search, $category);
        $totalPages = (int) ceil($totalPosts / 20);

        if ($request->isXmlHttpRequest()) {
            return $this->render('community/_posts.html.twig', [
                'posts' => $posts,
                'page' => $page,
                'total_pages' => $totalPages,
                'search' => $search,
                'category' => $category,
            ]);
        }

        return $this->render('community/index.html.twig', [
            'posts' => $posts,
            'search' => $search,
            'category' => $category,
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'community_new', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requireAppUser();

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

        $postMedia = $request->files->get('media') ?? $request->files->get('image') ?? $request->files->get('video');

        $post = new Post();
        $post->setUser($user);
        $post->setTitle($title);
        $post->setContent($content);
        $post->setCategory($category ?: 'General');
        $post->setLocation($location ? trim((string) $location) : null);
        $post->setLocationLat($locationLat !== null && trim((string) $locationLat) !== '' ? trim((string) $locationLat) : null);
        $post->setLocationLon($locationLon !== null && trim((string) $locationLon) !== '' ? trim((string) $locationLon) : null);
        if ($postMedia instanceof UploadedFile) {
            $publicPath = $this->storeCommunityMedia($postMedia);
            if ($publicPath !== null) {
                $post->setMediaUrl($publicPath);
                $post->setMediaType((string) ($postMedia->getClientMimeType() ?: 'application/octet-stream'));
            }
        }

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
        $user = $this->requireAppUser();
        if ($post->getUser()?->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
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
            $post->setLocationLat($locationLat !== null && trim((string) $locationLat) !== '' ? trim((string) $locationLat) : null);
            $post->setLocationLon($locationLon !== null && trim((string) $locationLon) !== '' ? trim((string) $locationLon) : null);
            $postMedia = $request->files->get('media') ?? $request->files->get('image') ?? $request->files->get('video');
            if ($postMedia instanceof UploadedFile) {
                $publicPath = $this->storeCommunityMedia($postMedia);
                if ($publicPath !== null) {
                    $post->setMediaUrl($publicPath);
                    $post->setMediaType((string) ($postMedia->getClientMimeType() ?: 'application/octet-stream'));
                }
            }
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

        $user = $this->requireAppUser();
        if ($post->getUser()?->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer ce post.');
            return $this->redirectToRoute('community_index');
        }

        // Supprimer les commentaires associés manuellement (ou par cascade si configuré)
        $commentRepo = $em->getRepository(Commentaire::class);
        $comments = $commentRepo->findBy(['post' => $post]);
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
        $user = $this->requireAppUser();

        $post = $postRepo->findOneWithAuthor($id);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé');
        }
        $comments = $commentRepo->findByPostIdWithAuthor($id);
        foreach ($comments as &$comment) {
            $parsedComment = $this->extractCommentImage((string) ($comment['content'] ?? ''));
            $comment['content_text'] = $parsedComment['text'];
            $comment['image_url'] = $parsedComment['image'];
        }
        unset($comment);

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
    $userReaction = $reactionManager->getUserReactionsForPosts([$id], $user->getUserId())[$id] ?? null;

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
        $user = $this->requireAppUser();

        $post = $em->getRepository(Post::class)->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Post non trouvé.');
        }

        $content = trim((string) $request->request->get('content', ''));
        $commentImage = $request->files->get('image');
        if ($content === '' && !($commentImage instanceof UploadedFile)) {
            $this->addFlash('error', 'Le commentaire ou une image est obligatoire.');
            return $this->redirectToRoute('community_show', ['id' => $postId]);
        }

        if ($commentImage instanceof UploadedFile) {
            $publicPath = $this->storeCommunityMedia($commentImage, false);
            if ($publicPath !== null) {
                $content = $this->injectCommentImage($content, $publicPath);
            }
        }

        $comment = new Commentaire();
        $comment->setPost($post);
        $comment->setContent($content);
    $comment->setUser($user);

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajouté.');
        return $this->redirectToRoute('community_show', ['id' => $postId]);
    }

    #[Route('/comment/{id}/edit', name: 'community_comment_edit', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentEdit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->requireAppUser();

        $comment = $em->getRepository(Commentaire::class)->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        if ($comment->getUser()?->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier ce commentaire.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPost()?->getId()]);
        }

        $newContent = trim((string) $request->request->get('content', ''));
        $commentImage = $request->files->get('image');
        $existingParsed = $this->extractCommentImage((string) $comment->getContent());
        $existingImage = $existingParsed['image'];

        if ($commentImage instanceof UploadedFile) {
            $newImagePath = $this->storeCommunityMedia($commentImage, false);
            if ($newImagePath !== null) {
                $existingImage = $newImagePath;
            }
        }

        if ($newContent === '' && $existingImage === null) {
            $this->addFlash('error', 'Le commentaire ou une image est obligatoire.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPost()?->getId()]);
        }

        $comment->setContent($this->injectCommentImage($newContent, $existingImage));
        $em->flush();

        $this->addFlash('success', 'Commentaire modifié.');
        return $this->redirectToRoute('community_show', ['id' => $comment->getPost()?->getId()]);
    }

    #[Route('/comment/{id}/delete', name: 'community_comment_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentDelete(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->requireAppUser();

        $comment = $em->getRepository(Commentaire::class)->find($id);
        if (!$comment) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        if ($comment->getUser()?->getUserId() !== $user->getUserId() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer ce commentaire.');
            return $this->redirectToRoute('community_show', ['id' => $comment->getPost()?->getId()]);
        }

        $postId = $comment->getPost()?->getId();
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
        $user = $this->requireAppUser();

        $payload = json_decode($request->getContent(), true) ?: [];
        $type = (string) ($payload['type'] ?? '');
        $tokenValue = (string) ($payload['_token'] ?? '');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('community_react_' . $id, $tokenValue))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        try {
            $result = $reactionManager->toggleReaction($id, $user->getUserId(), $type);
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

    private function storeCommunityMedia(UploadedFile $file, bool $allowVideo = true): ?string
    {
        $mime = (string) $file->getClientMimeType();
        $isImage = strpos($mime, 'image/') === 0;
        $isVideo = strpos($mime, 'video/') === 0;
        if (!$isImage && !($allowVideo && $isVideo)) {
            return null;
        }

        $ext = strtolower((string) $file->guessExtension());
        if ($isImage && !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }
        if ($isVideo && !in_array($ext, ['mp4', 'webm', 'ogg'], true)) {
            $ext = 'mp4';
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/' . self::COMMUNITY_UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $name = 'community_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $file->move($uploadDir, $name);

        return '/' . self::COMMUNITY_UPLOAD_DIR . '/' . $name;
    }

    private function extractCommentImage(string $content): array
    {
        $image = null;
        if (preg_match('/\[img\](.*?)\[\/img\]/s', $content, $m) === 1) {
            $image = trim((string) ($m[1] ?? '')) ?: null;
        }

        $text = trim((string) preg_replace('/\[img\].*?\[\/img\]/s', '', $content));
        return ['text' => $text, 'image' => $image];
    }

    private function injectCommentImage(string $text, ?string $image): string
    {
        $clean = trim((string) preg_replace('/\[img\].*?\[\/img\]/s', '', $text));
        if ($image === null || trim($image) === '') {
            return $clean;
        }

        if ($clean === '') {
            return '[img]' . $image . '[/img]';
        }

        return $clean . "\n\n" . '[img]' . $image . '[/img]';
    }
}
